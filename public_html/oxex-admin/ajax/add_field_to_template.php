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

// DB connection (PDO)
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if (!$pdo) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'No database connection available']));
}

// Check if template exists
$check_template = $pdo->prepare("SELECT id FROM csv_templates WHERE id = ?");
$check_template->execute([$template_id]);
$template_row = $check_template->fetch(PDO::FETCH_ASSOC);

if (!$template_row) {
    die(json_encode(['status' => 'error', 'message' => 'Template not found']));
}

// Check if field already exists in the template
$check_field = $pdo->prepare("SELECT id FROM csv_template_columns WHERE template_id = ? AND table_id = ? AND field_id = ?");
$check_field->execute([$template_id, $table_id, $field_id]);
$field_row = $check_field->fetch(PDO::FETCH_ASSOC);

if ($field_row) {
    die(json_encode(['status' => 'error', 'message' => 'Field already exists in this template']));
}

// Get the current max display_order for this table in the template
$order_check = $pdo->prepare("SELECT MAX(display_order) AS max_order FROM csv_template_columns WHERE template_id = ? AND table_id = ?");
$order_check->execute([$template_id, $table_id]);
$max_order = (int)($order_check->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0);

// Increment for the new field
$new_order = $max_order + 1;

// Add the field to the template
try {
    $add_field = $pdo->prepare("INSERT INTO csv_template_columns (template_id, table_id, field_id, display_order) VALUES (?, ?, ?, ?)");
    $add_field->execute([$template_id, $table_id, $field_id, $new_order]);

    if ($add_field->rowCount() === 0) {
        die(json_encode(['status' => 'error', 'message' => 'Failed to add field to template']));
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Field added to template successfully',
        'column_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    error_log("Error adding field to template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
 