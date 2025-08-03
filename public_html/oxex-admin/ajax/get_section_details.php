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
    $section_stmt = $mysqli->prepare("
        SELECT 
            fs.section_id, 
            fs.section_name, 
            fs.section_description,
            GROUP_CONCAT(DISTINCT stl.tbid) AS table_ids
        FROM 
            field_sections fs
        LEFT JOIN 
            section_table_link stl ON fs.section_id = stl.section_id AND stl.tbid = ?
        WHERE 
            fs.section_id = ?
        GROUP BY 
            fs.section_id, fs.section_name, fs.section_description
    ");
    $section_stmt->bind_param("ii", $table_id, $section_id);
    $section_stmt->execute();
    $section_result = $section_stmt->get_result();
    
    if ($section_result->num_rows === 0) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Section not found'
        ]);
        exit;
    }
    
    $section = $section_result->fetch_assoc();
    $section_stmt->close();
    
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

$mysqli->close();
exit;
?> 