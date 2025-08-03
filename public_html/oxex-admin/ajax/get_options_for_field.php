<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Only allow authorized admins
if (login_check($mysqli) !== true || ($admintype !== 'AT' && $admintype !== 'DV')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Not authorized.']);
    exit;
}

header('Content-Type: application/json');

$stid = isset($_GET['stid']) ? (int)$_GET['stid'] : 0;

if ($stid === 0) {
    echo json_encode([]);
    exit;
}

$options = [];
// This query assumes that the predefined values for a field are stored in the `select_gen` table.
// Adjust the table and column names if your schema is different.
$sql = "SELECT select_val FROM select_gen WHERE stid = ? ORDER BY select_val ASC";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $stid);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $options[] = $row['select_val'];
    }
    $stmt->close();
}

echo json_encode($options); 