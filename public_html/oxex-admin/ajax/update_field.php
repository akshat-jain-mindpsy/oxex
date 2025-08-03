<?php
header('Content-Type: application/json');

include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Ensure this script is accessed via AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Check user permissions
if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    http_response_code(403); // Forbidden
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

// Get and validate POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['field_id']) || !isset($input['field_name']) || !isset($input['field_type'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields']));
}

$field_id = intval($input['field_id']);
$field_name = trim($input['field_name']);
$field_type = trim($input['field_type']);

// Validate input
if (empty($field_name)) {
    die(json_encode(['status' => 'error', 'message' => 'Field name cannot be empty']));
}

// Check if field exists
$check_field = $mysqli->prepare("SELECT stid FROM select_types WHERE stid = ?");
$check_field->bind_param("i", $field_id);
$check_field->execute();
$field_result = $check_field->get_result();

if ($field_result->num_rows === 0) {
    die(json_encode(['status' => 'error', 'message' => 'Field not found']));
}

// Update the field
try {
    // Update the field in select_types
    $update_field = $mysqli->prepare("UPDATE select_types SET str = ?, type = ? WHERE stid = ?");
    $update_field->bind_param("ssi", $field_name, $field_type, $field_id);
    $update_field->execute();
    
    if ($update_field->affected_rows === 0) {
        die(json_encode(['status' => 'error', 'message' => 'No changes were made']));
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Field updated successfully',
        'field_id' => $field_id
    ]);
    
} catch (Exception $e) {
    error_log("Error updating field: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?> 