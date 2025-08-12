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

// Get the standard ID from the URL
$psid = isset($_GET['which']) ? (int)$_GET['which'] : 0;

if (!$psid) {
    header("Location: $listurl");
    exit();
}

// Fetch the pass standard
$stmt = $mysqli->prepare("SELECT * FROM pass_standards WHERE psid = ?");
$stmt->bind_param("i", $psid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: $listurl");
    exit();
}

$standard = $result->fetch_assoc();
$stmt->close();

// Fetch related data
$table_query = "SELECT tab_name FROM tabs_tbl WHERE tbid = ?";
$table_stmt = $mysqli->prepare($table_query);
$table_stmt->bind_param("i", $standard['tbid']);
$table_stmt->execute();
$table_result = $table_stmt->get_result();
$table_name = $table_result->fetch_assoc()['tab_name'] ?? 'Unknown';
$table_stmt->close();

$field_query = "SELECT str FROM select_types WHERE stid = ?";
$field_stmt = $mysqli->prepare($field_query);
$field_stmt->bind_param("i", $standard['stid']);
$field_stmt->execute();
$field_result = $field_stmt->get_result();
$field_name = $field_result->fetch_assoc()['str'] ?? 'Unknown';
$field_stmt->close();

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

// Debug output
error_log("View Pass Standard Debug - PSID: " . $psid);
error_log("Field value: " . ($standard['field_value'] ?? 'NULL'));
error_log("Contains SUBFIELD_RULES: " . (strpos($standard['field_value'] ?? '', 'SUBFIELD_RULES:') !== false ? 'Yes' : 'No'));
error_log("Main field value: " . $main_field_value);
error_log("Subfield rules count: " . count($subfield_rules));
error_log("Parsed subfield rules: " . json_encode($subfield_rules));

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

// Fetch parent standard name
$parent_name = '';
if (!empty($standard['parent_standard_id'])) {
    $parent_stmt = $mysqli->prepare("SELECT standard_name FROM pass_standards WHERE psid = ?");
    $parent_stmt->bind_param("i", $standard['parent_standard_id']);
    $parent_stmt->execute();
    $parent_result = $parent_stmt->get_result();
    if ($parent_row = $parent_result->fetch_assoc()) {
        $parent_name = $parent_row['standard_name'];
    }
    $parent_stmt->close();
}

// Fetch who created/modified (simplified - just show the usrkey)
$who_by_name = $standard['who_by'] ?? 'Unknown';
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

                            <!-- Main Field Value Filter -->
                            <?php if (!empty($main_field_value)): ?>
                            <div class="subfield-rules-section">
                                <h5>Main Field Value Filter</h5>
                                <div class="info-item">
                                    <div class="info-label">Filter Value</div>
                                    <div class="info-value"><?php echo htmlspecialchars($main_field_value); ?></div>
                                </div>
                                <small class="text-muted">This filter is applied to the main field before subfield rules are evaluated.</small>
                            </div>
                            <?php endif; ?>

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

                            <!-- Raw Data (for debugging) -->
                            <?php if ($admintype === 'AT'): // Only show for admin users ?>
                            <div class="subfield-rules-section">
                                <h5>Raw Data (Debug)</h5>
                                <div class="info-item">
                                    <div class="info-label">Field Value (Raw)</div>
                                    <div class="info-value">
                                        <code style="word-break: break-all;"><?php echo htmlspecialchars($standard['field_value'] ?? 'NULL'); ?></code>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Parsed Subfield Rules</div>
                                    <div class="info-value">
                                        <pre><?php echo json_encode($subfield_rules, JSON_PRETTY_PRINT); ?></pre>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <?php include 'incl/adminjs.php' ?>
</body>
</html>
