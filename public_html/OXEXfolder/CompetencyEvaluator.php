<?php
/**
 * CompetencyEvaluator Class
 * 
 * A wrapper class that provides a clean interface for evaluating trainee competencies
 * across different parts of the system. This acts as a decorator for the pass standard functions.
 * 
 * Usage:
 * $evaluator = new CompetencyEvaluator($mysqli);
 * $status = $evaluator->evaluateCompetency($traineeKey, $competencyId);
 */
class CompetencyEvaluator {
    private $mysqli;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        
        // Include the core functions
        if (!function_exists('checkCompetencyStatus')) {
            require_once __DIR__ . '/pass_standard_functions.php';
        }
    }
    
    /**
     * Evaluate a trainee's competency status
     * 
     * @param string $traineeKey The trainee's unique key
     * @param int $competencyId The competency/table ID to evaluate
     * @param string|int|null $endDate Optional end date for time-based failures
     * @return array The competency status and breakdown
     */
    public function evaluateCompetency($traineeKey, $competencyId, $endDate = null) {
        return checkCompetencyStatus($traineeKey, $competencyId, $this->mysqli, $endDate);
    }
    
    /**
     * Get a simple pass/fail status for a competency
     * 
     * @param string $traineeKey The trainee's unique key
     * @param int $competencyId The competency/table ID to evaluate
     * @return string 'Passed', 'In Progress', 'Fail', or 'Not Applicable'
     */
    public function getSimpleStatus($traineeKey, $competencyId) {
        $status = $this->evaluateCompetency($traineeKey, $competencyId);
        return $status['overall_status'];
    }
    
    /**
     * Check if a trainee has passed a specific competency
     * 
     * @param string $traineeKey The trainee's unique key
     * @param int $competencyId The competency/table ID to evaluate
     * @return bool True if passed, false otherwise
     */
    public function hasPassed($traineeKey, $competencyId) {
        $status = $this->evaluateCompetency($traineeKey, $competencyId);
        return $status['overall_status'] === 'Passed';
    }
    
    /**
     * Get competency status for multiple competencies at once
     * 
     * @param string $traineeKey The trainee's unique key
     * @param array $competencyIds Array of competency IDs to evaluate
     * @return array Array of competency statuses keyed by competency ID
     */
    public function evaluateMultipleCompetencies($traineeKey, $competencyIds) {
        $results = [];
        foreach ($competencyIds as $competencyId) {
            $results[$competencyId] = $this->evaluateCompetency($traineeKey, $competencyId);
        }
        return $results;
    }
    
    /**
     * Get a summary of all competencies for a trainee
     * 
     * @param string $traineeKey The trainee's unique key
     * @return array Summary with counts and overall status
     */
    public function getCompetencySummary($traineeKey) {
        // Get all available competencies
        $query = "SELECT DISTINCT tbid, tab_name FROM tabs_tbl ORDER BY tab_name";
        $stmt = $this->mysqli->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $competencies = [];
        $passed = 0;
        $inProgress = 0;
        $failed = 0;
        $notApplicable = 0;
        
        while ($row = $result->fetch_assoc()) {
            $status = $this->evaluateCompetency($traineeKey, $row['tbid']);
            $competencies[] = [
                'tbid' => $row['tbid'],
                'name' => $row['tab_name'],
                'status' => $status['overall_status'],
                'breakdown' => $status['breakdown']
            ];
            
            switch ($status['overall_status']) {
                case 'Passed':
                    $passed++;
                    break;
                case 'In Progress':
                    $inProgress++;
                    break;
                case 'Fail':
                    $failed++;
                    break;
                case 'Not Applicable':
                    $notApplicable++;
                    break;
            }
        }
        $stmt->close();
        
        return [
            'competencies' => $competencies,
            'summary' => [
                'total' => count($competencies),
                'passed' => $passed,
                'in_progress' => $inProgress,
                'failed' => $failed,
                'not_applicable' => $notApplicable
            ]
        ];
    }
    
    /**
     * Get competency status with HTML formatting for display
     * 
     * @param string $traineeKey The trainee's unique key
     * @param int $competencyId The competency/table ID to evaluate
     * @return string HTML formatted competency status
     */
    public function getFormattedStatus($traineeKey, $competencyId) {
        $status = $this->evaluateCompetency($traineeKey, $competencyId);
        
        $statusClass = $this->getStatusClass($status['overall_status']);
        $statusIcon = $this->getStatusIcon($status['overall_status']);
        
        $html = "<div class='competency-status {$statusClass}'>";
        $html .= "<span class='status-icon'>{$statusIcon}</span>";
        $html .= "<span class='status-text'>{$status['overall_status']}</span>";
        $html .= "</div>";
        
        return $html;
    }
    
    /**
     * Get CSS class for status styling
     */
    private function getStatusClass($status) {
        switch ($status) {
            case 'Passed':
                return 'status-passed';
            case 'Fail':
                return 'status-failed';
            case 'In Progress':
                return 'status-in-progress';
            default:
                return 'status-not-applicable';
        }
    }
    
    /**
     * Get icon for status display
     */
    private function getStatusIcon($status) {
        switch ($status) {
            case 'Passed':
                return '✓';
            case 'Fail':
                return '✗';
            case 'In Progress':
                return '⟳';
            default:
                return '−';
        }
    }
}
?>
