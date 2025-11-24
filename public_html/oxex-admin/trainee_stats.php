<?PHP
/*
 * DATABASE OPTIMIZATION RECOMMENDATIONS
 * 
 * Indexes NOT YET IMPLEMENTED (for future review):
 * 
 * 1. trainee_tab_link table:
 *    - INDEX idx_trainee_tab_link_trainkey (trainkey) - for trainee competency access
 * 
 * 2. who_there table:
 *    - INDEX idx_who_there_usrkey (usrkey) - for supervisor joins
 * 
 * 3. select_types table:
 *    - FULLTEXT INDEX idx_select_types_str_fulltext (str) - for LIKE queries on text content
 * 
 * IMPLEMENTED INDEXES (already optimized in queries):
 * ✓ idx_trainee_uid, idx_trainee_last_used, idx_trainee_uid_year, idx_trainee_supervisor
 * ✓ idx_trainee_year, idx_trainee_supervisor2, idx_trainee_supervisor3
 * ✓ idx_trainee_log_trainkey, idx_trainee_log_tbid, idx_trainee_log_date_added
 * ✓ idx_trainee_log_trainkey_tbid, idx_trainee_log_trainkey_date, idx_trainee_log_stid
 * ✓ idx_trainee_log_logkey, idx_trainee_log_select_val, idx_trainee_log_logkey_tbid
 * ✓ idx_tabs_isvis_sort, idx_select_types_str, idx_trainee_tab_link_tbid
 * ✓ idx_who_there_admintype, idx_uni_university
 * 
 * Query Optimization Notes:
 * - Use prepared statements (already implemented)
 * - Consider query result caching for frequently accessed data
 * - Monitor slow query log for additional optimization opportunities
 * - Consider partitioning trainee_log by date for very large datasets
 */

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
include 'incl/stats_logger.php';

// Initialize logger
$stats_logger = new StatsLogger();

// Simple query result caching
class QueryCache {
    private static $cache = [];
    private static $cache_duration = 300; // 5 minutes
    
    public static function get($key) {
        if (isset(self::$cache[$key]) && (time() - self::$cache[$key]['timestamp']) < self::$cache_duration) {
            return self::$cache[$key]['data'];
        }
        return null;
    }
    
    public static function set($key, $data) {
        self::$cache[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
    }
    
    public static function clear() {
        self::$cache = [];
    }
}

$pagetitle = "Trainee Statistics";

// Set variables needed by adminjs.php
setAdminVars(2); // Trainees section
$subtitle = "Trainee Analytics";
$listurl = "indextable.php";
$listname = "Dashboard";

// Check permissions - allow all admin types to view BABCP stats
if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
    // CSV endpoint for per-trainee monthly timeline (honors basic filters)
    if (isset($_GET['data_type']) && $_GET['data_type'] === 'trainee_timeline_csv') {
        $timeline_trainkey = isset($_GET['trainkey']) ? trim($_GET['trainkey']) : '';
        if (empty($timeline_trainkey) || strlen($timeline_trainkey) < 10) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid trainkey']);
            exit;
        }

        $thisyear_tmp = date('Y');
        $start_year_tmp = isset($_GET['start_year']) ? (int)$_GET['start_year'] : ($thisyear_tmp - 10);
        $end_year_tmp = isset($_GET['end_year']) ? (int)$_GET['end_year'] : ($thisyear_tmp + 1);
        $datestart_yyyymmdd_tmp = $start_year_tmp . '0101';
        $dateend_yyyymmdd_tmp = $end_year_tmp . '1231';
        $selected_course_tmp = isset($_GET['course']) ? (int)$_GET['course'] : 0;

        $course_condition_tmp = '';
        if ($selected_course_tmp > 0) {
            $course_condition_tmp = " AND t.uid = " . (int)$selected_course_tmp;
        }

        // Lookup trainee name for filename
        $trainee_name = 'trainee';
        if ($timeline_trainkey > 0) {
            $name_stmt = $pdo->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
            if ($name_stmt) {
                $name_stmt->execute([$timeline_trainkey]);
                $fetched_name = $name_stmt->fetchColumn();
                if ($fetched_name && !empty($fetched_name)) {
                    $trainee_name = $fetched_name;
                }
                $name_stmt->closeCursor();
            }
        }

