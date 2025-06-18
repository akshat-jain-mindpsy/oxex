<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Add debug logging
error_log("=== Starting update_field_type.php request ===");
error_log("POST data: " . json_encode($_POST));

header('Content-Type: application/json');

try {
    include '../../OXEXfolder/config.php';
    include '../../OXEXfolder/u_functions.php';
    sec_session_start();

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
            'message' => 'Unauthorized access: ' . $admintype
        ]);
        exit;
    }

    // Validate input
    $field_id = isset($_POST['field_id']) ? (int)$_POST['field_id'] : 0;
    $field_type = isset($_POST['field_type']) ? (int)$_POST['field_type'] : -1;

    if ($field_id <= 0 || $field_type < 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid input parameters: field_id=' . $field_id . ', field_type=' . $field_type
        ]);
        exit;
    }

    // Verify the field exists
    $check_stmt = $mysqli->prepare("SELECT stid, str FROM select_types WHERE stid = ?");
    $check_stmt->bind_param("i", $field_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Field not found: ID=' . $field_id
        ]);
        exit;
    }
    
    $field_data = $result->fetch_assoc();
    $field_name = $field_data['str'];
    
    // Update field type
    $update_stmt = $mysqli->prepare("UPDATE select_types SET single = ? WHERE stid = ?");
    $update_stmt->bind_param("ii", $field_type, $field_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update field type: " . $mysqli->error);
    }
    
    if ($mysqli->affected_rows === 0) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'No changes made - field type already set to: ' . $field_type
        ]);
        exit;
    }
    
    // Handle options for select fields (type 0 or 1)
    if ($field_type == 0 || $field_type == 1) {
        // Check if field already has options
        $options_query = $mysqli->prepare("SELECT COUNT(*) as option_count FROM select_gen WHERE stid = ?");
        $options_query->bind_param("i", $field_id);
        $options_query->execute();
        $options_result = $options_query->get_result();
        $option_data = $options_result->fetch_assoc();
        
        // If no options and field is now a select type, add a default option
        if ($option_data['option_count'] == 0) {
            $default_option = "Option 1";
            $insert_stmt = $mysqli->prepare("INSERT INTO select_gen (stid, select_val) VALUES (?, ?)");
            $insert_stmt->bind_param("is", $field_id, $default_option);
            $insert_stmt->execute();
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Field type updated successfully',
        'field_id' => $field_id,
        'field_name' => $field_name,
        'field_type' => $field_type
    ]);
    
} catch (Exception $e) {
    error_log("Error in update_field_type.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

if (isset($mysqli)) {
    $mysqli->close();
}
?> 