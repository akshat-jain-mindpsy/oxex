<?php
require_once '../../OXEXfolder/config.php';
require_once '../../OXEXfolder/u_functions.php';
sec_session_start();

// Basic security check
if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Ensure data is properly received
if (!isset($_GET['section_id']) || empty($_GET['section_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Section ID is required'
    ]);
    exit();
}

// Get section ID
$section_id = (int)$_GET['section_id'];

// Get tables associated with this section
$query = "
    SELECT DISTINCT tbid 
    FROM section_table_link 
    WHERE section_id = ?
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $section_id);
$stmt->execute();
$result = $stmt->get_result();

$tables = [];
while ($row = $result->fetch_assoc()) {
    $tables[] = $row['tbid'];
}

// Return response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'section_id' => $section_id,
    'tables' => $tables
]);
exit();
?> 