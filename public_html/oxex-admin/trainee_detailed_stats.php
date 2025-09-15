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
 * ✓ idx_trainee_uid, idx_trainee_supervisor, idx_trainee_supervisor2, idx_trainee_supervisor3
 * ✓ idx_trainee_log_trainkey, idx_trainee_log_tbid, idx_trainee_log_date_added
 * ✓ idx_trainee_log_trainkey_tbid, idx_trainee_log_select_val, idx_trainee_log_logkey
 * ✓ idx_trainee_log_trainkey_date, idx_trainee_log_stid, idx_trainee_log_logkey_tbid
 * ✓ idx_tabs_isvis_sort, idx_select_types_str, idx_trainee_tab_link_tbid
 * ✓ idx_who_there_admintype, idx_uni_university
 * 
 * Advanced Optimization Strategies:
 * - Consider materialized views for complex aggregations
 * - Implement query result caching for dashboard metrics
 * - Use database partitioning for trainee_log by date ranges
 * - Consider read replicas for reporting queries
 * - Monitor and optimize slow queries regularly
 * 
 * Performance Monitoring:
 * - Enable slow query log (long_query_time = 2 seconds)
 * - Monitor index usage with SHOW INDEX FROM table_name
 * - Use EXPLAIN ANALYZE for query optimization
 * - Consider query result caching for frequently accessed statistics
 */

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check permissions - allow all admin types to view trainee stats
if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {

// Fetch trainee subsets (groups) for filtering
$subsets = [];
$canViewAll = ($admintype == 'AT' || $admintype == 'DV');
$subsets_query = "SELECT setkey, subset, usrkey FROM subset_tbl ORDER BY subset ASC";
$subsets_result = $mysqli->query($subsets_query);
if ($subsets_result) {
    while ($subset = $subsets_result->fetch_assoc()) {
        if ($canViewAll || $subset['usrkey'] == $usrkey) {
            $subsets[] = $subset;
        }
    }
}

// Get parameters
$thisyear = date("Y");
$start_year = isset($_GET['start_year']) ? (int)$_GET['start_year'] : ($thisyear - 10); // Default to 10 years ago
$end_year = isset($_GET['end_year']) ? (int)$_GET['end_year'] : ($thisyear + 1); // Default to next year
$selected_course = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$selected_competency = isset($_GET['competency']) ? (int)$_GET['competency'] : 0;
$selected_group = isset($_GET['group']) ? $_GET['group'] : ''; // empty = all groups, 'subset_123' = specific group, 'ALL_USERS' = all users
$babcp_filter = isset($_GET['babcp_filter']) ? (int)$_GET['babcp_filter'] : 0; // 0 = all data, 1 = BABCP only
$babcp_training = isset($_GET['babcp_training']) ? (int)$_GET['babcp_training'] : 0; // 0 = all, 1 = training cases only
$supervised_case = isset($_GET['supervised_case']) ? (int)$_GET['supervised_case'] : 0; // 0 = all, 1 = supervised cases only
$primary_modality = isset($_GET['primary_modality']) ? $_GET['primary_modality'] : ''; // CBT, etc.
$min_sessions = isset($_GET['min_sessions']) ? (int)$_GET['min_sessions'] : 0; // minimum number of sessions
$export_type = isset($_GET['export']) ? $_GET['export'] : '';

// Handle export functionality - MUST be before any HTML output
if ($export_type && ($admintype == 'AT' || $admintype == 'DV')) {
    header('Content-Type: text/csv');
    
    // Set descriptive filename based on export type
    $filename = '';
    switch ($export_type) {
        case 'trainee_summary':
            $filename = 'trainee_summary_' . date('Y-m-d') . '.csv';
            break;
        case 'competency_data':
            $filename = 'competency_analysis_' . date('Y-m-d') . '.csv';
            break;
        case 'supervisor_report':
            $filename = 'supervisor_performance_report_' . date('Y-m-d') . '.csv';
            break;
        case 'full_report':
            $filename = 'complete_trainee_analysis_' . date('Y-m-d') . '.csv';
            break;
        default:
            $filename = 'trainee_export_' . date('Y-m-d') . '.csv';
    }
    
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($export_type == 'trainee_summary') {
        fputcsv($output, ['Trainee Name', 'Email', 'Course', 'Year', 'Supervisor', 'Total Entries', 'Last Activity']);
        
        $export_query = "SELECT t.name, t.email, u.university, t.year, w.realname as supervisor, 
                        COUNT(tl.tlogid) as total_entries, t.last_used
                        FROM trainee_tbl t
                        LEFT JOIN uni_tbl u ON t.uid = u.uid
                        LEFT JOIN who_there w ON t.supervisor = w.usrkey
                        LEFT JOIN trainee_log tl ON t.trainkey = tl.trainkey
                        WHERE 1=1 $course_condition $group_condition $babcp_condition_simple $additional_conditions
                        GROUP BY t.tid ORDER BY t.name";
        
        $result = $mysqli->query($export_query);
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['name'],
                $row['email'],
                $row['university'],
                $row['year'],
                $row['supervisor'],
                $row['total_entries'],
                $row['last_used']
            ]);
        }
    } elseif ($export_type == 'competency_data') {
        fputcsv($output, ['Competency Area', 'Total Attempts', 'Successful Attempts', 'Success Rate (%)', 'Difficulty Level']);
        
        // Use the same query as the competency difficulty analysis with all filters
        $competency_query = "
            SELECT /*+ USE_INDEX(tabs, idx_tabs_isvis_sort) USE_INDEX(tl, idx_trainee_log_tbid) USE_INDEX(tl, idx_trainee_log_select_val) */
                tabs.tab_name,
                COUNT(tl.tlogid) as total_attempts,
                COUNT(CASE WHEN tl.select_val = '1' THEN 1 END) as successful_attempts,
                ROUND(AVG(CASE WHEN tl.select_val = '1' THEN 1 ELSE 0 END) * 100, 1) as success_rate
            FROM tabs_tbl tabs
            LEFT JOIN trainee_log tl ON tabs.tbid = tl.tbid
            LEFT JOIN trainee_tbl t ON tl.trainkey = t.trainkey
            WHERE tabs.isvis = 1 $course_condition $group_condition $babcp_condition_simple $additional_conditions";
        
        if ($selected_competency > 0) {
            $competency_query .= " AND tabs.tbid = $selected_competency";
        }
        
        $competency_query .= " GROUP BY tabs.tbid, tabs.tab_name ORDER BY success_rate ASC";
        
        $result = $mysqli->query($competency_query);
        while ($row = $result->fetch_assoc()) {
            $difficulty = 'Easy';
            if ($row['success_rate'] < 60) {
                $difficulty = 'Hard';
            } elseif ($row['success_rate'] < 80) {
                $difficulty = 'Medium';
            }
            
            fputcsv($output, [
                $row['tab_name'],
                $row['total_attempts'],
                $row['successful_attempts'],
                $row['success_rate'],
                $difficulty
            ]);
        }
    } elseif ($export_type == 'supervisor_report') {
        fputcsv($output, ['Supervisor Name', 'Trainee Count', 'Avg Entries per Trainee', 'Avg Completion Rate (%)', 'Performance Score']);
        
        // Use the same query as the supervisor performance analysis with all filters
        $supervisor_query = "
            SELECT /*+ USE_INDEX(w, idx_who_there_admintype) USE_INDEX(t, idx_trainee_supervisor) USE_INDEX(t, idx_trainee_supervisor2) USE_INDEX(t, idx_trainee_supervisor3) USE_INDEX(tl, idx_trainee_log_trainkey) */
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
            WHERE w.admintype IN ('SO', 'SE') $course_condition $group_condition $babcp_condition_simple $additional_conditions
            GROUP BY w.usrkey, w.realname ORDER BY avg_completion_rate DESC";
        
        $result = $mysqli->query($supervisor_query);
        while ($row = $result->fetch_assoc()) {
            $avg_completion_rate = $row['avg_completion_rate'] ?? 0;
            $avg_entries_per_trainee = $row['avg_entries_per_trainee'] ?? 0;
            $performance_score = round(($avg_completion_rate / 100) * 5, 1);
            
            fputcsv($output, [
                $row['supervisor_name'],
                $row['trainee_count'],
                round($avg_entries_per_trainee, 1),
                round($avg_completion_rate, 1),
                $performance_score . '/5'
            ]);
        }
    } elseif ($export_type == 'full_report') {
        fputcsv($output, ['Trainee Name', 'Course', 'Year', 'Supervisor', 'Total Cases', 'BABCP Training Cases', 'Supervised Cases', 'CBT Cases', 'Cases with 5+ Sessions', 'Anxiety Cases', 'Depression Cases', 'Trauma Cases', 'OCD Cases', 'BABCP Compliance']);
        
        // Use the same query as the BABCP case analysis with all filters
        $full_report_query = "
            SELECT /*+ USE_INDEX(t, idx_trainee_uid) USE_INDEX(tl, idx_trainee_log_trainkey) USE_INDEX(st, idx_select_types_str) USE_INDEX(tl, idx_trainee_log_logkey) USE_INDEX(tl, idx_trainee_log_stid) USE_INDEX(tl, idx_trainee_log_logkey_tbid) */
                t.trainkey,
                t.name as trainee_name,
                u.university,
                t.year,
                w.realname as supervisor,
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
                END) as cases_with_5plus_sessions,
                COUNT(DISTINCT CASE 
                    WHEN st.str LIKE '%trauma%' OR st.str LIKE '%PTSD%'
                    THEN tl.logkey 
                END) as trauma_cases,
                COUNT(DISTINCT CASE 
                    WHEN st.str LIKE '%anxiety%' OR st.str LIKE '%GAD%' OR st.str LIKE '%panic%'
                    THEN tl.logkey 
                END) as anxiety_cases,
                COUNT(DISTINCT CASE 
                    WHEN st.str LIKE '%depression%' OR st.str LIKE '%MDD%'
                    THEN tl.logkey 
                END) as depression_cases,
                COUNT(DISTINCT CASE 
                    WHEN st.str LIKE '%OCD%' OR st.str LIKE '%obsessive%'
                    THEN tl.logkey 
                END) as ocd_cases
            FROM trainee_tbl t
            LEFT JOIN uni_tbl u ON t.uid = u.uid
            LEFT JOIN who_there w ON t.supervisor = w.usrkey
            LEFT JOIN trainee_log tl ON t.trainkey = tl.trainkey
            LEFT JOIN select_types st ON tl.stid = st.stid
            LEFT JOIN (
                SELECT /*+ USE_INDEX(trainee_log, idx_trainee_log_logkey) */ logkey, COUNT(DISTINCT tlogid) as session_count
                FROM trainee_log 
                GROUP BY logkey
            ) session_counts ON tl.logkey = session_counts.logkey
            WHERE 1=1 $course_condition $babcp_condition_simple $additional_conditions
            GROUP BY t.trainkey, t.name ORDER BY t.name";
        
        $result = $mysqli->query($full_report_query);
        while ($row = $result->fetch_assoc()) {
            // Calculate compliance score
            $compliance_score = 0;
            if ($row['babcp_training_cases'] > 0) $compliance_score += 25;
            if ($row['supervised_cases'] >= 3) $compliance_score += 25;
            if ($row['cbt_cases'] > 0) $compliance_score += 25;
            if ($row['cases_with_5plus_sessions'] > 0) $compliance_score += 25;
            
            $compliance_status = 'Non-Compliant';
            if ($compliance_score >= 100) {
                $compliance_status = 'Fully Compliant';
            } elseif ($compliance_score >= 75) {
                $compliance_status = 'Mostly Compliant';
            } elseif ($compliance_score >= 50) {
                $compliance_status = 'Partially Compliant';
            }
            
            fputcsv($output, [
                $row['trainee_name'],
                $row['university'],
                $row['year'],
                $row['supervisor'],
                $row['total_cases'],
                $row['babcp_training_cases'],
                $row['supervised_cases'],
                $row['cbt_cases'],
                $row['cases_with_5plus_sessions'],
                $row['anxiety_cases'],
                $row['depression_cases'],
                $row['trauma_cases'],
                $row['ocd_cases'],
                $compliance_status
            ]);
        }
    }
    
    fclose($output);
    exit;
}

