<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Ensure proper access control
if(!(login_check($mysqli) == true && 
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
    // Start transaction
    $mysqli->begin_transaction();

    // Delete the option using stid and select_val
    $stmt = $mysqli->prepare("DELETE FROM select_gen WHERE stid = ? AND select_val = ? LIMIT 1");
    $stmt->bind_param("is", $stid, $option_value);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $mysqli->commit();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Option deleted successfully'
        ]);
    } else {
        throw new Exception('No option found to delete');
    }

} catch (Exception $e) {
    $mysqli->rollback();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred while deleting option: ' . $e->getMessage()
    ]);
}

if (isset($stmt)) {
    $stmt->close();
}
$mysqli->close(); 