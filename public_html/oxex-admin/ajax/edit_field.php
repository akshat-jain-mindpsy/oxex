<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    include '../../OXEXfolder/config.php';
    include '../../OXEXfolder/u_functions.php';
    sec_session_start();
    include '../incl/sess.php';

    // Debug logging for incoming requests
    error_log("Edit field request: " . json_encode($_REQUEST));

    // Check if the database connection is valid
    if (!isset($supabase_pdo)) {
        throw new Exception("Database connection failed: PDO connection not established");
    }

    // Get admin type from session
    $admintype = isset($_SESSION['admintype']) ? $_SESSION['admintype'] : '';

    // Check login and permissions
    if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Unauthorized access'
        ]);
        exit;
    }

    // Get request type - either GET (retrieve field data) or POST (update field data)
    $request_method = $_SERVER['REQUEST_METHOD'];

    // For GET requests - return field data
    if ($request_method === 'GET') {
        // Validate input
        $field_id = isset($_GET['field_id']) ? (int)$_GET['field_id'] : 0;
        $table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

        if ($field_id <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid field ID'
            ]);
            exit;
        }

        // Use prepared statements for security instead of direct SQL
        $field_stmt = $supabase_pdo->prepare("SELECT stid, str AS field_name, single AS field_type, section_id FROM select_types WHERE stid = ? LIMIT 1");
        $field_stmt->execute([$field_id]);
        $field = $field_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$field) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Field not found'
            ]);
            exit;
        }
        
        // Get field options using prepared statement
        $options_stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE stid = ? ORDER BY select_val ASC");
        $options_stmt->execute([$field_id]);
        $options = [];
        
        while ($option = $options_stmt->fetch(PDO::FETCH_ASSOC)) {
            $options[] = $option['select_val'];
        }
        
        $response = [
            'status' => 'success',
            'field_id' => $field_id,
            'field_name' => $field['field_name'],
            'field_type' => $field['field_type'],
            'options' => $options,
            'table_id' => $table_id, // Include table_id in response
            'current_section_id' => $field['section_id'] // Include current section ID
        ];
        
        error_log("Edit field GET response: " . json_encode($response));
        echo json_encode($response);
    }
    // For POST requests - update field data
    else if ($request_method === 'POST') {
        // Validate input
        $field_id = isset($_POST['field_id']) ? (int)$_POST['field_id'] : 0;
        $field_name = isset($_POST['field_name']) ? trim($_POST['field_name']) : '';
        $field_type = isset($_POST['field_type']) ? (int)$_POST['field_type'] : -1;
        $field_options = isset($_POST['field_options']) ? trim($_POST['field_options']) : '';
        $field_section = isset($_POST['field_section']) ? $_POST['field_section'] : '';
        $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;

        // Validate field_id and at least one of field_name or field_type
        if ($field_id <= 0 || ($field_name === '' && $field_type < 0)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid input parameters'
            ]);
            exit;
        }

        // Begin a transaction for multiple database operations
        $supabase_pdo->beginTransaction();
        
        try {
            // If only updating the field type without a name
            if (empty($field_name)) {
                // Get the current field name
                $name_query = $supabase_pdo->prepare("SELECT str FROM select_types WHERE stid = ?");
                $name_query->execute([$field_id]);
                $field_data = $name_query->fetch(PDO::FETCH_ASSOC);
                
                if (!$field_data) {
                    throw new Exception("Field not found");
                }
                
                $field_name = $field_data['str'];
            }
            
            // Use prepared statements for security
            $update_stmt = $supabase_pdo->prepare("UPDATE select_types SET str = ?, single = ? WHERE stid = ?");
            if (!$update_stmt->execute([$field_name, $field_type, $field_id])) {
                throw new Exception("Failed to update field");
            }
            
            // Handle section assignment if provided
            if ($field_section !== '') {
                $section_id = $field_section === '' ? null : (int)$field_section;
                $section_stmt = $supabase_pdo->prepare("UPDATE select_types SET section_id = ? WHERE stid = ?");
                
                if (!$section_stmt->execute([$section_id, $field_id])) {
                    throw new Exception("Failed to update field section");
                }
            }
            
            // Handle options for select fields (type 0 or 1)
            if ($field_type == 0 || $field_type == 1) {
                // First, delete existing options
                $delete_stmt = $supabase_pdo->prepare("DELETE FROM select_gen WHERE stid = ?");
                
                if (!$delete_stmt->execute([$field_id])) {
                    throw new Exception("Failed to delete existing options");
                }
                
                // Then, insert new options if they exist
                if (!empty($field_options)) {
                    $options = explode("\n", $field_options);
                    $options = array_map('trim', $options);
                    $options = array_filter($options); // Remove empty lines
                    
                    if (count($options) > 0) {
                        $insert_stmt = $supabase_pdo->prepare("INSERT INTO select_gen (stid, select_val) VALUES (?, ?)");
                        
                        foreach ($options as $option_text) {
                            if (empty($option_text)) continue;
                            
                            if (!$insert_stmt->execute([$field_id, $option_text])) {
                                throw new Exception("Failed to insert option");
                            }
                        }
                    }
                }
                // For select fields with no options submitted but field type is being changed
                else if (isset($_POST['field_type']) && !isset($_POST['field_options'])) {
                    // This case is when only updating field type without changing options
                    // For safety, we'll keep any existing options
                    $get_options_stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE stid = ? ORDER BY select_val");
                    $get_options_stmt->execute([$field_id]);
                    $options_result = $get_options_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($options_result) > 0) {
                        $insert_stmt = $supabase_pdo->prepare("INSERT INTO select_gen (stid, select_val) VALUES (?, ?)");
                        
                        foreach ($options_result as $option) {
                            $option_text = $option['select_val'];
                            
                            if (!$insert_stmt->execute([$field_id, $option_text])) {
                                throw new Exception("Failed to reinsert option");
                            }
                        }
                    }
                }
            }
            
            // Commit the transaction
            $supabase_pdo->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Field updated successfully',
                'field_id' => $field_id,
                'field_name' => $field_name,
                'field_type' => $field_type,
                'table_id' => $table_id, // Include table_id in response
                'reload_required' => true
            ]);
        } catch (Exception $e) {
            // Roll back the transaction on error
            if ($supabase_pdo->inTransaction()) {
                $supabase_pdo->rollBack();
            }
            
            error_log("Error in edit_field.php field update: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'debug_info' => [
                    'field_id' => $field_id,
                    'field_type' => $field_type,
                    'has_name' => !empty($field_name),
                    'table_id' => $table_id
                ]
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request method'
        ]);
    }
} catch (Exception $e) {
    error_log("Error in edit_field.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// PDO connection is managed by config.php
?> 