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

if (!isset($input['table_id']) || !isset($input['field_name']) || !isset($input['field_type'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields']));
}

$table_id = intval($input['table_id']);
$field_name = trim($input['field_name']);
$field_type = trim($input['field_type']);

// Validate input
if (empty($field_name)) {
    die(json_encode(['status' => 'error', 'message' => 'Field name cannot be empty']));
}

// Check if table exists
$check_table = $mysqli->prepare("SELECT tbid FROM tabs_tbl WHERE tbid = ?");
$check_table->bind_param("i", $table_id);
$check_table->execute();
$table_result = $check_table->get_result();

if ($table_result->num_rows === 0) {
    die(json_encode(['status' => 'error', 'message' => 'Table not found']));
}

// Create the new field in select_types
try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Insert into select_types
    $insert_field = $mysqli->prepare("INSERT INTO select_types (str, type) VALUES (?, ?)");
    $insert_field->bind_param("ss", $field_name, $field_type);
    $insert_field->execute();
    
    // Get the new field ID
    $field_id = $mysqli->insert_id;
    
    // Get the next sort order
    $get_max_sort = $mysqli->prepare("SELECT MAX(sort_order) as max_sort FROM tab_fields WHERE tbid = ?");
    $get_max_sort->bind_param("i", $table_id);
    $get_max_sort->execute();
    $sort_result = $get_max_sort->get_result();
    $sort_row = $sort_result->fetch_assoc();
    $next_sort = ($sort_row['max_sort'] !== null) ? intval($sort_row['max_sort']) + 1 : 1;
    
    // Link field to table in tab_fields
    $link_field = $mysqli->prepare("INSERT INTO tab_fields (tbid, stid, sort_order) VALUES (?, ?, ?)");
    $link_field->bind_param("iii", $table_id, $field_id, $next_sort);
    $link_field->execute();
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Field created successfully',
        'field_id' => $field_id
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    error_log("Error creating field: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} 