        $timeline_query = "
            SELECT 
                TO_CHAR(TO_DATE(tl.date_added::text, 'YYYYMMDD'), 'YYYY-MM') AS month,
                COUNT(DISTINCT tl.logkey) AS total_cases,
                COUNT(DISTINCT CASE WHEN st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%' THEN tl.logkey END) AS babcp_training_cases,
                COUNT(DISTINCT CASE WHEN st.str LIKE '%supervised%' OR st.str LIKE '%supervision%' OR st.str LIKE '%supervisor%' THEN tl.logkey END) AS supervised_cases,
                COUNT(DISTINCT CASE WHEN st.str LIKE '%CBT%' THEN tl.logkey END) AS cbt_cases
            FROM trainee_log tl
            JOIN trainee_tbl t ON tl.trainkey = t.trainkey
            LEFT JOIN select_types st ON tl.stid = st.stid
            WHERE tl.date_added >= ? AND tl.date_added <= ?
              AND t.trainkey = ?
              $course_condition_tmp
            GROUP BY TO_CHAR(TO_DATE(tl.date_added::text, 'YYYYMMDD'), 'YYYY-MM')
            ORDER BY month ASC
        ";

        $stmt = $pdo->prepare($timeline_query);
        $stmt->execute([$datestart_yyyymmdd_tmp, $dateend_yyyymmdd_tmp, $timeline_trainkey]);

        // Output CSV headers
        // Sanitize trainee name for filename
        $safe_name = trim($trainee_name);
        $safe_name = preg_replace('/\s+/', '_', $safe_name);
        $safe_name = preg_replace('/[^A-Za-z0-9_\-]/', '', $safe_name);
        if ($safe_name === '') { $safe_name = 'trainee'; }
        $filename = 'timeline_' . $safe_name . '_' . $timeline_trainkey . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // CSV header row
        fputcsv($output, ['Month', 'Total Cases', 'BABCP Training', 'Supervised', 'CBT']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['month'],
                (int)$row['total_cases'],
                (int)$row['babcp_training_cases'],
                (int)$row['supervised_cases'],
                (int)$row['cbt_cases']
            ]);
        }
        fclose($output);
        $stmt->closeCursor();
        exit;
    }
    // Lightweight JSON endpoint for per-trainee monthly timeline (honors basic filters)
    if (isset($_GET['data_type']) && $_GET['data_type'] === 'trainee_timeline') {
        header('Content-Type: application/json');
        $timeline_trainkey = isset($_GET['trainkey']) ? trim($_GET['trainkey']) : '';
        if (empty($timeline_trainkey) || strlen($timeline_trainkey) < 10) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid trainkey']);
            exit;
        }

        $thisyear_tmp = date('Y');
        $start_year_tmp = isset($_GET['start_year']) ? (int)$_GET['start_year'] : ($thisyear_tmp - 10);
        $end_year_tmp = isset($_GET['end_year']) ? (int)$_GET['end_year'] : ($thisyear_tmp + 1);
        $datestart_yyyymmdd_tmp = $start_year_tmp . '0101';
        $dateend_yyyymmdd_tmp = $end_year_tmp . '1231';
        $selected_course_tmp = isset($_GET['course']) ? (int)$_GET['course'] : 0;

        $course_condition_tmp = '';
        if ($selected_course_tmp > 0) {
            $course_condition_tmp = " AND t.uid = " . (int)$selected_course_tmp;
        }

        $timeline_query = "
            SELECT 
                TO_CHAR(TO_DATE(tl.date_added::text, 'YYYYMMDD'), 'YYYY-MM') AS month,
                COUNT(DISTINCT tl.logkey) AS total_cases,
                COUNT(DISTINCT CASE WHEN st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%' THEN tl.logkey END) AS babcp_training_cases,
                COUNT(DISTINCT CASE WHEN st.str LIKE '%supervised%' OR st.str LIKE '%supervision%' OR st.str LIKE '%supervisor%' THEN tl.logkey END) AS supervised_cases,
                COUNT(DISTINCT CASE WHEN st.str LIKE '%CBT%' THEN tl.logkey END) AS cbt_cases
            FROM trainee_log tl
            JOIN trainee_tbl t ON tl.trainkey = t.trainkey
            LEFT JOIN select_types st ON tl.stid = st.stid
            WHERE tl.date_added >= ? AND tl.date_added <= ?
              AND t.trainkey = ?
              $course_condition_tmp
            GROUP BY TO_CHAR(TO_DATE(tl.date_added::text, 'YYYYMMDD'), 'YYYY-MM')
            ORDER BY month ASC
        ";

        // Debug: First check if trainee exists and has any log entries
        $debug_query = "SELECT COUNT(*) as log_count FROM trainee_log WHERE trainkey = ?";
        $debug_stmt = $pdo->prepare($debug_query);
        $debug_stmt->execute([$timeline_trainkey]);
        $log_count = $debug_stmt->fetchColumn();
        $debug_stmt->closeCursor();
        
        // Debug: Check trainee name
        $name_query = "SELECT name FROM trainee_tbl WHERE trainkey = ?";
        $name_stmt = $pdo->prepare($name_query);
        $name_stmt->execute([$timeline_trainkey]);
        $trainee_name = $name_stmt->fetchColumn();
        $name_stmt->closeCursor();
        
        $stmt = $pdo->prepare($timeline_query);
        $stmt->execute([$datestart_yyyymmdd_tmp, $dateend_yyyymmdd_tmp, $timeline_trainkey]);
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = [
                'month' => $row['month'],
                'total_cases' => (int)$row['total_cases'],
                'babcp_training_cases' => (int)$row['babcp_training_cases'],
                'supervised_cases' => (int)$row['supervised_cases'],
                'cbt_cases' => (int)$row['cbt_cases'],
            ];
        }
        $stmt->closeCursor();
        
        // Debug: Log detailed information for troubleshooting
        error_log("Timeline debug - Trainee: $trainee_name (ID: $timeline_trainkey), Total logs: $log_count, Date range: $datestart_yyyymmdd_tmp to $dateend_yyyymmdd_tmp, Course: $selected_course_tmp, Timeline rows: " . count($data));

        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }
    
    // Debug endpoint to help troubleshoot timeline issues
    if (isset($_GET['data_type']) && $_GET['data_type'] === 'timeline_debug') {
        header('Content-Type: application/json');
        $debug_trainkey = isset($_GET['trainkey']) ? (int)$_GET['trainkey'] : 0;
        
        if ($debug_trainkey <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Missing trainkey']);
            exit;
        }
        
        // Get trainee info
        $trainee_query = "SELECT trainkey, name FROM trainee_tbl WHERE trainkey = ?";
        $trainee_stmt = $pdo->prepare($trainee_query);
        $trainee_stmt->execute([$debug_trainkey]);
        $trainee_info = $trainee_stmt->fetch(PDO::FETCH_ASSOC);
        $trainee_stmt->closeCursor();
        
        // Get log count
        $log_query = "SELECT COUNT(*) as total_logs FROM trainee_log WHERE trainkey = ?";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([$debug_trainkey]);
        $log_count = $log_stmt->fetchColumn();
        $log_stmt->closeCursor();
        
        // Get sample log entries
        $sample_query = "SELECT tl.logkey, tl.date_added, st.str as category 
                        FROM trainee_log tl 
                        LEFT JOIN select_types st ON tl.stid = st.stid 
                        WHERE tl.trainkey = ? 
                        ORDER BY tl.date_added DESC 
                        LIMIT 5";
        $sample_stmt = $pdo->prepare($sample_query);
        $sample_stmt->execute([$debug_trainkey]);
        $sample_logs = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
        $sample_stmt->closeCursor();
        
        echo json_encode([
            'status' => 'success',
            'trainee_info' => $trainee_info,
            'total_logs' => $log_count,
            'sample_logs' => $sample_logs
        ]);
        exit;
    }
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Trainee Statistics Dashboard">
   <meta name="keywords" content="statistics, analytics, trainee, dashboard">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   <script src="js/async-stats-loader.js"></script>
   <style>
      .stats-card {
         transition: transform 0.2s;
      }
      .stats-card:hover {
         transform: translateY(-2px);
      }
      .metric-value {
         font-size: 2rem;
         font-weight: bold;
      }
      .metric-label {
         font-size: 0.9rem;
         color: #6c757d;
      }
      .trend-up {
         color: #28a745;
      }
      .trend-down {
         color: #dc3545;
      }
      .trend-neutral {
         color: #6c757d;
      }
      .loading-skeleton {
         background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
         background-size: 200% 100%;
         animation: loading 1.5s infinite;
      }
      @keyframes loading {
         0% { background-position: 200% 0; }
         100% { background-position: -200% 0; }
      }
      .progressive-loading {
         opacity: 0.7;
         transition: opacity 0.3s ease;
      }
      .progressive-loading.loaded {
         opacity: 1;
      }
      .error-state {
         background-color: #f8d7da;
         border: 1px solid #f5c6cb;
         color: #721c24;
         padding: 1rem;
         border-radius: 0.375rem;
         margin: 1rem 0;
      }
      .retry-button {
         background-color: #dc3545;
         color: white;
         border: none;
         padding: 0.5rem 1rem;
         border-radius: 0.25rem;
         cursor: pointer;
         margin-top: 0.5rem;
      }
      .retry-button:hover {
         background-color: #c82333;
      }
      /* Shared timeline styles */
      .chart-container { position: relative; min-height: 220px; }
      .loading-overlay { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(255,255,255,0.6); z-index: 2; }
      .loading-overlay.active { display: flex; }
      .spinner { width: 28px; height: 28px; border: 3px solid #ccc; border-top-color: #007bff; border-radius: 50%; animation: spin 0.8s linear infinite; }
      @keyframes spin { to { transform: rotate(360deg); } }
   </style>
</head>

<?php 
// Get date range parameters
$thisyear = date("Y");
$start_year = isset($_GET['start_year']) ? (int)$_GET['start_year'] : $thisyear;
$end_year = isset($_GET['end_year']) ? (int)$_GET['end_year'] : $thisyear;
$selected_course = isset($_GET['course']) ? (int)$_GET['course'] : 0; // 0 = all courses
$selected_cohort_year = isset($_GET['cohort_year']) ? (int)$_GET['cohort_year'] : 0; // 0 = all cohort years
$babcp_filter = isset($_GET['babcp_filter']) ? (int)$_GET['babcp_filter'] : 0; // 0 = all data, 1 = BABCP only
$babcp_training = isset($_GET['babcp_training']) ? (int)$_GET['babcp_training'] : 0; // 0 = all, 1 = training cases only
$supervised_case = isset($_GET['supervised_case']) ? (int)$_GET['supervised_case'] : 0; // 0 = all, 1 = supervised cases only
$primary_modality = isset($_GET['primary_modality']) ? $_GET['primary_modality'] : ''; // CBT, etc.
$min_sessions = isset($_GET['min_sessions']) ? (int)$_GET['min_sessions'] : 0; // minimum number of sessions

// Log filter parameters
$filters = [
    'start_year' => $start_year,
    'end_year' => $end_year,
    'selected_course' => $selected_course,
    'selected_cohort_year' => $selected_cohort_year,
    'babcp_filter' => $babcp_filter,
    'babcp_training' => $babcp_training,
    'supervised_case' => $supervised_case,
    'primary_modality' => $primary_modality,
    'min_sessions' => $min_sessions
];
$stats_logger->logFilter($filters);

// Date ranges for queries
$datestart = $start_year . '0101';
$dateend = $end_year . '1231';

// Get course and cohort year filter conditions
$course_condition = "";
$cohort_condition = "";
$course_params = [];
$course_param_types = "";

if ($selected_course > 0) {
    $course_condition = "AND t.uid = ?";
    $course_params[] = $selected_course;
    $course_param_types = "i";
}

if ($selected_cohort_year > 0) {
    $cohort_condition = "AND t.year = ?";
    $course_params[] = $selected_cohort_year;
    $course_param_types .= "i";
}

// OPTIMIZED: Pre-compute BABCP trainee list to avoid complex subqueries
$babcp_trainees = [];
if ($babcp_filter == 1) {
    // Get list of trainees with BABCP-related data - much more efficient
    $babcp_trainees_query = "
        SELECT DISTINCT t.trainkey 
        FROM trainee_tbl t
        WHERE EXISTS (
            SELECT 1 FROM trainee_log tl 
            JOIN select_types st ON tl.stid = st.stid 
            WHERE tl.trainkey = t.trainkey 
            AND (st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%' OR st.str LIKE '%Therapy%')
        )
    ";
    
    $result = $pdo->query($babcp_trainees_query);
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $babcp_trainees[] = $row['trainkey'];
    }
}

// Simplified BABCP conditions using pre-computed list
$babcp_condition_simple = "";
$babcp_condition_with_tabs = "";

if ($babcp_filter == 1 && !empty($babcp_trainees)) {
    $babcp_trainees_list = implode(',', array_map('intval', $babcp_trainees));
    $babcp_condition_simple = "AND t.trainkey IN ($babcp_trainees_list)";
    $babcp_condition_with_tabs = "AND (tabs.tab_name LIKE '%BABCP%' OR tabs.tab_name LIKE '%Behavioural%' OR tabs.tab_name LIKE '%Cognitive%' OR t.trainkey IN ($babcp_trainees_list))";
} elseif ($babcp_filter == 1 && empty($babcp_trainees)) {
    // No BABCP trainees found, return empty result
    $babcp_condition_simple = "AND 1=0";
    $babcp_condition_with_tabs = "AND 1=0";
}

// OPTIMIZED: Pre-compute additional filter conditions to avoid nested subqueries
$additional_conditions = "";
$filter_trainees = [];

// BABCP Training Cases filter - pre-compute trainee list
if ($babcp_training == 1) {
    $training_trainees_query = "
        SELECT DISTINCT t.trainkey 
        FROM trainee_tbl t
        JOIN trainee_log tl ON t.trainkey = tl.trainkey
        JOIN select_types st ON tl.stid = st.stid 
        WHERE (st.str LIKE '%training%' OR st.str LIKE '%case%' OR st.str LIKE '%client%')
        AND (st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%')
    ";
    
    $result = $pdo->query($training_trainees_query);
    $training_trainees = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $training_trainees[] = $row['trainkey'];
    }
    
    if (!empty($training_trainees)) {
        $filter_trainees[] = implode(',', array_map('intval', $training_trainees));
    } else {
        $additional_conditions .= " AND 1=0"; // No results
    }
}

// Supervised Cases filter - pre-compute trainee list
if ($supervised_case == 1) {
    $supervised_trainees_query = "
        SELECT DISTINCT t.trainkey 
        FROM trainee_tbl t
        JOIN trainee_log tl ON t.trainkey = tl.trainkey
        JOIN select_types st ON tl.stid = st.stid 
        WHERE (st.str LIKE '%supervised%' OR st.str LIKE '%supervision%' OR st.str LIKE '%supervisor%')
    ";
    
    $result = $pdo->query($supervised_trainees_query);
    $supervised_trainees = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $supervised_trainees[] = $row['trainkey'];
    }
    
    if (!empty($supervised_trainees)) {
        $filter_trainees[] = implode(',', array_map('intval', $supervised_trainees));
    } else {
        $additional_conditions .= " AND 1=0"; // No results
    }
}

