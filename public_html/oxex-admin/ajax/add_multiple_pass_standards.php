<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();

// Ensure proper access control
if(!(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'DV'))) {
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
        $parent_stmt = $supabase_pdo->prepare("SELECT tbid FROM pass_standards WHERE psid = ?");
        $parent_stmt->execute([$parent_standard_id]);
        $parent_row = $parent_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$parent_row) {
            throw new Exception('Invalid parent standard selected');
        }
        
        if ((int)$parent_row['tbid'] != $tbid) {
            throw new Exception('Parent standard must be from the same table');
        }
    }

    // Start transaction
    $supabase_pdo->beginTransaction();

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
            $field_stmt = $supabase_pdo->prepare("SELECT tf.tfid FROM tab_fields tf WHERE tf.tbid = ? AND tf.stid = ?");
            $field_stmt->execute([$tbid, $standard['stid']]);
            $field_row = $field_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$field_row) {
                $errors[] = "Selected field does not belong to the selected table in row " . ($index + 1);
                continue;
            }
        }

        // Insert the standard
        $stmt = $supabase_pdo->prepare(
            "INSERT INTO pass_standards (standard_name, tbid, stid, requirement_type, required_value, field_value, parent_standard_id, is_active, who_by, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, EXTRACT(EPOCH FROM NOW()))"
        );

        $stid = !empty($standard['stid']) ? (int)$standard['stid'] : null;
        $field_value = !empty($standard['field_value']) ? $standard['field_value'] : null;
        
        if ($stmt->execute([
            $standard['standard_name'],
            $tbid,
            $stid,
            $standard['requirement_type'],
            $standard['required_value'],
            $field_value,
            $parent_standard_id,
            $is_active,
            $usrkey
        ])) {
            $created_count++;
        } else {
            $errors[] = "Failed to create standard '" . $standard['standard_name'] . "'";
        }
    }

    if (!empty($errors)) {
        // Rollback transaction if there were errors
        $supabase_pdo->rollBack();
        echo json_encode([
            'status' => 'error', 
            'message' => 'Some standards could not be created: ' . implode('; ', $errors)
        ]);
        exit();
    }

    // Commit transaction
    $supabase_pdo->commit();

    // Set success message
    $_SESSION['flash_message'] = [
        'type' => 'success', 
        'message' => "Successfully created {$created_count} pass standard(s)."
    ];

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($supabase_pdo->inTransaction()) {
        $supabase_pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    // Rollback transaction on error
    if ($supabase_pdo->inTransaction()) {
        $supabase_pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'A system error occurred: ' . $e->getMessage()
    ]);
}
?>
