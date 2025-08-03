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

$pagetitle = "Pass Standard Details";
$listurl = "pass_standards.php";
$listname = "Pass Standards";
$done = false;

// Get the standard ID from the URL, if it exists
$psid = isset($_GET['which']) ? (int)$_GET['which'] : 0;
$is_editing = $psid > 0;

// Initialize variables
$standard = [
    'standard_name' => '',
    'tbid' => 0,
    'stid' => null,
    'requirement_type' => 'TOTAL_COUNT',
    'required_value' => 0,
    'field_value' => null,
    'parent_standard_id' => null,
    'is_active' => 1
];

$selected_or_fields = [];

if ($is_editing) {
    $subtitle = "Edit Pass Standard";
    $stmt = $mysqli->prepare("SELECT * FROM pass_standards WHERE psid = ?");
    $stmt->bind_param("i", $psid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $standard = $result->fetch_assoc();
        // If the main stid is null, it's an OR condition, so fetch the fields
        if (is_null($standard['stid'])) {
            $or_stmt = $mysqli->prepare("SELECT stid FROM pass_standard_fields WHERE standard_id = ?");
            $or_stmt->bind_param("i", $psid);
            $or_stmt->execute();
            $or_result = $or_stmt->get_result();
            while($row = $or_result->fetch_assoc()) {
                $selected_or_fields[] = $row['stid'];
            }
            $or_stmt->close();
        }
    }
    $stmt->close();
} else {
    $subtitle = "Add New Pass Standard";
}

// Fetch all tables for the dropdown
$tables_query = "SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name ASC";
$tables_result = $mysqli->query($tables_query);

// Fetch all possible parent standards
$parents_query = "SELECT psid, standard_name, tbid FROM pass_standards";
if ($is_editing) {
    // A standard cannot be its own parent
    $parents_query .= " WHERE psid != " . $psid;
}
$parents_result = $mysqli->query($parents_query);
$all_parents = [];
while($parent = $parents_result->fetch_assoc()) {
    $all_parents[] = $parent;
}

// Fetch all fields for the dropdown
$fields_query = "SELECT stid, str FROM select_types ORDER BY str ASC";
$fields_result = $mysqli->query($fields_query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
    <?php include 'incl/admincss.php' ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            padding: .375rem .75rem;
        }
    </style>
</head>

