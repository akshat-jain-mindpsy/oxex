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
    
    // Parse existing subfield rules if editing - handle multiple SUBFIELD_RULES entries
    var existingSubfieldRules = [];
    <?php if ($is_editing && !empty($standard['field_value']) && strpos($standard['field_value'], 'SUBFIELD_RULES:') !== false): ?>
    try {
        var fieldValueData = <?php echo json_encode($standard['field_value']); ?>;
        console.log('🔍 Raw field_value data:', fieldValueData);
        console.log('🔍 Contains SUBFIELD_RULES?:', fieldValueData.includes('SUBFIELD_RULES:'));
        
        if (fieldValueData.includes('SUBFIELD_RULES:')) {
            // Split by | to get individual rule sets
            var ruleSets = fieldValueData.split('|');
            console.log('🔍 Rule sets found:', ruleSets.length);
            
            // Process each rule set
            for (var i = 0; i < ruleSets.length; i++) {
                var ruleSet = ruleSets[i];
                if (ruleSet.includes('SUBFIELD_RULES:')) {
                    var jsonPart = ruleSet.replace('SUBFIELD_RULES:', '').trim();
                    console.log('🔍 Processing rule set ' + (i + 1) + ':', jsonPart);
                    
                    try {
                        var rules = JSON.parse(jsonPart);
                        if (Array.isArray(rules)) {
                            // Merge rules from this set
                            for (var j = 0; j < rules.length; j++) {
                                var rule = rules[j];
                                if (rule.subfield_value && rule.requirement_type && rule.specific_value) {
                                    existingSubfieldRules.push(rule);
                                }
                            }
                        }
                    } catch (parseError) {
                        console.error('❌ Error parsing rule set ' + (i + 1) + ':', parseError);
                    }
                }
            }
            
            // Remove duplicates based on subfield_value
            var uniqueRules = [];
            var seenValues = [];
            for (var k = 0; k < existingSubfieldRules.length; k++) {
                var rule = existingSubfieldRules[k];
                if (seenValues.indexOf(rule.subfield_value) === -1) {
                    uniqueRules.push(rule);
                    seenValues.push(rule.subfield_value);
                }
            }
            existingSubfieldRules = uniqueRules;
            
            console.log('📋 Loaded existing subfield rules:', existingSubfieldRules);
        } else {
            console.log('⚠️ No SUBFIELD_RULES found in field_value');
        }
    } catch (e) {
        console.error('❌ Error parsing existing subfield rules:', e);
        console.error('   Error details:', e.message);
        console.error('   Stack trace:', e.stack);
        existingSubfieldRules = [];
    }
    <?php else: ?>
    console.log('⚠️ Not in editing mode or no field_value or no SUBFIELD_RULES');
    <?php endif; ?>
    
    // Log editing status
    <?php if ($is_editing): ?>
    console.log('✏️ Editing mode - Standard ID: <?php echo $psid; ?>');
    console.log('   Standard name: <?php echo json_encode($standard['standard_name'] ?? ''); ?>');
    console.log('   Field value: <?php echo json_encode($standard['field_value'] ?? ''); ?>');
    console.log('   Has subfield rules: <?php echo (strpos($standard['field_value'] ?? '', 'SUBFIELD_RULES:') !== false) ? 'Yes' : 'No'; ?>');
    <?php else: ?>
    console.log('➕ Creating new pass standard');
    <?php endif; ?>
    
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
            console.log('   Fields received from AJAX:', fields);
            $stidSelect.html('<option value="">-- Select a Field --</option>');
            $stidsMultiSelect.html(''); // Clear existing

            $.each(fields, function(index, field) {
                var option = new Option(field.str, field.stid, false, false);
                $stidSelect.append(option);
                // Also create an option for the multi-select
                var multiOption = new Option(field.str, field.stid, false, false);
                $stidsMultiSelect.append(multiOption);
            });
            
            console.log('   Field options added to stid select. Total options:', $stidSelect.find('option').length);
            console.log('   Available field options:', $stidSelect.find('option').map(function() { return {value: $(this).val(), text: $(this).text()}; }).get());
            
            $stidSelect.prop('disabled', false);
            $stidsMultiSelect.prop('disabled', false).trigger('change');

            if (callback) callback();
        });
    }
    
    function loadSubfieldsForField(fieldId, callback) {
        console.log('🔄 loadSubfieldsForField() called with fieldId:', fieldId);
        
        var $subfieldContainer = $('#subfieldContainer');
        var $subfieldRulesContainer = $('#subfieldRulesContainer');
        var $mainFieldValueContainer = $('#mainFieldValueContainer');
        
        console.log('   Subfield container:', $subfieldContainer[0]);
        console.log('   Subfield rules container:', $subfieldRulesContainer[0]);
        console.log('   Main field value container:', $mainFieldValueContainer[0]);
        
        if (!fieldId) {
            console.log('   No fieldId provided, hiding subfield container');
            $subfieldContainer.hide();
            $mainFieldValueContainer.show();
            return;
        }

        console.log('   Making AJAX call to get_subfields_for_field.php');
        console.log('   Current fieldId parameter:', fieldId);
        console.log('   Current stid select value:', $('#stid').val());
        
        // Get the actual subfield values from select_gen table
        $.getJSON('ajax/get_subfields_for_field.php', { stid: fieldId }, function(subfields) {
            console.log('   AJAX response received:', subfields);
            
            if (subfields && subfields.length > 0) {
                console.log(`   Found ${subfields.length} subfields, showing subfield container`);
                // Store subfields globally for use in rule creation
                window.availableSubfields = subfields;
                console.log('   Stored subfields in window.availableSubfields');
                
                $subfieldContainer.show();
                $mainFieldValueContainer.hide(); // Hide main field value when using subfield rules
                
                console.log('   Clearing existing rules');
                $subfieldRulesContainer.empty();
                
                // If editing and we have existing rules, populate them
                if (existingSubfieldRules.length > 0) {
                    console.log('   Populating existing subfield rules:', existingSubfieldRules.length);
                    existingSubfieldRules.forEach(function(rule, index) {
                        addSubfieldRuleWithData(rule, index);
                    });
                    
                    // Check if there's additional field_value data beyond subfield rules
                    var fieldValueData = <?php echo json_encode($standard['field_value'] ?? ''); ?>;
                    if (fieldValueData && fieldValueData.includes('SUBFIELD_RULES:')) {
                        var beforeSubfieldRules = fieldValueData.split('SUBFIELD_RULES:')[0].trim();
                        if (beforeSubfieldRules) {
                            console.log('   Populating existing field value:', beforeSubfieldRules);
                            $('#field_value').val(beforeSubfieldRules);
                        }
                    }
        } else {
                    console.log('   Adding first rule (no existing rules)');
                    addSubfieldRule();
                }
            } else {
                console.log('   No subfields found, hiding subfield container');
                $subfieldContainer.hide();
                $mainFieldValueContainer.show(); // Show main field value when no subfields
                
                // If editing and we have existing field_value (not subfield rules), populate it
                if (existingSubfieldRules.length === 0 && <?php echo json_encode($standard['field_value'] ?? ''); ?>) {
                    var existingFieldValue = <?php echo json_encode($standard['field_value'] ?? ''); ?>;
                    if (existingFieldValue && !existingFieldValue.includes('SUBFIELD_RULES:')) {
                        console.log('   Populating existing field value:', existingFieldValue);
                        $('#field_value').val(existingFieldValue);
                    }
                }
            }
            
            if (callback) {
                console.log('   Executing callback');
                callback();
            }
        }).fail(function(xhr, status, error) {
            console.error('   AJAX call failed:', {xhr: xhr, status: status, error: error});
            // If the AJAX call fails, assume no subfields
            $subfieldContainer.hide();
            $mainFieldValueContainer.show();
        });
    }

    function addSubfieldRule() {
        console.log('➕ addSubfieldRule() called');
        var ruleIndex = $('.subfield-rule-row').length;
        console.log(`   Creating rule ${ruleIndex + 1}`);
        
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
        
        console.log('   Appending new row to container');
        $('#subfieldRulesContainer').append(ruleHtml);
        
        // Initialize Select2 for the new subfield select
        var $newSelect = $('#subfieldRulesContainer').find('.subfield-rule-row[data-rule="' + ruleIndex + '"] .subfield-select');
        console.log('   New select element:', $newSelect[0]);
        
        console.log('   Initializing Select2');
        $newSelect.select2({
            placeholder: '-- Select One Subfield Value --',
            width: '100%',
            allowClear: true
        });
        
        // Add change event to update available options in other rules
        console.log('   Adding change event listener');
        $newSelect.on('change', function() {
            console.log('   Select2 change event triggered');
            // Simple approach: just rebuild all options
            rebuildAllSubfieldOptions();
        });
        
        console.log('✅ addSubfieldRule() completed');
    }
    
    function addSubfieldRuleWithData(ruleData, ruleIndex) {
        console.log('➕ addSubfieldRuleWithData() called with data:', ruleData, 'index:', ruleIndex);
        
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
        
        console.log('   Appending new row to container');
        $('#subfieldRulesContainer').append(ruleHtml);
        
        // Initialize Select2 for the new subfield select
        var $newSelect = $('#subfieldRulesContainer').find('.subfield-rule-row[data-rule="' + ruleIndex + '"] .subfield-select');
        console.log('   New select element:', $newSelect[0]);
        
        console.log('   Initializing Select2');
        $newSelect.select2({
            placeholder: '-- Select One Subfield Value --',
            width: '100%',
            allowClear: true
        });
        
        // Add change event to update available options in other rules
        console.log('   Adding change event listener');
        $newSelect.on('change', function() {
            console.log('   Select2 change event triggered');
            // Simple approach: just rebuild all options
            rebuildAllSubfieldOptions();
        });
        
        // Populate the form fields with existing data
        console.log('   Populating form fields with existing data');
        $newSelect.val(ruleData.subfield_value).trigger('change');
        $newSelect.closest('.subfield-rule-row').find('select[name*="[requirement_type]"]').val(ruleData.requirement_type);
        $newSelect.closest('.subfield-rule-row').find('input[name*="[specific_value]"]').val(ruleData.specific_value);
        
        console.log('✅ addSubfieldRuleWithData() completed');
    }

    function getAvailableSubfieldOptions() {
        console.log('📋 getAvailableSubfieldOptions() called');
        
        if (!window.availableSubfields) {
            console.log('   No available subfields, returning empty string');
            return '';
        }
        
        console.log('   Available subfields:', window.availableSubfields);
        var usedValues = getUsedSubfieldValues();
        console.log('   Used values:', usedValues);
        
        var availableOptions = window.availableSubfields.filter(function(subfield) {
            var isAvailable = !usedValues.includes(subfield.pid);
            console.log(`   Subfield ${subfield.pid} (${subfield.str}): ${isAvailable ? 'available' : 'used'}`);
            return isAvailable;
        });
        
        console.log('   Filtered available options:', availableOptions);
        
        var result = availableOptions.map(function(subfield) {
            return `<option value="${subfield.pid}">${subfield.str}</option>`;
        }).join('');
        
        console.log('   Generated HTML options:', result);
        return result;
    }

    function getUsedSubfieldValues() {
        console.log('🔍 getUsedSubfieldValues() called');
        var usedValues = [];
        
        try {
            var ruleCount = $('.subfield-rule-row').length;
            console.log(`   Found ${ruleCount} rule rows`);
            
            $('.subfield-rule-row').each(function(index) {
                var $select = $(this).find('.subfield-select');
                var selectedValue = $select.val();
                
                console.log(`   Rule ${index + 1}: select element:`, $select[0]);
                console.log(`   Rule ${index + 1}: selected value:`, selectedValue);
                
                if (selectedValue && selectedValue !== '') {
                    usedValues.push(selectedValue);
                    console.log(`   Added to used values:`, selectedValue);
                }
            });
            
            console.log(`   Final used values:`, usedValues);
        } catch (e) {
            console.error('❌ Error in getUsedSubfieldValues():', e);
            console.error('Stack trace:', e.stack);
        }
        
        return usedValues;
    }


    
    function rebuildAllSubfieldOptions() {
        console.log('🔄 rebuildAllSubfieldOptions() called');
        console.log('📊 Current rule rows:', $('.subfield-rule-row').length);
        
        try {
            var usedValues = getUsedSubfieldValues();
            console.log('🚫 Used values:', usedValues);
            console.log('📋 Available subfields:', window.availableSubfields);
            
            $('.subfield-rule-row').each(function(index) {
                console.log(`📝 Processing rule row ${index + 1}`);
                
                var $select = $(this).find('.subfield-select');
                var currentValue = $select.val();
                
                console.log(`   Select element:`, $select[0]);
                console.log(`   Current value:`, currentValue);
                
                // Clear current options
                var optionsBefore = $select.find('option').length;
                $select.find('option:not(:first)').remove();
                var optionsAfter = $select.find('option').length;
                console.log(`   Cleared options: ${optionsBefore} → ${optionsAfter}`);
                
                // Add available options
                if (window.availableSubfields && Array.isArray(window.availableSubfields)) {
                    var addedOptions = 0;
                    window.availableSubfields.forEach(function(subfield) {
                        // Include current value and unused values
                        if (subfield.pid == currentValue || !usedValues.includes(subfield.pid)) {
                            var option = new Option(subfield.str, subfield.pid, false, false);
                            $select.append(option);
                            addedOptions++;
                        }
                    });
                    console.log(`   Added ${addedOptions} options`);
            } else {
                    console.warn('   No available subfields or invalid format');
                }
                
                // Restore current selection
                $select.val(currentValue);
                console.log(`   Final value set to:`, $select.val());
            });
            
            console.log('✅ rebuildAllSubfieldOptions() completed successfully');
        } catch (e) {
            console.error('❌ Error in rebuildAllSubfieldOptions():', e);
            console.error('Stack trace:', e.stack);
        }
    }

    function removeSubfieldRule(ruleIndex) {
        console.log('🗑️ removeSubfieldRule() called with index:', ruleIndex);
        
        var $ruleToRemove = $('.subfield-rule-row[data-rule="' + ruleIndex + '"]');
        console.log('   Rule to remove:', $ruleToRemove[0]);
        
        $ruleToRemove.remove();
        console.log('   Rule removed from DOM');
        
        // Reindex remaining rules
        console.log('   Reindexing remaining rules');
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
        
        console.log('   Rules reindexed');
        
        // Update available options after removing a rule
        console.log('   Calling rebuildAllSubfieldOptions()');
        rebuildAllSubfieldOptions();
        
        console.log('✅ removeSubfieldRule() completed');
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
    console.log('🚀 Initial State Setup started');
    console.log('   initialTbid:', initialTbid);
    console.log('   initialStid:', initialStid);
    console.log('   existingSubfieldRules:', existingSubfieldRules);
    
    toggleFieldDependent($('#requirement_type').val());
    toggleFieldLogicMode();
    filterParents(initialTbid);
    if (initialTbid) {
        loadFieldsForTable(initialTbid, function() {
            console.log('   Fields loaded for table, restoring selections');
            // Restore selections after fields are loaded
            if ($('#logicSingle').is(':checked')) {
                console.log('   Single field mode, setting stid to:', initialStid);
                $('#stid').val(initialStid);
                console.log('   stid value after setting:', $('#stid').val());
                
                // Check field value after a short delay to see if it was set correctly
                setTimeout(function() {
                    console.log('   stid value after delay:', $('#stid').val());
                    console.log('   stid select element:', $('#stid')[0]);
                    console.log('   stid options:', $('#stid').find('option').map(function() { return {value: $(this).val(), text: $(this).text()}; }).get());
                }, 100);
                
                // Load subfields if editing and field is selected
                if (initialStid && initialStid !== 'null') {
                    console.log('   Field selected, loading subfields');
                    loadSubfieldsForField(initialStid);
                } else if (initialStid === 'null' && existingSubfieldRules.length === 0) {
                    console.log('   No field selected, checking for existing field value');
                    // If editing but no field selected and no subfield rules, show main field value
                    var fieldValueData = <?php echo json_encode($standard['field_value'] ?? ''); ?>;
                    if (fieldValueData && !fieldValueData.includes('SUBFIELD_RULES:')) {
                        console.log('   Editing with existing field value, showing main field value container');
                        $('#mainFieldValueContainer').show();
                        $('#field_value').val(fieldValueData);
                    }
                }
            } else {
                console.log('   Multiple fields mode, setting selected fields');
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
        console.log('🎯 stid change event triggered');
        var fieldId = $(this).val();
        console.log('   Selected fieldId:', fieldId);
        loadSubfieldsForField(fieldId);
    });
    
    $('input[name="field_logic_mode"]').on('change', function() {
        toggleFieldLogicMode();
    });

    // Add Rule button event handler
    $(document).on('click', '#addSubfieldRuleBtn', function() {
        console.log('🔘 Add Rule button clicked');
        addSubfieldRule();
    });

    // Handle form submission
    $('#standardForm').on('submit', function(e) {
        console.log('📝 Form submission started');
        e.preventDefault();

        // Basic validation
        console.log('🔍 Running form validation');
        if (this.checkValidity() === false) {
            console.log('❌ Form validation failed');
            $(this).addClass('was-validated');
            return;
        }
        console.log('✅ Form validation passed');

        // Before serializing, disable the field input that is not active
        if ($('#logicSingle').is(':checked')) {
            console.log('   Disabling multiple fields input (single mode)');
            $('#stids').prop('disabled', true);
        } else {
            console.log('   Disabling single field input (multiple mode)');
            $('#stid').prop('disabled', true);
        }

        console.log('📊 Serializing form data...');
        var formData = $(this).serialize();
        console.log('   Form data:', formData);
        
        // Log subfield rules specifically
        var subfieldRules = [];
        $('.subfield-rule-row').each(function(index) {
            var rule = {
                subfield_value: $(this).find('.subfield-select').val(),
                requirement_type: $(this).find('select[name*="[requirement_type]"]').val(),
                specific_value: $(this).find('input[name*="[specific_value]"]').val()
            };
            subfieldRules.push(rule);
        });
        console.log('   Subfield rules to be sent:', subfieldRules);
        console.log('   Number of subfield rule rows found:', $('.subfield-rule-row').length);
        console.log('   Subfield rules container HTML:', $('#subfieldRulesContainer').html());
        
        // Log all form inputs for debugging
        console.log('🔍 All form inputs:');
        $(this).find('input, select, textarea').each(function() {
            var $input = $(this);
            var name = $input.attr('name');
            var value = $input.val();
            var type = $input.attr('type') || $input.prop('tagName').toLowerCase();
            console.log(`   ${name} (${type}): ${value}`);
        });

        var isEditing = $('input[name="psid"]').val() > 0;
        var ajaxUrl = isEditing ? 'ajax/update_pass_standard.php' : 'ajax/add_pass_standard.php';
        console.log(`   Mode: ${isEditing ? 'Editing' : 'Creating new'}`);
        console.log(`   AJAX URL: ${ajaxUrl}`);

        console.log('🚀 Making AJAX request...');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                console.log('   Request sent to server');
            },
            success: function(response) {
                console.log('✅ AJAX success response received:', response);
                if (response.status === 'success') {
                    console.log('   Success! Redirecting to list page');
                    // The server now sets a flash message. We just need to redirect.
                    window.location.href = '<?php echo $listurl; ?>';
                } else {
                    console.error('   Server returned error status:', response.message);
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX error occurred:');
                console.error('   Status:', status);
                console.error('   Error:', error);
                console.error('   XHR object:', xhr);
                console.error('   Response text:', xhr.responseText);
                console.error('   Status code:', xhr.status);
                console.error('   Status text:', xhr.statusText);
                
                // Try to parse JSON error response
                 var errorMsg = 'An unknown error occurred.';
                try {
                    var json = $.parseJSON(xhr.responseText);
                    console.log('   Parsed JSON response:', json);
                    if(json.message) {
                        errorMsg = json.message;
                    }
                } catch(e) {
                    console.error('   Failed to parse JSON response:', e);
                }
                
                console.error('   Final error message:', errorMsg);
                alert('Request Failed: ' + errorMsg);
            }
        });
    });
});
</script>
</body>
</html> 