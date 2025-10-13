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
$check_table = $supabase_pdo->prepare("SELECT tbid FROM tabs_tbl WHERE tbid = ?");
$check_table->execute([$table_id]);
$table_row = $check_table->fetch(PDO::FETCH_ASSOC);

if (!$table_row) {
    die(json_encode(['status' => 'error', 'message' => 'Table not found']));
}

// Create the new field in select_types
try {
    // Start transaction
    $supabase_pdo->beginTransaction();
    
    // Insert into select_types
    $insert_field = $supabase_pdo->prepare("INSERT INTO select_types (str, type) VALUES (?, ?)");
    $insert_field->execute([$field_name, $field_type]);
    
    // Get the new field ID
    $field_id = $supabase_pdo->lastInsertId();
    
    // Get the next sort order
    $get_max_sort = $supabase_pdo->prepare("SELECT MAX(sort_order) as max_sort FROM tab_fields WHERE tbid = ?");
    $get_max_sort->execute([$table_id]);
    $sort_row = $get_max_sort->fetch(PDO::FETCH_ASSOC);
    $next_sort = ($sort_row['max_sort'] !== null) ? intval($sort_row['max_sort']) + 1 : 1;
    
    // Link field to table in tab_fields
    $link_field = $supabase_pdo->prepare("INSERT INTO tab_fields (tbid, stid, sort_order) VALUES (?, ?, ?)");
    $link_field->execute([$table_id, $field_id, $next_sort]);
    
    // Commit transaction
    $supabase_pdo->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Field created successfully',
        'field_id' => $field_id
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($supabase_pdo->inTransaction()) {
        $supabase_pdo->rollBack();
    }
    error_log("Error creating field: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} 