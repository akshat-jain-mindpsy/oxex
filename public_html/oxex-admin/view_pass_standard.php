<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Ensure proper access control
if(!(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'DV'))) {
    header("Location: index.php");
    exit();
}

$pagetitle = "View Pass Standard";
$listurl = "pass_standards.php";
$listname = "Pass Standards";

// Function to generate human-readable explanation of a pass standard
function generateStandardExplanation($row) {
    $explanation = "";
    
    // Basic structure
    $requirement_type = $row['requirement_type'];
    $required_value = $row['required_value'];
    $table_name = $row['tab_name'] ?? 'Unknown';
    $field_name = $row['field_name'] ?? '';
    $or_fields = $row['or_fields'] ?? '';
    $field_value = $row['field_value'] ?? '';
    $parent_name = $row['parent_name'] ?? '';
    
    // Get minimum threshold if available (will be null until database column is added)
    $minimum_threshold = isset($row['minimum_threshold']) ? $row['minimum_threshold'] : null;
    
    // Start with the basic requirement - be very specific
    if ($requirement_type == 'PER_CASE_MINIMUM') {
        $explanation = "This rule requires <strong>{$required_value} cases</strong> where <strong>each individual case</strong> must meet a minimum threshold";
        if ($minimum_threshold) {
            $explanation .= " of <strong>{$minimum_threshold}</strong>";
        }
    } elseif ($requirement_type == 'TOTAL_HOURS') {
        $explanation = "This rule requires a <strong>total of {$required_value} hours</strong> across all cases";
    } elseif ($requirement_type == 'TOTAL_COUNT') {
        $explanation = "This rule requires a <strong>total count of {$required_value}</strong> records";
    } elseif ($requirement_type == 'UNIQUE_VALUES') {
        $explanation = "This rule requires <strong>{$required_value} unique values</strong>";
    } elseif ($requirement_type == 'UNIQUE_VALUES_IN_RANGE') {
        $explanation = "This rule requires <strong>{$required_value} unique values within a specified range</strong>";
    } else {
        $explanation = "This rule requires <strong>{$required_value}</strong> " . str_replace('_', ' ', strtolower($requirement_type));
    }
    
    // Add table context
    $explanation .= " in the <strong>{$table_name}</strong> table";
    
    // Add field context - be very specific about which field(s)
    if (!empty($field_name)) {
        $explanation .= " for the <strong>{$field_name}</strong> field";
    } elseif (!empty($or_fields)) {
        $explanation .= " for <strong>any of these fields: {$or_fields}</strong>";
    } else {
        $explanation .= " (no specific field selected)";
    }
    
    // Add field value filter - be specific about what values are checked
    if (!empty($field_value) && strpos($field_value, 'SUBFIELD_RULES:') === false) {
        $explanation .= " where the field value equals <strong>{$field_value}</strong>";
    }
    
    // Add subfield rules explanation - be very detailed
    if (!empty($field_value) && strpos($field_value, 'SUBFIELD_RULES:') !== false) {
        try {
            $subfield_rules = json_decode(str_replace('SUBFIELD_RULES:', '', $field_value), true);
            if (is_array($subfield_rules) && !empty($subfield_rules)) {
                $explanation .= "<br><br><strong>Within the main requirement, there are specific subfield rules:</strong>";
                foreach ($subfield_rules as $index => $rule) {
                    if (!empty($rule['subfield_value']) && !empty($rule['requirement_type']) && !empty($rule['specific_value'])) {
                        $rule_num = $index + 1;
                        $rule_type = str_replace('_', ' ', strtolower($rule['requirement_type']));
                        
                        // Get subfield name from database
                        global $mysqli;
                        $subfield_query = "SELECT select_val FROM select_gen WHERE pid = ?";
                        $subfield_name = "Unknown Subfield";
                        if ($subfield_stmt = $mysqli->prepare($subfield_query)) {
                            $subfield_stmt->bind_param("i", $rule['subfield_value']);
                            $subfield_stmt->execute();
                            $subfield_result = $subfield_stmt->get_result();
                            if ($subfield_row = $subfield_result->fetch_assoc()) {
                                $subfield_name = $subfield_row['select_val'];
                            }
                            $subfield_stmt->close();
                        }
                        
                        $explanation .= "<br><strong>Rule {$rule_num}:</strong> For subfield <strong>{$subfield_name}</strong>, require <strong>{$rule['specific_value']}</strong> " . $rule_type;
                        
                        if ($rule['requirement_type'] == 'PER_CASE_MINIMUM' && !empty($rule['minimum_threshold'])) {
                            $explanation .= " where each case must meet a minimum of <strong>{$rule['minimum_threshold']}</strong>";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $explanation .= "<br><br><strong>Subfield rules:</strong> (Error parsing subfield rules)";
        }
    }
    
    // Add parent context
    if (!empty($parent_name)) {
        $explanation .= "<br><br><em><strong>Note:</strong> This is a sub-rule that applies within the context of: <strong>{$parent_name}</strong></em>";
    }
    
    // Add summary at the end
    $explanation .= "<br><br><div class='alert alert-info'><strong>Summary:</strong> ";
    if ($requirement_type == 'PER_CASE_MINIMUM') {
        $explanation .= "You need {$required_value} cases, and each case must meet the minimum threshold";
        if ($minimum_threshold) {
            $explanation .= " of {$minimum_threshold}";
        }
    } else {
        $explanation .= "You need {$required_value} " . str_replace('_', ' ', strtolower($requirement_type));
    }
    $explanation .= " in the {$table_name} table";
    if (!empty($field_name)) {
        $explanation .= " for the {$field_name} field";
    } elseif (!empty($or_fields)) {
        $explanation .= " for any of: {$or_fields}";
    }
    $explanation .= ".</div>";
    
    return $explanation;
}

// Get the standard ID from the URL
$psid = isset($_GET['which']) ? (int)$_GET['which'] : 0;

if (!$psid) {
    header("Location: $listurl");
    exit();
}

// Fetch the pass standard with related data
$stmt = $mysqli->prepare("
    SELECT 
        ps.*,
        t.tab_name,
        st.str as field_name,
        parent.standard_name as parent_name,
        (SELECT GROUP_CONCAT(st_or.str SEPARATOR ', ') 
         FROM pass_standard_fields psf 
         JOIN select_types st_or ON psf.stid = st_or.stid 
         WHERE psf.standard_id = ps.psid) as or_fields
    FROM pass_standards ps
    LEFT JOIN tabs_tbl t ON ps.tbid = t.tbid
    LEFT JOIN select_types st ON ps.stid = st.stid
    LEFT JOIN pass_standards parent ON ps.parent_standard_id = parent.psid
    WHERE ps.psid = ?
");
$stmt->bind_param("i", $psid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: $listurl");
    exit();
}

$standard = $result->fetch_assoc();
$stmt->close();

// Extract data from the main query
$table_name = $standard['tab_name'] ?? 'Unknown';
$field_name = $standard['field_name'] ?? 'Unknown';

// Parse subfield rules - handle multiple SUBFIELD_RULES entries
$subfield_rules = [];
$main_field_value = '';
if (!empty($standard['field_value']) && strpos($standard['field_value'], 'SUBFIELD_RULES:') !== false) {
    // Split by | to get individual rule sets
    $rule_sets = explode('|', $standard['field_value']);
    $main_field_value = trim($rule_sets[0]);
    
    // Process each rule set
    foreach ($rule_sets as $rule_set) {
        if (strpos($rule_set, 'SUBFIELD_RULES:') !== false) {
            $json_part = str_replace('SUBFIELD_RULES:', '', $rule_set);
            $json_part = trim($json_part);
            
            try {
                $rules = json_decode($json_part, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($rules)) {
                    // Merge rules from this set
                    foreach ($rules as $rule) {
                        if (isset($rule['subfield_value']) && isset($rule['requirement_type']) && isset($rule['specific_value'])) {
                            $subfield_rules[] = $rule;
                        }
                    }
                } else {
                    error_log("JSON decode error in rule set: " . json_last_error_msg() . " for: " . $json_part);
                }
            } catch (Exception $e) {
                error_log("Exception parsing rule set: " . $e->getMessage() . " for: " . $json_part);
            }
        }
    }
    
    // Remove duplicates based on subfield_value
    $unique_rules = [];
    $seen_values = [];
    foreach ($subfield_rules as $rule) {
        if (!in_array($rule['subfield_value'], $seen_values)) {
            $unique_rules[] = $rule;
            $seen_values[] = $rule['subfield_value'];
        }
    }
    $subfield_rules = $unique_rules;
}

// Debug output removed for cleaner code

// Fetch subfield names
$subfield_names = [];
if (!empty($subfield_rules)) {
    foreach ($subfield_rules as $rule) {
        if (!empty($rule['subfield_value'])) {
            $subfield_query = "SELECT select_val FROM select_gen WHERE pid = ?";
            $subfield_stmt = $mysqli->prepare($subfield_query);
            $subfield_stmt->bind_param("i", $rule['subfield_value']);
            $subfield_stmt->execute();
            $subfield_result = $subfield_stmt->get_result();
            if ($subfield_row = $subfield_result->fetch_assoc()) {
                $subfield_names[$rule['subfield_value']] = $subfield_row['select_val'];
            }
            $subfield_stmt->close();
        }
    }
}

// Extract parent name from the main query
$parent_name = $standard['parent_name'] ?? '';

// Fetch who created/modified - get actual user name
$who_by_name = 'Unknown';
if (!empty($standard['who_by'])) {
    $user_query = "SELECT realname FROM who_there WHERE usrkey = ?";
    $user_stmt = $mysqli->prepare($user_query);
    $user_stmt->bind_param("s", $standard['who_by']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user_row = $user_result->fetch_assoc()) {
        $who_by_name = $user_row['realname'];
    }
    $user_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
    <?php include 'incl/admincss.php' ?>
    <style>
        .subfield-rules-table {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            margin: 15px 0;
        }
        .subfield-rules-header {
            background: #e9ecef;
            padding: 12px;
            font-weight: bold;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 80px;
            gap: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .subfield-rule-row {
            padding: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 80px;
            gap: 15px;
            border-bottom: 1px solid #dee2e6;
            align-items: center;
        }
        .subfield-rule-row:last-child {
            border-bottom: none;
        }
        .subfield-rule-row:nth-child(even) {
            background: #f8f9fa;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }
        .info-value {
            color: #212529;
        }
        .subfield-rules-section {
            background: white;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .no-rules {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 40px 20px;
        }
    </style>
</head>
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
                    <div class="content-title"><?php echo $pagetitle ?>
                        <small>View Standard Details</small>
                    </div>
                </div>
                
                <div class="card card-default">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title"><?php echo htmlspecialchars($standard['standard_name']); ?></h3>
                        <div>
                            <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#explanationModal" data-explanation="<?php echo htmlspecialchars(generateStandardExplanation($standard), ENT_QUOTES); ?>" data-title="<?php echo htmlspecialchars($standard['standard_name'], ENT_QUOTES); ?>">
                                <i class="fa fa-info-circle"></i> Explain Rule
                            </button>
                            <a href="pass_standard_detail.php?which=<?php echo $psid; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Edit Standard
                            </a>
                            <a href="<?php echo $listurl; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                        
                        <div class="card-body">
                            <!-- Basic Information -->
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Standard Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($standard['standard_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="badge <?php echo $standard['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $standard['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Applies to Table</div>
                                    <div class="info-value"><?php echo htmlspecialchars($table_name); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Field to Check</div>
                                    <div class="info-value"><?php echo htmlspecialchars($field_name); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Requirement Type</div>
                                    <div class="info-value"><?php echo str_replace('_', ' ', $standard['requirement_type']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Required Value</div>
                                    <div class="info-value"><?php echo htmlspecialchars($standard['required_value']); ?></div>
                                </div>
                                <?php if (!empty($parent_name)): ?>
                                <div class="info-item">
                                    <div class="info-label">Parent Standard</div>
                                    <div class="info-value"><?php echo htmlspecialchars($parent_name); ?></div>
                                </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <div class="info-label">Created/Modified By</div>
                                    <div class="info-value"><?php echo htmlspecialchars($who_by_name); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Date Added</div>
                                    <div class="info-value"><?php echo $standard['date_added'] ? date('Y-m-d H:i:s', $standard['date_added']) : 'N/A'; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Date Modified</div>
                                    <div class="info-value"><?php echo $standard['date_modified'] ? date('Y-m-d H:i:s', $standard['date_modified']) : 'N/A'; ?></div>
                                </div>
                            </div>


                            <!-- Subfield Rules -->
                            <div class="subfield-rules-section">
                                <h5>Subfield Rules</h5>
                                <?php if (!empty($subfield_rules)): ?>
                                    <div class="subfield-rules-table">
                                        <div class="subfield-rules-header">
                                            <div>Subfield Value</div>
                                            <div>Rule Type</div>
                                            <div>Rule Value</div>
                                            <div>Actions</div>
                                        </div>
                                        <?php foreach ($subfield_rules as $index => $rule): ?>
                                        <div class="subfield-rule-row">
                                            <div>
                                                <strong><?php echo htmlspecialchars($subfield_names[$rule['subfield_value']] ?? 'Unknown'); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $rule['subfield_value']; ?></small>
                                            </div>
                                            <div><?php echo str_replace('_', ' ', $rule['requirement_type']); ?></div>
                                            <div><?php echo htmlspecialchars($rule['specific_value']); ?></div>
                                            <div>
                                                <span class="badge badge-info">Rule <?php echo $index + 1; ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">These rules define specific requirements for individual subfield values.</small>
                                <?php else: ?>
                                    <div class="no-rules">
                                        <i class="fas fa-info-circle fa-2x mb-3"></i>
                                        <p>No subfield rules defined for this standard.</p>
                                        <p>Click "Edit Standard" to add subfield rules.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Explanation Modal -->
    <div class="modal fade" id="explanationModal" tabindex="-1" role="dialog" aria-labelledby="explanationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="explanationModalLabel">Rule Explanation</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="explanationContent">
                        <!-- Content will be populated by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'incl/adminjs.php' ?>
    <script>
        $(document).ready(function() {
            // Handle explanation modal
            $('#explanationModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var explanation = button.data('explanation');
                var title = button.data('title');
                
                var modal = $(this);
                modal.find('.modal-title').text('Rule Explanation: ' + title);
                modal.find('#explanationContent').html(explanation);
            });
        });
    </script>
</body>
</html>
