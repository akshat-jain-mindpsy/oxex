<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

header('Content-Type: application/json');

// Get admin type from session
$admintype = isset($_SESSION['admintype']) ? $_SESSION['admintype'] : '';

// Check login and permissions
if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
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
    error_log("get_sections.php: table_id = $table_id");
    
    // Get all sections - for Edit Field dialog, we need ALL sections, not just table-linked ones
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
    
    error_log("get_sections.php: executing query");
    
    $stmt = $supabase_pdo->prepare($query);
    $stmt->execute([$table_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sections = [];
    
    error_log("get_sections.php: result rows = " . count($rows));
    
    if (!empty($rows)) {
        foreach ($rows as $row) {
            error_log("get_sections.php: found section - " . json_encode($row));
            $sections[] = [
                'section_id' => (int)$row['section_id'],
                'section_name' => $row['section_name'],
                'section_description' => $row['section_description'],
                'section_order' => (int)$row['section_order'],
                'is_used_in_table' => (bool)$row['is_used_in_table']
            ];
        }
    } else {
        error_log("get_sections.php: No sections found in database");
    }
    
    error_log("get_sections.php: total sections found = " . count($sections));
    
    echo json_encode([
        'status' => 'success',
        'sections' => $sections,
        'table_id' => $table_id
    ]);
} catch (Exception $e) {
    error_log("Error retrieving sections: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve sections: ' . $e->getMessage(),
        'sections' => []
    ]);
}

?> 