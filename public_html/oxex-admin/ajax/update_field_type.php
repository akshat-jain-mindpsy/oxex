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
    if (!isset($pdo) || !$pdo) {
        throw new Exception("Database connection failed: Connection not established");
    }

    // Get admin type from session
    $admintype = isset($_SESSION['admintype']) ? $_SESSION['admintype'] : '';

    // Check login and permissions
    if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
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
    $check_stmt = $pdo->prepare("SELECT stid, str FROM select_types WHERE stid = ?");
    $check_stmt->execute([$field_id]);
    $field_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$field_data) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Field not found: ID=' . $field_id
        ]);
        exit;
    }
    
    $field_name = $field_data['str'];
    
    // Update field type
    $update_stmt = $pdo->prepare("UPDATE select_types SET single = ? WHERE stid = ?");
    
    if (!$update_stmt->execute([$field_type, $field_id])) {
        throw new Exception("Failed to update field type: " . $update_stmt->errorInfo()[2]);
    }
    
    if ($update_stmt->rowCount() === 0) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'No changes made - field type already set to: ' . $field_type
        ]);
        exit;
    }
    
    // Handle options for select fields (type 0 or 1)
    if ($field_type == 0 || $field_type == 1) {
        // Check if field already has options
        $options_query = $pdo->prepare("SELECT COUNT(*) as option_count FROM select_gen WHERE stid = ?");
        $options_query->execute([$field_id]);
        $option_count = $options_query->fetchColumn();
        
        // If no options and field is now a select type, add a default option
        if ($option_count == 0) {
            $default_option = "Option 1";
            $insert_stmt = $pdo->prepare("INSERT INTO select_gen (stid, select_val) VALUES (?, ?)");
            $insert_stmt->execute([$field_id, $default_option]);
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

if (isset($pdo)) {
    $pdo = null;
}
?> 