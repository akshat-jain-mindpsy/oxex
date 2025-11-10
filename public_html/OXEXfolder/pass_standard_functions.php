<?php
/**
 * Main function to evaluate a trainee's status for a given competency (table).
 * This is the only function that should be called externally.
 *
 * @param string $traineeKey The unique key for the trainee.
 * @param int $tbid The ID of the competency/table to check.
 * @param PDO $pdo The database connection object.
 * @param string|int|null $endDate The optional end date for the competency (format: YYYYMMDD or YYYY-MM-DD).
 * @return array A structured array with the overall status and a detailed breakdown.
 */
function checkCompetencyStatus($traineeKey, $tbid, $pdo, $endDate = null) {
    // Step 1: Fetch all rules for this competency, ordered to process parents first.
    $all_rules = _fetchAllRulesForCompetency($tbid, $pdo);

    if (empty($all_rules)) {
        return [
            'overall_status' => 'Not Applicable',
            'message' => 'No pass standards have been defined for this competency.',
            'breakdown' => []
        ];
    }

    // Step 2: Organize the flat list of rules into a parent-child hierarchy.
    $rule_tree = _buildRuleTree($all_rules);

    // Step 3: Evaluate each top-level (parent) rule tree.
    $all_parent_rules_passed = true;
    $results_breakdown = [];
    foreach ($rule_tree as $parent_rule) {
        // The recursive evaluation function does the heavy lifting.
        $parent_result = _evaluateRule($parent_rule, $traineeKey, $pdo);

        if (!$parent_result['is_passed']) {
            $all_parent_rules_passed = false;
        }
        $results_breakdown[] = $parent_result;
    }

    // Step 4: Determine and return the final overall status and detailed breakdown.
    $finalStatus = 'In Progress'; // Default to In Progress
    if ($all_parent_rules_passed) {
        $finalStatus = 'Passed';
    } else {
        // If not passed, check if they have failed due to a time limit
        if ($endDate !== null) {
            $today_int = (int)date('Ymd');
            // Allow YYYY-MM-DD or YYYYMMDD formats by stripping hyphens
            $endDate_int = (int)str_replace('-', '', $endDate); 
            if ($today_int > $endDate_int) {
                $finalStatus = 'Fail';
            }
        }
    }

    return [
        'overall_status' => $finalStatus,
        'breakdown' => $results_breakdown
    ];
}


// --- Helper Functions (Internal use within this file) ---

/**
 * Fetches a flat list of all rules associated with a competency.
 *
 * @param int $tbid The competency ID.
 * @param PDO $pdo The database connection.
 * @return array A list of rules.
 */
function _fetchAllRulesForCompetency($tbid, $pdo) {
    $query = "SELECT * FROM pass_standards WHERE tbid = ? AND is_active = 1 ORDER BY parent_standard_id ASC, psid ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tbid]);
    $rules = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Fetch OR fields if the main stid is NULL
        if (is_null($row['stid'])) {
            $or_query = "SELECT stid FROM pass_standard_fields WHERE standard_id = ?";
            $or_stmt = $pdo->prepare($or_query);
            $or_stmt->execute([$row['psid']]);
            $row['or_fields'] = [];
            while ($or_row = $or_stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['or_fields'][] = $or_row['stid'];
            }
        }
        
        // Parse subfield rules if they exist
        if (!empty($row['field_value']) && strpos($row['field_value'], 'SUBFIELD_RULES:') !== false) {
            $row['subfield_rules'] = _parseSubfieldRules($row['field_value']);
        }
        
        // Parse category_groups if they exist
        if (!empty($row['field_value']) && strpos($row['field_value'], 'category_groups') !== false) {
            $row['category_groups'] = _parseCategoryGroups($row['field_value']);
        }
        
        $rules[] = $row;
    }
    return $rules;
}

/**
 * Parses subfield rules from the field_value column.
 * 
 * @param string $field_value The field_value string that may contain SUBFIELD_RULES
 * @return array Array of subfield rules or empty array if none found
 */
function _parseSubfieldRules($field_value) {
    if (empty($field_value) || strpos($field_value, 'SUBFIELD_RULES:') === false) {
        return [];
    }
    
    try {
        // Extract the JSON part after SUBFIELD_RULES:
        $json_part = str_replace('SUBFIELD_RULES:', '', $field_value);
        $json_part = trim($json_part);
        
        $rules = json_decode($json_part, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($rules)) {
            return $rules;
        }
    } catch (Exception $e) {
        error_log("Error parsing subfield rules: " . $e->getMessage());
    }
    
    return [];
}

/**
 * Parses category_groups from the field_value column.
 * 
 * @param string $field_value The field_value string that may contain category_groups JSON
 * @return array Array of category groups or empty array if none found
 */
function _parseCategoryGroups($field_value) {
    if (empty($field_value)) {
        return [];
    }
    
    try {
        $data = json_decode($field_value, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['category_groups']) && is_array($data['category_groups'])) {
            return $data['category_groups'];
        }
    } catch (Exception $e) {
        error_log("Error parsing category groups: " . $e->getMessage());
    }
    
    return [];
}

/**
 * Extracts plain literal select_val values from a mixed field_value string that may contain SUBFIELD_RULES segments.
 * Returns an array of non-empty literal values (excluding any segments containing SUBFIELD_RULES:).
 * Simple and conservative: supports equality/IN via a list of values separated by '|'.
 */
function _extractLiteralValuesFromFieldValue($field_value) {
    if (empty($field_value)) {
        return [];
    }
    $parts = explode('|', (string)$field_value);
    $literals = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') { continue; }
        if (strpos($part, 'SUBFIELD_RULES:') !== false) { continue; }
        $literals[] = $part;
    }
    return $literals;
}

/**
 * Evaluates category_groups and returns a result.
 *
 * @param array $rule The rule with category_groups to evaluate.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @param array|null $population_logkeys A pre-filtered list of logkeys from a parent rule.
 * @return array A detailed result object for this rule with category groups breakdown.
 */
function _evaluateCategoryGroups($rule, $traineeKey, $pdo, $population_logkeys = null) {
    $category_results = [];
    $all_groups_passed = true;
    $tbid = $rule['tbid'] ?? null;
    
    // Evaluate each category group
    foreach ($rule['category_groups'] as $group) {
        try {
            $group_result = _evaluateSingleCategoryGroup($rule, $group, $traineeKey, $pdo, $population_logkeys, $tbid);
            $category_results[] = $group_result;
            
            if (!$group_result['is_passed']) {
                $all_groups_passed = false;
            }
        } catch (Exception $e) {
            // Handle invalid category group data gracefully
            error_log('[PASS DEBUG] Error evaluating category group: ' . $e->getMessage());
            $category_results[] = [
                'group_name' => "Category Group (stid: " . ($group['stid'] ?? 'N/A') . ") - ERROR",
                'is_passed' => false,
                'current_value' => 0,
                'required_value' => 0,
                'error' => $e->getMessage()
            ];
            $all_groups_passed = false;
        }
    }
    
    // Determine current_value based on category logic
    // If there's a single category group, use its actual current_value
    // Otherwise, count how many groups passed
    $current_value = 0;
    $required_value = count($rule['category_groups']);
    
    if (count($rule['category_groups']) === 1) {
        // Single category group - use its actual count
        $current_value = $category_results[0]['current_value'] ?? 0;
        $required_value = $category_results[0]['required_value'] ?? 0;
    } else {
        // Multiple category groups - count how many passed
        $current_value = count(array_filter($category_results, function($r) { return $r['is_passed']; }));
        // Support "N of M" groups by using parent rule's required_value if provided
        if (isset($rule['required_value']) && is_numeric($rule['required_value']) && (int)$rule['required_value'] > 0) {
            $required_value = (int)$rule['required_value'];
        }
        // Recompute pass using threshold rather than strict ALL
        $all_groups_passed = ($current_value >= $required_value);
    }
    
    // Check if this rule has children and evaluate them if the rule passed
    $child_results = [];
    $all_children_passed = true;
    $final_is_passed = $all_groups_passed;
    
    if ($all_groups_passed && !empty($rule['children'])) {
        // Get the specific population of logkeys that satisfied THIS rule
        $new_population_logkeys = _getLogkeysForCategoryGroupRule($rule, $traineeKey, $pdo, $population_logkeys);
        
        foreach ($rule['children'] as &$child_rule) {
            $child_result = _evaluateRule($child_rule, $traineeKey, $pdo, $new_population_logkeys);
            if (!$child_result['is_passed']) {
                $all_children_passed = false;
            }
            $child_results[] = $child_result;
        }
        
        // The final status depends on this rule AND all its children passing
        $final_is_passed = $all_groups_passed && $all_children_passed;
    }
    
    return [
        'standard_name' => $rule['standard_name'],
        'is_passed' => $final_is_passed,
        'current_value' => $current_value,
        'required_value' => $required_value,
        'category_groups' => $category_results,
        'children' => $child_results
    ];
}

/**
 * Evaluates a single category group.
 *
 * @param array $main_rule The main rule containing the category groups.
 * @param array $group The specific category group to evaluate.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @param array|null $population_logkeys A pre-filtered list of logkeys from a parent rule.
 * @param int|null $tbid The table/competency ID to filter logs.
 * @return array A detailed result object for this category group.
 */
function _evaluateSingleCategoryGroup($main_rule, $group, $traineeKey, $pdo, $population_logkeys = null, $tbid = null) {
    // Use tbid from main_rule if not provided
    if (empty($tbid) && !empty($main_rule['tbid'])) {
        $tbid = $main_rule['tbid'];
    }
    
    // Check if this is a simple requirement or has subfield rules
    if (isset($group['subfield_rules']) && is_array($group['subfield_rules']) && !empty($group['subfield_rules'])) {
        // Has subfield rules - evaluate each subfield
        return _evaluateCategoryGroupWithSubfields($group, $traineeKey, $pdo, $population_logkeys, $tbid);
    } else {
        // Simple requirement
        return _evaluateCategoryGroupSimple($group, $traineeKey, $pdo, $population_logkeys, $tbid);
    }
}

/**
 * Evaluates a category group with subfield rules.
 */
