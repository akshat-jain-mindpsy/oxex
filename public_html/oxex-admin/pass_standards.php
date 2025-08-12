<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

$pagetitle = "Pass Standards";
$subtitle = "Manage Pass/Fail Criteria";

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
                                        <th>Field(s)</th>
                                        <th>Requirement Type</th>
                                        <th>Required Value</th>
                                        <th>Active</th>
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
                                            
                                            // Determine what to show in the field column
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
                                                                $field_display .= '<br><small class="text-muted">Subfields: ' . implode(', ', $subfield_names) . '</small>';
                                                            }
                                                        }
                                                    } catch (Exception $e) {
                                                        // Log error but don't break the display
                                                        error_log("Error parsing subfield rules: " . $e->getMessage());
                                                    }
                                                }
                                            } elseif (!empty($row['or_fields'])) {
                                                $field_display = "<i>Multiple (OR):</i><br>" . htmlspecialchars($row['or_fields']);
                                            }

                                            echo "<tr>
                                                <td>" . htmlspecialchars($row['standard_name']) . "</td>
                                                <td>" . htmlspecialchars($row['tab_name']) . "</td>
                                                <td>" . ($row['parent_name'] ? htmlspecialchars($row['parent_name']) : '<em>None</em>') . "</td>
                                                <td>" . $field_display . "</td>
                                                <td>" . htmlspecialchars(str_replace('_', ' ', $row['requirement_type'])) . "</td>
                                                <td>" . htmlspecialchars($row['required_value']) . "</td>
                                                <td>{$status_badge}</td>
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
    <?php include 'incl/adminjs.php' ?>
    <script>
        $(document).ready(function() {
            $('#passStandardsTable').DataTable({
                "pageLength": 25,
                "order": [[ 1, "asc" ], [ 2, "asc" ]],
                "columnDefs": [
                    { "orderable": false, "targets": 6 }
                ]
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