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
if(!isset($_POST['field_id']) || !isset($_POST['table_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

$field_id = (int)$_POST['field_id'];
$table_id = (int)$_POST['table_id'];
$section_id = isset($_POST['section_id']) && $_POST['section_id'] !== 'null' ? (int)$_POST['section_id'] : null;
$field_order = isset($_POST['field_order']) ? (int)$_POST['field_order'] : 1;
$use_link_table = isset($_POST['use_link_table']) && $_POST['use_link_table'] === 'true';

try {
    // Initialize PDO connection
    $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
    if (!$pdo) {
        throw new Exception("Database connection not available");
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get the current section_id of the field
    $get_current = $pdo->prepare("SELECT section_id FROM select_types WHERE stid = ?");
    $get_current->execute([$field_id]);
    $row = $get_current->fetch(PDO::FETCH_ASSOC);
    $current_section_id = $row ? $row['section_id'] : null;
    
    // Update the field's section_id in select_types
    $update_field = $pdo->prepare("UPDATE select_types SET section_id = ? WHERE stid = ?");
    $update_field->execute([$section_id, $field_id]);
    $field_updated = $update_field->rowCount() > 0;
    
    // If we're using the section_table_link approach
    if ($use_link_table) {
        // If moving to a section (not to null/unsectioned)
        if ($section_id !== null) {
            // Check if link between section and table already exists
            $check_link = $pdo->prepare("SELECT COUNT(*) as link_exists FROM section_table_link WHERE section_id = ? AND tbid = ?");
            $check_link->execute([$section_id, $table_id]);
            $link_row = $check_link->fetch(PDO::FETCH_ASSOC);
            $link_exists = (int)$link_row['link_exists'] > 0;
            
            // Only create the link if it doesn't exist
            if (!$link_exists) {
                // Get next display order
                $order_query = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM section_table_link WHERE section_id = ?");
                $order_query->execute([$section_id]);
                $order_row = $order_query->fetch(PDO::FETCH_ASSOC);
                $display_order = (int)$order_row['next_order'];
                
                // Create link between section and table
                $create_link = $pdo->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, ?)");
                
                try {
                    $create_link->execute([$section_id, $table_id, $display_order]);
                    $link_created = $create_link->rowCount() > 0;
                } catch (PDOException $e) {
                    // If duplicate entry, it's not a critical error
                    if ($e->getCode() != 1062) { // 1062 is duplicate entry error
                        throw $e;
                    }
                    $link_created = false;
                }
            }
        }
        
        // Check if there are any other fields in the previous section for this table
        if ($current_section_id !== null && $current_section_id != $section_id) {
            $check_fields = $pdo->prepare("
                SELECT COUNT(*) as field_count 
                FROM select_types st
                JOIN tab_fields tf ON st.stid = tf.stid
                WHERE st.section_id = ? AND tf.tbid = ? AND st.stid != ?
            ");
            $check_fields->execute([$current_section_id, $table_id, $field_id]);
            $fields_row = $check_fields->fetch(PDO::FETCH_ASSOC);
            $other_fields_exist = (int)$fields_row['field_count'] > 0;
            
            // Only remove section_table_link if no other fields are using this section in this table
            if (!$other_fields_exist) {
                $remove_link = $pdo->prepare("DELETE FROM section_table_link WHERE section_id = ? AND tbid = ?");
                $remove_link->execute([$current_section_id, $table_id]);
                $link_removed = $remove_link->rowCount() > 0;
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Response
    echo json_encode([
        'status' => 'success',
        'message' => $section_id === null ? 'Field moved to unsectioned fields' : 'Field moved to new section',
        'field_updated' => $field_updated,
        'previous_section' => $current_section_id,
        'new_section' => $section_id,
        'link_details' => $use_link_table ? [
            'link_created' => isset($link_created) ? $link_created : false,
            'link_removed' => isset($link_removed) ? $link_removed : false,
            'other_fields_exist' => isset($other_fields_exist) ? $other_fields_exist : false
        ] : null
    ]);
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    // Special handling for duplicate entry error
    if($e instanceof PDOException && $e->getCode() === 1062) {
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
?>