<?php
/*
 * MATERIALIZED VIEWS REFRESH SCRIPT
 * 
 * This script refreshes the materialized views to keep them up to date.
 * Can be run manually or scheduled via cron job.
 * 
 * Recommended schedule: Every 15 minutes
 * Cron example: */15 * * * * /usr/bin/php /path/to/refresh_materialized_views.php
 */

include '../OXEXfolder/config.php';

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
$log_messages = [];

foreach ($views as $view) {
    try {
        $pdo->query("REFRESH MATERIALIZED VIEW $view");
        $log_messages[] = "✓ Refreshed $view";
        $success_count++;
    } catch (PDOException $e) {
        $log_messages[] = "✗ Failed to refresh $view: " . $e->getMessage();
        $error_count++;
    }
}

// Log the refresh
$log_message = "Materialized views refresh at " . date('Y-m-d H:i:s') . " - $success_count successful, $error_count failed";
error_log($log_message);

// If running from command line, output results
if (php_sapi_name() === 'cli') {
    echo $log_message . "\n";
    foreach ($log_messages as $msg) {
        echo $msg . "\n";
    }
} else {
    echo "<p style='color: green;'>$log_message</p>";
    foreach ($log_messages as $msg) {
        $color = strpos($msg, '✓') === 0 ? 'green' : 'red';
        echo "<p style='color: $color;'>$msg</p>";
    }
}
?>
