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

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['filename']) || !isset($input['data'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing filename or data']);
    exit;
}

// Sanitize filename
$filename = basename($input['filename']);
$filepath = $_SERVER['DOCUMENT_ROOT'] . '/public_html/oxex-admin/' . $filename;

try {
    // Attempt to write the CSV data
    $result = file_put_contents($filepath, $input['data']);

    if ($result !== false) {
        // Log the file update
        error_log("CSV file updated: $filename by $adminname");

        echo json_encode([
            'status' => 'success', 
            'message' => 'CSV file saved successfully',
            'bytes_written' => $result
        ]);
    } else {
        throw new Exception('Failed to write file');
    }
} catch (Exception $e) {
    // Log the error
    error_log("CSV Save Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to save CSV file: ' . $e->getMessage()
    ]);
}
exit;
?> 