function _evaluateCategoryGroupWithSubfields($group, $traineeKey, $pdo, $population_logkeys = null, $tbid = null) {
    $subfield_results = [];
    $all_subfields_passed = true;
    
    // Evaluate each subfield rule; support any_of groups
    foreach ($group['subfield_rules'] as $subfield_rule) {
        try {
            // any_of: treat as a single requirement that passes if any alternative passes
            if (isset($subfield_rule['any_of']) && is_array($subfield_rule['any_of']) && !empty($subfield_rule['any_of'])) {
                $alternatives = $subfield_rule['any_of'];
                $alt_results = [];
                $any_passed = false;
                foreach ($alternatives as $alt) {
                    // Skip alternatives with invalid subfield_value
                    if (empty($alt['subfield_value']) || 
                        !is_numeric($alt['subfield_value']) || 
                        trim((string)$alt['subfield_value']) === '') {
                        continue;
                    }
                    $alt_result = _evaluateCategorySubfieldRule($group, $alt, $traineeKey, $pdo, $population_logkeys, $tbid);
                    $alt_results[] = $alt_result;
                    if ($alt_result['is_passed']) {
                        $any_passed = true;
                    }
                }
                $subfield_results[] = [
                    'subfield_value' => null,
                    'subfield_name' => 'any_of',
                    'requirement_type' => 'ANY_OF',
                    'is_passed' => $any_passed,
                    'current_value' => array_sum(array_map(function($r){ return $r['is_passed'] ? 1 : 0; }, $alt_results)),
                    'required_value' => 1,
                    'alternatives' => $alt_results
                ];
                if (!$any_passed) {
                    $all_subfields_passed = false;
                }
            } else {
                // Skip subfield rules with invalid subfield_value (unless it's a TOTAL_COUNT without subfield)
                $skip_rule = false;
                if (!empty($subfield_rule['subfield_value']) && 
                    (!is_numeric($subfield_rule['subfield_value']) || trim((string)$subfield_rule['subfield_value']) === '')) {
                    // Invalid subfield_value - skip this rule
                    $skip_rule = true;
                }
                
                if (!$skip_rule) {
                    $subfield_result = _evaluateCategorySubfieldRule($group, $subfield_rule, $traineeKey, $pdo, $population_logkeys, $tbid);
                    $subfield_results[] = $subfield_result;
                    if (!$subfield_result['is_passed']) {
                        $all_subfields_passed = false;
                    }
                } else {
                    // Add a failed result for invalid rule
                    $subfield_results[] = [
                        'subfield_value' => $subfield_rule['subfield_value'] ?? '',
                        'subfield_name' => 'Invalid Rule',
                        'requirement_type' => $subfield_rule['requirement_type'] ?? 'TOTAL_COUNT',
                        'is_passed' => false,
                        'current_value' => 0,
                        'required_value' => 0,
                        'error' => 'Invalid subfield_value'
                    ];
                    $all_subfields_passed = false;
                }
            }
        } catch (Exception $e) {
            // Handle invalid subfield rule data gracefully
            error_log('[PASS DEBUG] Error evaluating subfield rule: ' . $e->getMessage());
            $subfield_results[] = [
                'subfield_value' => $subfield_rule['subfield_value'] ?? '',
                'subfield_name' => 'Error',
                'requirement_type' => $subfield_rule['requirement_type'] ?? 'TOTAL_COUNT',
                'is_passed' => false,
                'current_value' => 0,
                'required_value' => 0,
                'error' => $e->getMessage()
            ];
            $all_subfields_passed = false;
        }
    }
    
    return [
        'group_name' => "Category Group (stid: " . ($group['stid'] ?? 'N/A') . ")",
        'is_passed' => $all_subfields_passed,
        'current_value' => count(array_filter($subfield_results, function($r) { return $r['is_passed']; })),
        'required_value' => count($group['subfield_rules']),
        'subfield_rules' => $subfield_results
    ];
}

/**
 * Evaluates a category group with a simple requirement.
 */
