<?php
// Set error reporting but disable display to prevent HTML output before JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    include '../OXEXfolder/config.php';
    include '../OXEXfolder/p_functions.php';
    sec_session_start();
    
    // Debug logging
    error_log("=== TRAINEE AJAX get_graph_data.php Debug ===");
    error_log("POST data: " . print_r($_POST, true));
    
    // Get trainkey from session
    $trainkey = isset($_SESSION['trainkey']) ? $_SESSION['trainkey'] : null;
    error_log("Session trainkey: " . ($trainkey ? $trainkey : 'NULL'));
    
    // Check login - trainee must be logged in and can only access their own data
    if (!$trainkey || !login_check($mysqli)) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Unauthorized access - please log in'
        ]);
        exit;
    }

    // Get trainee information
    $trainee_name = '';
    if (isset($trainkey) && !empty($trainkey)) {
        $trainee_stmt = $mysqli->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
        if ($trainee_stmt) {
            $trainee_stmt->bind_param("s", $trainkey);
            $trainee_stmt->execute();
            $trainee_stmt->bind_result($trainee_name);
            $trainee_stmt->fetch();
            $trainee_stmt->close();
        }
    }

    // If name is still empty, set a default
    if (empty($trainee_name)) {
        $trainee_name = 'Unknown User';
    }

    // Get request parameters
    $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
    $field_x = isset($_POST['field_x']) ? (int)$_POST['field_x'] : 0;
    $field_y = isset($_POST['field_y']) ? (int)$_POST['field_y'] : 0;
    $chart_type = isset($_POST['chart_type']) ? $_POST['chart_type'] : 'bar';
    $time_frame = isset($_POST['time_frame']) ? $_POST['time_frame'] : 'all';
    $trainee_key = isset($_POST['trainee_key']) ? $_POST['trainee_key'] : $trainkey;
    
    error_log("Parameters - Table: $table_id, Field X: $field_x, Field Y: $field_y, Chart: $chart_type, Time: $time_frame");
    
    // Validate required parameters
    if ($table_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid table ID'
        ]);
        exit;
    }
    
    if ($field_x <= 0 || $field_y <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Both X and Y field IDs are required'
        ]);
        exit;
    }
    
    // Ensure user can only access their own data
    if ($trainee_key !== $trainkey) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Access denied: You can only view your own data'
        ]);
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
            echo json_encode([
                'status' => 'error',
                'message' => 'You do not have access to this table'
            ]);
            exit;
        }
        $table_permission_stmt->close();
    }
    
    // Build time range parameters
    $date_condition = "";
    $date_params = [];
    $param_types = "sii"; // trainkey, table_id, field_x
    $date_range_text = "";
    
    switch ($time_frame) {
        case 'custom':
            if (empty($_POST['start_date']) || empty($_POST['end_date'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Start date and end date are required for custom date range'
                ]);
                exit;
            }
            $start_date = date('Ymd', strtotime($_POST['start_date']));
            $end_date = date('Ymd', strtotime($_POST['end_date']));
            
            if ($start_date > $end_date) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Start date cannot be after end date'
                ]);
                exit;
            }
            
            $date_condition = "AND tl.date_added >= ? AND tl.date_added <= ?";
            $date_params[] = $start_date;
            $date_params[] = $end_date;
            $param_types .= "ii";
            $date_range_text = "From: " . date('M d, Y', strtotime($_POST['start_date'])) . " To: " . date('M d, Y', strtotime($_POST['end_date']));
            error_log("Custom date range: $start_date to $end_date");
            break;
        case 'last30':
            $start_date = date('Ymd', strtotime('-30 days'));
            $date_condition = "AND tl.date_added >= ?";
            $date_params[] = $start_date;
            $param_types .= "i";
            $date_range_text = "Last 30 Days";
            error_log("Last 30 days from: $start_date");
            break;
        case 'last90':
            $start_date = date('Ymd', strtotime('-90 days'));
            $date_condition = "AND tl.date_added >= ?";
            $date_params[] = $start_date;
            $param_types .= "i";
            $date_range_text = "Last 90 Days";
            error_log("Last 90 days from: $start_date");
            break;
        case 'last180':
            $start_date = date('Ymd', strtotime('-180 days'));
            $date_condition = "AND tl.date_added >= ?";
            $date_params[] = $start_date;
            $param_types .= "i";
            $date_range_text = "Last 180 Days";
            error_log("Last 180 days from: $start_date");
            break;
        case 'lastyear':
            $start_date = date('Ymd', strtotime('-1 year'));
            $date_condition = "AND tl.date_added >= ?";
            $date_params[] = $start_date;
            $param_types .= "i";
            $date_range_text = "Last Year";
            error_log("Last year from: $start_date");
            break;
        case 'all':
        default:
            $date_condition = "";
            $date_range_text = "All Time";
            error_log("All time data requested");
            break;
    }
    
    // Get field information for better labeling
    $x_field_name = "Field X ($field_x)";
    $y_field_name = "Field Y ($field_y)";
    
    $field_x_info = $mysqli->prepare("SELECT str FROM select_types WHERE stid = ?");
    if ($field_x_info) {
        $field_x_info->bind_param("i", $field_x);
        $field_x_info->execute();
        $field_x_info->bind_result($x_field_name);
        $field_x_info->fetch();
        $field_x_info->close();
    }
    
    $field_y_info = $mysqli->prepare("SELECT str FROM select_types WHERE stid = ?");
    if ($field_y_info) {
        $field_y_info->bind_param("i", $field_y);
        $field_y_info->execute();
        $field_y_info->bind_result($y_field_name);
        $field_y_info->fetch();
        $field_y_info->close();
    }
    
    error_log("Field names - X: $x_field_name, Y: $y_field_name");
    
    // Build final parameters array
    $final_params = [$trainkey, $table_id, $field_x];
    $final_params = array_merge($final_params, $date_params);
    
    error_log("Final parameters: " . print_r($final_params, true));
    error_log("Parameter types: $param_types");
    
    // Use simple categorical analysis for all cases
    $data = [];
    $labels = [];
    
    // Simple query for categorical distribution
    $query = "SELECT 
                COALESCE(sg.select_val, tl.select_val, 'Unknown') as category_name,
                COUNT(*) as count
              FROM trainee_log tl
              LEFT JOIN select_gen sg ON tl.pid = sg.pid
              WHERE tl.trainkey = ? 
              AND tl.tbid = ?
              AND tl.stid = ?
              $date_condition
              GROUP BY category_name
              ORDER BY count DESC, category_name ASC";
    
    error_log("Executing query: $query");
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("Query preparation failed: " . $mysqli->error);
        throw new Exception("Failed to prepare query: " . $mysqli->error);
    }
    
    // Bind parameters
    if (!empty($final_params)) {
        $stmt->bind_param($param_types, ...$final_params);
    }
    
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'label' => $row['category_name'],
            'value' => (int)$row['count']
        ];
    }
    
    error_log("Query returned " . count($data) . " rows");
    
    // If no data found, provide debugging information
    if (empty($data)) {
        error_log("No data found - debugging info:");
        error_log("Time frame: $time_frame");
        error_log("Date condition: $date_condition");
        error_log("Final params: " . print_r($final_params, true));
        
        // Check if there's any data in the table at all
        $debug_query = "SELECT COUNT(*) as total, MIN(date_added) as min_date, MAX(date_added) as max_date FROM trainee_log WHERE trainkey = ? AND tbid = ?";
        $debug_stmt = $mysqli->prepare($debug_query);
        if ($debug_stmt) {
            $debug_stmt->bind_param("si", $trainkey, $table_id);
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();
            $debug_row = $debug_result->fetch_assoc();
            error_log("Debug - Total records: " . $debug_row['total'] . ", Date range: " . $debug_row['min_date'] . " to " . $debug_row['max_date']);
            $debug_stmt->close();
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => 'No data found for the selected criteria and time period. Try selecting a different time frame or data source.',
            'debug_info' => [
                'table_id' => $table_id,
                'field_x' => $field_x,
                'field_y' => $field_y,
                'time_frame' => $time_frame,
                'date_condition' => $date_condition,
                'trainee_key' => $trainkey
            ]
        ]);
        exit;
    }
    
    $labels = array_map(function($item) { return $item['label']; }, $data);
    
    // Return successful response
    echo json_encode([
        'status' => 'success',
        'message' => 'Data retrieved successfully',
        'data' => $data,
        'labels' => $labels,
        'x_field' => $x_field_name,
        'y_field' => $y_field_name,
        'chart_type' => $chart_type,
        'table_id' => $table_id,
        'trainee_key' => $trainkey,
        'trainee_name' => $trainee_name,
        'time_frame' => $time_frame,
        'date_range' => $date_range_text,
        'record_count' => count($data)
    ]);
    
} catch (Exception $e) {
    error_log("Error in trainee get_graph_data.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

if (isset($mysqli)) {
    $mysqli->close();
}
?> 