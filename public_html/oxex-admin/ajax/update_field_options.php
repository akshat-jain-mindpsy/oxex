<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include config and functions - fix paths to use absolute paths
include_once __DIR__ . '/../../OXEXfolder/config.php';
include_once __DIR__ . '/../../OXEXfolder/u_functions.php';
sec_session_start();
include_once __DIR__ . '/../incl/sess.php';

// Debug log
error_log("update_field_options.php called");

// Check login and permissions
if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Default response
$response = [
    'status' => 'error',
    'message' => 'An unexpected error occurred'
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Check if required parameters exist
if (!isset($_POST['field_id']) || !isset($_POST['options'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

$field_id = intval($_POST['field_id']);
$field_type = isset($_POST['field_type']) ? intval($_POST['field_type']) : 0;
$options_string = $_POST['options'];
$options_array = !empty($options_string) ? explode('|', $options_string) : [];

error_log("Field ID: $field_id, Options: $options_string");

// Validate field ID
if ($field_id <= 0) {
    $response['message'] = 'Invalid field ID';
    echo json_encode($response);
    exit;
}

try {
    // First check if field_options column exists
    $check_column = $mysqli->query("SHOW COLUMNS FROM select_types LIKE 'field_options'");
    if ($check_column === false || $check_column->num_rows === 0) {
        // Column doesn't exist, redirect to update page
        $response = [
            'status' => 'error',
            'message' => 'Database structure needs update',
            'redirect' => '../db_update.php?tbid=' . $field_id
        ];
        echo json_encode($response);
        exit;
    }
    
    // Begin transaction
    $mysqli->begin_transaction();
    
    // 1. Update field_options in select_types
    $update_stmt = $mysqli->prepare("UPDATE select_types SET field_options = ? WHERE stid = ?");
    $update_stmt->bind_param("si", $options_string, $field_id);
    $update_result = $update_stmt->execute();
    $update_stmt->close();
    
    if (!$update_result) {
        throw new Exception("Failed to update field_options: " . $mysqli->error);
    }
    
    // 2. Update select_gen table
    // First, get existing options
    $existing_opts = [];
    $query = "SELECT pid, select_val FROM select_gen WHERE stid = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $field_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $existing_opts[$row['select_val']] = $row['pid'];
    }
    $stmt->close();
    
    // Add new options
    $add_count = 0;
    $insert_stmt = $mysqli->prepare("INSERT INTO select_gen (stid, select_val) VALUES (?, ?)");
    
    foreach ($options_array as $option) {
        $option = trim($option);
        if (empty($option) || isset($existing_opts[$option])) {
            continue;  // Skip empty or existing options
        }
        
        $insert_stmt->bind_param("is", $field_id, $option);
        if ($insert_stmt->execute()) {
            $add_count++;
        }
    }
    $insert_stmt->close();
    
    // Remove options that are no longer in the list
    $remove_count = 0;
    if (!empty($existing_opts)) {
        $to_remove = [];
        foreach ($existing_opts as $val => $pid) {
            if (!in_array($val, $options_array)) {
                $to_remove[] = $pid;
            }
        }
        
        if (!empty($to_remove)) {
            $placeholders = implode(',', array_fill(0, count($to_remove), '?'));
            $types = str_repeat('i', count($to_remove));
            
            $delete_query = "DELETE FROM select_gen WHERE pid IN ($placeholders)";
            $delete_stmt = $mysqli->prepare($delete_query);
            
            // Dynamically bind parameters
            $delete_params = array($types);
            foreach ($to_remove as $key => $val) {
                $delete_params[] = &$to_remove[$key];
            }
            call_user_func_array(array($delete_stmt, 'bind_param'), $delete_params);
            
            if ($delete_stmt->execute()) {
                $remove_count = $delete_stmt->affected_rows;
            }
            $delete_stmt->close();
        }
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Success response
    $response = [
        'status' => 'success',
        'message' => "Options updated successfully. Added $add_count new options, removed $remove_count options.",
        'field_id' => $field_id,
        'options' => $options_string
    ];
    
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Exception in update_field_options.php: " . $e->getMessage());
}

// Return response
echo json_encode($response);
exit;
?> 