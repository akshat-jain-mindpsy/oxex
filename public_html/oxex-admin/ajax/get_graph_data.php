<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Helper function to get field type name
function getFieldTypeName($type) {
    switch ($type) {
        case 0: return 'Single Selection';
        case 1: return 'Multiple Selection';
        case 2: return 'Text';
        case 3: return 'Date';
        case 4: return 'Numeric (0.1)';
        case 5: return 'Numeric (Integer)';
        case 6: return 'Time';
        default: return 'Unknown';
    }
}

try {
    include '../../OXEXfolder/config.php';
    include '../../OXEXfolder/u_functions.php';
    sec_session_start();
    include '../incl/sess.php';
    
    // Log the start of the request
    error_log("=== ADMIN get_graph_data.php started ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("Session data: " . print_r($_SESSION, true));
    
    // Check user permissions (sess.php already validates the session)
    if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
        error_log("ERROR: Authentication or permission check failed");
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized access'
        ]);
        exit;
    }
    
    error_log("Admin authenticated: usrkey=$usrkey, type=$admintype");
    
    // Get trainkey from POST parameter (admin can select which trainee to view)
    $trainkey = isset($_POST['trainee_key']) ? $_POST['trainee_key'] : null;
    error_log("Requested trainkey from POST: " . ($trainkey ? $trainkey : 'NULL'));
    
    // Check for different modes
    $is_subset_mode = (strpos($trainkey, 'subset_') === 0);
    $all_users_mode = ($trainkey === 'ALL_USERS');
    
    // If no specific trainee selected and not "All Users" mode, return error
    if (!$trainkey) {
        error_log("ERROR: No trainee selected for admin graph view");
        echo json_encode([
            'status' => 'error',
            'message' => 'Please select a trainee or "All Users" to view data'
        ]);
        exit;
    }
    
    // Prepare list of accessible trainee keys for this admin
    $accessible_trainee_keys = [];
    $effective_trainkey = null;

    if ($is_subset_mode) {
        $setkey = substr($trainkey, 7);
        error_log("Subset mode detected for setkey: $setkey");
        
        // Fetch all trainkeys from the subset
        $subset_trainees_query = "SELECT trainkey FROM subset_link_tbl WHERE setkey = ?";
        $subset_stmt = $mysqli->prepare($subset_trainees_query);
        $subset_stmt->bind_param("s", $setkey);
        $subset_stmt->execute();
        $subset_result = $subset_stmt->get_result();
        
        $trainees_in_subset = [];
        while ($row = $subset_result->fetch_assoc()) {
            $trainees_in_subset[] = $row['trainkey'];
        }
        $subset_stmt->close();
        
        if (empty($trainees_in_subset)) {
            error_log("ERROR: Subset $setkey is empty.");
            echo json_encode(['status' => 'error', 'message' => 'This trainee group is empty.']);
            exit;
        }
        
        // Verify the admin has access to EACH trainee in the subset
        $canViewAll = ($admintype == 'AT' || $admintype == 'DV');
        if ($canViewAll) {
            $accessible_trainee_keys = $trainees_in_subset;
        } else {
            // For non-super-admins, filter the list of subset trainees
            $placeholders = rtrim(str_repeat('?,', count($trainees_in_subset)), ',');
            $access_query = "SELECT trainkey FROM trainee_tbl 
                             WHERE trainkey IN ($placeholders) 
                             AND (supervisor = ? OR supervisor2 = ? OR supervisor3 = ? OR tutor = ?)";
            
            $access_stmt = $mysqli->prepare($access_query);
            $params = array_merge($trainees_in_subset, [$usrkey, $usrkey, $usrkey, $usrkey]);
            $types = str_repeat('s', count($params));
            $access_stmt->bind_param($types, ...$params);
            $access_stmt->execute();
            $access_result = $access_stmt->get_result();
            
            while ($row = $access_result->fetch_assoc()) {
                $accessible_trainee_keys[] = $row['trainkey'];
            }
            $access_stmt->close();
        }
        
        if (empty($accessible_trainee_keys)) {
            error_log("ERROR: Admin $usrkey has no access to any trainees in subset $setkey");
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to view data for any trainees in this group.']);
            exit;
        }
        
        error_log("Subset mode: Found " . count($accessible_trainee_keys) . " accessible trainees in subset $setkey for admin $usrkey");
        $effective_trainkey = $trainkey; // Use the full subset key for reference
        $all_users_mode = true; // Treat as a multi-user mode for query building

    } else if ($all_users_mode) {
        // Get all trainee keys this admin can access
        $canViewAll = ($admintype == 'AT' || $admintype == 'DV');
        
        if ($canViewAll) {
            // Super admins can view all trainees
            $trainee_access_query = "SELECT trainkey FROM trainee_tbl ORDER BY name ASC";
            $trainee_access_stmt = $mysqli->prepare($trainee_access_query);
        } else {
            // Regular admins can only view trainees they supervise/tutor
            $trainee_access_query = "SELECT trainkey FROM trainee_tbl 
                                   WHERE supervisor = ? OR supervisor2 = ? OR supervisor3 = ? OR tutor = ? 
                                   ORDER BY name ASC";
            $trainee_access_stmt = $mysqli->prepare($trainee_access_query);
            $trainee_access_stmt->bind_param("ssss", $usrkey, $usrkey, $usrkey, $usrkey);
        }
        
        if ($trainee_access_stmt) {
            if ($canViewAll) {
                $trainee_access_stmt->execute();
            }
            $trainee_access_result = $trainee_access_stmt->get_result();
            
            while ($row = $trainee_access_result->fetch_assoc()) {
                $accessible_trainee_keys[] = $row['trainkey'];
            }
            $trainee_access_stmt->close();
        }
        
        if (empty($accessible_trainee_keys)) {
            error_log("ERROR: No accessible trainees found for admin $usrkey");
            echo json_encode([
                'status' => 'error',
                'message' => 'No accessible trainee data found'
            ]);
            exit;
        }
        
        error_log("All Users mode: Found " . count($accessible_trainee_keys) . " accessible trainees for admin $usrkey");
        
        // For queries, we'll use a placeholder since we'll modify the WHERE clause
        $effective_trainkey = 'ALL_USERS';
        
    } else {
        // Single trainee mode - validate access as before
    $access_check_query = "SELECT t.trainkey, t.name, t.supervisor, t.supervisor2, t.supervisor3, t.tutor 
                          FROM trainee_tbl t 
                          WHERE t.trainkey = ? LIMIT 1";
    $access_stmt = $mysqli->prepare($access_check_query);
    if ($access_stmt) {
        $access_stmt->bind_param("s", $trainkey);
        $access_stmt->execute();
        $access_result = $access_stmt->get_result();
        
        if ($access_result->num_rows === 0) {
            error_log("ERROR: Invalid trainkey: $trainkey");
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid trainee selected'
            ]);
            exit;
        }
        
        $trainee_data = $access_result->fetch_assoc();
        $access_stmt->close();
        
        // Check if admin has permission to view this trainee's data
        $canViewAll = ($admintype == 'AT' || $admintype == 'DV');
        $canView = $canViewAll;
        
        // Check if admin is supervisor or tutor for this trainee
        if (!$canView) {
            if ($trainee_data['supervisor'] == $usrkey || 
                $trainee_data['supervisor2'] == $usrkey || 
                $trainee_data['supervisor3'] == $usrkey || 
                $trainee_data['tutor'] == $usrkey) {
                $canView = true;
            }
        }
        
        if (!$canView) {
            error_log("ERROR: Admin $usrkey does not have permission to view trainee $trainkey");
            echo json_encode([
                'status' => 'error',
                'message' => 'You do not have permission to view this trainee\'s data'
            ]);
            exit;
        }
        
        error_log("Access granted: Admin $usrkey can view trainee $trainkey ({$trainee_data['name']})");
            $accessible_trainee_keys = [$trainkey];
            $effective_trainkey = $trainkey;
    } else {
        error_log("ERROR: Failed to prepare access check query");
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error during access check'
        ]);
        exit;
        }
    }

    // Get request parameters
    $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
    $field_x = isset($_POST['field_x']) ? (int)$_POST['field_x'] : 0;
    $field_y = isset($_POST['field_y']) ? (int)$_POST['field_y'] : 0;
    $chart_type = isset($_POST['chart_type']) ? $_POST['chart_type'] : 'bar';
    $time_frame = isset($_POST['time_frame']) ? $_POST['time_frame'] : 'all';
    
    error_log("Parameters - table_id: $table_id, field_x: $field_x, field_y: $field_y, chart_type: $chart_type, time_frame: $time_frame");
    
    // Validate required parameters
    if ($table_id <= 0) {
        error_log("ERROR: Invalid table_id: $table_id");
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid table ID'
        ]);
        exit;
    }
    
    if ($field_x <= 0 || $field_y <= 0) {
        error_log("ERROR: Invalid field IDs - field_x: $field_x, field_y: $field_y");
        echo json_encode([
            'status' => 'error',
            'message' => 'Field IDs are required'
        ]);
        exit;
    }
    
    // Verify that trainees have access to this table
    if ($all_users_mode) {
        // For all users mode, check if any of the accessible trainees have access to this table
        $trainkey_placeholders = str_repeat('?,', count($accessible_trainee_keys) - 1) . '?';
        $access_stmt = $mysqli->prepare("
            SELECT COUNT(DISTINCT ttl.trainkey) as count 
            FROM trainee_tab_link ttl 
            JOIN tabs_tbl tt ON ttl.tbid = tt.tbid 
            WHERE ttl.trainkey IN ($trainkey_placeholders) AND ttl.tbid = ? AND tt.isvis = 1
        ");
        
        if ($access_stmt) {
            $params_for_access = array_merge($accessible_trainee_keys, [$table_id]);
            $types_for_access = str_repeat('s', count($accessible_trainee_keys)) . 'i';
            $access_stmt->bind_param($types_for_access, ...$params_for_access);
            $access_stmt->execute();
            $access_result = $access_stmt->get_result();
            $access_row = $access_result->fetch_assoc();
            $access_stmt->close();
            
            error_log("Table access check (All Users) - accessible trainees with table access: " . $access_row['count']);
            
            if ($access_row['count'] == 0) {
                error_log("ERROR: No accessible trainees have access to table $table_id");
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No accessible users have access to this table'
                ]);
                exit;
            }
        }
    } else {
        // Single trainee mode - original access check
    $access_stmt = $mysqli->prepare("
        SELECT COUNT(*) as count 
        FROM trainee_tab_link ttl 
        JOIN tabs_tbl tt ON ttl.tbid = tt.tbid 
        WHERE ttl.trainkey = ? AND ttl.tbid = ? AND tt.isvis = 1
    ");
    
    if ($access_stmt) {
        $access_stmt->bind_param("si", $trainkey, $table_id);
        $access_stmt->execute();
        $access_result = $access_stmt->get_result();
        $access_row = $access_result->fetch_assoc();
        $access_stmt->close();
        
            error_log("Table access check (Single User) - count: " . $access_row['count']);
        
        if ($access_row['count'] == 0) {
            error_log("ERROR: Access denied to table $table_id for trainkey $trainkey");
            echo json_encode([
                'status' => 'error',
                'message' => 'Access denied to this table'
            ]);
            exit;
            }
        }
    }
    
    // Calculate date range based on time_frame and prepare base parameters
    $date_condition = "";
    $date_condition_multi = ""; // For queries with tl1/tl2 aliases
    $params = [];
    $param_types = "";
    
    // Build trainkey filtering parameters
    if ($all_users_mode) {
        // For all users mode, we'll pass the array of trainee keys
        $trainkey_params = $accessible_trainee_keys;
        $table_param = $table_id;
    } else {
        // For single user mode, we'll pass the single trainee key
        $trainkey_params = [$effective_trainkey];
        $table_param = $table_id;
    }
    
    // Set up base parameters (will be used differently by each analysis function)
    $base_params = array_merge($trainkey_params, [$table_param]);
    $base_param_types = str_repeat('s', count($trainkey_params)) . 'i';
    
    switch ($time_frame) {
        case 'custom':
            if (empty($_POST['start_date']) || empty($_POST['end_date'])) {
                error_log("ERROR: Missing custom date range");
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Start date and end date are required for custom date range'
                ]);
                exit;
            }
            // Convert dates to YYYYMMDD format for comparison with date_added
            $start_date = date('Ymd', strtotime($_POST['start_date']));
            $end_date = date('Ymd', strtotime($_POST['end_date']));
            $date_condition = "AND tl.date_added >= ? AND tl.date_added <= ?";
            $date_condition_multi = "AND tl1.date_added >= ? AND tl1.date_added <= ?";
            $base_params[] = $start_date;
            $base_params[] = $end_date;
            $base_param_types .= "ii";
            error_log("Custom date range: " . $_POST['start_date'] . " to " . $_POST['end_date'] . " (converted: $start_date to $end_date)");
            break;
        case 'last30':
            $start_date = date('Ymd', strtotime('-30 days'));
            $date_condition = "AND tl.date_added >= ?";
            $date_condition_multi = "AND tl1.date_added >= ?";
            $base_params[] = $start_date;
            $base_param_types .= "i";
            error_log("Time frame: Last 30 days");
            break;
        case 'last90':
            $start_date = date('Ymd', strtotime('-90 days'));
            $date_condition = "AND tl.date_added >= ?";
            $date_condition_multi = "AND tl1.date_added >= ?";
            $base_params[] = $start_date;
            $base_param_types .= "i";
            error_log("Time frame: Last 90 days");
            break;
        case 'last180':
            $start_date = date('Ymd', strtotime('-180 days'));
            $date_condition = "AND tl.date_added >= ?";
            $date_condition_multi = "AND tl1.date_added >= ?";
            $base_params[] = $start_date;
            $base_param_types .= "i";
            error_log("Time frame: Last 180 days");
            break;
        case 'lastyear':
            $start_date = date('Ymd', strtotime('-1 year'));
            $date_condition = "AND tl.date_added >= ?";
            $date_condition_multi = "AND tl1.date_added >= ?";
            $base_params[] = $start_date;
            $base_param_types .= "i";
            error_log("Time frame: Last year");
            break;
        case 'all':
        default:
            $date_condition = "";
            $date_condition_multi = "";
            error_log("Time frame: All time");
            break;
    }
    
    // Get field names and types for better labeling and appropriate handling
    $x_field_name = "Field X ($field_x)";
    $y_field_name = "Field Y ($field_y)";
    $x_field_type = 0; // Default to single selection
    $y_field_type = 0;
    
    $field_x_stmt = $mysqli->prepare("SELECT str, single FROM select_types WHERE stid = ?");
    if ($field_x_stmt) {
        $field_x_stmt->bind_param("i", $field_x);
        $field_x_stmt->execute();
        $field_x_stmt->bind_result($x_field_name, $x_field_type);
        $field_x_stmt->fetch();
        $field_x_stmt->close();
        error_log("X field name: " . ($x_field_name ?: "Not found") . ", type: $x_field_type");
    }
    
    $field_y_stmt = $mysqli->prepare("SELECT str, single FROM select_types WHERE stid = ?");
    if ($field_y_stmt) {
        $field_y_stmt->bind_param("i", $field_y);
        $field_y_stmt->execute();
        $field_y_stmt->bind_result($y_field_name, $y_field_type);
        $field_y_stmt->fetch();
        $field_y_stmt->close();
        error_log("Y field name: " . ($y_field_name ?: "Not found") . ", type: $y_field_type");
    }
    
    $data = [];
    $labels = [];
    
    error_log("Starting intelligent data query - X field: $x_field_name (type: $x_field_type), Y field: $y_field_name (type: $y_field_type), Chart: $chart_type");
    
    // Determine the best analysis approach based on field type combinations
    $analysis_type = determineAnalysisType($x_field_type, $y_field_type, $field_x, $field_y, $chart_type);
    error_log("Analysis type determined: " . $analysis_type);
    
    switch ($analysis_type) {
        case 'categorical_distribution':
            // X is categorical (0,1), show distribution of categories
            $data = getCategoricalDistribution($mysqli, null, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types);
            break;
            
        case 'numeric_analysis':
            // X is numeric (4,5), show value analysis
            $data = getNumericAnalysis($mysqli, null, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types, $chart_type);
            break;
            
        case 'time_series':
            // X is date/time (3,6), show trends over time
            $data = getTimeSeriesAnalysis($mysqli, null, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types, $time_frame);
            break;
            
        case 'text_analysis':
            // X is text (2), analyze text patterns
            $data = getTextAnalysis($mysqli, null, $field_x, $date_condition, $base_params, $base_param_types);
            break;
            
        case 'categorical_vs_numeric':
            // X is categorical, Y is numeric - show numeric values per category
            $data = getCategoricalVsNumeric($mysqli, null, $field_x, $field_y, $x_field_type, $y_field_type, $date_condition_multi, $base_params, $base_param_types);
            // Fallback to X field analysis if no data
            if (empty($data)) {
                error_log("Multi-field analysis returned no data, falling back to single field analysis for field $field_x");
                $data = getCategoricalDistribution($mysqli, null, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types);
            }
            break;
            
        case 'categorical_vs_categorical':
            // Both categorical - show cross-tabulation
            $data = getCategoricalVsCategorical($mysqli, null, $field_x, $field_y, $x_field_type, $y_field_type, $date_condition_multi, $base_params, $base_param_types);
            // Fallback to X field analysis if no data
            if (empty($data)) {
                error_log("Multi-field analysis returned no data, falling back to single field analysis for field $field_x");
                $data = getCategoricalDistribution($mysqli, null, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types);
            }
            break;
            
        case 'numeric_vs_numeric':
            // Both numeric - show correlation/scatter analysis
            $data = getNumericVsNumeric($mysqli, null, $field_x, $field_y, $date_condition_multi, $base_params, $base_param_types, $chart_type);
            // Fallback to X field analysis if no data
            if (empty($data)) {
                error_log("Multi-field analysis returned no data, falling back to single field analysis for field $field_x");
                $data = getNumericAnalysis($mysqli, null, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types, $chart_type);
            }
            break;
            
        case 'time_vs_numeric':
            // X is date/time, Y is numeric - show values over time
            $data = getTimeVsNumeric($mysqli, null, $field_x, $field_y, $x_field_type, $y_field_type, $date_condition_multi, $base_params, $base_param_types, $time_frame);
            // Fallback to X field analysis if no data
            if (empty($data)) {
                error_log("Multi-field analysis returned no data, falling back to single field analysis for field $field_x");
                $data = getTimeSeriesAnalysis($mysqli, null, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types, $time_frame);
            }
            break;
            
        case 'time_vs_categorical':
            // X is date/time, Y is categorical - show category frequency over time
            $data = getTimeVsCategorical($mysqli, null, $field_x, $field_y, $x_field_type, $y_field_type, $date_condition_multi, $base_params, $base_param_types, $time_frame);
            // Fallback to X field analysis if no data
            if (empty($data)) {
                error_log("Multi-field analysis returned no data, falling back to single field analysis for field $field_x");
                $data = getTimeSeriesAnalysis($mysqli, null, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types, $time_frame);
            }
            break;
            
        default:
            // Fallback to basic counting
            $data = getBasicCount($mysqli, null, $field_x, $date_condition, $base_params, $base_param_types);
            break;
    }
    
    // Extract labels from data if not already set
    if (empty($labels) && !empty($data)) {
        $labels = array_map(function($item) { return $item['label']; }, $data);
    }
    
    error_log("Final data array: " . print_r($data, true));
    error_log("Final labels array: " . print_r($labels, true));
    
    // Check if we have any data
    if (empty($data)) {
        error_log("ERROR: No data found for the query");
        error_log("Time frame: $time_frame");
        error_log("Date condition: $date_condition");
        error_log("Params: " . print_r($base_params, true));
        
        // Get trainee name for display
        $trainee_name = '';
        if ($all_users_mode) {
            if ($is_subset_mode) {
                $setkey = substr($effective_trainkey, 7);
                $subset_name_stmt = $mysqli->prepare("SELECT subset FROM subset_tbl WHERE setkey = ? LIMIT 1");
                if ($subset_name_stmt) {
                    $subset_name_stmt->bind_param("s", $setkey);
                    $subset_name_stmt->execute();
                    $subset_name_stmt->bind_result($trainee_name);
                    $subset_name_stmt->fetch();
                    $subset_name_stmt->close();
                    $trainee_name .= " (Group)";
                }
            } else {
                $trainee_name = 'All Users (Aggregated)';
            }
        } else {
            $name_stmt = $mysqli->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
            if ($name_stmt) {
                $name_stmt->bind_param("s", $effective_trainkey);
                $name_stmt->execute();
                $name_stmt->bind_result($trainee_name);
                $name_stmt->fetch();
                $name_stmt->close();
                error_log("Trainee name: " . ($trainee_name ?: 'Not found'));
            }
        }
        
        // Debug information for "All Users" mode
        if ($all_users_mode && empty($data)) {
            error_log("DEBUG: All Users mode with no data");
            error_log("Accessible trainee keys: " . print_r($accessible_trainee_keys, true));
            
            // Check if there's any data in trainee_log for any accessible trainees and table
            $trainkey_placeholders = str_repeat('?,', count($accessible_trainee_keys) - 1) . '?';
            $debug_query = "SELECT COUNT(*) as total, MIN(date_added) as min_date, MAX(date_added) as max_date 
                           FROM trainee_log 
                           WHERE trainkey IN ($trainkey_placeholders) AND tbid = ?";
            $debug_stmt = $mysqli->prepare($debug_query);
            if ($debug_stmt) {
                $debug_params = array_merge($accessible_trainee_keys, [$table_id]);
                $debug_types = str_repeat('s', count($accessible_trainee_keys)) . 'i';
                $debug_stmt->bind_param($debug_types, ...$debug_params);
                $debug_stmt->execute();
                $debug_result = $debug_stmt->get_result();
                $debug_row = $debug_result->fetch_assoc();
                error_log("Debug (All Users): Total records in trainee_log: " . $debug_row['total'] . ", Date range: " . $debug_row['min_date'] . " to " . $debug_row['max_date']);
                $debug_stmt->close();
            }
        } elseif (!$all_users_mode && empty($data)) {
            // Original single user debug
            $debug_query = "SELECT COUNT(*) as total, MIN(date_added) as min_date, MAX(date_added) as max_date FROM trainee_log WHERE trainkey = ? AND tbid = ?";
            $debug_stmt = $mysqli->prepare($debug_query);
            if ($debug_stmt) {
                $debug_stmt->bind_param("si", $effective_trainkey, $table_id);
                $debug_stmt->execute();
                $debug_result = $debug_stmt->get_result();
                $debug_row = $debug_result->fetch_assoc();
                error_log("Debug (Single User): Total records in trainee_log for this trainee/table: " . $debug_row['total'] . ", Date range: " . $debug_row['min_date'] . " to " . $debug_row['max_date']);
                $debug_stmt->close();
            }
            
            // Check if there's any data for the specific field
            $debug_field_query = "SELECT COUNT(*) as total FROM trainee_log WHERE trainkey = ? AND tbid = ? AND stid = ?";
            $debug_field_stmt = $mysqli->prepare($debug_field_query);
            if ($debug_field_stmt) {
                $debug_field_stmt->bind_param("sii", $effective_trainkey, $table_id, $field_x);
                $debug_field_stmt->execute();
                $debug_field_result = $debug_field_stmt->get_result();
                $debug_field_row = $debug_field_result->fetch_assoc();
                error_log("Debug (Single User): Total records for field $field_x: " . $debug_field_row['total']);
                $debug_field_stmt->close();
            }
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => 'No data found for the selected criteria and time frame'
        ]);
        exit;
    }
    
    // Format date range for display
    $date_range = '';
    if ($time_frame === 'custom' && isset($_POST['start_date']) && isset($_POST['end_date'])) {
        $date_range = date('M d, Y', strtotime($_POST['start_date'])) . ' - ' . date('M d, Y', strtotime($_POST['end_date']));
    }
    
    // Get trainee name for display
    $trainee_name = '';
    if ($all_users_mode) {
        if ($is_subset_mode) {
            $setkey = substr($effective_trainkey, 7);
            $subset_name_stmt = $mysqli->prepare("SELECT subset FROM subset_tbl WHERE setkey = ? LIMIT 1");
            if ($subset_name_stmt) {
                $subset_name_stmt->bind_param("s", $setkey);
                $subset_name_stmt->execute();
                $subset_name_stmt->bind_result($trainee_name);
                $subset_name_stmt->fetch();
                $subset_name_stmt->close();
                $trainee_name .= " (Group)";
            }
        } else {
            $trainee_name = 'All Users (Aggregated)';
        }
    } else {
        $name_stmt = $mysqli->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
        if ($name_stmt) {
            $name_stmt->bind_param("s", $effective_trainkey);
            $name_stmt->execute();
            $name_stmt->bind_result($trainee_name);
            $name_stmt->fetch();
            $name_stmt->close();
            error_log("Trainee name retrieved: " . ($trainee_name ?: 'Not found'));
        }
    }
    
    $response = [
        'status' => 'success',
        'message' => 'Data retrieved successfully',
        'data' => $data,
        'labels' => $labels,
        'x_field' => $x_field_name ?: "Field $field_x",
        'y_field' => $y_field_name ?: "Field $field_y",
        'x_field_type' => $x_field_type,
        'y_field_type' => $y_field_type,
        'chart_type' => $chart_type,
        'time_frame' => $time_frame,
        'date_range' => $date_range,
        'table_id' => $table_id,
        'trainee_key' => $is_subset_mode ? $effective_trainkey : ($all_users_mode ? 'ALL_USERS' : $effective_trainkey),
        'trainee_name' => $trainee_name ?: ($all_users_mode ? 'All Users (Aggregated)' : 'Unknown User'),
        'total_records' => count($data),
        'is_all_users_mode' => $all_users_mode,
        'accessible_user_count' => $all_users_mode ? count($accessible_trainee_keys) : 1,
        'field_type_info' => [
            'x_field_type_name' => getFieldTypeName($x_field_type),
            'y_field_type_name' => getFieldTypeName($y_field_type),
            'is_numeric' => ($x_field_type == 4 || $x_field_type == 5),
            'is_date' => ($x_field_type == 3),
            'is_time' => ($x_field_type == 6),
            'is_text' => ($x_field_type == 2)
        ]
    ];
    
    error_log("SUCCESS: Returning response with " . count($data) . " data points");
    error_log("Response: " . json_encode($response));
    
    // Return successful response
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("EXCEPTION: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

if (isset($mysqli)) {
    $mysqli->close();
}

// Function to determine the best analysis type based on field types
function determineAnalysisType($x_type, $y_type, $field_x, $field_y, $chart_type) {
    // If same field (X = Y), do single field analysis
            if ($field_x === $field_y) {
        switch ($x_type) {
            case 0: case 1: return 'categorical_distribution';
            case 2: return 'text_analysis';
            case 3: case 6: return 'time_series';
            case 4: case 5: return 'numeric_analysis';
            default: return 'categorical_distribution';
        }
    }
    
    // Different fields - determine best combination analysis
    $type_map = [
        'categorical' => [0, 1],
        'text' => [2],
        'temporal' => [3, 6],
        'numeric' => [4, 5]
    ];
    
    $x_category = getFieldCategory($x_type, $type_map);
    $y_category = getFieldCategory($y_type, $type_map);
    
    // Determine analysis based on combination
    if ($x_category === 'categorical' && $y_category === 'numeric') {
        return 'categorical_vs_numeric';
    } elseif ($x_category === 'categorical' && $y_category === 'categorical') {
        return 'categorical_vs_categorical';
    } elseif ($x_category === 'numeric' && $y_category === 'numeric') {
        return 'numeric_vs_numeric';
    } elseif ($x_category === 'temporal' && $y_category === 'numeric') {
        return 'time_vs_numeric';
    } elseif ($x_category === 'temporal' && $y_category === 'categorical') {
        return 'time_vs_categorical';
    } elseif ($x_category === 'numeric' && $y_category === 'categorical') {
        // Flip the analysis - treat Y as primary
        return 'categorical_vs_numeric';
    } elseif ($x_category === 'temporal') {
        return 'time_series';
    } else {
        // Default fallback
        return 'categorical_distribution';
    }
}

function getFieldCategory($type, $type_map) {
    foreach ($type_map as $category => $types) {
        if (in_array($type, $types)) {
            return $category;
        }
    }
    return 'categorical'; // Default
}

// Categorical field distribution analysis
function getCategoricalDistribution($mysqli, $unused_param, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types) {
    error_log("Executing categorical distribution analysis for field $field_x with " . count($base_params) . " base params");
    
    // Extract components from base_params
    // Format: [trainee_key1, trainee_key2, ..., table_id, date_params...]
    $all_params = $base_params;
    
    // Find table_id (it's an integer in the middle-end of the array)
    $table_id = null;
    $trainee_keys = [];
    $date_params = [];
    
    // Since we know the structure: trainee_keys (strings) + table_id (integer) + optional date_params (integers)
    for ($i = 0; $i < count($all_params); $i++) {
        if (is_numeric($all_params[$i]) && intval($all_params[$i]) > 0) {
            // This could be table_id if it's reasonable (< 100) or date if > 20000000
            if (intval($all_params[$i]) < 100) {
                $table_id = intval($all_params[$i]);
                $trainee_keys = array_slice($all_params, 0, $i);
                $date_params = array_slice($all_params, $i + 1);
                break;
            }
        }
    }
    
    error_log("Extracted: " . count($trainee_keys) . " trainee keys, table_id: $table_id, " . count($date_params) . " date params");
    
    // Build WHERE clause for trainee keys
    if (count($trainee_keys) > 1) {
        $trainkey_placeholders = str_repeat('?,', count($trainee_keys) - 1) . '?';
        $trainkey_condition = "tl.trainkey IN ($trainkey_placeholders)";
    } else {
        $trainkey_condition = "tl.trainkey = ?";
    }
    
    if ($x_field_type == 0 || $x_field_type == 1) {
        // Selection fields - get actual option values
                    $query = "
                        SELECT 
                COALESCE(sg.select_val, tl.select_val, 'Unknown') as category,
                            COUNT(*) as count
                        FROM trainee_log tl
            LEFT JOIN select_gen sg ON tl.pid = sg.pid
            WHERE $trainkey_condition
                        AND tl.tbid = ? 
                        AND tl.stid = ?
                        $date_condition
            GROUP BY category
            ORDER BY count DESC, category ASC
            LIMIT 20
                    ";
    } else {
        // Fallback for other types
                    $query = "
                        SELECT 
                COALESCE(tl.select_val, 'Unknown') as category,
                            COUNT(*) as count
                        FROM trainee_log tl
            WHERE $trainkey_condition
                        AND tl.tbid = ? 
                        AND tl.stid = ?
                        AND tl.select_val IS NOT NULL 
                        AND tl.select_val != ''
                        $date_condition
            GROUP BY category
            ORDER BY count DESC
            LIMIT 20
        ";
    }
    
    // Build final parameters: trainee_keys + table_id + field_x + date_params
    $final_params = array_merge($trainee_keys, [$table_id, $field_x], $date_params);
    $final_param_types = str_repeat('s', count($trainee_keys)) . 'ii' . str_repeat('i', count($date_params));
    
    error_log("Final params count: " . count($final_params) . ", types: $final_param_types");
    
    return executeSimpleQuery($mysqli, $query, $final_params, $final_param_types, 'count');
}

// Numeric field analysis
function getNumericAnalysis($mysqli, $unused_param, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types, $chart_type) {
    error_log("Executing numeric analysis for field $field_x with " . count($base_params) . " base params");
    
    // Extract components from base_params
    $all_params = $base_params;
    
    // Find table_id (it's an integer in the middle-end of the array)
    $table_id = null;
    $trainee_keys = [];
    $date_params = [];
    
    for ($i = 0; $i < count($all_params); $i++) {
        if (is_numeric($all_params[$i]) && intval($all_params[$i]) > 0) {
            if (intval($all_params[$i]) < 100) {
                $table_id = intval($all_params[$i]);
                $trainee_keys = array_slice($all_params, 0, $i);
                $date_params = array_slice($all_params, $i + 1);
                break;
            }
        }
    }
    
    error_log("Extracted: " . count($trainee_keys) . " trainee keys, table_id: $table_id, " . count($date_params) . " date params");
    
    // Build WHERE clause for trainee keys
    if (count($trainee_keys) > 1) {
        $trainkey_placeholders = str_repeat('?,', count($trainee_keys) - 1) . '?';
        $trainkey_condition = "tl.trainkey IN ($trainkey_placeholders)";
    } else {
        $trainkey_condition = "tl.trainkey = ?";
    }
    
    // Create intelligent ranges based on data distribution
                $query = "
                    SELECT 
                        CASE 
                WHEN CAST(tl.select_val AS DECIMAL(10,2)) = 0 THEN 'Zero'
                            WHEN CAST(tl.select_val AS DECIMAL(10,2)) < 1 THEN '< 1'
                            WHEN CAST(tl.select_val AS DECIMAL(10,2)) < 5 THEN '1-5'
                            WHEN CAST(tl.select_val AS DECIMAL(10,2)) < 10 THEN '5-10'
                WHEN CAST(tl.select_val AS DECIMAL(10,2)) < 25 THEN '10-25'
                WHEN CAST(tl.select_val AS DECIMAL(10,2)) < 50 THEN '25-50'
                WHEN CAST(tl.select_val AS DECIMAL(10,2)) < 100 THEN '50-100'
                ELSE '100+'
                        END as category,
            COUNT(*) as count,
                        AVG(CAST(tl.select_val AS DECIMAL(10,2))) as avg_value,
            MIN(CAST(tl.select_val AS DECIMAL(10,2))) as min_value,
            MAX(CAST(tl.select_val AS DECIMAL(10,2))) as max_value
                    FROM trainee_log tl
        WHERE $trainkey_condition
                    AND tl.tbid = ? 
                    AND tl.stid = ?
                    AND tl.select_val IS NOT NULL 
                    AND tl.select_val != ''
        AND tl.select_val REGEXP '^[0-9]+\.?[0-9]*$'
                    $date_condition
                    GROUP BY category
                    ORDER BY 
                        CASE category
                WHEN 'Zero' THEN 1
                WHEN '< 1' THEN 2
                WHEN '1-5' THEN 3
                WHEN '5-10' THEN 4
                WHEN '10-25' THEN 5
                WHEN '25-50' THEN 6
                WHEN '50-100' THEN 7
                WHEN '100+' THEN 8
            END
    ";
    
    // Build final parameters: trainee_keys + table_id + field_x + date_params
    $final_params = array_merge($trainee_keys, [$table_id, $field_x], $date_params);
    $final_param_types = str_repeat('s', count($trainee_keys)) . 'ii' . str_repeat('i', count($date_params));
    
    error_log("Final params count: " . count($final_params) . ", types: $final_param_types");
    
    return executeSimpleQuery($mysqli, $query, $final_params, $final_param_types, 'avg_value');
}

// Time series analysis
function getTimeSeriesAnalysis($mysqli, $unused_param, $field_x, $x_field_type, $date_condition, $base_params, $base_param_types, $time_frame) {
    error_log("Executing time series analysis for field $field_x with " . count($base_params) . " base params");
    
    // Extract components from base_params
    $all_params = $base_params;
    
    // Find table_id (it's an integer in the middle-end of the array)
    $table_id = null;
    $trainee_keys = [];
    $date_params = [];
    
    for ($i = 0; $i < count($all_params); $i++) {
        if (is_numeric($all_params[$i]) && intval($all_params[$i]) > 0) {
            if (intval($all_params[$i]) < 100) {
                $table_id = intval($all_params[$i]);
                $trainee_keys = array_slice($all_params, 0, $i);
                $date_params = array_slice($all_params, $i + 1);
                break;
            }
        }
    }
    
    error_log("Extracted: " . count($trainee_keys) . " trainee keys, table_id: $table_id, " . count($date_params) . " date params");
    
    // Build WHERE clause for trainee keys
    if (count($trainee_keys) > 1) {
        $trainkey_placeholders = str_repeat('?,', count($trainee_keys) - 1) . '?';
        $trainkey_condition = "tl.trainkey IN ($trainkey_placeholders)";
    } else {
        $trainkey_condition = "tl.trainkey = ?";
    }
    
    if ($x_field_type == 3) {
        // Date field - analyze actual dates in the field
                $query = "
                    SELECT 
                        DATE_FORMAT(STR_TO_DATE(tl.select_val, '%Y-%m-%d'), '%Y-%m') as category,
                        COUNT(*) as count
                    FROM trainee_log tl
            WHERE $trainkey_condition
                    AND tl.tbid = ? 
                    AND tl.stid = ?
                    AND tl.select_val IS NOT NULL 
                    AND tl.select_val != ''
                    $date_condition
                    GROUP BY category
            ORDER BY category ASC
            LIMIT 24
                ";
    } elseif ($x_field_type == 6) {
        // Time field - analyze time patterns
                $query = "
                    SELECT 
                        CASE 
                            WHEN TIME(tl.select_val) < '06:00:00' THEN 'Early Morning (00-06)'
                            WHEN TIME(tl.select_val) < '12:00:00' THEN 'Morning (06-12)'
                            WHEN TIME(tl.select_val) < '18:00:00' THEN 'Afternoon (12-18)'
                            ELSE 'Evening (18-24)'
                        END as category,
                COUNT(*) as count,
                AVG(HOUR(TIME(tl.select_val))) as avg_hour
                    FROM trainee_log tl
            WHERE $trainkey_condition
                    AND tl.tbid = ? 
                    AND tl.stid = ?
                    AND tl.select_val IS NOT NULL 
                    AND tl.select_val != ''
                    $date_condition
                    GROUP BY category
                    ORDER BY 
                        CASE category
                            WHEN 'Early Morning (00-06)' THEN 1
                            WHEN 'Morning (06-12)' THEN 2
                            WHEN 'Afternoon (12-18)' THEN 3
                            WHEN 'Evening (18-24)' THEN 4
                        END
                ";
    } else {
        // Entry date analysis - when data was entered
        $date_format = ($time_frame === 'last30' || $time_frame === 'last90') ? '%Y-%m-%d' : '%Y-%m';
                $query = "
                    SELECT 
                DATE_FORMAT(STR_TO_DATE(tl.date_added, '%Y%m%d'), '$date_format') as category,
                        COUNT(*) as count
                    FROM trainee_log tl
            WHERE $trainkey_condition
                    AND tl.tbid = ? 
                    AND tl.stid = ?
                    $date_condition
                    GROUP BY category
            ORDER BY category ASC
        ";
    }
    
    // Build final parameters: trainee_keys + table_id + field_x + date_params
    $final_params = array_merge($trainee_keys, [$table_id, $field_x], $date_params);
    $final_param_types = str_repeat('s', count($trainee_keys)) . 'ii' . str_repeat('i', count($date_params));
    
    error_log("Final params count: " . count($final_params) . ", types: $final_param_types");
    
    return executeSimpleQuery($mysqli, $query, $final_params, $final_param_types, 'count');
}

// Text analysis
function getTextAnalysis($mysqli, $unused_param, $field_x, $date_condition, $base_params, $base_param_types) {
    error_log("Executing text analysis for field $field_x with " . count($base_params) . " base params");
    
    // Extract components from base_params
    $all_params = $base_params;
    
    // Find table_id (it's an integer in the middle-end of the array)
    $table_id = null;
    $trainee_keys = [];
    $date_params = [];
    
    for ($i = 0; $i < count($all_params); $i++) {
        if (is_numeric($all_params[$i]) && intval($all_params[$i]) > 0) {
            if (intval($all_params[$i]) < 100) {
                $table_id = intval($all_params[$i]);
                $trainee_keys = array_slice($all_params, 0, $i);
                $date_params = array_slice($all_params, $i + 1);
                break;
            }
        }
    }
    
    error_log("Extracted: " . count($trainee_keys) . " trainee keys, table_id: $table_id, " . count($date_params) . " date params");
    
    // Build WHERE clause for trainee keys
    if (count($trainee_keys) > 1) {
        $trainkey_placeholders = str_repeat('?,', count($trainee_keys) - 1) . '?';
        $trainkey_condition = "tl.trainkey IN ($trainkey_placeholders)";
            } else {
        $trainkey_condition = "tl.trainkey = ?";
    }
    
                $query = "
                    SELECT 
            CASE 
                WHEN LENGTH(TRIM(tl.select_val)) = 0 THEN 'Empty'
                WHEN LENGTH(TRIM(tl.select_val)) < 10 THEN 'Very Short (< 10 chars)'
                WHEN LENGTH(TRIM(tl.select_val)) < 25 THEN 'Short (10-25 chars)'
                WHEN LENGTH(TRIM(tl.select_val)) < 50 THEN 'Medium (25-50 chars)'
                WHEN LENGTH(TRIM(tl.select_val)) < 100 THEN 'Long (50-100 chars)'
                WHEN LENGTH(TRIM(tl.select_val)) < 200 THEN 'Very Long (100-200 chars)'
                ELSE 'Extremely Long (200+ chars)'
            END as category,
            COUNT(*) as count,
            AVG(LENGTH(TRIM(tl.select_val))) as avg_length,
            AVG(CHAR_LENGTH(TRIM(tl.select_val)) - CHAR_LENGTH(REPLACE(TRIM(tl.select_val), ' ', ''))) as avg_word_count
                    FROM trainee_log tl
        WHERE $trainkey_condition
                    AND tl.tbid = ? 
                    AND tl.stid = ?
        AND tl.select_val IS NOT NULL 
                    $date_condition
                    GROUP BY category
        ORDER BY 
            CASE category
                WHEN 'Empty' THEN 1
                WHEN 'Very Short (< 10 chars)' THEN 2
                WHEN 'Short (10-25 chars)' THEN 3
                WHEN 'Medium (25-50 chars)' THEN 4
                WHEN 'Long (50-100 chars)' THEN 5
                WHEN 'Very Long (100-200 chars)' THEN 6
                WHEN 'Extremely Long (200+ chars)' THEN 7
            END
    ";
    
    // Build final parameters: trainee_keys + table_id + field_x + date_params
    $final_params = array_merge($trainee_keys, [$table_id, $field_x], $date_params);
    $final_param_types = str_repeat('s', count($trainee_keys)) . 'ii' . str_repeat('i', count($date_params));
    
    error_log("Final params count: " . count($final_params) . ", types: $final_param_types");
    
    return executeSimpleQuery($mysqli, $query, $final_params, $final_param_types, 'count');
}

// Basic counting fallback
function getBasicCount($mysqli, $unused_param, $field_x, $date_condition, $base_params, $base_param_types) {
    error_log("Executing basic count analysis for field $field_x with " . count($base_params) . " base params");
    
    // Extract components from base_params
    $all_params = $base_params;
    
    // Find table_id (it's an integer in the middle-end of the array)
    $table_id = null;
    $trainee_keys = [];
    $date_params = [];
    
    for ($i = 0; $i < count($all_params); $i++) {
        if (is_numeric($all_params[$i]) && intval($all_params[$i]) > 0) {
            if (intval($all_params[$i]) < 100) {
                $table_id = intval($all_params[$i]);
                $trainee_keys = array_slice($all_params, 0, $i);
                $date_params = array_slice($all_params, $i + 1);
                break;
            }
        }
    }
    
    error_log("Extracted: " . count($trainee_keys) . " trainee keys, table_id: $table_id, " . count($date_params) . " date params");
    
    // Build WHERE clause for trainee keys
    if (count($trainee_keys) > 1) {
        $trainkey_placeholders = str_repeat('?,', count($trainee_keys) - 1) . '?';
        $trainkey_condition = "tl.trainkey IN ($trainkey_placeholders)";
    } else {
        $trainkey_condition = "tl.trainkey = ?";
    }
    
    $query = "
        SELECT 
            COALESCE(tl.select_val, 'Unknown') as category,
            COUNT(*) as count
        FROM trainee_log tl
        WHERE $trainkey_condition
        AND tl.tbid = ?
        AND tl.stid = ?
        $date_condition
        GROUP BY category
        ORDER BY count DESC
        LIMIT 15
    ";
    
    // Build final parameters: trainee_keys + table_id + field_x + date_params
    $final_params = array_merge($trainee_keys, [$table_id, $field_x], $date_params);
    $final_param_types = str_repeat('s', count($trainee_keys)) . 'ii' . str_repeat('i', count($date_params));
    
    error_log("Final params count: " . count($final_params) . ", types: $final_param_types");
    
    return executeSimpleQuery($mysqli, $query, $final_params, $final_param_types, 'count');
}

// Stub functions for multi-field analysis (to be implemented later)
function getCategoricalVsNumeric($mysqli, $unused_param, $field_x, $field_y, $x_field_type, $y_field_type, $date_condition_multi, $base_params, $base_param_types) {
    error_log("Executing categorical vs numeric analysis: field $field_x vs field $field_y");
    return []; // Stub for now - will fallback to single field analysis
}

function getCategoricalVsCategorical($mysqli, $unused_param, $field_x, $field_y, $x_field_type, $y_field_type, $date_condition_multi, $base_params, $base_param_types) {
    error_log("Executing categorical vs categorical analysis: field $field_x vs field $field_y");
    return []; // Stub for now - will fallback to single field analysis
}

function getNumericVsNumeric($mysqli, $unused_param, $field_x, $field_y, $date_condition_multi, $base_params, $base_param_types, $chart_type) {
    error_log("Executing numeric vs numeric analysis: field $field_x vs field $field_y");
    return []; // Stub for now - will fallback to single field analysis
}

function getTimeVsNumeric($mysqli, $unused_param, $field_x, $field_y, $x_field_type, $y_field_type, $date_condition_multi, $base_params, $base_param_types, $time_frame) {
    error_log("Executing time vs numeric analysis: field $field_x vs field $field_y");
    return []; // Stub for now - will fallback to single field analysis
}

function getTimeVsCategorical($mysqli, $unused_param, $field_x, $field_y, $x_field_type, $y_field_type, $date_condition_multi, $base_params, $base_param_types, $time_frame) {
    error_log("Executing time vs categorical analysis: field $field_x vs field $field_y");
    return []; // Stub for now - will fallback to single field analysis
}

// Helper function to execute queries consistently
function executeSimpleQuery($mysqli, $query, $params, $param_types, $value_field = 'count') {
    error_log("=== executeSimpleQuery START ===");
    error_log("Query: " . str_replace(["\n", "\t"], [" ", " "], trim($query)));
    error_log("Params count: " . count($params));
    error_log("Param types: $param_types");
    error_log("Param types length: " . strlen($param_types));
    
    // Count placeholders in query
    $placeholder_count = substr_count($query, '?');
    error_log("Placeholder count in query: $placeholder_count");
    
    if (count($params) !== $placeholder_count) {
        error_log("ERROR: Parameter count mismatch! Params: " . count($params) . ", Placeholders: $placeholder_count");
        error_log("Params array: " . print_r($params, true));
        return [];
    }
    
    if (strlen($param_types) !== count($params)) {
        error_log("ERROR: Param types length mismatch! Types: " . strlen($param_types) . ", Params: " . count($params));
        return [];
    }
    
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $value = isset($row[$value_field]) ? $row[$value_field] : (isset($row['count']) ? $row['count'] : 0);
            
            // Handle different value types appropriately
            if ($value_field === 'avg_value' && is_numeric($value)) {
                $value = round((float)$value, 2);
            } else {
                $value = (int)$value;
            }
            
            $data[] = [
                'label' => $row['category'],
                'value' => $value
            ];
        }
        $stmt->close();
        error_log("Query returned " . count($data) . " results");
        error_log("=== executeSimpleQuery END ===");
        return $data;
    }
    
    error_log("Failed to prepare query");
    error_log("MySQL error: " . $mysqli->error);
    error_log("=== executeSimpleQuery END (FAILED) ===");
    return [];
}
?> 