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

if (!isset($input['template_id']) || !isset($input['table_id'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields']));
}

$template_id = intval($input['template_id']);
$table_id = intval($input['table_id']);

// Validate input
if ($template_id <= 0 || $table_id <= 0) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid input values']));
}

// Remove the table and all its fields from the template
try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Remove all fields for this table from the template
    $remove_fields = $mysqli->prepare("DELETE FROM csv_template_columns WHERE template_id = ? AND table_id = ?");
    $remove_fields->bind_param("ii", $template_id, $table_id);
    $remove_fields->execute();
    
    $rows_affected = $remove_fields->affected_rows;
    
    // Commit the transaction
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Table and all its fields removed from template successfully',
        'fields_removed' => $rows_affected
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    error_log("Error removing table from template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close(); 