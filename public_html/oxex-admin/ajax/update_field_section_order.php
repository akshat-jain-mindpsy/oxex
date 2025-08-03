<?php
require_once '../../OXEXfolder/config.php';
require_once '../../OXEXfolder/u_functions.php';
sec_session_start();

// Basic security check
if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    // Check for other admin privileges as fallback
    if (isset($_SESSION['admintype']) && in_array($_SESSION['admintype'], ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
        // Allow access if admin type is valid
    } else {
        // Debug info
        $session_info = '';
        if (isset($_SESSION)) {
            $session_info = 'Session vars: ';
            foreach ($_SESSION as $key => $value) {
                if ($key != 'password' && $key != 'username') { // Don't expose sensitive info
                    $session_info .= "$key=" . (is_array($value) ? 'array' : $value) . ', ';
                } else {
                    $session_info .= "$key=******, ";
                }
            }
        } else {
            $session_info = 'No session variables found.';
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized access. Please login as admin.',
            'debug' => $session_info
        ]);
        exit();
    }
}

// Check required parameters
if (!isset($_POST['field_id']) || !isset($_POST['table_id']) || !isset($_POST['section_fields'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameters'
    ]);
    exit();
}

// Get parameters
$field_id = (int)$_POST['field_id'];
$table_id = (int)$_POST['table_id'];
$section_id = isset($_POST['section_id']) && $_POST['section_id'] !== "null" ? (int)$_POST['section_id'] : null;
$section_fields = json_decode($_POST['section_fields'], true);

// Validate data
if (!is_array($section_fields)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid section fields data'
    ]);
    exit();
}

// Validate section_id if it's not null
if ($section_id !== null) {
    // Check if the section exists in field_sections
    $check_section = $mysqli->prepare("SELECT section_id FROM field_sections WHERE section_id = ?");
    $check_section->bind_param("i", $section_id);
    $check_section->execute();
    $check_section->store_result();
    
    if ($check_section->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid section ID: Section does not exist'
        ]);
        exit();
    }
    $check_section->close();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // First, remove the field from its current section (if any)
    $delete_stmt = $mysqli->prepare("
        DELETE FROM section_table_link 
        WHERE stid = ? AND tbid = ?
    ");
    $delete_stmt->bind_param("ii", $field_id, $table_id);
    $delete_stmt->execute();
    
    // If the field is being assigned to a section
    if ($section_id !== null) {
        // Insert the field into its new section with the correct display order
        $order = 0;
        
        // Find the order for this field
        foreach ($section_fields as $field) {
            if ($field['fieldId'] == $field_id) {
                $order = $field['order'];
                break;
            }
        }
        
        // Insert the field into the section
        $insert_stmt = $mysqli->prepare("
            INSERT INTO section_table_link 
            (section_id, tbid, stid, display_order) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE display_order = VALUES(display_order)
        ");
        $insert_stmt->bind_param("iiii", $section_id, $table_id, $field_id, $order);
        $insert_stmt->execute();
        
        // Update the display order for other fields in the section
        foreach ($section_fields as $field) {
            if ($field['fieldId'] != $field_id) {
                $update_order_stmt = $mysqli->prepare("
                    INSERT INTO section_table_link 
                    (section_id, tbid, stid, display_order) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE display_order = VALUES(display_order)
                ");
                $update_order_stmt->bind_param("iiii", $section_id, $table_id, $field['fieldId'], $field['order']);
                $update_order_stmt->execute();
            }
        }
        
        // Update the field's section_id in select_types as a fallback
        $update_stmt = $mysqli->prepare("
            UPDATE select_types 
            SET section_id = ? 
            WHERE stid = ?
        ");
        $update_stmt->bind_param("ii", $section_id, $field_id);
        $update_stmt->execute();
    } else {
        // Field is moved to unsectioned area
        // Update the field's section_id in select_types to NULL
        $update_stmt = $mysqli->prepare("
            UPDATE select_types 
            SET section_id = NULL 
            WHERE stid = ?
        ");
        $update_stmt->bind_param("i", $field_id);
        $update_stmt->execute();
        
        // Update the sort_order in tab_fields based on the new position
        $order = 0;
        foreach ($section_fields as $index => $field) {
            if ($field['fieldId'] == $field_id) {
                $order = $index + 1;
                break;
            }
        }
        
        if ($order > 0) {
            $update_order_stmt = $mysqli->prepare("
                UPDATE tab_fields 
                SET sort_order = ? 
                WHERE stid = ? AND tbid = ?
            ");
            $update_order_stmt->bind_param("iii", $order, $field_id, $table_id);
            $update_order_stmt->execute();
        }
    }
    
    // Commit the transaction
    $mysqli->commit();
    
    // Return success
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Field order updated successfully'
    ]);
    exit();
} catch (Exception $e) {
    // Rollback the transaction
    $mysqli->rollback();
    
    // Log the error for debugging
    error_log("Error in update_field_section_order.php: " . $e->getMessage());
    
    // Return error with more details
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'field_id' => $field_id,
            'table_id' => $table_id,
            'section_id' => $section_id,
            'field_count' => count($section_fields)
        ]
    ]);
    exit();
}
?> 