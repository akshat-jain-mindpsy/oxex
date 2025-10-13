<?php
header('Content-Type: application/json');

include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Ensure proper access control
if(!(login_check($pdo) == true && 
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
    $supabase_pdo->beginTransaction();
    
    // Update the field's section_id in select_types
    $update_field = $supabase_pdo->prepare("UPDATE select_types SET section_id = ? WHERE stid = ?");
    $update_field->execute([$section_id, $field_id]);
    $field_updated = $update_field->rowCount() > 0;
    
    // Check if link between section and table already exists
    $check_link = $supabase_pdo->prepare("SELECT COUNT(*) as link_exists FROM section_table_link WHERE section_id = ? AND tbid = ?");
    $check_link->execute([$section_id, $table_id]);
    $row = $check_link->fetch(PDO::FETCH_ASSOC);
    $link_exists = (int)$row['link_exists'] > 0;
    
    // Only create the link if it doesn't exist
    if(!$link_exists) {
        // Get next display order
        $order_query = $supabase_pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM section_table_link WHERE section_id = ?");
        $order_query->execute([$section_id]);
        $order_row = $order_query->fetch(PDO::FETCH_ASSOC);
        $display_order = (int)$order_row['next_order'];
        
        // Create link between section and table
        $create_link = $supabase_pdo->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, ?)");
        $create_link->execute([$section_id, $table_id, $display_order]);
        $link_created = $create_link->rowCount() > 0;
    } else {
        $link_created = false; // Link already existed
    }
    
    // Commit transaction
    $supabase_pdo->commit();
    
    // Respond with success
    echo json_encode([
        'status' => 'success',
        'message' => 'Field assigned to section successfully' . ($link_exists ? ' (table already linked)' : ''),
        'field_updated' => $field_updated,
        'link_created' => $link_created
    ]);
} catch (Exception $e) {
    // Rollback on error
    $supabase_pdo->rollBack();
    
    // Special handling for duplicate entry error (Postgres uses different error codes)
    if($e->getCode() === '23505') { // Postgres unique violation
        echo json_encode([
            'status' => 'warning',
            'message' => 'Field assigned to section, but table link already exists',
            'error_code' => 23505
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage(),
            'error_code' => $e->getCode()
        ]);
    }
}
?> 