function _evaluateCategoryGroupSimple($group, $traineeKey, $pdo, $population_logkeys = null, $tbid = null) {
    $params = [$traineeKey];
    $types = 's';
    $where_clauses = ["trainkey = ?"];
    
    // Add tbid filter if provided (from main_rule)
    if (!empty($tbid) && $tbid !== '' && is_numeric($tbid) && trim((string)$tbid) !== '') {
        $tbid_int = (int)$tbid;
        if ($tbid_int > 0) {
            $where_clauses[] = "tbid = ?";
            $types .= 'i';
            $params[] = $tbid_int;
            error_log('[PASS DEBUG] Added tbid to params: ' . $tbid_int);
        } else {
            error_log('[PASS DEBUG] tbid validation failed: tbid_int=' . $tbid_int);
        }
    } else {
        error_log('[PASS DEBUG] tbid not added: empty=' . var_export(empty($tbid), true) . ', is_numeric=' . var_export(is_numeric($tbid), true));
    }
    
    // Add STID condition (supports group['stids'] for OR-of-stids)
    if (!empty($group['stids']) && is_array($group['stids'])) {
        // Filter out invalid stids before building query
        $valid_stids = array_filter($group['stids'], function($sid) {
            return is_numeric($sid) && trim((string)$sid) !== '' && (int)$sid > 0;
        });
        if (!empty($valid_stids)) {
            $placeholders = implode(',', array_fill(0, count($valid_stids), '?'));
            $where_clauses[] = "stid IN ($placeholders)";
            $types .= str_repeat('i', count($valid_stids));
            foreach ($valid_stids as $sid) {
                $params[] = (int)$sid;
            }
            error_log('[PASS DEBUG] Added stids to params: ' . json_encode($valid_stids));
        } else {
            error_log('[PASS DEBUG] No valid stids found in group[stids]');
        }
    } elseif (isset($group['stid']) && $group['stid'] !== '' && is_numeric($group['stid']) && trim((string)$group['stid']) !== '') {
        $stid_int = (int)$group['stid'];
        if ($stid_int > 0) {
            $where_clauses[] = "stid = ?";
            $types .= 'i';
            $params[] = $stid_int;
            error_log('[PASS DEBUG] Added stid to params: ' . $stid_int);
        } else {
            error_log('[PASS DEBUG] stid validation failed: stid_int=' . $stid_int . ', original=' . var_export($group['stid'], true));
        }
    } else {
        error_log('[PASS DEBUG] stid not added: isset=' . var_export(isset($group['stid']), true) . ', empty=' . var_export(empty($group['stid']), true) . ', is_numeric=' . var_export(is_numeric($group['stid'] ?? null), true));
    }
    
    // Handle parent population if provided
    if ($population_logkeys !== null) {
        if (empty($population_logkeys)) {
            $current_value = 0;
        } else {
            $placeholders = implode(',', array_fill(0, count($population_logkeys), '?'));
            $where_clauses[] = "logkey IN ($placeholders)";
            $types .= str_repeat('s', count($population_logkeys));
            array_push($params, ...$population_logkeys);
        }
    }
    
    // Build query based on requirement type
    $base_query = "SELECT COUNT(DISTINCT logkey) FROM trainee_log";
    
    // Handle PER_CASE_MINIMUM with minimum_threshold
    if (isset($group['requirement_type']) && $group['requirement_type'] === 'PER_CASE_MINIMUM' && !empty($group['minimum_threshold'])) {
        // Count distinct logkeys (cases) where the MAXIMUM field value (select_val) per case is >= minimum_threshold
        // The group's stid represents the field to check (e.g., "Number of sessions so far")
        // We need to find the maximum value per logkey first, then filter by threshold
        $base_query = "
            SELECT COUNT(DISTINCT max_sessions.logkey)
            FROM (
                SELECT 
                    t1.logkey,
                    MAX(CAST(t1.select_val AS DECIMAL(10,2))) AS max_value
                FROM trainee_log t1
                WHERE t1.trainkey = ? ";
        // Support stid OR stids
        $types = 's';
        $params = [$traineeKey];
        if (!empty($group['stids']) && is_array($group['stids'])) {
            // Filter out invalid stids before building query
            $valid_stids = array_filter($group['stids'], function($sid) {
                return is_numeric($sid) && trim((string)$sid) !== '' && (int)$sid > 0;
            });
            if (!empty($valid_stids)) {
                $placeholders = implode(',', array_fill(0, count($valid_stids), '?'));
                $base_query .= " AND t1.stid IN ($placeholders)";
                $types .= str_repeat('i', count($valid_stids));
                foreach ($valid_stids as $sid) {
                    $params[] = (int)$sid;
                }
            }
        } elseif (isset($group['stid']) && $group['stid'] !== '' && is_numeric($group['stid']) && trim((string)$group['stid']) !== '') {
            $stid_int = (int)$group['stid'];
            if ($stid_int > 0) {
                $base_query .= " AND t1.stid = ?";
                $types .= 'i';
                $params[] = $stid_int;
            }
        }
        if (!empty($tbid) && $tbid !== '' && is_numeric($tbid) && trim((string)$tbid) !== '') {
            $tbid_int = (int)$tbid;
            if ($tbid_int > 0) {
                $base_query .= " AND t1.tbid = ?";
                $params[] = $tbid_int;
                $types .= 'i';
            }
        }
        $base_query .= "
                GROUP BY t1.logkey
            ) max_sessions
            WHERE max_sessions.max_value >= ?";
        $params[] = (float)$group['minimum_threshold'];
        $types .= 'd';
        
        // Add population filter if provided
        if ($population_logkeys !== null && !empty($population_logkeys)) {
            $placeholders = implode(',', array_fill(0, count($population_logkeys), '?'));
            $base_query .= " AND max_sessions.logkey IN ($placeholders)";
            $types .= str_repeat('s', count($population_logkeys));
            array_push($params, ...$population_logkeys);
        }
        
        // Rebuild where clauses (empty since conditions are in base_query)
        $where_clauses = [];
    } else {
        // Standard query - use existing where_clauses and params built earlier
        // No need to rebuild them, they're already correct from lines 306-339
    }
    
    // Execute query
    if (!isset($current_value)) {
        $final_query = $base_query;
        if (!empty($where_clauses)) {
            $final_query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        

        // Check for empty strings in params
        foreach ($params as $idx => $param) {
            if ($param === '' || (is_string($param) && trim($param) === '')) {
                error_log('[PASS DEBUG] ERROR: Empty string param at index ' . $idx . ': ' . var_export($param, true));
            }
            if (is_numeric($param) && (int)$param <= 0 && $idx > 0) {
                error_log('[PASS DEBUG] WARNING: Non-positive numeric param at index ' . $idx . ': ' . var_export($param, true));
            }
        }
        
        $stmt = $pdo->prepare($final_query);
        $stmt->execute($params);
        $current_value = $stmt->fetchColumn();
    }
    
    $current_value = $current_value ?? 0;
    $required_value = isset($group['required_value']) ? (int)$group['required_value'] : 0;
    $is_passed = ($current_value >= $required_value);
    
    return [
        'group_name' => "Category Group (stid: {$group['stid']})",
        'is_passed' => $is_passed,
        'current_value' => $current_value,
        'required_value' => $required_value,
        'requirement_type' => $group['requirement_type'] ?? 'TOTAL_COUNT',
        'minimum_threshold' => $group['minimum_threshold'] ?? null
    ];
}

/**
 * Evaluates a subfield rule within a category group.
 */
function _evaluateCategorySubfieldRule($group, $subfield_rule, $traineeKey, $pdo, $population_logkeys = null, $tbid = null) {
    // Similar to _evaluateSubfieldIndividualRule but for category groups
    $params = [$traineeKey];
    $types = 's';
    $where_clauses = ["trainkey = ?"];
    
    // Add tbid filter if provided
    if (!empty($tbid) && $tbid !== '' && is_numeric($tbid) && trim((string)$tbid) !== '') {
        $tbid_int = (int)$tbid;
        if ($tbid_int > 0) {
            $where_clauses[] = "tbid = ?";
            $types .= 'i';
            $params[] = $tbid_int;
        }
    }
    
    // Add main STID (from group)
    if (isset($group['stid']) && $group['stid'] !== '' && is_numeric($group['stid']) && trim((string)$group['stid']) !== '') {
        $stid_int = (int)$group['stid'];
        if ($stid_int > 0) {
            $where_clauses[] = "stid = ?";
            $types .= 'i';
            $params[] = $stid_int;
        }
    }
    
    // Add subfield value (PID filter)
    $sub_pid = null;
    if (!empty($subfield_rule['subfield_value'] ?? '') && 
        is_numeric($subfield_rule['subfield_value']) && 
        trim((string)$subfield_rule['subfield_value']) !== '') {
        $pid = $subfield_rule['subfield_value'];
        $sub_pid = (int)$pid;
        if ($sub_pid > 0) {
            // pid filter (direct)
            $where_clauses[] = "pid = ?";
            $types .= 'i';
            $params[] = $sub_pid;
            // Also map PID to STID (usually redundant but harmless)
            $stid_query = "SELECT stid FROM select_gen WHERE pid = ?";
            $stid_stmt = $pdo->prepare($stid_query);
            $stid_stmt->execute([$sub_pid]);
            $stid_row = $stid_stmt->fetch(PDO::FETCH_ASSOC);
            if ($stid_row && !empty($stid_row['stid']) && is_numeric($stid_row['stid'])) {
                $stid_val = (int)$stid_row['stid'];
                if ($stid_val > 0) {
                    $where_clauses[] = "stid = ?";
                    $types .= 'i';
                    $params[] = $stid_val;
                }
            }
        }
    }
    
    // If this is a child rule, constrain the query to the parent's population of logkeys
    if ($population_logkeys !== null) {
        if (empty($population_logkeys)) {
            $current_value = 0;
            $required_value = 0;
            if (isset($subfield_rule['specific_value']) && 
                is_numeric($subfield_rule['specific_value']) && 
                trim((string)$subfield_rule['specific_value']) !== '') {
                $required_value = (int)$subfield_rule['specific_value'];
            }
            $is_passed = ($current_value >= $required_value);
            return [
                'subfield_value' => $subfield_rule['subfield_value'] ?? '',
                'subfield_name' => _getSubfieldName($subfield_rule['subfield_value'] ?? '', $pdo),
                'requirement_type' => $subfield_rule['requirement_type'] ?? 'TOTAL_COUNT',
                'is_passed' => $is_passed,
                'current_value' => $current_value,
                'required_value' => $required_value
            ];
        } else {
            $placeholders = implode(',', array_fill(0, count($population_logkeys), '?'));
            $where_clauses[] = "logkey IN ($placeholders)";
            $types .= str_repeat('s', count($population_logkeys));
            array_push($params, ...$population_logkeys);
        }
    }
    
    // Build the query based on requirement type
    $base_query = "";
    $sub_req_type = $subfield_rule['requirement_type'] ?? 'TOTAL_COUNT';
    switch ($sub_req_type) {
        case 'PER_CASE_MINIMUM':
            // Count distinct cases where per-case maximum numeric select_val meets minimum_threshold
            $min_threshold = null;
            if (isset($subfield_rule['minimum_threshold']) && 
                is_numeric($subfield_rule['minimum_threshold']) && 
                trim((string)$subfield_rule['minimum_threshold']) !== '') {
                $min_threshold = (float)$subfield_rule['minimum_threshold'];
            }
            $specific_required = trim((string)($subfield_rule['specific_value'] ?? '0'));
            $required_value = (is_numeric($specific_required) && $specific_required !== '') ? (int)$specific_required : 0;
            
            $inner_params = [$traineeKey];
            $inner_query = "SELECT COUNT(DISTINCT max_sessions.logkey) FROM ( SELECT t1.logkey, MAX(CASE WHEN t1.select_val IS NOT NULL AND t1.select_val != '' AND t1.select_val ~ '^[0-9]+\\.?[0-9]*$' THEN CAST(t1.select_val AS DECIMAL(10,2)) ELSE NULL END) AS max_value FROM trainee_log t1 WHERE t1.trainkey = ?";
            
            // tbid
            if (!empty($tbid) && is_numeric($tbid) && (int)$tbid > 0) {
                $inner_query .= " AND t1.tbid = ?";
                $inner_params[] = (int)$tbid;
            }
            
            // main stid (from group)
            if (isset($group['stid']) && is_numeric($group['stid']) && (int)$group['stid'] > 0) {
                $inner_query .= " AND t1.stid = ?";
                $inner_params[] = (int)$group['stid'];
            }
            
            // pid (subfield filter)
            if (!is_null($sub_pid) && $sub_pid !== '' && is_numeric($sub_pid) && (int)$sub_pid > 0) {
                $inner_query .= " AND t1.pid = ?";
                $inner_params[] = (int)$sub_pid;
            }
            
            // parent population
            if ($population_logkeys !== null && !empty($population_logkeys)) {
                $ph = implode(',', array_fill(0, count($population_logkeys), '?'));
                $inner_query .= " AND t1.logkey IN ($ph)";
                foreach ($population_logkeys as $lk) { $inner_params[] = $lk; }
            }
            
            $inner_query .= " GROUP BY t1.logkey ) max_sessions WHERE max_sessions.max_value >= ?";
            $inner_params[] = ($min_threshold !== null && $min_threshold > 0 ? $min_threshold : 0.0);
            $stmt = $pdo->prepare($inner_query);
            $stmt->execute($inner_params);
            $current_value = (int)$stmt->fetchColumn();
            $is_passed = ($current_value >= $required_value);
            return [
                'subfield_value' => $subfield_rule['subfield_value'] ?? '',
                'subfield_name' => _getSubfieldName($subfield_rule['subfield_value'] ?? '', $pdo),
                'requirement_type' => 'PER_CASE_MINIMUM',
                'is_passed' => $is_passed,
                'current_value' => $current_value,
                'required_value' => $required_value
            ];
        case 'UNIQUE_VALUES':
            // Count distinct values for the subfield (pid or select_val), default to pid
            $base_query = "SELECT COUNT(DISTINCT pid) FROM trainee_log";
            break;
        case 'UNIQUE_VALUES_IN_RANGE':
            $base_query = "SELECT COUNT(DISTINCT pid) FROM trainee_log";
            // Add range condition if specific_value contains a range
            if (!empty($subfield_rule['specific_value']) && strpos($subfield_rule['specific_value'], '-') !== false) {
                list($start, $end) = explode('-', $subfield_rule['specific_value']);
                // For subfield ranges, apply to a numeric select_val if present
                $where_clauses[] = "CASE WHEN select_val ~ '^[0-9]+$' THEN CAST(select_val AS INTEGER) ELSE NULL END BETWEEN ? AND ?";
                $types .= 'ii';
                $params[] = trim($start);
                $params[] = trim($end);
            }
            break;
        case 'TOTAL_COUNT':
        default:
            $base_query = "SELECT COUNT(*) FROM trainee_log";
            break;
    }
    
    // Execute the query
    $final_query = $base_query . " WHERE " . implode(" AND ", $where_clauses);
    $stmt = $pdo->prepare($final_query);
    $stmt->execute($params);
    $current_value = $stmt->fetchColumn();

    $current_value = $current_value ?? 0;
    $required_value_raw = trim((string)($subfield_rule['specific_value'] ?? '0'));
    $required_value = is_numeric($required_value_raw) ? (int)$required_value_raw : 0;

    // Flexible comparison for UNIQUE_VALUES: allow ranges ("X-Y") or CSV lists ("a, b, c")
    $is_passed = false;
    if ($sub_req_type === 'UNIQUE_VALUES' && $required_value_raw !== '') {
        if (strpos($required_value_raw, '-') !== false) {
            // Range format: "X-Y"
            list($start, $end) = array_map('trim', explode('-', $required_value_raw));
            $is_passed = ($current_value >= (int)$start && $current_value <= (int)$end);
        } elseif (strpos($required_value_raw, ',') !== false) {
            // CSV format: "a, b, c"
            $allowed_values = array_map('trim', explode(',', $required_value_raw));
            $is_passed = in_array((string)$current_value, $allowed_values);
        } else {
            // Single value
            $is_passed = ($current_value >= $required_value);
        }
    } else {
        $is_passed = ($current_value >= $required_value);
    }
    
    return [
        'subfield_value' => $subfield_rule['subfield_value'] ?? '',
        'subfield_name' => _getSubfieldName($subfield_rule['subfield_value'] ?? '', $pdo),
        'requirement_type' => $subfield_rule['requirement_type'] ?? 'TOTAL_COUNT',
        'is_passed' => $is_passed,
        'current_value' => $current_value,
        'required_value' => $required_value
    ];
}

/**
 * Organizes a flat list of rules into a hierarchical tree.
 *
 * @param array $rules The flat list of rules from the database.
 * @return array The rules organized into a parent-child tree.
 */
function _buildRuleTree(array &$rules) {
    $tree = [];
    $rules_by_id = [];
    foreach ($rules as $rule) {
        $rules_by_id[$rule['psid']] = $rule;
        $rules_by_id[$rule['psid']]['children'] = [];
    }

    foreach ($rules_by_id as $psid => &$rule) {
        if (!empty($rule['parent_standard_id'])) {
            $rules_by_id[$rule['parent_standard_id']]['children'][] = &$rule;
        } else {
            $tree[] = &$rule;
        }
    }
    return $tree;
}

/**
 * The core evaluation engine. Recursively evaluates a rule and its children.
 *
 * @param array $rule The rule to evaluate.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @param array|null $population_logkeys A pre-filtered list of logkeys from a parent rule.
 * @return array A detailed result object for this specific rule.
 */
function _evaluateRule($rule, $traineeKey, $pdo, $population_logkeys = null) {
    // Check if this rule has category_groups
    if (!empty($rule['category_groups'])) {
        return _evaluateCategoryGroups($rule, $traineeKey, $pdo, $population_logkeys);
    }
    
    // Check if this rule has subfield rules
    if (!empty($rule['subfield_rules'])) {
        return _evaluateSubfieldRule($rule, $traineeKey, $pdo, $population_logkeys);
    }
    
    // Original evaluation logic for regular rules
    $params = [$traineeKey];
    $types = 's';
    
    // --- Step A: Build the SQL query for the current rule ---
    $base_query = "";
    $where_clauses = ["trainkey = ?"];

    // Add tbid filter to only check logs from the correct table/competency
    if (!empty($rule['tbid']) && is_numeric($rule['tbid']) && (int)$rule['tbid'] > 0) {
        $where_clauses[] = "tbid = ?";
        $types .= 'i';
        $params[] = (int)$rule['tbid'];
    }

    // Handle TOTAL_HOURS as a special case (legacy single-field hours)
    if (($rule['requirement_type'] ?? '') === 'TOTAL_HOURS') {
        // Sum hours from a single stid (default 60) using robust HH:MM or decimal parsing
        $hours_stid = isset($rule['stid']) && !is_null($rule['stid']) ? (int)$rule['stid'] : 60;
        $base_query = "SELECT SUM(CASE 
                WHEN select_val IS NOT NULL AND select_val != '0' AND select_val != '00:00'
                     AND SPLIT_PART(select_val, ':', 1) ~ '^[0-9]+$' AND SPLIT_PART(select_val, ':', 2) ~ '^[0-9]+$'
                THEN (CAST(SPLIT_PART(select_val, ':', 1) AS INTEGER) * 60 + CAST(SPLIT_PART(select_val, ':', 2) AS INTEGER)) / 60.0
                WHEN select_val ~ '^[0-9]+(\.[0-9]+)?$' THEN CAST(select_val AS NUMERIC)
                ELSE 0 END) FROM trainee_log";
        $where_clauses[] = "stid = ?";
        $types .= 'i';
        $params[] = $hours_stid;
    }
    // Handle TOTAL_HOURS_COMBINED across multiple stids provided in field_value JSON
    elseif (($rule['requirement_type'] ?? '') === 'TOTAL_HOURS_COMBINED' && !empty($rule['field_value'])) {
        // Expect field_value JSON: {"total_of":[{"category_stid":"60","category_value":"X","max_value":40},...]}
        $total_hours = 0.0;
        try {
            $data = json_decode($rule['field_value'], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['total_of']) && is_array($data['total_of'])) {
                // Calculate hours for each source separately (to handle category_value and max_value)
                foreach ($data['total_of'] as $source) {
                    if (empty($source['category_stid'])) {
                        continue;
                    }
                    
                    $category_stid = (int)$source['category_stid'];
                    if ($category_stid <= 0) {
                        continue; // Skip invalid category_stid
                    }
                    $category_value = isset($source['category_value']) ? $source['category_value'] : '';
                    $max_value = isset($source['max_value']) && is_numeric($source['max_value']) ? (float)$source['max_value'] : null;
                    
                    // Build query for this source
                    $source_params = [$traineeKey];
                    $source_where = ["trainkey = ?"];
                    
                    // Add tbid filter
                    if (!empty($rule['tbid']) && is_numeric($rule['tbid']) && (int)$rule['tbid'] > 0) {
                        $source_where[] = "tbid = ?";
                        $source_params[] = (int)$rule['tbid'];
                    }
                    
                    // Add category_stid filter
                    $source_where[] = "stid = ?";
                    $source_params[] = $category_stid;
                    
                    // Add category_value filter if provided (not empty)
                    if (!empty($category_value)) {
                        $source_where[] = "select_val = ?";
                        $source_params[] = $category_value;
                    }
                    
                    // If this is a child rule, constrain to parent's population of logkeys
                    if ($population_logkeys !== null) {
                        if (empty($population_logkeys)) {
                            // Skip this source if parent population is empty
                            $source_hours = 0.0;
                            $total_hours += $source_hours;
                            continue;
                        } else {
                            $placeholders = implode(',', array_fill(0, count($population_logkeys), '?'));
                            $source_where[] = "logkey IN ($placeholders)";
                            array_push($source_params, ...$population_logkeys);
                        }
                    }
                    
                    // Calculate hours for this source
                    $source_query = "SELECT SUM(CASE 
                        WHEN select_val IS NOT NULL AND select_val != '0' AND select_val != '00:00'
                             AND SPLIT_PART(select_val, ':', 1) ~ '^[0-9]+$' AND SPLIT_PART(select_val, ':', 2) ~ '^[0-9]+$'
                        THEN (CAST(SPLIT_PART(select_val, ':', 1) AS INTEGER) * 60 + CAST(SPLIT_PART(select_val, ':', 2) AS INTEGER)) / 60.0
                        WHEN select_val ~ '^[0-9]+(\.[0-9]+)?$' THEN CAST(select_val AS NUMERIC)
                        ELSE 0 END) FROM trainee_log WHERE " . implode(" AND ", $source_where);
                    
                    $source_stmt = $pdo->prepare($source_query);
                    $source_stmt->execute($source_params);
                    $source_hours = (float)($source_stmt->fetchColumn() ?? 0.0);
                    
                    // Apply max_value cap if provided
                    if ($max_value !== null && $source_hours > $max_value) {
                        $source_hours = $max_value;
                    }
                    
                    $total_hours += $source_hours;
                }
            }
        } catch (Exception $e) {
            error_log("Error parsing TOTAL_HOURS_COMBINED field_value: " . $e->getMessage());
            $total_hours = 0.0;
        }
        
        // Set current_value directly from calculated total
        $current_value = $total_hours;
        // No query execution needed for this case
        $base_query = "";
        $where_clauses = [];
    }
    // Handle PER_CASE_MINIMUM at rule level (count cases whose per-case max value meets minimum_threshold)
    elseif (($rule['requirement_type'] ?? '') === 'PER_CASE_MINIMUM' && !empty($rule['minimum_threshold']) && (!empty($rule['stid']) || !empty($rule['or_fields']))) {
        $base_query = "
            SELECT COUNT(DISTINCT max_sessions.logkey)
            FROM (
                SELECT 
                    t1.logkey,
                    MAX(CASE 
                        WHEN t1.select_val IS NOT NULL AND t1.select_val != '' AND t1.select_val ~ '^[0-9]+\\.?[0-9]*$'
                        THEN CAST(t1.select_val AS DECIMAL(10,2))
                        ELSE NULL
                    END) AS max_value
                FROM trainee_log t1
                WHERE t1.trainkey = ? ";
        $types = 's';
        $params = [$traineeKey];
        if (!empty($rule['or_fields'])) {
            $placeholders = implode(',', array_fill(0, count($rule['or_fields']), '?'));
            $base_query .= " AND t1.stid IN ($placeholders)";
            $types .= str_repeat('i', count($rule['or_fields']));
            array_push($params, ...array_map('intval', $rule['or_fields']));
        } elseif (!empty($rule['stid']) && is_numeric($rule['stid']) && (int)$rule['stid'] > 0) {
            $base_query .= " AND t1.stid = ?";
            $types .= 'i';
            $params[] = (int)$rule['stid'];
        }
        if (!empty($rule['tbid']) && is_numeric($rule['tbid']) && (int)$rule['tbid'] > 0) {
            $base_query .= " AND t1.tbid = ?";
            $types .= 'i';
            $params[] = (int)$rule['tbid'];
        }
        if ($population_logkeys !== null && !empty($population_logkeys)) {
            $placeholders = implode(',', array_fill(0, count($population_logkeys), '?'));
            $base_query .= " AND t1.logkey IN ($placeholders)";
            $types .= str_repeat('s', count($population_logkeys));
            array_push($params, ...$population_logkeys);
        }
        $base_query .= "
                GROUP BY t1.logkey
            ) max_sessions
            WHERE max_sessions.max_value >= ?";
        $types .= 'd';
        $params[] = (float)$rule['minimum_threshold'];
        // No additional where_clauses for this special-case query
        $where_clauses = [];
    } else {
        // Logic for all other requirement types which use trainee_log
        switch ($rule['requirement_type']) {
            case 'UNIQUE_VALUES':
            case 'UNIQUE_VALUES_IN_RANGE':
                $base_query = "SELECT COUNT(DISTINCT select_val) FROM trainee_log";
                break;
            case 'TOTAL_COUNT':
            default:
                $base_query = "SELECT COUNT(*) FROM trainee_log";
                break;
        }
        
        // Add STID condition (single field or multiple OR fields)
        if (!empty($rule['or_fields'])) {
            $placeholders = implode(',', array_fill(0, count($rule['or_fields']), '?'));
            $where_clauses[] = "stid IN ($placeholders)";
            $types .= str_repeat('i', count($rule['or_fields']));
            array_push($params, ...$rule['or_fields']);
        } elseif (!is_null($rule['stid']) && is_numeric($rule['stid']) && (int)$rule['stid'] > 0) {
            $where_clauses[] = "stid = ?";
            $types .= 'i';
            $params[] = (int)$rule['stid'];
        }

        // Add field_value condition
        if (!empty($rule['field_value'])) {
            if ($rule['requirement_type'] === 'UNIQUE_VALUES_IN_RANGE') {
                if (preg_match('/^\s*\d+\s*-\s*\d+\s*$/', (string)$rule['field_value'])) {
                    list($start, $end) = array_map('trim', explode('-', $rule['field_value']));
                    $where_clauses[] = "CAST(select_val AS INTEGER) BETWEEN ? AND ?";
                    $types .= 'ii';
                    $params[] = (int)$start;
                    $params[] = (int)$end;
                }
            } elseif (strpos($rule['field_value'], '|') !== false) {
                $values = explode('|', $rule['field_value']);
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $where_clauses[] = "select_val IN ($placeholders)";
                $types .= str_repeat('s', count($values));
                array_push($params, ...array_map('trim', $values));
            } else {
                $where_clauses[] = "select_val = ?";
                $types .= 's';
                $params[] = $rule['field_value'];
            }
        }
    }

    // If this is a child rule, constrain the query to the parent's population of logkeys
    // Only append to WHERE when base_query does NOT already include its own WHERE clause
    if ($population_logkeys !== null && strpos($base_query, 'WHERE') === false) {
        if (empty($population_logkeys)) {
            $current_value = 0; // If parent population is empty, child value must be 0
        } else {
            $placeholders = implode(',', array_fill(0, count($population_logkeys), '?'));
            $where_clauses[] = "logkey IN ($placeholders)";
            $types .= str_repeat('s', count($population_logkeys));
            array_push($params, ...$population_logkeys);
        }
    }
    
    // Execute the query to get the trainee's current value
    if (!isset($current_value)) {
        $final_query = $base_query;
        if (!empty($where_clauses)) {
            $final_query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $stmt = $pdo->prepare($final_query);
        $stmt->execute($params);
        $current_value = $stmt->fetchColumn();
    }
    
    $current_value = $current_value ?? 0;
    $is_passed = ($current_value >= $rule['required_value']);

    // Debug: log per-rule evaluation
    error_log('[PASS DEBUG] _evaluateRule trainee=' . $traineeKey . ' tbid=' . ($rule['tbid'] ?? 'NULL') .
        ' stid=' . (isset($rule['stid']) ? $rule['stid'] : (isset($rule['or_fields']) ? ('OR[' . implode(',', $rule['or_fields']) . ']') : 'NULL')) .
        ' req_type=' . ($rule['requirement_type'] ?? '') . ' current=' . $current_value . ' required=' . ($rule['required_value'] ?? 0) .
        ' passed=' . ($is_passed ? '1' : '0'));

    // --- Step B: If this rule passed and has children, evaluate them ---
    $child_results = [];
    $all_children_passed = true;
    if ($is_passed && !empty($rule['children'])) {
        // Get the specific population of logkeys that satisfied THIS rule
        $new_population_logkeys = _getLogkeysForPassedRule($rule, $traineeKey, $pdo, $population_logkeys);
        
        foreach ($rule['children'] as &$child_rule) {
            $child_result = _evaluateRule($child_rule, $traineeKey, $pdo, $new_population_logkeys);
            if (!$child_result['is_passed']) {
                $all_children_passed = false;
            }
            $child_results[] = $child_result;
        }
    }
    
    // The final status depends on this rule AND all its children passing
    $final_is_passed = $is_passed && $all_children_passed;

    // --- Step C: Return a detailed result object for this rule ---
    return [
        'standard_name' => $rule['standard_name'],
        'is_passed' => $final_is_passed,
        'current_value' => $current_value,
        'required_value' => $rule['required_value'],
        'children' => $child_results
    ];
}

