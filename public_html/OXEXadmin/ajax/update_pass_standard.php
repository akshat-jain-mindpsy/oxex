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
$psid = (int)($_POST['psid'] ?? 0);
$standard_name = trim($_POST['standard_name'] ?? '');
$tbid = (int)($_POST['tbid'] ?? 0);
$requirement_type = trim($_POST['requirement_type'] ?? '');
$required_value = (int)($_POST['required_value'] ?? 0);
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Handle nullable fields: stid and field_value
$stid = !empty($_POST['stid']) ? (int)$_POST['stid'] : null;
$field_value = !empty($_POST['field_value']) ? trim($_POST['field_value']) : null;

// Basic validation
if ($psid === 0 || empty($standard_name) || $tbid === 0 || empty($requirement_type)) {
    $response['message'] = 'Invalid data provided. Please fill in all required fields.';
    echo json_encode($response);
    exit;
}

$date_modified = time();
// The $usrkey variable comes from the included 'incl/sess.php' file

$sql = "UPDATE pass_standards SET
            standard_name = ?,
            tbid = ?,
            stid = ?,
            requirement_type = ?,
            required_value = ?,
            field_value = ?,
            is_active = ?,
            who_by = ?,
            date_modified = ?
        WHERE psid = ?";

if ($stmt = $mysqli->prepare($sql)) {
    // The type string 'siisisisii' corresponds to the data types
    $stmt->bind_param("siisisisii", 
        $standard_name, 
        $tbid, 
        $stid, 
        $requirement_type, 
        $required_value, 
        $field_value, 
        $is_active, 
        $usrkey, 
        $date_modified,
        $psid
    );
    
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Pass standard updated successfully.'];
        $response['status'] = 'success';
        $response['message'] = 'Pass standard updated successfully.';
    } else {
        $response['message'] = 'Database execution failed: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'Database prepare statement failed: ' . $mysqli->error;
}

$mysqli->close();
echo json_encode($response); 