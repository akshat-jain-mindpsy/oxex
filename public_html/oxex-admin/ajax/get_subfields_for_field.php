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

$subfields = [];

// Get the actual subfield values from select_gen table
// This represents the specific options/values for the selected field
$sql = "SELECT pid, select_val 
        FROM select_gen 
        WHERE stid = ? 
        ORDER BY select_val ASC";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $stid);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subfields[] = [
            'pid' => $row['pid'],
            'str' => $row['select_val'] // Using 'str' for consistency with existing code
        ];
    }
    $stmt->close();
}

echo json_encode($subfields);
?>
