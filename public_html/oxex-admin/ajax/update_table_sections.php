<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Set content type to JSON
header('Content-Type: application/json');

// Ensure proper access control
if(!(login_check($mysqli) == true && 
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
    $mysqli->begin_transaction();
    
    // First, get the current section IDs for this table
    $current_stmt = $mysqli->prepare("SELECT section_id FROM section_table_link WHERE tbid = ?");
    $current_stmt->bind_param("i", $table_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    
    $current_section_ids = [];
    while($row = $current_result->fetch_assoc()) {
        $current_section_ids[] = (int)$row['section_id'];
    }
    $current_stmt->close();
    
    // Determine which sections to add and which to remove
    $sections_to_add = array_diff($section_ids, $current_section_ids);
    $sections_to_remove = array_diff($current_section_ids, $section_ids);
    
    // Remove sections that are no longer assigned
    if(!empty($sections_to_remove)) {
        $remove_stmt = $mysqli->prepare("DELETE FROM section_table_link WHERE tbid = ? AND section_id = ?");
        
        foreach($sections_to_remove as $section_id) {
            $remove_stmt->bind_param("ii", $table_id, $section_id);
            $remove_stmt->execute();
        }
        
        $remove_stmt->close();
    }
    
    // Add new section assignments
    if(!empty($sections_to_add)) {
        $insert_stmt = $mysqli->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, ?)");
        
        $order = 1;
        foreach($sections_to_add as $section_id) {
            $insert_stmt->bind_param("iii", $section_id, $table_id, $order);
            
            try {
                $insert_stmt->execute();
                $order++;
            } catch(mysqli_sql_exception $e) {
                // Skip duplicate entries - error code 1062
                if($e->getCode() != 1062) {
                    throw $e;
                }
            }
        }
        
        $insert_stmt->close();
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
        
        $affected_fields_stmt = $mysqli->prepare($affected_fields_query);
        $affected_fields_stmt->bind_param("i", $table_id);
        $affected_fields_stmt->execute();
        $affected_fields_result = $affected_fields_stmt->get_result();
        
        $affected_field_ids = [];
        while($row = $affected_fields_result->fetch_assoc()) {
            $affected_field_ids[] = (int)$row['stid'];
        }
        $affected_fields_stmt->close();
        
        // If there are affected fields, handle them appropriately
        if(!empty($affected_field_ids)) {
            // Option 1: Preserve the section_id in the field but remove just the link
            // (Already done by removing from section_table_link)
            
            // Option 2: Move fields to unsectioned (null) by clearing their section_id
            // Uncomment below to enable this behavior instead
            /*
            $update_fields_query = "UPDATE select_types SET section_id = NULL WHERE stid IN (" . implode(',', $affected_field_ids) . ")";
            $mysqli->query($update_fields_query);
            */
        }
    }
    
    // Commit transaction
    $mysqli->commit();
    
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
    $mysqli->rollback();
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Error updating table sections: ' . $e->getMessage(),
        'error_code' => $e instanceof mysqli_sql_exception ? $e->getCode() : 0
    ]);
}

$mysqli->close();
?> 