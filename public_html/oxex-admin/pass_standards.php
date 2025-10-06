<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

$pagetitle = "Pass Standards";
$subtitle = "Manage Pass/Fail Criteria";

// Function to generate human-readable explanation of a pass standard
function generateStandardExplanation($row) {
    $explanation = "";
    
    // Basic structure
    $requirement_type = $row['requirement_type'];
    $required_value = $row['required_value'];
    $table_name = $row['tab_name'];
    $field_name = $row['field_name'];
    $or_fields = $row['or_fields'];
    $field_value = $row['field_value'];
    $parent_name = $row['parent_name'];
    
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
    
    // Add category context - be very specific about which category/categories
    if (!empty($field_name)) {
        $explanation .= " for the <strong>{$field_name}</strong> category";
    } elseif (!empty($or_fields)) {
        $explanation .= " for <strong>any of these categories: {$or_fields}</strong>";
    } else {
        $explanation .= " (no specific category selected)";
    }
    
    // Add category value filter - be specific about what values are checked
    if (!empty($field_value) && strpos($field_value, 'SUBFIELD_RULES:') === false) {
        $explanation .= " where the category value equals <strong>{$field_value}</strong>";
    }
    
    // Add subfield rules explanation - robustly handle multiple SUBFIELD_RULES segments
    if (!empty($field_value) && strpos($field_value, 'SUBFIELD_RULES:') !== false) {
        try {
            $explanation .= "<br><br><strong>Within the main requirement, there are specific subfield rules:</strong>";
            $rule_sets = explode('|', $field_value);
            $rule_index = 0;
            foreach ($rule_sets as $rule_set) {
                $pos = strpos($rule_set, 'SUBFIELD_RULES:');
                if ($pos === false) { continue; }
                $json_text = trim(substr($rule_set, $pos + strlen('SUBFIELD_RULES:')));
                $parsed = json_decode($json_text, true);
                if (!is_array($parsed) || empty($parsed)) { continue; }
                foreach ($parsed as $rule) {
                    if (empty($rule['subfield_value']) || empty($rule['requirement_type']) || !isset($rule['specific_value'])) { continue; }
                    $rule_index++;
                    $rule_type = str_replace('_', ' ', strtolower($rule['requirement_type']));
                    // Resolve subfield name
                    global $mysqli;
                    $subfield_name = 'Unknown';
                    if ($stmt_sf = $mysqli->prepare("SELECT select_val FROM select_gen WHERE pid = ?")) {
                        $pid = (int)$rule['subfield_value'];
                        $stmt_sf->bind_param("i", $pid);
                        $stmt_sf->execute();
                        $res_sf = $stmt_sf->get_result();
                        if ($row_sf = $res_sf->fetch_assoc()) {
                            $subfield_name = $row_sf['select_val'];
                        }
                        $stmt_sf->close();
                    }
                    $explanation .= "<br><strong>Rule {$rule_index}:</strong> For subcategory <strong>" . htmlspecialchars($subfield_name) . "</strong>, require <strong>" . htmlspecialchars($rule['specific_value']) . "</strong> " . $rule_type;
                    if ($rule['requirement_type'] == 'PER_CASE_MINIMUM' && !empty($rule['minimum_threshold'])) {
                        $explanation .= " where each case must meet a minimum of <strong>" . htmlspecialchars($rule['minimum_threshold']) . "</strong>";
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
        $explanation .= " for the {$field_name} category";
    } elseif (!empty($or_fields)) {
        $explanation .= " for any of: {$or_fields}";
    }
    $explanation .= ".</div>";
    
    return $explanation;
}

if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'DV')) {

// Handle Delete Action
$del = isset($_GET['del']) ? $_GET['del'] : '';
$which = isset($_GET['which']) ? (int)$_GET['which'] : 0;
$delalert = '';

if ($del == "del" && $which > 0) {
    $stmt = $mysqli->prepare("DELETE FROM pass_standards WHERE psid = ? LIMIT 1");
    $stmt->bind_param("i", $which); 
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Record deleted successfully.'];
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Failed to delete record.'];
    }
    $stmt->close();
    header("Location: pass_standards.php"); // Redirect to clear GET params and show message
    exit();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
    <?php include 'incl/admincss.php' ?>
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
                        <a href="pass_standard_detail.php" class="btn btn-sm btn-info ml-5">Add New Standards</a>
                        <small><?php echo $subtitle ?></small>
                    </div>
                </div>
                
                <?php
                // Display flash message if it exists
                if (isset($_SESSION['flash_message'])) {
                    $flash = $_SESSION['flash_message'];
                    echo "<div class='alert alert-{$flash['type']}' role='alert'>{$flash['message']}</div>";
                    unset($_SESSION['flash_message']); // Clear the message
                }
                ?>

                <div class="card card-default">
                    <div class="card-header">Manage Pass Standards</div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <strong>Tip:</strong> Use "Add New Standards" to create pass standards with multiple subfield rules. This allows you to define different requirements for various subfield values within the same field, making your standards more comprehensive and flexible.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped my-4 w-100" id="passStandardsTable">
                                <thead>
                                    <tr>
                                        <th>Standard Name</th>
                                        <th>Applies to Table</th>
                                        <th>Parent Standard</th>
                                        <th>Category/Categories</th>
                                        <th>Requirement Type</th>
                                        <th>Required Value</th>
                                        <th>Active</th>
                                        <th>Explanation</th>
                                        <th data-priority="1">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query = "
                                        SELECT 
                                            ps.psid, 
                                            ps.standard_name, 
                                            ps.requirement_type, 
                                            ps.required_value, 
                                            ps.is_active,
                                            t.tab_name,
                                            st.str as field_name,
                                            ps.field_value,
                                            parent.standard_name as parent_name,
                                            (SELECT GROUP_CONCAT(st_or.str SEPARATOR ', ') 
                                             FROM pass_standard_fields psf 
                                             JOIN select_types st_or ON psf.stid = st_or.stid 
                                             WHERE psf.standard_id = ps.psid) as or_fields
                                        FROM 
                                            pass_standards ps
                                        LEFT JOIN 
                                            tabs_tbl t ON ps.tbid = t.tbid
                                        LEFT JOIN
                                            select_types st ON ps.stid = st.stid
                                        LEFT JOIN
                                            pass_standards parent ON ps.parent_standard_id = parent.psid
                                        ORDER BY 
                                            t.tab_name, parent.standard_name, ps.standard_name";
                                    
                                    if ($stmt = $mysqli->prepare($query)) {
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        
                                        while ($row = $result->fetch_assoc()) {
                                            $status_badge = $row['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
                                            
                                            // Determine what to show in the category column
                                            $field_display = 'N/A';
                                            if (!empty($row['field_name'])) {
                                                $field_display = htmlspecialchars($row['field_name']);
                                                
                                                // Check if there are subfield rules in field_value
                                                if (!empty($row['field_value']) && strpos($row['field_value'], 'SUBFIELD_RULES:') !== false) {
                                                    try {
                                                        $subfield_rules = json_decode(str_replace('SUBFIELD_RULES:', '', $row['field_value']), true);
                                                        if (is_array($subfield_rules) && !empty($subfield_rules)) {
                                                            $subfield_names = [];
                                                            foreach ($subfield_rules as $rule) {
                                                                if (!empty($rule['subfield_value'])) {
                                                                    // Get subfield name from select_gen table
                                                                    $subfield_query = "SELECT select_val FROM select_gen WHERE pid = ?";
                                                                    if ($subfield_stmt = $mysqli->prepare($subfield_query)) {
                                                                        $subfield_stmt->bind_param("i", $rule['subfield_value']);
                                                                        $subfield_stmt->execute();
                                                                        $subfield_result = $subfield_stmt->get_result();
                                                                        if ($subfield_row = $subfield_result->fetch_assoc()) {
                                                                            $subfield_names[] = htmlspecialchars($subfield_row['select_val']);
                                                                        }
                                                                        $subfield_stmt->close();
                                                                    }
                                                                }
                                                            }
                                                            if (!empty($subfield_names)) {
                                                                $field_display .= '<br><small class="text-muted">Subcategories: ' . implode(', ', $subfield_names) . '</small>';
                                                            }
                                                        }
                                                    } catch (Exception $e) {
                                                        // Log error but don't break the display
                                                        error_log("Error parsing subfield rules: " . $e->getMessage());
                                                    }
                                                }
                                            } elseif (!empty($row['or_fields'])) {
                                                $field_display = "<i>Multiple Categories (OR):</i><br>" . htmlspecialchars($row['or_fields']);
                                            }

                                            // Generate explanation
                                            $explanation = generateStandardExplanation($row);
                                            
                                            echo "<tr>
                                                <td>" . htmlspecialchars($row['standard_name']) . "</td>
                                                <td>" . htmlspecialchars($row['tab_name']) . "</td>
                                                <td>" . ($row['parent_name'] ? htmlspecialchars($row['parent_name']) : '<em>None</em>') . "</td>
                                                <td>" . $field_display . "</td>
                                                <td>" . htmlspecialchars(str_replace('_', ' ', $row['requirement_type'])) . "</td>
                                                <td>" . htmlspecialchars($row['required_value']) . "</td>
                                                <td>{$status_badge}</td>
                                                <td>
                                                    <button type='button' class='btn btn-sm btn-outline-info' data-toggle='modal' data-target='#explanationModal' data-explanation='" . htmlspecialchars($explanation, ENT_QUOTES) . "' data-title='" . htmlspecialchars($row['standard_name'], ENT_QUOTES) . "'>
                                                        <i class='fa fa-info-circle'></i>
                                                    </button>
                                                </td>
                                                <td>
                                                    <div class='btn-group' role='group'>
                                                        <a href='view_pass_standard.php?which={$row['psid']}' class='btn btn-sm btn-secondary'>View</a>
                                                        <a href='pass_standard_detail.php?which={$row['psid']}' class='btn btn-sm btn-info'>Edit</a>
                                                        <a href='pass_standards.php?del=del&which={$row['psid']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this standard?\");'>Delete</a>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                        $stmt->close();
                                    }
                                    ?>
                                </tbody>
                            </table>
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
            $('#passStandardsTable').DataTable({
                "pageLength": 25,
                "order": [[ 1, "asc" ], [ 2, "asc" ]],
                "columnDefs": [
                    { "orderable": false, "targets": [7, 8] } // Explanation and Actions columns
                ]
            });
            
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
<?php
} else {
    echo "Not authorised.";
}
?> 