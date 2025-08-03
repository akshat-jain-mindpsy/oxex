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

// Get the table ID from the request
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($table_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid table ID'
    ]);
    exit;
}

try {
    // Get all sections and mark if they're used with this table
    $query = "
        SELECT 
            fs.section_id, 
            fs.section_name, 
            fs.section_description,
            fs.section_order,
            (SELECT COUNT(*) FROM section_table_link 
             WHERE section_id = fs.section_id AND tbid = ?) AS is_used_in_table
        FROM 
            field_sections fs
        ORDER BY 
            fs.section_order ASC
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sections = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sections[] = [
                'section_id' => (int)$row['section_id'],
                'section_name' => $row['section_name'],
                'section_description' => $row['section_description'],
                'section_order' => (int)$row['section_order'],
                'is_used_in_table' => (bool)$row['is_used_in_table']
            ];
        }
        $result->free();
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'sections' => $sections,
        'table_id' => $table_id
    ]);
} catch (Exception $e) {
    error_log("Error retrieving sections: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve sections: ' . $e->getMessage()
    ]);
}

$mysqli->close();
?> 