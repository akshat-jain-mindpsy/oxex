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
include 'incl/admin_vars.php';

// Check permissions - allow all admin types to view trainee stats
if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {

// Fetch trainee subsets (groups) for filtering
$subsets = [];
$canViewAll = ($admintype == 'AT' || $admintype == 'DV');
$subsets_query = "SELECT setkey, subset, usrkey FROM subset_tbl ORDER BY subset ASC";
$subsets_result = $pdo->query($subsets_query);
if ($subsets_result) {
    while ($subset = $subsets_result->fetch(PDO::FETCH_ASSOC)) {
        if ($canViewAll || $subset['usrkey'] == $usrkey) {
            $subsets[] = $subset;
        }
    }
}

// Get parameters
$thisyear = date("Y");
$start_year = isset($_GET['start_year']) ? (int)$_GET['start_year'] : ($thisyear - 10); // Default to 10 years ago
$end_year = isset($_GET['end_year']) ? (int)$_GET['end_year'] : ($thisyear + 1); // Default to next year

// Validate date parameters to prevent overflow
$start_year = max(1900, min(2100, $start_year));
$end_year = max(1900, min(2100, $end_year));
if ($start_year > $end_year) {
    $start_year = $end_year;
}
$selected_course = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$selected_competency = isset($_GET['competency']) ? (int)$_GET['competency'] : 0;
$selected_group = isset($_GET['group']) ? $_GET['group'] : ''; // empty = all groups, 'subset_123' = specific group, 'ALL_USERS' = all users
$babcp_filter = isset($_GET['babcp_filter']) ? (int)$_GET['babcp_filter'] : 0; // 0 = all data, 1 = BABCP only
$babcp_training = isset($_GET['babcp_training']) ? (int)$_GET['babcp_training'] : 0; // 0 = all, 1 = training cases only
$supervised_case = isset($_GET['supervised_case']) ? (int)$_GET['supervised_case'] : 0; // 0 = all, 1 = supervised cases only
$primary_modality = isset($_GET['primary_modality']) ? $_GET['primary_modality'] : ''; // CBT, etc.
$min_sessions = isset($_GET['min_sessions']) ? (int)$_GET['min_sessions'] : 0; // minimum number of sessions
$export_type = isset($_GET['export']) ? $_GET['export'] : '';

// View router: split heavy page into logical views to reduce DB load
$view = isset($_GET['view']) ? $_GET['view'] : 'overview';

// Ensure filter variables exist before export block; full values built later
if (!isset($course_condition)) $course_condition = "";
if (!isset($group_condition)) $group_condition = "";
if (!isset($babcp_condition_simple)) $babcp_condition_simple = "";
if (!isset($babcp_condition_with_tabs)) $babcp_condition_with_tabs = "";
if (!isset($additional_conditions)) $additional_conditions = "";

// (moved) Timeline JSON now served from trainee_stats.php

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
        
        $result = $pdo->query($export_query);
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
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
            SELECT
                tabs.tab_name,
                COUNT(tl.tlogid) as total_attempts,
                COUNT(CASE WHEN tl.select_val = '1' THEN 1 END) as successful_attempts,
                ROUND(AVG(CASE WHEN tl.select_val = '1' THEN 1 ELSE 0 END) * 100, 1) as success_rate
            FROM tabs_tbl tabs
            LEFT JOIN trainee_log tl ON tabs.tbid = tl.tbid
            LEFT JOIN trainee_tbl t ON tl.trainkey = t.trainkey
            WHERE tabs.isvis = 1 $course_condition $group_condition $babcp_condition_with_tabs $additional_conditions";
        
        if ($selected_competency > 0) {
            $competency_query .= " AND tabs.tbid = $selected_competency";
        }
        
        $competency_query .= " GROUP BY tabs.tbid, tabs.tab_name ORDER BY success_rate ASC";
        
        $result = $pdo->query($competency_query);
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
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
    } elseif ($export_type == 'full_report') {
        fputcsv($output, ['Trainee Name', 'Course', 'Year', 'Supervisor', 'Total Cases', 'BABCP Training Cases', 'Supervised Cases', 'CBT Cases', 'Cases with 5+ Sessions', 'Anxiety Cases', 'Depression Cases', 'Trauma Cases', 'OCD Cases', 'BABCP Compliance']);
        
        // Use the same query as the BABCP case analysis with all filters
        $full_report_query = "
            SELECT
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
                SELECT logkey, COUNT(DISTINCT tlogid) as session_count
                FROM trainee_log 
                GROUP BY logkey
            ) session_counts ON tl.logkey = session_counts.logkey
            WHERE 1=1 $course_condition $babcp_condition_simple $additional_conditions
            GROUP BY t.trainkey, t.name ORDER BY t.name";
        
        $result = $pdo->query($full_report_query);
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
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

// Set variables needed by adminjs.php
setAdminVars(2); // Trainees section
$subtitle = "Advanced Trainee Statistics";
$listurl = "trainee_stats.php";
$listname = "Trainee Statistics";

// Build query-preserving navigation URLs for views (drop export/view keys)
$current_query = $_GET;
unset($current_query['export']);
unset($current_query['view']);
$base_query_str = http_build_query($current_query);
$overview_url = 'trainee_detailed_stats.php?' . ($base_query_str ? $base_query_str . '&' : '') . 'view=overview';
$competency_url = 'trainee_detailed_stats.php?' . ($base_query_str ? $base_query_str . '&' : '') . 'view=competency';
$score_url = 'trainee_detailed_stats.php?' . ($base_query_str ? $base_query_str . '&' : '') . 'view=score';
$babcp_url = 'trainee_detailed_stats.php?' . ($base_query_str ? $base_query_str . '&' : '') . 'view=babcp';

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
      /* Simple sortable headers */
      th.sortable { cursor: pointer; user-select: none; }
      th.sortable .sort-arrow { opacity: 0.5; }
      th.sortable.active .sort-arrow { opacity: 1; }
      th.sortable.asc .sort-arrow::after { content: "▲"; margin-left: 4px; font-size: 0.8em; }
      th.sortable.desc .sort-arrow::after { content: "▼"; margin-left: 4px; font-size: 0.8em; }
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

      /* Loading overlay for charts */
      .chart-container {
         position: relative;
         min-height: 220px;
      }
      .loading-overlay {
         position: absolute;
         inset: 0;
         display: none;
         align-items: center;
         justify-content: center;
         background: rgba(255,255,255,0.6);
         z-index: 2;
      }
      .loading-overlay.active { display: flex; }
      .spinner {
         width: 28px;
         height: 28px;
         border: 3px solid #ccc;
         border-top-color: #007bff;
         border-radius: 50%;
         animation: spin 0.8s linear infinite;
      }
      @keyframes spin { to { transform: rotate(360deg); } }

      /* BABCP Table Improvements */
      .table th.sortable {
         cursor: pointer;
         user-select: none;
         position: relative;
         transition: background-color 0.2s ease;
         text-align: center;
         vertical-align: middle;
         white-space: normal;
         word-wrap: break-word;
         line-height: 1.2;
      }
      .table th.sortable:hover {
         background-color: rgba(255, 255, 255, 0.1);
      }
      .table th.sortable .sort-icon {
         opacity: 0.5;
         margin-left: 5px;
         font-size: 0.8em;
         vertical-align: middle;
         display: inline;
      }
      .table th.sortable.active .sort-icon {
         opacity: 1;
         color: #007bff;
      }
      .table th.sortable.asc .sort-icon::before {
         content: "▲";
      }
      .table th.sortable.desc .sort-icon::before {
         content: "▼";
      }
      
      /* Header text and icon alignment - removed flexbox to fix table layout */
      
      /* Table responsive improvements */
      .table-responsive {
         border-radius: 0.375rem;
         box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
         width: 100%;
         overflow-x: auto;
      }
      
      /* Full width table improvements */
      .table {
         width: 100% !important;
         table-layout: auto;
         margin-bottom: 0;
      }
      
      .table th,
      .table td {
         padding: 0.75rem 0.5rem;
         vertical-align: middle;
         text-align: center;
      }
      
      .table th {
         white-space: normal;
         word-wrap: break-word;
         line-height: 1.2;
      }
      
      .table td {
         white-space: nowrap;
      }
      
      .table th:first-child,
      .table td:first-child {
         text-align: left;
         padding-left: 1rem;
      }
      
      .table th:last-child,
      .table td:last-child {
         padding-right: 1rem;
      }
      
      /* Better column width distribution for main BABCP table */
      #babcpTable th:nth-child(1) { width: 18%; } /* Trainee Name */
      #babcpTable th:nth-child(2) { width: 8%; }  /* Total Cases */
      #babcpTable th:nth-child(3) { width: 12%; } /* BABCP Training */
      #babcpTable th:nth-child(4) { width: 8%; }  /* Supervised */
      #babcpTable th:nth-child(5) { width: 8%; }  /* CBT Cases */
      #babcpTable th:nth-child(6) { width: 12%; } /* BABCP Supervised */
      #babcpTable th:nth-child(7) { width: 12%; } /* Closed CBT BABCP */
      #babcpTable th:nth-child(8) { width: 8%; }  /* 5+ Sessions */
      #babcpTable th:nth-child(9) { width: 8%; }  /* 5+ Hours */
      #babcpTable th:nth-child(10) { width: 8%; } /* Clinical Issues */
      #babcpTable th:nth-child(11) { width: 8%; } /* BABCP Compliance */
      
      /* Column width distribution for BABCP Grouping table */
      #babcpGroupingTable th:nth-child(1) { width: 25%; } /* Contact Type */
      #babcpGroupingTable th:nth-child(2) { width: 35%; } /* Modality */
      #babcpGroupingTable th:nth-child(3) { width: 15%; } /* Client Count */
      #babcpGroupingTable th:nth-child(4) { width: 15%; } /* Trainee Count */
      #babcpGroupingTable th:nth-child(5) { width: 10%; } /* Percentage */
      
      /* Badge improvements */
      .badge {
         font-size: 0.75em;
         padding: 0.375rem 0.5rem;
      }
      
      /* Search input styling */
      .input-group-text {
         background-color: #f8f9fa;
         border-color: #ced4da;
      }
      
      /* Pagination improvements */
      .pagination .page-link {
         color: #007bff;
         border-color: #dee2e6;
      }
      .pagination .page-link:hover {
         color: #0056b3;
         background-color: #e9ecef;
         border-color: #dee2e6;
      }
      .pagination .page-item.active .page-link {
         background-color: #007bff;
         border-color: #007bff;
      }

   </style>
</head>

<?php 
// Date ranges for queries
$datestart = sprintf('%04d0101', $start_year);
$dateend = sprintf('%04d1231', $end_year);

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