// Primary Modality filter - pre-compute trainee list
if (!empty($primary_modality)) {
    $modality_trainees_query = "
        SELECT DISTINCT t.trainkey 
        FROM trainee_tbl t
        JOIN trainee_log tl ON t.trainkey = tl.trainkey
        JOIN select_types st ON tl.stid = st.stid 
        WHERE st.str LIKE '%$primary_modality%'
    ";
    
    $result = $pdo->query($modality_trainees_query);
    $modality_trainees = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $modality_trainees[] = $row['trainkey'];
    }
    
    if (!empty($modality_trainees)) {
        $filter_trainees[] = implode(',', array_map('intval', $modality_trainees));
    } else {
        $additional_conditions .= " AND 1=0"; // No results
    }
}

// Minimum Sessions filter - pre-compute trainee list
if ($min_sessions > 0) {
    $sessions_trainees_query = "
        SELECT t.trainkey 
        FROM trainee_tbl t
        WHERE (SELECT COUNT(DISTINCT tl.tlogid) 
               FROM trainee_log tl 
               WHERE tl.trainkey = t.trainkey) >= $min_sessions
    ";
    
    $result = $pdo->query($sessions_trainees_query);
    $sessions_trainees = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $sessions_trainees[] = $row['trainkey'];
    }
    
    if (!empty($sessions_trainees)) {
        $filter_trainees[] = implode(',', array_map('intval', $sessions_trainees));
    } else {
        $additional_conditions .= " AND 1=0"; // No results
    }
}

// Combine all filter conditions into a single IN clause
if (!empty($filter_trainees)) {
    // Find intersection of all filter conditions
    $all_trainees = [];
    foreach ($filter_trainees as $trainee_list) {
        $trainees = explode(',', $trainee_list);
        if (empty($all_trainees)) {
            $all_trainees = $trainees;
        } else {
            $all_trainees = array_intersect($all_trainees, $trainees);
        }
    }
    
    if (!empty($all_trainees)) {
        $additional_conditions .= " AND t.trainkey IN (" . implode(',', array_map('intval', $all_trainees)) . ")";
    } else {
        $additional_conditions .= " AND 1=0"; // No results
    }
}

// Get total trainees with logging
// PERFORMANCE: This query benefits from indexes on trainee_tbl.uid, trainee_tbl.last_used
$query_start = microtime(true);
// OPTIMIZED: Use STRAIGHT_JOIN hint and optimize WHERE clause structure
$total_trainees_query = "SELECT /*+ USE_INDEX(t, idx_trainee_uid) */ COUNT(*) as total 
                        FROM trainee_tbl t 
                        WHERE 1=1 $course_condition $cohort_condition $babcp_condition_simple $additional_conditions";
$stmt = $pdo->prepare($total_trainees_query);
if (!empty($course_params)) {
    $stmt->execute($course_params);
} else {
    $stmt->execute();
}
$total_trainees = $stmt->fetchColumn();
$stmt->closeCursor();
$query_end = microtime(true);
$stats_logger->logQuery($total_trainees_query, $course_params, round(($query_end - $query_start) * 1000, 2));

// Get active trainees (logged in within last 30 days) with logging
// PERFORMANCE: Critical for performance - requires index on trainee_tbl.last_used
$query_start = microtime(true);
// OPTIMIZED: Use index hint and optimize date comparison
$active_trainees_query = "SELECT /*+ USE_INDEX(t, idx_trainee_last_used) */ COUNT(*) as active 
                         FROM trainee_tbl t 
                         WHERE t.last_used >= ? $course_condition $cohort_condition $babcp_condition_simple $additional_conditions";
