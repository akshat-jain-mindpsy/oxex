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
if(!isset($_POST['section_id']) || !isset($_POST['table_id']) || !isset($_POST['field_ids'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

$section_id = $_POST['section_id'] === 'null' ? null : (int)$_POST['section_id'];
$table_id = (int)$_POST['table_id'];
$field_ids = json_decode($_POST['field_ids'], true);

// Debug logging
error_log("UPDATE_FIELD_ORDER DEBUG: section_id=$section_id, table_id=$table_id, field_ids=" . print_r($field_ids, true));

// Validate parameters
if($table_id <= 0 || !is_array($field_ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters: table_id=' . $table_id . ', field_ids=' . print_r($field_ids, true)]);
    exit();
}

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // If section_id is null, we're dealing with unsectioned fields
    if($section_id === null) {
        // Simply update the sort_order in tab_fields
        $order_stmt = $mysqli->prepare("UPDATE tab_fields SET sort_order = ? WHERE tbid = ? AND stid = ?");
        
        foreach($field_ids as $index => $field_id) {
            $order = $index + 1; // Start from 1
            $order_stmt->bind_param("iii", $order, $table_id, $field_id);
            $order_stmt->execute();
        }
        
        $order_stmt->close();
    } else {
        // First check if there's an entry in section_table_link for this section and table
        $check_link = $mysqli->prepare("SELECT COUNT(*) as link_exists FROM section_table_link WHERE section_id = ? AND tbid = ?");
        $check_link->bind_param("ii", $section_id, $table_id);
        $check_link->execute();
        $result = $check_link->get_result();
        $row = $result->fetch_assoc();
        $link_exists = (int)$row['link_exists'] > 0;
        $check_link->close();
        
        // If no link exists, create one
        if(!$link_exists) {
            $create_link = $mysqli->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, 1)");
            $create_link->bind_param("ii", $section_id, $table_id);
            
            try {
                $create_link->execute();
            } catch(mysqli_sql_exception $e) {
                // Skip duplicate entries
                if($e->getCode() != 1062) {
                    throw $e;
                }
            }
            
            $create_link->close();
        }
        
        // Update each field to ensure it's assigned to this section
        $update_field = $mysqli->prepare("UPDATE select_types SET section_id = ? WHERE stid = ?");
        
        foreach($field_ids as $field_id) {
            $update_field->bind_param("ii", $section_id, $field_id);
            $update_field->execute();
        }
        
        $update_field->close();
        
        // Update the sort_order in tab_fields for consistency
        $sort_stmt = $mysqli->prepare("UPDATE tab_fields SET sort_order = ? WHERE tbid = ? AND stid = ?");
        
        foreach($field_ids as $index => $field_id) {
            $order = $index + 1; // Start from 1
            $sort_stmt->bind_param("iii", $order, $table_id, $field_id);
            $sort_stmt->execute();
        }
        
        $sort_stmt->close();
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Field order updated successfully',
        'section_id' => $section_id,
        'table_id' => $table_id,
        'field_count' => count($field_ids)
    ]);
} catch(Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Error updating field order: ' . $e->getMessage(),
        'error_code' => $e instanceof mysqli_sql_exception ? $e->getCode() : 0
    ]);
}

$mysqli->close();
?> 