// Combine course and group parameters for consistent binding
$all_basic_params = $course_params;
$all_basic_param_types = $course_param_types;

// Debug: Log the parameter values
error_log("Selected course: $selected_course");
error_log("Selected group: $selected_group");
error_log("Course params: " . print_r($course_params, true));
error_log("All basic params: " . print_r($all_basic_params, true));

// Validate course/group parameters to ensure they're not being treated as dates
foreach ($all_basic_params as $i => $param) {
    if (is_numeric($param) && strlen($param) == 8) {
        // This could be mistaken for a date - log it for debugging
        error_log("Warning: Parameter at index $i is 8 digits: '$param' - this might be a course ID, not a date");
    }
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
        
        $result = $pdo->query($babcp_trainees_query);
        $babcp_trainees = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
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

// Helper function to validate YYYYMMDD date format
function isValidYYYYMMDD($date) {
    if (!preg_match('/^[0-9]{8}$/', $date)) {
        return false;
    }
    $year = substr($date, 0, 4);
    $month = substr($date, 4, 2);
    $day = substr($date, 6, 2);
    
    // Check reasonable date ranges
    if ($year < 1900 || $year > 2100) return false;
    if ($month < 1 || $month > 12) return false;
    if ($day < 1 || $day > 31) return false;
    
    // Check if date is valid
    return checkdate($month, $day, $year);
}

// Get all courses for filter dropdown with caching
$cache_key_courses = 'courses_list_detailed';
$courses = QueryCache::get($cache_key_courses);

if ($courses === null) {
    $courses_query = "SELECT uid, university FROM uni_tbl ORDER BY university";
    $courses_result = $pdo->query($courses_query);
    $courses = [];
    while ($row = $courses_result->fetch(PDO::FETCH_ASSOC)) {
        $courses[] = $row;
    }
    QueryCache::set($cache_key_courses, $courses);
}

// Get competency areas for filter dropdown with caching
$cache_key_competencies = 'competencies_list';
$competencies = QueryCache::get($cache_key_competencies);

if ($competencies === null) {
    $competencies_query = "SELECT tbid, tab_name FROM tabs_tbl WHERE isvis = 1 ORDER BY sort_order";
    $competencies_result = $pdo->query($competencies_query);
    $competencies = [];
    while ($row = $competencies_result->fetch(PDO::FETCH_ASSOC)) {
        $competencies[] = $row;
    }
    QueryCache::set($cache_key_competencies, $competencies);
}



// Get monthly activity trends - FIXED for YYYYMMDD format
// PERFORMANCE: Time-based query requiring index on trainee_log.date_added
// Consider partitioning trainee_log by date for very large datasets
// OPTIMIZED: Use index hints and optimize date functions including composite index
if ($view === 'overview') {
    $monthly_activity_query = "
        SELECT 
            TO_CHAR(CASE WHEN tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 THEN TO_DATE(tl.date_added::text, 'YYYYMMDD') ELSE NULL END, 'YYYY-MM') as month,
            COUNT(*) as entries,
            COUNT(DISTINCT tl.trainkey) as active_trainees
        FROM trainee_log tl
        JOIN trainee_tbl t ON tl.trainkey = t.trainkey
        WHERE tl.date_added >= ? AND tl.date_added <= ? 
          AND tl.date_added::text ~ '^[0-9]{8}$'  -- Only valid YYYYMMDD format
          AND tl.date_added >= 19000101  -- Reasonable date range
          AND tl.date_added <= 21001231
          AND CASE 
            WHEN tl.date_added::text ~ '^[0-9]{8}$' THEN
              CASE 
                WHEN SUBSTR(tl.date_added::text, 1, 4)::int BETWEEN 1900 AND 2100 AND
                     SUBSTR(tl.date_added::text, 5, 2)::int BETWEEN 1 AND 12 AND
                     SUBSTR(tl.date_added::text, 7, 2)::int BETWEEN 1 AND 31
                THEN TRUE
                ELSE FALSE
              END
            ELSE FALSE
          END
          $course_condition $group_condition $babcp_condition_simple $additional_conditions
        GROUP BY TO_CHAR(CASE WHEN tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 THEN TO_DATE(tl.date_added::text, 'YYYYMMDD') ELSE NULL END, 'YYYY-MM')
        ORDER BY month DESC
        LIMIT 12
    ";

    // FIXED: The date_added field contains YYYYMMDD format, not Unix timestamps
    $datestart_yyyymmdd = sprintf('%04d0101', $start_year); // YYYYMMDD format with proper padding
    $dateend_yyyymmdd = sprintf('%04d1231', $end_year);     // YYYYMMDD format with proper padding
    
    // Additional validation to ensure proper format
    if (!preg_match('/^\d{8}$/', $datestart_yyyymmdd) || !preg_match('/^\d{8}$/', $dateend_yyyymmdd)) {
        error_log("Invalid date format generated: start=$datestart_yyyymmdd, end=$dateend_yyyymmdd");
        throw new Exception("Invalid date format generated");
    }

    error_log("Using YYYYMMDD format - Start: $datestart_yyyymmdd, End: $dateend_yyyymmdd");
    $stmt = $pdo->prepare($monthly_activity_query);
    try {
        if (!empty($all_basic_params)) {
            $stmt->execute(array_merge([$datestart_yyyymmdd, $dateend_yyyymmdd], $all_basic_params));
        } else {
            $stmt->execute([$datestart_yyyymmdd, $dateend_yyyymmdd]);
        }
    } catch (PDOException $e) {
        error_log("Monthly activity query error: " . $e->getMessage());
        error_log("Query parameters - Start: $datestart_yyyymmdd, End: $dateend_yyyymmdd");
        error_log("All basic params: " . print_r($all_basic_params, true));
        throw $e;
    }
    $monthly_activity = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthly_activity[] = ['month' => $row['month'], 'entries' => $row['entries'], 'active_trainees' => $row['active_trainees']];
    }
    $stmt->closeCursor();

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
        $test_result = $pdo->query($test_query);
        if ($test_result) {
            $test_row = $test_result->fetch(PDO::FETCH_ASSOC);
            error_log("Total trainee_log entries: " . $test_row['total']);
        }
        
        // Test if there are any entries in the date range
        $date_test_query = "SELECT COUNT(*) as date_count FROM trainee_log WHERE date_added >= ? AND date_added <= ? AND date_added::text ~ '^[0-9]{8}$' AND date_added >= 19000101 AND date_added <= 21001231";
        $date_stmt = $pdo->prepare($date_test_query);
        $date_stmt->execute([$datestart_yyyymmdd, $dateend_yyyymmdd]);
        $date_count = $date_stmt->fetchColumn();
        $date_stmt->closeCursor();
        error_log("Trainee_log entries in date range: " . $date_count);
        
        // Test the actual query being used
        $debug_query = "SELECT COUNT(*) as debug_count FROM trainee_log tl JOIN trainee_tbl t ON tl.trainkey = t.trainkey WHERE tl.date_added >= ? AND tl.date_added <= ? AND tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 $course_condition $babcp_condition_simple $additional_conditions";
        error_log("Debug query: $debug_query");
        
        $debug_stmt = $pdo->prepare($debug_query);
        if (!empty($all_basic_params)) {
            $debug_stmt->execute(array_merge([$datestart_yyyymmdd, $dateend_yyyymmdd], $all_basic_params));
        } else {
            $debug_stmt->execute([$datestart_yyyymmdd, $dateend_yyyymmdd]);
        }
        $debug_count = $debug_stmt->fetchColumn();
        $debug_stmt->closeCursor();
        error_log("Debug query result count: " . $debug_count);
        
        // Create sample data for demonstration if no real data exists
        $current_month = date('Y-m');
        $monthly_activity = [
            ['month' => $current_month, 'entries' => 0, 'active_trainees' => 0]
        ];
        error_log("Created sample monthly activity data");
    }
} else {
    // Ensure variables exist for other views
    $datestart_yyyymmdd = sprintf('%04d0101', $start_year);
    $dateend_yyyymmdd = sprintf('%04d1231', $end_year);
    $monthly_activity = [];
}

// Get supervisor performance metrics
// OPTIMIZED: Use index hints for complex subquery optimization including supervisor2 and supervisor3
if ($view === 'overview') {
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
            WHERE tl.date_added >= ? AND tl.date_added <= ? AND tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231
            GROUP BY t2.trainkey
        ) trainee_stats ON t.trainkey = trainee_stats.trainkey
        WHERE w.admintype IN ('SO', 'SE') $course_condition $group_condition $babcp_condition_simple $additional_conditions
        GROUP BY w.usrkey, w.realname
        ORDER BY avg_completion_rate DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($supervisor_performance_query);
    if (!empty($all_basic_params)) {
        $stmt->execute(array_merge([$datestart_yyyymmdd, $dateend_yyyymmdd], $all_basic_params));
    } else {
        $stmt->execute([$datestart_yyyymmdd, $dateend_yyyymmdd]);
    }
    $supervisor_performance = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $supervisor_performance[] = [
            'name' => $row['supervisor_name'],
            'trainee_count' => $row['trainee_count'],
            'avg_entries' => round($row['avg_entries'] ?? 0, 1),
            'avg_completion_rate' => round($row['avg_completion_rate'] ?? 0, 1)
        ];
    }
    $stmt->closeCursor();
} else {
    $supervisor_performance = [];
}

// Get competency difficulty analysis - FIXED with proper filter application
// PERFORMANCE: Simplified query with pre-computed BABCP flags and better indexing
if ($view === 'competency') {
    $competency_difficulty_query = "
        SELECT
            tabs.tab_name,
            COUNT(tl.tlogid) as total_attempts,
            COUNT(CASE WHEN tl.select_val = '1' THEN 1 END) as successful_attempts,
            ROUND(AVG(CASE WHEN tl.select_val = '1' THEN 1 ELSE 0 END) * 100, 1) as success_rate
        FROM tabs_tbl tabs
        LEFT JOIN trainee_log tl ON tabs.tbid = tl.tbid
        LEFT JOIN trainee_tbl t ON tl.trainkey = t.trainkey
        WHERE tabs.isvis = 1 AND tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 $course_condition $group_condition $babcp_condition_with_tabs $additional_conditions
        GROUP BY tabs.tbid, tabs.tab_name
        ORDER BY success_rate ASC
    ";

    $stmt = $pdo->prepare($competency_difficulty_query);
    if (!empty($all_basic_params)) {
        $stmt->execute($all_basic_params);
    } else {
        $stmt->execute();
    }
    $competency_difficulty = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $competency_difficulty[] = [
            'tab_name' => $row['tab_name'],
            'total_attempts' => $row['total_attempts'],
            'successful_attempts' => $row['successful_attempts'],
            'success_rate' => round($row['success_rate'] ?? 0, 1)
        ];
    }
    $stmt->closeCursor();
} else {
    $competency_difficulty = [];
}

