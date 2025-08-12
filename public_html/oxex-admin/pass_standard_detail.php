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
        
        /* Style for subfield rules table */
        .subfield-rules-table {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .subfield-rules-header {
            background: #f8f9fa;
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 80px;
            gap: 15px;
            padding: 12px 15px;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
        }
        
        .subfield-rule-row {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 80px;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            align-items: center;
        }
        
        .subfield-rule-row:last-child {
            border-bottom: none;
        }
        
        .subfield-rule-row:hover {
            background: #f8f9fa;
        }
        
        .rule-col {
            min-width: 0;
        }
        
        .rule-col-actions {
            text-align: center;
        }
        
        .remove-rule-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
        }
        
        .remove-rule-btn:hover {
            background: #c82333;
        }
        
        @media (max-width: 768px) {
            .subfield-rules-header,
            .subfield-rule-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .rule-col-actions {
                text-align: left;
            }
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

                            <div class="form-group" id="subfieldContainer" style="display:none;">
                                <label>Subfield Rules (Optional)</label>
                                <div class="subfield-rules-table">
                                                                    <div class="subfield-rules-header">
                                    <div class="rule-col">Subfield Value</div>
                                    <div class="rule-col">Rule Type</div>
                                    <div class="rule-col">Rule Value</div>
                                    <div class="rule-col-actions">Actions</div>
                                </div>
                                    <div id="subfieldRulesContainer">
                                        <!-- Rules will be added here dynamically -->
                                    </div>
                                </div>
                                <button type="button" id="addSubfieldRuleBtn" class="btn btn-success btn-sm mt-2">
                                    <i class="fa fa-plus"></i> Add Rule
                                </button>
                                <small class="form-text text-muted">Create specific rules for individual subfield values. Each rule can have different requirement types and values. These rules will be applied to the selected subfield values.</small>
                            </div>

                            <div class="form-group" id="multipleFieldContainer" style="display:none;">
                                <label for="stids">Fields to Check (OR condition)</label>
                                <select class="form-control" id="stids" name="stids[]" multiple>
                                     <!-- Options loaded by JS -->
                                </select>
                                <small class="form-text text-muted">The rule will pass if the condition is met in ANY of the selected fields.</small>
                            </div>

                            <div class="form-group" id="mainFieldValueContainer">
                                <label for="field_value">Field Value Filter (Optional)</label>
                                <input type="text" class="form-control" id="field_value" name="field_value" value="<?php echo htmlspecialchars($standard['field_value'] ?? ''); ?>">
                                <small class="form-text text-muted">Filter the main field before applying subfield rules. For ranges, use a hyphen (e.g., 18-64). Leave empty to check all field values.</small>
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

    function loadSubfieldsForField(fieldId, callback) {
        var $subfieldContainer = $('#subfieldContainer');
        var $subfieldRulesContainer = $('#subfieldRulesContainer');
        var $mainFieldValueContainer = $('#mainFieldValueContainer');
        
        if (!fieldId) {
            $subfieldContainer.hide();
            $mainFieldValueContainer.show();
            return;
        }

        // Get the actual subfield values from select_gen table
        $.getJSON('ajax/get_subfields_for_field.php', { stid: fieldId }, function(subfields) {
            if (subfields && subfields.length > 0) {
                // Store subfields globally for use in rule creation
                window.availableSubfields = subfields;
                $subfieldContainer.show();
                $mainFieldValueContainer.hide(); // Hide main field value when using subfield rules
                // Clear existing rules and add first rule
                $subfieldRulesContainer.empty();
                addSubfieldRule();
            } else {
                $subfieldContainer.hide();
                $mainFieldValueContainer.show(); // Show main field value when no subfields
            }
            
            if (callback) callback();
        }).fail(function() {
            // If the AJAX call fails, assume no subfields
            $subfieldContainer.hide();
            $mainFieldValueContainer.show();
        });
    }

    function addSubfieldRule() {
        var ruleIndex = $('.subfield-rule-row').length;
        var ruleHtml = `
            <div class="subfield-rule-row" data-rule="${ruleIndex}">
                <div class="rule-col">
                    <select class="form-control subfield-select" name="subfield_rules[${ruleIndex}][subfield_values]">
                        <option value="">-- Select One Subfield Value --</option>
                        ${getAvailableSubfieldOptions()}
                    </select>
                    <small class="form-text text-muted">Choose one subfield value for this rule</small>
                </div>
                <div class="rule-col">
                    <select class="form-control" name="subfield_rules[${ruleIndex}][requirement_type]" required>
                        <option value="">-- Select Rule Type --</option>
                        <option value="TOTAL_COUNT">Total Count</option>
                        <option value="UNIQUE_VALUES">Unique Values</option>
                        <option value="UNIQUE_VALUES_IN_RANGE">Unique Values in Range</option>
                    </select>
                    <small class="form-text text-muted">What to count for this rule</small>
                </div>
                <div class="rule-col">
                    <input type="text" class="form-control" name="subfield_rules[${ruleIndex}][specific_value]" placeholder="e.g., 100 or 18-64" required>
                    <small class="form-text text-muted">The value this rule must meet</small>
                </div>
                <div class="rule-col-actions">
                    <button type="button" class="remove-rule-btn" onclick="removeSubfieldRule(${ruleIndex})">×</button>
                </div>
            </div>
        `;
        
        $('#subfieldRulesContainer').append(ruleHtml);
        
        // Initialize Select2 for the new subfield select
        var $newSelect = $('#subfieldRulesContainer').find('.subfield-rule-row[data-rule="' + ruleIndex + '"] .subfield-select');
        $newSelect.select2({
            placeholder: '-- Select One Subfield Value --',
            width: '100%',
            allowClear: true
        });
        
        // Add change event to update available options in other rules
        $newSelect.on('change', function() {
            updateAvailableSubfieldOptions();
        });
    }

    function getAvailableSubfieldOptions() {
        if (!window.availableSubfields) return '';
        
        var usedValues = getUsedSubfieldValues();
        var availableOptions = window.availableSubfields.filter(function(subfield) {
            return !usedValues.includes(subfield.pid);
        });
        
        return availableOptions.map(function(subfield) {
            return `<option value="${subfield.pid}">${subfield.str}</option>`;
        }).join('');
    }

    function getUsedSubfieldValues() {
        var usedValues = [];
        $('.subfield-rule-row').each(function() {
            var selectedValue = $(this).find('.subfield-select').val();
            if (selectedValue && selectedValue !== '') {
                usedValues.push(selectedValue);
            }
        });
        return usedValues;
    }

    function updateAvailableSubfieldOptions() {
        $('.subfield-rule-row').each(function() {
            var $select = $(this).find('.subfield-select');
            var currentValue = $select.val();
            var usedValues = getUsedSubfieldValues();
            
            // Clear current options
            $select.find('option:not(:first)').remove();
            
            // Add available options
            if (window.availableSubfields) {
                window.availableSubfields.forEach(function(subfield) {
                    // Include current value and unused values
                    if (subfield.pid == currentValue || !usedValues.includes(subfield.pid)) {
                        var option = new Option(subfield.str, subfield.pid, false, false);
                        $select.append(option);
                    }
                });
            }
            
            // Restore current selection
            $select.val(currentValue).trigger('change');
        });
    }

    function removeSubfieldRule(ruleIndex) {
        $('.subfield-rule-row[data-rule="' + ruleIndex + '"]').remove();
        // Reindex remaining rules
        $('.subfieldRulesContainer').find('.subfield-rule-row').each(function(index) {
            $(this).attr('data-rule', index);
            $(this).find('select, input').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                }
            });
            $(this).find('.remove-rule-btn').attr('onclick', 'removeSubfieldRule(' + index + ')');
        });
        
        // Update available options after removing a rule
        updateAvailableSubfieldOptions();
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
                // Load subfields if editing and field is selected
                if (initialStid && initialStid !== 'null') {
                    loadSubfieldsForField(initialStid);
                }
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
        // Clear subfields when table changes
        $('#subfieldContainer').hide();
        $('#subfieldRulesContainer').empty();
        $('#mainFieldValueContainer').show();
    });
    
    $('#stid').on('change', function() {
        var fieldId = $(this).val();
        loadSubfieldsForField(fieldId);
    });
    
    $('input[name="field_logic_mode"]').on('change', function() {
        toggleFieldLogicMode();
    });

    // Add Rule button event handler
    $(document).on('click', '#addSubfieldRuleBtn', function() {
        addSubfieldRule();
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