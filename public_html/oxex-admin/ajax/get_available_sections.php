<?php
header('Content-Type: application/json');

include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();

// Check login and permissions
if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized access'
    ]);
    exit;
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
    $sections_stmt = $mysqli->prepare("
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
    $sections_stmt->bind_param("i", $table_id);
    $sections_stmt->execute();
    $sections_result = $sections_stmt->get_result();
    
    $sections = [];
    while ($section = $sections_result->fetch_assoc()) {
        $sections[] = $section;
    }
    $sections_stmt->close();
    
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

$mysqli->close();
exit;
?> 