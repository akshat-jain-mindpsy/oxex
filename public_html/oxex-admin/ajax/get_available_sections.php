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
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($table_id <= 0) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid table ID'
    ]);
    exit;
}

try {
    // Get sections not already linked to this table
    $sections_stmt = $supabase_pdo->prepare("
        SELECT 
            fs.section_id, 
            fs.section_name, 
            fs.section_description
        FROM 
            field_sections fs
        LEFT JOIN 
            section_table_link stl ON fs.section_id = stl.section_id AND stl.tbid = ?
        WHERE 
            stl.section_id IS NULL
        ORDER BY 
            fs.section_name
    ");
    $sections_stmt->execute([$table_id]);
    
    $sections = [];
    while ($section = $sections_stmt->fetch(PDO::FETCH_ASSOC)) {
        $sections[] = $section;
    }
    
    echo json_encode([
        'status' => 'success',
        'sections' => $sections
    ]);
} catch (Exception $e) {
    error_log("Error retrieving available sections: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to retrieve available sections: ' . $e->getMessage()
    ]);
}

exit;
?> 