/**
 * Evaluates a rule that has subfield rules.
 * This is the new evaluation logic for the updated rules system.
 *
 * @param array $rule The rule with subfield rules to evaluate.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @param array|null $population_logkeys A pre-filtered list of logkeys from a parent rule.
 * @return array A detailed result object for this rule with subfield breakdown.
 */
function _evaluateSubfieldRule($rule, $traineeKey, $pdo, $population_logkeys = null) {
    $subfield_results = [];
    $all_subfields_passed = true;

    // Evaluate each subfield rule; support any_of groups
    foreach ($rule['subfield_rules'] as $subfield_rule) {
        // any_of: treat as a single requirement that passes if any alternative passes
        if (isset($subfield_rule['any_of']) && is_array($subfield_rule['any_of']) && !empty($subfield_rule['any_of'])) {
            $alternatives = $subfield_rule['any_of'];
            $alt_results = [];
            $any_passed = false;
            foreach ($alternatives as $alt) {
                $alt_result = _evaluateSubfieldIndividualRule($rule, $alt, $traineeKey, $pdo, $population_logkeys);
                $alt_results[] = $alt_result;
                if ($alt_result['is_passed']) {
                    $any_passed = true;
                }
            }
            $subfield_results[] = [
                'subfield_value' => null,
                'subfield_name' => 'any_of',
                'requirement_type' => 'ANY_OF',
                'is_passed' => $any_passed,
                'current_value' => array_sum(array_map(function($r){ return $r['is_passed'] ? 1 : 0; }, $alt_results)),
                'required_value' => 1,
                'alternatives' => $alt_results
            ];
            if (!$any_passed) {
                $all_subfields_passed = false;
            }
        } else {
            $subfield_result = _evaluateSubfieldIndividualRule($rule, $subfield_rule, $traineeKey, $pdo, $population_logkeys);
            $subfield_results[] = $subfield_result;
            if (!$subfield_result['is_passed']) {
                $all_subfields_passed = false;
            }
        }
    }
    
    // The main rule passes only if ALL subfield rules pass
    $final_is_passed = $all_subfields_passed;
    
    // Check if this rule has children and evaluate them if the rule passed
    $child_results = [];
    $all_children_passed = true;
    
    if ($final_is_passed && !empty($rule['children'])) {
        // Get the specific population of logkeys that satisfied THIS rule
        $new_population_logkeys = _getLogkeysForSubfieldRuleParent($rule, $traineeKey, $pdo, $population_logkeys);
        
        foreach ($rule['children'] as &$child_rule) {
            $child_result = _evaluateRule($child_rule, $traineeKey, $pdo, $new_population_logkeys);
            if (!$child_result['is_passed']) {
                $all_children_passed = false;
            }
            $child_results[] = $child_result;
        }
        
        // The final status depends on this rule AND all its children passing
        $final_is_passed = $all_subfields_passed && $all_children_passed;
    }
    
    return [
        'standard_name' => $rule['standard_name'],
        'is_passed' => $final_is_passed,
        'current_value' => count(array_filter($subfield_results, function($r) { return $r['is_passed']; })),
        'required_value' => count($rule['subfield_rules']),
        'subfield_rules' => $subfield_results,
        'children' => $child_results
    ];
}

