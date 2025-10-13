<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Check if user is logged in and has appropriate permissions
if (login_check($pdo) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get parameters
$stid = isset($_POST['stid']) ? (int)$_POST['stid'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$type = isset($_POST['type']) ? (int)$_POST['type'] : 0;

// Validate parameters
if ($stid <= 0 || empty($name)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid parameters'
    ]);
    exit;
}

// Update field name and type in database
try {
    $stmt = $supabase_pdo->prepare("UPDATE select_types SET str = ?, single = ? WHERE stid = ? LIMIT 1");
    $stmt->execute([$name, $type, $stid]);
    $affected = $stmt->rowCount();
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update field details: ' . $e->getMessage()
    ]);
    exit;
}

if ($affected > 0) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Field details updated successfully'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update field details'
    ]);
}

?> 