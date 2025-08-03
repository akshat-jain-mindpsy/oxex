<?php
// Include database connection and any required configuration files
include_once '../../OXEXfolder/config.php';
include_once '../../OXEXfolder/u_functions.php';
sec_session_start();

// Set response header to JSON
header('Content-Type: application/json');

// Default response
$response = [
    'status' => 'error',
    'message' => 'An unexpected error occurred'
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Check login and permissions
if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit;
}

// Check if required parameters exist
if (!isset($_POST['field_id']) || !isset($_POST['field_name'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

$field_id = intval($_POST['field_id']);
$field_name = trim($_POST['field_name']);

// Validate field ID
if ($field_id <= 0) {
    $response['message'] = 'Invalid field ID';
    echo json_encode($response);
    exit;
}

// Validate field name
if (empty($field_name)) {
    $response['message'] = 'Field name cannot be empty';
    echo json_encode($response);
    exit;
}

try {
    // Update field name in the 'select_types' table instead of 'structure'
    $stmt = $mysqli->prepare("UPDATE select_types SET str = ? WHERE stid = ?");
    $stmt->bind_param("si", $field_name, $field_id);
    $result = $stmt->execute();
    
    if ($result) {
        $response = [
            'status' => 'success',
            'message' => 'Field name updated successfully',
            'field_id' => $field_id,
            'field_name' => $field_name
        ];
    } else {
        $response['message'] = 'Failed to update field: ' . $mysqli->error;
    }
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return response
echo json_encode($response);
exit;
?> 