/**
 * Evaluates an individual subfield rule.
 *
 * @param array $main_rule The main rule containing the subfield rules.
 * @param array $subfield_rule The specific subfield rule to evaluate.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @param array|null $population_logkeys A pre-filtered list of logkeys from a parent rule.
 * @return array A detailed result object for this subfield rule.
 */
function _evaluateSubfieldIndividualRule($main_rule, $subfield_rule, $traineeKey, $pdo, $population_logkeys = null) {
    $params = [$traineeKey];
    $types = 's';
    $where_clauses = ["trainkey = ?"];
    
    // Add tbid filter to only check logs from the correct table/competency
    if (!empty($main_rule['tbid']) && is_numeric($main_rule['tbid']) && (int)$main_rule['tbid'] > 0) {
        $where_clauses[] = "tbid = ?";
        $types .= 'i';
        $params[] = (int)$main_rule['tbid'];
    }
    
    // Add the main field condition
    if (!is_null($main_rule['stid']) && is_numeric($main_rule['stid']) && (int)$main_rule['stid'] > 0) {
        $where_clauses[] = "stid = ?";
        $types .= 'i';
        $params[] = (int)$main_rule['stid'];
    }
    
    // Add the subfield value condition: filter by pid (and keep stid mapping for safety)
    $sub_pid = null;
    if (!empty($subfield_rule['subfield_value'] ?? '')) {
        $pid = $subfield_rule['subfield_value'];
        $sub_pid = $pid;
        // pid filter (direct)
        $where_clauses[] = "pid = ?";
        $types .= 'i';
        $params[] = (int)$pid;
        // Also map PID to STID (usually redundant but harmless)
        $stid_query = "SELECT stid FROM select_gen WHERE pid = ?";
        $stid_stmt = $pdo->prepare($stid_query);
        $stid_stmt->execute([$pid]);
        $stid_row = $stid_stmt->fetch(PDO::FETCH_ASSOC);
        if ($stid_row) {
            $where_clauses[] = "stid = ?";
            $types .= 'i';
            $params[] = (int)$stid_row['stid'];
        }
    }
    
    // If this is a child rule, constrain the query to the parent's population of logkeys
    if ($population_logkeys !== null) {
        if (empty($population_logkeys)) {
            $current_value = 0;
        } else {
            $placeholders = implode(',', array_fill(0, count($population_logkeys), '?'));
            $where_clauses[] = "logkey IN ($placeholders)";
            $types .= str_repeat('s', count($population_logkeys));
            array_push($params, ...$population_logkeys);
        }
    }
    
    // Optionally combine main literal field_value constraints (simple equals/IN) with subfield rules
    $literal_values = [];
    if (!empty($main_rule['field_value'])) {
        $literal_values = _extractLiteralValuesFromFieldValue($main_rule['field_value']);
        if (!empty($literal_values)) {
            $placeholders = implode(',', array_fill(0, count($literal_values), '?'));
            $where_clauses[] = "select_val IN ($placeholders)";
            foreach ($literal_values as $lv) { $params[] = $lv; }
        }
    }

    // Build the query based on requirement type
    $base_query = "";
    $sub_req_type = $subfield_rule['requirement_type'] ?? 'TOTAL_COUNT';
    switch ($sub_req_type) {
        case 'PER_CASE_MINIMUM':
            // Count distinct cases where per-case maximum numeric select_val meets minimum_threshold
            $min_threshold = isset($subfield_rule['minimum_threshold']) ? (float)$subfield_rule['minimum_threshold'] : null;
            $specific_required = trim((string)($subfield_rule['specific_value'] ?? '0'));
            $required_value = is_numeric($specific_required) ? (int)$specific_required : 0;
            // Early exit if parent population known empty
            if ($population_logkeys !== null && empty($population_logkeys)) {
                $current_value = 0;
                $is_passed = ($current_value >= $required_value);
                return [
                    'subfield_value' => $subfield_rule['subfield_value'] ?? '',
                    'subfield_name' => _getSubfieldName($subfield_rule['subfield_value'] ?? '', $pdo),
                    'requirement_type' => 'PER_CASE_MINIMUM',
                    'is_passed' => $is_passed,
                    'current_value' => $current_value,
                    'required_value' => $required_value
                ];
            }
            $inner_params = [$traineeKey];
            $inner_query = "SELECT COUNT(DISTINCT max_sessions.logkey) FROM ( SELECT t1.logkey, MAX(CASE WHEN t1.select_val IS NOT NULL AND t1.select_val != '' AND t1.select_val ~ '^[0-9]+\\.?[0-9]*$' THEN CAST(t1.select_val AS DECIMAL(10,2)) ELSE NULL END) AS max_value FROM trainee_log t1 WHERE t1.trainkey = ?";
            // tbid
            if (!empty($main_rule['tbid'])) {
                $inner_query .= " AND t1.tbid = ?";
                $inner_params[] = (int)$main_rule['tbid'];
            }
            // main stid
            if (!is_null($main_rule['stid'])) {
                $inner_query .= " AND t1.stid = ?";
                $inner_params[] = (int)$main_rule['stid'];
            }
            // pid (subfield filter)
            if (!is_null($sub_pid)) {
                $inner_query .= " AND t1.pid = ?";
                $inner_params[] = (int)$sub_pid;
            }
            // literal select_val IN (...) if provided (applied before MAX)
            if (!empty($literal_values)) {
                $ph = implode(',', array_fill(0, count($literal_values), '?'));
                $inner_query .= " AND t1.select_val IN ($ph)";
                foreach ($literal_values as $lv) { $inner_params[] = $lv; }
            }
            // parent population
            if ($population_logkeys !== null && !empty($population_logkeys)) {
                $ph = implode(',', array_fill(0, count($population_logkeys), '?'));
                $inner_query .= " AND t1.logkey IN ($ph)";
                foreach ($population_logkeys as $lk) { $inner_params[] = $lk; }
            }
            $inner_query .= " GROUP BY t1.logkey ) max_sessions WHERE max_sessions.max_value >= ?";
            $inner_params[] = ($min_threshold !== null ? $min_threshold : 0.0);
            $stmt = $pdo->prepare($inner_query);
            $stmt->execute($inner_params);
            $current_value = (int)$stmt->fetchColumn();
            $is_passed = ($current_value >= $required_value);
            return [
                'subfield_value' => $subfield_rule['subfield_value'] ?? '',
                'subfield_name' => _getSubfieldName($subfield_rule['subfield_value'] ?? '', $pdo),
                'requirement_type' => 'PER_CASE_MINIMUM',
                'is_passed' => $is_passed,
                'current_value' => $current_value,
                'required_value' => $required_value
            ];
        case 'UNIQUE_VALUES':
            // Count distinct values for the subfield (pid or select_val), default to pid
            $base_query = "SELECT COUNT(DISTINCT pid) FROM trainee_log";
            break;
        case 'UNIQUE_VALUES_IN_RANGE':
            $base_query = "SELECT COUNT(DISTINCT pid) FROM trainee_log";
            // Add range condition if specific_value contains a range
            if (!empty($subfield_rule['specific_value']) && strpos($subfield_rule['specific_value'], '-') !== false) {
                list($start, $end) = explode('-', $subfield_rule['specific_value']);
                // For subfield ranges, apply to a numeric select_val if present
                $where_clauses[] = "CASE WHEN select_val ~ '^[0-9]+$' THEN CAST(select_val AS INTEGER) ELSE NULL END BETWEEN ? AND ?";
                $types .= 'ii';
                $params[] = trim($start);
                $params[] = trim($end);
            }
            break;
        case 'TOTAL_COUNT':
        default:
            $base_query = "SELECT COUNT(*) FROM trainee_log";
            break;
    }
    
    // Execute the query
    $final_query = $base_query . " WHERE " . implode(" AND ", $where_clauses);
    $stmt = $pdo->prepare($final_query);
    $stmt->execute($params);
    $current_value = $stmt->fetchColumn();

    $current_value = $current_value ?? 0;
    $required_value_raw = trim((string)($subfield_rule['specific_value'] ?? '0'));
    $required_value = is_numeric($required_value_raw) ? (int)$required_value_raw : 0;

    // Flexible comparison for UNIQUE_VALUES: allow ranges ("X-Y") or CSV lists ("a, b, c")
    $is_passed = false;
    if ($sub_req_type === 'UNIQUE_VALUES' && $required_value_raw !== '') {
        if (strpos($required_value_raw, '-') !== false) {
            list($lo, $hi) = array_map('trim', explode('-', $required_value_raw));
            $is_passed = is_numeric($lo) && is_numeric($hi) ? ($current_value >= (int)$lo && $current_value <= (int)$hi) : ($current_value >= $required_value);
        } elseif (strpos($required_value_raw, ',') !== false) {
            $allowed = array_filter(array_map('trim', explode(',', $required_value_raw)), function($v){ return $v !== ''; });
            $allowed_ints = array_map('intval', $allowed);
            $is_passed = in_array((int)$current_value, $allowed_ints, true);
        } else {
            $is_passed = ($current_value >= $required_value);
        }
    } else {
        $is_passed = ($current_value >= $required_value);
    }
    
    return [
        'subfield_value' => $subfield_rule['subfield_value'] ?? '',
        'subfield_name' => _getSubfieldName($subfield_rule['subfield_value'] ?? '', $pdo),
        'requirement_type' => $subfield_rule['requirement_type'] ?? 'TOTAL_COUNT',
        'is_passed' => $is_passed,
        'current_value' => $current_value,
        'required_value' => $required_value
    ];
}

