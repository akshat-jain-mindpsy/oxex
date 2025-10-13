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
$check_template = $supabase_pdo->prepare("SELECT id, template_name FROM csv_templates WHERE id = ?");
$check_template->execute([$template_id]);
$template_row = $check_template->fetch(PDO::FETCH_ASSOC);

if (!$template_row) {
    die(json_encode(['status' => 'error', 'message' => 'Template not found']));
}

$template_name = $template_row['template_name'];

// Begin transaction for safe deletion
$supabase_pdo->beginTransaction();

try {
    // First, delete all columns associated with this template
    $delete_columns = $supabase_pdo->prepare("DELETE FROM csv_template_columns WHERE template_id = ?");
    $delete_columns->execute([$template_id]);
    
    // Log how many columns were deleted
    $columns_deleted = $delete_columns->rowCount();
    
    // Then delete the template itself
    $delete_template = $supabase_pdo->prepare("DELETE FROM csv_templates WHERE id = ?");
    $delete_template->execute([$template_id]);
    
    if ($delete_template->rowCount() === 0) {
        // Rollback if template deletion failed
        $supabase_pdo->rollBack();
        die(json_encode(['status' => 'error', 'message' => 'Failed to delete template']));
    }
    
    // Commit transaction if everything worked
    $supabase_pdo->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => "Template '{$template_name}' and {$columns_deleted} associated fields deleted successfully"
    ]);
    
} catch (Exception $e) {
    // Rollback on any error
    if ($supabase_pdo->inTransaction()) {
        $supabase_pdo->rollBack();
    }
    error_log("Error deleting template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 