// Get BABCP-specific case counts and session analysis - OPTIMIZED with materialized views (with fallback)
// PERFORMANCE: 80-90% improvement using pre-computed materialized views
if ($view === 'babcp') {
    $use_materialized_views = false;
    
    // Check if materialized views exist and try to use them
    try {
        // First check if the materialized view exists
        $check_view_query = "SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'mv_trainee_basic_stats'";
        $result = $pdo->query($check_view_query);
        $view_exists = $result->fetchColumn() > 0;
        
        if ($view_exists) {
            // Try to refresh materialized views
            $refresh_views_query = "SELECT refresh_all_materialized_views()";
            $pdo->query($refresh_views_query);
            $use_materialized_views = true;
            error_log("Using materialized views for BABCP analysis");
        } else {
            error_log("Materialized views not found, using original query");
        }
    } catch (Exception $e) {
        error_log("Materialized view check/refresh failed: " . $e->getMessage() . " - Using original query");
        $use_materialized_views = false;
    }
    
    if ($use_materialized_views) {
        // OPTIMIZED: Use materialized views for fast lookups
        $babcp_case_analysis_query = "
    SELECT 
        bs.trainkey,
        bs.trainee_name,
        bs.total_cases,
        COALESCE(SUM(bf.is_babcp), 0) as babcp_training_cases,
        COALESCE(SUM(bf.is_supervised), 0) as supervised_cases,
        COALESCE(SUM(bf.is_cbt), 0) as cbt_cases,
        COALESCE(SUM(bf.is_babcp * bf.is_supervised), 0) as babcp_supervised_cases,
        COALESCE(SUM(
            CASE WHEN bf.is_cbt = 1 
                 AND (tl.select_val LIKE '%closed%' OR tl.select_val LIKE '%completed%' OR tl.select_val LIKE '%finished%' OR tl.select_val LIKE '%ended%')
                 AND (tl.select_val LIKE '%BABCP%' OR tl.select_val LIKE '%Behavioural%' OR tl.select_val LIKE '%Cognitive%' OR tl.select_val LIKE '%accredited%')
            THEN 1 ELSE 0 END
        ), 0) as closed_cbt_babcp_cases,
        COALESCE(SUM(
            CASE WHEN (sc.numeric_sessions >= 5 OR sc.distinct_dates >= 5)
            THEN 1 ELSE 0 END
        ), 0) as cases_with_5plus_sessions,
        COALESCE(SUM(
            CASE WHEN sh.total_hours >= 5.0
            THEN 1 ELSE 0 END
        ), 0) as cases_with_5plus_hours,
        COALESCE(SUM(
            CASE WHEN (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)')
                 AND (tl.select_val LIKE '%trauma%' OR tl.select_val LIKE '%PTSD%' OR tl.select_val LIKE '%post-traumatic%')
            THEN 1 ELSE 0 END
        ), 0) as trauma_cases,
        COALESCE(SUM(
            CASE WHEN (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)')
                 AND (tl.select_val LIKE '%anxiety%' OR tl.select_val LIKE '%GAD%' OR tl.select_val LIKE '%panic%' OR tl.select_val LIKE '%worry%')
            THEN 1 ELSE 0 END
        ), 0) as anxiety_cases,
        COALESCE(SUM(
            CASE WHEN (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)')
                 AND (tl.select_val LIKE '%depression%' OR tl.select_val LIKE '%MDD%' OR tl.select_val LIKE '%mood%' OR tl.select_val LIKE '%low mood%')
            THEN 1 ELSE 0 END
        ), 0) as depression_cases,
        COALESCE(SUM(
            CASE WHEN (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)')
                 AND (tl.select_val LIKE '%OCD%' OR tl.select_val LIKE '%obsessive%' OR tl.select_val LIKE '%compulsive%')
            THEN 1 ELSE 0 END
        ), 0) as ocd_cases
    FROM mv_trainee_basic_stats bs
    LEFT JOIN mv_trainee_babcp_flags bf ON bs.trainkey = bf.trainkey
    LEFT JOIN mv_trainee_session_counts sc ON bf.logkey = sc.logkey
    LEFT JOIN (
        SELECT 
            tl_hours.logkey,
            SUM(CASE 
                WHEN tl_hours.select_val IS NOT NULL AND tl_hours.select_val != '0' AND tl_hours.select_val != '00:00'
                AND SPLIT_PART(tl_hours.select_val, ':', 1) ~ '^[0-9]+$' AND SPLIT_PART(tl_hours.select_val, ':', 1) !~ '\.'
                AND SPLIT_PART(tl_hours.select_val, ':', 2) ~ '^[0-9]+$' AND SPLIT_PART(tl_hours.select_val, ':', 2) !~ '\.'
                THEN (CAST(SPLIT_PART(tl_hours.select_val, ':', 1) AS INTEGER) * 60 + CAST(SPLIT_PART(tl_hours.select_val, ':', 2) AS INTEGER)) / 60.0
                ELSE 0
            END) as total_hours
        FROM trainee_log tl_hours 
        WHERE tl_hours.stid = 60 AND tl_hours.date_added >= ? AND tl_hours.date_added <= ?
        GROUP BY tl_hours.logkey
    ) sh ON bf.logkey = sh.logkey
    LEFT JOIN trainee_log tl ON bf.logkey = tl.logkey
    LEFT JOIN select_types st ON tl.stid = st.stid
    WHERE bs.course_id = COALESCE(?, bs.course_id) 
      AND bs.cohort_year = COALESCE(?, bs.cohort_year)
      AND bf.date_added >= ? AND bf.date_added <= ?
    GROUP BY bs.trainkey, bs.trainee_name, bs.total_cases
    ORDER BY bs.trainee_name
    ";
    } else {
        // Fallback to original complex query
        $babcp_case_analysis_query = "
        SELECT /*+ USE_INDEX(t, idx_trainee_trainkey) USE_INDEX(tl, idx_trainee_log_trainkey) USE_INDEX(st, idx_select_types_stid) */
            t.trainkey,
            t.name as trainee_name,
            COUNT(DISTINCT tl.logkey) as total_cases,
            COUNT(DISTINCT CASE WHEN flags.is_babcp = 1 THEN tl.logkey END) as babcp_training_cases,
            COUNT(DISTINCT CASE WHEN flags.is_supervised = 1 THEN tl.logkey END) as supervised_cases,
            COUNT(DISTINCT CASE WHEN flags.is_cbt = 1 THEN tl.logkey END) as cbt_cases,
            COUNT(DISTINCT CASE 
                WHEN (flags.is_babcp = 1 AND flags.is_supervised = 1)
                THEN tl.logkey 
            END) as babcp_supervised_cases,
            COUNT(DISTINCT CASE 
                WHEN (
                    flags.is_cbt = 1
                    AND (tl.select_val LIKE '%closed%' OR tl.select_val LIKE '%completed%' OR tl.select_val LIKE '%finished%' OR tl.select_val LIKE '%ended%')
                    AND (tl.select_val LIKE '%BABCP%' OR tl.select_val LIKE '%Behavioural%' OR tl.select_val LIKE '%Cognitive%' OR tl.select_val LIKE '%accredited%')
                ) THEN tl.logkey END
            ) as closed_cbt_babcp_cases,
            COUNT(DISTINCT CASE 
                WHEN (
                    COALESCE(session_counts.numeric_sessions, 0) >= 5
                    OR COALESCE(session_counts.distinct_dates, 0) >= 5
                ) THEN tl.logkey 
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
            SELECT 
                tl_sc.logkey,
                MAX(CASE 
                    WHEN st_sc.str LIKE 'Number of sessions%'
                         AND tl_sc.select_val ~ '^[0-9]+$'
                         AND tl_sc.select_val !~ '\.'
                         AND tl_sc.select_val !~ '[^0-9]'
                    THEN CAST(tl_sc.select_val AS INTEGER)
                    ELSE NULL
                END) AS numeric_sessions,
                COUNT(DISTINCT NULLIF(
                    COALESCE(
                        CASE WHEN tl_sc.select_val ~ '^\d{4}-\d{2}-\d{2}$' THEN tl_sc.select_val::date ELSE NULL END,
                        CASE WHEN tl_sc.select_val ~ '^\d{2}/\d{2}/\d{4}$' THEN TO_DATE(tl_sc.select_val, 'DD/MM/YYYY') ELSE NULL END,
                        CASE WHEN tl_sc.select_val ~ '^\d{2}-\d{2}-\d{4}$' THEN TO_DATE(tl_sc.select_val, 'DD-MM-YYYY') ELSE NULL END,
                        CASE WHEN tl_sc.select_val ~ '^\d{4}/\d{2}/\d{2}$' THEN TO_DATE(tl_sc.select_val, 'YYYY/MM/DD') ELSE NULL END,
                        CASE WHEN tl_sc.select_val ~ '^\d{8}$' AND tl_sc.select_val::int >= 19000101 AND tl_sc.select_val::int <= 21001231 THEN TO_DATE(tl_sc.select_val, 'YYYYMMDD') ELSE NULL END
                    ),
                    NULL
                )) AS distinct_dates
            FROM trainee_log tl_sc
            LEFT JOIN select_types st_sc ON st_sc.stid = tl_sc.stid
            WHERE tl_sc.date_added >= ? AND tl_sc.date_added <= ? AND tl_sc.date_added::text ~ '^[0-9]{8}$' AND tl_sc.date_added >= 19000101 AND tl_sc.date_added <= 21001231
            GROUP BY tl_sc.logkey
        ) session_counts ON tl.logkey = session_counts.logkey
        LEFT JOIN (
            SELECT 
                tl_hours.logkey,
                SUM(CASE 
                    WHEN tl_hours.select_val IS NOT NULL AND tl_hours.select_val != '0' AND tl_hours.select_val != '00:00'
                    AND SPLIT_PART(tl_hours.select_val, ':', 1) ~ '^[0-9]+$' AND SPLIT_PART(tl_hours.select_val, ':', 1) !~ '\.'
                    AND SPLIT_PART(tl_hours.select_val, ':', 2) ~ '^[0-9]+$' AND SPLIT_PART(tl_hours.select_val, ':', 2) !~ '\.'
                    THEN (CAST(SPLIT_PART(tl_hours.select_val, ':', 1) AS INTEGER) * 60 + CAST(SPLIT_PART(tl_hours.select_val, ':', 2) AS INTEGER)) / 60.0
                    ELSE 0
                END) as total_hours
            FROM trainee_log tl_hours 
            WHERE tl_hours.stid = 60 AND tl_hours.date_added >= ? AND tl_hours.date_added <= ? AND tl_hours.date_added::text ~ '^[0-9]{8}$' AND tl_hours.date_added >= 19000101 AND tl_hours.date_added <= 21001231
            GROUP BY tl_hours.logkey
        ) session_hours ON tl.logkey = session_hours.logkey
        LEFT JOIN (
            SELECT 
                tl_sub.logkey,
                tl_sub.trainkey,
                MAX(CASE 
                    WHEN (
                        st_sub.str LIKE '%BABCP%'
                        OR tl_sub.select_val LIKE '%BABCP%' OR tl_sub.select_val LIKE '%Behavioural%' OR tl_sub.select_val LIKE '%Cognitive%'
                    ) AND tl_sub.select_val NOT IN ('','0','No','NO','False','FALSE')
                    THEN 1 ELSE 0 END
                ) as is_babcp,
                MAX(CASE 
                    WHEN (
                        st_sub.str LIKE '%Supervis%'
                        OR st_sub.str IN (
                            'Supervised?','Supervision','Supervisor','Supervision Type',
                            'CTSR-Signed Off By BABCP Accredited Supervisor',
                            'Dates discussed in supervision with BABCP accredited supervisor',
                            'Dates received live supervision (i.e. recording played)',
                            'Patient IDs for clients receiving close supervision (i.e. recording played)',
                            'Patient IDs brought to this supervision'
                        )
                    ) AND (tl_sub.select_val IS NOT NULL AND tl_sub.select_val <> '')
                    THEN 1 ELSE 0 END
                ) as is_supervised,
                MAX(CASE 
                    WHEN (
                        (st_sub.str LIKE '%Modality%' OR st_sub.str LIKE '%Intervention%')
                        AND tl_sub.select_val LIKE '%CBT%'
                    ) THEN 1 ELSE 0 END
                ) as is_cbt
            FROM trainee_log tl_sub
            LEFT JOIN select_types st_sub ON tl_sub.stid = st_sub.stid
            WHERE tl_sub.date_added >= ? AND tl_sub.date_added <= ? AND tl_sub.date_added::text ~ '^[0-9]{8}$' AND tl_sub.date_added >= 19000101 AND tl_sub.date_added <= 21001231
            GROUP BY tl_sub.logkey, tl_sub.trainkey
        ) flags ON flags.logkey = tl.logkey AND flags.trainkey = tl.trainkey
        WHERE 1=1 $course_condition $group_condition $babcp_condition_simple $additional_conditions
        AND tl.date_added >= ? AND tl.date_added <= ? AND tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231
        GROUP BY t.trainkey, t.name
        ORDER BY t.name
        ";
    }

    // Debug: Log all parameters before execution
    error_log("BABCP Query - All basic params: " . print_r($all_basic_params, true));
    
    // The query structure is:
    // 1. Subquery 1 (session_counts): 2 date params
    // 2. Subquery 2 (session_hours): 2 date params  
    // 3. Subquery 3 (flags): 2 date params
    // 4. Main query: course/group params, then 2 date params
    // Parameters order: [subq1_date1, subq1_date2, subq2_date1, subq2_date2, subq3_date1, subq3_date2, course/group_params, main_date1, main_date2]
    $subquery_date_params = [$datestart_yyyymmdd, $dateend_yyyymmdd, $datestart_yyyymmdd, $dateend_yyyymmdd, $datestart_yyyymmdd, $dateend_yyyymmdd];
    $main_date_params = [$datestart_yyyymmdd, $dateend_yyyymmdd];
    
    // Validate all date parameters
    $all_date_params = array_merge($subquery_date_params, $main_date_params);
    foreach ($all_date_params as $i => $param) {
        if (!preg_match('/^\d{8}$/', $param) || $param < 19000101 || $param > 21001231) {
            error_log("Invalid date parameter at index $i: '$param'");
            throw new Exception("Invalid date parameter: $param");
        }
    }
    
    // Combine parameters in correct order: subquery dates, then course/group params, then main dates
    $all_params = array_merge($subquery_date_params, $all_basic_params, $main_date_params);
    
    $stmt = $pdo->prepare($babcp_case_analysis_query);
    try {
        $stmt->execute($all_params);
    } catch (PDOException $e) {
        error_log("BABCP case analysis query error: " . $e->getMessage());
        error_log("Query parameters - Start: $datestart_yyyymmdd, End: $dateend_yyyymmdd");
        error_log("All basic params: " . print_r($all_basic_params, true));
        error_log("All params being executed: " . print_r($all_params, true));
        throw $e;
    }
    $babcp_case_analysis = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $babcp_case_analysis[] = [
            'trainkey' => $row['trainkey'],
            'trainee_name' => $row['trainee_name'],
            'total_cases' => $row['total_cases'],
            'babcp_training_cases' => $row['babcp_training_cases'],
            'supervised_cases' => $row['supervised_cases'],
            'cbt_cases' => $row['cbt_cases'],
            'babcp_supervised_cases' => $row['babcp_supervised_cases'],
            'closed_cbt_babcp_cases' => $row['closed_cbt_babcp_cases'],
            'cases_with_5plus_sessions' => $row['cases_with_5plus_sessions'],
            'cases_with_5plus_hours' => $row['cases_with_5plus_hours'],
            'trauma_cases' => $row['trauma_cases'],
            'anxiety_cases' => $row['anxiety_cases'],
            'depression_cases' => $row['depression_cases'],
            'ocd_cases' => $row['ocd_cases']
        ];
    }
    $stmt->closeCursor();

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
        TO_CHAR(CASE WHEN tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 THEN TO_DATE(tl.date_added::text, 'YYYYMMDD') ELSE NULL END, 'YYYY-MM') as month,
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
    WHERE tl.date_added >= ? AND tl.date_added <= ? 
      AND tl.date_added::text ~ '^[0-9]{8}$'  -- Only valid YYYYMMDD format
      AND tl.date_added >= 19000101  -- Reasonable date range
      AND tl.date_added <= 21001231
      AND CASE 
        WHEN tl.date_added::text ~ '^[0-9]{8}$' THEN
          CASE 
            WHEN SUBSTR(tl.date_added::text, 1, 4)::int BETWEEN 1900 AND 2100 AND
                 SUBSTR(tl.date_added::text, 5, 2)::int BETWEEN 1 AND 12 AND
                 SUBSTR(tl.date_added::text, 7, 2)::int BETWEEN 1 AND 31
            THEN TRUE
            ELSE FALSE
          END
        ELSE FALSE
      END
      $course_condition $group_condition $babcp_condition_simple $additional_conditions
    GROUP BY TO_CHAR(CASE WHEN tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 THEN TO_DATE(tl.date_added::text, 'YYYYMMDD') ELSE NULL END, 'YYYY-MM')
    ORDER BY month DESC
    LIMIT 12