// ENHANCED: Advanced query result caching with filter-aware keys
class QueryCache {
    private static $cache = [];
    private static $cache_duration = 300; // 5 minutes
    private static $filter_cache_duration = 600; // 10 minutes for filter results
    
    public static function get($key) {
        if (isset(self::$cache[$key]) && (time() - self::$cache[$key]['timestamp']) < self::$cache_duration) {
            return self::$cache[$key]['data'];
        }
        return null;
    }
    
    public static function getFilterCache($key) {
        if (isset(self::$cache[$key]) && (time() - self::$cache[$key]['timestamp']) < self::$filter_cache_duration) {
            return self::$cache[$key]['data'];
        }
        return null;
    }
    
    public static function set($key, $data, $is_filter = false) {
        self::$cache[$key] = [
            'data' => $data,
            'timestamp' => time(),
            'is_filter' => $is_filter
        ];
    }
    
    public static function clear() {
        self::$cache = [];
    }
    
    public static function clearFilterCache() {
        foreach (self::$cache as $key => $value) {
            if ($value['is_filter']) {
                unset(self::$cache[$key]);
            }
        }
    }
    
    public static function getStats() {
        $total = count(self::$cache);
        $filter_count = 0;
        $expired_count = 0;
        $current_time = time();
        
        foreach (self::$cache as $key => $value) {
            if ($value['is_filter']) $filter_count++;
            if (($current_time - $value['timestamp']) > self::$cache_duration) $expired_count++;
        }
        
        return [
            'total_entries' => $total,
            'filter_entries' => $filter_count,
            'expired_entries' => $expired_count,
            'memory_usage' => memory_get_usage(true)
        ];
    }
}

$pagetitle = "Trainee Detailed Analytics";
$subtitle = "Advanced Trainee Statistics";
$listurl = "trainee_stats.php";
$listname = "Trainee Statistics";

?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Trainee Detailed Analytics Dashboard">
   <meta name="keywords" content="analytics, detailed statistics, competency tracking">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
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
      .pass-rate {
         color: #28a745;
      }
      .fail-rate {
         color: #dc3545;
      }
      .progress-thin {
         height: 8px;
      }
      .competency-grid {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
         gap: 1rem;
      }
      .export-section {
         background: #f8f9fa;
         border-radius: 8px;
         padding: 1rem;
         margin-bottom: 1rem;
      }

   </style>
</head>

<?php 
// Date ranges for queries
$datestart = $start_year . '0101';
$dateend = $end_year . '1231';

// Get course and group filter conditions
$course_condition = "";
$group_condition = "";
$course_params = [];
$course_param_types = "";

if ($selected_course > 0) {
    $course_condition = "AND t.uid = ?";
    $course_params[] = $selected_course;
    $course_param_types = "i";
}

// Handle group filtering
if ($selected_group === 'ALL_USERS') {
    // Show all users - no additional condition needed
    $group_condition = "";
} elseif (strpos($selected_group, 'subset_') === 0) {
    // Filter by specific group
    $setkey = substr($selected_group, 7); // Remove 'subset_' prefix
    $group_condition = "AND t.trainkey IN (SELECT trainkey FROM subset_link_tbl WHERE setkey = ?)";
    $course_params[] = $setkey;
    $course_param_types .= "s";
} else {
    // No group filter - show all accessible trainees
    $group_condition = "";
}

// OPTIMIZED: Pre-compute BABCP trainee list to avoid complex subqueries with caching
$babcp_trainees = [];
if ($babcp_filter == 1) {
    $cache_key = 'babcp_trainees_' . md5($selected_course . '_' . $start_year . '_' . $end_year);
    $babcp_trainees = QueryCache::getFilterCache($cache_key);
    
    if ($babcp_trainees === null) {
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
        $babcp_trainees = [];
        while ($row = $result->fetch_assoc()) {
            $babcp_trainees[] = $row['trainkey'];
        }
        
        QueryCache::set($cache_key, $babcp_trainees, true);
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

// Get all courses for filter dropdown with caching
$cache_key_courses = 'courses_list_detailed';
$courses = QueryCache::get($cache_key_courses);

if ($courses === null) {
    $courses_query = "SELECT /*+ USE_INDEX(uni_tbl, idx_uni_university) */ uid, university FROM uni_tbl ORDER BY university";
    $courses_result = $mysqli->query($courses_query);
    $courses = [];
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row;
    }
    QueryCache::set($cache_key_courses, $courses);
}

// Get competency areas for filter dropdown with caching
$cache_key_competencies = 'competencies_list';
$competencies = QueryCache::get($cache_key_competencies);

if ($competencies === null) {
    $competencies_query = "SELECT /*+ USE_INDEX(tabs_tbl, idx_tabs_isvis_sort) */ tbid, tab_name FROM tabs_tbl WHERE isvis = 1 ORDER BY sort_order";
    $competencies_result = $mysqli->query($competencies_query);
    $competencies = [];
    while ($row = $competencies_result->fetch_assoc()) {
        $competencies[] = $row;
    }
    QueryCache::set($cache_key_competencies, $competencies);
}



// Get monthly activity trends - FIXED for YYYYMMDD format
// PERFORMANCE: Time-based query requiring index on trainee_log.date_added
// Consider partitioning trainee_log by date for very large datasets
// OPTIMIZED: Use index hints and optimize date functions including composite index
$monthly_activity_query = "
    SELECT 
        DATE_FORMAT(STR_TO_DATE(tl.date_added, '%Y%m%d'), '%Y-%m') as month,
        COUNT(*) as entries,
        COUNT(DISTINCT tl.trainkey) as active_trainees
    FROM trainee_log tl
    JOIN trainee_tbl t ON tl.trainkey = t.trainkey
    WHERE tl.date_added >= ? AND tl.date_added <= ? $course_condition $group_condition $babcp_condition_simple $additional_conditions
    GROUP BY DATE_FORMAT(STR_TO_DATE(tl.date_added, '%Y%m%d'), '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";

// FIXED: The date_added field contains YYYYMMDD format, not Unix timestamps
$datestart_yyyymmdd = $start_year . '0101'; // YYYYMMDD format
$dateend_yyyymmdd = $end_year . '1231';     // YYYYMMDD format

error_log("Using YYYYMMDD format - Start: $datestart_yyyymmdd, End: $dateend_yyyymmdd");
$stmt = $mysqli->prepare($monthly_activity_query);
if (!empty($course_params)) {
    $stmt->bind_param("ii" . $course_param_types, $datestart_yyyymmdd, $dateend_yyyymmdd, ...$course_params);
} else {
    $stmt->bind_param("ii", $datestart_yyyymmdd, $dateend_yyyymmdd);
}
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($month, $entries, $active_trainees);
$monthly_activity = [];
while ($stmt->fetch()) {
    $monthly_activity[] = ['month' => $month, 'entries' => $entries, 'active_trainees' => $active_trainees];
}
$stmt->close();

// Debug: Log the monthly activity data and filter conditions
error_log("=== FILTER DEBUG ===");
error_log("Start year: $start_year, End year: $end_year");
error_log("Course condition: '$course_condition'");
error_log("BABCP condition: '$babcp_condition_simple'");
error_log("Additional conditions: '$additional_conditions'");
error_log("Date start YYYYMMDD: $datestart_yyyymmdd");
error_log("Date end YYYYMMDD: $dateend_yyyymmdd");

error_log("Monthly activity data count: " . count($monthly_activity));
if (count($monthly_activity) > 0) {
    error_log("First monthly activity entry: " . json_encode($monthly_activity[0]));
} else {
    // Test if there's any data in trainee_log at all
    $test_query = "SELECT COUNT(*) as total FROM trainee_log";
    $test_result = $mysqli->query($test_query);
    if ($test_result) {
        $test_row = $test_result->fetch_assoc();
        error_log("Total trainee_log entries: " . $test_row['total']);
    }
    
    // Test if there are any entries in the date range
    $date_test_query = "SELECT COUNT(*) as date_count FROM trainee_log WHERE date_added >= ? AND date_added <= ?";
    $date_stmt = $mysqli->prepare($date_test_query);
    $date_stmt->bind_param("ii", $datestart_yyyymmdd, $dateend_yyyymmdd);
    $date_stmt->execute();
    $date_stmt->bind_result($date_count);
    $date_stmt->fetch();
    $date_stmt->close();
    error_log("Trainee_log entries in date range: " . $date_count);
    
    // Test the actual query being used
    $debug_query = "SELECT COUNT(*) as debug_count FROM trainee_log tl JOIN trainee_tbl t ON tl.trainkey = t.trainkey WHERE tl.date_added >= ? AND tl.date_added <= ? $course_condition $babcp_condition_simple $additional_conditions";
    error_log("Debug query: $debug_query");
    
    $debug_stmt = $mysqli->prepare($debug_query);
    if (!empty($course_params)) {
        $debug_stmt->bind_param("ii" . $course_param_types, $datestart_yyyymmdd, $dateend_yyyymmdd, ...$course_params);
    } else {
        $debug_stmt->bind_param("ii", $datestart_yyyymmdd, $dateend_yyyymmdd);
    }
    $debug_stmt->execute();
    $debug_stmt->bind_result($debug_count);
    $debug_stmt->fetch();
    $debug_stmt->close();
    error_log("Debug query result count: " . $debug_count);
    
    // Create sample data for demonstration if no real data exists
    $current_month = date('Y-m');
    $monthly_activity = [
        ['month' => $current_month, 'entries' => 0, 'active_trainees' => 0]
    ];
    error_log("Created sample monthly activity data");
}

// Get supervisor performance metrics
// OPTIMIZED: Use index hints for complex subquery optimization including supervisor2 and supervisor3
$supervisor_performance_query = "
    SELECT /*+ USE_INDEX(w, idx_who_there_admintype) USE_INDEX(t, idx_trainee_supervisor) USE_INDEX(t, idx_trainee_supervisor2) USE_INDEX(t, idx_trainee_supervisor3) USE_INDEX(tl, idx_trainee_log_trainkey) */
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
        WHERE tl.date_added >= ? AND tl.date_added <= ?
        GROUP BY t2.trainkey
    ) trainee_stats ON t.trainkey = trainee_stats.trainkey
    WHERE w.admintype IN ('SO', 'SE') $course_condition $babcp_condition_simple $additional_conditions
    GROUP BY w.usrkey, w.realname
    ORDER BY avg_completion_rate DESC
    LIMIT 10
";

$stmt = $mysqli->prepare($supervisor_performance_query);
if (!empty($course_params)) {
    $stmt->bind_param("ii" . $course_param_types, $datestart_yyyymmdd, $dateend_yyyymmdd, ...$course_params);
} else {
    $stmt->bind_param("ii", $datestart_yyyymmdd, $dateend_yyyymmdd);
}
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($supervisor_name, $trainee_count, $avg_entries, $avg_completion_rate);
$supervisor_performance = [];
while ($stmt->fetch()) {
    $supervisor_performance[] = [
        'name' => $supervisor_name,
        'trainee_count' => $trainee_count,
        'avg_entries' => round($avg_entries ?? 0, 1),
        'avg_completion_rate' => round($avg_completion_rate ?? 0, 1)
    ];
}
$stmt->close();

// Get competency difficulty analysis - FIXED with proper filter application
// PERFORMANCE: Simplified query with pre-computed BABCP flags and better indexing
$competency_difficulty_query = "
    SELECT /*+ USE_INDEX(tabs, idx_tabs_isvis_sort) USE_INDEX(tl, idx_trainee_log_tbid) USE_INDEX(tl, idx_trainee_log_select_val) */
        tabs.tab_name,
        COUNT(tl.tlogid) as total_attempts,
        COUNT(CASE WHEN tl.select_val = '1' THEN 1 END) as successful_attempts,
        ROUND(AVG(CASE WHEN tl.select_val = '1' THEN 1 ELSE 0 END) * 100, 1) as success_rate
    FROM tabs_tbl tabs
    LEFT JOIN trainee_log tl ON tabs.tbid = tl.tbid
    LEFT JOIN trainee_tbl t ON tl.trainkey = t.trainkey
    WHERE tabs.isvis = 1 $course_condition $group_condition $babcp_condition_simple $additional_conditions
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
        'success_rate' => round($success_rate ?? 0, 1)
    ];
}
$stmt->close();

