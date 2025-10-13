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

if (!isset($input['template_name'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing template name']));
}

$template_name = trim($input['template_name']);
$description = isset($input['description']) ? trim($input['description']) : '';

// Validate input
if (empty($template_name)) {
    die(json_encode(['status' => 'error', 'message' => 'Template name cannot be empty']));
}

// Create the new template
try {
    // Insert the new template with only the columns that exist in the database
    // Based on the error message, we're removing fields that don't exist in the table
    $insert_template = $supabase_pdo->prepare("
        INSERT INTO csv_templates (
            template_name, 
            description, 
            created_by
        ) VALUES (?, ?, ?)
        RETURNING id
    ");
    
    $insert_template->execute([
        $template_name, 
        $description, 
        $adminname  // Using $adminname from the session
    ]);
    
    // Fetch the newly created id (Postgres-safe)
    $template_id = $insert_template->fetchColumn();
    if (!$template_id) {
        die(json_encode(['status' => 'error', 'message' => 'Failed to create template (no id returned)']));
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Template created successfully',
        'template_id' => $template_id
    ]);
    
} catch (Exception $e) {
    error_log("Error creating template: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 