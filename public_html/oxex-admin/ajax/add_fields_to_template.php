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

if (!isset($input['template_id']) || !isset($input['table_id']) || !isset($input['fields']) || !is_array($input['fields'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields or invalid format']));
}

$template_id = intval($input['template_id']);
$table_id = intval($input['table_id']);
$fields = $input['fields'];

// Validate input
if ($template_id <= 0 || $table_id <= 0 || empty($fields)) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid input values']));
}

// Check if template exists
$check_template = $mysqli->prepare("SELECT id FROM csv_templates WHERE id = ?");
$check_template->bind_param("i", $template_id);
$check_template->execute();
$template_result = $check_template->get_result();

if ($template_result->num_rows === 0) {
    die(json_encode(['status' => 'error', 'message' => 'Template not found']));
}

// Get the next display order
$get_max_order = $mysqli->prepare("SELECT MAX(display_order) as max_order FROM csv_template_columns WHERE template_id = ?");
$get_max_order->bind_param("i", $template_id);
$get_max_order->execute();
$order_result = $get_max_order->get_result();
$order_row = $order_result->fetch_assoc();
$next_order = ($order_row['max_order'] !== null) ? intval($order_row['max_order']) + 1 : 1;

// Add the fields to the template
try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Prepare statement for multiple inserts
    $add_field = $mysqli->prepare("INSERT INTO csv_template_columns (template_id, table_id, field_id, display_order) VALUES (?, ?, ?, ?)");
    
    $fields_added = 0;
    $current_order = $next_order;
    
    foreach ($fields as $field) {
        $field_id = intval($field['field_id']);
        
        // Check if field already exists in the template
        $check_field = $mysqli->prepare("SELECT id FROM csv_template_columns WHERE template_id = ? AND table_id = ? AND field_id = ?");
        $check_field->bind_param("iii", $template_id, $table_id, $field_id);
        $check_field->execute();
        $field_result = $check_field->get_result();
        
        if ($field_result->num_rows === 0) {
            // Add the field
            $add_field->bind_param("iiii", $template_id, $table_id, $field_id, $current_order);
            $add_field->execute();
            
            if ($add_field->affected_rows > 0) {
                $fields_added++;
                $current_order++;
            }
        }
    }
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Fields added to template successfully',
        'fields_added' => $fields_added
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    error_log("Error adding fields to template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close(); 