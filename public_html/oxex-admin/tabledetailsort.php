<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Ensure proper access control
if(!(login_check($mysqli) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Debugging: Log incoming data
error_log("Incoming POST data: " . print_r($_POST, true));

// Handle reset order action
if (isset($_POST['action']) && $_POST['action'] == 'reset_order') {
    $tbid = isset($_POST['tbid']) ? (int)$_POST['tbid'] : 0;
    
    // Fetch original order based on stid
    $reset_stmt = $mysqli->prepare("
        UPDATE tab_fields 
        SET sort_order = original_order 
        WHERE tbid = ?
    ");
    $reset_stmt->bind_param("i", $tbid);
    $reset_stmt->execute();
    
    if ($reset_stmt->affected_rows > 0) {
        // Fetch the reset fields to return to the client
        $fields_stmt = $mysqli->prepare("
            SELECT stid, str, original_order 
            FROM tab_fields 
            JOIN select_types ON tab_fields.stid = select_types.stid
            WHERE tbid = ? 
            ORDER BY original_order
        ");
        $fields_stmt->bind_param("i", $tbid);
        $fields_stmt->execute();
        $result = $fields_stmt->get_result();
        
        $reset_fields = [];
        while ($row = $result->fetch_assoc()) {
            $reset_fields[] = $row;
        }
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Order reset successfully',
            'reset_fields' => $reset_fields
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'No fields found or failed to reset'
        ]);
    }
    
    $reset_stmt->close();
    exit();
}

// Handle saving new order
if (isset($_POST['save_order']) && $_POST['save_order'] == true) {
    $tbid = isset($_POST['tbid']) ? (int)$_POST['tbid'] : 0;
    $positions = isset($_POST['positions']) ? $_POST['positions'] : [];
    
    // Validate inputs
    if ($tbid <= 0 || empty($positions)) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invalid table ID or positions'
        ]);
        exit();
    }
    
    // Start transaction for better error handling
    $mysqli->begin_transaction();
    
    try {
        // Prepare statement for updating sort order
        $stmt = $mysqli->prepare("
            UPDATE tab_fields 
            SET sort_order = ? 
            WHERE stid = ? AND tbid = ?
        ");
        
        // Track success
        $success = true;
        
        // Update each field's sort order
        foreach ($positions as $index => $stid) {
            $sort_order = $index + 1;  // 1-based indexing
            $stmt->bind_param("iii", $sort_order, $stid, $tbid);
            
            if (!$stmt->execute()) {
                $success = false;
                break;
            }
        }
        
        // Commit or rollback
        if ($success) {
            $mysqli->commit();
            echo json_encode([
                'status' => 'success', 
                'message' => 'Field order saved successfully',
                'positions' => $positions
            ]);
        } else {
            $mysqli->rollback();
            echo json_encode([
                'status' => 'error', 
                'message' => 'Failed to save field order'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $mysqli->rollback();
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    
    exit();
}

$mysqli->close();
?>