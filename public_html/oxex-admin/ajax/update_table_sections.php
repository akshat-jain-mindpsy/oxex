<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Set content type to JSON
header('Content-Type: application/json');

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check parameters
if(!isset($_POST['table_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameter: table_id']);
    exit();
}

$table_id = (int)$_POST['table_id'];
$section_ids = isset($_POST['section_ids']) ? $_POST['section_ids'] : [];

// Validate parameters
if($table_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid table ID']);
    exit();
}

// Convert section IDs to integers
foreach($section_ids as &$id) {
    $id = (int)$id;
}
unset($id);

try {
    // Start transaction
    $supabase_pdo->beginTransaction();
    
    // First, get the current section IDs for this table
    $current_stmt = $supabase_pdo->prepare("SELECT section_id FROM section_table_link WHERE tbid = ?");
    $current_stmt->execute([$table_id]);
    
    $current_section_ids = [];
    while($row = $current_stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_section_ids[] = (int)$row['section_id'];
    }
    
    // Determine which sections to add and which to remove
    $sections_to_add = array_diff($section_ids, $current_section_ids);
    $sections_to_remove = array_diff($current_section_ids, $section_ids);
    
    // Remove sections that are no longer assigned
    if(!empty($sections_to_remove)) {
        $remove_stmt = $supabase_pdo->prepare("DELETE FROM section_table_link WHERE tbid = ? AND section_id = ?");
        
        foreach($sections_to_remove as $section_id) {
            $remove_stmt->execute([$table_id, $section_id]);
        }
    }
    
    // Add new section assignments
    if(!empty($sections_to_add)) {
        $insert_stmt = $supabase_pdo->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, ?)");
        
        $order = 1;
        foreach($sections_to_add as $section_id) {
            try {
                $insert_stmt->execute([$section_id, $table_id, $order]);
                $order++;
            } catch(PDOException $e) {
                // Skip duplicate entries - error code 23505 for PostgreSQL
                if($e->getCode() != 23505) {
                    throw $e;
                }
            }
        }
    }
    
    // Now check if any fields in removed sections need to be updated
    if(!empty($sections_to_remove)) {
        // Find all fields that are in removed sections AND used in this table
        $affected_fields_query = "
            SELECT st.stid 
            FROM select_types st
            JOIN tab_fields tf ON st.stid = tf.stid
            WHERE st.section_id IN (" . implode(',', $sections_to_remove) . ")
            AND tf.tbid = ?
        ";
        
        $affected_fields_stmt = $supabase_pdo->prepare($affected_fields_query);
        $affected_fields_stmt->execute([$table_id]);
        
        $affected_field_ids = [];
        while($row = $affected_fields_stmt->fetch(PDO::FETCH_ASSOC)) {
            $affected_field_ids[] = (int)$row['stid'];
        }
        
        // If there are affected fields, handle them appropriately
        if(!empty($affected_field_ids)) {
            // Option 1: Preserve the section_id in the field but remove just the link
            // (Already done by removing from section_table_link)
            
            // Option 2: Move fields to unsectioned (null) by clearing their section_id
            // Uncomment below to enable this behavior instead
            /*
            $update_fields_query = "UPDATE select_types SET section_id = NULL WHERE stid IN (" . implode(',', $affected_field_ids) . ")";
            $supabase_pdo->query($update_fields_query);
            */
        }
    }
    
    // Commit transaction
    $supabase_pdo->commit();
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Table sections updated successfully',
        'stats' => [
            'added' => count($sections_to_add),
            'removed' => count($sections_to_remove),
            'affected_fields' => isset($affected_field_ids) ? count($affected_field_ids) : 0
        ]
    ]);
} catch(Exception $e) {
    // Rollback on error
    if ($supabase_pdo->inTransaction()) {
        $supabase_pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Error updating table sections: ' . $e->getMessage(),
        'error_code' => $e instanceof PDOException ? $e->getCode() : 0
    ]);
}
?> 