/**
 * Gets the display name for a subfield value.
 *
 * @param string $subfield_value The subfield value ID.
 * @param PDO $pdo The database connection.
 * @return string The display name for the subfield value.
 */
function _getSubfieldName($subfield_value, $pdo) {
    if (empty($subfield_value)) {
        return 'Unknown';
    }
    
    // Look up by pid in select_gen table to get the display name
    $query = "SELECT select_val FROM select_gen WHERE pid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$subfield_value]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row ? $row['select_val'] : 'Unknown';
}

/**
 * Gets the specific logkeys that satisfied a given rule.
 * This is used to define the population for the next level of child rules.
 *
 * @param array $rule The rule that was passed.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @param array|null $parent_population_logkeys The logkey population from the parent.
 * @return array An array of logkeys.
 */
function _getLogkeysForPassedRule($rule, $traineeKey, $pdo, $parent_population_logkeys = null) {
    // This function re-builds the WHERE clause from _evaluateRule but selects `logkey`
    // This is a simplified version for brevity. The actual implementation would mirror
    // the query construction logic from _evaluateRule to ensure consistency.
    $params = [$traineeKey];
    $types = 's';
    $where_clauses = ["trainkey = ?"];

    // Add tbid filter to only get logkeys from the correct table/competency
    if (!empty($rule['tbid']) && is_numeric($rule['tbid']) && (int)$rule['tbid'] > 0) {
        $where_clauses[] = "tbid = ?";
        $types .= 'i';
        $params[] = (int)$rule['tbid'];
    }

    if (!is_null($rule['stid']) && is_numeric($rule['stid']) && (int)$rule['stid'] > 0) {
        $where_clauses[] = "stid = ?";
        $types .= 'i';
        $params[] = (int)$rule['stid'];
    }
    
    if (!empty($rule['field_value'])) {
       if ($rule['requirement_type'] === 'UNIQUE_VALUES_IN_RANGE') {
            if (preg_match('/^\s*\d+\s*-\s*\d+\s*$/', (string)$rule['field_value'])) {
                list($start, $end) = array_map('trim', explode('-', $rule['field_value']));
                $where_clauses[] = "CAST(select_val AS INTEGER) BETWEEN ? AND ?";
                $types .= 'ii';
                $params[] = (int)$start;
                $params[] = (int)$end;
            }
        } else {
            $where_clauses[] = "select_val = ?";
            $types .= 's';
            $params[] = $rule['field_value'];
        }
    }

    if ($parent_population_logkeys !== null) {
        $placeholders = implode(',', array_fill(0, count($parent_population_logkeys), '?'));
        $where_clauses[] = "logkey IN ($placeholders)";
        $types .= str_repeat('s', count($parent_population_logkeys));
        array_push($params, ...$parent_population_logkeys);
    }
    
    $query = "SELECT DISTINCT logkey FROM trainee_log WHERE " . implode(" AND ", $where_clauses);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logkeys = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $logkeys[] = $row['logkey'];
    }
    return $logkeys;
}

/**
 * Gets logkeys that satisfied a category group rule.
 * Used to get the population for child rules.
 *
 * @param array $rule The rule with category groups.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @param array|null $population_logkeys The parent population.
 * @return array Array of logkeys.
 */
function _getLogkeysForCategoryGroupRule($rule, $traineeKey, $pdo, $population_logkeys = null) {
    $all_logkeys = [];
    
    // Collect logkeys from all category groups that passed
    foreach ($rule['category_groups'] as $group) {
        $group_logkeys = _getLogkeysForCategoryGroup($rule, $group, $traineeKey, $pdo);
        $all_logkeys = array_merge($all_logkeys, $group_logkeys);
    }
    
    // If parent population is provided, intersect with it
    if ($population_logkeys !== null && !empty($population_logkeys)) {
        $all_logkeys = array_intersect($all_logkeys, $population_logkeys);
    }
    
    return array_unique($all_logkeys);
}

/**
 * Gets logkeys that satisfied a subfield rule parent.
 * Used to get the population for child rules.
 *
 * @param array $rule The rule with subfield rules.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @param array|null $population_logkeys The parent population.
 * @return array Array of logkeys.
 */
function _getLogkeysForSubfieldRuleParent($rule, $traineeKey, $pdo, $population_logkeys = null) {
    $all_logkeys = [];
    
    // Collect logkeys from all subfield rules that passed
    foreach ($rule['subfield_rules'] as $subfield_rule) {
        // Handle any_of groups
        if (isset($subfield_rule['any_of']) && is_array($subfield_rule['any_of']) && !empty($subfield_rule['any_of'])) {
            foreach ($subfield_rule['any_of'] as $alt) {
                $subfield_logkeys = _getLogkeysForSubfieldRule($rule, $alt, $traineeKey, $pdo);
                $all_logkeys = array_merge($all_logkeys, $subfield_logkeys);
            }
        } else {
            $subfield_logkeys = _getLogkeysForSubfieldRule($rule, $subfield_rule, $traineeKey, $pdo);
            $all_logkeys = array_merge($all_logkeys, $subfield_logkeys);
        }
    }
    
    // If parent population is provided, intersect with it
    if ($population_logkeys !== null && !empty($population_logkeys)) {
        $all_logkeys = array_intersect($all_logkeys, $population_logkeys);
    }
    
    return array_unique($all_logkeys);
}

/**
 * Gets matching log entries for a pass standard for a trainee.
 * Returns detailed log information including logkey, date, and field values.
 * Handles simple rules, subfield rules, and category groups.
 *
 * @param string $traineeKey The unique key for the trainee.
 * @param int $psid The pass standard ID.
 * @param PDO $pdo The database connection object.
 * @return array Array of matching log entries with details.
 */
function getMatchingLogsForStandard($traineeKey, $psid, $pdo) {
    // Fetch the specific pass standard
    $query = "SELECT * FROM pass_standards WHERE psid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$psid]);
    $standard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$standard) {
        return [];
    }
    
    // Fetch OR fields if the main stid is NULL
    if (is_null($standard['stid'])) {
        $or_query = "SELECT stid FROM pass_standard_fields WHERE standard_id = ?";
        $or_stmt = $pdo->prepare($or_query);
        $or_stmt->execute([$psid]);
        $standard['or_fields'] = [];
        while ($or_row = $or_stmt->fetch(PDO::FETCH_ASSOC)) {
            $standard['or_fields'][] = $or_row['stid'];
        }
    }
    
    // Parse subfield rules if they exist
    if (!empty($standard['field_value']) && strpos($standard['field_value'], 'SUBFIELD_RULES:') !== false) {
        $standard['subfield_rules'] = _parseSubfieldRules($standard['field_value']);
    }
    
    // Parse category_groups if they exist
    if (!empty($standard['field_value']) && strpos($standard['field_value'], 'category_groups') !== false) {
        $standard['category_groups'] = _parseCategoryGroups($standard['field_value']);
    }
    
    $all_logkeys = [];
    // Track any per-case minimum requirements to optionally suppress sub-threshold rows in detail view
    $per_case_minimum_rules = [];
    
    // Handle category groups
    if (!empty($standard['category_groups'])) {
        foreach ($standard['category_groups'] as $group) {
            $group_logkeys = _getLogkeysForCategoryGroup($standard, $group, $traineeKey, $pdo);
            $all_logkeys = array_merge($all_logkeys, $group_logkeys);

            // Remember PER_CASE_MINIMUM rules (stid + threshold) for detail filtering
            if (isset($group['requirement_type']) && $group['requirement_type'] === 'PER_CASE_MINIMUM' && !empty($group['minimum_threshold']) && !empty($group['stid'])) {
                $per_case_minimum_rules[] = [
                    'stid' => (int)$group['stid'],
                    'minimum_threshold' => (float)$group['minimum_threshold']
                ];
            }
        }
    }
    // Handle TOTAL_HOURS_COMBINED requirement type
    elseif (($standard['requirement_type'] ?? '') === 'TOTAL_HOURS_COMBINED' && !empty($standard['field_value'])) {
        $all_logkeys = _getLogkeysForTotalHoursCombined($standard, $traineeKey, $pdo);
    }
    // Handle subfield rules
    elseif (!empty($standard['subfield_rules'])) {
        // Get logkeys from all subfield rules - we want to show ALL matching logs
        // even if not all rules pass, so users can see what matched
        foreach ($standard['subfield_rules'] as $subfield_rule) {
            $subfield_logkeys = _getLogkeysForSubfieldRule($standard, $subfield_rule, $traineeKey, $pdo);
            $all_logkeys = array_merge($all_logkeys, $subfield_logkeys);
        }
        
        // Also get logkeys matching just the main criteria (tbid + stid) 
        // in case subfield rules don't have specific values but still match
        if (empty($all_logkeys) && !is_null($standard['stid'])) {
            // Get logkeys with just tbid + stid (no field_value filter)
            $params = [$traineeKey];
            $where_clauses = ["trainkey = ?"];
            if (!empty($standard['tbid']) && is_numeric($standard['tbid']) && (int)$standard['tbid'] > 0) {
                $where_clauses[] = "tbid = ?";
                $params[] = (int)$standard['tbid'];
            }
            if (!is_null($standard['stid']) && is_numeric($standard['stid']) && (int)$standard['stid'] > 0) {
                $where_clauses[] = "stid = ?";
                $params[] = (int)$standard['stid'];
            }
            $logkey_query = "SELECT DISTINCT logkey FROM trainee_log WHERE " . implode(" AND ", $where_clauses);
            $logkey_stmt = $pdo->prepare($logkey_query);
            $logkey_stmt->execute($params);
            while ($row = $logkey_stmt->fetch(PDO::FETCH_ASSOC)) {
                $all_logkeys[] = $row['logkey'];
            }
        }
    }
    // Handle simple rules
    else {
        $all_logkeys = _getLogkeysForSimpleRule($standard, $traineeKey, $pdo);
    }
    
    // Remove duplicates
    $all_logkeys = array_unique($all_logkeys);
    
    if (empty($all_logkeys)) {
        return [];
    }
    
    // Get distinct logkeys with their dates first
    $placeholders = implode(',', array_fill(0, count($all_logkeys), '?'));
    $distinct_logs_query = "
        SELECT DISTINCT tl.logkey, tl.tbid, tl.date_added
        FROM trainee_log tl
        WHERE tl.logkey IN ($placeholders) AND tl.trainkey = ?
        GROUP BY tl.logkey, tl.tbid, tl.date_added
        ORDER BY tl.date_added DESC
    ";
    $distinct_stmt = $pdo->prepare($distinct_logs_query);
    $distinct_stmt->execute(array_merge($all_logkeys, [$traineeKey]));
    
    $logs_by_key = [];
    while ($log_row = $distinct_stmt->fetch(PDO::FETCH_ASSOC)) {
        $logkey = $log_row['logkey'];
        $logs_by_key[$logkey] = [
            'logkey' => $logkey,
            'date' => $log_row['date_added'],
            'tbid' => $log_row['tbid'],
            'tab_name' => 'Unknown',
            'fields' => []
        ];
    }
    
    // Now get all fields for these logkeys
    if (!empty($logs_by_key)) {
        $logkey_array = array_keys($logs_by_key);
        $all_placeholders = implode(',', array_fill(0, count($logkey_array), '?'));
        $fields_query = "
            SELECT 
                tl.logkey,
                tl.stid,
                tl.select_val,
                t.tab_name,
                st.str as select_type_name
            FROM trainee_log tl
            LEFT JOIN tabs_tbl t ON tl.tbid = t.tbid
            LEFT JOIN select_types st ON tl.stid = st.stid
            WHERE tl.logkey IN ($all_placeholders) AND tl.trainkey = ?
        ";

        // If there are per-case minimum rules, suppress rows for those stids below their threshold
        if (!empty($per_case_minimum_rules)) {
            $suppression_clauses = [];
            $suppression_params = [];
            foreach ($per_case_minimum_rules as $rule) {
                if (!empty($rule['stid']) && is_numeric($rule['stid']) && (int)$rule['stid'] > 0) {
                    $suppression_clauses[] = "AND NOT (tl.stid = ? AND tl.select_val IS NOT NULL AND tl.select_val != '' AND tl.select_val ~ '^[0-9]+\\.?[0-9]*$' AND CAST(tl.select_val AS DECIMAL(10,2)) < ?)";
                    $suppression_params[] = (int)$rule['stid'];
                    $suppression_params[] = (float)$rule['minimum_threshold'];
                }
            }
            if (!empty($suppression_clauses)) {
                $fields_query .= ' ' . implode(' ', $suppression_clauses);
            }
            $fields_query .= "\n            ORDER BY tl.logkey, tl.stid";
            $fields_stmt = $pdo->prepare($fields_query);
            $fields_stmt->execute(array_merge($logkey_array, [$traineeKey], $suppression_params));
        } else {
            $fields_query .= "\n            ORDER BY tl.logkey, tl.stid";
            $fields_stmt = $pdo->prepare($fields_query);
            $fields_stmt->execute(array_merge($logkey_array, [$traineeKey]));
        }
        
        while ($field_row = $fields_stmt->fetch(PDO::FETCH_ASSOC)) {
            $logkey = $field_row['logkey'];
            if (isset($logs_by_key[$logkey])) {
                // Update tab_name from first field found
                if ($logs_by_key[$logkey]['tab_name'] === 'Unknown' && !empty($field_row['tab_name'])) {
                    $logs_by_key[$logkey]['tab_name'] = $field_row['tab_name'];
                }
                $logs_by_key[$logkey]['fields'][] = [
                    'stid' => $field_row['stid'],
                    'select_type_name' => $field_row['select_type_name'] ?? 'Unknown',
                    'select_val' => $field_row['select_val']
                ];
            }
        }
    }
    
    return array_values($logs_by_key);
}

