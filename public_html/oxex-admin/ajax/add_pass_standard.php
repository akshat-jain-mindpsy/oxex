<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Only allow authorized admins
if (login_check($mysqli) !== true || ($admintype !== 'AT' && $admintype !== 'DV')) {
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
        if (!empty($rule['subfield_values']) && !empty($rule['requirement_type']) && !empty($rule['specific_value'])) {
            $processed_rules[] = [
                'subfield_value' => $rule['subfield_values'], // Single value now
                'requirement_type' => $rule['requirement_type'],
                'specific_value' => $rule['specific_value']
            ];
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
    $parent_stmt = $mysqli->prepare("SELECT tbid FROM pass_standards WHERE psid = ?");
    $parent_stmt->bind_param("i", $parent_standard_id);
    $parent_stmt->execute();
    $parent_res = $parent_stmt->get_result();
    $parent_row = $parent_res->fetch_assoc();
    $parent_stmt->close();
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
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = $mysqli->prepare($sql)) {
    // Types: s i i s i s i i s i
    $stmt->bind_param("siisisisisi", 
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
    );
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'New pass standard added successfully.'];
            $response['status'] = 'success';
            $response['message'] = 'New pass standard added successfully.';
            $response['new_id'] = $stmt->insert_id;
        } else {
            $response['message'] = 'Failed to add the new standard. No rows were affected.';
        }
    } else {
        $response['message'] = 'Database execution failed: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'Database prepare statement failed: ' . $mysqli->error;
}

$mysqli->close();
echo json_encode($response); 