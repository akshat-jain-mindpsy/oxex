<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are present
if (!isset($_POST['stid']) || !isset($_POST['option_value'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

$stid = (int)$_POST['stid'];
$option_value = $_POST['option_value'];

try {
    $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
    if (!$pdo) {
        throw new Exception('No database connection available');
    }
    
    // Start transaction
    $pdo->beginTransaction();

    // Delete the option using stid and select_val
    $stmt = $pdo->prepare("DELETE FROM select_gen WHERE stid = ? AND select_val = ? LIMIT 1");
    $stmt->execute([$stid, $option_value]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Option deleted successfully'
        ]);
    } else {
        throw new Exception('No option found to delete');
    }

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred while deleting option: ' . $e->getMessage()
    ]);
} 