<?php
// Fix the relative paths to work from the ajax subdirectory
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();

// Important: Use the correct relative path for includes from the ajax directory
include '../incl/sess.php';

// Enable detailed error logging
ini_set('display_errors', 0); // Turn off display_errors for production
ini_set('log_errors', 1);
error_log("Add fields to section request received: " . print_r($_POST, true));

// Check authorization
if (login_check($pdo) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit;
}

// Get parameters
$section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
$field_ids = isset($_POST['field_ids']) ? $_POST['field_ids'] : [];

// Validate input
if ($section_id <= 0) {
    error_log("Invalid section ID: $section_id");
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid section ID'
    ]);
    exit;
}

if (empty($field_ids)) {
    error_log("No fields selected");
    echo json_encode([
        'status' => 'error',
        'message' => 'No fields selected'
    ]);
    exit;
}

try {
    // Verify section exists
    $section_stmt = $supabase_pdo->prepare("SELECT section_name FROM field_sections WHERE section_id = ?");
    $section_stmt->execute([$section_id]);
    $section_row = $section_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$section_row) {
        throw new Exception("Section not found with ID: $section_id");
    }
    $section_name = $section_row['section_name'];

    // Update each field to assign it to the section - without transaction for simplicity
    $update_stmt = $supabase_pdo->prepare("UPDATE select_types SET section_id = ? WHERE stid = ?");

    $updated_count = 0;
    foreach ($field_ids as $field_id) {
        $field_id = (int)$field_id;
        if (!$update_stmt->execute([$section_id, $field_id])) {
            error_log("Failed to update field ID $field_id");
            continue;
        }
        // Count as updated even if no change was made
        $updated_count++;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "$updated_count field(s) added to section '$section_name'",
        'updated_count' => $updated_count
    ]);
    
} catch (Exception $e) {
    error_log("Exception in add_fields_to_section.php: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 