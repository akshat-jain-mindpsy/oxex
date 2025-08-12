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
        // If field_value already exists, append subfield rules
        if (!empty($field_value)) {
            $field_value .= ' | SUBFIELD_RULES:' . json_encode($processed_rules);
        } else {
            $field_value = 'SUBFIELD_RULES:' . json_encode($processed_rules);
        }
    }
}

// Basic validation
if (empty($standard_name) || $tbid === 0 || empty($requirement_type)) {
    $response['message'] = 'Please fill in all required fields: Name, Table, and Requirement Type.';
    echo json_encode($response);
    exit;
}

$date_added = time();
// The $usrkey variable comes from the included 'incl/sess.php' file

$sql = "INSERT INTO pass_standards 
            (standard_name, tbid, stid, requirement_type, required_value, field_value, is_active, who_by, date_added) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = $mysqli->prepare($sql)) {
    // The type string 'siisisisii' corresponds to the data types:
    // s: standard_name, i: tbid, i: stid, s: requirement_type, i: required_value, 
    // s: field_value, i: subfield_id, i: is_active, s: who_by, i: date_added
            $stmt->bind_param("siisisisi", 
            $standard_name,
            $tbid,
            $stid,
            $requirement_type,
            $required_value,
            $field_value,
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