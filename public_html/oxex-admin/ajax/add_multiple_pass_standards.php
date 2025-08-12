<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();

// Ensure proper access control
if(!(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'DV'))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get form data
    $tbid = isset($_POST['tbid']) ? (int)$_POST['tbid'] : 0;
    $parent_standard_id = isset($_POST['parent_standard_id']) && !empty($_POST['parent_standard_id']) ? (int)$_POST['parent_standard_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $standards = isset($_POST['standards']) ? $_POST['standards'] : [];

    // Validation
    if ($tbid <= 0) {
        throw new Exception('Invalid table selected');
    }

    if (empty($standards) || !is_array($standards)) {
        throw new Exception('No standards provided');
    }

    // Validate parent standard if provided
    if ($parent_standard_id) {
        $parent_stmt = $mysqli->prepare("SELECT tbid FROM pass_standards WHERE psid = ?");
        $parent_stmt->bind_param("i", $parent_standard_id);
        $parent_stmt->execute();
        $parent_result = $parent_stmt->get_result();
        
        if ($parent_result->num_rows === 0) {
            throw new Exception('Invalid parent standard selected');
        }
        
        $parent_data = $parent_result->fetch_assoc();
        if ($parent_data['tbid'] != $tbid) {
            throw new Exception('Parent standard must be from the same table');
        }
        $parent_stmt->close();
    }

    // Start transaction
    $mysqli->begin_transaction();

    $created_count = 0;
    $errors = [];

    foreach ($standards as $index => $standard) {
        // Validate each standard
        if (empty($standard['standard_name'])) {
            $errors[] = "Standard name is required for row " . ($index + 1);
            continue;
        }

        if (empty($standard['requirement_type'])) {
            $errors[] = "Requirement type is required for row " . ($index + 1);
            continue;
        }

        if (empty($standard['required_value']) || !is_numeric($standard['required_value'])) {
            $errors[] = "Valid required value is required for row " . ($index + 1);
            continue;
        }

        // For certain requirement types, field is required
        if (in_array($standard['requirement_type'], ['UNIQUE_VALUES', 'TOTAL_COUNT', 'UNIQUE_VALUES_IN_RANGE'])) {
            if (empty($standard['stid'])) {
                $errors[] = "Field selection is required for " . str_replace('_', ' ', $standard['requirement_type']) . " in row " . ($index + 1);
                continue;
            }
        }

        // Check if field belongs to the selected table
        if (!empty($standard['stid'])) {
            $field_stmt = $mysqli->prepare("
                SELECT tf.tfid 
                FROM tab_fields tf 
                WHERE tf.tbid = ? AND tf.stid = ?
            ");
            $field_stmt->bind_param("ii", $tbid, $standard['stid']);
            $field_stmt->execute();
            $field_result = $field_stmt->get_result();
            
            if ($field_result->num_rows === 0) {
                $errors[] = "Selected field does not belong to the selected table in row " . ($index + 1);
                $field_stmt->close();
                continue;
            }
            $field_stmt->close();
        }

        // Insert the standard
        $stmt = $mysqli->prepare("
            INSERT INTO pass_standards 
            (standard_name, tbid, stid, requirement_type, required_value, field_value, parent_standard_id, is_active, who_by, date_added) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())
        ");

        $stid = !empty($standard['stid']) ? (int)$standard['stid'] : null;
        $field_value = !empty($standard['field_value']) ? $standard['field_value'] : null;
        
        $stmt->bind_param("siisdsii", 
            $standard['standard_name'],
            $tbid,
            $stid,
            $standard['requirement_type'],
            $standard['required_value'],
            $field_value,
            $parent_standard_id,
            $is_active,
            $usrkey
        );

        if ($stmt->execute()) {
            $created_count++;
        } else {
            $errors[] = "Failed to create standard '" . $standard['standard_name'] . "': " . $stmt->error;
        }
        
        $stmt->close();
    }

    if (!empty($errors)) {
        // Rollback transaction if there were errors
        $mysqli->rollback();
        echo json_encode([
            'status' => 'error', 
            'message' => 'Some standards could not be created: ' . implode('; ', $errors)
        ]);
        exit();
    }

    // Commit transaction
    $mysqli->commit();

    // Set success message
    $_SESSION['flash_message'] = [
        'type' => 'success', 
        'message' => "Successfully created {$created_count} pass standard(s)."
    ];

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($mysqli->connect_errno === 0) {
        $mysqli->rollback();
    }
    
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    // Rollback transaction on error
    if ($mysqli->connect_errno === 0) {
        $mysqli->rollback();
    }
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'A system error occurred: ' . $e->getMessage()
    ]);
}
?>
