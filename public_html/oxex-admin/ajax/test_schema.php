<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

header('Content-Type: application/json');

try {
    // Check if section_id column exists in select_types table
    $query = "DESCRIBE select_types";
    $result = $mysqli->query($query);
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    
    // Check if section_id column exists
    $section_id_exists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'section_id') {
            $section_id_exists = true;
            break;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'section_id_exists' => $section_id_exists,
        'columns' => $columns,
        'admin_type' => $_SESSION['admintype'] ?? 'not set'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
