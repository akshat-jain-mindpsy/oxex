<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Check authorization
if (!(login_check($pdo) == true && 
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
$supabase_pdo->beginTransaction();

try {
    // Prepare statement for updating sort order
    $update_stmt = $supabase_pdo->prepare("
        UPDATE tab_fields 
        SET sort_order = ? 
        WHERE stid = ? AND tbid = ?
    ");
    
    // Update each field's sort order
    foreach ($positions as $index => $stid) {
        $$value0 = 0; // Default value for sort_order
$subtitle = "index + 1;  // 1-based indexing
        $update_stmt->execute([$$value0 = 0; // Default value for sort_order
$subtitle = "tbid]);
    }
    
    // Commit transaction
    $supabase_pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Field order updated successfully'
    ]);
} catch (Exception $e) {
    // Rollback on error
    $supabase_pdo->rollback();
    
    error_log("Field order update error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not update field order: ' . $e->getMessage()
    ]);
}
?> 