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

// Validate input
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($section_id <= 0) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid section ID'
    ]);
    exit;
}

try {
    // Get section details
    $section_stmt = $supabase_pdo->prepare("
        SELECT 
            fs.section_id, 
            fs.section_name, 
            fs.section_description,
            STRING_AGG(DISTINCT stl.tbid::text, ',') AS table_ids
        FROM 
            field_sections fs
        LEFT JOIN 
            section_table_link stl ON fs.section_id = stl.section_id AND stl.tbid = ?
        WHERE 
            fs.section_id = ?
        GROUP BY 
            fs.section_id, fs.section_name, fs.section_description
    ");
    $section_stmt->execute([$table_id, $section_id]);
    $section = $section_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Section not found'
        ]);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'section_id' => $section['section_id'],
        'section_name' => $section['section_name'],
        'section_description' => $section['section_description'],
        'table_ids' => $section['table_ids']
    ]);
} catch (Exception $e) {
    error_log("Error retrieving section details: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to retrieve section details: ' . $e->getMessage()
    ]);
}
exit;
?> 