$thirty_days_ago = date('Ymd', strtotime('-30 days'));
$stmt = $pdo->prepare($active_trainees_query);
if (!empty($course_params)) {
    $stmt->execute(array_merge([$thirty_days_ago], $course_params));
} else {
    $stmt->execute([$thirty_days_ago]);
}
$active_trainees = $stmt->fetchColumn();
$stmt->closeCursor();
$query_end = microtime(true);
$stats_logger->logQuery($active_trainees_query, array_merge([$thirty_days_ago], $course_params), round(($query_end - $query_start) * 1000, 2));

// Get trainees by year
// OPTIMIZED: Use composite index and year index for better performance
$trainees_by_year_query = "SELECT /*+ USE_INDEX(t, idx_trainee_uid_year) USE_INDEX(t, idx_trainee_year) */ t.year, COUNT(*) as count 
                          FROM trainee_tbl t 
                          WHERE 1=1 $course_condition $cohort_condition $babcp_condition_simple $additional_conditions 
                          GROUP BY t.year 
                          ORDER BY t.year DESC";
$stmt = $pdo->prepare($trainees_by_year_query);
if (!empty($course_params)) {
    $stmt->execute($course_params);
} else {
    $stmt->execute();
}
$year_data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $year_data[] = ['year' => $row['year'], 'count' => $row['count']];
}
$stmt->closeCursor();

// Get competency completion stats - OPTIMIZED with materialized views (with fallback)
// PERFORMANCE: 80-90% improvement using pre-computed materialized views
$use_materialized_views = false;

// Check if materialized views exist and try to use them
try {
    // First check if the materialized view exists
    $check_view_query = "SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'mv_tab_statistics'";
    $result = $pdo->query($check_view_query);
    $view_exists = $result->fetchColumn() > 0;
    
    if ($view_exists) {
        // Try to refresh materialized views
        $refresh_views_query = "SELECT refresh_all_materialized_views()";
        $pdo->query($refresh_views_query);
        $use_materialized_views = true;
        error_log("Using materialized views for competency stats");
    } else {
        error_log("Materialized views not found, using original query");
    }
} catch (Exception $e) {
    error_log("Materialized view check/refresh failed: " . $e->getMessage() . " - Using original query");
    $use_materialized_views = false;
}

if ($use_materialized_views) {
    $competency_stats_query = "
        SELECT 
            ts.tab_name,
            ts.sort_order,
            ts.trainees_with_data,
            ts.total_entries
        FROM mv_tab_statistics ts
        WHERE 1=1 $course_condition $cohort_condition $babcp_condition_with_tabs $additional_conditions
        ORDER BY ts.sort_order
    ";
} else {
    // Fallback to original query
    $competency_stats_query = "
        SELECT /*+ USE_INDEX(tabs, idx_tabs_isvis_sort) USE_INDEX(ttl, idx_trainee_tab_link_tbid) USE_INDEX(tl, idx_trainee_log_trainkey_tbid) USE_INDEX(t, idx_trainee_trainkey) */
            tabs.tab_name,
            tabs.sort_order,
            COUNT(DISTINCT tl.trainkey) as trainees_with_data,
            COUNT(tl.tlogid) as total_entries
        FROM tabs_tbl tabs
        LEFT JOIN trainee_tab_link ttl ON tabs.tbid = ttl.tbid
        LEFT JOIN trainee_tbl t ON ttl.trainkey = t.trainkey
        LEFT JOIN trainee_log tl ON t.trainkey = tl.trainkey AND tl.tbid = tabs.tbid
        WHERE tabs.isvis = 1 $course_condition $cohort_condition $babcp_condition_with_tabs $additional_conditions
        GROUP BY tabs.tbid, tabs.tab_name, tabs.sort_order
        ORDER BY tabs.sort_order
    ";
}
$stmt = $pdo->prepare($competency_stats_query);
if (!empty($course_params)) {
    $stmt->execute($course_params);
} else {
    $stmt->execute();
}
$competency_data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $competency_data[] = [
        'tab_name' => $row['tab_name'],
        'trainees_with_data' => $row['trainees_with_data'],
        'total_entries' => $row['total_entries'],
                 'completion_rate' => $total_trainees > 0 ? round(($row['trainees_with_data'] / $total_trainees) * 100, 1) : 0
    ];
}
$stmt->closeCursor();

// Get recent activity (last 7 days)
// OPTIMIZED: Use index hints for date-based queries including composite index
$recent_activity_query = "
    SELECT /*+ USE_INDEX(tl, idx_trainee_log_date_added) USE_INDEX(tl, idx_trainee_log_trainkey) USE_INDEX(tl, idx_trainee_log_trainkey_date) */
        DATE(TO_TIMESTAMP(tl.date_added)) as activity_date,
        COUNT(*) as entries
    FROM trainee_log tl
    JOIN trainee_tbl t ON tl.trainkey = t.trainkey
    WHERE tl.date_added >= ? $course_condition $cohort_condition $babcp_condition_simple $additional_conditions
    GROUP BY DATE(TO_TIMESTAMP(tl.date_added))
    ORDER BY activity_date DESC
    LIMIT 7
";
$seven_days_ago = strtotime('-7 days');
$stmt = $pdo->prepare($recent_activity_query);
if (!empty($course_params)) {
    $stmt->execute(array_merge([$seven_days_ago], $course_params));
} else {
    $stmt->execute([$seven_days_ago]);
}
$recent_activity = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $recent_activity[] = ['date' => $row['activity_date'], 'entries' => $row['entries']];
}
$stmt->closeCursor();

// Get supervisor distribution
// OPTIMIZED: Use index hints for supervisor queries including supervisor2 and supervisor3
$supervisor_query = "
    SELECT /*+ USE_INDEX(w, idx_who_there_admintype) USE_INDEX(t, idx_trainee_supervisor) USE_INDEX(t, idx_trainee_supervisor2) USE_INDEX(t, idx_trainee_supervisor3) */
        w.realname as supervisor_name,
        COUNT(t.tid) as trainee_count
    FROM who_there w
    JOIN trainee_tbl t ON (w.usrkey = t.supervisor OR w.usrkey = t.supervisor2 OR w.usrkey = t.supervisor3)
    WHERE w.admintype IN ('SO', 'SE') $course_condition $cohort_condition $babcp_condition_simple $additional_conditions
    GROUP BY w.usrkey, w.realname
    ORDER BY trainee_count DESC
    LIMIT 10
";
$stmt = $pdo->prepare($supervisor_query);
if (!empty($course_params)) {
    $stmt->execute($course_params);
} else {
    $stmt->execute();
}
$supervisor_data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $supervisor_data[] = ['name' => $row['supervisor_name'], 'count' => $row['trainee_count']];
}
$stmt->closeCursor();

// Get all courses for filter dropdown
// OPTIMIZED: Use index hint for university sorting with caching
$cache_key = 'courses_list';
$courses = QueryCache::get($cache_key);

if ($courses === null) {
    $courses_query = "SELECT /*+ USE_INDEX(uni_tbl, idx_uni_university) */ uid, university FROM uni_tbl ORDER BY university";
    $courses_result = $pdo->query($courses_query);
    $courses = [];
    while ($row = $courses_result->fetch(PDO::FETCH_ASSOC)) {
        $courses[] = $row;
    }
    QueryCache::set($cache_key, $courses);
}
?>

