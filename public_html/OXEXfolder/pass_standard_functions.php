<?php
/**
 * Main function to evaluate a trainee's status for a given competency (table).
 * This is the only function that should be called externally.
 *
 * @param string $traineeKey The unique key for the trainee.
 * @param int $tbid The ID of the competency/table to check.
 * @param mysqli $mysqli The database connection object.
 * @param string|int|null $endDate The optional end date for the competency (format: YYYYMMDD or YYYY-MM-DD).
 * @return array A structured array with the overall status and a detailed breakdown.
 */
function checkCompetencyStatus($traineeKey, $tbid, $mysqli, $endDate = null) {
    // Step 1: Fetch all rules for this competency, ordered to process parents first.
    $all_rules = _fetchAllRulesForCompetency($tbid, $mysqli);

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
        $parent_result = _evaluateRule($parent_rule, $traineeKey, $mysqli);

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
 * @param mysqli $mysqli The database connection.
 * @return array A list of rules.
 */
function _fetchAllRulesForCompetency($tbid, $mysqli) {
    $query = "SELECT * FROM pass_standards WHERE tbid = ? AND is_active = 1 ORDER BY parent_standard_id ASC, psid ASC";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $tbid);
    $stmt->execute();
    $result = $stmt->get_result();
    $rules = [];
    while ($row = $result->fetch_assoc()) {
        // Fetch OR fields if the main stid is NULL
        if (is_null($row['stid'])) {
            $or_query = "SELECT stid FROM pass_standard_fields WHERE standard_id = ?";
            $or_stmt = $mysqli->prepare($or_query);
            $or_stmt->bind_param("i", $row['psid']);
            $or_stmt->execute();
            $or_result = $or_stmt->get_result();
            $row['or_fields'] = [];
            while ($or_row = $or_result->fetch_assoc()) {
                $row['or_fields'][] = $or_row['stid'];
            }
            $or_stmt->close();
        }
        
        // Parse subfield rules if they exist
        if (!empty($row['field_value']) && strpos($row['field_value'], 'SUBFIELD_RULES:') !== false) {
            $row['subfield_rules'] = _parseSubfieldRules($row['field_value']);
        }
        
        $rules[] = $row;
    }
    $stmt->close();
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
 * @param mysqli $mysqli The database connection.
 * @param array|null $population_logkeys A pre-filtered list of logkeys from a parent rule.
 * @return array A detailed result object for this specific rule.
 */
function _evaluateRule($rule, $traineeKey, $mysqli, $population_logkeys = null) {
    // Check if this rule has subfield rules
    if (!empty($rule['subfield_rules'])) {
        return _evaluateSubfieldRule($rule, $traineeKey, $mysqli, $population_logkeys);
    }
    
    // Original evaluation logic for regular rules
    $params = [$traineeKey];
    $types = 's';
    
    // --- Step A: Build the SQL query for the current rule ---
    $base_query = "";
    $where_clauses = ["trainkey = ?"];

    // Handle TOTAL_HOURS as a special case (assuming it might come from a different table/logic)
    if ($rule['requirement_type'] === 'TOTAL_HOURS') {
        // This is a placeholder for the logic to sum hours.
        // Based on the README, this might query a 'logbook' table.
        // For now, we'll simulate a query on trainee_log for a specific 'hours' field (e.g., stid=60)
        $base_query = "SELECT SUM(CAST(select_val AS DECIMAL(10,2))) FROM trainee_log";
        $where_clauses[] = "stid = 60"; // Example STID for hours
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
        } elseif (!is_null($rule['stid'])) {
            $where_clauses[] = "stid = ?";
            $types .= 'i';
            $params[] = $rule['stid'];
        }

        // Add field_value condition
        if (!empty($rule['field_value'])) {
            if ($rule['requirement_type'] === 'UNIQUE_VALUES_IN_RANGE') {
                list($start, $end) = explode('-', $rule['field_value']);
                $where_clauses[] = "CAST(select_val AS SIGNED) BETWEEN ? AND ?";
                $types .= 'ii';
                $params[] = trim($start);
                $params[] = trim($end);
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
    if ($population_logkeys !== null) {
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
        $final_query = $base_query . " WHERE " . implode(" AND ", $where_clauses);
        $stmt = $mysqli->prepare($final_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->bind_result($current_value);
        $stmt->fetch();
        $stmt->close();
    }
    
    $current_value = $current_value ?? 0;
    $is_passed = ($current_value >= $rule['required_value']);

    // --- Step B: If this rule passed and has children, evaluate them ---
    $child_results = [];
    $all_children_passed = true;
    if ($is_passed && !empty($rule['children'])) {
        // Get the specific population of logkeys that satisfied THIS rule
        $new_population_logkeys = _getLogkeysForPassedRule($rule, $traineeKey, $mysqli, $population_logkeys);
        
        foreach ($rule['children'] as &$child_rule) {
            $child_result = _evaluateRule($child_rule, $traineeKey, $mysqli, $new_population_logkeys);
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
 * @param mysqli $mysqli The database connection.
 * @param array|null $population_logkeys A pre-filtered list of logkeys from a parent rule.
 * @return array A detailed result object for this rule with subfield breakdown.
 */
function _evaluateSubfieldRule($rule, $traineeKey, $mysqli, $population_logkeys = null) {
    $subfield_results = [];
    $all_subfields_passed = true;
    
    // Evaluate each subfield rule
    foreach ($rule['subfield_rules'] as $subfield_rule) {
        $subfield_result = _evaluateSubfieldIndividualRule($rule, $subfield_rule, $traineeKey, $mysqli, $population_logkeys);
        $subfield_results[] = $subfield_result;
        
        if (!$subfield_result['is_passed']) {
            $all_subfields_passed = false;
        }
    }
    
    // The main rule passes only if ALL subfield rules pass
    $final_is_passed = $all_subfields_passed;
    
    return [
        'standard_name' => $rule['standard_name'],
        'is_passed' => $final_is_passed,
        'current_value' => count(array_filter($subfield_results, function($r) { return $r['is_passed']; })),
        'required_value' => count($rule['subfield_rules']),
        'subfield_rules' => $subfield_results,
        'children' => [] // Subfield rules don't have children in the traditional sense
    ];
}

/**
 * Evaluates an individual subfield rule.
 *
 * @param array $main_rule The main rule containing the subfield rules.
 * @param array $subfield_rule The specific subfield rule to evaluate.
 * @param string $traineeKey The trainee's key.
 * @param mysqli $mysqli The database connection.
 * @param array|null $population_logkeys A pre-filtered list of logkeys from a parent rule.
 * @return array A detailed result object for this subfield rule.
 */
function _evaluateSubfieldIndividualRule($main_rule, $subfield_rule, $traineeKey, $mysqli, $population_logkeys = null) {
    $params = [$traineeKey];
    $types = 's';
    $where_clauses = ["trainkey = ?"];
    
    // Add the main field condition
    if (!is_null($main_rule['stid'])) {
        $where_clauses[] = "stid = ?";
        $types .= 'i';
        $params[] = $main_rule['stid'];
    }
    
    // Add the subfield value condition
    if (!empty($subfield_rule['subfield_value'])) {
        $where_clauses[] = "select_val = ?";
        $types .= 's';
        $params[] = $subfield_rule['subfield_value'];
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
    
    // Build the query based on requirement type
    $base_query = "";
    switch ($subfield_rule['requirement_type']) {
        case 'UNIQUE_VALUES':
            $base_query = "SELECT COUNT(DISTINCT logkey) FROM trainee_log";
            break;
        case 'UNIQUE_VALUES_IN_RANGE':
            $base_query = "SELECT COUNT(DISTINCT logkey) FROM trainee_log";
            // Add range condition if specific_value contains a range
            if (!empty($subfield_rule['specific_value']) && strpos($subfield_rule['specific_value'], '-') !== false) {
                list($start, $end) = explode('-', $subfield_rule['specific_value']);
                $where_clauses[] = "CAST(logkey AS SIGNED) BETWEEN ? AND ?";
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
    $stmt = $mysqli->prepare($final_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($current_value);
    $stmt->fetch();
    $stmt->close();
    
    $current_value = $current_value ?? 0;
    $required_value = (int)($subfield_rule['specific_value'] ?? 0);
    $is_passed = ($current_value >= $required_value);
    
    return [
        'subfield_value' => $subfield_rule['subfield_value'],
        'subfield_name' => _getSubfieldName($subfield_rule['subfield_value'], $mysqli),
        'requirement_type' => $subfield_rule['requirement_type'],
        'is_passed' => $is_passed,
        'current_value' => $current_value,
        'required_value' => $required_value
    ];
}

/**
 * Gets the display name for a subfield value.
 *
 * @param string $subfield_value The subfield value ID.
 * @param mysqli $mysqli The database connection.
 * @return string The display name for the subfield value.
 */
function _getSubfieldName($subfield_value, $mysqli) {
    if (empty($subfield_value)) {
        return 'Unknown';
    }
    
    $query = "SELECT select_val FROM select_gen WHERE pid = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $subfield_value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row['select_val'] : 'Unknown';
}

/**
 * Gets the specific logkeys that satisfied a given rule.
 * This is used to define the population for the next level of child rules.
 *
 * @param array $rule The rule that was passed.
 * @param string $traineeKey The trainee's key.
 * @param mysqli $mysqli The database connection.
 * @param array|null $parent_population_logkeys The logkey population from the parent.
 * @return array An array of logkeys.
 */
function _getLogkeysForPassedRule($rule, $traineeKey, $mysqli, $parent_population_logkeys = null) {
    // This function re-builds the WHERE clause from _evaluateRule but selects `logkey`
    // This is a simplified version for brevity. The actual implementation would mirror
    // the query construction logic from _evaluateRule to ensure consistency.
    $params = [$traineeKey];
    $types = 's';
    $where_clauses = ["trainkey = ?"];

    if (!is_null($rule['stid'])) {
        $where_clauses[] = "stid = ?";
        $types .= 'i';
        $params[] = $rule['stid'];
    }
    
    if (!empty($rule['field_value'])) {
       if ($rule['requirement_type'] === 'UNIQUE_VALUES_IN_RANGE') {
            list($start, $end) = explode('-', $rule['field_value']);
            $where_clauses[] = "CAST(select_val AS SIGNED) BETWEEN ? AND ?";
            $types .= 'ii';
            $params[] = trim($start);
            $params[] = trim($end);
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
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $logkeys = [];
    while($row = $result->fetch_assoc()) {
        $logkeys[] = $row['logkey'];
    }
    $stmt->close();
    return $logkeys;
}
?> 