<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';
require_once '../../OXEXfolder/PassStandard/autoload.php';

// Only allow authorized admins
if (login_check($pdo) !== true || ($admintype !== 'AT' && $admintype !== 'DV')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Not authorized.']);
    exit;
}

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

try {
    // Use the service layer to build and validate the DTO
    $service = new PassStandardService();
    $dto = $service->buildDtoFromRequest($_POST);
    $service->validate($dto);
    
    // Convert DTO to legacy field_value format for storage
    $field_value = $service->dtoToFieldValue($dto);
    
    // Handle simple field_value (non-JSON strings like "18-64")
    if (empty($field_value) && !empty($_POST['field_value']) && strpos($_POST['field_value'], '{') !== 0 && strpos($_POST['field_value'], 'SUBFIELD_RULES:') !== 0) {
        $field_value = trim($_POST['field_value']);
    }
    
    // Determine stid (for backward compatibility with existing code)
    $stid = null;
    if (!empty($dto->rules)) {
        // Try to derive stid from the first rule's subfield_value (pid)
        $firstPid = null;
        foreach ($dto->rules as $r) { if (!empty($r->subfield_value)) { $firstPid = (int)$r->subfield_value; break; } }
        if ($firstPid) {
            $q = $supabase_pdo->prepare('SELECT stid FROM select_gen WHERE pid = ?');
            if ($q && $q->execute([$firstPid])) {
                $rowSt = $q->fetch(PDO::FETCH_ASSOC);
                if ($rowSt && !empty($rowSt['stid'])) { $stid = (int)$rowSt['stid']; }
            }
        }
    } elseif (!empty($dto->categoryGroups)) {
        // Category groups mode - stid is null
        $stid = null;
    } elseif (!empty($_POST['stid'])) {
        $stid = (int)$_POST['stid'];
    } elseif (!empty($_POST['stids']) && is_array($_POST['stids']) && count($_POST['stids']) > 0) {
        // Multiple fields (OR condition) - stid is null
        $stid = null;
    }
    
    // If a parent is provided, ensure parent exists and is in the same table
    if (!is_null($dto->parent_standard_id)) {
        $parent_stmt = $supabase_pdo->prepare("SELECT tbid FROM pass_standards WHERE psid = ?");
        $parent_stmt->execute([$dto->parent_standard_id]);
        $parent_row = $parent_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$parent_row) {
            throw new ValidationException('Selected parent standard does not exist.');
        }
        if ((int)$parent_row['tbid'] !== (int)$dto->tbid) {
            throw new ValidationException('Parent standard must belong to the same table.');
        }
    }
    
    $date_added = time();
    // The $usrkey variable comes from the included 'incl/sess.php' file
    
    $sql = "INSERT INTO pass_standards 
                (standard_name, tbid, stid, requirement_type, required_value, minimum_threshold, field_value, parent_standard_id, is_active, who_by, date_added) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING psid";
    
    $stmt = $supabase_pdo->prepare($sql);
    // Ensure integer fields are properly converted (null instead of empty string)
    $stid_param = ($stid !== null && $stid !== '') ? (int)$stid : null;
    $parent_id_param = ($dto->parent_standard_id !== null && $dto->parent_standard_id !== '') ? (int)$dto->parent_standard_id : null;
    $tbid_param = (int)$dto->tbid; // tbid is required, should always have a value
    $required_value_param = (int)$dto->required_value; // required_value is required
    $minimum_threshold_param = ($dto->minimum_threshold !== null && $dto->minimum_threshold !== '') ? (float)$dto->minimum_threshold : null;
    
    if ($stmt->execute([
        $dto->standard_name,
        $tbid_param,
        $stid_param,
        $dto->requirement_type,
        $required_value_param,
        $minimum_threshold_param,
        $field_value,
        $parent_id_param,
        $dto->is_active ? 1 : 0,
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
            $fallback->execute([$dto->standard_name, $tbid_param, $usrkey, $date_added]);
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
            $response['psid'] = $new_id;
        } else {
            throw new Exception('Added, but failed to retrieve new ID. Please refresh the list.');
        }
    } else {
        throw new Exception('Database execution failed.');
    }
} catch (ValidationException $ex) {
    $response['message'] = $ex->getMessage();
    echo json_encode($response);
    exit;
} catch (Exception $ex) {
    error_log('ADD_PASS_STANDARD Error: ' . $ex->getMessage());
    $response['message'] = $ex->getMessage();
    echo json_encode($response);
    exit;
}

echo json_encode($response); 