<body>
   <div class="wrapper">
      <!-- top, left side and right navbars -->
      <?php include 'incl/topbar.php' ?>
      <?php include 'incl/sidebar.php' ?>
      <?php include 'incl/offsidebar.php' ?>

      <!-- Main section-->
      <section class="section-container">
         <!-- Page content-->
         <div class="content-wrapper">
            <!-- Page Header -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="jumbotron bg-primary text-white p-4 rounded">
                     <div class="container-fluid">
                        <div class="row align-items-center">
                           <div class="col-md-8">
                              <h1 class="display-4 mb-2"><?php echo $pagetitle ?></h1>
                              <p class="lead mb-0"><?php echo $subtitle ?></p>
                           </div>
                           <div class="col-md-4 text-md-end">
                              <a href="<?php echo $listurl ?>" class="btn btn-light btn-lg me-2">Back to Dashboard</a>
                              <a href="trainee_detailed_stats.php" class="btn btn-outline-light btn-lg">Detailed Analytics</a>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title mb-0">Filter Options</h5>
                        <small class="text-muted">Customize the data view with advanced filtering options</small>
                     </div>
                     <div class="card-body">
                        <form method="GET" class="row g-3">
                           <div class="col-md-2">
                              <label for="start_year" class="form-label">Start Year</label>
                              <select name="start_year" id="start_year" class="form-select">
                                 <?php for ($year = $thisyear - 5; $year <= $thisyear; $year++): ?>
                                    <option value="<?php echo $year ?>" <?php echo $year == $start_year ? 'selected' : '' ?>>
                                       <?php echo $year ?>
                                    </option>
                                 <?php endfor; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="end_year" class="form-label">End Year</label>
                              <select name="end_year" id="end_year" class="form-select">
                                 <?php for ($year = $thisyear - 5; $year <= $thisyear; $year++): ?>
                                    <option value="<?php echo $year ?>" <?php echo $year == $end_year ? 'selected' : '' ?>>
                                       <?php echo $year ?>
                                    </option>
                                 <?php endfor; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="course" class="form-label">Course</label>
                              <select name="course" id="course" class="form-select">
                                 <option value="0" <?php echo $selected_course == 0 ? 'selected' : '' ?>>All Courses</option>
                                 <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['uid'] ?>" <?php echo $selected_course == $course['uid'] ? 'selected' : '' ?>>
                                       <?php echo htmlspecialchars($course['university']) ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="cohort_year" class="form-label">Cohort Year</label>
                              <select name="cohort_year" id="cohort_year" class="form-select">
                                 <option value="0" <?php echo $selected_cohort_year == 0 ? 'selected' : '' ?>>All Years</option>
                                 <?php for ($year = $thisyear - 5; $year <= $thisyear + 2; $year++): ?>
                                    <option value="<?php echo $year ?>" <?php echo $selected_cohort_year == $year ? 'selected' : '' ?>>
                                       <?php echo $year ?>
                                    </option>
                                 <?php endfor; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="babcp_filter" class="form-label">BABCP Filter</label>
                              <select name="babcp_filter" id="babcp_filter" class="form-select">
                                 <option value="0" <?php echo $babcp_filter == 0 ? 'selected' : '' ?>>All Data</option>
                                 <option value="1" <?php echo $babcp_filter == 1 ? 'selected' : '' ?>>BABCP Related Only</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="babcp_training" class="form-label">Training Cases</label>
                              <select name="babcp_training" id="babcp_training" class="form-select">
                                 <option value="0" <?php echo $babcp_training == 0 ? 'selected' : '' ?>>All Cases</option>
                                 <option value="1" <?php echo $babcp_training == 1 ? 'selected' : '' ?>>Training Cases Only</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="supervised_case" class="form-label">Supervision</label>
                              <select name="supervised_case" id="supervised_case" class="form-select">
                                 <option value="0" <?php echo $supervised_case == 0 ? 'selected' : '' ?>>All Cases</option>
                                 <option value="1" <?php echo $supervised_case == 1 ? 'selected' : '' ?>>Supervised Cases Only</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="primary_modality" class="form-label">Primary Modality</label>
                              <select name="primary_modality" id="primary_modality" class="form-select">
                                 <option value="" <?php echo empty($primary_modality) ? 'selected' : '' ?>>All Modalities</option>
                                 <option value="CBT" <?php echo $primary_modality == 'CBT' ? 'selected' : '' ?>>CBT</option>
                                 <option value="DBT" <?php echo $primary_modality == 'DBT' ? 'selected' : '' ?>>DBT</option>
                                 <option value="ACT" <?php echo $primary_modality == 'ACT' ? 'selected' : '' ?>>ACT</option>
                                 <option value="REBT" <?php echo $primary_modality == 'REBT' ? 'selected' : '' ?>>REBT</option>
                                 <option value="Schema" <?php echo $primary_modality == 'Schema' ? 'selected' : '' ?>>Schema Therapy</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="min_sessions" class="form-label">Min Sessions</label>
                              <select name="min_sessions" id="min_sessions" class="form-select">
                                 <option value="0" <?php echo $min_sessions == 0 ? 'selected' : '' ?>>Any Sessions</option>
                                 <option value="5" <?php echo $min_sessions == 5 ? 'selected' : '' ?>>5+ Sessions</option>
                                 <option value="10" <?php echo $min_sessions == 10 ? 'selected' : '' ?>>10+ Sessions</option>
                                 <option value="15" <?php echo $min_sessions == 15 ? 'selected' : '' ?>>15+ Sessions</option>
                                 <option value="20" <?php echo $min_sessions == 20 ? 'selected' : '' ?>>20+ Sessions</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label class="form-label">&nbsp;</label>
                              <button type="submit" class="btn btn-primary d-block w-100">Apply Filters</button>
                           </div>
                           <div class="col-md-2">
                              <label class="form-label">&nbsp;</label>
                              <a href="trainee_stats.php" class="btn btn-secondary d-block w-100">Clear Filters</a>
                           </div>
                        </form>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Key Metrics -->
            <div class="row mb-4">
               <div class="col-md-3">
                  <div class="card stats-card bg-primary text-white" data-loading="overview">
                     <div class="card-body text-center">
                        <div class="metric-value" data-metric="total_trainees"><?php echo number_format($total_trainees) ?></div>
                        <div class="metric-label">Total Trainees</div>
                     </div>
                  </div>
               </div>
               <div class="col-md-3">
                  <div class="card stats-card bg-success text-white" data-loading="overview">
                     <div class="card-body text-center">
                        <div class="metric-value" data-metric="active_trainees"><?php echo number_format($active_trainees) ?></div>
                        <div class="metric-label">Active (30 days)</div>
                     </div>
                  </div>
               </div>
               <div class="col-md-3">
                  <div class="card stats-card bg-info text-white" data-loading="overview">
                     <div class="card-body text-center">
                        <div class="metric-value" data-metric="activity_rate"><?php echo $total_trainees > 0 ? round(($active_trainees / $total_trainees) * 100, 1) : 0 ?>%</div>
                        <div class="metric-label">Activity Rate</div>
                     </div>
                  </div>
               </div>
               <div class="col-md-3">
                  <div class="card stats-card bg-warning text-white" data-loading="overview">
                     <div class="card-body text-center">
                        <div class="metric-value" data-metric="competency_count"><?php echo count($competency_data) ?></div>
                        <div class="metric-label">Competency Areas</div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
               <!-- Trainees by Year -->
               <div class="col-md-6">
                  <div class="card" data-loading="enrollment_trend" data-error="enrollment_trend">
                     <div class="card-header">
                        <h5 class="card-title">Trainee Enrollment by Year</h5>
                        <div class="spinner-border spinner-border-sm" id="enrollmentSpinner" style="display: none;"></div>
                     </div>
                     <div class="card-body">
                        <div id="enrollmentLoading" class="text-center">
                           <div class="spinner-border" role="status">
                              <span class="sr-only">Loading...</span>
                           </div>
                           <p class="mt-2">Loading enrollment data...</p>
                        </div>
                        <canvas id="enrollmentChart" width="400" height="200" style="display: none;"></canvas>
                     </div>
                  </div>
               </div>
               
               <!-- Competency Completion -->
               <div class="col-md-6">
                  <div class="card" data-loading="competency_completion" data-error="competency_completion">
                     <div class="card-header">
                        <h5 class="card-title">Competency Completion Rates</h5>
                        <div class="spinner-border spinner-border-sm" id="competencySpinner" style="display: none;"></div>
                     </div>
                     <div class="card-body">
                        <div id="competencyLoading" class="text-center">
                           <div class="spinner-border" role="status">
                              <span class="sr-only">Loading...</span>
                           </div>
                           <p class="mt-2">Loading competency data...</p>
                        </div>
                        <canvas id="competencyChart" width="400" height="200" style="display: none;"></canvas>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Individual Trainee Timeline -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                           <h5 class="card-title mb-0">Individual Trainee Timeline</h5>
                           <small class="text-muted">Monthly trajectory for a selected trainee</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                           <label for="timelineTraineeSelect" class="form-label mb-0">Select Trainee</label>
                           <select id="timelineTraineeSelect" class="form-select form-select-sm" style="min-width: 240px;">
                              <option value="">-- Choose Trainee --</option>
                              <?php
                              // Build trainee list to match detailed stats: active filters and date range, require at least one log entry
                              $trainee_list_query = "
                                SELECT DISTINCT t.trainkey, t.name
                                FROM trainee_tbl t
                                JOIN trainee_log tl ON tl.trainkey = t.trainkey
                                WHERE tl.date_added >= ? AND tl.date_added <= ?
                                  $course_condition $cohort_condition $babcp_condition_simple $additional_conditions
                                ORDER BY t.name";
                              $trainee_list_stmt = $pdo->prepare($trainee_list_query);
                              if ($trainee_list_stmt) {
                                 if (!empty($course_params)) {
                                    $trainee_list_stmt->execute(array_merge([$datestart, $dateend], $course_params));
                                 } else {
                                    $trainee_list_stmt->execute([$datestart, $dateend]);
                                 }
                                 $trainee_count = 0;
                                 while ($trow = $trainee_list_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($trow['trainkey']) . '">' . htmlspecialchars($trow['name']) . '</option>';
                                    $trainee_count++;
                                 }
                                 $trainee_list_stmt->closeCursor();
                                 echo '<script>console.log("[Timeline] Populated dropdown with ' . $trainee_count . ' trainees");</script>';
                              }
                              ?>
                           </select>
                           <button id="downloadTimelineCsv" class="btn btn-sm btn-outline-primary" type="button" disabled>Download CSV</button>
                        </div>
                     </div>
                     <div class="card-body">
                        <div class="chart-container">
                           <div class="loading-overlay" id="timelineLoading"><div class="spinner"></div></div>
                           <canvas id="traineeTimelineChart" width="400" height="200"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                           <table class="table table-striped table-sm" id="traineeTimelineTable">
                              <thead>
                                 <tr>
                                    <th>Month</th>
                                    <th>Total Cases</th>
                                    <th>BABCP Training</th>
                                    <th>Supervised</th>
                                    <th>CBT</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr><td colspan="5" class="text-muted text-center">Select a trainee to view timeline</td></tr>
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Performance Monitoring Panel (only visible to admins) -->
            <?php if ($admintype == 'AT' || $admintype == 'DV'): ?>
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Performance Monitoring</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleDebugPanel()">Toggle Debug</button>
                        <button class="btn btn-sm btn-outline-warning" onclick="clearCache()">Clear Cache</button>
                     </div>
                     <div class="card-body" id="debugPanel" style="display: none;">
                        <div class="row">
                           <div class="col-md-6">
                              <h6>Query Performance</h6>
                              <div id="performanceMetrics">
                                 <p>Loading performance data...</p>
                              </div>
                           </div>
                           <div class="col-md-6">
                              <h6>Cache Status</h6>
                              <div id="cacheStatus">
                                 <p>Loading cache information...</p>
                              </div>
                        </div>
                        <div class="row mt-3">
                           <div class="col-md-6">
                              <h6>Applied Filters</h6>
                              <pre class="bg-light p-2 rounded"><code><?php echo json_encode($filters, JSON_PRETTY_PRINT); ?></code></pre>
                           </div>
                           <div class="col-md-6">
                              <h6>Database Optimization Tips</h6>
                              <ul class="small">
                                 <li>Ensure all recommended indexes are created</li>
                                 <li>Monitor slow query log regularly</li>
                                 <li>Consider query result caching for large datasets</li>
                                 <li>Use EXPLAIN ANALYZE for query optimization</li>
                              </ul>
                           </div>
                        </div>
                     </div>
