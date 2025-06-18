<?php
// Disable error display to prevent HTML output before JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    include '../OXEXfolder/config.php';
    include '../OXEXfolder/p_functions.php';
    sec_session_start();
    
    // Get trainkey from session
    $trainkey = isset($_SESSION['trainkey']) ? $_SESSION['trainkey'] : null;
    
    // Fallback: if no session trainkey, try to get it from GET parameter and validate
    if (!$trainkey && isset($_GET['trainee_key'])) {
        $potential_trainkey = $_GET['trainee_key'];
        // Validate that this trainkey exists in the database
        $validate_stmt = $mysqli->prepare("SELECT trainkey FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
        if ($validate_stmt) {
            $validate_stmt->bind_param("s", $potential_trainkey);
            $validate_stmt->execute();
            $validate_stmt->store_result();
            if ($validate_stmt->num_rows > 0) {
                $trainkey = $potential_trainkey;
            }
            $validate_stmt->close();
        }
    }
    
    // Check user login - allow if we have a valid trainkey even if login_check fails
    if (!$trainkey || (!login_check($mysqli) && !isset($_GET['trainee_key']))) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
        exit;
    }

    // Get trainee information
    $name = '';
    if (!empty($trainkey)) {
        $trainee_stmt = $mysqli->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
        if ($trainee_stmt) {
            $trainee_stmt->bind_param("s", $trainkey);
            $trainee_stmt->execute();
            $trainee_stmt->bind_result($name);
            $trainee_stmt->fetch();
            $trainee_stmt->close();
        }
    }

    // If name is still empty, set a default
    if (empty($name)) {
        $name = 'Unknown User';
    }

    // Get and validate request parameters
    if (!isset($_GET['table_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing table_id parameter']);
        exit;
    }

    $table_id = intval($_GET['table_id']);

    if ($table_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid table_id']);
        exit;
    }

    // Verify that the current user can access this table
    $table_permission_stmt = $mysqli->prepare("SELECT 1 FROM trainee_tab_link 
                                           WHERE trainkey = ? AND tbid = ? 
                                           LIMIT 1");
    if ($table_permission_stmt) {
        $table_permission_stmt->bind_param("si", $trainkey, $table_id);
        $table_permission_stmt->execute();
        $table_permission_stmt->store_result();
        
        if ($table_permission_stmt->num_rows == 0) {
            echo json_encode(['status' => 'error', 'message' => 'You do not have access to this table']);
            exit;
        }
        $table_permission_stmt->close();
    }

    // Get fields for the specified table
    $fields = [];
    
    // Try tab_fields + select_types join
    $fields_query = "SELECT st.stid, st.str 
                   FROM tab_fields tf 
                   JOIN select_types st ON tf.stid = st.stid 
                   WHERE tf.tbid = ? 
                   ORDER BY tf.sort_order ASC";
    $fields_stmt = $mysqli->prepare($fields_query);
    
    if ($fields_stmt) {
        $fields_stmt->bind_param("i", $table_id);
        $fields_stmt->execute();
        $fields_result = $fields_stmt->get_result();
        
        while ($field = $fields_result->fetch_assoc()) {
            $fields[] = [
                'stid' => $field['stid'],
                'str' => $field['str']
            ];
        }
        $fields_stmt->close();
    }
    
    // If no fields found, try getting all select_types that might be related
    if (empty($fields)) {
        $all_fields_query = "SELECT stid, str FROM select_types WHERE str IS NOT NULL AND str != '' ORDER BY str ASC LIMIT 20";
        $all_fields_stmt = $mysqli->prepare($all_fields_query);
        
        if ($all_fields_stmt) {
            $all_fields_stmt->execute();
            $all_fields_result = $all_fields_stmt->get_result();
            
            while ($field = $all_fields_result->fetch_assoc()) {
                $fields[] = [
                    'stid' => $field['stid'],
                    'str' => $field['str']
                ];
            }
            $all_fields_stmt->close();
        }
    }
    
    // If still no fields, try getting fields from trainee_log for this table
    if (empty($fields)) {
        $log_fields_query = "SELECT DISTINCT tl.stid, st.str 
                           FROM trainee_log tl 
                           LEFT JOIN select_types st ON tl.stid = st.stid 
                           WHERE tl.tbid = ? AND tl.trainkey = ?
                           ORDER BY st.str ASC";
        $log_fields_stmt = $mysqli->prepare($log_fields_query);
        
        if ($log_fields_stmt) {
            $log_fields_stmt->bind_param("is", $table_id, $trainkey);
            $log_fields_stmt->execute();
            $log_fields_result = $log_fields_stmt->get_result();
            
            while ($field = $log_fields_result->fetch_assoc()) {
                if (!empty($field['str'])) {
                    $fields[] = [
                        'stid' => $field['stid'],
                        'str' => $field['str']
                    ];
                }
            }
            $log_fields_stmt->close();
        }
    }
    
    // Return fields along with trainee info
    $response = [
        'status' => 'success',
        'fields' => $fields,
        'trainee_key' => $trainkey,
        'trainee_name' => $name,
        'table_id' => $table_id
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

if (isset($mysqli)) {
    $mysqli->close();
}
?> 