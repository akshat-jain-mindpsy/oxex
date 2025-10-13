<?php
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
if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    http_response_code(403); // Forbidden
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

// Get and validate POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['template_id']) || !isset($input['table_id']) || !isset($input['field_id'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields']));
}

$template_id = intval($input['template_id']);
$table_id = intval($input['table_id']);
$field_id = intval($input['field_id']);

// Validate input
if ($template_id <= 0 || $table_id <= 0 || $field_id <= 0) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid input values']));
}

// Remove the field from the template
try {
    $remove_field = $supabase_pdo->prepare("DELETE FROM csv_template_columns WHERE template_id = ? AND table_id = ? AND field_id = ?");
    $remove_field->execute([$template_id, $table_id, $field_id]);
    
    if ($remove_field->rowCount() === 0) {
        die(json_encode(['status' => 'error', 'message' => 'Field not found in template or already removed']));
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Field removed from template successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error removing field from template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} 