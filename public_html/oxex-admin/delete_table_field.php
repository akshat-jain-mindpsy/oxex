<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check authorization
if (!(login_check($mysqli) == true && 
     in_array($admintype, ['AT', 'DV']))) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit();
}

// Get POST data
$stid = isset($_POST['stid']) ? (int)$_POST['stid'] : 0;
$tbid = isset($_POST['tbid']) ? (int)$_POST['tbid'] : 0;

// Validate input
if ($stid <= 0 || $tbid <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input'
    ]);
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Remove the field from tab_fields for this specific table
    $delete_field_stmt = $mysqli->prepare("DELETE FROM tab_fields WHERE stid = ? AND tbid = ?");
    $delete_field_stmt->bind_param("ii", $stid, $tbid);
    $delete_field_stmt->execute();
    
    // Reorder remaining fields
    $mysqli->query("SET @row_number = 0");
    $reorder_stmt = $mysqli->prepare("
        UPDATE tab_fields 
        SET sort_order = (@row_number:=@row_number + 1) 
        WHERE tbid = ? 
        ORDER BY sort_order
    ");
    $reorder_stmt->bind_param("i", $tbid);
    $reorder_stmt->execute();
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Field removed from table and order updated'
    ]);
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    error_log("Field removal error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not remove field: ' . $e->getMessage()
    ]);
}
?> 