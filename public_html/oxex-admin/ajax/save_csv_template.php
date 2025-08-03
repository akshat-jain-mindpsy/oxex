<?php
header('Content-Type: application/json');

// Include necessary configuration and database connection
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

if (!isset($input['template_name']) || !isset($input['columns']) || !is_array($input['columns'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields or invalid format']));
}

$template_id = isset($input['template_id']) ? intval($input['template_id']) : 0;
$template_name = trim($input['template_name']);
$description = isset($input['description']) ? trim($input['description']) : '';
$columns = $input['columns'];

// Validate input
if (empty($template_name)) {
    die(json_encode(['status' => 'error', 'message' => 'Template name cannot be empty']));
}

// Save the template
try {
    // Start transaction
    $mysqli->begin_transaction();
    
    if ($template_id > 0) {
        // Update existing template - using only columns that exist in the database
        $update_template = $mysqli->prepare("
            UPDATE csv_templates SET 
                template_name = ?, 
                description = ?
            WHERE id = ?
        ");
        
        $update_template->bind_param(
            "ssi", 
            $template_name, 
            $description, 
            $template_id
        );
        
        $update_template->execute();
        
        if ($update_template->affected_rows < 0) {
            throw new Exception("Failed to update template");
        }
    } else {
        // Insert new template - using only columns that exist in the database
        $insert_template = $mysqli->prepare("
            INSERT INTO csv_templates (
                template_name, 
                description, 
                created_by
            ) VALUES (?, ?, ?)
        ");
        
        $insert_template->bind_param(
            "sss", 
            $template_name, 
            $description, 
            $adminname
        );
        
        $insert_template->execute();
        
        if ($insert_template->affected_rows === 0) {
            throw new Exception("Failed to create template");
        }
        
        $template_id = $mysqli->insert_id;
    }
    
    // Handle columns if this is an update
    if ($template_id > 0 && !empty($columns)) {
        // First, delete existing columns for this template
        $delete_columns = $mysqli->prepare("DELETE FROM csv_template_columns WHERE template_id = ?");
        $delete_columns->bind_param("i", $template_id);
        $delete_columns->execute();
        
        // Then insert the new columns
        $insert_column = $mysqli->prepare("
            INSERT INTO csv_template_columns (
                template_id, 
                table_id, 
                field_id, 
                display_order
            ) VALUES (?, ?, ?, ?)
        ");
        
        foreach ($columns as $index => $column) {
            $table_id = intval($column['table_id']);
            $field_id = intval($column['field_id']);
            $display_order = $index + 1;
            
            $insert_column->bind_param("iiii", $template_id, $table_id, $field_id, $display_order);
            $insert_column->execute();
        }
    }
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => ($template_id > 0 ? 'Template updated successfully' : 'Template created successfully'),
        'template_id' => $template_id
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    error_log("Error saving template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
exit;
?> 