<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

header('Content-Type: application/json');

try {
    // Check if section_id column exists in select_types table
    $query = "SELECT column_name, data_type, is_nullable, column_default 
              FROM information_schema.columns 
              WHERE table_name = 'select_types' 
              ORDER BY ordinal_position";
    $result = $supabase_pdo->query($query);
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if section_id column exists
    $section_id_exists = false;
    foreach ($columns as $column) {
        if ($column['column_name'] === 'section_id') {
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
