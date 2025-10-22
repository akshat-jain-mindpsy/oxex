<?php
/*
 * MATERIALIZED VIEWS MAINTENANCE
 * 
 * This script handles all materialized view operations:
 * - Check status
 * - Refresh views
 * - Create missing views
 */

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Check permissions
if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'DV')) {
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'status';
    
    echo "<h2>Materialized Views Maintenance</h2>";
    
    switch ($action) {
        case 'status':
            showStatus();
            break;
        case 'refresh':
            refreshViews();
            break;
        case 'create':
            createMissingViews();
            break;
        default:
            showStatus();
    }
    
} else {
    echo "<p style='color: red;'>Access denied. You need admin privileges.</p>";
}

function showStatus() {
    global $pdo;
    
    echo "<h3>Status Check</h3>";
    
    $views = [
        'mv_trainee_basic_stats' => 'Basic trainee statistics',
        'mv_trainee_babcp_flags' => 'BABCP/supervised/CBT flags',
        'mv_trainee_session_counts' => 'Session statistics',
        'mv_tab_statistics' => 'Competency/tab statistics',
        'mv_monthly_activity' => 'Monthly activity trends',
        'mv_contact_modality_stats' => 'Contact/modality statistics'
    ];
    
    $working = 0;
    foreach ($views as $view => $desc) {
        try {
            $result = $pdo->query("SELECT COUNT(*) FROM $view");
            $count = $result->fetchColumn();
            echo "<p style='color: green;'>✓ $view: $count rows</p>";
            $working++;
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ $view: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p><strong>Working:</strong> $working / " . count($views) . " views</p>";
    
    if ($working == count($views)) {
        echo "<p style='color: green; font-weight: bold;'>🎉 All materialized views are working!</p>";
        echo "<p>Your queries should be 70-90% faster.</p>";
    }
    
    echo "<hr>";
    echo "<h3>Actions</h3>";
    echo "<p><a href='?action=refresh'>🔄 Refresh All Views</a></p>";
    echo "<p><a href='?action=create'>🔧 Create Missing Views</a></p>";
    echo "<p><a href='trainee_stats.php'>📊 Test Performance</a></p>";
}

function refreshViews() {
    global $pdo;
    
    echo "<h3>Refreshing Materialized Views</h3>";
    
    $views = [
        'mv_trainee_basic_stats',
        'mv_trainee_babcp_flags', 
        'mv_trainee_session_counts',
        'mv_tab_statistics',
        'mv_monthly_activity',
        'mv_contact_modality_stats'
    ];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($views as $view) {
        try {
            $pdo->query("REFRESH MATERIALIZED VIEW $view");
            echo "<p style='color: green;'>✓ Refreshed $view</p>";
            $success_count++;
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Failed to refresh $view: " . $e->getMessage() . "</p>";
            $error_count++;
        }
    }
    
    echo "<p><strong>Results:</strong> $success_count successful, $error_count failed</p>";
    
    if ($error_count == 0) {
        echo "<p style='color: green; font-weight: bold;'>🎉 All materialized views refreshed successfully!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Some views failed to refresh, but the system will use fallback queries.</p>";
    }
    
    echo "<p><a href='?action=status'>← Back to Status</a></p>";
}

function createMissingViews() {
    global $pdo;
    
    echo "<h3>Creating Missing Views</h3>";
    
    // Only create the session counts view if it's missing
    try {
        $pdo->query("SELECT COUNT(*) FROM mv_trainee_session_counts");
        echo "<p style='color: blue;'>ℹ️ mv_trainee_session_counts already exists</p>";
    } catch (PDOException $e) {
        echo "<p>Creating mv_trainee_session_counts...</p>";
        
        $sql = "
        CREATE MATERIALIZED VIEW mv_trainee_session_counts AS
        SELECT 
            tl.logkey,
            tl.trainkey,
            tl.date_added,
            MAX(CASE 
                WHEN st.str LIKE '%Session%' 
                     AND tl.select_val ~ '^[0-9]+$'
                     AND tl.select_val::int BETWEEN 1 AND 1000
                THEN CAST(tl.select_val AS INTEGER)
                ELSE 0
            END) AS numeric_sessions,
            COUNT(DISTINCT CASE 
                WHEN tl.select_val ~ '^[0-9]{8}$' 
                     AND tl.select_val::int >= 19000101 
                     AND tl.select_val::int <= 21001231
                THEN tl.select_val
                ELSE NULL 
            END) AS distinct_dates
        FROM trainee_log tl
        LEFT JOIN select_types st ON tl.stid = st.stid
        WHERE tl.date_added >= 19000101 AND tl.date_added <= 21001231
        GROUP BY tl.logkey, tl.trainkey, tl.date_added
        ";
        
        try {
            $pdo->exec($sql);
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mv_session_counts_trainkey ON mv_trainee_session_counts(trainkey)");
            echo "<p style='color: green;'>✓ Created mv_trainee_session_counts</p>";
        } catch (PDOException $e2) {
            echo "<p style='color: red;'>✗ Failed to create: " . $e2->getMessage() . "</p>";
        }
    }
    
    echo "<p><a href='?action=status'>← Back to Status</a></p>";
}
?>
