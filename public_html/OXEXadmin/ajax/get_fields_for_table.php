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

$tbid = isset($_GET['tbid']) ? (int)$_GET['tbid'] : 0;

if ($tbid === 0) {
    echo json_encode([]);
    exit;
}

$fields = [];
$sql = "SELECT st.stid, st.str 
        FROM select_types st
        JOIN tab_fields tf ON st.stid = tf.stid
        WHERE tf.tbid = ?
        ORDER BY st.str ASC";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $tbid);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $fields[] = $row;
    }
    $stmt->close();
}

echo json_encode($fields); 