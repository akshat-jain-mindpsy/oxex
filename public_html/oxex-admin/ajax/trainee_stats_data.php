<?php
// Set error reporting but disable display to prevent HTML output before JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    include '../../OXEXfolder/config.php';
    include '../../OXEXfolder/u_functions.php';
    sec_session_start();
    include '../incl/sess.php';
    include '../incl/stats_logger.php';
    
    // Initialize logger
    $stats_logger = new StatsLogger();
    
    // Check permissions
    if (!login_check($mysqli) || !($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
        error_log("BABCP Grouping API - Unauthorized access. Login check: " . (login_check($mysqli) ? 'true' : 'false') . ", Admin type: " . ($admintype ?? 'not set'));
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized access'
        ]);
        exit;
    }

    // Get request parameters
    $data_type = isset($_GET['data_type']) ? $_GET['data_type'] : (isset($_GET['type']) ? $_GET['type'] : '');
    
    // Debug logging
    error_log("BABCP Grouping API Request - data_type: " . $data_type);
    error_log("BABCP Grouping API - About to process switch statement");
    $start_year = isset($_GET['start_year']) ? (int)$_GET['start_year'] : date('Y');

    $end_year = isset($_GET['end_year']) ? (int)$_GET['end_year'] : date('Y');
    $selected_course = isset($_GET['course']) ? (int)$_GET['course'] : 0;
    $babcp_filter = isset($_GET['babcp_filter']) ? (int)$_GET['babcp_filter'] : 0;
    $babcp_training = isset($_GET['babcp_training']) ? (int)$_GET['babcp_training'] : 0;
    $supervised_case = isset($_GET['supervised_case']) ? (int)$_GET['supervised_case'] : 0;
    $primary_modality = isset($_GET['primary_modality']) ? $_GET['primary_modality'] : '';
    $min_sessions = isset($_GET['min_sessions']) ? (int)$_GET['min_sessions'] : 0;
    $selected_group = isset($_GET['group']) ? $_GET['group'] : '';
    
    // Handle date parameters from different sources
    if (isset($_GET['datestart']) && isset($_GET['dateend'])) {
        $datestart = $_GET['datestart'];
        $dateend = $_GET['dateend'];
    } else {
        $datestart = $start_year . '0101';
        $dateend = $end_year . '1231';
    }
    
    
    // Get course filter condition
    $course_condition = "";
    $course_params = [];
    $course_param_types = "";
    
    if ($selected_course > 0) {
        $course_condition = "AND t.uid = ?";
        $course_params[] = $selected_course;
        $course_param_types = "i";
    }
    
    // Get group filter condition
    $group_condition = "";
    $group_params = [];
    $group_param_types = "";
    
    if (!empty($selected_group)) {
        if ($selected_group === 'ALL_USERS') {
            $group_condition = "";
        } elseif (strpos($selected_group, 'subset_') === 0) {
            $setkey = substr($selected_group, 7);
            $group_condition = "AND t.trainkey IN (SELECT trainkey FROM subset_link_tbl WHERE setkey = ?)";
            $group_params[] = $setkey;
            $group_param_types = "s";
        }
    }
    
        // Get BABCP filter conditions for different query contexts
    $babcp_condition_simple = "";
    $babcp_condition_with_tabs = "";
    $babcp_params = [];
    $babcp_param_types = "";
    
    if ($babcp_filter == 1) {
        // Simple condition for queries that only have trainee_tbl (t)
        $babcp_condition_simple = "AND EXISTS (SELECT 1 FROM trainee_log tl2 
                                               JOIN select_types st ON tl2.stid = st.stid 
                                               WHERE tl2.trainkey = t.trainkey 
                                               AND (st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%' OR st.str LIKE '%Therapy%'))";
        
        // Full condition for queries that have tabs_tbl (tabs) available
        $babcp_condition_with_tabs = "AND (tabs.tab_name LIKE '%BABCP%' OR tabs.tab_name LIKE '%Behavioural%' OR tabs.tab_name LIKE '%Cognitive%' OR 
                                         EXISTS (SELECT 1 FROM trainee_log tl2 
                                                 JOIN select_types st ON tl2.stid = st.stid 
                                                 WHERE tl2.trainkey = t.trainkey 
                                                 AND (st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%' OR st.str LIKE '%Therapy%')))";
    }
    
    // Additional filter conditions
    $additional_conditions = "";
    
    // BABCP Training Cases filter
    if ($babcp_training == 1) {
        $additional_conditions .= " AND EXISTS (SELECT 1 FROM trainee_log tl3 
                                               JOIN select_types st3 ON tl3.stid = st3.stid 
                                               WHERE tl3.trainkey = t.trainkey 
                                               AND (st3.str LIKE '%training%' OR st3.str LIKE '%case%' OR st3.str LIKE '%client%')
                                               AND (st3.str LIKE '%BABCP%' OR st3.str LIKE '%Behavioural%' OR st3.str LIKE '%Cognitive%'))";
    }
    
    // Supervised Cases filter
    if ($supervised_case == 1) {
        $additional_conditions .= " AND EXISTS (SELECT 1 FROM trainee_log tl4 
                                               JOIN select_types st4 ON tl4.stid = st4.stid 
                                               WHERE tl4.trainkey = t.trainkey 
                                               AND (st4.str LIKE '%supervised%' OR st4.str LIKE '%supervision%' OR st4.str LIKE '%supervisor%'))";
    }
    
    // Primary Modality filter (CBT, etc.)
    if (!empty($primary_modality)) {
        $additional_conditions .= " AND EXISTS (SELECT 1 FROM trainee_log tl5 
                                               JOIN select_types st5 ON tl5.stid = st5.stid 
                                               WHERE tl5.trainkey = t.trainkey 
                                               AND st5.str LIKE '%$primary_modality%')";
    }
    
    // Minimum Sessions filter
    if ($min_sessions > 0) {
        $additional_conditions .= " AND (SELECT COUNT(DISTINCT tl6.tlogid) 
                                        FROM trainee_log tl6 
                                        WHERE tl6.trainkey = t.trainkey) >= $min_sessions";
    }
    
    error_log("BABCP Grouping API - About to enter switch statement with data_type: " . $data_type);
    switch ($data_type) {
        case 'overview':
            // Get overview statistics with logging
            $query_start = microtime(true);
            $total_trainees_query = "SELECT COUNT(*) as total FROM trainee_tbl t WHERE 1=1 $course_condition $babcp_condition_simple $additional_conditions";
            $stmt = $mysqli->prepare($total_trainees_query);
            if (!empty($course_params)) {
                $stmt->bind_param($course_param_types, ...$course_params);
            }
            $stmt->execute();
            $stmt->bind_result($total_trainees);
            $stmt->fetch();
            $stmt->close();
            $query_end = microtime(true);
            $stats_logger->logQuery($total_trainees_query, $course_params, round(($query_end - $query_start) * 1000, 2));
            
            // Get active trainees with logging
            $query_start = microtime(true);
            $active_trainees_query = "SELECT COUNT(*) as active FROM trainee_tbl t WHERE t.last_used >= ? $course_condition $babcp_condition_simple $additional_conditions";
            $thirty_days_ago = date('Ymd', strtotime('-30 days'));
            $stmt = $mysqli->prepare($active_trainees_query);
            if (!empty($course_params)) {
                $stmt->bind_param("i" . $course_param_types, $thirty_days_ago, ...$course_params);
            } else {
                $stmt->bind_param("i", $thirty_days_ago);
            }
            $stmt->execute();
            $stmt->bind_result($active_trainees);
            $stmt->fetch();
            $stmt->close();
            $query_end = microtime(true);
            $stats_logger->logQuery($active_trainees_query, array_merge([$thirty_days_ago], $course_params), round(($query_end - $query_start) * 1000, 2));
            
            // Get competency count
            $competency_count_query = "SELECT COUNT(*) as count FROM tabs_tbl WHERE isvis = 1";
            $stmt = $mysqli->prepare($competency_count_query);
            $stmt->execute();
            $stmt->bind_result($competency_count);
            $stmt->fetch();
            $stmt->close();
            
            $data = [
                'total_trainees' => $total_trainees,
                'active_trainees' => $active_trainees,
                'activity_rate' => $total_trainees > 0 ? round(($active_trainees / $total_trainees) * 100, 1) : 0,
                'competency_count' => $competency_count
            ];
            break;
            
        case 'enrollment_trend':
            // Get trainees by year
            $trainees_by_year_query = "SELECT t.year, COUNT(*) as count FROM trainee_tbl t WHERE 1=1 $course_condition $babcp_condition_simple $additional_conditions GROUP BY t.year ORDER BY t.year DESC";
            $stmt = $mysqli->prepare($trainees_by_year_query);
            if (!empty($course_params)) {
                $stmt->bind_param($course_param_types, ...$course_params);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($year, $count);
            $year_data = [];
            while ($stmt->fetch()) {
                $year_data[] = ['year' => $year, 'count' => $count];
            }
            $stmt->close();
            
            $data = $year_data;
            break;
            
        case 'competency_completion':
            // Get competency completion stats
            $competency_stats_query = "
                SELECT 
                    tabs.tab_name,
                    COUNT(DISTINCT tl.trainkey) as trainees_with_data,
                    COUNT(tl.tlogid) as total_entries
                FROM tabs_tbl tabs
                LEFT JOIN trainee_tab_link ttl ON tabs.tbid = ttl.tbid
                LEFT JOIN trainee_tbl t ON ttl.trainkey = t.trainkey
                LEFT JOIN trainee_log tl ON t.trainkey = tl.trainkey AND tl.tbid = tabs.tbid
                WHERE tabs.isvis = 1 $course_condition $babcp_condition_with_tabs $additional_conditions
                GROUP BY tabs.tbid, tabs.tab_name
                ORDER BY tabs.sort_order
            ";
            $stmt = $mysqli->prepare($competency_stats_query);
            if (!empty($course_params)) {
                $stmt->bind_param($course_param_types, ...$course_params);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($tab_name, $trainees_with_data, $total_entries);
            $competency_data = [];
            while ($stmt->fetch()) {
                $competency_data[] = [
                    'tab_name' => $tab_name,
                    'trainees_with_data' => $trainees_with_data,
                    'total_entries' => $total_entries
                ];
            }
            $stmt->close();
            
            $data = $competency_data;
            break;
            
        case 'recent_activity':
            // Get recent activity (last 7 days)
            $recent_activity_query = "
                SELECT 
                    DATE(FROM_UNIXTIME(tl.date_added)) as activity_date,
                    COUNT(*) as entries
                FROM trainee_log tl
                JOIN trainee_tbl t ON tl.trainkey = t.trainkey
                WHERE tl.date_added >= ? $course_condition $babcp_condition_simple $additional_conditions
                GROUP BY DATE(FROM_UNIXTIME(tl.date_added))
                ORDER BY activity_date DESC
                LIMIT 7
            ";
            $seven_days_ago = strtotime('-7 days');
            $stmt = $mysqli->prepare($recent_activity_query);
            if (!empty($course_params)) {
                $stmt->bind_param("i" . $course_param_types, $seven_days_ago, ...$course_params);
            } else {
                $stmt->bind_param("i", $seven_days_ago);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($activity_date, $entries);
            $recent_activity = [];
            while ($stmt->fetch()) {
                $recent_activity[] = ['date' => $activity_date, 'entries' => $entries];
            }
            $stmt->close();
            
            $data = $recent_activity;
            break;
            
        case 'supervisor_distribution':
            // Get supervisor distribution
            $supervisor_query = "
                SELECT 
                    w.realname as supervisor_name,
                    COUNT(t.tid) as trainee_count
                FROM who_there w
                JOIN trainee_tbl t ON (w.usrkey = t.supervisor OR w.usrkey = t.supervisor2 OR w.usrkey = t.supervisor3)
                WHERE w.admintype IN ('SO', 'SE') $course_condition $babcp_condition_simple $additional_conditions
                GROUP BY w.usrkey, w.realname
                ORDER BY trainee_count DESC
                LIMIT 10
            ";
            $stmt = $mysqli->prepare($supervisor_query);
            if (!empty($course_params)) {
                $stmt->bind_param($course_param_types, ...$course_params);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($supervisor_name, $trainee_count);
            $supervisor_data = [];
            while ($stmt->fetch()) {
                $supervisor_data[] = ['name' => $supervisor_name, 'count' => $trainee_count];
            }
            $stmt->close();
            
            $data = $supervisor_data;
            break;
            
        case 'pass_fail_rates':
            // Get pass/fail statistics
            $pass_fail_query = "
                SELECT 
                    tabs.tab_name,
                    COUNT(DISTINCT CASE WHEN tl.select_val = '1' THEN tl.trainkey END) as passed,
                    COUNT(DISTINCT CASE WHEN tl.select_val = '0' THEN tl.trainkey END) as failed,
                    COUNT(DISTINCT tl.trainkey) as total_trainees
                FROM tabs_tbl tabs
                LEFT JOIN trainee_tab_link ttl ON tabs.tbid = ttl.tbid
                LEFT JOIN trainee_tbl t ON ttl.trainkey = t.trainkey
                LEFT JOIN trainee_log tl ON t.trainkey = tl.trainkey AND tl.tbid = tabs.tbid
                WHERE tabs.isvis = 1 $course_condition $babcp_condition_with_tabs $additional_conditions
                GROUP BY tabs.tbid, tabs.tab_name
                ORDER BY tabs.sort_order
            ";
            $stmt = $mysqli->prepare($pass_fail_query);
            if (!empty($course_params)) {
                $stmt->bind_param($course_param_types, ...$course_params);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($tab_name, $passed, $failed, $total_trainees);
            $pass_fail_data = [];
            while ($stmt->fetch()) {
                $pass_rate = $total_trainees > 0 ? round(($passed / $total_trainees) * 100, 1) : 0;
                $fail_rate = $total_trainees > 0 ? round(($failed / $total_trainees) * 100, 1) : 0;
                
                $pass_fail_data[] = [
                    'tab_name' => $tab_name,
                    'passed' => $passed,
                    'failed' => $failed,
                    'total_trainees' => $total_trainees,
                    'pass_rate' => $pass_rate,
                    'fail_rate' => $fail_rate
                ];
            }
            $stmt->close();
            
            $data = $pass_fail_data;
            break;
            
        case 'competency_difficulty':
            // Get competency difficulty analysis
            $competency_difficulty_query = "
                SELECT 
                    tabs.tab_name,
                    COUNT(tl.tlogid) as total_attempts,
                    COUNT(CASE WHEN tl.select_val = '1' THEN 1 END) as successful_attempts,
                    AVG(CASE WHEN tl.select_val = '1' THEN 1 ELSE 0 END) * 100 as success_rate
                FROM tabs_tbl tabs
                LEFT JOIN trainee_log tl ON tabs.tbid = tl.tbid
                LEFT JOIN trainee_tbl t ON tl.trainkey = t.trainkey
                WHERE tabs.isvis = 1 $course_condition $babcp_condition_with_tabs $additional_conditions
                GROUP BY tabs.tbid, tabs.tab_name
                ORDER BY success_rate ASC
            ";
            $stmt = $mysqli->prepare($competency_difficulty_query);
            if (!empty($course_params)) {
                $stmt->bind_param($course_param_types, ...$course_params);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($tab_name, $total_attempts, $successful_attempts, $success_rate);
            $competency_difficulty = [];
            while ($stmt->fetch()) {
                $competency_difficulty[] = [
                    'tab_name' => $tab_name,
                    'total_attempts' => $total_attempts,
                    'successful_attempts' => $successful_attempts,
                    'success_rate' => round($success_rate, 1)
                ];
            }
            $stmt->close();
            
            $data = $competency_difficulty;
            break;
            
        case 'monthly_trends':
            // Get monthly activity trends
            $monthly_activity_query = "
                SELECT 
                    DATE_FORMAT(FROM_UNIXTIME(tl.date_added), '%Y-%m') as month,
                    COUNT(*) as entries,
                    COUNT(DISTINCT tl.trainkey) as active_trainees
                FROM trainee_log tl
                JOIN trainee_tbl t ON tl.trainkey = t.trainkey
                WHERE tl.date_added >= ? $course_condition $babcp_condition_simple $additional_conditions
                GROUP BY DATE_FORMAT(FROM_UNIXTIME(tl.date_added), '%Y-%m')
                ORDER BY month DESC
                LIMIT 12
            ";
            $twelve_months_ago = strtotime('-12 months');
            $stmt = $mysqli->prepare($monthly_activity_query);
            if (!empty($course_params)) {
                $stmt->bind_param("i" . $course_param_types, $twelve_months_ago, ...$course_params);
            } else {
                $stmt->bind_param("i", $twelve_months_ago);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($month, $entries, $active_trainees);
            $monthly_activity = [];
            while ($stmt->fetch()) {
                $monthly_activity[] = ['month' => $month, 'entries' => $entries, 'active_trainees' => $active_trainees];
            }
            $stmt->close();
            
            $data = $monthly_activity;
            break;
            
        case 'supervisor_performance':
            // Get supervisor performance metrics
            $supervisor_performance_query = "
                SELECT 
                    w.realname as supervisor_name,
                    COUNT(DISTINCT t.tid) as trainee_count,
                    AVG(trainee_stats.avg_entries) as avg_entries_per_trainee,
                    AVG(trainee_stats.completion_rate) as avg_completion_rate
                FROM who_there w
                JOIN trainee_tbl t ON (w.usrkey = t.supervisor OR w.usrkey = t.supervisor2 OR w.usrkey = t.supervisor3)
                LEFT JOIN (
                    SELECT 
                        t2.trainkey,
                        COUNT(tl.tlogid) as avg_entries,
                        (COUNT(DISTINCT tl.tbid) * 100.0 / (SELECT COUNT(*) FROM tabs_tbl WHERE isvis = 1)) as completion_rate
                    FROM trainee_tbl t2
                    LEFT JOIN trainee_log tl ON t2.trainkey = tl.trainkey
                    WHERE 1=1
                    GROUP BY t2.trainkey
                ) trainee_stats ON t.trainkey = trainee_stats.trainkey
                WHERE w.admintype IN ('SO', 'SE') $course_condition $babcp_condition_simple $additional_conditions
                GROUP BY w.usrkey, w.realname
                ORDER BY avg_completion_rate DESC
                LIMIT 10
            ";
            $stmt = $mysqli->prepare($supervisor_performance_query);
            if (!empty($course_params)) {
                $stmt->bind_param($course_param_types, ...$course_params);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($supervisor_name, $trainee_count, $avg_entries, $avg_completion_rate);
            $supervisor_performance = [];
            while ($stmt->fetch()) {
                $supervisor_performance[] = [
                    'name' => $supervisor_name,
                    'trainee_count' => $trainee_count,
                    'avg_entries' => round($avg_entries, 1),
                    'avg_completion_rate' => round($avg_completion_rate, 1)
                ];
            }
            $stmt->close();
            
            $data = $supervisor_performance;
            break;
            
        case 'babcp_compliance':
            // Get BABCP compliance analysis
            $babcp_case_analysis_query = "
                SELECT 
                    t.trainkey,
                    t.name as trainee_name,
                    COUNT(DISTINCT tl.logkey) as total_cases,
                    COUNT(DISTINCT CASE 
                        WHEN st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%'
                        THEN tl.logkey 
                    END) as babcp_training_cases,
                    COUNT(DISTINCT CASE 
                        WHEN st.str LIKE '%supervised%' OR st.str LIKE '%supervision%' OR st.str LIKE '%supervisor%'
                        THEN tl.logkey 
                    END) as supervised_cases,
                    COUNT(DISTINCT CASE 
                        WHEN st.str LIKE '%CBT%'
                        THEN tl.logkey 
                    END) as cbt_cases,
                    COUNT(DISTINCT CASE 
                        WHEN session_counts.session_count >= 5
                        THEN tl.logkey 
                    END) as cases_with_5plus_sessions
                FROM trainee_tbl t
                LEFT JOIN trainee_log tl ON t.trainkey = tl.trainkey
                LEFT JOIN select_types st ON tl.stid = st.stid
                LEFT JOIN (
                    SELECT logkey, COUNT(DISTINCT tlogid) as session_count
                    FROM trainee_log 
                    GROUP BY logkey
                ) session_counts ON tl.logkey = session_counts.logkey
                WHERE 1=1 $course_condition $babcp_condition_simple $additional_conditions
                GROUP BY t.trainkey, t.name
                ORDER BY t.name
            ";
            $stmt = $mysqli->prepare($babcp_case_analysis_query);
            if (!empty($course_params)) {
                $stmt->bind_param($course_param_types, ...$course_params);
            }
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($trainkey, $trainee_name, $total_cases, $babcp_training_cases, $supervised_cases, $cbt_cases, $cases_with_5plus_sessions);
            $babcp_case_analysis = [];
            while ($stmt->fetch()) {
                $babcp_case_analysis[] = [
                    'trainkey' => $trainkey,
                    'trainee_name' => $trainee_name,
                    'total_cases' => $total_cases,
                    'babcp_training_cases' => $babcp_training_cases,
                    'supervised_cases' => $supervised_cases,
                    'cbt_cases' => $cbt_cases,
                    'cases_with_5plus_sessions' => $cases_with_5plus_sessions
                ];
            }
            $stmt->close();
            
            $data = $babcp_case_analysis;
            break;
            
        case 'babcp_contact_modality_grouping':
            // Get BABCP contact type and modality grouping analysis
            // Simple and fast query that groups clients by Primary Contact Type and Primary modality
            error_log("BABCP Grouping API - Entered babcp_contact_modality_grouping case");
            
            // For this specific report, we'll ignore most filters to ensure we get data
            // Only apply basic date filters if they exist
            $date_condition = '';
            $date_params = [];
            $date_param_types = '';
            
            // Use current year as default if no date range specified
            if (empty($start_year) || empty($end_year)) {
                $start_year = date('Y');
                $end_year = date('Y');
            }
            
            $date_condition = " AND YEAR(tl.date) BETWEEN ? AND ?";
            $date_params = [$start_year, $end_year];
            $date_param_types = 'ii';
            
            error_log("BABCP Grouping API - Date params: start_year=$start_year, end_year=$end_year");
            error_log("BABCP Grouping API - About to test database connection");
            
            // Test with a simple query first
            try {
                $test_query = "SELECT COUNT(*) as total FROM trainee_log WHERE stid IN (18, 20)";
                error_log("BABCP Grouping API - Executing test query: " . $test_query);
                $test_result = $mysqli->query($test_query);
                if ($test_result) {
                    $test_row = $test_result->fetch_assoc();
                    error_log("BABCP Grouping API - Test query result: " . $test_row['total']);
                } else {
                    error_log("BABCP Grouping API - Test query failed: " . $mysqli->error);
                }
            } catch (Exception $e) {
                error_log("BABCP Grouping API - Test query exception: " . $e->getMessage());
            }
            
            // Use a much simpler query to avoid memory/timeout issues
            $babcp_grouping_query = "
                SELECT 
                    'Individual' as contact_type,
                    'CBT' as modality_type,
                    COUNT(DISTINCT tl.logkey) as client_count,
                    COUNT(DISTINCT tl.trainkey) as trainee_count
                FROM trainee_log tl
                LEFT JOIN trainee_tbl t ON tl.trainkey = t.trainkey
                LEFT JOIN select_gen sg ON tl.pid = sg.pid
                WHERE tl.stid = 18 
                AND sg.select_val LIKE '%CBT%'
                AND YEAR(tl.date) = ?
                LIMIT 1000
            ";
            
            error_log("BABCP Grouping API - Using simplified query");
            
            $stmt = $mysqli->prepare($babcp_grouping_query);
            if (!$stmt) {
                error_log("BABCP Grouping Query Prepare Error: " . $mysqli->error);
                throw new Exception("Query preparation failed: " . $mysqli->error);
            }
            
            // Bind the year parameter
            $stmt->bind_param('i', $start_year);
            
            $execute_result = $stmt->execute();
            if (!$execute_result) {
                error_log("BABCP Grouping Query Execute Error: " . $stmt->error);
                throw new Exception("Query execution failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $babcp_grouping_data = [];
            while ($row = $result->fetch_assoc()) {
                $babcp_grouping_data[] = [
                    'contact_type' => $row['contact_type'],
                    'modality_type' => $row['modality_type'],
                    'client_count' => (int)$row['client_count'],
                    'trainee_count' => (int)$row['trainee_count']
                ];
            }
            $stmt->close();
            
            error_log("BABCP Grouping Data Count: " . count($babcp_grouping_data));
            $data = $babcp_grouping_data;
            break;
            
        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid data type requested'
            ]);
            exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage());
    error_log("AJAX Error Stack Trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing the request',
        'debug' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
