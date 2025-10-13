<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Only allow authorized admins
if (login_check($pdo) !== true || ($admintype !== 'AT' && $admintype !== 'DV')) {
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

$stmt = $supabase_pdo->prepare($sql);
$stmt->execute([$stid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $subfields[] = [
        'pid' => $row['pid'],
        'str' => $row['select_val'] // Using 'str' for consistency with existing code
    ];
}

echo json_encode($subfields);
?>
