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
if(!isset($_POST['field_id']) || !isset($_POST['table_id']) || !isset($_POST['section_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

$field_id = (int)$_POST['field_id'];
$table_id = (int)$_POST['table_id'];
$section_id = (int)$_POST['section_id'];

// Validate parameters
if($field_id <= 0 || $table_id <= 0 || $section_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameter values']);
    exit();
}

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Update the field's section_id in select_types
    $update_field = $mysqli->prepare("UPDATE select_types SET section_id = ? WHERE stid = ?");
    $update_field->bind_param("ii", $section_id, $field_id);
    $update_field->execute();
    $field_updated = $update_field->affected_rows > 0;
    $update_field->close();
    
    // Check if link between section and table already exists
    $check_link = $mysqli->prepare("SELECT COUNT(*) as link_exists FROM section_table_link WHERE section_id = ? AND tbid = ?");
    $check_link->bind_param("ii", $section_id, $table_id);
    $check_link->execute();
    $result = $check_link->get_result();
    $row = $result->fetch_assoc();
    $link_exists = (int)$row['link_exists'] > 0;
    $check_link->close();
    
    // Only create the link if it doesn't exist
    if(!$link_exists) {
        // Get next display order
        $order_query = $mysqli->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM section_table_link WHERE section_id = ?");
        $order_query->bind_param("i", $section_id);
        $order_query->execute();
        $order_result = $order_query->get_result();
        $order_row = $order_result->fetch_assoc();
        $display_order = (int)$order_row['next_order'];
        $order_query->close();
        
        // Create link between section and table
        $create_link = $mysqli->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, ?)");
        $create_link->bind_param("iii", $section_id, $table_id, $display_order);
        $create_link->execute();
        $link_created = $create_link->affected_rows > 0;
        $create_link->close();
    } else {
        $link_created = false; // Link already existed
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Respond with success
    echo json_encode([
        'status' => 'success',
        'message' => 'Field assigned to section successfully' . ($link_exists ? ' (table already linked)' : ''),
        'field_updated' => $field_updated,
        'link_created' => $link_created
    ]);
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    // Special handling for duplicate entry error
    if($e->getCode() === 1062) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'Field assigned to section, but table link already exists',
            'error_code' => 1062
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage(),
            'error_code' => $e->getCode()
        ]);
    }
}

$mysqli->close();
?> 