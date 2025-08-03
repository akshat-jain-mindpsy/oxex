<?php
header('Content-Type: application/json');

include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Check user permissions
if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    http_response_code(403); // Forbidden
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

// Get and validate request parameters
if (!isset($_GET['table_id'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Missing table_id parameter']));
}

$table_id = intval($_GET['table_id']);
$trainee_key = isset($_GET['trainee_key']) ? $_GET['trainee_key'] : '';

if ($table_id <= 0) {
    http_response_code(400); // Bad Request
    die(json_encode(['status' => 'error', 'message' => 'Invalid table_id']));
}

// If trainee_key is provided, verify permissions
if (!empty($trainee_key)) {
    // Admin types that can see all data
    $canViewAll = ($admintype == 'AT' || $admintype == 'DV');
    
    if (!$canViewAll) {
        // Check if user is supervisor or tutor for this trainee
        $permission_stmt = $mysqli->prepare("SELECT 1 FROM trainee_tbl 
                                          WHERE trainkey = ? AND 
                                          (supervisor = ? OR supervisor2 = ? OR supervisor3 = ? OR tutor = ?) 
                                          LIMIT 1");
        if ($permission_stmt) {
            $permission_stmt->bind_param("sssss", $trainee_key, $usrkey, $usrkey, $usrkey, $usrkey);
            $permission_stmt->execute();
            $permission_stmt->store_result();
            
            // If no permission, return empty data
            if ($permission_stmt->num_rows == 0) {
                http_response_code(403); // Forbidden
                die(json_encode(['status' => 'error', 'message' => 'Permission denied: You do not have access to this trainee\'s data']));
            }
            $permission_stmt->close();
        }
    }
    
    // Also check if this trainee has access to the selected table
    $table_permission_stmt = $mysqli->prepare("SELECT 1 FROM trainee_tab_link 
                                           WHERE trainkey = ? AND tbid = ? 
                                           LIMIT 1");
    if ($table_permission_stmt) {
        $table_permission_stmt->bind_param("si", $trainee_key, $table_id);
        $table_permission_stmt->execute();
        $table_permission_stmt->store_result();
        
        // Only perform this check if the table exists (some systems might not use trainee_tab_link)
        if ($table_permission_stmt->num_rows == 0) {
            // Log this for debugging but don't block - some implementations may not use this table
            error_log("Note: Trainee $trainee_key may not have explicit permission to table $table_id");
        }
        $table_permission_stmt->close();
    }
    
    // Get trainee name for response
    $trainee_name = '';
    $trainee_stmt = $mysqli->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
    if ($trainee_stmt) {
        $trainee_stmt->bind_param("s", $trainee_key);
        $trainee_stmt->execute();
        $trainee_stmt->bind_result($trainee_name);
        $trainee_stmt->fetch();
        $trainee_stmt->close();
    }
}

// Get fields for the specified table
try {
    // Log the table ID for debugging
    error_log("Fetching fields for table ID: $table_id");
    
    $fields_query = "SELECT st.stid, st.str 
                   FROM tab_fields tf 
                   JOIN select_types st ON tf.stid = st.stid 
                   WHERE tf.tbid = ? 
                   ORDER BY tf.sort_order ASC";
    $fields_stmt = $mysqli->prepare($fields_query);
    
    if (!$fields_stmt) {
        // Log prepare statement error
        error_log("Prepare statement error: " . $mysqli->error);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to prepare statement: ' . $mysqli->error,
            'query' => $fields_query
        ]);
        exit;
    }
    
    $fields_stmt->bind_param("i", $table_id);
    $fields_stmt->execute();
    $fields_result = $fields_stmt->get_result();
    
    $fields = [];
    while ($field = $fields_result->fetch_assoc()) {
        $fields[] = [
            'stid' => $field['stid'],
            'str' => $field['str']
        ];
    }
    $fields_stmt->close();
    
    // Log the number of fields found
    error_log("Found " . count($fields) . " fields for table $table_id");
    
    // If no fields, log additional debugging info
    if (empty($fields)) {
        // Check if the table exists
        $table_check_stmt = $mysqli->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
        $table_check_stmt->bind_param("i", $table_id);
        $table_check_stmt->execute();
        $table_result = $table_check_stmt->get_result();
        $table_info = $table_result->fetch_assoc();
        
        // Check if there are any fields for this table
        $field_count_stmt = $mysqli->prepare("SELECT COUNT(*) as field_count FROM tab_fields WHERE tbid = ?");
        $field_count_stmt->bind_param("i", $table_id);
        $field_count_stmt->execute();
        $field_count_result = $field_count_stmt->get_result();
        $field_count = $field_count_result->fetch_assoc()['field_count'];
        
        error_log("Table info: " . json_encode($table_info));
        error_log("Field count: $field_count");
    }
    
    // Return fields along with trainee info if provided
    $response = [
        'status' => 'success',
        'fields' => $fields
    ];
    
    if (!empty($trainee_key)) {
        $response['trainee_key'] = $trainee_key;
        $response['trainee_name'] = $trainee_name;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?> 