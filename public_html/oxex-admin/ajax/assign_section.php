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
$section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
$table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;

if ($section_id <= 0 || $table_id <= 0) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid section or table ID'
    ]);
    exit;
}

// Start transaction
$supabase_pdo->beginTransaction();

try {
    // Find unsectioned fields for this table
    $unsectioned_stmt = $supabase_pdo->prepare("
        SELECT 
            st.stid
        FROM 
            select_types st
        JOIN 
            tab_fields tf ON st.stid = tf.stid
        LEFT JOIN 
            section_table_link stl ON st.stid = stl.stid AND stl.tbid = tf.tbid
        WHERE 
            tf.tbid = ? AND stl.section_id IS NULL
    ");
    $unsectioned_stmt->execute([$table_id]);
    $unsectioned_fields = $unsectioned_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare insert statement
    $insert_stmt = $supabase_pdo->prepare("
        INSERT INTO section_table_link (section_id, tbid, stid, display_order) 
        VALUES (?, ?, ?, ?)
    ");
    
    $field_count = 0;
    $display_order = 1;
    
    // Assign each unsectioned field to the selected section
    foreach ($unsectioned_fields as $field) {
        $field_id = $field['stid'];
        $insert_stmt->execute([$section_id, $table_id, $field_id, $display_order]);
        $field_count++;
        $display_order++;
    }
    
    // Commit transaction
    $supabase_pdo->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => "Successfully assigned $field_count fields to the section"
    ]);
} catch (Exception $e) {
    // Rollback on error
    $supabase_pdo->rollBack();
    
    error_log("Error assigning fields to section: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to assign fields to section: ' . $e->getMessage()
    ]);
}
exit;
?> 