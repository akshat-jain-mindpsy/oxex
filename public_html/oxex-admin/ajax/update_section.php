<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if user is logged in and has proper permissions
    if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized access'
        ]);
        exit();
    }

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Debug: Log all POST data
error_log("UPDATE_SECTION POST DATA: " . print_r($_POST, true));

// Get POST data
$section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
$table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
$section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';
$section_description = isset($_POST['section_description']) ? trim($_POST['section_description']) : '';
$table_ids = isset($_POST['table_ids']) ? $_POST['table_ids'] : [];

// Debug: Log parsed values
error_log("UPDATE_SECTION PARSED VALUES: section_id=$section_id, section_name='$section_name', table_id=$table_id");
error_log("UPDATE_SECTION RAW POST section_id: " . (isset($_POST['section_id']) ? $_POST['section_id'] : 'NOT SET'));
error_log("UPDATE_SECTION RAW POST section_name: " . (isset($_POST['section_name']) ? $_POST['section_name'] : 'NOT SET'));

// Validate required fields
if (empty($section_id) || empty($section_name)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Section ID and name are required',
        'debug' => [
            'section_id' => $section_id,
            'section_name' => $section_name,
            'received_post' => $_POST
        ]
    ]);
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Update section details
    $update_stmt = $mysqli->prepare("
        UPDATE field_sections 
        SET section_name = ?, section_description = ? 
        WHERE section_id = ?
    ");
    $update_stmt->bind_param("ssi", $section_name, $section_description, $section_id);
    $update_stmt->execute();
    
    if ($update_stmt->affected_rows === 0) {
        throw new Exception("No section found with ID: $section_id");
    }
    $update_stmt->close();
    
    // Remove all existing table associations for this section
    $delete_stmt = $mysqli->prepare("DELETE FROM section_table_link WHERE section_id = ?");
    $delete_stmt->bind_param("i", $section_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Add new table associations if provided
    if (!empty($table_ids) && is_array($table_ids)) {
        $insert_stmt = $mysqli->prepare("
            INSERT INTO section_table_link (section_id, tbid, display_order) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($table_ids as $index => $tbid) {
            $tbid = (int)$tbid;
            if ($tbid > 0) {
                $display_order = $index + 1;
                $insert_stmt->bind_param("iii", $section_id, $tbid, $display_order);
                $insert_stmt->execute();
            }
        }
        $insert_stmt->close();
    }
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Section updated successfully',
        'section_id' => $section_id,
        'section_name' => $section_name
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    
    error_log("Error updating section: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update section: ' . $e->getMessage()
    ]);
}

$mysqli->close();

} catch (Exception $e) {
    // Catch any fatal errors
    error_log("Fatal error in update_section.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'A fatal error occurred: ' . $e->getMessage()
    ]);
}
?>