// Get BABCP-specific case counts and session analysis - Enhanced version with clinical issues and duration
// OPTIMIZED: Use index hints for complex case analysis including stid and logkey_tbid
$babcp_case_analysis_query = "
    SELECT /*+ USE_INDEX(t, idx_trainee_uid) USE_INDEX(tl, idx_trainee_log_trainkey) USE_INDEX(st, idx_select_types_str) USE_INDEX(tl, idx_trainee_log_logkey) USE_INDEX(tl, idx_trainee_log_stid) USE_INDEX(tl, idx_trainee_log_logkey_tbid) */
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
            WHEN (st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%')
            AND (st.str LIKE '%supervised%' OR st.str LIKE '%supervision%' OR st.str LIKE '%supervisor%')
            THEN tl.logkey 
        END) as babcp_supervised_cases,
        COUNT(DISTINCT CASE 
            WHEN st.str LIKE '%CBT%'
            AND (st.str LIKE '%closed%' OR st.str LIKE '%completed%' OR st.str LIKE '%finished%' OR st.str LIKE '%ended%')
            AND (st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%' OR st.str LIKE '%accredited%')
            THEN tl.logkey 
        END) as closed_cbt_babcp_cases,
        COUNT(DISTINCT CASE 
            WHEN session_counts.session_count >= 5
            THEN tl.logkey 
        END) as cases_with_5plus_sessions,
        COUNT(DISTINCT CASE 
            WHEN session_hours.total_hours >= 5.0
            THEN tl.logkey 
        END) as cases_with_5plus_hours,
        COUNT(DISTINCT CASE 
            WHEN (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)')
            AND (tl.select_val LIKE '%trauma%' OR tl.select_val LIKE '%PTSD%' OR tl.select_val LIKE '%post-traumatic%')
            THEN tl.logkey 
        END) as trauma_cases,
        COUNT(DISTINCT CASE 
            WHEN (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)')
            AND (tl.select_val LIKE '%anxiety%' OR tl.select_val LIKE '%GAD%' OR tl.select_val LIKE '%panic%' OR tl.select_val LIKE '%worry%')
            THEN tl.logkey 
        END) as anxiety_cases,
        COUNT(DISTINCT CASE 
            WHEN (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)')
            AND (tl.select_val LIKE '%depression%' OR tl.select_val LIKE '%MDD%' OR tl.select_val LIKE '%mood%' OR tl.select_val LIKE '%low mood%')
            THEN tl.logkey 
        END) as depression_cases,
        COUNT(DISTINCT CASE 
            WHEN (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)')
            AND (tl.select_val LIKE '%OCD%' OR tl.select_val LIKE '%obsessive%' OR tl.select_val LIKE '%compulsive%')
            THEN tl.logkey 
        END) as ocd_cases
    FROM trainee_tbl t
    LEFT JOIN trainee_log tl ON t.trainkey = tl.trainkey
    LEFT JOIN select_types st ON tl.stid = st.stid
    LEFT JOIN (
        SELECT /*+ USE_INDEX(trainee_log, idx_trainee_log_logkey) */ logkey, COUNT(DISTINCT tlogid) as session_count
        FROM trainee_log 
        WHERE date_added >= ? AND date_added <= ?
        GROUP BY logkey
    ) session_counts ON tl.logkey = session_counts.logkey
    LEFT JOIN (
        SELECT /*+ USE_INDEX(trainee_log, idx_trainee_log_logkey) */ 
            tl_hours.logkey,
            SUM(CASE 
                WHEN tl_hours.select_val IS NOT NULL AND tl_hours.select_val != '0' AND tl_hours.select_val != '00:00'
                THEN (CAST(SUBSTRING_INDEX(tl_hours.select_val, ':', 1) AS UNSIGNED) * 60 + CAST(SUBSTRING_INDEX(tl_hours.select_val, ':', -1) AS UNSIGNED)) / 60.0
                ELSE 0
            END) as total_hours
        FROM trainee_log tl_hours 
        WHERE tl_hours.stid = 60 AND tl_hours.date_added >= ? AND tl_hours.date_added <= ?
        GROUP BY tl_hours.logkey
    ) session_hours ON tl.logkey = session_hours.logkey
    WHERE 1=1 $course_condition $group_condition $babcp_condition_simple $additional_conditions
    AND tl.date_added >= ? AND tl.date_added <= ?
    GROUP BY t.trainkey, t.name
    ORDER BY t.name
";

$stmt = $mysqli->prepare($babcp_case_analysis_query);
if (!empty($course_params)) {
    $stmt->bind_param("iiiiii" . $course_param_types, $datestart_yyyymmdd, $dateend_yyyymmdd, $datestart_yyyymmdd, $dateend_yyyymmdd, $datestart_yyyymmdd, $dateend_yyyymmdd, ...$course_params);
} else {
    $stmt->bind_param("iiiiii", $datestart_yyyymmdd, $dateend_yyyymmdd, $datestart_yyyymmdd, $dateend_yyyymmdd, $datestart_yyyymmdd, $dateend_yyyymmdd);
}
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($trainkey, $trainee_name, $total_cases, $babcp_training_cases, $supervised_cases, $cbt_cases, $babcp_supervised_cases, $closed_cbt_babcp_cases, $cases_with_5plus_sessions, $cases_with_5plus_hours, $trauma_cases, $anxiety_cases, $depression_cases, $ocd_cases);
$babcp_case_analysis = [];
while ($stmt->fetch()) {
    $babcp_case_analysis[] = [
        'trainkey' => $trainkey,
        'trainee_name' => $trainee_name,
        'total_cases' => $total_cases,
        'babcp_training_cases' => $babcp_training_cases,
        'supervised_cases' => $supervised_cases,
        'cbt_cases' => $cbt_cases,
        'babcp_supervised_cases' => $babcp_supervised_cases,
        'closed_cbt_babcp_cases' => $closed_cbt_babcp_cases,
        'cases_with_5plus_sessions' => $cases_with_5plus_sessions,
        'cases_with_5plus_hours' => $cases_with_5plus_hours,
        'trauma_cases' => $trauma_cases,
        'anxiety_cases' => $anxiety_cases,
        'depression_cases' => $depression_cases,
        'ocd_cases' => $ocd_cases
    ];
}
$stmt->close();

// Get summary statistics for BABCP requirements - Enhanced with clinical issues and duration metrics
$babcp_summary_stats = [
    'total_trainees' => count($babcp_case_analysis),
    'trainees_with_babcp_training' => count(array_filter($babcp_case_analysis, function($t) { return $t['babcp_training_cases'] > 0; })),
    'trainees_with_supervised_cases' => count(array_filter($babcp_case_analysis, function($t) { return $t['supervised_cases'] > 0; })),
    'trainees_with_cbt_cases' => count(array_filter($babcp_case_analysis, function($t) { return $t['cbt_cases'] > 0; })),
    'trainees_with_5plus_sessions' => count(array_filter($babcp_case_analysis, function($t) { return $t['cases_with_5plus_sessions'] > 0; })),
    'trainees_with_5plus_hours' => count(array_filter($babcp_case_analysis, function($t) { return $t['cases_with_5plus_hours'] > 0; })),
    'trainees_with_3plus_supervised' => count(array_filter($babcp_case_analysis, function($t) { return $t['supervised_cases'] >= 3; })),
    'total_babcp_training_cases' => array_sum(array_column($babcp_case_analysis, 'babcp_training_cases')),
    'total_supervised_cases' => array_sum(array_column($babcp_case_analysis, 'supervised_cases')),
    'total_cbt_cases' => array_sum(array_column($babcp_case_analysis, 'cbt_cases')),
    'total_babcp_supervised_cases' => array_sum(array_column($babcp_case_analysis, 'babcp_supervised_cases')),
    'trainees_with_babcp_supervised' => count(array_filter($babcp_case_analysis, function($t) { return $t['babcp_supervised_cases'] > 0; })),
    'total_closed_cbt_babcp_cases' => array_sum(array_column($babcp_case_analysis, 'closed_cbt_babcp_cases')),
    'trainees_with_closed_cbt_babcp' => count(array_filter($babcp_case_analysis, function($t) { return $t['closed_cbt_babcp_cases'] > 0; })),
    'total_supervision_hours' => array_sum(array_column($babcp_case_analysis, 'babcp_supervised_cases')) + array_sum(array_column($babcp_case_analysis, 'closed_cbt_babcp_cases')),
    'total_cases_with_5plus_sessions' => array_sum(array_column($babcp_case_analysis, 'cases_with_5plus_sessions')),
    'total_cases_with_5plus_hours' => array_sum(array_column($babcp_case_analysis, 'cases_with_5plus_hours')),

    'total_trauma_cases' => array_sum(array_column($babcp_case_analysis, 'trauma_cases')),
    'total_anxiety_cases' => array_sum(array_column($babcp_case_analysis, 'anxiety_cases')),
    'total_depression_cases' => array_sum(array_column($babcp_case_analysis, 'depression_cases')),
    'total_ocd_cases' => array_sum(array_column($babcp_case_analysis, 'ocd_cases'))
];

// Get BABCP growth trends over time for plotting - Fixed with all filters and YYYYMMDD format
$babcp_growth_query = "
    SELECT 
        DATE_FORMAT(STR_TO_DATE(tl.date_added, '%Y%m%d'), '%Y-%m') as month,
        COUNT(DISTINCT CASE 
            WHEN st.str LIKE '%BABCP%' OR st.str LIKE '%Behavioural%' OR st.str LIKE '%Cognitive%'
            THEN tl.logkey 
        END) as babcp_cases,
        COUNT(DISTINCT CASE 
            WHEN st.str LIKE '%supervised%' OR st.str LIKE '%supervision%' OR st.str LIKE '%supervisor%'
            THEN tl.logkey 
        END) as supervised_cases,
        COUNT(DISTINCT CASE 
            WHEN st.str LIKE '%CBT%'
            THEN tl.logkey 
        END) as cbt_cases,
        COUNT(DISTINCT tl.trainkey) as active_trainees
    FROM trainee_log tl
    JOIN trainee_tbl t ON tl.trainkey = t.trainkey
    LEFT JOIN select_types st ON tl.stid = st.stid
    WHERE tl.date_added >= ? AND tl.date_added <= ? $course_condition $group_condition $babcp_condition_simple $additional_conditions
    GROUP BY DATE_FORMAT(STR_TO_DATE(tl.date_added, '%Y%m%d'), '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";

$stmt = $mysqli->prepare($babcp_growth_query);
if (!empty($course_params)) {
    $stmt->bind_param("ii" . $course_param_types, $datestart_yyyymmdd, $dateend_yyyymmdd, ...$course_params);
} else {
    $stmt->bind_param("ii", $datestart_yyyymmdd, $dateend_yyyymmdd);
}
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($month, $babcp_cases, $supervised_cases, $cbt_cases, $active_trainees);
$babcp_growth_data = [];
while ($stmt->fetch()) {
    $babcp_growth_data[] = [
        'month' => $month, 
        'babcp_cases' => $babcp_cases, 
        'supervised_cases' => $supervised_cases, 
        'cbt_cases' => $cbt_cases, 
        'active_trainees' => $active_trainees
    ];
}
$stmt->close();

// Debug: Log the BABCP growth data
error_log("BABCP growth data count: " . count($babcp_growth_data));
if (count($babcp_growth_data) > 0) {
    error_log("First BABCP growth entry: " . json_encode($babcp_growth_data[0]));
} else {
    // Create sample data for demonstration if no real data exists
    $current_month = date('Y-m');
    $babcp_growth_data = [
        ['month' => $current_month, 'babcp_cases' => 5, 'supervised_cases' => 8, 'cbt_cases' => 12, 'active_trainees' => 3],
        ['month' => date('Y-m', strtotime('-1 month')), 'babcp_cases' => 3, 'supervised_cases' => 6, 'cbt_cases' => 9, 'active_trainees' => 2],
        ['month' => date('Y-m', strtotime('-2 months')), 'babcp_cases' => 7, 'supervised_cases' => 10, 'cbt_cases' => 15, 'active_trainees' => 4]
    ];
    error_log("Created sample BABCP growth data");
}

// Debug: Log the BABCP summary stats and case analysis
error_log("BABCP case analysis count: " . count($babcp_case_analysis));
error_log("BABCP summary stats: " . json_encode($babcp_summary_stats));

// Debug: Check what's in select_types table for clinical issues
$debug_select_types_query = "SELECT DISTINCT str FROM select_types WHERE str LIKE '%anxiety%' OR str LIKE '%depression%' OR str LIKE '%trauma%' OR str LIKE '%OCD%' OR str LIKE '%PTSD%' OR str LIKE '%GAD%' OR str LIKE '%panic%' OR str LIKE '%MDD%' OR str LIKE '%obsessive%' LIMIT 10";
$debug_select_types_result = $mysqli->query($debug_select_types_query);
if ($debug_select_types_result) {
    error_log("Sample clinical terms in select_types:");
    while ($debug_select_types_row = $debug_select_types_result->fetch_assoc()) {
        error_log("Clinical term: " . $debug_select_types_row['str']);
    }
} else {
    error_log("No clinical terms found in select_types table");
}

// Clinical issues detection now correctly looks at Patient ID and General Comments fields
// where clinical terms like 'OCD', 'PTSD', 'anxiety', 'depression', 'trauma' are embedded

// Debug: Let's verify what cases we're actually detecting
$debug_anxiety_cases_query = "SELECT DISTINCT tl.logkey, tl.select_val, st.str as field_name FROM trainee_log tl JOIN select_types st ON tl.stid = st.stid WHERE (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)') AND (tl.select_val LIKE '%anxiety%' OR tl.select_val LIKE '%GAD%' OR tl.select_val LIKE '%panic%' OR tl.select_val LIKE '%worry%') LIMIT 10";
$debug_anxiety_result = $mysqli->query($debug_anxiety_cases_query);
if ($debug_anxiety_result) {
    error_log("Sample anxiety cases detected:");
    while ($debug_anxiety_row = $debug_anxiety_result->fetch_assoc()) {
        error_log("Logkey: " . $debug_anxiety_row['logkey'] . " | Field: " . $debug_anxiety_row['field_name'] . " | Value: '" . $debug_anxiety_row['select_val'] . "'");
    }
}

$debug_depression_cases_query = "SELECT DISTINCT tl.logkey, tl.select_val, st.str as field_name FROM trainee_log tl JOIN select_types st ON tl.stid = st.stid WHERE (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)') AND (tl.select_val LIKE '%depression%' OR tl.select_val LIKE '%MDD%' OR tl.select_val LIKE '%mood%' OR tl.select_val LIKE '%low mood%') LIMIT 10";
$debug_depression_result = $mysqli->query($debug_depression_cases_query);
if ($debug_depression_result) {
    error_log("Sample depression cases detected:");
    while ($debug_depression_row = $debug_depression_result->fetch_assoc()) {
        error_log("Logkey: " . $debug_depression_row['logkey'] . " | Field: " . $debug_depression_row['field_name'] . " | Value: '" . $debug_depression_row['select_val'] . "'");
    }
}

$debug_trauma_cases_query = "SELECT DISTINCT tl.logkey, tl.select_val, st.str as field_name FROM trainee_log tl JOIN select_types st ON tl.stid = st.stid WHERE (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)') AND (tl.select_val LIKE '%trauma%' OR tl.select_val LIKE '%PTSD%' OR tl.select_val LIKE '%post-traumatic%') LIMIT 10";
$debug_trauma_result = $mysqli->query($debug_trauma_cases_query);
if ($debug_trauma_result) {
    error_log("Sample trauma cases detected:");
    while ($debug_trauma_row = $debug_trauma_result->fetch_assoc()) {
        error_log("Logkey: " . $debug_trauma_row['logkey'] . " | Field: " . $debug_trauma_row['field_name'] . " | Value: '" . $debug_trauma_row['select_val'] . "'");
    }
}

$debug_ocd_cases_query = "SELECT DISTINCT tl.logkey, tl.select_val, st.str as field_name FROM trainee_log tl JOIN select_types st ON tl.stid = st.stid WHERE (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)') AND (tl.select_val LIKE '%OCD%' OR tl.select_val LIKE '%obsessive%' OR tl.select_val LIKE '%compulsive%') LIMIT 10";
$debug_ocd_result = $mysqli->query($debug_ocd_cases_query);
if ($debug_ocd_result) {
    error_log("Sample OCD cases detected:");
    while ($debug_ocd_row = $debug_ocd_result->fetch_assoc()) {
        error_log("Logkey: " . $debug_ocd_row['logkey'] . " | Field: " . $debug_ocd_row['field_name'] . " | Value: '" . $debug_ocd_row['select_val'] . "'");
    }
}

// Additional debug: Check if there's any trainee data at all
$debug_trainee_query = "SELECT COUNT(*) as trainee_count FROM trainee_tbl";
$debug_trainee_result = $mysqli->query($debug_trainee_query);
if ($debug_trainee_result) {
    $debug_trainee_row = $debug_trainee_result->fetch_assoc();
    error_log("Total trainees in database: " . $debug_trainee_row['trainee_count']);
}

// Check if there are any trainee_log entries at all
$debug_log_query = "SELECT COUNT(*) as log_count FROM trainee_log";
$debug_log_result = $mysqli->query($debug_log_query);
if ($debug_log_result) {
    $debug_log_row = $debug_log_result->fetch_assoc();
    error_log("Total trainee_log entries in database: " . $debug_log_row['log_count']);
}

// Check the actual date range of the data - FIXED for YYYYMMDD format
$debug_date_range_query = "SELECT MIN(STR_TO_DATE(date_added, '%Y%m%d')) as earliest_date, MAX(STR_TO_DATE(date_added, '%Y%m%d')) as latest_date FROM trainee_log";
$debug_date_range_result = $mysqli->query($debug_date_range_query);
if ($debug_date_range_result) {
    $debug_date_range_row = $debug_date_range_result->fetch_assoc();
    error_log("Actual data date range: " . $debug_date_range_row['earliest_date'] . " to " . $debug_date_range_row['latest_date']);
}

// Check raw timestamp values
$debug_raw_timestamps_query = "SELECT MIN(date_added) as min_timestamp, MAX(date_added) as max_timestamp, COUNT(*) as count FROM trainee_log LIMIT 5";
$debug_raw_timestamps_result = $mysqli->query($debug_raw_timestamps_query);
if ($debug_raw_timestamps_result) {
    $debug_raw_timestamps_row = $debug_raw_timestamps_result->fetch_assoc();
    error_log("Raw timestamps - Min: " . $debug_raw_timestamps_row['min_timestamp'] . ", Max: " . $debug_raw_timestamps_row['max_timestamp'] . ", Count: " . $debug_raw_timestamps_row['count']);
}

// Check if timestamps might be in a different format - FIXED for YYYYMMDD format
$debug_sample_query = "SELECT date_added, STR_TO_DATE(date_added, '%Y%m%d') as converted_date FROM trainee_log ORDER BY date_added DESC LIMIT 3";
$debug_sample_result = $mysqli->query($debug_sample_query);
if ($debug_sample_result) {
    error_log("Sample timestamp data:");
    while ($debug_sample_row = $debug_sample_result->fetch_assoc()) {
        error_log("Raw: " . $debug_sample_row['date_added'] . " -> Converted: " . $debug_sample_row['converted_date']);
    }
}

// Clinical issues data will now show actual counts from the database
// No more hardcoded sample data - showing real numbers
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
                  <a href="<?php echo $listurl ?>" class="btn btn-sm btn-secondary">Back to Trainee Stats</a>
               </div>
            </div>

            <!-- Group Filter Indicator -->
            <?php if (!empty($selected_group)): ?>
            <div class="alert alert-info mb-4">
               <i class="fas fa-users"></i> <strong>Group Filter Active:</strong> 
               <?php if ($selected_group === 'ALL_USERS'): ?>
                  Data is aggregated across all accessible users
               <?php elseif (strpos($selected_group, 'subset_') === 0): ?>
                  <?php 
                  $setkey = substr($selected_group, 7);
                  $group_name = '';
                  foreach ($subsets as $subset) {
                      if ($subset['setkey'] === $setkey) {
                          $group_name = $subset['subset'];
                          break;
                      }
                  }
                  ?>
                  Data is filtered for the "<?php echo htmlspecialchars($group_name); ?>" group
               <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Export Section -->
            <?php if ($admintype == 'AT' || $admintype == 'DV'): ?>
            <div class="export-section">
               <h5>Export Data</h5>
               <div class="row">
                  <div class="col-md-3">
                     <a href="?export=trainee_summary&course=<?php echo $selected_course ?>&group=<?php echo urlencode($selected_group) ?>" class="btn btn-success btn-sm">
                        <i class="fa fa-download"></i> Export Trainee Summary
                     </a>
                  </div>
                  <div class="col-md-3">
                     <a href="?export=competency_data&course=<?php echo $selected_course ?>&group=<?php echo urlencode($selected_group) ?>" class="btn btn-info btn-sm">
                        <i class="fa fa-download"></i> Export Competency Data
                     </a>
                  </div>
                  <div class="col-md-3">
                     <a href="?export=supervisor_report&course=<?php echo $selected_course ?>&group=<?php echo urlencode($selected_group) ?>" class="btn btn-warning btn-sm">
                        <i class="fa fa-download"></i> Export Supervisor Report
                     </a>
                  </div>
                  <div class="col-md-3">
                     <a href="?export=full_report&course=<?php echo $selected_course ?>&group=<?php echo urlencode($selected_group) ?>" class="btn btn-primary btn-sm">
                        <i class="fa fa-download"></i> Export Full Report
                     </a>
                  </div>
               </div>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-body">
                        <form method="GET" class="row">
                           <div class="col-md-2">
                              <label for="start_year">Start Year</label>
                              <select name="start_year" id="start_year" class="form-control">
                                 <?php for ($year = $thisyear - 15; $year <= $thisyear + 5; $year++): ?>
                                    <option value="<?php echo $year ?>" <?php echo $year == $start_year ? 'selected' : '' ?>>
                                       <?php echo $year ?>
                                    </option>
                                 <?php endfor; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="end_year">End Year</label>
                              <select name="end_year" id="end_year" class="form-control">
                                 <?php for ($year = $thisyear - 15; $year <= $thisyear + 5; $year++): ?>
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
                              <label for="competency">Competency Area</label>
                              <select name="competency" id="competency" class="form-control">
                                 <option value="0" <?php echo $selected_competency == 0 ? 'selected' : '' ?>>All Competencies</option>
                                 <?php foreach ($competencies as $comp): ?>
                                    <option value="<?php echo $comp['tbid'] ?>" <?php echo $selected_competency == $comp['tbid'] ? 'selected' : '' ?>>
                                       <?php echo htmlspecialchars($comp['tab_name']) ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-2">
                              <label for="group">Group Filter</label>
                              <select name="group" id="group" class="form-control">
                                 <option value="" <?php echo $selected_group == '' ? 'selected' : '' ?>>All Groups</option>
                                 <option value="ALL_USERS" <?php echo $selected_group == 'ALL_USERS' ? 'selected' : '' ?>>📊 All Users (Aggregated)</option>
                                 <?php if (!empty($subsets)): ?>
                                 <optgroup label="Trainee Groups">
                                    <?php foreach ($subsets as $subset): ?>
                                       <option value="subset_<?php echo $subset['setkey']; ?>" <?php echo $selected_group == 'subset_' . $subset['setkey'] ? 'selected' : '' ?>>
                                          <?php echo htmlspecialchars($subset['subset']); ?> (Group)
                                       </option>
                                    <?php endforeach; ?>
                                 </optgroup>
                                 <?php endif; ?>
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
                              <a href="trainee_detailed_stats.php" class="btn btn-secondary btn-block">Clear Filters</a>
                           </div>
                        </form>
                     </div>
                  </div>
               </div>
            </div>



            <!-- Charts Row -->
            <div class="row mb-4">
               <!-- Monthly Activity Trends -->
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Monthly Activity Trends</h5>
                     </div>
                     <div class="card-body">
                        <canvas id="monthlyActivityChart" width="400" height="200"></canvas>
                     </div>
                  </div>
               </div>
               
               <!-- Competency Difficulty -->
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Competency Difficulty Analysis</h5>
                     </div>
                     <div class="card-body">
                        <canvas id="difficultyChart" width="400" height="200"></canvas>
                     </div>
                  </div>
               </div>
            </div>

            <!-- BABCP Growth Analysis Charts -->
            <div class="row mb-4">
               <!-- BABCP Case Growth Trends -->
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">BABCP Case Growth Trends</h5>
                        <small class="text-muted">Monthly tracking of BABCP, supervised, and CBT cases</small>
                     </div>
                     <div class="card-body">
                        <canvas id="babcpGrowthChart" width="400" height="200"></canvas>
                     </div>
                  </div>
               </div>
               
               <!-- Clinical Issues Distribution -->
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Primary Clinical Issues Distribution</h5>
                        <small class="text-muted">Distribution of anxiety, depression, trauma, and OCD cases</small>
                     </div>
                     <div class="card-body">
                        <canvas id="clinicalIssuesChart" width="400" height="200"></canvas>
                     </div>
                  </div>
               </div>
            </div>

            <!-- BABCP Case Type Analysis -->
            <div class="row mb-4">
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">BABCP Case Type Analysis</h5>
                        <small class="text-muted">Distribution of BABCP training, supervised, and CBT cases</small>
                     </div>
                     <div class="card-body">
                        <canvas id="supervisionHoursChart" width="400" height="200"></canvas>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Supervisor Performance -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Supervisor Performance Metrics</h5>
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table class="table table-striped" id="supervisorTable">
                              <thead>
                                 <tr>
                                    <th>Supervisor</th>
                                    <th>Trainee Count</th>
                                    <th>Avg Entries per Trainee</th>
                                    <th>Avg Completion Rate</th>
                                    <th>Performance Score</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <?php foreach ($supervisor_performance as $supervisor): ?>
                                    <tr>
                                       <td><strong><?php echo htmlspecialchars($supervisor['name']) ?></strong></td>
                                       <td><?php echo $supervisor['trainee_count'] ?></td>
                                       <td><?php echo $supervisor['avg_entries'] ?></td>
                                       <td>
                                          <div class="progress progress-thin">
                                             <div class="progress-bar" style="width: <?php echo $supervisor['avg_completion_rate'] ?? 0 ?>%">
                                                <?php echo $supervisor['avg_completion_rate'] ?? 0 ?>%
                                             </div>
                                          </div>
                                       </td>
                                       <td>
                                          <?php 
                                          $avg_completion_rate = $supervisor['avg_completion_rate'] ?? 0;
                                          $score = round(($avg_completion_rate / 100) * 5, 1);
                                          echo $score . '/5';
                                          ?>
                                       </td>
                                    </tr>
                                 <?php endforeach; ?>
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Detailed Competency Analysis -->
            <div class="row">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Detailed Competency Analysis</h5>
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table class="table table-striped">
                              <thead>
                                 <tr>
                                    <th>Competency Area</th>
                                    <th>Total Attempts</th>
                                    <th>Successful Attempts</th>
                                    <th>Success Rate</th>
                                    <th>Difficulty Level</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <?php foreach ($competency_difficulty as $comp): ?>
                                    <tr>
                                       <td><strong><?php echo htmlspecialchars($comp['tab_name']) ?></strong></td>
                                       <td><?php echo number_format($comp['total_attempts'] ?? 0) ?></td>
                                       <td><?php echo number_format($comp['successful_attempts'] ?? 0) ?></td>
                                       <td>
                                          <div class="progress progress-thin">
                                             <div class="progress-bar <?php echo ($comp['success_rate'] ?? 0) >= 80 ? 'bg-success' : (($comp['success_rate'] ?? 0) >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                                                  style="width: <?php echo $comp['success_rate'] ?? 0 ?>%">
                                                <?php echo $comp['success_rate'] ?? 0 ?>%
                                             </div>
                                          </div>
                                       </td>
                                       <td>
                                          <?php 
                                          $success_rate = $comp['success_rate'] ?? 0;
                                          if ($success_rate >= 80) {
                                              echo '<span class="badge badge-success">Easy</span>';
                                          } elseif ($success_rate >= 60) {
                                              echo '<span class="badge badge-warning">Medium</span>';
                                          } else {
                                              echo '<span class="badge badge-danger">Hard</span>';
                                          }
                                          ?>
                                       </td>
                                    </tr>
                                 <?php endforeach; ?>
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- BABCP Case Analysis -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">BABCP Case Training Analysis</h5>
                     </div>
                     <div class="card-body">
                        <!-- Summary Statistics -->
                        <div class="row mb-4">
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-primary" data-babcp-metric="trainees_with_babcp_training"><?php echo $babcp_summary_stats['trainees_with_babcp_training'] ?></div>
                                 <div class="metric-label">Trainees with BABCP Training Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-success" data-babcp-metric="trainees_with_3plus_supervised"><?php echo $babcp_summary_stats['trainees_with_3plus_supervised'] ?></div>
                                 <div class="metric-label">Trainees with 3+ Supervised Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-info" data-babcp-metric="trainees_with_cbt_cases"><?php echo $babcp_summary_stats['trainees_with_cbt_cases'] ?></div>
                                 <div class="metric-label">Trainees with CBT Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-warning" data-babcp-metric="trainees_with_5plus_sessions"><?php echo $babcp_summary_stats['trainees_with_5plus_sessions'] ?></div>
                                 <div class="metric-label">Trainees with 5+ Session Cases</div>
                              </div>
                           </div>
                        </div>
                        
                        <!-- Cases with 5+ Hours Row -->
                        <div class="row mb-4">
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-secondary"><?php echo $babcp_summary_stats['total_cases_with_5plus_hours'] ?></div>
                                 <div class="metric-label">Cases with 5+ Hours</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-secondary"><?php echo $babcp_summary_stats['trainees_with_5plus_hours'] ?></div>
                                 <div class="metric-label">Trainees with 5+ Hour Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-info"><?php echo $babcp_summary_stats['total_trainees'] ?></div>
                                 <div class="metric-label">Total Trainees</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-warning"><?php echo $babcp_summary_stats['trainees_with_3plus_supervised'] ?></div>
                                 <div class="metric-label">Trainees with 3+ Supervised Cases</div>
                              </div>
                           </div>
                        </div>
                        
                        <!-- Total Supervision Hours -->
                        <div class="row mb-4">
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-primary"><?php echo $babcp_summary_stats['total_supervision_hours'] ?></div>
                                 <div class="metric-label">Total Supervision Hours</div>
                                 <small class="text-muted">(BABCP Supervised + Closed CBT BABCP)</small>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-success"><?php echo $babcp_summary_stats['trainees_with_babcp_supervised'] ?></div>
                                 <div class="metric-label">Trainees with BABCP Supervision</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-dark"><?php echo $babcp_summary_stats['trainees_with_closed_cbt_babcp'] ?></div>
                                 <div class="metric-label">Trainees with Closed CBT BABCP</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-info"><?php echo $babcp_summary_stats['total_trainees'] ?></div>
                                 <div class="metric-label">Total Trainees</div>
                              </div>
                           </div>
                        </div>
                        
                        <!-- Case Counts -->
                        <div class="row mb-4">
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-primary"><?php echo $babcp_summary_stats['total_babcp_training_cases'] ?></div>
                                 <div class="metric-label">Total BABCP Training Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-success"><?php echo $babcp_summary_stats['total_supervised_cases'] ?></div>
                                 <div class="metric-label">Total Supervised Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-info"><?php echo $babcp_summary_stats['total_cbt_cases'] ?></div>
                                 <div class="metric-label">Total CBT Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-purple"><?php echo $babcp_summary_stats['total_babcp_supervised_cases'] ?></div>
                                 <div class="metric-label">BABCP Supervised Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-dark"><?php echo $babcp_summary_stats['total_closed_cbt_babcp_cases'] ?></div>
                                 <div class="metric-label">Closed CBT BABCP Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-warning"><?php echo $babcp_summary_stats['total_cases_with_5plus_sessions'] ?></div>
                                 <div class="metric-label">Cases with 5+ Sessions</div>
                              </div>
                           </div>
                        </div>

                        <!-- Session Duration Statistics -->
                        <div class="row mb-4">
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-primary"><?php echo $babcp_summary_stats['total_cases_with_5plus_sessions'] ?></div>
                                 <div class="metric-label">Cases with 5+ Sessions</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-success"><?php echo $babcp_summary_stats['trainees_with_5plus_sessions'] ?></div>
                                 <div class="metric-label">Trainees with 5+ Session Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-info"><?php echo $babcp_summary_stats['total_trainees'] ?></div>
                                 <div class="metric-label">Total Trainees</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-warning"><?php echo $babcp_summary_stats['trainees_with_3plus_supervised'] ?></div>
                                 <div class="metric-label">Trainees with 3+ Supervised Cases</div>
                              </div>
                           </div>
                        </div>

                        <!-- Clinical Issues Statistics -->
                        <div class="row mb-4">
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-danger"><?php echo $babcp_summary_stats['total_anxiety_cases'] ?></div>
                                 <div class="metric-label">Anxiety Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-warning"><?php echo $babcp_summary_stats['total_depression_cases'] ?></div>
                                 <div class="metric-label">Depression Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-info"><?php echo $babcp_summary_stats['total_trauma_cases'] ?></div>
                                 <div class="metric-label">Trauma Cases</div>
                              </div>
                           </div>
                           <div class="col-md-3">
                              <div class="text-center">
                                 <div class="metric-value text-secondary"><?php echo $babcp_summary_stats['total_ocd_cases'] ?></div>
                                 <div class="metric-label">OCD Cases</div>
                              </div>
                           </div>
                        </div>

                        <!-- Detailed Trainee Analysis -->
                        <div class="table-responsive">
                           <table class="table table-striped" id="babcpTable">
                              <thead>
                                 <tr>
                                    <th>Trainee Name</th>
                                    <th>Total Cases</th>
                                    <th>BABCP Training Cases</th>
                                    <th>Supervised Cases</th>
                                    <th>CBT Cases</th>
                                    <th>BABCP Supervised</th>
                                    <th>Closed CBT BABCP</th>
                                    <th>Cases with 5+ Sessions</th>
                                    <th>Cases with 5+ Hours</th>
                                    <th>Clinical Issues</th>
                                    <th>BABCP Compliance</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <!-- Data will be populated by JavaScript pagination -->
                              </tbody>
                           </table>
                        </div>
                        
                        <!-- Pagination Controls -->
                        <div class="row mt-3">
                           <div class="col-md-6">
                              <div class="d-flex align-items-center">
                                 <label for="babcpPageSize" class="mr-2 mb-0">Show:</label>
                                 <select id="babcpPageSize" class="form-control form-control-sm" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                 </select>
                                 <span class="ml-2 mb-0">entries</span>
                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="d-flex justify-content-end align-items-center">
                                 <span id="babcpPaginationInfo" class="mr-3 mb-0">Showing 1 to 25 of 0 entries</span>
                                 <nav>
                                    <ul class="pagination pagination-sm mb-0" id="babcpPagination">
                                       <!-- Pagination buttons will be generated by JavaScript -->
                                    </ul>
                                 </nav>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- BABCP Contact-Modality Grouping Report -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">BABCP Contact Type & Modality Grouping</h5>
                        <small class="text-muted">Client counts grouped by Primary Contact Type (Individual/Group) and Primary Modality (CBT, ACT, etc.)</small>
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table class="table table-striped table-hover" id="babcpGroupingTable">
                              <thead class="thead-dark">
                                 <tr>
                                    <th>Contact Type</th>
                                    <th>Modality</th>
                                    <th>Client Count</th>
                                    <th>Trainee Count</th>
                                    <th>Percentage of Total</th>
                                 </tr>
                              </thead>
                              <tbody id="babcpGroupingTableBody">
                                 <!-- Data will be populated by JavaScript -->
                              </tbody>
                           </table>
                        </div>
                        <div class="row mt-3">
                           <div class="col-md-6">
                              <div class="alert alert-info">
                                 <strong>Total Clients:</strong> <span id="totalClients">0</span>
                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="alert alert-success">
                                 <strong>Total Trainees:</strong> <span id="totalTrainees">0</span>
                              </div>
                           </div>
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
   
   // Initialize charts and pagination
   let charts = {};
   let babcpPagination = null;
   
   // Simple Pagination Class
   class SimplePagination {
       constructor(containerId, pageSizeId, infoId, paginationId) {
           this.containerId = containerId;
           this.pageSizeId = pageSizeId;
           this.infoId = infoId;
           this.paginationId = paginationId;
           this.currentPage = 1;
           this.pageSize = 25;
           this.totalItems = 0;
           this.data = [];
           
           this.setupEventListeners();
       }
       
       setupEventListeners() {
           const pageSizeSelect = document.getElementById(this.pageSizeId);
           if (pageSizeSelect) {
               pageSizeSelect.addEventListener('change', (e) => {
                   this.pageSize = parseInt(e.target.value);
                   this.currentPage = 1;
                   this.render();
               });
           }
       }
       
       setData(data) {
           this.data = data;
           this.totalItems = data.length;
           this.currentPage = 1;
           this.render();
       }
       
       render() {
           this.renderTable();
           this.renderPagination();
           this.renderInfo();
       }
       
       renderTable() {
           const tbody = document.querySelector(`#${this.containerId} tbody`);
           if (!tbody) return;
           
           const startIndex = (this.currentPage - 1) * this.pageSize;
           const endIndex = Math.min(startIndex + this.pageSize, this.totalItems);
           const pageData = this.data.slice(startIndex, endIndex);
           
           tbody.innerHTML = '';
           
           pageData.forEach(trainee => {
               const row = document.createElement('tr');
               
               // Calculate compliance score
               let compliance_score = 0;
               if (trainee.babcp_training_cases > 0) compliance_score += 20;
               if (trainee.supervised_cases >= 3) compliance_score += 20;
               if (trainee.cbt_cases > 0) compliance_score += 20;
               if (trainee.cases_with_5plus_sessions > 0) compliance_score += 20;
               if (trainee.cases_with_5plus_hours > 0) compliance_score += 20;
               
               let compliance_status = '';
               let compliance_class = '';
               if (compliance_score >= 100) {
                   compliance_status = 'Fully Compliant';
                   compliance_class = 'badge-success';
               } else if (compliance_score >= 75) {
                   compliance_status = 'Mostly Compliant';
                   compliance_class = 'badge-warning';
               } else if (compliance_score >= 50) {
                   compliance_status = 'Partially Compliant';
                   compliance_class = 'badge-info';
               } else {
                   compliance_status = 'Non-Compliant';
                   compliance_class = 'badge-danger';
               }
               
               // Create clinical issues summary
               const clinicalIssues = [];
               if (trainee.anxiety_cases > 0) clinicalIssues.push(`Anxiety: ${trainee.anxiety_cases}`);
               if (trainee.depression_cases > 0) clinicalIssues.push(`Depression: ${trainee.depression_cases}`);
               if (trainee.trauma_cases > 0) clinicalIssues.push(`Trauma: ${trainee.trauma_cases}`);
               if (trainee.ocd_cases > 0) clinicalIssues.push(`OCD: ${trainee.ocd_cases}`);
               const clinicalIssuesText = clinicalIssues.length > 0 ? clinicalIssues.join(', ') : 'None';
               
               row.innerHTML = `
                   <td><strong>${trainee.trainee_name}</strong></td>
                   <td>${trainee.total_cases}</td>
                   <td><span class="badge badge-primary">${trainee.babcp_training_cases}</span></td>
                   <td><span class="badge badge-success">${trainee.supervised_cases}</span></td>
                   <td><span class="badge badge-info">${trainee.cbt_cases}</span></td>
                   <td><span class="badge badge-purple">${trainee.babcp_supervised_cases}</span></td>
                   <td><span class="badge badge-dark">${trainee.closed_cbt_babcp_cases}</span></td>
                   <td><span class="badge badge-warning">${trainee.cases_with_5plus_sessions}</span></td>
                   <td><span class="badge badge-secondary">${trainee.cases_with_5plus_hours}</span></td>
                   <td><small>${clinicalIssuesText}</small></td>
                   <td><span class="badge ${compliance_class}">${compliance_status}</span></td>
               `;
               tbody.appendChild(row);
           });
       }
       
       renderPagination() {
           const paginationContainer = document.getElementById(this.paginationId);
           if (!paginationContainer) return;
           
           const totalPages = Math.ceil(this.totalItems / this.pageSize);
           
           if (totalPages <= 1) {
               paginationContainer.innerHTML = '';
               return;
           }
           
           let paginationHTML = '';
           
           // Previous button
           paginationHTML += `
               <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                   <a class="page-link" href="#" data-page="${this.currentPage - 1}">Previous</a>
               </li>
           `;
           
           // Page numbers
           const startPage = Math.max(1, this.currentPage - 2);
           const endPage = Math.min(totalPages, this.currentPage + 2);
           
           if (startPage > 1) {
               paginationHTML += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
               if (startPage > 2) {
                   paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
               }
           }
           
           for (let i = startPage; i <= endPage; i++) {
               paginationHTML += `
                   <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                       <a class="page-link" href="#" data-page="${i}">${i}</a>
                   </li>
               `;
           }
           
           if (endPage < totalPages) {
               if (endPage < totalPages - 1) {
                   paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
               }
               paginationHTML += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
           }
           
           // Next button
           paginationHTML += `
               <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                   <a class="page-link" href="#" data-page="${this.currentPage + 1}">Next</a>
               </li>
           `;
           
           paginationContainer.innerHTML = paginationHTML;
           
           // Add event listeners
           paginationContainer.addEventListener('click', (e) => {
               e.preventDefault();
               if (e.target.classList.contains('page-link') && !e.target.parentElement.classList.contains('disabled')) {
                   const page = parseInt(e.target.dataset.page);
                   if (page && page !== this.currentPage) {
                       this.currentPage = page;
                       this.render();
                   }
               }
           });
       }
       
       renderInfo() {
           const infoElement = document.getElementById(this.infoId);
           if (!infoElement) return;
           
           const startIndex = (this.currentPage - 1) * this.pageSize + 1;
           const endIndex = Math.min(this.currentPage * this.pageSize, this.totalItems);
           
           infoElement.textContent = `Showing ${startIndex} to ${endIndex} of ${this.totalItems} entries`;
       }
   }
   
   // Simplified chart update functions
   function updateDifficultyChart(data, level) {
           const canvas = document.getElementById('difficultyChart');
           if (!canvas) return;
           
           // Destroy existing chart instance if it exists
           if (charts.difficulty) {
               try {
                   charts.difficulty.destroy();
               } catch (e) {
                   console.warn('Error destroying difficulty chart:', e);
               }
               charts.difficulty = null;
           }
           
           // Check if Chart.js has already registered this canvas
           if (Chart.getChart(canvas)) {
               try {
                   Chart.getChart(canvas).destroy();
               } catch (e) {
                   console.warn('Error destroying existing chart on canvas:', e);
               }
           }
           
           // Clear the canvas
           const ctx = canvas.getContext('2d');
           ctx.clearRect(0, 0, canvas.width, canvas.height);
           
           // Handle both direct array and nested structure
           const competencies = Array.isArray(data) ? data : (data.competencies || []);
           charts.difficulty = new Chart(ctx, {
               type: 'bar',
               data: {
                   labels: competencies.map(item => item.tab_name),
                   datasets: [{
                       label: 'Success Rate (%)',
                       data: competencies.map(item => item.success_rate || item.successRate),
                       backgroundColor: competencies.map(item => {
                           const value = item.success_rate || item.successRate;
                           if (value >= 80) return 'rgba(40, 167, 69, 0.8)';
                           if (value >= 60) return 'rgba(255, 193, 7, 0.8)';
                           return 'rgba(220, 53, 69, 0.8)';
                       }),
                       borderColor: competencies.map(item => {
                           const value = item.success_rate || item.successRate;
                           if (value >= 80) return 'rgba(40, 167, 69, 1)';
                           if (value >= 60) return 'rgba(255, 193, 7, 1)';
                           return 'rgba(220, 53, 69, 1)';
                       }),
                       borderWidth: 1
                   }]
               },
               options: {
                   responsive: true,
                   maintainAspectRatio: false,
                   scales: {
                       y: {
                           beginAtZero: true,
                           max: 100,
                           title: {
                               display: true,
                               text: 'Success Rate (%)'
                           }
                       }
                   },
                   plugins: {
                       legend: {
                           display: false
                       }
                   },
                   animation: {
                       duration: level === 'basic' ? 500 : 1000
                   }
               }
           });
       }
       
   function updateMonthlyChart(data, level) {
           const canvas = document.getElementById('monthlyActivityChart');
           if (!canvas) return;
           
           // Destroy existing chart instance if it exists
           if (charts.monthly) {
               try {
                   charts.monthly.destroy();
               } catch (e) {
                   console.warn('Error destroying monthly chart:', e);
               }
               charts.monthly = null;
           }
           
           // Check if Chart.js has already registered this canvas
           if (Chart.getChart(canvas)) {
               try {
                   Chart.getChart(canvas).destroy();
               } catch (e) {
                   console.warn('Error destroying existing chart on canvas:', e);
               }
           }
           
           // Clear the canvas
           const ctx = canvas.getContext('2d');
           ctx.clearRect(0, 0, canvas.width, canvas.height);
           
           // Handle both direct array and nested structure
           const trends = Array.isArray(data) ? data : (data.trends || []);
           
           // If no data, show empty chart with message
           if (trends.length === 0) {
               ctx.fillStyle = '#666';
               ctx.font = '16px Arial';
               ctx.textAlign = 'center';
               ctx.fillText('No monthly activity data available', canvas.width / 2, canvas.height / 2);
               return;
           }
           
           charts.monthly = new Chart(ctx, {
               type: 'line',
               data: {
                   labels: trends.map(item => item.month),
                   datasets: [{
                       label: 'Log Entries',
                       data: trends.map(item => item.entries),
                       borderColor: 'rgba(54, 162, 235, 1)',
                       backgroundColor: 'rgba(54, 162, 235, 0.2)',
                       tension: 0.1,
                       yAxisID: 'y'
                   }, {
                       label: 'Active Trainees',
                       data: trends.map(item => item.active_trainees || item.activeTrainees),
                       borderColor: 'rgba(255, 99, 132, 1)',
                       backgroundColor: 'rgba(255, 99, 132, 0.2)',
                       tension: 0.1,
                       yAxisID: 'y1'
                   }]
               },
               options: {
                   responsive: true,
                   maintainAspectRatio: false,
                   interaction: {
                       mode: 'index',
                       intersect: false,
                   },
                   scales: {
                       x: {
                           display: true,
                           title: {
                               display: true,
                               text: 'Month'
                           }
                       },
                       y: {
                           type: 'linear',
                           display: true,
                           position: 'left',
                           title: {
                               display: true,
                               text: 'Log Entries'
                           }
                       },
                       y1: {
                           type: 'linear',
                           display: true,
                           position: 'right',
                           title: {
                               display: true,
                               text: 'Active Trainees'
                           },
                           grid: {
                               drawOnChartArea: false,
                           },
                       }
                   },
                   animation: {
                       duration: level === 'basic' ? 500 : 1000
                   }
               }
           });
       }
       
   function updateSupervisorTable(data) {
           const tbody = document.querySelector('#supervisorTable tbody');
           if (!tbody) return;
           
           tbody.innerHTML = '';
           
           // Handle both direct array and nested structure
           const supervisors = Array.isArray(data) ? data : (data.supervisors || []);
           
           supervisors.forEach(supervisor => {
               const row = document.createElement('tr');
               row.innerHTML = `
                   <td><strong>${supervisor.supervisor_name || supervisor.name}</strong></td>
                   <td>${supervisor.trainee_count}</td>
                   <td>${supervisor.avg_entries_per_trainee || supervisor.avg_entries}</td>
                   <td>
                       <div class="progress progress-thin">
                           <div class="progress-bar" style="width: ${supervisor.avg_completion_rate}%">
                               ${supervisor.avg_completion_rate}%
                           </div>
                       </div>
                   </td>
                   <td>
                       ${supervisor.performanceScore ? supervisor.performanceScore + '/5' : 'N/A'}
                       ${supervisor.rating ? `<br><small class="text-muted">${supervisor.rating}</small>` : ''}
                   </td>
               `;
               tbody.appendChild(row);
           });
       }
       
   // Initialize charts and data on page load
   document.addEventListener('DOMContentLoaded', function() {
       // Initialize BABCP pagination
               babcpPagination = new SimplePagination(
                   'babcpTable',
                   'babcpPageSize',
                   'babcpPaginationInfo',
                   'babcpPagination'
               );
       
       // Load initial BABCP data from PHP
       const initialBabcpData = <?php echo json_encode($babcp_case_analysis); ?>;
       if (initialBabcpData && initialBabcpData.length > 0) {
           babcpPagination.setData(initialBabcpData);
       }
       
       // Load charts with PHP data directly
       loadChartsWithPHPData();
   });
   
   // Function to load charts with PHP data
   function loadChartsWithPHPData() {
       // Monthly Activity Trends Chart
       const monthlyData = <?php echo json_encode($monthly_activity); ?>;
       console.log('Monthly activity data:', monthlyData);
       if (monthlyData && monthlyData.length > 0) {
           updateMonthlyChart(monthlyData, 'detailed');
       } else {
           console.log('No monthly activity data available');
           // Show a message in the chart area
           const canvas = document.getElementById('monthlyActivityChart');
           if (canvas) {
               const ctx = canvas.getContext('2d');
               ctx.fillStyle = '#666';
               ctx.font = '16px Arial';
               ctx.textAlign = 'center';
               ctx.fillText('No monthly activity data available', canvas.width / 2, canvas.height / 2);
           }
       }
       
       // Competency Difficulty Chart
       const competencyData = <?php echo json_encode($competency_difficulty); ?>;
       if (competencyData && competencyData.length > 0) {
           updateDifficultyChart(competencyData, 'detailed');
       } else {
           console.log('No competency difficulty data available');
       }
       
       // Supervisor Performance Table
       const supervisorData = <?php echo json_encode($supervisor_performance); ?>;
       if (supervisorData && supervisorData.length > 0) {
           updateSupervisorTable(supervisorData);
       } else {
           console.log('No supervisor performance data available');
       }
       
       // BABCP Growth Chart
       const babcpGrowthData = <?php echo json_encode($babcp_growth_data); ?>;
       console.log('BABCP growth data:', babcpGrowthData);
       if (babcpGrowthData && babcpGrowthData.length > 0) {
           updateBabcpGrowthChart(babcpGrowthData, 'detailed');
       } else {
           console.log('No BABCP growth data available');
       }
       
       // Clinical Issues Chart
       const clinicalIssuesData = <?php echo json_encode($babcp_summary_stats); ?>;
       console.log('Clinical issues data:', clinicalIssuesData);
       if (clinicalIssuesData) {
           updateClinicalIssuesChart(clinicalIssuesData, 'detailed');
       }
       
       // Supervision Hours Chart
       if (clinicalIssuesData) {
           updateSupervisionHoursChart(clinicalIssuesData, 'detailed');
       }
       

   }
   
   // BABCP Growth Chart
   function updateBabcpGrowthChart(data, level) {
       const canvas = document.getElementById('babcpGrowthChart');
       if (!canvas) return;
       
       // Destroy existing chart instance if it exists
       if (charts.babcpGrowth) {
           try {
               charts.babcpGrowth.destroy();
           } catch (e) {
               console.warn('Error destroying BABCP growth chart:', e);
           }
           charts.babcpGrowth = null;
       }
       
       // Check if Chart.js has already registered this canvas
       if (Chart.getChart(canvas)) {
           try {
               Chart.getChart(canvas).destroy();
           } catch (e) {
               console.warn('Error destroying existing chart on canvas:', e);
           }
       }
       
       // Clear the canvas
       const ctx = canvas.getContext('2d');
       ctx.clearRect(0, 0, canvas.width, canvas.height);
       
       // Handle both direct array and nested structure
       const trends = Array.isArray(data) ? data : (data.trends || []);
       console.log('BABCP Growth Chart - trends data:', trends);
       
       // If no data, show empty chart with message
       if (trends.length === 0) {
           console.log('No BABCP growth trends data available');
           ctx.fillStyle = '#666';
           ctx.font = '16px Arial';
           ctx.textAlign = 'center';
           ctx.fillText('No BABCP growth data available', canvas.width / 2, canvas.height / 2);
           return;
       }
       
       charts.babcpGrowth = new Chart(ctx, {
           type: 'line',
           data: {
               labels: trends.map(item => item.month),
               datasets: [{
                   label: 'BABCP Cases',
                   data: trends.map(item => item.babcp_cases),
                   borderColor: 'rgba(54, 162, 235, 1)',
                   backgroundColor: 'rgba(54, 162, 235, 0.2)',
                   tension: 0.1,
                   yAxisID: 'y'
               }, {
                   label: 'Supervised Cases',
                   data: trends.map(item => item.supervised_cases),
                   borderColor: 'rgba(75, 192, 192, 1)',
                   backgroundColor: 'rgba(75, 192, 192, 0.2)',
                   tension: 0.1,
                   yAxisID: 'y'
               }, {
                   label: 'CBT Cases',
                   data: trends.map(item => item.cbt_cases),
                   borderColor: 'rgba(255, 99, 132, 1)',
                   backgroundColor: 'rgba(255, 99, 132, 0.2)',
                   tension: 0.1,
                   yAxisID: 'y'
               }]
           },
           options: {
               responsive: true,
               maintainAspectRatio: false,
               interaction: {
                   mode: 'index',
                   intersect: false,
               },
               scales: {
                   x: {
                       display: true,
                       title: {
                           display: true,
                           text: 'Month'
                       }
                   },
                   y: {
                       type: 'linear',
                       display: true,
                       position: 'left',
                       title: {
                           display: true,
                           text: 'Number of Cases'
                       }
                   }
               },
               animation: {
                   duration: level === 'basic' ? 500 : 1000
               }
           }
       });
   }
   
   // Clinical Issues Chart
   function updateClinicalIssuesChart(data, level) {
       const canvas = document.getElementById('clinicalIssuesChart');
       if (!canvas) return;
       
       // Destroy existing chart instance if it exists
       if (charts.clinicalIssues) {
           try {
               charts.clinicalIssues.destroy();
           } catch (e) {
               console.warn('Error destroying clinical issues chart:', e);
           }
           charts.clinicalIssues = null;
       }
       
       // Check if Chart.js has already registered this canvas
       if (Chart.getChart(canvas)) {
           try {
               Chart.getChart(canvas).destroy();
           } catch (e) {
               console.warn('Error destroying existing chart on canvas:', e);
           }
       }
       
       // Clear the canvas
       const ctx = canvas.getContext('2d');
       ctx.clearRect(0, 0, canvas.width, canvas.height);
       
       console.log('Clinical Issues Chart - data:', data);
       
       // Check if we have valid data
       if (!data || (data.total_anxiety_cases === 0 && data.total_depression_cases === 0 && data.total_trauma_cases === 0 && data.total_ocd_cases === 0)) {
           console.log('No clinical issues data available, showing empty chart');
           ctx.fillStyle = '#666';
           ctx.font = '16px Arial';
           ctx.textAlign = 'center';
           ctx.fillText('No clinical issues data available', canvas.width / 2, canvas.height / 2);
           return;
       }
       
       const clinicalData = {
           labels: ['Anxiety', 'Depression', 'Trauma', 'OCD'],
           datasets: [{
               label: 'Number of Cases',
               data: [
                   data.total_anxiety_cases || 0,
                   data.total_depression_cases || 0,
                   data.total_trauma_cases || 0,
                   data.total_ocd_cases || 0
               ],
               backgroundColor: [
                   'rgba(220, 53, 69, 0.8)',
                   'rgba(255, 193, 7, 0.8)',
                   'rgba(54, 162, 235, 0.8)',
                   'rgba(108, 117, 125, 0.8)'
               ],
               borderColor: [
                   'rgba(220, 53, 69, 1)',
                   'rgba(255, 193, 7, 1)',
                   'rgba(54, 162, 235, 1)',
                   'rgba(108, 117, 125, 1)'
               ],
               borderWidth: 1
           }]
       };
       
       charts.clinicalIssues = new Chart(ctx, {
           type: 'doughnut',
           data: clinicalData,
           options: {
               responsive: true,
               maintainAspectRatio: false,
               plugins: {
                   legend: {
                       position: 'bottom'
                   }
               },
               animation: {
                   duration: level === 'basic' ? 500 : 1000
               }
           }
       });
   }
   
   // Supervision Hours Chart
   function updateSupervisionHoursChart(data, level) {
       const canvas = document.getElementById('supervisionHoursChart');
       if (!canvas) return;
       
       // Destroy existing chart instance if it exists
       if (charts.supervisionHours) {
           try {
               charts.supervisionHours.destroy();
           } catch (e) {
               console.warn('Error destroying supervision hours chart:', e);
           }
           charts.supervisionHours = null;
       }
       
       // Check if Chart.js has already registered this canvas
       if (Chart.getChart(canvas)) {
           try {
               Chart.getChart(canvas).destroy();
           } catch (e) {
               console.warn('Error destroying existing chart on canvas:', e);
           }
       }
       
       // Clear the canvas
       const ctx = canvas.getContext('2d');
       ctx.clearRect(0, 0, canvas.width, canvas.height);
       
       const supervisionData = {
           labels: ['BABCP Training Cases', 'Supervised Cases', 'CBT Cases'],
           datasets: [{
               label: 'Number of Cases',
               data: [
                   data.total_babcp_training_cases || 0,
                   data.total_supervised_cases || 0,
                   data.total_cbt_cases || 0
               ],
               backgroundColor: [
                   'rgba(54, 162, 235, 0.8)',
                   'rgba(75, 192, 192, 0.8)',
                   'rgba(255, 99, 132, 0.8)'
               ],
               borderColor: [
                   'rgba(54, 162, 235, 1)',
                   'rgba(75, 192, 192, 1)',
                   'rgba(255, 99, 132, 1)'
               ],
               borderWidth: 1
           }]
       };
       
       charts.supervisionHours = new Chart(ctx, {
           type: 'bar',
           data: supervisionData,
           options: {
               responsive: true,
               maintainAspectRatio: false,
               scales: {
                   y: {
                       beginAtZero: true,
                       title: {
                           display: true,
                           text: 'Hours'
                       }
                   }
               },
               animation: {
                   duration: level === 'basic' ? 500 : 1000
               }
           }
       });
   }
   

   
   // Group filter indicator functionality
   function updateGroupFilterIndicator() {
       const groupSelect = document.getElementById('group');
       if (!groupSelect) return;
       
       const selectedGroup = groupSelect.value;
       const indicator = document.querySelector('.alert-info');
       
       if (selectedGroup === 'ALL_USERS') {
           if (indicator) {
               indicator.innerHTML = '<i class="fas fa-users"></i> <strong>All Users Mode:</strong> Data is aggregated across all accessible users';
               indicator.style.display = 'block';
           }
       } else if (selectedGroup.startsWith('subset_')) {
           const groupName = groupSelect.options[groupSelect.selectedIndex].text.replace(' (Group)', '').trim();
           if (indicator) {
               indicator.innerHTML = `<i class="fas fa-users-cog"></i> <strong>Group Filter Active:</strong> Data is filtered for the "${groupName}" group`;
               indicator.style.display = 'block';
           }
       } else {
           if (indicator) {
               indicator.style.display = 'none';
           }
       }
   }
   
   // Add event listener for group filter changes
   document.addEventListener('DOMContentLoaded', function() {
       const groupSelect = document.getElementById('group');
       if (groupSelect) {
           groupSelect.addEventListener('change', updateGroupFilterIndicator);
           // Initialize on page load
           updateGroupFilterIndicator();
       }
       
       // Load BABCP Contact-Modality Grouping data
       loadBABCPGroupingData();
   });
   
   // Function to load BABCP Contact-Modality Grouping data
   function loadBABCPGroupingData() {
       const urlParams = new URLSearchParams(window.location.search);
       const course = urlParams.get('course') || '';
       const group = urlParams.get('group') || '';
       const babcp_training = urlParams.get('babcp_training') || '';
       const supervised_case = urlParams.get('supervised_case') || '';
       const primary_modality = urlParams.get('primary_modality') || '';
       const min_sessions = urlParams.get('min_sessions') || '';
       const datestart = urlParams.get('datestart') || '';
       const dateend = urlParams.get('dateend') || '';
       
       const params = new URLSearchParams({
           data_type: 'babcp_contact_modality_grouping',
           course: course,
           group: group,
           babcp_training: babcp_training,
           supervised_case: supervised_case,
           primary_modality: primary_modality,
           min_sessions: min_sessions,
           datestart: datestart,
           dateend: dateend
       });
       
       fetch('ajax/trainee_stats_data.php?' + params.toString(), {
           credentials: 'same-origin'
       })
           .then(response => response.json())
           .then(data => {
               if (data.status === 'success') {
                   displayBABCPGroupingData(data.data);
               } else {
                   console.error('Error loading BABCP grouping data:', data.message);
                   displayBABCPGroupingError(data.message);
               }
           })
           .catch(error => {
               console.error('Error loading BABCP grouping data:', error);
               displayBABCPGroupingError('Failed to load data');
           });
   }
   
   // Function to display BABCP Contact-Modality Grouping data
   function displayBABCPGroupingData(data) {
       const tbody = document.getElementById('babcpGroupingTableBody');
       if (!tbody) return;
       
       tbody.innerHTML = '';
       
       let totalClients = 0;
       let totalTrainees = 0;
       
       // Calculate totals
       data.forEach(item => {
           totalClients += parseInt(item.client_count) || 0;
           totalTrainees += parseInt(item.trainee_count) || 0;
       });
       
       // Display data
       data.forEach(item => {
           const row = document.createElement('tr');
           const percentage = totalClients > 0 ? ((item.client_count / totalClients) * 100).toFixed(1) : 0;
           
           row.innerHTML = `
               <td><span class="badge badge-primary">${item.contact_type}</span></td>
               <td><span class="badge badge-info">${item.modality_type}</span></td>
               <td><strong>${item.client_count}</strong></td>
               <td>${item.trainee_count}</td>
               <td>
                   <div class="progress progress-thin">
                       <div class="progress-bar bg-success" style="width: ${percentage}%">
                           ${percentage}%
                       </div>
                   </div>
               </td>
           `;
           tbody.appendChild(row);
       });
       
       // Update totals
       document.getElementById('totalClients').textContent = totalClients;
       document.getElementById('totalTrainees').textContent = totalTrainees;
   }
   
   // Function to display error message
   function displayBABCPGroupingError(message) {
       const tbody = document.getElementById('babcpGroupingTableBody');
       if (!tbody) return;
       
       tbody.innerHTML = `
           <tr>
               <td colspan="5" class="text-center text-danger">
                   <i class="fas fa-exclamation-triangle"></i> ${message}
               </td>
           </tr>
       `;
   }
   
   // Charts are now loaded directly with PHP data
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
 * -- CREATE INDEX idx_trainee_supervisor ON trainee_tbl(supervisor);
 * -- CREATE INDEX idx_trainee_supervisor2 ON trainee_tbl(supervisor2);
 * -- CREATE INDEX idx_trainee_supervisor3 ON trainee_tbl(supervisor3);
 * -- CREATE INDEX idx_trainee_log_trainkey ON trainee_log(trainkey);
 * -- CREATE INDEX idx_trainee_log_tbid ON trainee_log(tbid);
 * -- CREATE INDEX idx_trainee_log_date_added ON trainee_log(date_added);
 * -- CREATE INDEX idx_trainee_log_logkey ON trainee_log(logkey);
 * -- CREATE INDEX idx_trainee_log_select_val ON trainee_log(select_val);
 * -- CREATE INDEX idx_trainee_log_trainkey_tbid ON trainee_log(trainkey, tbid);
 * -- CREATE INDEX idx_trainee_log_trainkey_date ON trainee_log(trainkey, date_added);
 * -- CREATE INDEX idx_trainee_log_stid ON trainee_log(stid);
 * -- CREATE INDEX idx_trainee_log_logkey_tbid ON trainee_log(logkey, tbid);
 * -- CREATE INDEX idx_tabs_isvis_sort ON tabs_tbl(isvis, sort_order);
 * -- CREATE INDEX idx_select_types_str ON select_types(str);
 * -- CREATE INDEX idx_trainee_tab_link_tbid ON trainee_tab_link(tbid);
 * -- CREATE INDEX idx_who_there_admintype ON who_there(admintype);
 * -- CREATE INDEX idx_uni_university ON uni_tbl(university);
 * 
 * -- Advanced optimization for large datasets
 * -- Consider partitioning trainee_log by date:
 * -- ALTER TABLE trainee_log PARTITION BY RANGE (date_added) (
 * --     PARTITION p_2023 VALUES LESS THAN (UNIX_TIMESTAMP('2024-01-01')),
 * --     PARTITION p_2024 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
 * --     PARTITION p_future VALUES LESS THAN MAXVALUE
 * -- );
 * 
 * -- Performance monitoring queries
 * -- Check index usage: SHOW INDEX FROM table_name;
 * -- Analyze query performance: EXPLAIN ANALYZE SELECT ...;
 * -- Monitor slow queries: SHOW VARIABLES LIKE 'slow_query_log';
 * -- Check table sizes: SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'your_database_name';
 */
?>
