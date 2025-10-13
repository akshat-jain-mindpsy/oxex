<?php
header('Content-Type: application/json');

// Include necessary configuration and database connection
require_once('../config.php');
include '../OXEXfolder/u_functions.php';
sec_session_start();

// Check user permissions
if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csvFile'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['csvFile'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
    exit;
}

// Check file type
$fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($fileType !== 'csv') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only CSV files are allowed.']);
    exit;
}

// Define upload directory
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/public_html/oxex-admin/uploads/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$filename = uniqid('csv_') . '.csv';
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Log the file upload
    error_log("CSV file uploaded: $filename by $adminname");

    echo json_encode([
        'status' => 'success', 
        'message' => 'CSV file uploaded successfully',
        'filename' => $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to move uploaded file'
    ]);
}
exit;
?> 