<body>
<div class="wrapper">
    <?php include 'incl/topbar.php' ?>
    <?php include 'incl/sidebar.php' ?>
    <?php include 'incl/offsidebar.php' ?>

    <section class="section-container">
        <div class="content-wrapper">
            <div class="content-header">
                <div class="content-title"><?php echo $pagetitle ?>
                    <small><?php echo $subtitle ?></small>
                </div>
                <div class="ml-auto">
                    <a href="<?php echo $listurl ?>" class="btn btn-secondary">Back to List</a>
                </div>
            </div>

            <div class="card card-default">
                <div class="card-header"><?php echo $is_editing ? 'Edit Standard: ' . htmlspecialchars($standard['standard_name']) : 'Create New Pass Standard'; ?></div>
                <div class="card-body">
                    <form id="standardForm" novalidate>
                        <input type="hidden" name="psid" value="<?php echo $psid; ?>">

                        <div class="form-group">
                            <label for="standard_name">Standard Name*</label>
                            <input type="text" class="form-control" id="standard_name" name="standard_name" value="<?php echo htmlspecialchars($standard['standard_name']); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="tbid">Applies to Table*</label>
                                <select class="form-control" id="tbid" name="tbid" required>
                                    <option value="">-- Select a Table --</option>
                                    <?php while($row = $tables_result->fetch_assoc()): ?>
                                        <option value="<?php echo $row['tbid']; ?>" <?php echo ($standard['tbid'] == $row['tbid']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($row['tab_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="requirement_type">Requirement Type*</label>
                                <select class="form-control" id="requirement_type" name="requirement_type" required>
                                    <option value="TOTAL_HOURS" <?php echo ($standard['requirement_type'] == 'TOTAL_HOURS') ? 'selected' : ''; ?>>Total Hours</option>
                                    <option value="UNIQUE_VALUES" <?php echo ($standard['requirement_type'] == 'UNIQUE_VALUES') ? 'selected' : ''; ?>>Unique Values</option>
                                    <option value="TOTAL_COUNT" <?php echo ($standard['requirement_type'] == 'TOTAL_COUNT') ? 'selected' : ''; ?>>Total Count</option>
                                    <option value="UNIQUE_VALUES_IN_RANGE" <?php echo ($standard['requirement_type'] == 'UNIQUE_VALUES_IN_RANGE') ? 'selected' : ''; ?>>Unique Values in Range</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="parent_standard_id">Parent Standard (for nested rules)</label>
                            <select class="form-control" id="parent_standard_id" name="parent_standard_id">
                                <option value="">-- None (this is a main rule) --</option>
                                <?php foreach($all_parents as $parent): ?>
                                    <option class="parent-option" style="display:none;" value="<?php echo $parent['psid']; ?>" data-tbid="<?php echo $parent['tbid']; ?>" <?php echo ($standard['parent_standard_id'] == $parent['psid']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($parent['standard_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">A rule can only be nested under another rule from the same table.</small>
                        </div>

                        <div id="fieldDependentSection" class="card card-body mb-3">
                            <div class="form-group">
                                <label>How should the field(s) be checked?</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="field_logic_mode" id="logicSingle" value="single" <?php echo (is_null($standard['stid']) && !empty($selected_or_fields)) ? '' : 'checked'; ?>>
                                    <label class="form-check-label" for="logicSingle">On a Single Field</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="field_logic_mode" id="logicMultiple" value="multiple" <?php echo (is_null($standard['stid']) && !empty($selected_or_fields)) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="logicMultiple">On Multiple Fields (OR condition)</label>
                                </div>
                            </div>
                        
                            <div class="form-group" id="singleFieldContainer">
                                <label for="stid">Field to Check</label>
                                <select class="form-control" id="stid" name="stid">
                                    <option value="">-- Select a Table First --</option>
                                </select>
                                <small class="form-text text-muted">Required for 'Unique Values' and 'Total Count'. Ignored for 'Total Hours'.</small>
                            </div>

                            <div class="form-group" id="multipleFieldContainer" style="display:none;">
                                <label for="stids">Fields to Check (OR condition)</label>
                                <select class="form-control" id="stids" name="stids[]" multiple>
                                     <!-- Options loaded by JS -->
                                </select>
                                <small class="form-text text-muted">The rule will pass if the condition is met in ANY of the selected fields.</small>
                            </div>

                            <div class="form-group">
                                <label for="field_value">Specific Value / Range (Optional)</label>
                                <input type="text" class="form-control" id="field_value" name="field_value" value="<?php echo htmlspecialchars($standard['field_value'] ?? ''); ?>">
                                <small id="fieldValueHelp" class="form-text text-muted">If set, only entries matching this value will be counted. For OR conditions, separate values with a pipe (|). For ranges, use a hyphen (e.g., 18-64).</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="required_value">Required Value*</label>
                            <input type="number" class="form-control" id="required_value" name="required_value" value="<?php echo (int)$standard['required_value']; ?>" required min="0">
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($standard['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Standard is Active
                            </label>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary">Save Standard</button>
                        <a href="<?php echo $listurl; ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include 'incl/adminjs.php' ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    
    // Initialize Select2
    $('#stids').select2({
        placeholder: '-- Select a Table First --',
        width: '100%'
    });

    var initialTbid = $('#tbid').val();
    var initialStid = '<?php echo $standard['stid'] ?? 'null'; ?>';
    var selectedOrFields = <?php echo json_encode($selected_or_fields); ?>;
    
    function filterParents(tableId) {
        var selectedParent = $('#parent_standard_id').val();
        $('#parent_standard_id option.parent-option').each(function() {
            if ($(this).data('tbid') == tableId) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // If the currently selected parent doesn't belong to the new table, deselect it
        if ($('#parent_standard_id option:selected').data('tbid') != tableId) {
            $('#parent_standard_id').val('');
        }
    }

    function loadFieldsForTable(tableId, callback) {
        var $stidSelect = $('#stid');
        var $stidsMultiSelect = $('#stids');

        $stidSelect.prop('disabled', true).html('<option value="">Loading...</option>');
        $stidsMultiSelect.prop('disabled', true).html('').trigger('change');
        
        if (!tableId) {
            $stidSelect.html('<option value="">-- Select a Table --</option>').prop('disabled', false);
            $stidsMultiSelect.select2({ placeholder: '-- Select a Table --' }).prop('disabled', false);
            return;
        }

        $.getJSON('ajax/get_fields_for_table.php', { tbid: tableId }, function(fields) {
            $stidSelect.html('<option value="">-- Select a Field --</option>');
            $stidsMultiSelect.html(''); // Clear existing

            $.each(fields, function(index, field) {
                var option = new Option(field.str, field.stid, false, false);
                $stidSelect.append(option);
                // Also create an option for the multi-select
                var multiOption = new Option(field.str, field.stid, false, false);
                $stidsMultiSelect.append(multiOption);
            });
            
            $stidSelect.prop('disabled', false);
            $stidsMultiSelect.prop('disabled', false).trigger('change');

            if (callback) callback();
        });
    }
    
    function toggleFieldDependent(type) {
        var helpText = "If set, only entries matching this value will be counted. For OR conditions, separate values with a pipe (|).";
        if (type === 'TOTAL_HOURS') {
            $('#fieldDependentSection').slideUp();
        } else {
            $('#fieldDependentSection').slideDown();
            if (type === 'UNIQUE_VALUES_IN_RANGE') {
                helpText = "Define the numeric range to check (e.g., 18-64).";
            }
        }
        $('#fieldValueHelp').text(helpText);
    }
    
    function toggleFieldLogicMode() {
        if ($('#logicSingle').is(':checked')) {
            $('#singleFieldContainer').show();
            $('#stid').prop('disabled', false);
            $('#multipleFieldContainer').hide();
            $('#stids').prop('disabled', true);
        } else {
            $('#singleFieldContainer').hide();
            $('#stid').prop('disabled', true);
            $('#multipleFieldContainer').show();
            $('#stids').prop('disabled', false);
        }
    }

    // Initial State Setup
    toggleFieldDependent($('#requirement_type').val());
    toggleFieldLogicMode();
    filterParents(initialTbid);
    if (initialTbid) {
        loadFieldsForTable(initialTbid, function() {
            // Restore selections after fields are loaded
            if ($('#logicSingle').is(':checked')) {
                $('#stid').val(initialStid);
            } else {
                $('#stids').val(selectedOrFields).trigger('change');
            }
        });
    }

    // Event Handlers
    $('#requirement_type').on('change', function() {
        toggleFieldDependent($(this).val());
    });

    $('#tbid').on('change', function() {
        var tableId = $(this).val();
        filterParents(tableId);
        loadFieldsForTable(tableId);
    });
    
    $('input[name="field_logic_mode"]').on('change', function() {
        toggleFieldLogicMode();
    });

    // Handle form submission
    $('#standardForm').on('submit', function(e) {
        e.preventDefault();

        // Basic validation
        if (this.checkValidity() === false) {
            $(this).addClass('was-validated');
            return;
        }

        // Before serializing, disable the field input that is not active
        if ($('#logicSingle').is(':checked')) {
            $('#stids').prop('disabled', true);
        } else {
            $('#stid').prop('disabled', true);
        }

        var formData = $(this).serialize();
        var isEditing = $('input[name="psid"]').val() > 0;
        var ajaxUrl = isEditing ? 'ajax/update_pass_standard.php' : 'ajax/add_pass_standard.php';

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // The server now sets a flash message. We just need to redirect.
                    window.location.href = '<?php echo $listurl; ?>';
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                // Try to parse JSON error response
                var errorMsg = 'An unknown error occurred.';
                try {
                    var json = $.parseJSON(xhr.responseText);
                    if(json.message) {
                        errorMsg = json.message;
                    }
                } catch(e) {
                    // Ignore if not JSON
                }
                alert('Request Failed: ' + errorMsg);
            }
        });
    });
});
</script>
</body>
</html> 