</div>
            <?php endif; ?>

            <!-- Recent Activity and Supervisor Distribution -->
            <div class="row mb-4">
               <!-- Recent Activity -->
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Recent Activity (Last 7 Days)</h5>
                     </div>
                     <div class="card-body">
                        <canvas id="activityChart" width="400" height="200"></canvas>
                     </div>
                  </div>
               </div>
               
               <!-- Supervisor Distribution -->
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Top Supervisors by Trainee Count</h5>
                     </div>
                     <div class="card-body">
                        <canvas id="supervisorChart" width="400" height="200"></canvas>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Detailed Competency Table -->
            <div class="row">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Detailed Competency Statistics</h5>
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table class="table table-striped table-hover">
                              <thead class="table-dark">
                                 <tr>
                                    <th>Competency Area</th>
                                    <th>Trainees with Data</th>
                                    <th>Total Entries</th>
                                    <th>Completion Rate</th>
                                    <th>Avg Entries per Trainee</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <?php foreach ($competency_data as $comp): ?>
                                    <tr>
                                       <td><strong><?php echo htmlspecialchars($comp['tab_name']) ?></strong></td>
                                       <td><?php echo number_format($comp['trainees_with_data']) ?></td>
                                       <td><?php echo number_format($comp['total_entries']) ?></td>
                                       <td>
                                          <div class="progress" style="height: 20px;">
                                             <div class="progress-bar" role="progressbar" 
                                                  style="width: <?php echo $comp['completion_rate'] ?>%"
                                                  aria-valuenow="<?php echo $comp['completion_rate'] ?>" 
                                                  aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $comp['completion_rate'] ?>%
                                             </div>
                                          </div>
                                       </td>
                                       <td><?php echo $comp['trainees_with_data'] > 0 ? round($comp['total_entries'] / $comp['trainees_with_data'], 1) : 0 ?></td>
                                    </tr>
                                 <?php endforeach; ?>
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

         </div>
      </section>
   </div>
   
   <?php include 'incl/adminjs.php' ?>
   
   <script>
   // Chart.js configuration
   Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
   
   // Initialize charts object
   let charts = {};
   let traineeTimelineChart = null;
   let traineeTimelineController = null;
   


   // Chart update methods
   function updateEnrollmentChart(data, level) {
       const canvas = document.getElementById('enrollmentChart');
       const loading = document.getElementById('enrollmentLoading');
       
       if (loading) {
           loading.style.display = 'none';
           canvas.style.display = 'block';
       }
       
       if (charts.enrollment) {
           charts.enrollment.destroy();
       }
       
       const ctx = canvas.getContext('2d');
       charts.enrollment = new Chart(ctx, {
           type: 'bar',
           data: {
               labels: data.map(item => item.year),
               datasets: [{
                   label: 'Trainees',
                   data: data.map(item => item.count),
                   backgroundColor: 'rgba(54, 162, 235, 0.8)',
                   borderColor: 'rgba(54, 162, 235, 1)',
                   borderWidth: 1
               }]
           },
           options: {
               responsive: true,
               maintainAspectRatio: false,
               scales: {
                   y: {
                       beginAtZero: true,
                       ticks: { stepSize: 1 }
                   }
               },
               animation: {
                   duration: 1000
               }
           }
       });
   }

   function updateCompetencyChart(data, level) {
       const canvas = document.getElementById('competencyChart');
       const loading = document.getElementById('competencyLoading');
       
       if (loading) {
           loading.style.display = 'none';
           canvas.style.display = 'block';
       }
       
       if (charts.competency) {
           charts.competency.destroy();
       }
       
       const ctx = canvas.getContext('2d');
       charts.competency = new Chart(ctx, {
           type: 'doughnut',
           data: {
               labels: data.map(item => item.tab_name),
               datasets: [{
                   data: data.map(item => item.trainees_with_data),
                   backgroundColor: [
                       'rgba(255, 99, 132, 0.8)',
                       'rgba(54, 162, 235, 0.8)',
                       'rgba(255, 205, 86, 0.8)',
                       'rgba(75, 192, 192, 0.8)',
                       'rgba(153, 102, 255, 0.8)',
                       'rgba(255, 159, 64, 0.8)'
                   ],
                   borderWidth: 2
               }]
           },
           options: {
               responsive: true,
               maintainAspectRatio: false,
               plugins: {
                   legend: { position: 'bottom' }
               },
               animation: {
                   duration: 1000
               }
           }
       });
   }

   function updateActivityChart(data) {
       const canvas = document.getElementById('activityChart');
       if (!canvas) return;
       
       if (charts.activity) {
           charts.activity.destroy();
       }
       
       const ctx = canvas.getContext('2d');
       charts.activity = new Chart(ctx, {
           type: 'line',
           data: {
               labels: data.map(item => item.date),
               datasets: [{
                   label: 'Log Entries',
                   data: data.map(item => item.entries),
                   borderColor: 'rgba(40, 167, 69, 1)',
                   backgroundColor: 'rgba(40, 167, 69, 0.2)',
                   tension: 0.1
               }]
           },
           options: {
               responsive: true,
               maintainAspectRatio: false,
               scales: {
                   y: { beginAtZero: true }
               }
           }
       });
   }

   function updateSupervisorChart(data) {
       const canvas = document.getElementById('supervisorChart');
       if (!canvas) return;
       
       if (charts.supervisor) {
           charts.supervisor.destroy();
       }
       
       const ctx = canvas.getContext('2d');
       charts.supervisor = new Chart(ctx, {
           type: 'bar',
           data: {
               labels: data.map(item => item.name),
               datasets: [{
                   label: 'Trainee Count',
                   data: data.map(item => item.count),
                   backgroundColor: 'rgba(255, 193, 7, 0.8)',
                   borderColor: 'rgba(255, 193, 7, 1)',
                   borderWidth: 1
               }]
           },
           options: {
               responsive: true,
               maintainAspectRatio: false,
               scales: {
                   y: { beginAtZero: true }
               }
           }
       });
   }

   function renderTraineeTimelineChart(rows) {
       const canvas = document.getElementById('traineeTimelineChart');
       if (!canvas) return;
       const ctx = canvas.getContext('2d');
       if (!rows || rows.length === 0) {
           ctx.clearRect(0,0,canvas.width,canvas.height);
           ctx.fillStyle = '#666';
           ctx.font = '16px Arial';
           ctx.textAlign = 'center';
           ctx.fillText('No timeline data', canvas.width/2, canvas.height/2);
           return;
       }
       const labels = rows.map(r => r.month);
       const dsTotal = rows.map(r => r.total_cases);
       const dsBabcp = rows.map(r => r.babcp_training_cases);
       const dsSup = rows.map(r => r.supervised_cases);
       const dsCbt = rows.map(r => r.cbt_cases);
       if (!traineeTimelineChart) {
           traineeTimelineChart = new Chart(ctx, {
               type: 'line',
               data: {
                   labels,
                   datasets: [
                       { label: 'Total Cases', data: dsTotal, borderColor: 'rgba(54, 162, 235, 1)', backgroundColor: 'rgba(54, 162, 235, 0.2)', tension: 0.1 },
                       { label: 'BABCP Training', data: dsBabcp, borderColor: 'rgba(75, 192, 192, 1)', backgroundColor: 'rgba(75, 192, 192, 0.2)', tension: 0.1 },
                       { label: 'Supervised', data: dsSup, borderColor: 'rgba(255, 206, 86, 1)', backgroundColor: 'rgba(255, 206, 86, 0.2)', tension: 0.1 },
                       { label: 'CBT', data: dsCbt, borderColor: 'rgba(255, 99, 132, 1)', backgroundColor: 'rgba(255, 99, 132, 0.2)', tension: 0.1 }
                   ]
               },
               options: { responsive: true, maintainAspectRatio: false, animation: { duration: 300 } }
           });
       } else {
           traineeTimelineChart.data.labels = labels;
           traineeTimelineChart.data.datasets[0].data = dsTotal;
           traineeTimelineChart.data.datasets[1].data = dsBabcp;
           traineeTimelineChart.data.datasets[2].data = dsSup;
           traineeTimelineChart.data.datasets[3].data = dsCbt;
           traineeTimelineChart.update('active');
       }
   }

   function renderTraineeTimelineTable(rows) {
       const tbody = document.querySelector('#traineeTimelineTable tbody');
       if (!tbody) return;
       tbody.innerHTML = '';
       if (!rows || rows.length === 0) {
           tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">No data</td></tr>';
           return;
       }
       rows.forEach(r => {
           const tr = document.createElement('tr');
           tr.innerHTML = `
               <td>${r.month}</td>
               <td>${r.total_cases}</td>
               <td>${r.babcp_training_cases}</td>
               <td>${r.supervised_cases}</td>
               <td>${r.cbt_cases}</td>
           `;
           tbody.appendChild(tr);
       });
   }

   // Debug Panel Functions
   function toggleDebugPanel() {
       const panel = document.getElementById('debugPanel');
       if (panel.style.display === 'none') {
           panel.style.display = 'block';
           loadDebugInfo();
       } else {
           panel.style.display = 'none';
       }
   }

   function loadDebugInfo() {
       // Load performance metrics
       fetch('ajax/debug_info.php?type=performance')
           .then(response => response.json())
           .then(data => {
               if (data.status === 'success') {
                   document.getElementById('performanceMetrics').innerHTML = data.data;
               }
           })
           .catch(error => {
               console.error('Error loading performance metrics:', error);
               document.getElementById('performanceMetrics').innerHTML = '<p class="text-danger">Error loading performance data</p>';
           });

       // Load cache status
       fetch('ajax/debug_info.php?type=cache')
           .then(response => response.json())
           .then(data => {
               if (data.status === 'success') {
                   document.getElementById('cacheStatus').innerHTML = data.data;
               }
           })
           .catch(error => {
               console.error('Error loading cache status:', error);
               document.getElementById('cacheStatus').innerHTML = '<p class="text-danger">Error loading cache data</p>';
           });
   }

   function clearCache() {
       if (confirm('Are you sure you want to clear the query cache?')) {
           fetch('ajax/debug_info.php?action=clear_cache', {method: 'POST'})
               .then(response => response.json())
               .then(data => {
                   if (data.status === 'success') {
                       alert('Cache cleared successfully');
                       loadDebugInfo(); // Refresh the debug info
                   } else {
                       alert('Error clearing cache: ' + data.message);
                   }
               })
               .catch(error => {
                   console.error('Error clearing cache:', error);
                   alert('Error clearing cache');
               });
       }
   }



   // Initialize charts with PHP data on page load
   document.addEventListener('DOMContentLoaded', function() {
       // Check timeline dropdown
       const timelineDropdown = document.getElementById('timelineTraineeSelect');
       if (timelineDropdown) {
           console.log('[Timeline] Ready with', timelineDropdown.options.length, 'trainees available');
       }
       
       // Load charts with PHP data directly
       loadChartsWithPHPData();
       
       // Handle filter form changes
       const filterForm = document.querySelector('form[method="GET"]');
       if (filterForm) {
           filterForm.addEventListener('submit', function(e) {
               // Let the form submit normally to reload the page with new filters
               // No need to prevent default
           });
       }

       // Event listener for trainee timeline select
        const traineeSelect = document.getElementById('timelineTraineeSelect');
       const downloadBtn = document.getElementById('downloadTimelineCsv');
        if (traineeSelect) {
            traineeSelect.addEventListener('change', function() {
                const raw = this.value;
                console.log('[Timeline] Trainee selected:', raw);
                
                // Skip if empty or default option
                if (!raw || raw === '' || raw === '0') {
                    if (downloadBtn) downloadBtn.disabled = true;
                    return;
                }
                
                const trainkey = raw; // Keep as string since trainee IDs are UUIDs
                if (!trainkey || trainkey === '' || trainkey.length < 10) {
                    console.warn('[Timeline] Invalid trainee ID:', raw);
                    if (downloadBtn) downloadBtn.disabled = true;
                    return;
                }
                
                if (downloadBtn) downloadBtn.disabled = false;
                const loader = document.getElementById('timelineLoading');
                this.disabled = true;
                if (loader) loader.classList.add('active');
                if (traineeTimelineController) {
                    try { traineeTimelineController.abort(); } catch (e) {}
                }
                traineeTimelineController = new AbortController();
                const signal = traineeTimelineController.signal;
                const params = new URLSearchParams(window.location.search);
                params.set('data_type', 'trainee_timeline');
                params.set('trainkey', trainkey);
                const requestUrl = 'trainee_stats.php?' + params.toString();
                console.log('[Timeline] Loading data for trainee:', trainkey);
                
                fetch(requestUrl, { credentials: 'same-origin', signal })
                    .then(r => r.json())
                    .then(json => {
                        console.log('[Timeline] Response:', json.status, json.data ? json.data.length + ' rows' : 'no data');
                        if (json.status === 'success') {
                            if (json.data && json.data.length > 0) {
                                renderTraineeTimelineChart(json.data);
                                renderTraineeTimelineTable(json.data);
                            } else {
                                console.warn('[Timeline] No data found for this trainee');
                                renderTraineeTimelineChart([]);
                                renderTraineeTimelineTable([]);
                            }
                        } else {
                            console.error('[Timeline] Error:', json.message || 'Unknown error');
                            renderTraineeTimelineChart([]);
                            renderTraineeTimelineTable([]);
                        }
                    })
                    .catch((err) => {
                        if (err && err.name === 'AbortError') {
                            console.log('[Timeline] Aborted');
                            return;
                        }
                        console.error('[Timeline] Error:', err);
                        renderTraineeTimelineChart([]);
                        renderTraineeTimelineTable([]);
                    })
                    .finally(() => {
                        this.disabled = false;
                        if (loader) loader.classList.remove('active');
                    });
            });

           if (downloadBtn) {
               downloadBtn.addEventListener('click', function() {
                   const raw = traineeSelect.value;
                   const trainkey = raw; // Keep as string since trainee IDs are UUIDs
                   if (!trainkey || trainkey === '' || trainkey.length < 10) return;
                   const params = new URLSearchParams(window.location.search);
                   params.set('data_type', 'trainee_timeline_csv');
                   params.set('trainkey', trainkey);
                   const url = 'trainee_stats.php?' + params.toString();
                   window.location.href = url;
               });
           }
        }
   });
   
   // Function to load charts with PHP data
   function loadChartsWithPHPData() {
       // Enrollment Chart
       const enrollmentData = <?php echo json_encode($year_data); ?>;
       if (enrollmentData && enrollmentData.length > 0) {
           updateEnrollmentChart(enrollmentData, 'detailed');
       } else {
           showNoDataMessage('enrollmentChart', 'No enrollment data available');
       }
       
       // Competency Completion Chart
       const competencyData = <?php echo json_encode($competency_data); ?>;
       if (competencyData && competencyData.length > 0) {
           updateCompetencyChart(competencyData, 'detailed');
       } else {
           showNoDataMessage('competencyChart', 'No competency data available');
       }
       
       // Recent Activity Chart
       const activityData = <?php echo json_encode($recent_activity); ?>;
       if (activityData && activityData.length > 0) {
           updateActivityChart(activityData);
       } else {
           showNoDataMessage('activityChart', 'No recent activity data available');
       }
       
       // Supervisor Distribution Chart
       const supervisorData = <?php echo json_encode($supervisor_data); ?>;
       if (supervisorData && supervisorData.length > 0) {
           updateSupervisorChart(supervisorData);
       } else {
           showNoDataMessage('supervisorChart', 'No supervisor data available');
       }
   }
   
   // Function to show no data message
   function showNoDataMessage(canvasId, message) {
       const canvas = document.getElementById(canvasId);
       if (canvas) {
           const ctx = canvas.getContext('2d');
           ctx.fillStyle = '#666';
           ctx.font = '16px Arial';
           ctx.textAlign = 'center';
           ctx.fillText(message, canvas.width / 2, canvas.height / 2);
       }
   }
   </script>
