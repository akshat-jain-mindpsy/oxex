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
    // Initialize PDO connection
    $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
    if (!$pdo) {
        throw new Exception("Database connection not available");
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // If section_id is null, we're dealing with unsectioned fields
    if($section_id === null) {
        // Simply update the sort_order in tab_fields
        $order_stmt = $pdo->prepare("UPDATE tab_fields SET sort_order = ? WHERE tbid = ? AND stid = ?");
        
        foreach($field_ids as $index => $field_id) {
            $order = $index + 1; // Start from 1
            $order_stmt->execute([$order, $table_id, $field_id]);
        }
    } else {
        // First check if there's an entry in section_table_link for this section and table
        $check_link = $pdo->prepare("SELECT COUNT(*) as link_exists FROM section_table_link WHERE section_id = ? AND tbid = ?");
        $check_link->execute([$section_id, $table_id]);
        $row = $check_link->fetch(PDO::FETCH_ASSOC);
        $link_exists = (int)$row['link_exists'] > 0;
        
        // If no link exists, create one
        if(!$link_exists) {
            $create_link = $pdo->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, 1)");
            
            try {
                $create_link->execute([$section_id, $table_id]);
            } catch(PDOException $e) {
                // Skip duplicate entries
                if($e->getCode() != 1062) {
                    throw $e;
                }
            }
        }
        
        // Update each field to ensure it's assigned to this section
        $update_field = $pdo->prepare("UPDATE select_types SET section_id = ? WHERE stid = ?");
        
        foreach($field_ids as $field_id) {
            $update_field->execute([$section_id, $field_id]);
        }
        
        // Update the sort_order in tab_fields for consistency
        $sort_stmt = $pdo->prepare("UPDATE tab_fields SET sort_order = ? WHERE tbid = ? AND stid = ?");
        
        foreach($field_ids as $index => $field_id) {
            $order = $index + 1; // Start from 1
            $sort_stmt->execute([$order, $table_id, $field_id]);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
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
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Error updating field order: ' . $e->getMessage(),
        'error_code' => $e instanceof PDOException ? $e->getCode() : 0
    ]);
}
?> 