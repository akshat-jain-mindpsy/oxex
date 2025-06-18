<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check authorization
if (!(login_check($mysqli) == true && 
     in_array($admintype, ['AT', 'DV', 'AO', 'AE', 'SO', 'SE']))) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit();
}

// Get POST data
$tbid = isset($_POST['tbid']) ? (int)$_POST['tbid'] : 0;
$positions = isset($_POST['positions']) ? $_POST['positions'] : [];

// Validate input
if ($tbid <= 0 || empty($positions)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input'
    ]);
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Prepare statement for updating sort order
    $update_stmt = $mysqli->prepare("
        UPDATE tab_fields 
        SET sort_order = ? 
        WHERE stid = ? AND tbid = ?
    ");
    
    // Update each field's sort order
    foreach ($positions as $index => $stid) {
        $sort_order = $index + 1;  // 1-based indexing
        $update_stmt->bind_param("iii", $sort_order, $stid, $tbid);
        $update_stmt->execute();
    }
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Field order updated successfully'
    ]);
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    error_log("Field order update error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not update field order: ' . $e->getMessage()
    ]);
}
?> 