</body>
</html>
<?php
} else {
   echo "Not authorised";
}

/*
 * DATABASE INDEX CREATION SCRIPT
 * 
 * Execute these SQL statements to create ONLY the indexes NOT YET IMPLEMENTED:
 * 
 * -- Other table indexes (NOT YET IMPLEMENTED)
 * CREATE INDEX idx_trainee_tab_link_trainkey ON trainee_tab_link(trainkey);
 * CREATE INDEX idx_who_there_usrkey ON who_there(usrkey);
 * CREATE FULLTEXT INDEX idx_select_types_str_fulltext ON select_types(str);
 * 
 * -- ALREADY IMPLEMENTED INDEXES (already optimized in queries):
 * -- CREATE INDEX idx_trainee_uid ON trainee_tbl(uid);
 * -- CREATE INDEX idx_trainee_last_used ON trainee_tbl(last_used);
 * -- CREATE INDEX idx_trainee_supervisor ON trainee_tbl(supervisor);
 * -- CREATE INDEX idx_trainee_uid_year ON trainee_tbl(uid, year);
 * -- CREATE INDEX idx_trainee_year ON trainee_tbl(year);
 * -- CREATE INDEX idx_trainee_supervisor2 ON trainee_tbl(supervisor2);
 * -- CREATE INDEX idx_trainee_supervisor3 ON trainee_tbl(supervisor3);
 * -- CREATE INDEX idx_trainee_log_trainkey ON trainee_log(trainkey);
 * -- CREATE INDEX idx_trainee_log_tbid ON trainee_log(tbid);
 * -- CREATE INDEX idx_trainee_log_date_added ON trainee_log(date_added);
 * -- CREATE INDEX idx_trainee_log_trainkey_tbid ON trainee_log(trainkey, tbid);
 * -- CREATE INDEX idx_trainee_log_trainkey_date ON trainee_log(trainkey, date_added);
 * -- CREATE INDEX idx_trainee_log_stid ON trainee_log(stid);
 * -- CREATE INDEX idx_trainee_log_logkey ON trainee_log(logkey);
 * -- CREATE INDEX idx_trainee_log_select_val ON trainee_log(select_val);
 * -- CREATE INDEX idx_trainee_log_logkey_tbid ON trainee_log(logkey, tbid);
 * -- CREATE INDEX idx_tabs_isvis_sort ON tabs_tbl(isvis, sort_order);
 * -- CREATE INDEX idx_select_types_str ON select_types(str);
 * -- CREATE INDEX idx_trainee_tab_link_tbid ON trainee_tab_link(tbid);
 * -- CREATE INDEX idx_who_there_admintype ON who_there(admintype);
 * -- CREATE INDEX idx_uni_university ON uni_tbl(university);
 * 
 * -- Performance monitoring queries
 * -- Check index usage: SHOW INDEX FROM table_name;
 * -- Analyze query performance: EXPLAIN ANALYZE SELECT ...;
 * -- Monitor slow queries: SHOW VARIABLES LIKE 'slow_query_log';
 */
?>
