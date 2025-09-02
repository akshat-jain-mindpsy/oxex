<?php
header('Content-Type: application/json');

try {
    include '../../OXEXfolder/config.php';
    include '../../OXEXfolder/u_functions.php';
    sec_session_start();
    include '../incl/sess.php';
    include '../incl/stats_logger.php';
    
    // Check permissions - only admins can see debug info
    if (!login_check($mysqli) || !($admintype == 'AT' || $admintype == 'DV')) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Unauthorized access'
        ]);
        exit;
    }

    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $logger = new StatsLogger();
    
    switch ($type) {
        case 'performance':
            // Get basic performance metrics
            $log_file = __DIR__ . '/../logs/stats_queries.log';
            $performance_data = '';
            
            if (file_exists($log_file)) {
                $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recent_lines = array_slice($lines, -20); // Last 20 lines
                
                $total_queries = count($lines);
                $avg_time = 0;
                $slow_queries = 0;
                
                foreach ($recent_lines as $line) {
                    if (preg_match('/Time: (\d+\.?\d*)ms/', $line, $matches)) {
                        $time = floatval($matches[1]);
                        $avg_time += $time;
                        if ($time > 100) { // Queries taking more than 100ms
                            $slow_queries++;
                        }
                    }
                }
                
                if (count($recent_lines) > 0) {
                    $avg_time = round($avg_time / count($recent_lines), 2);
                }
                
                $performance_data = "
                    <div class='row'>
                        <div class='col-6'>
                            <strong>Total Queries:</strong> $total_queries<br>
                            <strong>Avg Query Time:</strong> {$avg_time}ms<br>
                            <strong>Slow Queries (>100ms):</strong> $slow_queries
                        </div>
                        <div class='col-6'>
                            <strong>Log File Size:</strong> " . round(filesize($log_file) / 1024, 2) . " KB<br>
                            <strong>Last Updated:</strong> " . date('Y-m-d H:i:s', filemtime($log_file)) . "
                        </div>
                    </div>
                ";
            } else {
                $performance_data = '<p class="text-muted">No performance data available</p>';
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => $performance_data
            ]);
            break;
            
        case 'logs':
            // Get recent logs
            $log_file = __DIR__ . '/../logs/stats_queries.log';
            $logs_data = '';
            
            if (file_exists($log_file)) {
                $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recent_lines = array_slice($lines, -10); // Last 10 lines
                
                if (!empty($recent_lines)) {
                    $logs_data = '<div class="small">';
                    foreach (array_reverse($recent_lines) as $line) {
                        $logs_data .= '<div class="mb-1">' . htmlspecialchars($line) . '</div>';
                    }
                    $logs_data .= '</div>';
                } else {
                    $logs_data = '<p class="text-muted">No recent logs</p>';
                }
            } else {
                $logs_data = '<p class="text-muted">Log file not found</p>';
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => $logs_data
            ]);
            break;
            
        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid type requested'
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing the request',
        'debug' => $e->getMessage()
    ]);
}
?>
