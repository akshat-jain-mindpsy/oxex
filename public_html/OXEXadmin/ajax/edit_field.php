<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    include '../../OXEXfolder/config.php';
    include '../../OXEXfolder/u_functions.php';
    sec_session_start();

    // Debug logging for incoming requests
    error_log("Edit field request: " . json_encode($_REQUEST));

    // Check if the database connection is valid
    if (!isset($mysqli) || $mysqli->connect_errno) {
        throw new Exception("Database connection failed: " . ($mysqli ? $mysqli->connect_error : "Connection not established"));
    }

    // Get admin type from session
    $admintype = isset($_SESSION['admintype']) ? $_SESSION['admintype'] : '';

    // Check login and permissions
    if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
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
        $field_stmt = $mysqli->prepare("SELECT stid, str AS field_name, single AS field_type FROM select_types WHERE stid = ? LIMIT 1");
        $field_stmt->bind_param("i", $field_id);
        
        if (!$field_stmt->execute()) {
            throw new Exception("Database query failed: " . $mysqli->error);
        }
        
        $field_result = $field_stmt->get_result();
        
        if ($field_result->num_rows === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Field not found'
            ]);
            exit;
        }

        $field = $field_result->fetch_assoc();
        $field_stmt->close();
        
        // Get field options using prepared statement
        $options_stmt = $mysqli->prepare("SELECT select_val FROM select_gen WHERE stid = ? ORDER BY select_val ASC");
        $options_stmt->bind_param("i", $field_id);
        
        if (!$options_stmt->execute()) {
            throw new Exception("Options query failed: " . $mysqli->error);
        }
        
        $options_result = $options_stmt->get_result();
        $options = [];
        
        while ($option = $options_result->fetch_assoc()) {
            $options[] = $option['select_val'];
        }
        $options_stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'field_id' => $field_id,
            'field_name' => $field['field_name'],
            'field_type' => $field['field_type'],
            'options' => $options,
            'table_id' => $table_id // Include table_id in response
        ]);
    }
    // For POST requests - update field data
    else if ($request_method === 'POST') {
        // Validate input
        $field_id = isset($_POST['field_id']) ? (int)$_POST['field_id'] : 0;
        $field_name = isset($_POST['field_name']) ? trim($_POST['field_name']) : '';
        $field_type = isset($_POST['field_type']) ? (int)$_POST['field_type'] : -1;
        $field_options = isset($_POST['field_options']) ? trim($_POST['field_options']) : '';
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
        $mysqli->begin_transaction();
        
        try {
            // If only updating the field type without a name
            if (empty($field_name)) {
                // Get the current field name
                $name_query = $mysqli->prepare("SELECT str FROM select_types WHERE stid = ?");
                $name_query->bind_param("i", $field_id);
                $name_query->execute();
                $name_result = $name_query->get_result();
                
                if ($name_result->num_rows === 0) {
                    throw new Exception("Field not found");
                }
                
                $field_data = $name_result->fetch_assoc();
                $field_name = $field_data['str'];
                $name_query->close();
            }
            
            // Use prepared statements for security
            $update_stmt = $mysqli->prepare("UPDATE select_types SET str = ?, single = ? WHERE stid = ?");
            $update_stmt->bind_param("sii", $field_name, $field_type, $field_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update field: " . $mysqli->error);
            }
            $update_stmt->close();
            
            // Handle options for select fields (type 0 or 1)
            if ($field_type == 0 || $field_type == 1) {
                // First, delete existing options
                $delete_stmt = $mysqli->prepare("DELETE FROM select_gen WHERE stid = ?");
                $delete_stmt->bind_param("i", $field_id);
                
                if (!$delete_stmt->execute()) {
                    throw new Exception("Failed to delete existing options: " . $mysqli->error);
                }
                $delete_stmt->close();
                
                // Then, insert new options if they exist
                if (!empty($field_options)) {
                    $options = explode("\n", $field_options);
                    $options = array_map('trim', $options);
                    $options = array_filter($options); // Remove empty lines
                    
                    if (count($options) > 0) {
                        $insert_stmt = $mysqli->prepare("INSERT INTO select_gen (stid, select_val) VALUES (?, ?)");
                        
                        foreach ($options as $option_text) {
                            if (empty($option_text)) continue;
                            
                            $insert_stmt->bind_param("is", $field_id, $option_text);
                            
                            if (!$insert_stmt->execute()) {
                                throw new Exception("Failed to insert option: " . $mysqli->error);
                            }
                        }
                        $insert_stmt->close();
                    }
                }
                // For select fields with no options submitted but field type is being changed
                else if (isset($_POST['field_type']) && !isset($_POST['field_options'])) {
                    // This case is when only updating field type without changing options
                    // For safety, we'll keep any existing options
                    $get_options_stmt = $mysqli->prepare("SELECT select_val FROM select_gen WHERE stid = ? ORDER BY select_val");
                    $get_options_stmt->bind_param("i", $field_id);
                    $get_options_stmt->execute();
                    $options_result = $get_options_stmt->get_result();
                    
                    if ($options_result->num_rows > 0) {
                        $insert_stmt = $mysqli->prepare("INSERT INTO select_gen (stid, select_val) VALUES (?, ?)");
                        
                        while ($option = $options_result->fetch_assoc()) {
                            $option_text = $option['select_val'];
                            $insert_stmt->bind_param("is", $field_id, $option_text);
                            
                            if (!$insert_stmt->execute()) {
                                throw new Exception("Failed to reinsert option: " . $mysqli->error);
                            }
                        }
                        $insert_stmt->close();
                    }
                    $get_options_stmt->close();
                }
            }
            
            // Commit the transaction
            $mysqli->commit();
            
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
            $mysqli->rollback();
            
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

if (isset($mysqli)) {
    $mysqli->close();
}
?> 