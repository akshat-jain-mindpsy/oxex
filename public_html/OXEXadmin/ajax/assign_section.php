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
$mysqli->begin_transaction();

try {
    // Find unsectioned fields for this table
    $unsectioned_stmt = $mysqli->prepare("
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
    $unsectioned_stmt->bind_param("i", $table_id);
    $unsectioned_stmt->execute();
    $unsectioned_result = $unsectioned_stmt->get_result();
    
    // Prepare insert statement
    $insert_stmt = $mysqli->prepare("
        INSERT INTO section_table_link (section_id, tbid, stid, display_order) 
        VALUES (?, ?, ?, ?)
    ");
    
    $field_count = 0;
    $display_order = 1;
    
    // Assign each unsectioned field to the selected section
    while ($field = $unsectioned_result->fetch_assoc()) {
        $field_id = $field['stid'];
        $insert_stmt->bind_param("iiii", $section_id, $table_id, $field_id, $display_order);
        $insert_stmt->execute();
        $field_count++;
        $display_order++;
    }
    
    $unsectioned_stmt->close();
    $insert_stmt->close();
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => "Successfully assigned $field_count fields to the section"
    ]);
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    error_log("Error assigning fields to section: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to assign fields to section: ' . $e->getMessage()
    ]);
}

$mysqli->close();
exit;
?> 