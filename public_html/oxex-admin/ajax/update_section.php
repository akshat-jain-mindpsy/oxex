<?php
require_once '../../OXEXfolder/config.php';
require_once '../../OXEXfolder/u_functions.php';
sec_session_start();

// Basic security check
if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get POST data
$section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
$table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
$section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';
$section_description = isset($_POST['section_description']) ? trim($_POST['section_description']) : '';
$table_ids = isset($_POST['table_ids']) ? $_POST['table_ids'] : [];

// Validate required fields
if (empty($section_id) || empty($section_name)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Section ID and name are required'
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
    
    // Remove all existing table associations for this section
    $delete_stmt = $mysqli->prepare("
        DELETE FROM section_table_link 
        WHERE section_id = ?
    ");
    $delete_stmt->bind_param("i", $section_id);
    $delete_stmt->execute();
    
    // Add new table associations
    if (!empty($table_ids)) {
        $insert_stmt = $mysqli->prepare("
            INSERT INTO section_table_link (section_id, tbid, display_order) 
            VALUES (?, ?, 0)
        ");
        
        foreach ($table_ids as $tbid) {
            $insert_stmt->bind_param("ii", $section_id, $tbid);
            $insert_stmt->execute();
        }
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Section updated successfully',
        'section_id' => $section_id
    ]);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update section: ' . $e->getMessage()
    ]);
    exit();
}
?> 