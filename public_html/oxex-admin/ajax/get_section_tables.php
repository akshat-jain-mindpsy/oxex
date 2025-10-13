<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

header('Content-Type: application/json');

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
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

$stmt = $supabase_pdo->prepare($query);
$stmt->execute([$section_id]);
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Return response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'section_id' => $section_id,
    'tables' => $tables
]);
exit();
?> 