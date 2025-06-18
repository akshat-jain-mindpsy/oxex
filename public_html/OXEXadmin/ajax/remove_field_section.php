<?php
header('Content-Type: application/json');

include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Ensure proper access control
if(!(login_check($mysqli) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check parameters
if(!isset($_POST['field_id']) || !isset($_POST['table_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

$field_id = (int)$_POST['field_id'];
$table_id = (int)$_POST['table_id'];

// Validate parameters
if($field_id <= 0 || $table_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameter values']);
    exit();
}

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Get current section ID before removing
    $get_section = $mysqli->prepare("SELECT section_id FROM select_types WHERE stid = ?");
    $get_section->bind_param("i", $field_id);
    $get_section->execute();
    $section_result = $get_section->get_result();
    $section_id = null;
    
    if($row = $section_result->fetch_assoc()) {
        $section_id = $row['section_id'];
    }
    $get_section->close();
    
    // Update the field to remove its section assignment
    $update_field = $mysqli->prepare("UPDATE select_types SET section_id = NULL WHERE stid = ?");
    $update_field->bind_param("i", $field_id);
    $update_field->execute();
    $field_updated = $update_field->affected_rows > 0;
    $update_field->close();
    
    // Check if there are any other fields in this section for this table
    $check_fields = null;
    $other_fields_exist = false;
    
    if($section_id) {
        $check_fields = $mysqli->prepare("
            SELECT COUNT(*) as field_count 
            FROM select_types st
            JOIN tab_fields tf ON st.stid = tf.stid
            WHERE st.section_id = ? AND tf.tbid = ?
        ");
        $check_fields->bind_param("ii", $section_id, $table_id);
        $check_fields->execute();
        $fields_result = $check_fields->get_result();
        
        if($fields_row = $fields_result->fetch_assoc()) {
            $other_fields_exist = (int)$fields_row['field_count'] > 0;
        }
        $check_fields->close();
        
        // Only remove section_table_link if no other fields are using this section in this table
        if(!$other_fields_exist) {
            $remove_link = $mysqli->prepare("DELETE FROM section_table_link WHERE section_id = ? AND tbid = ?");
            $remove_link->bind_param("ii", $section_id, $table_id);
            $remove_link->execute();
            $link_removed = $remove_link->affected_rows > 0;
            $remove_link->close();
        }
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Respond with success
    if($field_updated) {
        if($section_id && !$other_fields_exist) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Field removed from section and table-section link removed (no more fields in this section for this table)',
                'section_id' => $section_id,
                'link_removed' => isset($link_removed) ? $link_removed : false
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'message' => 'Field removed from section (but table-section link preserved for other fields)',
                'section_id' => $section_id,
                'other_fields_exist' => $other_fields_exist
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'warning',
            'message' => 'Field was not assigned to any section'
        ]);
    }
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}

$mysqli->close();
?> 