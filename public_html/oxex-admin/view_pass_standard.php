<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Ensure proper access control
if(!(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'DV'))) {
    header("Location: index.php");
    exit();
}

$pagetitle = "View Pass Standard";

// Set variables needed by adminjs.php
$whichDocModal = 3; // Tables section
$value0 = 0; // Default value for $value0 = 0; // Default value for sort_order
$subtitle = "View Pass Standard";
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
    } elseif ($requirement_type == 'TOTAL_HOURS_COMBINED') {
        $explanation = "This rule requires a <strong>total of {$required_value} hours</strong> combined from multiple sources";
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
    
    // Handle TOTAL_HOURS_COMBINED specially
    if ($requirement_type == 'TOTAL_HOURS_COMBINED' && !empty($field_value)) {
        try {
            $total_of_data = json_decode($field_value, true);
            if (isset($total_of_data['total_of']) && is_array($total_of_data['total_of'])) {
                global $supabase_pdo;
                $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
                
                $explanation .= "<br><br><strong>Combined Sources:</strong>";
                foreach ($total_of_data['total_of'] as $index => $source) {
                    $source_num = $index + 1;
                    $category_stid = $source['category_stid'] ?? '';
                    $category_value = $source['category_value'] ?? '';
                    
                    // Get category name
                    $category_name = "Unknown Category";
                    if ($pdo && $category_stid) {
                        $cat_stmt = $pdo->prepare("SELECT str FROM select_types WHERE stid = ?");
                        $cat_stmt->execute([$category_stid]);
                        $cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($cat_row) {
                            $category_name = $cat_row['str'];
                        }
                    }
                    
                    $value_display = '';
                    if ($category_value) {
                        // Get value name if it's a PID
                        if (is_numeric($category_value) && $pdo) {
                            $val_stmt = $pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                            $val_stmt->execute([$category_value]);
                            $val_row = $val_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($val_row) {
                                $value_display = htmlspecialchars($val_row['select_val']);
                            } else {
                                $value_display = htmlspecialchars($category_value);
                            }
                        } else {
                            $value_display = htmlspecialchars($category_value);
                        }
                    } else {
                        $value_display = '<em>all values</em>';
                    }
                    
                    $max_display = '';
                    if (isset($source['max_value']) && $source['max_value'] !== null) {
                        $max_display = " (max: <strong>" . htmlspecialchars($source['max_value']) . " hours</strong>)";
                    }
                    $explanation .= "<br><strong>Source {$source_num}:</strong> {$category_name} = {$value_display}{$max_display}";
                }
                return $explanation;
            } elseif (isset($total_of_data['hour_sources']) && is_array($total_of_data['hour_sources'])) {
                // Legacy support for old hour_sources format
                global $supabase_pdo;
                $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
                
                $explanation .= "<br><br><strong>Combined Sources:</strong>";
                foreach ($total_of_data['hour_sources'] as $index => $source) {
                    $source_num = $index + 1;
                    $category_stid = $source['category_stid'] ?? '';
                    $category_value = $source['category_value'] ?? '';
                    
                    // Get category name
                    $category_name = "Unknown Category";
                    if ($pdo && $category_stid) {
                        $cat_stmt = $pdo->prepare("SELECT str FROM select_types WHERE stid = ?");
                        $cat_stmt->execute([$category_stid]);
                        $cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($cat_row) {
                            $category_name = $cat_row['str'];
                        }
                    }
                    
                    $value_display = '';
                    if ($category_value) {
                        $value_display = htmlspecialchars($category_value);
                    } else {
                        $value_display = '<em>all values</em>';
                    }
                    
                    $explanation .= "<br><strong>Source {$source_num}:</strong> {$category_name} = {$value_display}";
                }
                return $explanation;
            }
        } catch (Exception $e) {
            error_log("Error parsing TOTAL_HOURS_COMBINED data: " . $e->getMessage());
            $explanation .= " (Error parsing sources data)";
        }
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
                    $rule_num = $index + 1;
                    if (isset($rule['any_of']) && is_array($rule['any_of'])) {
                        $pieces = [];
                        foreach ($rule['any_of'] as $alt) {
                            if (empty($alt['subfield_value']) || empty($alt['requirement_type']) || empty($alt['specific_value'])) continue;
                            $rule_type = str_replace('_', ' ', strtolower($alt['requirement_type']));
                            // Resolve subfield name
                            global $supabase_pdo;
                            $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
                            $subfield_name = "Unknown Subfield";
                            if ($pdo) {
                                $subfield_stmt = $pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                                $subfield_stmt->execute([(int)$alt['subfield_value']]);
                                $subfield_row = $subfield_stmt->fetch(PDO::FETCH_ASSOC);
                                if ($subfield_row) { $subfield_name = $subfield_row['select_val']; }
                            }
                            $piece = "<em>" . htmlspecialchars($subfield_name) . "</em>: <strong>" . htmlspecialchars($alt['specific_value']) . "</strong> " . $rule_type;
                            if ($alt['requirement_type'] == 'PER_CASE_MINIMUM' && !empty($alt['minimum_threshold'])) {
                                $piece .= " (min per case: <strong>" . htmlspecialchars($alt['minimum_threshold']) . "</strong>)";
                            }
                            $pieces[] = $piece;
                        }
                        if (!empty($pieces)) {
                            $explanation .= "<br><strong>Rule {$rule_num} (any of):</strong> " . implode(' <strong>OR</strong> ', $pieces);
                        }
                    } elseif (!empty($rule['subfield_value']) && !empty($rule['requirement_type']) && !empty($rule['specific_value'])) {
                        $rule_type = str_replace('_', ' ', strtolower($rule['requirement_type']));
                        // Resolve subfield name
                        global $supabase_pdo;
                        $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
                        $subfield_query = "SELECT select_val FROM select_gen WHERE pid = ?";
                        $subfield_name = "Unknown Subfield";
                        if ($pdo) {
                            $subfield_stmt = $pdo->prepare($subfield_query);
                            $subfield_stmt->execute([(int)$rule['subfield_value']]);
                            $subfield_row = $subfield_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($subfield_row) {
                                $subfield_name = $subfield_row['select_val'];
                            }
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

// Fetch the pass standard with related data using PDO
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if (!$pdo) {
    header("Location: $listurl");
    exit();
}

$stmt = $pdo->prepare("
    SELECT 
        ps.*,
        t.tab_name,
        st.str as field_name,
        parent.standard_name as parent_name,
        (SELECT STRING_AGG(st_or.str, ', ')
         FROM pass_standard_fields psf 
         JOIN select_types st_or ON psf.stid::int = st_or.stid 
         WHERE psf.standard_id::int = ps.psid) as or_fields
    FROM pass_standards ps
    LEFT JOIN tabs_tbl t ON ps.tbid::int = t.tbid
    LEFT JOIN select_types st ON ps.stid::int = st.stid
    LEFT JOIN pass_standards parent ON ps.parent_standard_id::int = parent.psid
    WHERE ps.psid = ?
");
$stmt->execute([$psid]);
$standard = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$standard) {
    header("Location: $listurl");
    exit();
}
// PDO: no explicit close needed

// Extract data from the main query
$table_name = $standard['tab_name'] ?? 'Unknown';
$field_name = $standard['field_name'] ?? 'Unknown';

// Parse subfield rules - handle both old format and new any_of format
$subfield_rules = [];
$subfield_rule_groups = []; // For displaying OR groups
$main_field_value = '';
if (!empty($standard['field_value']) && strpos($standard['field_value'], 'SUBFIELD_RULES:') !== false) {
    $json_part = str_replace('SUBFIELD_RULES:', '', $standard['field_value']);
    $json_part = trim($json_part);
    
    try {
        $rules = json_decode($json_part, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($rules)) {
            foreach ($rules as $rule_index => $rule) {
                // Handle new any_of format (OR conditions)
                if (isset($rule['any_of']) && is_array($rule['any_of'])) {
                    $group = [
                        'type' => 'any_of',
                        'rules' => []
                    ];
                    foreach ($rule['any_of'] as $alt_rule) {
                        if (isset($alt_rule['subfield_value']) && isset($alt_rule['requirement_type']) && isset($alt_rule['specific_value'])) {
                            $subfield_rules[] = $alt_rule;
                            $group['rules'][] = $alt_rule;
                        }
                    }
                    if (!empty($group['rules'])) {
                        $subfield_rule_groups[] = $group;
                    }
                }
                // Handle old single rule format
                elseif (isset($rule['subfield_value']) && isset($rule['requirement_type']) && isset($rule['specific_value'])) {
                    $subfield_rules[] = $rule;
                    $subfield_rule_groups[] = [
                        'type' => 'single',
                        'rules' => [$rule]
                    ];
                }
            }
        } else {
            error_log("JSON decode error: " . json_last_error_msg() . " for: " . $json_part);
        }
    } catch (Exception $e) {
        error_log("Exception parsing subfield rules: " . $e->getMessage() . " for: " . $json_part);
    }
}

// Debug output removed for cleaner code

// Fetch subfield names
$subfield_names = [];
if (!empty($subfield_rules)) {
    foreach ($subfield_rules as $rule) {
        if (!empty($rule['subfield_value'])) {
            $subfield_query = "SELECT select_val FROM select_gen WHERE pid = ?";
            $subfield_stmt = $pdo->prepare($subfield_query);
            $subfield_stmt->execute([(int)$rule['subfield_value']]);
            $subfield_row = $subfield_stmt->fetch(PDO::FETCH_ASSOC);
            if ($subfield_row) {
                $subfield_names[$rule['subfield_value']] = $subfield_row['select_val'];
            }
        }
    }
}

// Extract parent name from the main query
$parent_name = $standard['parent_name'] ?? '';

// Fetch who created/modified - get actual user name (PDO)
$who_by_name = 'Unknown';
if (!empty($standard['who_by'])) {
    $user_query = "SELECT realname FROM who_there WHERE usrkey = ?";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$standard['who_by']]);
    $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_row) {
        $who_by_name = $user_row['realname'];
    }
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
            grid-template-columns: 1fr;
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
                        <span class="card-title">Standard Details</span>
                        <div class="d-flex align-items-center">
                            <h3 class="mb-0 mr-3"><?php echo htmlspecialchars($standard['standard_name']); ?></h3>
                            <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#explanationModal" data-explanation="<?php echo htmlspecialchars(generateStandardExplanation($standard), ENT_QUOTES); ?>" data-title="<?php echo htmlspecialchars($standard['standard_name'], ENT_QUOTES); ?>">
                                <i class="fa fa-info-circle"></i> Explain Rule
                            </button>
                            <a href="pass_standard_detail.php?which=<?php echo $psid; ?>" class="btn btn-primary btn-sm ml-2">
                                <i class="fas fa-edit"></i> Edit Standard
                            </a>
                            <a href="<?php echo $listurl; ?>" class="btn btn-secondary btn-sm ml-2">
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

                            <?php 
                            // Parse total_of sources if this is TOTAL_HOURS_COMBINED
                            $total_of_sources = [];
                            if ($standard['requirement_type'] == 'TOTAL_HOURS_COMBINED' && !empty($standard['field_value'])) {
                                try {
                                    $total_of_data = json_decode($standard['field_value'], true);
                                    if (isset($total_of_data['total_of']) && is_array($total_of_data['total_of'])) {
                                        $total_of_sources = $total_of_data['total_of'];
                                    } elseif (isset($total_of_data['hour_sources']) && is_array($total_of_data['hour_sources'])) {
                                        // Legacy support
                                        $total_of_sources = $total_of_data['hour_sources'];
                                    }
                                } catch (Exception $e) {
                                    error_log("Error parsing total_of data: " . $e->getMessage());
                                }
                            }
                            ?>
                            
                            <?php if (!empty($total_of_sources)): ?>
                            <!-- Combined Sources -->
                            <div class="combined-sources-section mt-4">
                                <h5>Combined Sources</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Source</th>
                                                <th>Category</th>
                                                <th>Category Value</th>
                                                <th>Max Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($total_of_sources as $index => $source): ?>
                                            <tr>
                                                <td><strong>Source <?php echo $index + 1; ?></strong></td>
                                                <td>
                                                    <?php
                                                    $category_stid = $source['category_stid'] ?? '';
                                                    $category_name = 'Unknown';
                                                    if ($category_stid) {
                                                        $cat_stmt = $pdo->prepare("SELECT str FROM select_types WHERE stid = ?");
                                                        $cat_stmt->execute([$category_stid]);
                                                        $cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                                                        if ($cat_row) {
                                                            $category_name = $cat_row['str'];
                                                        }
                                                    }
                                                    echo htmlspecialchars($category_name);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $category_value = $source['category_value'] ?? '';
                                                    if ($category_value) {
                                                        // Get value name if it's a PID
                                                        if (is_numeric($category_value)) {
                                                            $val_stmt = $pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                                                            $val_stmt->execute([$category_value]);
                                                            $val_row = $val_stmt->fetch(PDO::FETCH_ASSOC);
                                                            if ($val_row) {
                                                                echo htmlspecialchars($val_row['select_val']);
                                                            } else {
                                                                echo htmlspecialchars($category_value);
                                                            }
                                                        } else {
                                                            echo htmlspecialchars($category_value);
                                                        }
                                                    } else {
                                                        echo '<em class="text-muted">All values</em>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $max_value = $source['max_value'] ?? null;
                                                    if ($max_value !== null && $max_value !== '') {
                                                        echo '<strong>' . htmlspecialchars($max_value) . '</strong> hours max';
                                                    } else {
                                                        echo '<em class="text-muted">No limit</em>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">
                                    These sources will be summed together to meet the required total value. If a source has a max value, it will be capped at that amount.
                                </small>
                            </div>
                            <?php endif; ?>

                            <!-- Subfield Rules -->
                            <?php if (empty($total_of_sources)): ?>
                            <div class="subfield-rules-section">
                                <h5>Subfield Rules</h5>
                                <?php if (!empty($subfield_rule_groups)): ?>
                                    <div class="subfield-rules-table">
                                        <div class="subfield-rules-header">
                                            <div>Subfield Value</div>
                                            <div>Rule Type</div>
                                            <div>Rule Value</div>
                                            <div>Group</div>
                                        </div>
                                        <?php foreach ($subfield_rule_groups as $group_index => $group): ?>
                                            <?php if ($group['type'] === 'any_of'): ?>
                                                <!-- OR Group -->
                                                <?php foreach ($group['rules'] as $rule_index => $rule): ?>
                                                <div class="subfield-rule-row" style="background-color: #f8f9fa;">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($subfield_names[$rule['subfield_value']] ?? 'Unknown'); ?></strong>
                                                        <br>
                                                        <small class="text-muted">ID: <?php echo $rule['subfield_value']; ?></small>
                                                    </div>
                                                    <div><?php echo str_replace('_', ' ', $rule['requirement_type']); ?></div>
                                                    <div><?php echo htmlspecialchars($rule['specific_value']); ?></div>
                                                    <div>
                                                        <span class="badge badge-warning">OR Group <?php echo $group_index + 1; ?></span>
                                                        <?php if ($rule_index === 0): ?>
                                                            <br><small class="text-muted">Base Rule</small>
                                                        <?php else: ?>
                                                            <br><small class="text-muted">OR Alternative</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <!-- Single Rule -->
                                                <?php foreach ($group['rules'] as $rule): ?>
                                                <div class="subfield-rule-row">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($subfield_names[$rule['subfield_value']] ?? 'Unknown'); ?></strong>
                                                        <br>
                                                        <small class="text-muted">ID: <?php echo $rule['subfield_value']; ?></small>
                                                    </div>
                                                    <div><?php echo str_replace('_', ' ', $rule['requirement_type']); ?></div>
                                                    <div><?php echo htmlspecialchars($rule['specific_value']); ?></div>
                                                    <div>
                                                        <span class="badge badge-info">Single Rule</span>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">
                                        <strong>OR Groups:</strong> Rules in the same group are alternatives (any one can be met).<br>
                                        <strong>Single Rules:</strong> Individual requirements that must be met.
                                    </small>
                                <?php else: ?>
                                    <div class="no-rules">
                                        <i class="fas fa-info-circle fa-2x mb-3"></i>
                                        <p>No subfield rules defined for this standard.</p>
                                        <p>Click "Edit Standard" to add subfield rules.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
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
