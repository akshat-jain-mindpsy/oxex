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
if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
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
    // Initialize PDO connection
    $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
    if (!$pdo) {
        throw new Exception("Database connection not available");
    }
    
    // First check if field_options column exists
    $check_column = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'select_types' AND column_name = 'field_options'");
    if ($check_column === false || $check_column->rowCount() === 0) {
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
    $pdo->beginTransaction();
    
    // 1. Update field_options in select_types
    $update_stmt = $pdo->prepare("UPDATE select_types SET field_options = ? WHERE stid = ?");
    $update_result = $update_stmt->execute([$options_string, $field_id]);
    
    if (!$update_result) {
        throw new Exception("Failed to update field_options: " . implode(', ', $update_stmt->errorInfo()));
    }
    
    // 2. Update select_gen table
    // First, get existing options
    $existing_opts = [];
    $query = "SELECT pid, select_val FROM select_gen WHERE stid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$field_id]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_opts[$row['select_val']] = $row['pid'];
    }
    
    // Add new options
    $add_count = 0;
    $insert_stmt = $pdo->prepare("INSERT INTO select_gen (stid, select_val) VALUES (?, ?)");
    
    foreach ($options_array as $option) {
        $option = trim($option);
        if (empty($option) || isset($existing_opts[$option])) {
            continue;  // Skip empty or existing options
        }
        
        if ($insert_stmt->execute([$field_id, $option])) {
            $add_count++;
        }
    }
    
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
            
            $delete_query = "DELETE FROM select_gen WHERE pid IN ($placeholders)";
            $delete_stmt = $pdo->prepare($delete_query);
            
            if ($delete_stmt->execute($to_remove)) {
                $remove_count = $delete_stmt->rowCount();
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Success response
    $response = [
        'status' => 'success',
        'message' => "Options updated successfully. Added $add_count new options, removed $remove_count options.",
        'field_id' => $field_id,
        'options' => $options_string
    ];
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Exception in update_field_options.php: " . $e->getMessage());
}

// Return response
echo json_encode($response);
exit;
?> 