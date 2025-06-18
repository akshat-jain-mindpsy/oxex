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
    'is_active' => 1
];

if ($is_editing) {
    $subtitle = "Edit Pass Standard";
    $stmt = $mysqli->prepare("SELECT * FROM pass_standards WHERE psid = ?");
    $stmt->bind_param("i", $psid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $standard = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    $subtitle = "Add New Pass Standard";
}

// Fetch all tables for the dropdown
$tables_query = "SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name ASC";
$tables_result = $mysqli->query($tables_query);

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
    <style>
        #fieldSelectionGroup { display: none; }
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
                                </select>
                            </div>
                        </div>

                        <div id="fieldDependentSection">
                            <div class="form-group">
                                <label for="stid">Field to Check</label>
                                <select class="form-control" id="stid" name="stid" disabled>
                                    <option value="">-- Select a Table First --</option>
                                </select>
                                <small class="form-text text-muted">Required for 'Unique Values' and 'Total Count'. Ignored for 'Total Hours'.</small>
                            </div>

                            <div class="form-group" id="fieldValueContainer">
                                <label for="field_value">Specific Value (Optional)</label>
                                <input type="text" class="form-control" id="field_value" name="field_value" value="<?php echo htmlspecialchars($standard['field_value'] ?? ''); ?>">
                                <small class="form-text text-muted">If set, only entries matching this value will be counted.</small>
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
<script>
$(document).ready(function() {
    
    var initialTbid = $('#tbid').val();
    var initialStid = '<?php echo $standard['stid'] ?? 'null'; ?>';
    var initialFieldValue = '<?php echo addslashes(htmlspecialchars($standard['field_value'] ?? '')); ?>';

    function loadFieldsForTable(tableId, callback) {
        var $stidSelect = $('#stid');
        $stidSelect.prop('disabled', true).html('<option value="">Loading fields...</option>');
        
        if (!tableId) {
            $stidSelect.html('<option value="">-- Select a Table First --</option>');
            // Also reset field value input
            updateFieldValueInput(null, '');
            return;
        }

        $.getJSON('ajax/get_fields_for_table.php', { tbid: tableId }, function(fields) {
            $stidSelect.prop('disabled', false).html('<option value="">-- Select a Field (Optional) --</option>');
            $.each(fields, function(index, field) {
                $stidSelect.append($('<option>', {
                    value: field.stid,
                    text: field.str
                }));
            });
            if (callback) callback();
        });
    }

    function updateFieldValueInput(options, currentValue) {
        var $container = $('#fieldValueContainer');
        $container.find('input, select').remove(); // Remove old input/select

        var $newElement;
        if (options && options.length > 0) {
            $newElement = $('<select>', {
                class: 'form-control',
                id: 'field_value',
                name: 'field_value'
            });
            $newElement.append('<option value="">-- Select a Value --</option>');
            $.each(options, function(index, option) {
                $newElement.append($('<option>', {
                    value: option,
                    text: option,
                    selected: (option == currentValue)
                }));
            });
        } else {
            $newElement = $('<input>', {
                type: 'text',
                class: 'form-control',
                id: 'field_value',
                name: 'field_value',
                value: currentValue
            });
        }
        $container.find('label').after($newElement); // Place the new element after the label
    }

    function loadOptionsForField(fieldId, callback) {
        if (!fieldId) {
            updateFieldValueInput(null, initialFieldValue);
            if(callback) callback();
            return;
        }

        $.getJSON('ajax/get_options_for_field.php', { stid: fieldId }, function(options) {
            updateFieldValueInput(options, initialFieldValue);
            if (callback) callback();
        });
    }
    
    // Function to toggle field-dependent inputs
    function toggleFieldDependent(type) {
        if (type === 'TOTAL_HOURS') {
            $('#fieldDependentSection').slideUp();
            $('#stid').prop('required', false);
        } else {
            $('#fieldDependentSection').slideDown();
            $('#stid').prop('required', true);
        }
    }

    // Initial check on page load
    toggleFieldDependent($('#requirement_type').val());
    if (initialTbid) {
        loadFieldsForTable(initialTbid, function() {
            $('#stid').val(initialStid);
            loadOptionsForField(initialStid);
        });
    }

    // Event handlers
    $('#requirement_type').on('change', function() {
        toggleFieldDependent($(this).val());
    });

    $('#tbid').on('change', function() {
        // When table changes, reset initial field value since it's no longer relevant
        initialFieldValue = ''; 
        loadFieldsForTable($(this).val());
    });

    // Use event delegation for stid since its options are dynamic
    $(document).on('change', '#stid', function() {
        // When field changes, reset initial field value
        initialFieldValue = ''; 
        loadOptionsForField($(this).val());
    });

    // Handle form submission
    $('#standardForm').on('submit', function(e) {
        e.preventDefault();

        // Basic validation
        if (this.checkValidity() === false) {
            $(this).addClass('was-validated');
            return;
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