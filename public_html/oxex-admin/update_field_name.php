<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check if user is logged in and has appropriate permissions
if (login_check($mysqli) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get parameters
$stid = isset($_POST['stid']) ? (int)$_POST['stid'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';

// Validate parameters
if ($stid <= 0 || empty($name)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid parameters'
    ]);
    exit;
}

// Update field name in database
$stmt = $mysqli->prepare("UPDATE select_types SET str = ? WHERE stid = ? LIMIT 1");
$stmt->bind_param("si", $name, $stid);
$stmt->execute();

if ($stmt->affected_rows > 0 || $mysqli->errno === 0) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Field name updated successfully'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update field name: ' . $mysqli->error
    ]);
}

$stmt->close();
?> 