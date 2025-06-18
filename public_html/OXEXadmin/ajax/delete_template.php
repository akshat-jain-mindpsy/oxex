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
if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    http_response_code(403); // Forbidden
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

// Get and validate POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['template_id'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing template ID']));
}

$template_id = intval($input['template_id']);

// Validate input
if ($template_id <= 0) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid template ID']));
}

// Check if template exists
$check_template = $mysqli->prepare("SELECT id, template_name FROM csv_templates WHERE id = ?");
$check_template->bind_param("i", $template_id);
$check_template->execute();
$template_result = $check_template->get_result();

if ($template_result->num_rows === 0) {
    die(json_encode(['status' => 'error', 'message' => 'Template not found']));
}

$template = $template_result->fetch_assoc();
$template_name = $template['template_name'];

// Begin transaction for safe deletion
$mysqli->begin_transaction();

try {
    // First, delete all columns associated with this template
    $delete_columns = $mysqli->prepare("DELETE FROM csv_template_columns WHERE template_id = ?");
    $delete_columns->bind_param("i", $template_id);
    $delete_columns->execute();
    
    // Log how many columns were deleted
    $columns_deleted = $delete_columns->affected_rows;
    
    // Then delete the template itself
    $delete_template = $mysqli->prepare("DELETE FROM csv_templates WHERE id = ?");
    $delete_template->bind_param("i", $template_id);
    $delete_template->execute();
    
    if ($delete_template->affected_rows === 0) {
        // Rollback if template deletion failed
        $mysqli->rollback();
        die(json_encode(['status' => 'error', 'message' => 'Failed to delete template']));
    }
    
    // Commit transaction if everything worked
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => "Template '{$template_name}' and {$columns_deleted} associated fields deleted successfully"
    ]);
    
} catch (Exception $e) {
    // Rollback on any error
    $mysqli->rollback();
    error_log("Error deleting template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?> 