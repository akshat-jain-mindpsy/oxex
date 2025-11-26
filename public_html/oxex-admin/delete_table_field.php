<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Check authorization
if (!(login_check($pdo) == true && 
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

$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No database connection available'
    ]);
    exit();
}

// Start transaction
$pdo->beginTransaction();

try {
    // Remove the field from tab_fields for this specific table
    $delete_field_stmt = $pdo->prepare("DELETE FROM tab_fields WHERE stid = ? AND tbid = ?");
    $delete_field_stmt->execute([$stid, $tbid]);
    
    // Reorder remaining fields using PostgreSQL-compatible window function
    $reorder_stmt = $pdo->prepare("
        WITH ordered AS (
            SELECT stid,
                   ROW_NUMBER() OVER (ORDER BY sort_order, stid) AS new_order
            FROM tab_fields
            WHERE tbid = ?
        )
        UPDATE tab_fields tf
        SET sort_order = ordered.new_order
        FROM ordered
        WHERE tf.stid = ordered.stid
          AND tf.tbid = ?
    ");
    $reorder_stmt->execute([$tbid, $tbid]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Field removed from table and order updated'
    ]);
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollback();
    
    error_log("Field removal error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not remove field: ' . $e->getMessage()
    ]);
}
?> 