/**
 * Helper function to get logkeys for a TOTAL_HOURS_COMBINED rule.
 * 
 * @param array $rule The rule with TOTAL_HOURS_COMBINED requirement type.
 * @param string $traineeKey The trainee's key.
 * @param PDO $pdo The database connection.
 * @return array Array of logkeys matching the combined hours sources.
 */
function _getLogkeysForTotalHoursCombined($rule, $traineeKey, $pdo) {
    $all_logkeys = [];
    
    // Parse the field_value JSON structure
    if (empty($rule['field_value'])) {
        return [];
    }
    
    try {
        $data = json_decode($rule['field_value'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['total_of']) || !is_array($data['total_of'])) {
            return [];
        }
        
        // Process each source in the total_of array
        foreach ($data['total_of'] as $source) {
            if (empty($source['category_stid'])) {
                continue;
            }
            
            $category_stid = (int)$source['category_stid'];
            if ($category_stid <= 0) {
                continue; // Skip invalid category_stid
            }
            $category_value = isset($source['category_value']) ? $source['category_value'] : '';
            
            // Build query for this source
            $params = [$traineeKey];
            $where_clauses = ["trainkey = ?"];
            
            // Add tbid filter if provided
            if (!empty($rule['tbid']) && is_numeric($rule['tbid']) && (int)$rule['tbid'] > 0) {
                $where_clauses[] = "tbid = ?";
                $params[] = (int)$rule['tbid'];
            }
            
            // Add category_stid filter
            $where_clauses[] = "stid = ?";
            $params[] = $category_stid;
            
            // Add category_value filter if provided (not empty)
            if (!empty($category_value)) {
                $where_clauses[] = "select_val = ?";
                $params[] = $category_value;
            }
            
            // Get logkeys for this source
            $logkey_query = "SELECT DISTINCT logkey FROM trainee_log WHERE " . implode(" AND ", $where_clauses);
            $logkey_stmt = $pdo->prepare($logkey_query);
            $logkey_stmt->execute($params);
            
            while ($row = $logkey_stmt->fetch(PDO::FETCH_ASSOC)) {
                $all_logkeys[] = $row['logkey'];
            }
        }
    } catch (Exception $e) {
        error_log("Error parsing TOTAL_HOURS_COMBINED field_value: " . $e->getMessage());
        return [];
    }
    
    return array_unique($all_logkeys);
}

/**
 * Helper function to get logkeys for a simple rule.
 */
