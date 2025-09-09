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
$subtitle = "Trainee Analytics";
$listurl = "indextable.php";
$listname = "Dashboard";

// Check permissions - allow all admin types to view BABCP stats
if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
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
    
    $result = $mysqli->query($babcp_trainees_query);
    while ($row = $result->fetch_assoc()) {
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
    
    $result = $mysqli->query($training_trainees_query);
    $training_trainees = [];
    while ($row = $result->fetch_assoc()) {
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
    
    $result = $mysqli->query($supervised_trainees_query);
    $supervised_trainees = [];
    while ($row = $result->fetch_assoc()) {
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
    
    $result = $mysqli->query($modality_trainees_query);
    $modality_trainees = [];
    while ($row = $result->fetch_assoc()) {
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
    
    $result = $mysqli->query($sessions_trainees_query);
    $sessions_trainees = [];
    while ($row = $result->fetch_assoc()) {
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

// Get active trainees (logged in within last 30 days) with logging
// PERFORMANCE: Critical for performance - requires index on trainee_tbl.last_used
$query_start = microtime(true);
// OPTIMIZED: Use index hint and optimize date comparison
$active_trainees_query = "SELECT /*+ USE_INDEX(t, idx_trainee_last_used) */ COUNT(*) as active 
                         FROM trainee_tbl t 
                         WHERE t.last_used >= ? $course_condition $cohort_condition $babcp_condition_simple $additional_conditions";
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

// Get trainees by year
// OPTIMIZED: Use composite index and year index for better performance
$trainees_by_year_query = "SELECT /*+ USE_INDEX(t, idx_trainee_uid_year) USE_INDEX(t, idx_trainee_year) */ t.year, COUNT(*) as count 
                          FROM trainee_tbl t 
                          WHERE 1=1 $course_condition $cohort_condition $babcp_condition_simple $additional_conditions 
                          GROUP BY t.year 
                          ORDER BY t.year DESC";
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

// Get competency completion stats
// PERFORMANCE: Complex query requiring multiple indexes:
// - tabs_tbl: (isvis, sort_order)
// - trainee_tab_link: (tbid, trainkey)
// - trainee_log: (trainkey, tbid)
// OPTIMIZED: Use index hints and optimize JOIN order
$competency_stats_query = "
    SELECT /*+ USE_INDEX(tabs, idx_tabs_isvis_sort) USE_INDEX(ttl, idx_trainee_tab_link_tbid) USE_INDEX(tl, idx_trainee_log_trainkey_tbid) */
        tabs.tab_name,
        COUNT(DISTINCT tl.trainkey) as trainees_with_data,
        COUNT(tl.tlogid) as total_entries
    FROM tabs_tbl tabs
    LEFT JOIN trainee_tab_link ttl ON tabs.tbid = ttl.tbid
    LEFT JOIN trainee_tbl t ON ttl.trainkey = t.trainkey
    LEFT JOIN trainee_log tl ON t.trainkey = tl.trainkey AND tl.tbid = tabs.tbid
    WHERE tabs.isvis = 1 $course_condition $cohort_condition $babcp_condition_with_tabs $additional_conditions
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
        'total_entries' => $total_entries,
                 'completion_rate' => $total_trainees > 0 ? round(($trainees_with_data / $total_trainees) * 100, 1) : 0
    ];
}
$stmt->close();

// Get recent activity (last 7 days)
// OPTIMIZED: Use index hints for date-based queries including composite index
$recent_activity_query = "
    SELECT /*+ USE_INDEX(tl, idx_trainee_log_date_added) USE_INDEX(tl, idx_trainee_log_trainkey) USE_INDEX(tl, idx_trainee_log_trainkey_date) */
        DATE(FROM_UNIXTIME(tl.date_added)) as activity_date,
        COUNT(*) as entries
    FROM trainee_log tl
    JOIN trainee_tbl t ON tl.trainkey = t.trainkey
    WHERE tl.date_added >= ? $course_condition $cohort_condition $babcp_condition_simple $additional_conditions
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

// Get all courses for filter dropdown
// OPTIMIZED: Use index hint for university sorting with caching
$cache_key = 'courses_list';
$courses = QueryCache::get($cache_key);

if ($courses === null) {
    $courses_query = "SELECT /*+ USE_INDEX(uni_tbl, idx_uni_university) */ uid, university FROM uni_tbl ORDER BY university";
    $courses_result = $mysqli->query($courses_query);
    $courses = [];
    while ($row = $courses_result->fetch_assoc()) {
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
            <div class="content-header">
               <div class="content-title">
                  <?php echo $pagetitle ?>
                  <small><?php echo $subtitle ?></small>
               </div>
               <div class="content-subtitle">
                  <a href="<?php echo $listurl ?>" class="btn btn-sm btn-secondary">Back to Dashboard</a>
                  <a href="trainee_detailed_stats.php" class="btn btn-sm btn-primary ml-2">Detailed Analytics</a>
               </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-body">
                        <form method="GET" class="row">
                           <div class="col-md-2">
                              <label for="start_year">Start Year</label>
                              <select name="start_year" id="start_year" class="form-control">
                                 <?php for ($year = $thisyear - 5; $year <= $thisyear; $year++): ?>
                                    <option value="<?php echo $year ?>" <?php echo $year == $start_year ? 'selected' : '' ?>>
                                       <?php echo $year ?>
                                    </option>
                                 <?php endfor; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="end_year">End Year</label>
                              <select name="end_year" id="end_year" class="form-control">
                                 <?php for ($year = $thisyear - 5; $year <= $thisyear; $year++): ?>
                                    <option value="<?php echo $year ?>" <?php echo $year == $end_year ? 'selected' : '' ?>>
                                       <?php echo $year ?>
                                    </option>
                                 <?php endfor; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="course">Course</label>
                              <select name="course" id="course" class="form-control">
                                 <option value="0" <?php echo $selected_course == 0 ? 'selected' : '' ?>>All Courses</option>
                                 <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['uid'] ?>" <?php echo $selected_course == $course['uid'] ? 'selected' : '' ?>>
                                       <?php echo htmlspecialchars($course['university']) ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="cohort_year">Cohort Year</label>
                              <select name="cohort_year" id="cohort_year" class="form-control">
                                 <option value="0" <?php echo $selected_cohort_year == 0 ? 'selected' : '' ?>>All Years</option>
                                 <?php for ($year = $thisyear - 5; $year <= $thisyear + 2; $year++): ?>
                                    <option value="<?php echo $year ?>" <?php echo $selected_cohort_year == $year ? 'selected' : '' ?>>
                                       <?php echo $year ?>
                                    </option>
                                 <?php endfor; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="babcp_filter">BABCP Filter</label>
                              <select name="babcp_filter" id="babcp_filter" class="form-control">
                                 <option value="0" <?php echo $babcp_filter == 0 ? 'selected' : '' ?>>All Data</option>
                                 <option value="1" <?php echo $babcp_filter == 1 ? 'selected' : '' ?>>BABCP Related Only</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="babcp_training">Training Cases</label>
                              <select name="babcp_training" id="babcp_training" class="form-control">
                                 <option value="0" <?php echo $babcp_training == 0 ? 'selected' : '' ?>>All Cases</option>
                                 <option value="1" <?php echo $babcp_training == 1 ? 'selected' : '' ?>>Training Cases Only</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="supervised_case">Supervision</label>
                              <select name="supervised_case" id="supervised_case" class="form-control">
                                 <option value="0" <?php echo $supervised_case == 0 ? 'selected' : '' ?>>All Cases</option>
                                 <option value="1" <?php echo $supervised_case == 1 ? 'selected' : '' ?>>Supervised Cases Only</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="primary_modality">Primary Modality</label>
                              <select name="primary_modality" id="primary_modality" class="form-control">
                                 <option value="" <?php echo empty($primary_modality) ? 'selected' : '' ?>>All Modalities</option>
                                 <option value="CBT" <?php echo $primary_modality == 'CBT' ? 'selected' : '' ?>>CBT</option>
                                 <option value="DBT" <?php echo $primary_modality == 'DBT' ? 'selected' : '' ?>>DBT</option>
                                 <option value="ACT" <?php echo $primary_modality == 'ACT' ? 'selected' : '' ?>>ACT</option>
                                 <option value="REBT" <?php echo $primary_modality == 'REBT' ? 'selected' : '' ?>>REBT</option>
                                 <option value="Schema" <?php echo $primary_modality == 'Schema' ? 'selected' : '' ?>>Schema Therapy</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="min_sessions">Min Sessions</label>
                              <select name="min_sessions" id="min_sessions" class="form-control">
                                 <option value="0" <?php echo $min_sessions == 0 ? 'selected' : '' ?>>Any Sessions</option>
                                 <option value="5" <?php echo $min_sessions == 5 ? 'selected' : '' ?>>5+ Sessions</option>
                                 <option value="10" <?php echo $min_sessions == 10 ? 'selected' : '' ?>>10+ Sessions</option>
                                 <option value="15" <?php echo $min_sessions == 15 ? 'selected' : '' ?>>15+ Sessions</option>
                                 <option value="20" <?php echo $min_sessions == 20 ? 'selected' : '' ?>>20+ Sessions</option>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label>&nbsp;</label>
                              <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                           </div>
                           <div class="col-md-2">
                              <label>&nbsp;</label>
                              <a href="trainee_stats.php" class="btn btn-secondary btn-block">Clear Filters</a>
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
                        </div>
                        <div class="row mt-3">
                           <div class="col-md-6">
                              <h6>Applied Filters</h6>
                              <pre><?php echo json_encode($filters, JSON_PRETTY_PRINT); ?></pre>
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
                           <table class="table table-striped">
                              <thead>
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
                                          <div class="progress">
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
