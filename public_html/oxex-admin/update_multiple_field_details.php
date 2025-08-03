<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check if user is logged in and has appropriate permissions
if (login_check($mysqli) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit;
}

// Get raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!isset($data['tbid']) || !isset($data['changes']) || !is_array($data['changes'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input'
    ]);
    exit;
}

$tbid = (int)$data['tbid'];
$changes = $data['changes'];
$optionsChanges = isset($data['optionsChanges']) ? $data['optionsChanges'] : [];

// Start transaction
$mysqli->begin_transaction();

try {
    // Prepare statement for updating field details
    $stmt = $mysqli->prepare("UPDATE select_types SET str = COALESCE(?, str), single = COALESCE(?, single) WHERE stid = ?");

    // Process each field change
    foreach ($changes as $stid => $change) {
        $name = isset($change['name']) ? $change['name'] : null;
        $type = isset($change['type']) ? (int)$change['type'] : null;
        
        // If changing field type, check if we need to handle existing data
        if ($type !== null) {
            // Get current field type
            $type_check_stmt = $mysqli->prepare("SELECT single FROM select_types WHERE stid = ?");
            $type_check_stmt->bind_param("i", $stid);
            $type_check_stmt->execute();
            $type_result = $type_check_stmt->get_result();
            
            if ($row = $type_result->fetch_assoc()) {
                $current_type = (int)$row['single'];
                
                // If changing from non-select to select type, ensure we have a clean slate
                if (($type === 0 || $type === 1) && !($current_type === 0 || $current_type === 1)) {
                    // Clear any existing options that might be there
                    $clear_stmt = $mysqli->prepare("DELETE FROM select_gen WHERE stid = ?");
                    $clear_stmt->bind_param("i", $stid);
                    $clear_stmt->execute();
                    $clear_stmt->close();
                }
                
                // If changing from select to non-select type, we should clean up options
                if (!($type === 0 || $type === 1) && ($current_type === 0 || $current_type === 1)) {
                    // Clear existing options as they won't be needed
                    $clear_stmt = $mysqli->prepare("DELETE FROM select_gen WHERE stid = ?");
                    $clear_stmt->bind_param("i", $stid);
                    $clear_stmt->execute();
                    $clear_stmt->close();
                }
            }
            $type_check_stmt->close();
        }
        
        // Update the field
        $stmt->bind_param("sii", $name, $type, $stid);
        $stmt->execute();

        if ($stmt->errno) {
            throw new Exception("Error updating field $stid: " . $stmt->error);
        }
    }
    $stmt->close();
    
    // Process options changes if any
    if (!empty($optionsChanges)) {
        foreach ($optionsChanges as $stid => $options) {
            // Get field type (single/multi)
            $type_stmt = $mysqli->prepare("SELECT single FROM select_types WHERE stid = ?");
            $type_stmt->bind_param("i", $stid);
            $type_stmt->execute();
            $result = $type_stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $single = $row['single'];
            } else {
                throw new Exception("Field not found: $stid");
            }
            $type_stmt->close();
            
            // First, get existing options to determine which to delete
            $existing_stmt = $mysqli->prepare("SELECT pid FROM select_gen WHERE stid = ?");
            $existing_stmt->bind_param("i", $stid);
            $existing_stmt->execute();
            $existing_result = $existing_stmt->get_result();
            
            $existing_ids = [];
            while ($row = $existing_result->fetch_assoc()) {
                $existing_ids[] = (int)$row['pid'];
            }
            $existing_stmt->close();
            
            // Track which IDs are in the new set
            $new_ids = [];
            foreach ($options as $option) {
                if (isset($option['id']) && $option['id'] > 0) {
                    $new_ids[] = (int)$option['id'];
                }
            }
            
            // Delete options that are no longer in the list
            $to_delete = array_diff($existing_ids, $new_ids);
            if (!empty($to_delete)) {
                $delete_placeholders = implode(',', array_fill(0, count($to_delete), '?'));
                $delete_stmt = $mysqli->prepare("DELETE FROM select_gen WHERE pid IN ($delete_placeholders)");
                
                $delete_types = str_repeat('i', count($to_delete));
                $delete_stmt->bind_param($delete_types, ...$to_delete);
                $delete_stmt->execute();
                $delete_stmt->close();
            }
            
            // Update existing options and add new ones
            $update_stmt = $mysqli->prepare("UPDATE select_gen SET select_val = ? WHERE pid = ?");
            $insert_stmt = $mysqli->prepare("INSERT INTO select_gen (stid, single, select_val) VALUES (?, ?, ?)");
            
            foreach ($options as $option) {
                $value = trim($option['value']);
                if (empty($value)) continue;
                
                if (isset($option['id']) && $option['id'] > 0) {
                    // Update existing option
                    $update_stmt->bind_param("si", $value, $option['id']);
                    $update_stmt->execute();
                } else {
                    // Add new option
                    $insert_stmt->bind_param("iis", $stid, $single, $value);
                    $insert_stmt->execute();
                }
            }
            
            $update_stmt->close();
            $insert_stmt->close();
        }
    }

    // Commit transaction
    $mysqli->commit();

    echo json_encode([
        'status' => 'success',
        'message' => count($changes) . ' field(s) updated successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction
    $mysqli->rollback();

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 