function _getLogkeysForSimpleRule($rule, $traineeKey, $pdo) {
    $params = [$traineeKey];
    $where_clauses = ["trainkey = ?"];
    
    // Add tbid filter
    if (!empty($rule['tbid']) && is_numeric($rule['tbid']) && (int)$rule['tbid'] > 0) {
        $where_clauses[] = "tbid = ?";
        $params[] = (int)$rule['tbid'];
    }
    
    // Add STID condition (single field or multiple OR fields)
    if (!empty($rule['or_fields'])) {
        $placeholders = implode(',', array_fill(0, count($rule['or_fields']), '?'));
        $where_clauses[] = "stid IN ($placeholders)";
        array_push($params, ...$rule['or_fields']);
    } elseif (!is_null($rule['stid']) && is_numeric($rule['stid']) && (int)$rule['stid'] > 0) {
        $where_clauses[] = "stid = ?";
        $params[] = (int)$rule['stid'];
    }
    
    // Add field_value condition
    if (!empty($rule['field_value'])) {
        if ($rule['requirement_type'] === 'UNIQUE_VALUES_IN_RANGE') {
            if (preg_match('/^\s*\d+\s*-\s*\d+\s*$/', (string)$rule['field_value'])) {
                list($start, $end) = array_map('trim', explode('-', $rule['field_value']));
                $where_clauses[] = "CAST(select_val AS INTEGER) BETWEEN ? AND ?";
                $params[] = (int)$start;
                $params[] = (int)$end;
            }
        } elseif (strpos($rule['field_value'], '|') !== false) {
            $values = explode('|', $rule['field_value']);
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $where_clauses[] = "select_val IN ($placeholders)";
            array_push($params, ...array_map('trim', $values));
        } else {
            $where_clauses[] = "select_val = ?";
            $params[] = $rule['field_value'];
        }
    }
    
    // Special handling: PER_CASE_MINIMUM at simple rule level to return only logkeys meeting threshold
    if (($rule['requirement_type'] ?? '') === 'PER_CASE_MINIMUM' && !empty($rule['minimum_threshold']) && (!empty($rule['stid']) || !empty($rule['or_fields']))) {
        $logkey_query = "
            SELECT DISTINCT max_sessions.logkey
            FROM (
                SELECT 
                    t1.logkey,
                    MAX(CASE 
                        WHEN t1.select_val IS NOT NULL AND t1.select_val != '' AND t1.select_val ~ '^[0-9]+\\.?[0-9]*$'
                        THEN CAST(t1.select_val AS DECIMAL(10,2))
                        ELSE NULL
                    END) AS max_value
                FROM trainee_log t1
                WHERE t1.trainkey = ?";
        $spec_params = [$traineeKey];
        if (!empty($rule['or_fields'])) {
            $placeholders = implode(',', array_fill(0, count($rule['or_fields']), '?'));
            $logkey_query .= " AND t1.stid IN ($placeholders)";
            foreach ($rule['or_fields'] as $of) { $spec_params[] = (int)$of; }
        } else {
            $logkey_query .= " AND t1.stid = ?";
            $spec_params[] = (int)$rule['stid'];
        }
        if (!empty($rule['tbid'])) {
            $logkey_query .= " AND t1.tbid = ?";
            $spec_params[] = (int)$rule['tbid'];
        }
        $logkey_query .= " GROUP BY t1.logkey ) max_sessions
            WHERE max_sessions.max_value >= ?";
        $spec_params[] = (float)$rule['minimum_threshold'];

        $logkey_stmt = $pdo->prepare($logkey_query);
        $logkey_stmt->execute($spec_params);
        $logkeys = [];
        while ($row = $logkey_stmt->fetch(PDO::FETCH_ASSOC)) {
            $logkeys[] = $row['logkey'];
        }
    } else {
        $logkey_query = "SELECT DISTINCT logkey FROM trainee_log WHERE " . implode(" AND ", $where_clauses);
        $logkey_stmt = $pdo->prepare($logkey_query);
        $logkey_stmt->execute($params);
        $logkeys = [];
        while ($row = $logkey_stmt->fetch(PDO::FETCH_ASSOC)) {
            $logkeys[] = $row['logkey'];
        }
    }
    
    return $logkeys;
}

/**
 * Helper function to get logkeys for a subfield rule.
 */
function _getLogkeysForSubfieldRule($main_rule, $subfield_rule, $traineeKey, $pdo) {
    $params = [$traineeKey];
    $where_clauses = ["trainkey = ?"];
    
    // Add tbid filter
    if (!empty($main_rule['tbid']) && is_numeric($main_rule['tbid']) && (int)$main_rule['tbid'] > 0) {
        $where_clauses[] = "tbid = ?";
        $params[] = (int)$main_rule['tbid'];
    }
    
    // Add the main field condition
    if (!is_null($main_rule['stid']) && is_numeric($main_rule['stid']) && (int)$main_rule['stid'] > 0) {
        $where_clauses[] = "stid = ?";
        $params[] = (int)$main_rule['stid'];
    }
    
    // For subfield rules, we need to match based on the requirement type
    // If there's a subfield_value (PID), match by pid
    // Otherwise, just match by stid (for TOTAL_COUNT of any value in that field)
    
    $has_subfield_condition = false;
    
    // Handle any_of logic (OR conditions) - takes precedence
    if (isset($subfield_rule['any_of']) && is_array($subfield_rule['any_of']) && !empty($subfield_rule['any_of'])) {
        $any_of_conditions = [];
        $any_of_params = [];
        foreach ($subfield_rule['any_of'] as $alt) {
            if (!empty($alt['subfield_value']) && 
                is_numeric($alt['subfield_value']) && 
                trim((string)$alt['subfield_value']) !== '') {
                $pid = (int)$alt['subfield_value'];
                if ($pid > 0) {
                    $any_of_conditions[] = "pid = ?";
                    $any_of_params[] = $pid;
                }
            }
        }
        if (!empty($any_of_conditions)) {
            $where_clauses[] = "(" . implode(" OR ", $any_of_conditions) . ")";
            $params = array_merge($params, $any_of_params);
            $has_subfield_condition = true;
        }
    }
    // If no any_of, check direct subfield_value
    elseif (!empty($subfield_rule['subfield_value'] ?? '') && 
            is_numeric($subfield_rule['subfield_value']) && 
            trim((string)$subfield_rule['subfield_value']) !== '') {
        $pid = (int)$subfield_rule['subfield_value'];
        if ($pid > 0) {
            // Match by pid field in trainee_log
            $where_clauses[] = "pid = ?";
            $params[] = $pid;
            $has_subfield_condition = true;
        }
    }
    
    // If no subfield condition, we'll match all entries with the stid (for TOTAL_COUNT of all values)
    // This handles cases where subfield rules count all entries in a field
    
    $logkey_query = "SELECT DISTINCT logkey FROM trainee_log WHERE " . implode(" AND ", $where_clauses);
    $logkey_stmt = $pdo->prepare($logkey_query);
    $logkey_stmt->execute($params);
    $logkeys = [];
    while ($row = $logkey_stmt->fetch(PDO::FETCH_ASSOC)) {
        $logkeys[] = $row['logkey'];
    }
    
    return $logkeys;
}

/**
 * Helper function to get logkeys for a category group.
 */
function _getLogkeysForCategoryGroup($main_rule, $group, $traineeKey, $pdo) {
    $params = [$traineeKey];
    $where_clauses = ["trainkey = ?"];
    
    // Add tbid filter
    $tbid = $main_rule['tbid'] ?? $group['tbid'] ?? null;
    if (!empty($tbid) && is_numeric($tbid) && (int)$tbid > 0) {
        $where_clauses[] = "tbid = ?";
        $params[] = (int)$tbid;
    }
    
    // Handle PER_CASE_MINIMUM with minimum_threshold for simple category groups (no subfield rules)
    // This ensures we only get logkeys where the MAXIMUM field value per case meets the minimum threshold
    if (isset($group['requirement_type']) && $group['requirement_type'] === 'PER_CASE_MINIMUM' 
        && !empty($group['minimum_threshold']) 
        && (empty($group['subfield_rules']) || !is_array($group['subfield_rules']) || count($group['subfield_rules']) === 0)) {
        // For PER_CASE_MINIMUM, we need to find the MAX value per logkey, then filter by threshold
        // The group's stid represents the field to check (e.g., "Number of sessions so far")
        // Use a subquery to find max value per logkey first, then filter by threshold
        $logkey_query = "
            SELECT DISTINCT max_sessions.logkey
            FROM (
                SELECT 
                    t1.logkey,
                    MAX(CAST(t1.select_val AS DECIMAL(10,2))) AS max_value
                FROM trainee_log t1
                WHERE t1.trainkey = ? 
                AND t1.stid = ?
                AND t1.select_val IS NOT NULL 
                AND t1.select_val != ''
                AND t1.select_val ~ '^[0-9]+\\.?[0-9]*$'";
        // Validate stid before using it
        if (!empty($group['stid']) && is_numeric($group['stid']) && (int)$group['stid'] > 0) {
            $params = [$traineeKey, (int)$group['stid']];
            if (!empty($tbid) && is_numeric($tbid) && (int)$tbid > 0) {
                $logkey_query .= " AND t1.tbid = ?";
                $params[] = (int)$tbid;
            }
        } else {
            // Invalid stid, return empty array
            return [];
        }
        $logkey_query .= "
                GROUP BY t1.logkey
            ) max_sessions
            WHERE max_sessions.max_value >= ?";
        $params[] = (float)$group['minimum_threshold'];
        
        // Execute the query for PER_CASE_MINIMUM
        $logkey_stmt = $pdo->prepare($logkey_query);
        $logkey_stmt->execute($params);
        $logkeys = [];
        while ($row = $logkey_stmt->fetch(PDO::FETCH_ASSOC)) {
            $logkeys[] = $row['logkey'];
        }
        
        return $logkeys;
    } else {
        // Standard query - no minimum threshold filter
        // Add main STID (supports group['stids'])
        if (!empty($group['stids']) && is_array($group['stids'])) {
            // Filter out invalid stids before building query
            $valid_stids = array_filter($group['stids'], function($sid) {
                return is_numeric($sid) && trim((string)$sid) !== '' && (int)$sid > 0;
            });
            if (!empty($valid_stids)) {
                $where_clauses[] = "stid IN (" . implode(',', array_fill(0, count($valid_stids), '?')) . ")";
                foreach ($valid_stids as $sid) {
                    $params[] = (int)$sid;
                }
            }
        } elseif (isset($group['stid']) && $group['stid'] !== '' && is_numeric($group['stid']) && trim((string)$group['stid']) !== '') {
            $stid_int = (int)$group['stid'];
            if ($stid_int > 0) {
                $where_clauses[] = "stid = ?";
                $params[] = $stid_int;
            }
        }
        
        // Handle subfield rules in category group
        if (isset($group['subfield_rules']) && is_array($group['subfield_rules']) && !empty($group['subfield_rules'])) {
            $any_of_conditions = [];
            $any_of_params = [];
            foreach ($group['subfield_rules'] as $subfield_rule) {
                if (!empty($subfield_rule['subfield_value'])) {
                    $pid = $subfield_rule['subfield_value'];
                    $any_of_conditions[] = "pid = ?";
                    $any_of_params[] = $pid;
                }
            }
            if (!empty($any_of_conditions)) {
                $where_clauses[] = "(" . implode(" OR ", $any_of_conditions) . ")";
                $params = array_merge($params, $any_of_params);
            }
        }
        
        $logkey_query = "SELECT DISTINCT logkey FROM trainee_log WHERE " . implode(" AND ", $where_clauses);
        $logkey_stmt = $pdo->prepare($logkey_query);
        $logkey_stmt->execute($params);
        $logkeys = [];
        while ($row = $logkey_stmt->fetch(PDO::FETCH_ASSOC)) {
            $logkeys[] = $row['logkey'];
        }
    }
    
    return $logkeys;
}

/**
 * Evaluates a specific pass standard for a trainee by pass standard ID (psid).
 * This function checks all trainee logs against the specific standard's conditions.
 *
 * @param string $traineeKey The unique key for the trainee.
 * @param int $psid The pass standard ID to check.
 * @param PDO $pdo The database connection object.
 * @return array A structured array with the evaluation result including is_passed, current_value, required_value, etc.
 */
function checkPassStandardStatus($traineeKey, $psid, $pdo) {
    // Fetch the specific pass standard
    $query = "SELECT * FROM pass_standards WHERE psid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$psid]);
    $standard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$standard) {
        return [
            'is_passed' => false,
            'standard_name' => 'Unknown',
            'message' => 'Pass standard not found.',
            'current_value' => 0,
            'required_value' => 0,
            'subfield_results' => []
        ];
    }
    
    // Check if standard is active
    if (!$standard['is_active']) {
        return [
            'is_passed' => false,
            'standard_name' => $standard['standard_name'],
            'message' => 'This pass standard is inactive.',
            'current_value' => 0,
            'required_value' => $standard['required_value'],
            'subfield_results' => []
        ];
    }
    
    // Fetch OR fields if the main stid is NULL
    if (is_null($standard['stid'])) {
        $or_query = "SELECT stid FROM pass_standard_fields WHERE standard_id = ?";
        $or_stmt = $pdo->prepare($or_query);
        $or_stmt->execute([$psid]);
        $standard['or_fields'] = [];
        while ($or_row = $or_stmt->fetch(PDO::FETCH_ASSOC)) {
            $standard['or_fields'][] = $or_row['stid'];
        }
    }
    
    // Parse subfield rules if they exist
    if (!empty($standard['field_value']) && strpos($standard['field_value'], 'SUBFIELD_RULES:') !== false) {
        $standard['subfield_rules'] = _parseSubfieldRules($standard['field_value']);
    }
    
    // Parse category_groups if they exist
    if (!empty($standard['field_value']) && strpos($standard['field_value'], 'category_groups') !== false) {
        $standard['category_groups'] = _parseCategoryGroups($standard['field_value']);
    }
    
    // Build children array (if this standard has child standards)
    $children_query = "SELECT * FROM pass_standards WHERE parent_standard_id = ? AND is_active = 1";
    $children_stmt = $pdo->prepare($children_query);
    $children_stmt->execute([$psid]);
    $standard['children'] = [];
    while ($child = $children_stmt->fetch(PDO::FETCH_ASSOC)) {
        // Process child the same way
        if (is_null($child['stid'])) {
            $child_or_query = "SELECT stid FROM pass_standard_fields WHERE standard_id = ?";
            $child_or_stmt = $pdo->prepare($child_or_query);
            $child_or_stmt->execute([$child['psid']]);
            $child['or_fields'] = [];
            while ($child_or_row = $child_or_stmt->fetch(PDO::FETCH_ASSOC)) {
                $child['or_fields'][] = $child_or_row['stid'];
            }
        }
        if (!empty($child['field_value']) && strpos($child['field_value'], 'SUBFIELD_RULES:') !== false) {
            $child['subfield_rules'] = _parseSubfieldRules($child['field_value']);
        }
        if (!empty($child['field_value']) && strpos($child['field_value'], 'category_groups') !== false) {
            $child['category_groups'] = _parseCategoryGroups($child['field_value']);
        }
        $standard['children'][] = $child;
    }
    
    // Evaluate the standard using the existing evaluation logic
    $result = _evaluateRule($standard, $traineeKey, $pdo);
    
    // Debug: log evaluation summary for this standard
    error_log('[PASS DEBUG] trainee=' . $traineeKey . ' psid=' . $psid . ' tbid=' . ($standard['tbid'] ?? 'NULL') .
        ' name="' . ($standard['standard_name'] ?? '') . '" is_passed=' . (($result['is_passed'] ?? false) ? '1' : '0') .
        ' current=' . ($result['current_value'] ?? 0) . ' required=' . ($standard['required_value'] ?? 0));
    
    // Format the result to match the expected structure
    return [
        'is_passed' => $result['is_passed'] ?? false,
        'standard_name' => $standard['standard_name'],
        'current_value' => $result['current_value'] ?? 0,
        'required_value' => $standard['required_value'] ?? 0,
        'subfield_results' => $result['subfield_rules'] ?? [],
        'children' => $result['children'] ?? [],
        'requirement_type' => $standard['requirement_type'] ?? '',
        'breakdown' => $result
    ];
}