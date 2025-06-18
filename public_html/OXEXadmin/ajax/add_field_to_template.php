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

// Check if template exists
$check_template = $mysqli->prepare("SELECT id FROM csv_templates WHERE id = ?");
$check_template->bind_param("i", $template_id);
$check_template->execute();
$template_result = $check_template->get_result();

if ($template_result->num_rows === 0) {
    die(json_encode(['status' => 'error', 'message' => 'Template not found']));
}

// Check if field already exists in the template
$check_field = $mysqli->prepare("SELECT id FROM csv_template_columns WHERE template_id = ? AND table_id = ? AND field_id = ?");
$check_field->bind_param("iii", $template_id, $table_id, $field_id);
$check_field->execute();
$field_result = $check_field->get_result();

if ($field_result->num_rows > 0) {
    die(json_encode(['status' => 'error', 'message' => 'Field already exists in this template']));
}

// Get the current max display_order for this table in the template
$max_order = 0;
$order_check = $mysqli->prepare("SELECT MAX(display_order) AS max_order FROM csv_template_columns WHERE template_id = ? AND table_id = ?");
$order_check->bind_param("ii", $template_id, $table_id);
$order_check->execute();
$result = $order_check->get_result();
if ($row = $result->fetch_assoc()) {
    $max_order = $row['max_order'] ?? 0;
}
$order_check->close();

// Increment for the new field
$new_order = $max_order + 1;

// Add the field to the template
try {
    $add_field = $mysqli->prepare("INSERT INTO csv_template_columns (template_id, table_id, field_id, display_order) VALUES (?, ?, ?, ?)");
    $add_field->bind_param("iiii", $template_id, $table_id, $field_id, $new_order);
    $add_field->execute();
    
    if ($add_field->affected_rows === 0) {
        die(json_encode(['status' => 'error', 'message' => 'Failed to add field to template']));
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Field added to template successfully',
        'column_id' => $mysqli->insert_id
    ]);
    
} catch (Exception $e) {
    error_log("Error adding field to template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close(); 