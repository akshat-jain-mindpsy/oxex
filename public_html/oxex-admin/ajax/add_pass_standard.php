<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Only allow authorized admins
if (login_check($pdo) !== true || ($admintype !== 'AT' && $admintype !== 'DV')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Not authorized.']);
    exit;
}

header('Content-Type: application/json');

// Log the incoming request for debugging
error_log("ADD_PASS_STANDARD: Request received - " . json_encode($_POST));

$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Get and sanitize data from POST request
$standard_name = trim($_POST['standard_name'] ?? '');
$tbid = (int)($_POST['tbid'] ?? 0);
$requirement_type = trim($_POST['requirement_type'] ?? '');
$required_value = (int)($_POST['required_value'] ?? 0);
$is_active = isset($_POST['is_active']) ? 1 : 0;
// Parent standard (optional)
$parent_standard_id = isset($_POST['parent_standard_id']) && $_POST['parent_standard_id'] !== '' ? (int)$_POST['parent_standard_id'] : null;

// Handle nullable fields: stid and field_value
$stid = !empty($_POST['stid']) ? (int)$_POST['stid'] : null;
$field_value = !empty($_POST['field_value']) ? trim($_POST['field_value']) : null;

// Handle subfield rules - store as JSON in field_value
$subfield_rules = isset($_POST['subfield_rules']) ? $_POST['subfield_rules'] : [];
if (!empty($subfield_rules) && is_array($subfield_rules)) {
    // Process subfield rules and store as JSON
    $processed_rules = [];
    foreach ($subfield_rules as $rule) {
        // Support OR groups via any_of[]
        if (!empty($rule['any_of']) && is_array($rule['any_of'])) {
            $group = [];
            foreach ($rule['any_of'] as $alt) {
                if (!empty($alt['subfield_value']) && !empty($alt['requirement_type']) && !empty($alt['specific_value'])) {
                    $alt_rule = [
                        'subfield_value' => $alt['subfield_value'],
                        'requirement_type' => $alt['requirement_type'],
                        'specific_value' => $alt['specific_value']
                    ];
                    if (!empty($alt['minimum_threshold'])) {
                        $alt_rule['minimum_threshold'] = $alt['minimum_threshold'];
                    }
                    $group[] = $alt_rule;
                }
            }
            if (!empty($group)) {
                $processed_rules[] = [ 'any_of' => $group ];
            }
        } elseif (!empty($rule['subfield_values']) && !empty($rule['requirement_type']) && !empty($rule['specific_value'])) {
            $processed_rule = [
                'subfield_value' => $rule['subfield_values'], // Single value now
                'requirement_type' => $rule['requirement_type'],
                'specific_value' => $rule['specific_value']
            ];
            if (!empty($rule['minimum_threshold'])) {
                $processed_rule['minimum_threshold'] = $rule['minimum_threshold'];
            }
            $processed_rules[] = $processed_rule;
        }
    }
    
    if (!empty($processed_rules)) {
        // Always replace subfield rules - don't append
        $field_value = 'SUBFIELD_RULES:' . json_encode($processed_rules);
    }
}

// Basic validation
if (empty($standard_name) || $tbid === 0 || empty($requirement_type)) {
    $response['message'] = 'Please fill in all required fields: Name, Table, and Requirement Type.';
    echo json_encode($response);
    exit;
}

// If a parent is provided, ensure parent exists and is in the same table
if (!is_null($parent_standard_id)) {
    $parent_stmt = $supabase_pdo->prepare("SELECT tbid FROM pass_standards WHERE psid = ?");
    $parent_stmt->execute([$parent_standard_id]);
    $parent_row = $parent_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$parent_row) {
        $response['message'] = 'Selected parent standard does not exist.';
        echo json_encode($response);
        exit;
    }
    if ((int)$parent_row['tbid'] !== (int)$tbid) {
        $response['message'] = 'Parent standard must belong to the same table.';
        echo json_encode($response);
        exit;
    }
}

$date_added = time();
// The $usrkey variable comes from the included 'incl/sess.php' file

$sql = "INSERT INTO pass_standards 
            (standard_name, tbid, stid, requirement_type, required_value, field_value, parent_standard_id, is_active, who_by, date_added) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING psid";

$stmt = $supabase_pdo->prepare($sql);
if ($stmt->execute([
    $standard_name,
    $tbid,
    $stid,
    $requirement_type,
    $required_value,
    $field_value,
    $parent_standard_id,
    $is_active,
    $usrkey,
    $date_added
])) {
    // Fetch RETURNING psid without relying on rowCount (unreliable for SELECT/RETURNING)
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_id = $result['psid'] ?? null;

    // Fallback 1a: try PDO lastInsertId with known sequence name
    if (empty($new_id)) {
        try {
            $liid = $supabase_pdo->lastInsertId('pass_standards_psid_seq');
            if (!empty($liid)) {
                $new_id = (int)$liid;
            }
        } catch (Exception $e) {
            // ignore and try next fallback
        }
    }

    // Fallback 1b: try sequence currval for this table/column (same session)
    if (empty($new_id)) {
        try {
            $seqStmt = $supabase_pdo->query("SELECT currval(pg_get_serial_sequence('pass_standards','psid')) AS psid");
            $seqRow = $seqStmt ? $seqStmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($seqRow && !empty($seqRow['psid'])) {
                $new_id = (int)$seqRow['psid'];
            }
        } catch (Exception $e) {
            // ignore and try next fallback
        }
    }

    // Fallback 2: in case RETURNING/currval failed, try to resolve deterministically
    if (empty($new_id)) {
        try {
            $fallback = $supabase_pdo->prepare(
                "SELECT psid FROM pass_standards 
                 WHERE standard_name = ? AND tbid = ? AND who_by = ? AND date_added = ?
                 ORDER BY date_added DESC, psid DESC LIMIT 1"
            );
            $fallback->execute([$standard_name, $tbid, $usrkey, $date_added]);
            $fb = $fallback->fetch(PDO::FETCH_ASSOC);
            if ($fb && !empty($fb['psid'])) {
                $new_id = (int)$fb['psid'];
            }
        } catch (Exception $e) {
            // ignore, will surface generic message below if still empty
        }
    }

    if (!empty($new_id)) {
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'New pass standard added successfully.'];
        $response['status'] = 'success';
        $response['message'] = 'New pass standard added successfully.';
        $response['new_id'] = $new_id;
    } else {
        $response['message'] = 'Added, but failed to retrieve new ID. Please refresh the list.';
    }
} else {
    $response['message'] = 'Database execution failed.';
}

echo json_encode($response); 