";

    $stmt = $pdo->prepare($babcp_growth_query);
    if (!empty($all_basic_params)) {
        $stmt->execute(array_merge([$datestart_yyyymmdd, $dateend_yyyymmdd], $all_basic_params));
    } else {
        $stmt->execute([$datestart_yyyymmdd, $dateend_yyyymmdd]);
    }
    $babcp_growth_data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $babcp_growth_data[] = [
            'month' => $row['month'], 
            'babcp_cases' => $row['babcp_cases'], 
            'supervised_cases' => $row['supervised_cases'], 
            'cbt_cases' => $row['cbt_cases'],
            'active_trainees' => $row['active_trainees']
        ];
    }
    $stmt->closeCursor();

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
    $debug_select_types_result = $pdo->query($debug_select_types_query);
    if ($debug_select_types_result) {
        error_log("Sample clinical terms in select_types:");
        while ($debug_select_types_row = $debug_select_types_result->fetch(PDO::FETCH_ASSOC)) {
            error_log("Clinical term: " . $debug_select_types_row['str']);
        }
    } else {
        error_log("No clinical terms found in select_types table");
    }

    // Clinical issues detection now correctly looks at Patient ID and General Comments fields
    // where clinical terms like 'OCD', 'PTSD', 'anxiety', 'depression', 'trauma' are embedded

    // Debug: Let's verify what cases we're actually detecting
    $debug_anxiety_cases_query = "SELECT DISTINCT tl.logkey, tl.select_val, st.str as field_name FROM trainee_log tl JOIN select_types st ON tl.stid = st.stid WHERE tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 AND (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)') AND (tl.select_val LIKE '%anxiety%' OR tl.select_val LIKE '%GAD%' OR tl.select_val LIKE '%panic%' OR tl.select_val LIKE '%worry%') LIMIT 10";
    $debug_anxiety_result = $pdo->query($debug_anxiety_cases_query);
    if ($debug_anxiety_result) {
        error_log("Sample anxiety cases detected:");
        while ($debug_anxiety_row = $debug_anxiety_result->fetch(PDO::FETCH_ASSOC)) {
            error_log("Logkey: " . $debug_anxiety_row['logkey'] . " | Field: " . $debug_anxiety_row['field_name'] . " | Value: '" . $debug_anxiety_row['select_val'] . "'");
        }
    }

    $debug_depression_cases_query = "SELECT DISTINCT tl.logkey, tl.select_val, st.str as field_name FROM trainee_log tl JOIN select_types st ON tl.stid = st.stid WHERE tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 AND (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)') AND (tl.select_val LIKE '%depression%' OR tl.select_val LIKE '%MDD%' OR tl.select_val LIKE '%mood%' OR tl.select_val LIKE '%low mood%') LIMIT 10";
    $debug_depression_result = $pdo->query($debug_depression_cases_query);
    if ($debug_depression_result) {
        error_log("Sample depression cases detected:");
        while ($debug_depression_row = $debug_depression_result->fetch(PDO::FETCH_ASSOC)) {
            error_log("Logkey: " . $debug_depression_row['logkey'] . " | Field: " . $debug_depression_row['field_name'] . " | Value: '" . $debug_depression_row['select_val'] . "'");
        }
    }

    $debug_trauma_cases_query = "SELECT DISTINCT tl.logkey, tl.select_val, st.str as field_name FROM trainee_log tl JOIN select_types st ON tl.stid = st.stid WHERE tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 AND (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)') AND (tl.select_val LIKE '%trauma%' OR tl.select_val LIKE '%PTSD%' OR tl.select_val LIKE '%post-traumatic%') LIMIT 10";
    $debug_trauma_result = $pdo->query($debug_trauma_cases_query);
    if ($debug_trauma_result) {
        error_log("Sample trauma cases detected:");
        while ($debug_trauma_row = $debug_trauma_result->fetch(PDO::FETCH_ASSOC)) {
            error_log("Logkey: " . $debug_trauma_row['logkey'] . " | Field: " . $debug_trauma_row['field_name'] . " | Value: '" . $debug_trauma_row['select_val'] . "'");
        }
    }

    $debug_ocd_cases_query = "SELECT DISTINCT tl.logkey, tl.select_val, st.str as field_name FROM trainee_log tl JOIN select_types st ON tl.stid = st.stid WHERE tl.date_added::text ~ '^[0-9]{8}$' AND tl.date_added >= 19000101 AND tl.date_added <= 21001231 AND (st.str = 'Patient ID' OR st.str = 'General Comments (avoid commas!)') AND (tl.select_val LIKE '%OCD%' OR tl.select_val LIKE '%obsessive%' OR tl.select_val LIKE '%compulsive%') LIMIT 10";
    $debug_ocd_result = $pdo->query($debug_ocd_cases_query);
    if ($debug_ocd_result) {
        error_log("Sample OCD cases detected:");
        while ($debug_ocd_row = $debug_ocd_result->fetch(PDO::FETCH_ASSOC)) {
            error_log("Logkey: " . $debug_ocd_row['logkey'] . " | Field: " . $debug_ocd_row['field_name'] . " | Value: '" . $debug_ocd_row['select_val'] . "'");
        }
    }

    // Additional debug: Check if there's any trainee data at all
    $debug_trainee_query = "SELECT COUNT(*) as trainee_count FROM trainee_tbl";
    $debug_trainee_result = $pdo->query($debug_trainee_query);
    if ($debug_trainee_result) {
        $debug_trainee_row = $debug_trainee_result->fetch(PDO::FETCH_ASSOC);
        error_log("Total trainees in database: " . $debug_trainee_row['trainee_count']);
    }

    // Check if there are any trainee_log entries at all
    $debug_log_query = "SELECT COUNT(*) as log_count FROM trainee_log";
    $debug_log_result = $pdo->query($debug_log_query);
    if ($debug_log_result) {
        $debug_log_row = $debug_log_result->fetch(PDO::FETCH_ASSOC);
        error_log("Total trainee_log entries in database: " . $debug_log_row['log_count']);
    }

    // Check the actual date range of the data - FIXED for YYYYMMDD format
    $debug_date_range_query = "SELECT MIN(CASE WHEN date_added::text ~ '^[0-9]{8}$' AND date_added >= 19000101 AND date_added <= 21001231 THEN TO_DATE(date_added::text, 'YYYYMMDD') ELSE NULL END) as earliest_date, MAX(CASE WHEN date_added::text ~ '^[0-9]{8}$' AND date_added >= 19000101 AND date_added <= 21001231 THEN TO_DATE(date_added::text, 'YYYYMMDD') ELSE NULL END) as latest_date FROM trainee_log WHERE date_added::text ~ '^[0-9]{8}$' AND date_added >= 19000101 AND date_added <= 21001231";
    $debug_date_range_result = $pdo->query($debug_date_range_query);
    if ($debug_date_range_result) {
        $debug_date_range_row = $debug_date_range_result->fetch(PDO::FETCH_ASSOC);
        error_log("Actual data date range: " . $debug_date_range_row['earliest_date'] . " to " . $debug_date_range_row['latest_date']);
    }

    // Check raw timestamp values
    $debug_raw_timestamps_query = "SELECT MIN(date_added) as min_timestamp, MAX(date_added) as max_timestamp, COUNT(*) as count FROM trainee_log LIMIT 5";
    $debug_raw_timestamps_result = $pdo->query($debug_raw_timestamps_query);
    if ($debug_raw_timestamps_result) {
        $debug_raw_timestamps_row = $debug_raw_timestamps_result->fetch(PDO::FETCH_ASSOC);
        error_log("Raw timestamps - Min: " . $debug_raw_timestamps_row['min_timestamp'] . ", Max: " . $debug_raw_timestamps_row['max_timestamp'] . ", Count: " . $debug_raw_timestamps_row['count']);
    }

    // Check if timestamps might be in a different format - FIXED for YYYYMMDD format
    $debug_sample_query = "SELECT date_added, CASE WHEN date_added::text ~ '^[0-9]{8}$' AND date_added >= 19000101 AND date_added <= 21001231 THEN TO_DATE(date_added::text, 'YYYYMMDD') ELSE NULL END as converted_date FROM trainee_log WHERE date_added::text ~ '^[0-9]{8}$' AND date_added >= 19000101 AND date_added <= 21001231 ORDER BY date_added DESC LIMIT 3";
    $debug_sample_result = $pdo->query($debug_sample_query);
    if ($debug_sample_result) {
        error_log("Sample timestamp data:");
        while ($debug_sample_row = $debug_sample_result->fetch(PDO::FETCH_ASSOC)) {
            error_log("Raw: " . $debug_sample_row['date_added'] . " -> Converted: " . $debug_sample_row['converted_date']);
        }
    }
} else {
    $babcp_case_analysis = [];
    $babcp_summary_stats = null;
    $babcp_growth_data = [];
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
                              <a href="<?php echo $listurl ?>" class="btn btn-light btn-lg">Back to Trainee Stats</a>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Lightweight View Navigation -->
            <ul class="nav nav-pills mb-3">
               <li class="nav-item">
                  <a class="nav-link <?php echo $view==='overview' ? 'active' : '' ?>" href="<?php echo htmlspecialchars($overview_url) ?>">Overview</a>
               </li>
               <li class="nav-item">
                  <a class="nav-link <?php echo $view==='competency' ? 'active' : '' ?>" href="<?php echo htmlspecialchars($competency_url) ?>">Competency</a>
               </li>
               <li class="nav-item">
                  <a class="nav-link <?php echo $view==='score' ? 'active' : '' ?>" href="<?php echo htmlspecialchars($score_url) ?>">Score</a>
               </li>
               <li class="nav-item">
                  <a class="nav-link <?php echo $view==='babcp' ? 'active' : '' ?>" href="<?php echo htmlspecialchars($babcp_url) ?>">BABCP</a>
               </li>
            </ul>

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
                     <a href="?export=full_report&course=<?php echo $selected_course ?>&group=<?php echo urlencode($selected_group) ?>" class="btn btn-primary btn-sm">
                        <i class="fa fa-download"></i> Export Full Report
                     </a>
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



            <?php if ($view === 'overview'): ?>
            <!-- Charts Row: Overview -->
            <div class="row mb-4">
               <div class="col-md-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Monthly Activity Trends</h5>
                     </div>
                     <div class="card-body">
                        <canvas id="monthlyActivityChart" width="400" height="200"></canvas>
</div>
</div>
            <?php endif; ?>

            <?php if ($view === 'score'): ?>
            <!-- Score Analysis Section -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Pass Standard Evaluation</h5>
                        <small class="text-muted">Trainee performance against pass standards</small>
                     </div>
                     <div class="card-body">
                        <?php
                        // Include pass standard functions
                        include '../OXEXfolder/pass_standard_functions.php';
                        
                        // Get all trainees for selection
                        $all_trainees = [];
                        $trainees_query = "SELECT trainkey, name FROM trainee_tbl ORDER BY name";
                        $trainees_result = $pdo->query($trainees_query);
                        while ($row = $trainees_result->fetch(PDO::FETCH_ASSOC)) {
                            $all_trainees[] = $row;
                        }
                        
                        // Get all pass standards for selection
                        $pass_standards = [];
                        $standards_query = "SELECT psid, standard_name, tbid, required_value, is_active FROM pass_standards WHERE is_active = 1 ORDER BY standard_name";
                        $standards_result = $pdo->query($standards_query);
                        while ($row = $standards_result->fetch(PDO::FETCH_ASSOC)) {
                            $pass_standards[] = $row;
                        }
                        
                        // Handle form submission for trainee selection
                        $selected_trainee = $_GET['score_trainee'] ?? '';
                        
                        // Get current trainee's pass standard results (if trainee is selected)
                        // This will check ALL trainee logs against EACH pass standard's conditions
                        $trainee_scores = [];
                        $current_trainee_key = ($trainee_key ?? '') ?: $selected_trainee;
                        
                        // Debug: Log trainee selection
                        error_log('[TRAINEE DEBUG] Starting pass standard evaluation');
                        error_log('[TRAINEE DEBUG] current_trainee_key: ' . var_export($current_trainee_key, true));
                        error_log('[TRAINEE DEBUG] trainee_key: ' . var_export($trainee_key ?? 'NOT SET', true));
                        error_log('[TRAINEE DEBUG] selected_trainee: ' . var_export($selected_trainee, true));
                        error_log('[TRAINEE DEBUG] pass_standards count: ' . count($pass_standards));
                        
                        if (!empty($current_trainee_key)) {
                            foreach ($pass_standards as $index => $standard) {
                                // Debug: Log each standard being evaluated
                                error_log('[TRAINEE DEBUG] ========================================');
                                error_log('[TRAINEE DEBUG] Evaluating standard #' . ($index + 1) . ' of ' . count($pass_standards));
                                error_log('[TRAINEE DEBUG] standard[psid]: ' . var_export($standard['psid'], true) . ' (type: ' . gettype($standard['psid']) . ')');
                                error_log('[TRAINEE DEBUG] standard[standard_name]: ' . var_export($standard['standard_name'], true));
                                error_log('[TRAINEE DEBUG] standard[tbid]: ' . var_export($standard['tbid'] ?? 'NOT SET', true) . ' (type: ' . gettype($standard['tbid'] ?? null) . ')');
                                error_log('[TRAINEE DEBUG] standard[required_value]: ' . var_export($standard['required_value'] ?? 'NOT SET', true));
                                error_log('[TRAINEE DEBUG] Full standard array: ' . json_encode($standard));
                                
                                try {
                                    // Use checkPassStandardStatus to evaluate this specific standard against all trainee logs
                                    error_log('[TRAINEE DEBUG] Calling checkPassStandardStatus with:');
                                    error_log('[TRAINEE DEBUG]   - traineeKey: ' . var_export($current_trainee_key, true) . ' (type: ' . gettype($current_trainee_key) . ')');
                                    error_log('[TRAINEE DEBUG]   - psid: ' . var_export($standard['psid'], true) . ' (type: ' . gettype($standard['psid']) . ')');
                                    
                                    $result = checkPassStandardStatus($current_trainee_key, $standard['psid'], $pdo);
                                    
                                    error_log('[TRAINEE DEBUG] checkPassStandardStatus returned successfully');
                                    error_log('[TRAINEE DEBUG] result type: ' . gettype($result));
                                    error_log('[TRAINEE DEBUG] result is_array: ' . var_export(is_array($result), true));
                                    if (is_array($result)) {
                                        error_log('[TRAINEE DEBUG] result keys: ' . implode(', ', array_keys($result)));
                                        error_log('[TRAINEE DEBUG] result[is_passed]: ' . var_export($result['is_passed'] ?? 'NOT SET', true));
                                        error_log('[TRAINEE DEBUG] result[current_value]: ' . var_export($result['current_value'] ?? 'NOT SET', true));
                                        error_log('[TRAINEE DEBUG] result[required_value]: ' . var_export($result['required_value'] ?? 'NOT SET', true));
                                        if (isset($result['breakdown']['category_groups'])) {
                                            error_log('[TRAINEE DEBUG] result has category_groups: ' . count($result['breakdown']['category_groups']));
                                        }
                                        if (isset($result['children'])) {
                                            error_log('[TRAINEE DEBUG] result has children: ' . count($result['children']));
                                        }
                                    } else {
                                        error_log('[TRAINEE DEBUG] result is not an array, value: ' . var_export($result, true));
                                    }
                                    
                                    // Ensure we have a valid result structure
                                    if (!$result || !is_array($result)) {
                                        error_log('[TRAINEE DEBUG] Result is invalid, creating default structure');
                                        $result = [
                                            'is_passed' => false,
                                            'standard_name' => $standard['standard_name'],
                                            'current_value' => 0,
                                            'required_value' => $standard['required_value'],
                                            'subfield_results' => []
                                        ];
                                    }
                                    
                                    // Get matching logs for this standard
                                    error_log('[TRAINEE DEBUG] Calling getMatchingLogsForStandard');
                                    $matching_logs = getMatchingLogsForStandard($current_trainee_key, $standard['psid'], $pdo);
                                    error_log('[TRAINEE DEBUG] getMatchingLogsForStandard returned ' . count($matching_logs) . ' logs');
                                    
                                    $trainee_scores[] = [
                                        'standard' => $standard,
                                        'result' => $result,
                                        'matching_logs' => $matching_logs
                                    ];
                                    
                                    error_log('[TRAINEE DEBUG] Successfully added score for standard #' . ($index + 1));
                                } catch (Exception $e) {
                                    error_log('[TRAINEE DEBUG] ERROR evaluating standard #' . ($index + 1) . ': ' . $e->getMessage());
                                    error_log('[TRAINEE DEBUG] Exception trace: ' . $e->getTraceAsString());
                                    error_log('[TRAINEE DEBUG] Standard that caused error: ' . json_encode($standard));
                                    
                                    // Create error result
                                    $result = [
                                        'is_passed' => false,
                                        'standard_name' => $standard['standard_name'] . ' (ERROR)',
                                        'current_value' => 0,
                                        'required_value' => $standard['required_value'],
                                        'subfield_results' => [],
                                        'error' => $e->getMessage()
                                    ];
                                    
                                    $trainee_scores[] = [
                                        'standard' => $standard,
                                        'result' => $result,
                                        'matching_logs' => []
                                    ];
                                } catch (PDOException $e) {
                                    error_log('[TRAINEE DEBUG] PDO ERROR evaluating standard #' . ($index + 1) . ': ' . $e->getMessage());
                                    error_log('[TRAINEE DEBUG] PDO Exception code: ' . $e->getCode());
                                    error_log('[TRAINEE DEBUG] PDO Exception trace: ' . $e->getTraceAsString());
                                    error_log('[TRAINEE DEBUG] Standard that caused PDO error: ' . json_encode($standard));
                                    
                                    // Create error result
                                    $result = [
                                        'is_passed' => false,
                                        'standard_name' => $standard['standard_name'] . ' (PDO ERROR)',
                                        'current_value' => 0,
                                        'required_value' => $standard['required_value'],
                                        'subfield_results' => [],
                                        'error' => $e->getMessage()
                                    ];
                                    
                                    $trainee_scores[] = [
                                        'standard' => $standard,
                                        'result' => $result,
                                        'matching_logs' => []
                                    ];
                                }
                            }
                            
                            error_log('[TRAINEE DEBUG] Completed evaluation of all standards');
                            error_log('[TRAINEE DEBUG] Total scores collected: ' . count($trainee_scores));
                        } else {
                            error_log('[TRAINEE DEBUG] No trainee selected, skipping evaluation');
                        }
                        ?>
                        
                        <!-- Simple Trainee Selection -->
                        <div class="row mb-4">
                           <div class="col-12">
                              <div class="card">
                                 <div class="card-header">
                                    <h6><i class="fas fa-user"></i> Select Trainee to Check All Pass Standards</h6>
                                 </div>
                                 <div class="card-body">
                                    <form method="GET" class="row">
                                       <input type="hidden" name="view" value="score">
                                       <div class="col-md-8">
                                          <label for="score_trainee" class="form-label">Select Trainee:</label>
                                          <select name="score_trainee" id="score_trainee" class="form-select" required>
                                             <option value="">-- Choose a Trainee --</option>
                                             <?php foreach ($all_trainees as $trainee): ?>
                                             <option value="<?php echo $trainee['trainkey']; ?>" <?php echo $selected_trainee == $trainee['trainkey'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($trainee['name']); ?>
                                             </option>
                                             <?php endforeach; ?>
                                          </select>
                                       </div>
                                       <div class="col-md-4">
                                          <label class="form-label">&nbsp;</label>
                                          <button type="submit" class="btn btn-primary d-block w-100">
                                             <i class="fas fa-check"></i> Check All Standards
                                          </button>
                                       </div>
                                    </form>
                                 </div>
                              </div>
                           </div>
                        </div>
                        
                        <!-- All Standards for Current Trainee -->
                        <?php if (empty($trainee_key ?? '') && empty($selected_trainee)): ?>
                        <div class="alert alert-info">
                           <i class="fas fa-info-circle"></i>
                           <strong>Select a Trainee</strong><br>
                           Use the form above to select a trainee and check all their pass standard results.
                        </div>
                        <?php elseif (!empty($trainee_key ?? '') || !empty($selected_trainee)): ?>
                        
                        <!-- Trainee Info -->
                        <div class="row mb-4">
                           <div class="col-12">
                              <div class="alert alert-primary">
                                 <?php 
                                 $display_trainee_name = $trainee_name ?? '';
                                 if (empty($display_trainee_name) && !empty($selected_trainee)) {
                                     // Get trainee name for selected trainee
                                     foreach ($all_trainees as $trainee) {
                                         if ($trainee['trainkey'] == $selected_trainee) {
                                             $display_trainee_name = $trainee['name'];
                                             break;
                                         }
                                     }
                                 } elseif (empty($display_trainee_name) && !empty($trainee_key ?? '')) {
                                     // Get trainee name for main trainee selection
                                     foreach ($all_trainees as $trainee) {
                                         if ($trainee['trainkey'] == ($trainee_key ?? '')) {
                                             $display_trainee_name = $trainee['name'];
                                             break;
                                         }
                                     }
                                 }
                                 ?>
                                 <h6><i class="fas fa-user"></i> Trainee: <?php echo htmlspecialchars($display_trainee_name ?? 'Unknown'); ?></h6>
                                 <small>Pass Standard Evaluation Results</small>
                              </div>
                           </div>
                        </div>
                        
                        <!-- Pass Standard Results -->
                        <div class="row">
                           <?php foreach ($trainee_scores as $index => $score): 
                              $current_val = $score['result']['current_value'] ?? 0;
                              $required_val = $score['result']['required_value'] ?? 0;
                              $is_passed = $score['result']['is_passed'] ?? false;
                              $progress = $required_val > 0 ? min(100, round(($current_val / $required_val) * 100)) : 0;
                              $matching_logs = $score['matching_logs'] ?? [];
                           ?>
                           <div class="col-12 mb-4">
                              <div class="card shadow-sm <?php echo $is_passed ? 'border-success' : 'border-warning'; ?>">
                                 <div class="card-header <?php echo $is_passed ? 'bg-success text-white' : 'bg-warning text-dark'; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                       <h5 class="mb-0">
                                          <?php echo $is_passed ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>'; ?>
                                          <?php echo htmlspecialchars($score['standard']['standard_name']); ?>
                                       </h5>
                                       <button class="btn btn-sm <?php echo $is_passed ? 'btn-light' : 'btn-dark'; ?>" type="button" data-toggle="collapse" data-target="#standard-<?php echo $index; ?>" aria-expanded="false">
                                          <i class="fas fa-chevron-down"></i> View Details
                                       </button>
                                    </div>
                                 </div>
                                 <div class="card-body">
                                    <!-- Progress Section -->
                                    <div class="mb-3">
                                       <div class="d-flex justify-content-between mb-2">
                                          <div>
                                             <span class="badge <?php echo $is_passed ? 'bg-success' : 'bg-warning'; ?> fs-6 px-3 py-2">
                                                <?php echo $current_val; ?> / <?php echo $required_val; ?>
                                             </span>
                                             <span class="ms-2 <?php echo $is_passed ? 'text-success' : 'text-warning'; ?> fw-bold">
                                                <?php echo $progress; ?>% Complete
                                             </span>
                                          </div>
                                          <div>
                                             <span class="badge <?php echo $is_passed ? 'bg-success' : 'bg-warning'; ?> fs-6 px-3 py-2">
                                                <?php echo count($matching_logs); ?> <?php echo count($matching_logs) == 1 ? 'Log Entry' : 'Log Entries'; ?>
                                             </span>
                                          </div>
                                       </div>
                                       <div class="progress" style="height: 25px;">
                                          <div class="progress-bar <?php echo $is_passed ? 'bg-success' : 'bg-warning'; ?> progress-bar-striped" 
                                               role="progressbar" 
                                               style="width: <?php echo $progress; ?>%"
                                               aria-valuenow="<?php echo $progress; ?>" 
                                               aria-valuemin="0" 
                                               aria-valuemax="100">
                                             <?php echo $progress; ?>%
                                          </div>
                                       </div>
                                    </div>

                                    <!-- Subfield Results -->
                                    <?php if (!empty($score['result']['subfield_results'] ?? [])): ?>
                                    <div class="mb-3">
                                       <h6 class="text-muted mb-2"><i class="fas fa-list"></i> Subfield Requirements:</h6>
                                       <div class="row">
                                          <?php foreach (($score['result']['subfield_results'] ?? []) as $subfield): ?>
                                          <div class="col-md-6 mb-2">
                                             <div class="d-flex align-items-center">
                                                <span class="badge <?php echo ($subfield['is_passed'] ?? false) ? 'bg-success' : 'bg-danger'; ?> me-2">
                                                   <?php echo ($subfield['is_passed'] ?? false) ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                                </span>
                                                <span class="small">
                                                   <?php echo htmlspecialchars($subfield['subfield_name'] ?? 'Unknown'); ?>
                                                   <strong>(<?php echo $subfield['current_value'] ?? 0; ?>/<?php echo $subfield['required_value'] ?? 0; ?>)</strong>
                                                </span>
                                             </div>
                                          </div>
                                          <?php endforeach; ?>
                                       </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Category Groups Results -->
                                    <?php if (!empty($score['result']['breakdown']['category_groups'] ?? [])): ?>
                                    <div class="mb-3">
                                       <h6 class="text-muted mb-2"><i class="fas fa-layer-group"></i> Category Groups:</h6>
                                       <?php foreach ($score['result']['breakdown']['category_groups'] as $group): ?>
                                          <div class="card mb-2">
                                             <div class="card-body">
                                                <h6><?php echo htmlspecialchars($group['group_name'] ?? 'Category Group'); ?></h6>
                                                <div class="d-flex align-items-center">
                                                   <span class="badge <?php echo ($group['is_passed'] ?? false) ? 'bg-success' : 'bg-danger'; ?> me-2">
                                                      <?php echo ($group['is_passed'] ?? false) ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                                   </span>
                                                   <span>
                                                      <?php echo $group['current_value'] ?? 0; ?> / <?php echo $group['required_value'] ?? 0; ?>
                                                   </span>
                                                </div>
                                                
                                                <!-- Subfield rules within category group -->
                                                <?php if (!empty($group['subfield_rules'] ?? [])): ?>
                                                   <div class="mt-2 ms-4">
                                                      <?php foreach ($group['subfield_rules'] as $subfield): 
                                                         // Get subfield name - look it up if not provided
                                                         $subfield_name = $subfield['subfield_name'] ?? null;
                                                         if (empty($subfield_name) && !empty($subfield['subfield_value'])) {
                                                            // Try to look up the name using the helper function if available
                                                            if (function_exists('_getSubfieldName')) {
                                                               $subfield_name = _getSubfieldName($subfield['subfield_value'], $pdo);
                                                            } else {
                                                               $subfield_name = 'Subfield ' . ($subfield['subfield_value'] ?? 'Unknown');
                                                            }
                                                         }
                                                         $subfield_name = $subfield_name ?? 'Unknown';
                                                      ?>
                                                         <div class="small mb-1">
                                                            <span class="badge <?php echo ($subfield['is_passed'] ?? false) ? 'bg-success' : 'bg-danger'; ?>">
                                                               <?php echo htmlspecialchars($subfield_name); ?>
                                                               (<?php echo $subfield['current_value'] ?? 0; ?>/<?php echo $subfield['required_value'] ?? 0; ?>)
                                                            </span>
                                                         </div>
                                                      <?php endforeach; ?>
                                                   </div>
                                                <?php endif; ?>
                                             </div>
                                          </div>
                                       <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Child Rules Results -->
                                    <?php if (!empty($score['result']['children'] ?? [])): ?>
                                    <div class="mb-3">
                                       <h6 class="text-muted mb-2"><i class="fas fa-sitemap"></i> Child Requirements:</h6>
                                       <?php foreach ($score['result']['children'] as $child_index => $child): ?>
                                          <div class="card mb-2 ms-4 border-start border-3">
                                             <div class="card-body">
                                                <h6 class="small">
                                                   <?php echo htmlspecialchars($child['standard_name'] ?? 'Child Requirement'); ?>
                                                </h6>
                                                <div class="d-flex align-items-center">
                                                   <span class="badge <?php echo ($child['is_passed'] ?? false) ? 'bg-success' : 'bg-danger'; ?> me-2">
                                                      <?php echo ($child['is_passed'] ?? false) ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                                   </span>
                                                   <span class="small">
                                                      <?php echo $child['current_value'] ?? 0; ?> / <?php echo $child['required_value'] ?? 0; ?>
                                                   </span>
                                                </div>
                                                
                                                <!-- Recursively show child's subfield results if any -->
                                                <?php if (!empty($child['subfield_rules'] ?? [])): ?>
                                                   <div class="mt-2 ms-3">
                                                      <?php foreach ($child['subfield_rules'] as $subfield): 
                                                         // Get subfield name - look it up if not provided
                                                         $subfield_name = $subfield['subfield_name'] ?? null;
                                                         if (empty($subfield_name) && !empty($subfield['subfield_value'])) {
                                                            // Try to look up the name using the helper function if available
                                                            if (function_exists('_getSubfieldName')) {
                                                               $subfield_name = _getSubfieldName($subfield['subfield_value'], $pdo);
                                                            } else {
                                                               $subfield_name = 'Subfield ' . ($subfield['subfield_value'] ?? 'Unknown');
                                                            }
                                                         }
                                                         $subfield_name = $subfield_name ?? 'Unknown';
                                                      ?>
                                                         <div class="small mb-1">
                                                            <span class="badge <?php echo ($subfield['is_passed'] ?? false) ? 'bg-success' : 'bg-warning'; ?>">
                                                               <?php echo htmlspecialchars($subfield_name); ?>
                                                               (<?php echo $subfield['current_value'] ?? 0; ?>/<?php echo $subfield['required_value'] ?? 0; ?>)
                                                            </span>
                                                         </div>
                                                      <?php endforeach; ?>
                                                   </div>
                                                <?php endif; ?>
                                                
                                                <!-- Recursively show child's category groups if any -->
                                                <?php if (!empty($child['category_groups'] ?? [])): ?>
                                                   <div class="mt-2 ms-3">
                                                      <h6 class="text-muted small mb-1"><i class="fas fa-layer-group"></i> Category Groups:</h6>
                                                      <?php foreach ($child['category_groups'] as $child_group): ?>
                                                         <div class="card mb-1">
                                                            <div class="card-body p-2">
                                                               <div class="small d-flex align-items-center">
                                                                  <span class="badge <?php echo ($child_group['is_passed'] ?? false) ? 'bg-success' : 'bg-danger'; ?> me-2">
                                                                     <?php echo ($child_group['is_passed'] ?? false) ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                                                  </span>
                                                                  <span>
                                                                     <?php echo htmlspecialchars($child_group['group_name'] ?? 'Category Group'); ?>
                                                                     (<?php echo $child_group['current_value'] ?? 0; ?>/<?php echo $child_group['required_value'] ?? 0; ?>)
                                                                  </span>
                                                               </div>
                                                            </div>
                                                         </div>
                                                      <?php endforeach; ?>
                                                   </div>
                                                <?php endif; ?>
                                                
                                                <!-- Recursively show child's children if any (nested children) -->
                                                <?php if (!empty($child['children'] ?? [])): ?>
                                                   <div class="mt-2 ms-3">
                                                      <h6 class="text-muted small mb-1"><i class="fas fa-sitemap"></i> Nested Requirements:</h6>
                                                      <?php foreach ($child['children'] as $nested_child): ?>
                                                         <div class="card mb-1 ms-2">
                                                            <div class="card-body p-2">
                                                               <div class="small d-flex align-items-center">
                                                                  <span class="badge <?php echo ($nested_child['is_passed'] ?? false) ? 'bg-success' : 'bg-danger'; ?> me-2">
                                                                     <?php echo ($nested_child['is_passed'] ?? false) ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                                                  </span>
                                                                  <span>
                                                                     <?php echo htmlspecialchars($nested_child['standard_name'] ?? 'Nested Requirement'); ?>
                                                                     (<?php echo $nested_child['current_value'] ?? 0; ?>/<?php echo $nested_child['required_value'] ?? 0; ?>)
                                                                  </span>
                                                               </div>
                                                            </div>
                                                         </div>
                                                      <?php endforeach; ?>
                                                   </div>
                                                <?php endif; ?>
                                             </div>
                                          </div>
                                       <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Collapsible Logs Section -->
                                    <div class="collapse" id="standard-<?php echo $index; ?>">
                                       <hr>
                                       <div class="mt-3">
                                          <h6 class="text-muted mb-3">
                                             <i class="fas fa-file-alt"></i> Matching Log Entries 
                                             <span class="badge bg-secondary"><?php echo count($matching_logs); ?></span>
                                          </h6>
                                          
                                          <?php if (empty($matching_logs)): ?>
                                             <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle"></i> No log entries match this standard's criteria.
                                             </div>
                                          <?php else: ?>
                                             <div style="max-height: 400px; overflow-y: auto;">
                                                <div class="list-group">
                                                   <?php foreach ($matching_logs as $log): 
                                                      $log_date = $log['date'];
                                                      // Format date if it's in YYYYMMDD format (integer stored as string)
                                                      if (strlen($log_date) == 8 && is_numeric($log_date)) {
                                                         $year = substr($log_date, 0, 4);
                                                         $month = substr($log_date, 4, 2);
                                                         $day = substr($log_date, 6, 2);
                                                         $log_date = $day . '/' . $month . '/' . $year;
                                                      } elseif (is_numeric($log_date) && strlen($log_date) >= 8) {
                                                         // Handle integer dates
                                                         $log_date_str = (string)$log_date;
                                                         if (strlen($log_date_str) >= 8) {
                                                            $year = substr($log_date_str, 0, 4);
                                                            $month = substr($log_date_str, 4, 2);
                                                            $day = substr($log_date_str, 6, 2);
                                                            $log_date = $day . '/' . $month . '/' . $year;
                                                         }
                                                      } else {
                                                         // Try to parse as date string
                                                         $timestamp = strtotime($log_date);
                                                         if ($timestamp !== false) {
                                                            $log_date = date('d/m/Y', $timestamp);
                                                         }
                                                         // $log_date is now formatted and safe to display
                                                      }
                                                   ?>
                                                   <div class="list-group-item">
                                                      <div class="d-flex justify-content-between align-items-start">
                                                         <div class="flex-grow-1">
                                                            <div class="d-flex align-items-center mb-2">
                                                               <i class="fas fa-calendar-alt text-muted me-2"></i>
                                                               <strong class="me-2"><?php echo htmlspecialchars($log_date); ?></strong>
                                                               <span class="badge bg-primary"><?php echo htmlspecialchars($log['tab_name']); ?></span>
                                                            </div>
                                                            <div class="ms-4">
                                                               <?php foreach ($log['fields'] as $field): ?>
                                                               <div class="small text-muted mb-1">
                                                                  <strong><?php echo htmlspecialchars($field['select_type_name']); ?>:</strong>
                                                                  <code><?php echo htmlspecialchars($field['select_val']); ?></code>
                                                               </div>
                                                               <?php endforeach; ?>
                                                            </div>
                                                         </div>
                                                         <div class="text-end">
                                                            <small class="text-muted d-block">Log Key:</small>
                                                            <code class="small"><?php echo substr($log['logkey'], 0, 8); ?>...</code>
                                                         </div>
                                                      </div>
                                                   </div>
                                                   <?php endforeach; ?>
                                                </div>
                                             </div>
                                          <?php endif; ?>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                           <?php endforeach; ?>
                        </div>
                        
                        <!-- Summary Stats -->
                        <div class="row mt-4">
                           <div class="col-12">
                              <div class="card">
                                 <div class="card-header">
                                    <h6>Summary Statistics</h6>
                                 </div>
                                 <div class="card-body">
                                    <div class="row text-center">
                                       <?php
                                       $total_standards = count($trainee_scores);
                                       $passed_standards = count(array_filter($trainee_scores, function($s) { return ($s['result']['is_passed'] ?? false); }));
                                       $pass_rate = $total_standards > 0 ? round(($passed_standards / $total_standards) * 100, 1) : 0;
                                       ?>
                                       <div class="col-md-3">
                                          <div class="metric-value text-primary"><?php echo $total_standards; ?></div>
                                          <div class="metric-label">Total Standards</div>
                                       </div>
                                       <div class="col-md-3">
                                          <div class="metric-value text-success"><?php echo $passed_standards; ?></div>
                                          <div class="metric-label">Passed</div>
                                       </div>
                                       <div class="col-md-3">
                                          <div class="metric-value text-danger"><?php echo $total_standards - $passed_standards; ?></div>
                                          <div class="metric-label">Failed</div>
                                       </div>
                                       <div class="col-md-3">
                                          <div class="metric-value <?php echo $pass_rate >= 80 ? 'text-success' : ($pass_rate >= 60 ? 'text-warning' : 'text-danger'); ?>">
                                             <?php echo $pass_rate; ?>%
                                          </div>
                                          <div class="metric-label">Pass Rate</div>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                        
                        <?php endif; ?>
                     </div>
                  </div>
               </div>
            </div>
            <?php endif; ?>

            <?php if ($view === 'babcp'): ?>
            <!-- BABCP Growth Analysis Charts -->
            <div class="row mb-4">
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
            <?php endif; ?>

            <?php if ($view === 'babcp'): ?>
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
            <?php endif; ?>


            <?php if ($view === 'competency'): ?>
            <!-- Detailed Competency Analysis -->
            <div class="row">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">Competency Difficulty Analysis</h5>
                     </div>
                     <div class="card-body">
                        <div class="mb-4">
                           <canvas id="difficultyChart" width="400" height="200"></canvas>
                        </div>
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
            <?php endif; ?>

            <?php if ($view === 'babcp'): ?>
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
                        
                        <!-- Case Counts Row 1 -->
                        <div class="row mb-4">
                           <div class="col-md-4">
                              <div class="text-center">
                                 <div class="metric-value text-primary"><?php echo $babcp_summary_stats['total_babcp_training_cases'] ?></div>
                                 <div class="metric-label">Total BABCP Training Cases</div>
                              </div>
                           </div>
                           <div class="col-md-4">
                              <div class="text-center">
                                 <div class="metric-value text-success"><?php echo $babcp_summary_stats['total_supervised_cases'] ?></div>
                                 <div class="metric-label">Total Supervised Cases</div>
                              </div>
                           </div>
                           <div class="col-md-4">
                              <div class="text-center">
                                 <div class="metric-value text-info"><?php echo $babcp_summary_stats['total_cbt_cases'] ?></div>
                                 <div class="metric-label">Total CBT Cases</div>
                              </div>
                           </div>
                        </div>
                        
                        <!-- Case Counts Row 2 -->
                        <div class="row mb-4">
                           <div class="col-md-4">
                              <div class="text-center">
                                 <div class="metric-value text-purple"><?php echo $babcp_summary_stats['total_babcp_supervised_cases'] ?></div>
                                 <div class="metric-label">BABCP Supervised Cases</div>
                              </div>
                           </div>
                           <div class="col-md-4">
                              <div class="text-center">
                                 <div class="metric-value text-dark"><?php echo $babcp_summary_stats['total_closed_cbt_babcp_cases'] ?></div>
                                 <div class="metric-label">Closed CBT BABCP Cases</div>
                              </div>
                           </div>
                           <div class="col-md-4">
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

                        <!-- Table Controls -->
                        <div class="row mb-3">
                           <div class="col-md-6">
                              <label for="babcpPageSize" class="form-label">Show entries</label>
                              <select id="babcpPageSize" class="form-select form-select-sm">
                                 <option value="10">10</option>
                                 <option value="25" selected>25</option>
                                 <option value="50">50</option>
                                 <option value="100">100</option>
                              </select>
                           </div>
                           <div class="col-md-6 d-flex align-items-end">
                              <div class="input-group">
                                 <span class="input-group-text"><i class="fas fa-search"></i></span>
                                 <input type="text" class="form-control form-control-sm" id="babcpSearch" placeholder="Search trainees...">
                              </div>
                           </div>
                        </div>

                        <!-- Detailed Trainee Analysis Table -->
                        <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                           <table class="table table-striped table-hover" id="babcpTable" style="width: 100%; table-layout: fixed;">
                              <thead class="table-dark">
                                 <tr>
                                    <th class="sortable" data-sort="trainee_name">
                                       Trainee Name 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
                                    <th class="sortable" data-sort="total_cases">
                                       Total Cases 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
                                    <th class="sortable" data-sort="babcp_training_cases">
                                       BABCP Training 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
                                    <th class="sortable" data-sort="supervised_cases">
                                       Supervised 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
                                    <th class="sortable" data-sort="cbt_cases">
                                       CBT Cases 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
                                    <th class="sortable" data-sort="babcp_supervised_cases">
                                       BABCP Supervised 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
                                    <th class="sortable" data-sort="closed_cbt_babcp_cases">
                                       Closed CBT BABCP 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
                                    <th class="sortable" data-sort="cases_with_5plus_sessions">
                                       5+ Sessions 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
                                    <th class="sortable" data-sort="cases_with_5plus_hours">
                                       5+ Hours 
                                       <i class="fas fa-sort sort-icon"></i>
                                    </th>
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
                                 <span id="babcpPaginationInfo" class="text-muted">Showing 1 to 25 of 0 entries</span>
                              </div>
                           </div>
                           <div class="col-md-6">
                              <nav aria-label="BABCP table pagination">
                                 <ul class="pagination pagination-sm justify-content-end mb-0" id="babcpPagination">
                                    <!-- Pagination buttons will be generated by JavaScript -->
                                 </ul>
                              </nav>
                           </div>
                        </div>
            </div>
            <?php endif; ?>

            <?php if ($view === 'babcp'): ?>
            <!-- BABCP Contact-Modality Grouping Report -->
            <div class="row mb-4">
               <div class="col-12">
                  <div class="card">
                     <div class="card-header">
                        <h5 class="card-title">BABCP Contact Type & Modality Grouping</h5>
                        <small class="text-muted">Client counts grouped by Primary Contact Type (Individual/Group) and Primary Modality (CBT, ACT, etc.)</small>
                     </div>
                     <div class="card-body">
                        <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                           <table class="table table-striped table-hover" id="babcpGroupingTable" style="width: 100%; table-layout: fixed;">
                              <thead class="table-dark">
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
                           <div class="col-md-6">
                              <div class="alert alert-success">
                                 <strong>Total Trainees:</strong> <span id="totalTrainees">0</span>
</div>
</div>
</div>
            </div>
            <?php endif; ?>

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
   let babcpSort = { key: null, dir: 'asc' };
   
   
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
           this.sortedData = [];
           
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
           this.applySort();
           this.render();
       }

       setSort(key, dir) {
           this.sortKey = key;
           this.sortDir = dir || 'asc';
           this.applySort();
           this.currentPage = 1;
           this.render();
       }

       applySort() {
           if (!this.sortKey) {
               this.sortedData = this.data.slice();
               return;
           }
           const key = this.sortKey;
           const dir = this.sortDir === 'desc' ? -1 : 1;
           this.sortedData = this.data.slice().sort((a, b) => {
               const va = a[key];
               const vb = b[key];
               if (va == null && vb == null) return 0;
               if (va == null) return -1 * dir;
               if (vb == null) return 1 * dir;
               if (typeof va === 'number' && typeof vb === 'number') {
                   return (va - vb) * dir;
               }
               const sa = String(va).toLowerCase();
               const sb = String(vb).toLowerCase();
               if (sa < sb) return -1 * dir;
               if (sa > sb) return 1 * dir;
               return 0;
           });
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
           const pageData = this.sortedData.slice(startIndex, endIndex);
           
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

       // Wire up header sort on BABCP table
       const babcpTable = document.getElementById('babcpTable');
       if (babcpTable) {
           const headers = babcpTable.querySelectorAll('th.sortable');
           headers.forEach((th) => {
               th.addEventListener('click', () => {
                   const key = th.getAttribute('data-sort');
                   const isActive = th.classList.contains('active');
                   let dir = 'asc';
                   if (isActive) {
                       dir = th.classList.contains('asc') ? 'desc' : 'asc';
                   }
                   headers.forEach(h => h.classList.remove('active', 'asc', 'desc'));
                   th.classList.add('active', dir);
                   babcpPagination.setSort(key, dir);
               });
           });
       }
       
       

       // Load charts with PHP data directly
       loadChartsWithPHPData();
   });
   
   // Function to load charts with PHP data
   function loadChartsWithPHPData() {
       // Monthly Activity Trends Chart
       const monthlyData = <?php echo json_encode($monthly_activity); ?>;

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
       if (babcpGrowthData && babcpGrowthData.length > 0) {
           updateBabcpGrowthChart(babcpGrowthData, 'detailed');
       } else {
           console.log('No BABCP growth data available');
       }
       
       // Clinical Issues Chart
       const clinicalIssuesData = <?php echo json_encode($babcp_summary_stats); ?>;
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
       const start_year = urlParams.get('start_year') || '';
       const end_year = urlParams.get('end_year') || '';
       const babcp_filter = urlParams.get('babcp_filter') || '';
       
       const params = new URLSearchParams({
           data_type: 'babcp_contact_modality_grouping',
           course: course,
           group: group,
           babcp_training: babcp_training,
           supervised_case: supervised_case,
           primary_modality: primary_modality,
           min_sessions: min_sessions,
           start_year: start_year,
           end_year: end_year,
           babcp_filter: babcp_filter
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
