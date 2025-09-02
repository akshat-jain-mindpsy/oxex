<?php
class StatsLogger {
    private $log_file;
    
    public function __construct() {
        $this->log_file = __DIR__ . '/../logs/stats_queries.log';
        
        // Create logs directory if it doesn't exist
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    
    public function logQuery($query, $params = [], $execution_time = 0) {
        $timestamp = date('Y-m-d H:i:s');
        $params_str = !empty($params) ? json_encode($params) : 'none';
        $log_entry = "[$timestamp] Query: $query | Params: $params_str | Time: {$execution_time}ms\n";
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    public function logFilter($filters) {
        $timestamp = date('Y-m-d H:i:s');
        $filters_str = json_encode($filters);
        $log_entry = "[$timestamp] Filters applied: $filters_str\n";
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    public function logError($error, $context = '') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] ERROR: $error | Context: $context\n";
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}
?>
