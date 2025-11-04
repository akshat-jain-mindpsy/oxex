<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);

// Ensure proper access control
if(!(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'DV'))) {
    header("Location: index.php");
    exit();
}

$pagetitle = "Pass Standard Details";

// Set variables needed by adminjs.php
$whichDocModal = 3; // Tables section
$value0 = 0; // Default value for sort_order
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
    if ($usingSupabase) {
        $stmt = $supabase_pdo->prepare("SELECT * FROM pass_standards WHERE psid = ?");
        $stmt->execute([$psid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $standard = $row;
            // If the main stid is null, it's an OR condition, so fetch the fields
            if (is_null($standard['stid'])) {
                $or_stmt = $supabase_pdo->prepare("SELECT stid FROM pass_standard_fields WHERE standard_id = ?");
                $or_stmt->execute([$psid]);
                $or_data = $or_stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($or_data as $row) {
                    $selected_or_fields[] = $row['stid'];
                }
            }
        }
    }
} else {
    $subtitle = "Add New Pass Standard";
}

// Fetch all tables for the dropdown
if ($usingSupabase) {
    $tables_stmt = $supabase_pdo->prepare("SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name ASC");
    $tables_stmt->execute();
    $tables_result = $tables_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $tables_result = [];
}

// Fetch all possible parent standards
if ($usingSupabase) {
    $parents_query = "SELECT psid, standard_name, tbid FROM pass_standards";
    if ($is_editing) {
        // A standard cannot be its own parent
        $parents_query .= " WHERE psid != ?";
        $parents_stmt = $supabase_pdo->prepare($parents_query);
        $parents_stmt->execute([$psid]);
    } else {
        $parents_stmt = $supabase_pdo->prepare($parents_query);
        $parents_stmt->execute();
    }
    $all_parents = $parents_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $all_parents = [];
}

// Fetch all categories for the dropdown
if ($usingSupabase) {
    $fields_stmt = $supabase_pdo->prepare("SELECT stid, str FROM select_types ORDER BY str ASC");
    $fields_stmt->execute();
    $fields_result = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $fields_result = [];
}

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
        
        .or-alts-wrapper {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f3f4;
            background: #fafbfc;
        }
        
        .or-alts-wrapper .add-or-alt {
            margin-bottom: 10px;
        }
        
        .or-alts-wrapper .or-alt {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 80px;
            gap: 15px;
            padding: 10px 0;
            align-items: center;
        }
        
        .or-alts-wrapper .or-alt:first-of-type {
            padding-top: 0;
        }
        
        @media (max-width: 768px) {
            .or-alts-wrapper .or-alt {
                grid-template-columns: 1fr;
                gap: 10px;
            }
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
                <div class="card-header"><?php echo $is_editing ? 'Edit Pass Standard' : 'Create New Pass Standard'; ?></div>
                <div class="card-body">
                    <?php if ($is_editing): ?>
                    <h5 class="mb-3">Edit Standard: <?php echo htmlspecialchars($standard['standard_name']); ?></h5>
                    <?php endif; ?>
                    <form id="standardForm" novalidate>
                                <input type="hidden" name="psid" value="<?php echo $psid; ?>">

                                <fieldset class="mb-3">
                                    <legend class="h6 mb-3">Standard Details</legend>
                                    <div class="form-group">
                                        <label for="standard_name">Standard Name*</label>
                                        <input type="text" class="form-control" id="standard_name" name="standard_name" value="<?php echo htmlspecialchars($standard['standard_name']); ?>" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label for="tbid">Applies to Sheet*</label>
                                            <select class="form-control" id="tbid" name="tbid" required>
                                                <option value="">-- Select a Sheet --</option>
                                                <?php foreach($tables_result as $row): ?>
                                                    <option value="<?php echo $row['tbid']; ?>" <?php echo ($standard['tbid'] == $row['tbid']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['tab_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label for="requirement_type">Requirement Type</label>
                                            <select class="form-control" id="requirement_type" name="requirement_type">
                                                <option value="TOTAL_HOURS" <?php echo ($standard['requirement_type'] == 'TOTAL_HOURS') ? 'selected' : ''; ?>>Total Hours (Single Source)</option>
                                                <option value="TOTAL_HOURS_COMBINED" <?php echo ($standard['requirement_type'] == 'TOTAL_HOURS_COMBINED') ? 'selected' : ''; ?>>Combined Hours (Multiple Sources)</option>
                                                <option value="UNIQUE_VALUES" <?php echo ($standard['requirement_type'] == 'UNIQUE_VALUES') ? 'selected' : ''; ?>>Unique Values</option>
                                                <option value="TOTAL_COUNT" <?php echo ($standard['requirement_type'] == 'TOTAL_COUNT') ? 'selected' : ''; ?>>Total Count</option>
                                                <option value="UNIQUE_VALUES_IN_RANGE" <?php echo ($standard['requirement_type'] == 'UNIQUE_VALUES_IN_RANGE') ? 'selected' : ''; ?>>Unique Values in Range</option>
                                                <option value="PER_CASE_MINIMUM" <?php echo ($standard['requirement_type'] == 'PER_CASE_MINIMUM') ? 'selected' : ''; ?>>Per-Case Minimum</option>
                                            </select>
                                            <small class="form-text text-muted" id="requirementTypeHelp">Select how hours should be calculated</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label for="required_value">Required Value*</label>
                                            <input type="number" class="form-control" id="required_value" name="required_value" value="<?php echo (int)$standard['required_value']; ?>" required min="0">
                                            <small class="form-text text-muted" id="requiredValueHelp">Number of cases/items required</small>
                                        </div>
                                        <div class="col-md-6 form-group" id="minimumThresholdContainer" style="display:none;">
                                            <label for="minimum_threshold">Minimum Threshold per Case*</label>
                                            <input type="number" class="form-control" id="minimum_threshold" name="minimum_threshold" value="<?php echo (int)($standard['minimum_threshold'] ?? 0); ?>" min="0" step="0.1" required>
                                            <small class="form-text text-muted">Minimum value each case must meet (e.g., 5 hours)</small>
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
                                </fieldset>

                                <fieldset class="mb-3" id="fieldDependentSection">
                                    <legend class="h6 mb-3">Category Logic</legend>
                                    <div class="form-group">
                                        <label>How should the categories be checked?</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="field_logic_mode" id="logicSingle" value="single" <?php echo (is_null($standard['stid']) && !empty($selected_or_fields)) ? '' : 'checked'; ?>>
                                            <label class="form-check-label" for="logicSingle">On a Single Category</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="field_logic_mode" id="logicMultiple" value="multiple" <?php echo (is_null($standard['stid']) && !empty($selected_or_fields)) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="logicMultiple">On Multiple Categories (OR condition)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="field_logic_mode" id="logicCategoryGroups" value="category_groups" <?php echo (isset($standard['field_value']) && strpos($standard['field_value'], 'category_groups') !== false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="logicCategoryGroups">Multiple Category Groups (AND condition)</label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group" id="categoryGroupsInfo" style="display:none;">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> <strong>Multiple Category Groups:</strong> Define separate categories, each with their own requirements. All groups must pass (AND condition).
                                        </div>
                                    </div>

                                    <div class="form-group" id="singleFieldContainer">
                                        <label for="stid">Category to Check</label>
                                        <select class="form-control" id="stid" name="stid">
                                            <option value="">-- Select a Table First --</option>
                                        </select>
                                        <small class="form-text text-muted">Required for 'Unique Values' and 'Total Count'. Ignored for 'Total Hours'.</small>
                                    </div>

                                    <div class="form-group" id="multipleFieldContainer" style="display:none;">
                                        <label for="stids">Categories to Check (OR condition)</label>
                                        <select class="form-control" id="stids" name="stids[]" multiple>
                                            <!-- Options loaded by JS -->
                                        </select>
                                        <small class="form-text text-muted">The rule will pass if the condition is met in ANY of the selected categories.</small>
                                    </div>

                                    <div class="form-group" id="mainFieldValueContainer">
                                        <label for="field_value">Category Value Filter (Optional)</label>
                                        <input type="text" class="form-control" id="field_value" name="field_value" value="<?php echo htmlspecialchars($standard['field_value'] ?? ''); ?>">
                                        <small class="form-text text-muted">Filter the main category before applying subcategory rules. For ranges, use a hyphen (e.g., 18-64). Leave empty to check all category values.</small>
                                    </div>
                                </fieldset>

                                <fieldset class="mb-3" id="combinedHoursSources" style="display:none;">
                                    <legend class="h6 mb-3">Sources to Combine</legend>
                                    <div class="alert alert-info">
                                        <strong>Info:</strong> Define multiple sources that will be summed together. Each source filters by a category and its specific value. You can set a maximum value cap (e.g., 40 hours) for any source - hours exceeding that cap will be limited.
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-borderless" id="hourSourcesTable">
                                            <thead>
                                                <tr>
                                                    <th style="width: 30%;">Category Filter</th>
                                                    <th style="width: 30%;">Category Value</th>
                                                    <th style="width: 20%;">Max Value (Optional)</th>
                                                    <th style="width: 20%;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="hourSourcesContainer">
                                                <tr class="hour-source-row">
                                                    <td>
                                                        <select class="form-control form-control-sm source-category-select" name="hour_sources[0][category_stid]" required>
                                                            <option value="">-- Select Category --</option>
                                                        </select>
                                                        <small class="text-muted">Required</small>
                                                    </td>
                                                    <td>
                                                        <select class="form-control form-control-sm source-value-select" name="hour_sources[0][category_value]" disabled>
                                                            <option value="">-- No filter (all values) --</option>
                                                        </select>
                                                        <small class="text-muted">Optional - leave blank for all values</small>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm source-max-value" name="hour_sources[0][max_value]" placeholder="e.g., 40" min="0" step="0.1">
                                                        <small class="text-muted">Cap hours at this value (e.g., 40 max)</small>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-danger remove-source-btn">Remove</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" id="addHourSourceBtn" class="btn btn-success btn-sm mt-2">
                                        <i class="fa fa-plus"></i> Add Source
                                    </button>
                                </fieldset>

                                <fieldset class="mb-3" id="singleSubfieldContainer" style="display:none;">
                                    <legend class="h6 mb-3">Subcategory Rules</legend>
                                    <div class="alert alert-info">
                                        <strong>Info:</strong> Define specific requirements for subcategories within the selected category. You can add multiple rules and create OR conditions (alternatives).
                                    </div>
                                    <div class="subfield-rules-table">
                                        <div class="subfield-rules-header">
                                            <div class="rule-col">Subcategory Value</div>
                                            <div class="rule-col">Requirement Type</div>
                                            <div class="rule-col">Rule Value</div>
                                            <div class="rule-col-actions">Actions</div>
                                        </div>
                                        <div id="subfieldRulesContainer">
                                            <!-- Subcategory rules will be added here for single category mode -->
                                        </div>
                                    </div>
                                    <button type="button" id="addSubfieldRuleBtn" class="btn btn-success btn-sm mt-2" style="display:none;">
                                        <i class="fa fa-plus"></i> Add Subcategory Rule
                                    </button>
                                </fieldset>

                                <fieldset class="mb-3" id="subfieldContainer" style="display:none;">
                                    <legend class="h6 mb-3">Category Groups & Subcategory Rules</legend>
                                    <div class="alert alert-info">
                                        <strong>Info:</strong> Define multiple category groups. Each group can have its own category and subcategory rules. All groups must pass (AND condition).
                                    </div>
                                    
                                    <div id="categoryGroupsContainer">
                                        <!-- Category groups will be added here -->
                                    </div>
                                    
                                    <button type="button" id="addCategoryGroupBtn" class="btn btn-success btn-sm mt-3" style="display:none;">
                                        <i class="fa fa-plus"></i> Add Another Category Group
                                    </button>
                                </fieldset>


                                <fieldset class="mb-3">
                                    <legend class="h6 mb-3">Status</legend>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($standard['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Standard is Active</label>
                                    </div>
                                </fieldset>


                                <div class="d-flex align-items-center">
                                    <button type="submit" class="btn btn-primary mr-2">Save Standard</button>
                                    <a href="<?php echo $listurl; ?>" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
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
    window.existingSubfieldRules = [];
    <?php if ($is_editing && !empty($standard['field_value']) && strpos($standard['field_value'], 'SUBFIELD_RULES:') !== false): ?>
    try {
        var fieldValueData = <?php echo json_encode($standard['field_value']); ?>;
        console.log('🔍 Raw field_value data:', fieldValueData);
        console.log('🔍 Contains SUBFIELD_RULES?:', fieldValueData.includes('SUBFIELD_RULES:'));
        
        if (fieldValueData.includes('SUBFIELD_RULES:')) {
            // Extract JSON part after SUBFIELD_RULES:
            var jsonPart = fieldValueData.replace(/^SUBFIELD_RULES:/, '').trim();
            console.log('🔍 JSON part extracted:', jsonPart);
            
            try {
                var rules = JSON.parse(jsonPart);
                console.log('🔍 Parsed rules:', rules);
                
                if (Array.isArray(rules)) {
                    // Preserve structure including any_of groups
                    window.existingSubfieldRules = rules;
                    console.log('📋 Loaded existing subfield rules:', window.existingSubfieldRules);
                    console.log('📋 Number of rules:', window.existingSubfieldRules.length);
                } else {
                    console.warn('⚠️ Rules is not an array:', rules);
                }
            } catch (parseError) {
                console.error('❌ Error parsing JSON:', parseError);
                console.error('   JSON part was:', jsonPart);
            }
        } else {
            console.log('⚠️ No SUBFIELD_RULES found in field_value');
        }
    } catch (e) {
        console.error('❌ Error parsing existing subfield rules:', e);
        console.error('   Error details:', e.message);
        console.error('   Stack trace:', e.stack);
        window.existingSubfieldRules = [];
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
            
            // Store fields globally for use in hour sources and category filters
            window.availableFields = fields;
            
            $stidSelect.html('<option value="">-- Select a Field --</option>');
            $stidsMultiSelect.html(''); // Clear existing

            $.each(fields, function(index, field) {
                var option = new Option(field.str, field.stid, false, false);
                $stidSelect.append(option);
                // Also create an option for the multi-select
                var multiOption = new Option(field.str, field.stid, false, false);
                $stidsMultiSelect.append(multiOption);
            });
            
            // Update category filters
            $('#categoryFiltersContainer select').each(function() {
                var $select = $(this);
                var currentValue = $select.val();
                
                $select.find('option').not(':first').remove();
                
                $.each(fields, function(index, field) {
                    $select.append(new Option(field.str, field.stid, false, false));
                });
                
                if (currentValue) {
                    $select.val(currentValue);
                }
            });
            
            // Update hour sources with current fields
            updateHourSourceCategories();
            
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
        var $categoryGroupsContainer = $('#categoryGroupsContainer');
        var $mainFieldValueContainer = $('#mainFieldValueContainer');
        
        if (!fieldId) {
            console.log('   No fieldId provided, hiding subfield container');
            $subfieldContainer.hide();
            $mainFieldValueContainer.show();
            return;
        }

        console.log('   Making AJAX call to get_subfields_for_field.php');
        
        // Get the actual subcategory values from select_gen table
        $.getJSON('ajax/get_subfields_for_field.php', { stid: fieldId }, function(subfields) {
            console.log('   AJAX response received:', subfields);
            
            // Store subcategories globally
            window.availableSubfields = subfields;
            
            // Show category groups container
            $subfieldContainer.show();
            $mainFieldValueContainer.hide();
            
            // Create first category group if none exist
            if ($categoryGroupsContainer.find('.category-group').length === 0) {
                console.log('   Creating first category group');
                addCategoryGroup(0);
                $('#addCategoryGroupBtn').show();
                
                // Store subcategories in first group
                var $firstGroup = $categoryGroupsContainer.find('.category-group:first');
                $firstGroup.data('subcategories', subfields);
            }
            
            if (callback) {
                callback();
            }
        }).fail(function(xhr, status, error) {
            console.error('   AJAX call failed:', {xhr: xhr, status: status, error: error});
            $subfieldContainer.hide();
            $mainFieldValueContainer.show();
        });
    }

    function addSubfieldRuleToGroup(groupIndex) {
        console.log('➕ addSubfieldRuleToGroup() called for group:', groupIndex);
        
        var $group = $(`.category-group[data-group="${groupIndex}"]`);
        var $rulesContainer = $group.find('.subfield-rules-group');
        var ruleIndex = $rulesContainer.find('.subfield-rule-row').length;
        
        console.log(`   Creating rule ${ruleIndex + 1} for group ${groupIndex}`);
        
        var subfieldOptions = '';
        var groupSubcategories = $group.data('subcategories');
        if (groupSubcategories && groupSubcategories.length > 0) {
            groupSubcategories.forEach(function(subfield) {
                subfieldOptions += `<option value="${subfield.pid}">${subfield.str}</option>`;
            });
        }
        
        var ruleHtml = `
            <div class="subfield-rule-row" data-rule="${ruleIndex}">
                <div class="rule-col">
                    <select class="form-control subfield-select" name="category_groups[${groupIndex}][subfield_rules][${ruleIndex}][subfield_values]">
                        <option value="">-- Select One Subcategory Value --</option>
                        ${subfieldOptions}
                    </select>
                    <small class="form-text text-muted">Choose one subcategory value for this rule</small>
                </div>
                <div class="rule-col">
                    <select class="form-control" name="category_groups[${groupIndex}][subfield_rules][${ruleIndex}][requirement_type]" required>
                        <option value="">-- Select Rule Type --</option>
                        <option value="TOTAL_COUNT">Total Count</option>
                        <option value="UNIQUE_VALUES">Unique Values</option>
                        <option value="UNIQUE_VALUES_IN_RANGE">Unique Values in Range</option>
                        <option value="PER_CASE_MINIMUM">Per-Case Minimum</option>
                    </select>
                    <small class="form-text text-muted">What to count for this rule</small>
                </div>
                <div class="rule-col">
                    <input type="text" class="form-control" name="category_groups[${groupIndex}][subfield_rules][${ruleIndex}][specific_value]" placeholder="e.g., 100 or 18-64" required>
                    <input type="number" class="form-control mt-1" name="category_groups[${groupIndex}][subfield_rules][${ruleIndex}][minimum_threshold]" placeholder="Min per case (e.g., 5)" min="0" step="0.1" style="display:none;">
                    <small class="form-text text-muted">The value this rule must meet</small>
                </div>
                <div class="rule-col-actions">
                    <button type="button" class="remove-rule-btn" onclick="removeSubfieldRule(${ruleIndex}, ${groupIndex})">×</button>
                </div>
            </div>
            <div class="or-alts-wrapper" data-rule="${ruleIndex}" data-group="${groupIndex}">
                <button type="button" class="btn btn-outline-secondary btn-sm add-or-alt" data-rule="${ruleIndex}" data-group="${groupIndex}">Add OR alternative</button>
                <div class="or-alts" data-rule="${ruleIndex}" data-group="${groupIndex}"></div>
            </div>
            `;
        
        console.log('   Appending new row to group container');
        $rulesContainer.append(ruleHtml);
        
        // Initialize Select2 for the new subfield select
        var $newSelect = $rulesContainer.find('.subfield-rule-row[data-rule="' + ruleIndex + '"] .subfield-select');
        console.log('   New select element:', $newSelect[0]);
        
        console.log('   Initializing Select2');
        $newSelect.select2({
            placeholder: '-- Select One Subcategory Value --',
            width: '100%',
            allowClear: true
        });
        
        // Add change event to update available options in other rules
        console.log('   Adding change event listener');
        $newSelect.on('change', function() {
            console.log('   Select2 change event triggered');
            // Simple approach: just rebuild all options
            window.rebuildAllSubfieldOptions();
        });
        
        // Add change event for requirement type to show/hide minimum threshold
        $newSelect.closest('.subfield-rule-row').find('select[name*="[requirement_type]"]').on('change', function() {
            var $row = $(this).closest('.subfield-rule-row');
            var $minThreshold = $row.find('input[name*="[minimum_threshold]"]');
            if ($(this).val() === 'PER_CASE_MINIMUM') {
                $minThreshold.show().prop('required', true).prop('disabled', false);
            } else {
                $minThreshold.hide().prop('required', false).prop('disabled', true);
            }
        });
        
        console.log('✅ addSubfieldRuleToGroup() completed');
    }
    
    function addSubfieldRuleWithData(ruleData, ruleIndex, groupIndex) {
        console.log('➕ addSubfieldRuleWithData() called with data:', ruleData, 'index:', ruleIndex);
        
        var ruleHtml = `
            <div class="subfield-rule-row" data-rule="${ruleIndex}">
                <div class="rule-col">
                    <select class="form-control subfield-select" name="subfield_rules[${ruleIndex}][subfield_values]">
                        <option value="">-- Select One Subcategory Value --</option>
                        ${getAvailableSubfieldOptions(true)}
                    </select>
                    <small class="form-text text-muted">Choose one subcategory value for this rule</small>
                </div>
                <div class="rule-col">
                    <select class="form-control" name="subfield_rules[${ruleIndex}][requirement_type]" required>
                        <option value="">-- Select Rule Type --</option>
                        <option value="TOTAL_COUNT">Total Count</option>
                        <option value="UNIQUE_VALUES">Unique Values</option>
                        <option value="UNIQUE_VALUES_IN_RANGE">Unique Values in Range</option>
                        <option value="PER_CASE_MINIMUM">Per-Case Minimum</option>
                    </select>
                    <small class="form-text text-muted">What to count for this rule</small>
                </div>
                <div class="rule-col">
                    <input type="text" class="form-control" name="subfield_rules[${ruleIndex}][specific_value]" placeholder="e.g., 100 or 18-64" required>
                    <input type="number" class="form-control mt-1" name="subfield_rules[${ruleIndex}][minimum_threshold]" placeholder="Min per case (e.g., 5)" min="0" step="0.1" style="display:none;">
                    <small class="form-text text-muted">The value this rule must meet</small>
                </div>
                <div class="rule-col-actions">
                    <button type="button" class="remove-rule-btn" onclick="removeSubfieldRule(${ruleIndex}, null)">×</button>
                </div>
            </div>
            <div class="or-alts-wrapper" data-rule="${ruleIndex}">
                <button type="button" class="btn btn-outline-secondary btn-sm add-or-alt" data-rule="${ruleIndex}">Add OR alternative</button>
                <div class="or-alts" data-rule="${ruleIndex}"></div>
            </div>
        `;
        
        console.log('   Appending new row to container');
        $('#subfieldRulesContainer').append(ruleHtml);
        
        // Initialize Select2 for the new subfield select
        var $newSelect = $('#subfieldRulesContainer').find('.subfield-rule-row[data-rule="' + ruleIndex + '"] .subfield-select');
        console.log('   New select element:', $newSelect[0]);
        console.log('   Options in select before Select2:', $newSelect.find('option').length);
        console.log('   Option values:', $newSelect.find('option').map(function() { return $(this).val(); }).get());
        
        console.log('   Initializing Select2');
        $newSelect.select2({
            placeholder: '-- Select One Subcategory Value --',
            width: '100%',
            allowClear: true
        });
        
        console.log('   Options in select after Select2:', $newSelect.find('option').length);
        
        // Add change event to update available options in other rules
        console.log('   Adding change event listener');
        $newSelect.on('change', function() {
            console.log('   Select2 change event triggered');
            // Simple approach: just rebuild all options
            window.rebuildAllSubfieldOptions();
        });
        
        // Add change event for requirement type to show/hide minimum threshold
        $newSelect.closest('.subfield-rule-row').find('select[name*="[requirement_type]"]').on('change', function() {
            var $row = $(this).closest('.subfield-rule-row');
            var $minThreshold = $row.find('input[name*="[minimum_threshold]"]');
            if ($(this).val() === 'PER_CASE_MINIMUM') {
                $minThreshold.show().prop('required', true).prop('disabled', false);
            } else {
                $minThreshold.hide().prop('required', false).prop('disabled', true);
            }
        });
        
        // Populate the form fields with existing data (if provided)
        console.log('   Populating form fields with existing data');
        if (!ruleData) {
            console.log('   No rule data provided, creating empty rule');
            return; // Exit early if no data - just created empty form
        }
        
        // Wait a moment for Select2 to be fully initialized before setting values
        setTimeout(function() {
            if (ruleData.any_of && Array.isArray(ruleData.any_of)) {
                // Use first as base, others as OR alts
                var base = ruleData.any_of[0];
                console.log('   Setting OR group base rule value:', base.subfield_value);
                
                // Ensure the option exists before setting
                if (base.subfield_value) {
                    var optionExists = $newSelect.find('option[value="' + base.subfield_value + '"]').length > 0;
                    if (!optionExists && window.availableSubfields) {
                        // Add the missing option
                        var missingSub = window.availableSubfields.find(function(s) { return s.pid == base.subfield_value; });
                        if (missingSub) {
                            var newOption = new Option(missingSub.str, missingSub.pid, false, false);
                            $newSelect.append(newOption);
                        }
                    }
                }
                
                $newSelect.val(base.subfield_value);
                // Trigger change for Select2
                if ($newSelect.data('select2')) {
                    $newSelect.trigger('change.select2');
                } else {
                    $newSelect.trigger('change');
                }
                $newSelect.closest('.subfield-rule-row').find('select[name*="[requirement_type]"]').val(base.requirement_type).trigger('change');
                $newSelect.closest('.subfield-rule-row').find('input[name*="[specific_value]"]').val(base.specific_value);
                if (base.requirement_type === 'PER_CASE_MINIMUM' && base.minimum_threshold) {
                    var $minThreshold = $newSelect.closest('.subfield-rule-row').find('input[name*="[minimum_threshold]"]');
                    $minThreshold.show().prop('required', true).prop('disabled', false).val(base.minimum_threshold);
                }
                // Add OR alts
                for (var i = 1; i < ruleData.any_of.length; i++) {
                    console.log('   Adding OR alternative', i);
                    window.addOrAlternative(ruleIndex, ruleData.any_of[i]);
                }
            } else if (ruleData.subfield_value) {
                // Single rule (not OR group) - set the value
                console.log('   Setting single rule value:', ruleData.subfield_value);
                
                // Ensure the option exists before setting
                if (ruleData.subfield_value) {
                    var optionExists = $newSelect.find('option[value="' + ruleData.subfield_value + '"]').length > 0;
                    if (!optionExists && window.availableSubfields) {
                        // Add the missing option
                        var missingSub = window.availableSubfields.find(function(s) { return s.pid == ruleData.subfield_value; });
                        if (missingSub) {
                            var newOption = new Option(missingSub.str, missingSub.pid, false, false);
                            $newSelect.append(newOption);
                        }
                    }
                }
                
                $newSelect.val(ruleData.subfield_value);
                // Trigger change for Select2
                if ($newSelect.data('select2')) {
                    $newSelect.trigger('change.select2');
                } else {
                    $newSelect.trigger('change');
                }
                $newSelect.closest('.subfield-rule-row').find('select[name*="[requirement_type]"]').val(ruleData.requirement_type).trigger('change');
                $newSelect.closest('.subfield-rule-row').find('input[name*="[specific_value]"]').val(ruleData.specific_value);
                
                // Handle minimum threshold for existing data
                if (ruleData.requirement_type === 'PER_CASE_MINIMUM') {
                    var $minThreshold = $newSelect.closest('.subfield-rule-row').find('input[name*="[minimum_threshold]"]');
                    $minThreshold.show().prop('required', true).prop('disabled', false).val(ruleData.minimum_threshold || '');
                }
            }
        }, 100);
        
        console.log('✅ addSubfieldRuleWithData() completed');
    }

    function getAvailableSubfieldOptions(includeAll) {
        console.log('📋 getAvailableSubfieldOptions() called, includeAll:', includeAll);
        
        if (!window.availableSubfields || window.availableSubfields.length === 0) {
            console.log('   No available subfields, returning empty string');
            return '';
        }
        
        console.log('   Available subfields:', window.availableSubfields.length);
        
        // If includeAll is true, return all options (useful when creating new rows or loading existing data)
        if (includeAll) {
            var result = window.availableSubfields.map(function(subfield) {
                return `<option value="${subfield.pid}">${subfield.str}</option>`;
            }).join('');
            console.log('   Generated HTML options (all):', result.length, 'chars');
            return result;
        }
        
        // Otherwise, filter out used values
        var usedValues = window.getUsedSubfieldValues();
        console.log('   Used values:', usedValues);
        
        var availableOptions = window.availableSubfields.filter(function(subfield) {
            var isAvailable = !usedValues.includes(String(subfield.pid));
            return isAvailable;
        });
        
        console.log('   Filtered available options:', availableOptions.length);
        
        var result = availableOptions.map(function(subfield) {
            return `<option value="${subfield.pid}">${subfield.str}</option>`;
        }).join('');
        
        console.log('   Generated HTML options:', result.length, 'chars');
        return result;
    }

    // Move getUsedSubfieldValues to global scope
    window.getUsedSubfieldValues = function() {
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
    };


    
    // Move rebuildAllSubfieldOptions to global scope - updated for category groups
    window.rebuildAllSubfieldOptions = function() {
        console.log('🔄 rebuildAllSubfieldOptions() called');
        console.log('📊 Current rule rows:', $('.subfield-rule-row').length);
        
        try {
            var usedValues = window.getUsedSubfieldValues();
            console.log('🚫 Used values:', usedValues);
            
            $('.subfield-rule-row').each(function(index) {
                console.log(`📝 Processing rule row ${index + 1}`);
                
                var $select = $(this).find('.subfield-select');
                var currentValue = $select.val();
                
                console.log(`   Select element:`, $select[0]);
                console.log(`   Current value:`, currentValue);
                
                // Get the category group this rule belongs to
                var $group = $select.closest('.category-group');
                if (!$group.length) {
                    console.log('   Rule not in category group, using global subfields');
                    var subfields = window.availableSubfields;
                } else {
                    var subfields = $group.data('subcategories');
                    console.log('📋 Available subfields for this group:', subfields);
                }
                
                // Clear current options
                var optionsBefore = $select.find('option').length;
                $select.find('option:not(:first)').remove();
                var optionsAfter = $select.find('option').length;
                console.log(`   Cleared options: ${optionsBefore} → ${optionsAfter}`);
                
                // Add available options
                if (subfields && Array.isArray(subfields)) {
                    var addedOptions = 0;
                    subfields.forEach(function(subfield) {
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
    };

    // Add OR alternative row for a given rule
    window.addOrAlternative = function(ruleIndex, data) {
        var $container = $(".or-alts[data-rule='" + ruleIndex + "']");
        // Reserve index 0 for the base rule we inject at submit-time
        var altIdx = $container.find('.or-alt').length + 1;
        
        // Check if this is category group mode or single mode
        var $wrapper = $container.closest('.or-alts-wrapper');
        var groupIndex = $wrapper.data('group');
        var namePrefix = groupIndex !== undefined 
            ? `category_groups[${groupIndex}][subfield_rules][${ruleIndex}][any_of][${altIdx}]`
            : `subfield_rules[${ruleIndex}][any_of][${altIdx}]`;
        
        var html = `
            <div class="or-alt" data-alt="${altIdx}">
                <div class="rule-col">
                    <span class="mx-1" style="font-weight: 600; color: #495057;">OR</span>
                    <select class="form-control subfield-select" name="${namePrefix}[subfield_value]">
                        <option value="">-- Subcategory --</option>
                        ${getAvailableSubfieldOptions(true)}
                    </select>
                </div>
                <div class="rule-col">
                    <select class="form-control" name="${namePrefix}[requirement_type]">
                        <option value="TOTAL_COUNT">Total Count</option>
                        <option value="UNIQUE_VALUES">Unique Values</option>
                        <option value="UNIQUE_VALUES_IN_RANGE">Unique Values in Range</option>
                        <option value="PER_CASE_MINIMUM">Per-Case Minimum</option>
                    </select>
                </div>
                <div class="rule-col">
                    <input type="text" class="form-control" name="${namePrefix}[specific_value]" placeholder="Value">
                    <input type="number" class="form-control mt-1" name="${namePrefix}[minimum_threshold]" placeholder="Min/case" step="0.1" style="display:none;">
                </div>
                <div class="rule-col-actions">
                    <button type="button" class="btn btn-link text-danger remove-or-alt" style="padding: 0; font-size: 14px;">remove</button>
                </div>
            </div>
        `;
        $container.append(html);
        var $row = $container.children().last();
        var $sel = $row.find('select.subfield-select');
        $sel.select2({ placeholder: '-- Subcategory --', width: '100%', allowClear: true });
        if (data) {
            $sel.val(data.subfield_value).trigger('change');
            $row.find('select[name*="[requirement_type]"]').val(data.requirement_type);
            $row.find('input[name*="[specific_value]"]').val(data.specific_value);
            if (data.requirement_type === 'PER_CASE_MINIMUM') {
                var $mt = $row.find('input[name*="[minimum_threshold]"]');
                $mt.show().prop('required', true).prop('disabled', false).val(data.minimum_threshold || '');
            }
        }
        // toggle min threshold on change
        $row.find('select[name*="[requirement_type]"]').on('change', function() {
            var $mt = $row.find('input[name*="[minimum_threshold]"]');
            if ($(this).val() === 'PER_CASE_MINIMUM') {
                $mt.show().prop('required', true).prop('disabled', false);
            } else {
                $mt.hide().prop('required', false).prop('disabled', true).val('');
            }
        });
    };

    // Event: add OR alternative
    $(document).on('click', '.add-or-alt', function() {
        var ruleIndex = $(this).data('rule');
        window.addOrAlternative(ruleIndex);
    });

    // Event: remove OR alternative
    $(document).on('click', '.remove-or-alt', function() {
        $(this).closest('.or-alt').remove();
    });

    // Move removeSubfieldRule to global scope so it can be called from onclick
    window.removeSubfieldRule = function(ruleIndex, groupIndex) {
        console.log('🗑️ removeSubfieldRule() called with index:', ruleIndex, 'group:', groupIndex);
        
        var $ruleToRemove, $rulesContainer;
        
        if (groupIndex !== undefined && groupIndex !== null) {
            // Category groups mode
            var $group = $(`.category-group[data-group="${groupIndex}"]`);
            $rulesContainer = $group.find('.subfield-rules-group');
            $ruleToRemove = $rulesContainer.find('.subfield-rule-row[data-rule="' + ruleIndex + '"]');
        } else {
            // Single category mode
            $rulesContainer = $('#subfieldRulesContainer');
            $ruleToRemove = $rulesContainer.find('.subfield-rule-row[data-rule="' + ruleIndex + '"]');
        }
        
        console.log('   Rule to remove:', $ruleToRemove[0]);
        
        if ($ruleToRemove.length === 0) {
            console.warn('   Rule not found');
            return;
        }
        
        // Also remove the associated OR alternatives wrapper
        var $orWrapper = $ruleToRemove.next('.or-alts-wrapper');
        if ($orWrapper.length) {
            $orWrapper.remove();
            console.log('   OR alternatives wrapper removed');
        }
        
        $ruleToRemove.remove();
        console.log('   Rule removed from DOM');
        
        // Reindex remaining rules
        if (groupIndex !== undefined && groupIndex !== null) {
            // Category groups mode
            console.log('   Reindexing remaining rules in group', groupIndex);
            $rulesContainer.find('.subfield-rule-row').each(function(index) {
                var $row = $(this);
                $row.attr('data-rule', index);
                $row.find('select, input, button').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[subfield_rules\]\[\d+\]/, '[subfield_rules][' + index + ']'));
                    }
                    var onclick = $(this).attr('onclick');
                    if (onclick) {
                        $(this).attr('onclick', onclick.replace(/removeSubfieldRule\(\d+,\s*\d+\)/, 'removeSubfieldRule(' + index + ',' + groupIndex + ')'));
                    }
                });
                // Update OR wrapper
                var $orWrapper = $row.next('.or-alts-wrapper');
                if ($orWrapper.length) {
                    $orWrapper.attr('data-rule', index);
                    $orWrapper.find('.add-or-alt').attr('data-rule', index);
                    $orWrapper.find('.or-alts').attr('data-rule', index);
                    // Update name attributes in OR alternatives
                    $orWrapper.find('select, input').each(function() {
                        var name = $(this).attr('name');
                        if (name) {
                            var newName = name.replace(/category_groups\[\d+\]\[subfield_rules\]\[\d+\]/, 'category_groups[' + groupIndex + '][subfield_rules][' + index + ']');
                            $(this).attr('name', newName);
                        }
                    });
                }
            });
        } else {
            // Single category mode
            console.log('   Reindexing remaining rules in single mode');
            $rulesContainer.find('.subfield-rule-row').each(function(index) {
                var $row = $(this);
                $row.attr('data-rule', index);
                $row.find('select, input, button').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[subfield_rules\]\[\d+\]/, '[subfield_rules][' + index + ']'));
                    }
                    var onclick = $(this).attr('onclick');
                    if (onclick) {
                        $(this).attr('onclick', onclick.replace(/removeSubfieldRule\(\d+\)/, 'removeSubfieldRule(' + index + ')'));
                    }
                });
                // Update OR wrapper
                var $orWrapper = $row.next('.or-alts-wrapper');
                if ($orWrapper.length) {
                    $orWrapper.attr('data-rule', index);
                    $orWrapper.find('.add-or-alt').attr('data-rule', index);
                    $orWrapper.find('.or-alts').attr('data-rule', index);
                    // Update name attributes in OR alternatives
                    $orWrapper.find('select, input').each(function() {
                        var name = $(this).attr('name');
                        if (name) {
                            var newName = name.replace(/\[subfield_rules\]\[\d+\]/, '[subfield_rules][' + index + ']');
                            $(this).attr('name', newName);
                        }
                    });
                }
            });
        }
        
        console.log('✅ removeSubfieldRule() completed');
    };
    
    function toggleFieldDependent(type) {
        var helpText = "If set, only entries matching this value will be counted. For OR conditions, separate values with a pipe (|).";
        var requiredValueHelp = "Number of cases/items required";
        
        // Show/hide field dependent section
        if (type === 'TOTAL_HOURS' || type === 'TOTAL_HOURS_COMBINED') {
            $('#fieldDependentSection').slideUp();
        } else {
            $('#fieldDependentSection').slideDown();
            if (type === 'UNIQUE_VALUES_IN_RANGE') {
                helpText = "Define the numeric range to check (e.g., 18-64).";
            }
        }
        
        // Show/hide combined hours sources section
        if (type === 'TOTAL_HOURS_COMBINED') {
            $('#combinedHoursSources').slideDown();
            $('#requiredValueHelp').text("Total value required when all sources are summed");
        } else {
            $('#combinedHoursSources').slideUp();
        }
        
        // Handle per-case minimum requirements
        if (type === 'PER_CASE_MINIMUM') {
            $('#minimumThresholdContainer').show();
            $('#minimum_threshold').prop('required', true).prop('disabled', false);
            requiredValueHelp = "Number of cases that must each meet the minimum threshold";
            helpText = "Each case must meet the minimum threshold value. Use field value to filter which cases to check.";
        } else {
            $('#minimumThresholdContainer').hide();
            $('#minimum_threshold').prop('required', false).prop('disabled', true);
        }
        
        $('#fieldValueHelp').text(helpText);
        $('#requiredValueHelp').text(requiredValueHelp);
    }
    
    function toggleFieldLogicMode() {
        if ($('#logicSingle').is(':checked')) {
            // Single Category mode
            $('#singleFieldContainer').show();
            $('#multipleFieldContainer').hide();
            $('#categoryGroupsInfo').hide();
            $('#subfieldContainer').hide(); // Hide category groups container
            $('#singleSubfieldContainer').hide(); // Will be shown when category with subfields is selected
            $('#mainFieldValueContainer').show(); // Show category value filter
            $('#stids').prop('disabled', true);
            
        } else if ($('#logicMultiple').is(':checked')) {
            // Multiple Categories (OR) mode
            $('#singleFieldContainer').hide();
            $('#multipleFieldContainer').show();
            $('#categoryGroupsInfo').hide();
            $('#subfieldContainer').hide(); // Hide category groups
            $('#singleSubfieldContainer').hide(); // Hide single subfield container
            $('#stids').prop('disabled', false);
            
        } else if ($('#logicCategoryGroups').is(':checked')) {
            // Multiple Category Groups (AND) mode
            $('#singleFieldContainer').hide();
            $('#multipleFieldContainer').hide();
            $('#mainFieldValueContainer').hide(); // Hide main category value filter
            $('#categoryGroupsInfo').show();
            $('#singleSubfieldContainer').hide(); // Hide single subfield container
            $('#subfieldContainer').show(); // Show category groups container
            $('#stids').prop('disabled', true);
            
            // Initialize category groups if empty AND not loading saved data
            if ($('#categoryGroupsContainer .category-group').length === 0 && !window.existingCategoryGroups) {
                console.log('Category Groups mode: creating first group');
                addCategoryGroup(0);
                $('#addCategoryGroupBtn').show();
            }
        }
    }

    function updateAddCategoryButton() {
        var $container = $('#categoryFiltersContainer');
        var $rows = $container.find('.category-filter-row');
        
        $rows.each(function(index) {
            var $row = $(this);
            var $select = $row.find('select[name*="[stid]"]');
            var $addBtn = $row.find('.add-category-filter');
            
            if ($select.val() && $rows.length === index + 1) {
                $addBtn.show();
            } else {
                $addBtn.hide();
            }
        });
    }

    // Add category filter button handler
    $(document).on('click', '.add-category-filter', function() {
        var $container = $('#categoryFiltersContainer');
        var rowCount = $container.find('.category-filter-row').length;
        
        var categoryOptions = '';
        if (window.availableFields) {
            window.availableFields.forEach(function(field) {
                categoryOptions += `<option value="${field.stid}">${field.str}</option>`;
            });
        }
        
        var newRow = $(`
            <div class="category-filter-row mb-2 d-flex align-items-center">
                <select class="form-control" name="category_filters[${rowCount}][stid]" required>
                    <option value="">-- Select Category --</option>
                    ${categoryOptions}
                </select>
                <button type="button" class="btn btn-sm btn-danger ml-2 remove-category-filter">
                    <i class="fa fa-times"></i>
                </button>
                <button type="button" class="btn btn-sm btn-success ml-2 add-category-filter" style="display:none;">
                    <i class="fa fa-plus"></i> Add Another Category
                </button>
            </div>
        `);
        
        $container.append(newRow);
        
        // Hide add button from previous row
        $container.find('.category-filter-row').not(newRow).find('.add-category-filter').hide();
    });

    // Remove category filter button handler
    $(document).on('click', '.remove-category-filter', function() {
        var $row = $(this).closest('.category-filter-row');
        $row.remove();
        
        // Update indices
        $('#categoryFiltersContainer .category-filter-row').each(function(index) {
            $(this).find('select').attr('name', `category_filters[${index}][stid]`);
        });
        
        // Show add button on last row
        updateAddCategoryButton();
    });

    // Show/hide add button based on selection
    $(document).on('change', '#categoryFiltersContainer select', function() {
        updateAddCategoryButton();
    });
    
    // Add Category Group handler
    $(document).on('click', '#addCategoryGroupBtn', function() {
        var groupCount = $('.category-group').length;
        addCategoryGroup(groupCount);
    });
    
    // Handle requirement type changes in category groups (using delegation for dynamic elements)
    $(document).on('change', '.category-requirement-type', function() {
        var $select = $(this);
        var $group = $select.closest('.category-group');
        var $minThresholdRow = $group.find('.category-minimum-threshold').closest('.row');
        var $minThresholdInput = $group.find('.category-minimum-threshold');
        var $helpText = $group.find('.category-required-value-help');
        var placeholder = $group.find('input[name*="[required_value]"]');
        
        if ($select.val() === 'PER_CASE_MINIMUM') {
            $minThresholdRow.show();
            $minThresholdInput.prop('required', true).prop('disabled', false);
            $helpText.text('Number of cases needed (e.g., 3 = need 3 cases total)');
            placeholder.attr('placeholder', 'e.g., 3 (how many cases you need)');
        } else {
            $minThresholdRow.hide();
            $minThresholdInput.prop('required', false).prop('disabled', true);
            $helpText.text('What the rule must meet');
            placeholder.attr('placeholder', 'e.g., 5 or 18-64');
        }
    });
    
    // Remove Category Group handler
    $(document).on('click', '.remove-category-group', function() {
        var $group = $(this).closest('.category-group');
        var $container = $('#categoryGroupsContainer');
        
        if ($container.find('.category-group').length > 1) {
            $group.remove();
            // Re-index groups
            $container.find('.category-group').each(function(index) {
                $(this).attr('data-group', index);
                $(this).find('.category-group-number').text(index + 1);
                $(this).find('select').attr('name', `category_groups[${index}][stid]`);
            });
        } else {
            alert('You must have at least one category group.');
        }
    });
    
    function addCategoryGroup(groupIndex) {
        console.log('Adding category group:', groupIndex);
        
        var categoryOptions = '';
        if (window.availableFields) {
            window.availableFields.forEach(function(field) {
                categoryOptions += `<option value="${field.stid}">${field.str}</option>`;
            });
        }
        
        var groupHtml = `
            <div class="category-group mb-4 border p-3 rounded" data-group="${groupIndex}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Category Group <span class="category-group-number">${groupIndex + 1}</span></h6>
                    <button type="button" class="btn btn-sm btn-danger remove-category-group">
                        <i class="fa fa-times"></i> Remove Group
                    </button>
                </div>
                
                <div class="form-group mb-3">
                    <label>Category</label>
                    <select class="form-control category-select" name="category_groups[${groupIndex}][stid]" required>
                        <option value="">-- Select Category --</option>
                        ${categoryOptions}
                    </select>
                </div>
                
                <div class="subfield-rules-section">
                    <label class="mb-2">Requirements for this Category</label>
                    
                    <!-- Subcategory Rules (if category has subcategories) -->
                    <div class="subfield-rules-table" style="display:none;">
                        <div class="subfield-rules-header">
                            <div class="rule-col">Subcategory Value</div>
                            <div class="rule-col">Requirement Type</div>
                            <div class="rule-col">Rule Value</div>
                            <div class="rule-col-actions">Actions</div>
                        </div>
                        <div class="subfield-rules-group" data-group="${groupIndex}">
                            <!-- Rules will be added here -->
                        </div>
                        <button type="button" class="btn btn-sm btn-success mt-2 add-subfield-rule-btn" data-group="${groupIndex}">
                            <i class="fa fa-plus"></i> Add Subcategory Rule
                        </button>
                    </div>
                    
                    <!-- Simple Requirement (if category has no subcategories) -->
                    <div class="simple-requirement-container mb-3" style="display:none;">
                        <div class="row">
                            <div class="col-md-6">
                                <label>Requirement Type</label>
                                <select class="form-control category-requirement-type" name="category_groups[${groupIndex}][requirement_type]" data-group="${groupIndex}" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="TOTAL_COUNT">Total Count</option>
                                    <option value="TOTAL_HOURS">Total Hours</option>
                                    <option value="UNIQUE_VALUES">Unique Values</option>
                                    <option value="UNIQUE_VALUES_IN_RANGE">Unique Values in Range</option>
                                    <option value="PER_CASE_MINIMUM">Per-Case Minimum</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Required Value</label>
                                <input type="text" class="form-control" name="category_groups[${groupIndex}][required_value]" placeholder="e.g., 3 (number of cases needed)" required>
                                <small class="text-muted category-required-value-help">How many cases total</small>
                            </div>
                        </div>
                        <div class="row" style="display:none;">
                            <div class="col-md-6">
                                <label>Minimum Threshold per Case</label>
                                <input type="number" class="form-control category-minimum-threshold" name="category_groups[${groupIndex}][minimum_threshold]" placeholder="e.g., 5" min="0" step="0.1">
                                <small class="text-muted">Minimum sessions per case</small>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <label>Maximum Value (Optional)</label>
                                <input type="number" class="form-control category-max-value" name="category_groups[${groupIndex}][max_value]" placeholder="e.g., 2 (max count allowed)" min="0">
                                <small class="text-muted">Maximum count/value allowed (e.g., max 2 presentations)</small>
                            </div>
                        </div>
                        <small class="text-muted">This category doesn't have predefined subcategories. Specify the requirement directly.</small>
                    </div>
                </div>
            </div>
        `;
        
        $('#categoryGroupsContainer').append(groupHtml);
        
        // Show add button if there's at least one group
        if ($('#categoryGroupsContainer .category-group').length > 0) {
            $('#addCategoryGroupBtn').show();
        }
    }

    // Initial State Setup
    console.log('🚀 Initial State Setup started');
    console.log('   initialTbid:', initialTbid);
    console.log('   initialStid:', initialStid);
    console.log('   existingSubfieldRules:', window.existingSubfieldRules);
    
    toggleFieldDependent($('#requirement_type').val());
    toggleFieldLogicMode();
    filterParents(initialTbid);
    
    // Load hour sources if editing TOTAL_HOURS_COMBINED
    <?php if ($is_editing && $standard['requirement_type'] == 'TOTAL_HOURS_COMBINED'): ?>
    var fieldValueData = <?php echo json_encode($standard['field_value'] ?? ''); ?>;
    console.log('📋 Loading hour sources from field_value:', fieldValueData);
    if (fieldValueData) {
        try {
            var parsed = JSON.parse(fieldValueData);
            // Support both old 'hour_sources' and new 'total_of' format
            if (parsed.total_of && Array.isArray(parsed.total_of)) {
                console.log('   Found sources (total_of):', parsed.total_of.length);
                window.existingHourSources = parsed.total_of;
            } else if (parsed.hour_sources && Array.isArray(parsed.hour_sources)) {
                console.log('   Found sources (hour_sources legacy):', parsed.hour_sources.length);
                window.existingHourSources = parsed.hour_sources;
            }
        } catch (e) {
            console.error('   Error parsing hour sources:', e);
        }
    }
    <?php endif; ?>
    
    // Load category groups if editing
    <?php if ($is_editing): ?>
    var fieldValueData = <?php echo json_encode($standard['field_value'] ?? ''); ?>;
    if (fieldValueData) {
        try {
            var parsed = JSON.parse(fieldValueData);
            if (parsed.category_groups && Array.isArray(parsed.category_groups)) {
                console.log('📋 Loading category groups from field_value:', parsed.category_groups);
                window.existingCategoryGroups = parsed.category_groups;
            }
        } catch (e) {
            console.error('   Error parsing category groups:', e);
        }
    }
    <?php endif; ?>
    
    if (initialTbid) {
        loadFieldsForTable(initialTbid, function() {
            console.log('   Fields loaded for table, restoring selections');
            
            // Load hour sources if they exist
            if (window.existingHourSources && window.existingHourSources.length > 0 && $('#requirement_type').val() === 'TOTAL_HOURS_COMBINED') {
                console.log('   Loading existing hour sources');
                // Clear the default row first
                $('#hourSourcesContainer').empty();
                
                window.existingHourSources.forEach(function(source, index) {
                    console.log(`   Loading source ${index + 1}: category=${source.category_stid}, value=${source.category_value}`);
                    addHourSourceRow();
                });
                
                // After all rows are added, populate their values
                setTimeout(function() {
                    window.existingHourSources.forEach(function(source, index) {
                        var $row = $('.hour-source-row').eq(index);
                        console.log(`   Setting values for source ${index + 1}`);
                        
                        // Set category
                        $row.find('.source-category-select').val(source.category_stid);
                        
                        // Set max_value if present
                        if (source.max_value !== undefined && source.max_value !== null) {
                            $row.find('.source-max-value').val(source.max_value);
                            console.log(`   Setting max_value for source ${index + 1}:`, source.max_value);
                        }
                        
                        // Load and set category value
                        if (source.category_stid) {
                            // If no value was saved, check if we need to show manual input
                            if (!source.category_value) {
                                console.log(`   Source ${index + 1}: No category value saved, checking for predefined values`);
                                // Try to load values, and if none found, show manual input
                                loadCategoryValues(source.category_stid, $row.find('.source-value-select'), source.category_value);
                            } else {
                                loadCategoryValues(source.category_stid, $row.find('.source-value-select'), source.category_value);
                            }
                        }
                    });
                }, 200);
            }
            // Load category groups if they exist
            if (window.existingCategoryGroups && window.existingCategoryGroups.length > 0) {
                console.log('   Loading existing category groups:', window.existingCategoryGroups.length);
                
                // Switch to category groups mode
                $('#logicCategoryGroups').prop('checked', true);
                toggleFieldLogicMode();
                
                // Clear any auto-created groups before loading saved groups
                $('#categoryGroupsContainer').empty();
                
                // Create category groups
                window.existingCategoryGroups.forEach(function(group, index) {
                    console.log(`   Loading category group ${index + 1}:`, group);
                    addCategoryGroup(index);
                    
                    // Store the subcategories for this group (will be loaded when category is selected)
                    var $group = $(`.category-group[data-group="${index}"]`);
                    $group.data('stid', group.stid);
                });
                
                // After all groups are created, load their data
                setTimeout(function() {
                    window.existingCategoryGroups.forEach(function(group, index) {
                        var $categoryGroup = $(`.category-group[data-group="${index}"]`);
                        
                        // Set the category
                        $categoryGroup.find('.category-select').val(group.stid);
                        
                        // Store the group data on the element for later use
                        $categoryGroup.data('savedGroupData', group);
                        
                        // Load subcategories for this category, then populate rules
                        if (group.stid) {
                            loadSubfieldsForGroupAndPopulate(index, group.stid, group);
                        }
                        
                        // Load simple requirements immediately (no subcategories needed)
                        if (group.requirement_type && group.required_value) {
                            console.log(`   Loading simple requirement for group ${index}`);
                            var $simpleReq = $categoryGroup.find('.simple-requirement-container');
                            var $reqTypeSelect = $simpleReq.find('select[name*="[requirement_type]"]');
                            var $minThresholdInput = $simpleReq.find('input[name*="[minimum_threshold]"]');
                            var $minThresholdRow = $minThresholdInput.closest('.row');
                            
                            $reqTypeSelect.val(group.requirement_type);
                            $simpleReq.find('input[name*="[required_value]"]').val(group.required_value);
                            
                            // Handle minimum_threshold if it exists
                            if (group.minimum_threshold) {
                                console.log(`   Loading minimum_threshold for group ${index}:`, group.minimum_threshold);
                                $minThresholdInput.val(group.minimum_threshold);
                                $minThresholdRow.show();
                                $minThresholdInput.prop('required', true).prop('disabled', false);
                            }
                            
                            // Handle max_value if it exists
                            if (group.max_value !== undefined && group.max_value !== null) {
                                console.log(`   Loading max_value for group ${index}:`, group.max_value);
                                $simpleReq.find('input[name*="[max_value]"]').val(group.max_value);
                            }
                        }
                    });
                }, 500);
            }
            
            // Restore selections after fields are loaded
            if ($('#logicSingle').is(':checked')) {
                console.log('   Single field mode, setting stid dropdown');
                
                <?php if ($is_editing && !is_null($standard['stid'])): ?>
                // Load single category
                var initialStid = <?php echo $standard['stid']; ?>;
                console.log('   Setting stid to:', initialStid);
                
                // Set the stid dropdown value
                $('#stid').val(initialStid);
                console.log('   stid set to:', initialStid);
                console.log('   stid select options available:', $('#stid option').length);
                
                // Manually trigger the change handler to load subfields
                // Use a longer delay to ensure fields are fully loaded
                setTimeout(function() {
                    console.log('   Triggering stid change event to load subfields');
                    $('#stid').trigger('change');
                }, 300);
                
                // Check field value after a short delay to see if it was set correctly
                setTimeout(function() {
                    console.log('   stid value after delay:', $('#stid').val());
                    console.log('   stid select element:', $('#stid')[0]);
                    console.log('   stid options:', $('#stid').find('option').map(function() { return {value: $(this).val(), text: $(this).text()}; }).get());
                }, 100);
                <?php else: ?>
                var initialStid = null;
                <?php endif; ?>
                
                // Show field value filter if there's a simple field_value (not complex rules)
                var fieldValueData = <?php echo json_encode($standard['field_value'] ?? ''); ?>;
                if (fieldValueData && !fieldValueData.includes('SUBFIELD_RULES:') && !fieldValueData.includes('category_groups')) {
                    console.log('   Editing with existing category value, showing main category value container');
                    $('#mainFieldValueContainer').show();
                    $('#field_value').val(fieldValueData);
                } else {
                    // Show the field value container for simple edits
                    $('#mainFieldValueContainer').show();
                }
            } else {
                console.log('   Multiple categories mode, setting selected categories');
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
        // Clear category groups when table changes
        $('#subfieldContainer').hide();
        $('#categoryGroupsContainer').empty();
        $('#mainFieldValueContainer').show();
    });
    
    $('#stid').on('change', function() {
        console.log('🎯 stid change event triggered');
        var fieldId = $(this).val();
        console.log('   Selected categoryId:', fieldId);
        
        if ($('#logicCategoryGroups').is(':checked') && fieldId) {
            // Multiple Category Groups mode - use category groups UI
            if ($('#categoryGroupsContainer .category-group').length === 0) {
                $('#addCategoryGroupBtn').show();
                addCategoryGroup(0);
            }
            $('#categoryGroupsContainer .category-group:first .category-select').val(fieldId);
            loadSubfieldsForField(fieldId);
        } else if ($('#logicSingle').is(':checked') && fieldId) {
            // Single Category mode - check if this category has subfields
            console.log('   Single category mode - checking for subfields');
            $.getJSON('ajax/get_subfields_for_field.php', { stid: fieldId }, function(subfields) {
                console.log('   Subfields received:', subfields ? subfields.length : 0);
                console.log('   Subfields data:', subfields);
                if (subfields && subfields.length > 0) {
                    // Has subfields - show subcategory rules UI
                    window.availableSubfields = subfields;
                    console.log('   ✅ Set window.availableSubfields with', subfields.length, 'items');
                    console.log('   First few subfields:', subfields.slice(0, 3).map(s => ({pid: s.pid, str: s.str})));
                    $('#singleSubfieldContainer').show();
                    $('#mainFieldValueContainer').hide(); // Hide simple field value filter when using subfields
                    $('#addSubfieldRuleBtn').show();
                    
                    // Load existing rules if editing (wait a bit to ensure UI is ready)
                    if (window.existingSubfieldRules && window.existingSubfieldRules.length > 0) {
                        console.log('   Loading existing subfield rules:', window.existingSubfieldRules.length);
                        console.log('   Existing rules data:', window.existingSubfieldRules);
                        $('#subfieldRulesContainer').empty();
                        
                        // Wait longer to ensure Select2 is initialized and subfields are loaded
                        setTimeout(function() {
                            console.log('   Starting to populate rules, availableSubfields:', window.availableSubfields);
                            window.existingSubfieldRules.forEach(function(rule, index) {
                                console.log(`   Creating rule ${index} from data:`, rule);
                                addSubfieldRuleWithData(rule, index);
                            });
                            // Rebuild options after all rules are added
                            setTimeout(function() {
                                if (window.rebuildAllSubfieldOptions) {
                                    window.rebuildAllSubfieldOptions();
                                }
                            }, 200);
                        }, 300);
                    }
                } else {
                    // No subfields - show simple field value filter
                    $('#singleSubfieldContainer').hide();
                    $('#mainFieldValueContainer').show();
                }
            }).fail(function() {
                console.error('   Failed to load subfields');
                $('#singleSubfieldContainer').hide();
                $('#mainFieldValueContainer').show();
            });
        }
    });
    
    // Handle category group category change to load subcategories
    $(document).on('change', '.category-group .category-select', function() {
        var groupIndex = $(this).closest('.category-group').data('group');
        var categoryId = $(this).val();
        console.log(`Category Group ${groupIndex} changed to: ${categoryId}`);
        
        // Load subcategories for this group
        if (categoryId) {
            loadSubfieldsForGroup(groupIndex, categoryId);
        }
    });
    
    function loadSubfieldsForGroup(groupIndex, categoryId) {
        $.getJSON('ajax/get_subfields_for_field.php', { stid: categoryId }, function(data) {
            var $group = $(`.category-group[data-group="${groupIndex}"]`);
            var $subfieldSection = $group.find('.subfield-rules-section');
            var $rulesTable = $group.find('.subfield-rules-table');
            var $simpleRequirement = $group.find('.simple-requirement-container');
            
            console.log(`Loaded ${data.length} subcategories for group ${groupIndex}`);
            
            // Store subcategories for this group
            $group.data('subcategories', data);
            
            // Show appropriate UI based on whether subcategories exist
            if (data && data.length > 0) {
                // Has subcategories - show subcategory rules table
                console.log(`   Group ${groupIndex} has subcategories, showing rules table`);
                $rulesTable.show();
                $simpleRequirement.hide().removeClass('shown');
            } else {
                // No subcategories - show simple requirement inputs
                console.log(`   Group ${groupIndex} has no subcategories, showing simple requirement`);
                $rulesTable.hide();
                $simpleRequirement.show().addClass('shown');
            }
        }).fail(function() {
            console.error(`Failed to load subcategories for group ${groupIndex}`);
            // On error, show simple requirement as fallback
            var $group = $(`.category-group[data-group="${groupIndex}"]`);
            $group.find('.subfield-rules-table').hide();
            $group.find('.simple-requirement-container').show().addClass('shown');
        });
    }
    
    function loadSubfieldsForGroupAndPopulate(groupIndex, categoryId, groupData) {
        $.getJSON('ajax/get_subfields_for_field.php', { stid: categoryId }, function(data) {
            var $group = $(`.category-group[data-group="${groupIndex}"]`);
            var $rulesTable = $group.find('.subfield-rules-table');
            var $simpleRequirement = $group.find('.simple-requirement-container');
            
            console.log(`Loaded ${data.length} subcategories for group ${groupIndex}`);
            
            // Store subcategories for this group
            $group.data('subcategories', data);
            
            // Show appropriate UI based on whether subcategories exist
            if (data && data.length > 0) {
                // Has subcategories - show subcategory rules table
                console.log(`   Group ${groupIndex} has subcategories, showing rules table`);
                $rulesTable.show();
                $simpleRequirement.hide().removeClass('shown');
                
                // Populate subcategory rules if they exist
                if (groupData.subfield_rules && Array.isArray(groupData.subfield_rules)) {
                    console.log(`   Populating ${groupData.subfield_rules.length} subcategory rules`);
                    
                    groupData.subfield_rules.forEach(function(rule, ruleIndex) {
                        console.log(`   Creating rule ${ruleIndex + 1}:`, rule);
                        
                        // Add the rule row
                        addSubfieldRuleToGroup(groupIndex);
                        
                        // Set values after a short delay to ensure element exists
                        setTimeout(function() {
                            var $row = $group.find('.subfield-rule-row').eq(ruleIndex);
                            if ($row.length) {
                                console.log(`   Setting values for rule row ${ruleIndex + 1}`);
                                var $subfieldSelect = $row.find('.subfield-select');
                                
                                console.log(`   Available options in select:`, $subfieldSelect.find('option').map(function() { return {value: $(this).val(), text: $(this).text()}; }).get());
                                console.log(`   Trying to set value to:`, rule.subfield_value);
                                
                                // Check if the option exists
                                var optionExists = $subfieldSelect.find('option[value="' + rule.subfield_value + '"]').length > 0;
                                console.log(`   Option exists?:`, optionExists);
                                
                                if (!optionExists) {
                                    // Add the missing option
                                    var subcategories = $group.data('subcategories');
                                    if (subcategories) {
                                        var missingOption = subcategories.find(function(s) { return s.pid == rule.subfield_value; });
                                        if (missingOption) {
                                            console.log(`   Adding missing option:`, missingOption);
                                            var newOption = new Option(missingOption.str, missingOption.pid, false, false);
                                            $subfieldSelect.append(newOption);
                                        }
                                    }
                                }
                                
                                // Set the value
                                $subfieldSelect.val(rule.subfield_value).trigger('change.select2');
                                
                                console.log(`   Subfield select value set to:`, rule.subfield_value);
                                console.log(`   Current value after set:`, $subfieldSelect.val());
                                
                                // Set other values
                                $row.find('select[name*="[requirement_type]"]').val(rule.requirement_type).trigger('change');
                                $row.find('input[name*="[specific_value]"]').val(rule.specific_value);
                                
                                // Handle minimum threshold for PER_CASE_MINIMUM
                                if (rule.requirement_type === 'PER_CASE_MINIMUM' && rule.minimum_threshold) {
                                    var $minThreshold = $row.find('input[name*="[minimum_threshold]"]');
                                    $minThreshold.show().prop('required', true).prop('disabled', false).val(rule.minimum_threshold);
                                }
                            }
                        }, 200 * (ruleIndex + 1));
                    });
                }
            } else {
                // No subcategories - show simple requirement inputs
                console.log(`   Group ${groupIndex} has no subcategories, showing simple requirement`);
                $rulesTable.hide();
                $simpleRequirement.show().addClass('shown');
            }
        }).fail(function() {
            console.error(`Failed to load subcategories for group ${groupIndex}`);
            var $group = $(`.category-group[data-group="${groupIndex}"]`);
            $group.find('.subfield-rules-table').hide();
            $group.find('.simple-requirement-container').show().addClass('shown');
        });
    }
    
    $('input[name="field_logic_mode"]').on('change', function() {
        toggleFieldLogicMode();
    });

    // Add Subfield Rule button event handler (for category groups)
    $(document).on('click', '.add-subfield-rule-btn', function() {
        var groupIndex = $(this).data('group');
        console.log('🔘 Add Subcategory Rule button clicked for group:', groupIndex);
        addSubfieldRuleToGroup(groupIndex);
    });

    // Add Subfield Rule button event handler (for single category mode)
    $(document).on('click', '#addSubfieldRuleBtn', function() {
        console.log('🔘 Add Subcategory Rule button clicked (single mode)');
        var ruleIndex = $('#subfieldRulesContainer .subfield-rule-row').length;
        addSubfieldRuleWithData(null, ruleIndex);
    });

    // Add Hour Source button event handler
    $(document).on('click', '#addHourSourceBtn', function() {
        console.log('🔘 Add Hour Source button clicked');
        addHourSourceRow();
    });

    // Remove Hour Source button event handler
    $(document).on('click', '.remove-source-btn', function() {
        var rowCount = $('.hour-source-row').length;
        if (rowCount > 1) {
            $(this).closest('.hour-source-row').remove();
            reindexHourSources();
        } else {
            alert('You must have at least one hour source.');
        }
    });

    // Category change handler to load category values
    $(document).on('change', '.source-category-select', function() {
        var $row = $(this).closest('.hour-source-row');
        var $valueSelect = $row.find('.source-value-select');
        var categoryStid = $(this).val();
        
        if (categoryStid) {
            loadCategoryValues(categoryStid, $valueSelect);
        } else {
            $valueSelect.html('<option value="">-- Select Category First --</option>').prop('disabled', true);
        }
    });

    function loadCategoryValues(categoryStid, $valueSelect, preselectedValue) {
        $.getJSON('ajax/get_subfields_for_field.php', { stid: categoryStid }, function(data) {
            if (data && data.length > 0) {
                $valueSelect.html('<option value="">-- Select Value --</option>');
                $.each(data, function(index, item) {
                    var selected = (preselectedValue && item.pid == preselectedValue);
                    var option = new Option(item.str, item.pid, false, false);
                    $valueSelect.append(option);
                    if (selected) {
                        option.selected = true;
                    }
                });
                $valueSelect.prop('disabled', false);
                if (preselectedValue) {
                    console.log('   Preselected value:', preselectedValue);
                }
            } else {
                // No predefined values available - replace with text input
                console.log('   No predefined values, converting to text input');
                var savedValue = preselectedValue || '';
                var $manualInput = $('<input type="text" class="form-control form-control-sm manual-category-value" placeholder="Enter value or leave blank for all" value="' + savedValue + '" />');
                $manualInput.attr('name', $valueSelect.attr('name'));
                $valueSelect.replaceWith($manualInput);
                if (savedValue) {
                    console.log('   Restored saved value:', savedValue);
                }
            }
        }).fail(function() {
            console.log('   AJAX failed, using manual input');
            var savedValue = preselectedValue || '';
            var $manualInput = $('<input type="text" class="form-control form-control-sm manual-category-value" placeholder="Enter value or leave blank for all" value="' + savedValue + '" />');
            $manualInput.attr('name', $valueSelect.attr('name'));
            $valueSelect.replaceWith($manualInput);
        });
    }

    function addHourSourceRow() {
        var rowCount = $('.hour-source-row').length;
        console.log(`Adding hour source row ${rowCount + 1}`);
        
        var categoryOptions = '';
        if (window.availableFields) {
            window.availableFields.forEach(function(field) {
                categoryOptions += `<option value="${field.stid}">${field.str}</option>`;
            });
        }
        
                var newRow = `
            <tr class="hour-source-row">
                <td>
                    <select class="form-control form-control-sm source-category-select" name="hour_sources[${rowCount}][category_stid]" required>
                        <option value="">-- Select Category --</option>
                        ${categoryOptions}
                    </select>
                    <small class="text-muted">Required</small>
                </td>
                <td>
                    <select class="form-control form-control-sm source-value-select" name="hour_sources[${rowCount}][category_value]" disabled>
                        <option value="">-- No filter (all values) --</option>
                    </select>
                    <small class="text-muted">Optional - leave blank for all values</small>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm source-max-value" name="hour_sources[${rowCount}][max_value]" placeholder="e.g., 40" min="0" step="0.1">
                    <small class="text-muted">Cap hours at this value</small>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-source-btn">Remove</button>
                </td>
            </tr>
        `;
        
        $('#hourSourcesContainer').append(newRow);
    }

    function reindexHourSources() {
        $('.hour-source-row').each(function(index) {
            $(this).find('select, input').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                }
            });
        });
    }

    function updateHourSourceCategories() {
        if (!window.availableFields) return;
        
        $('.hour-source-row').each(function() {
            // Update category filter dropdown
            var $categorySelect = $(this).find('.source-category-select');
            var categoryCurrentValue = $categorySelect.val();
            
            $categorySelect.find('option').not(':first').remove();
            
            window.availableFields.forEach(function(field) {
                $categorySelect.append(new Option(field.str, field.stid, false, false));
            });
            
            if (categoryCurrentValue) {
                $categorySelect.val(categoryCurrentValue);
            }
        });
    }

    // Handle form submission
    $('#standardForm').on('submit', function(e) {
        console.log('📝 Form submission started');
        e.preventDefault();

        // Basic validation
        console.log('🔍 Running form validation');
        
        
        // Collect category groups data
        var categoryGroups = [];
        $('.category-group').each(function() {
            var $group = $(this);
            var groupIndex = $group.data('group');
            var stid = $group.find('.category-select').val();
            
            if (!stid) {
                console.log(`   Skipping group ${groupIndex} - no category selected`);
                return;
            }
            
            var groupData = {
                stid: stid
            };
            
            // Check if this group uses simple requirement or subcategory rules
            var $simpleRequirement = $group.find('.simple-requirement-container');
            var $rulesTable = $group.find('.subfield-rules-table');
            
            // Determine which UI is active
            if ($simpleRequirement.is(':visible') && $rulesTable.is(':hidden')) {
                // Simple requirement (no subcategories)
                var requirementType = $simpleRequirement.find('select[name*="[requirement_type]"]').val();
                var requiredValue = $simpleRequirement.find('input[name*="[required_value]"]').val();
                var minimumThreshold = $simpleRequirement.find('input[name*="[minimum_threshold]"]').val();
                var maxValue = $simpleRequirement.find('input[name*="[max_value]"]').val();
                
                if (requirementType && requiredValue) {
                    groupData.requirement_type = requirementType;
                    groupData.required_value = requiredValue;
                    
                    // Add minimum_threshold if it exists and is not empty
                    if (minimumThreshold && minimumThreshold !== '') {
                        groupData.minimum_threshold = minimumThreshold;
                    }
                    
                    // Add max_value if it exists and is not empty
                    if (maxValue && maxValue !== '') {
                        groupData.max_value = parseInt(maxValue);
                    }
                }
            } else if ($simpleRequirement.is(':hidden') && $rulesTable.is(':visible')) {
                // Subcategory rules
                var subfieldRules = [];
                $group.find('.subfield-rule-row').each(function() {
                    var $ruleRow = $(this);
                    var subfieldValue = $ruleRow.find('.subfield-select').val();
                    var requirementType = $ruleRow.find('select[name*="[requirement_type]"]').val();
                    var specificValue = $ruleRow.find('input[name*="[specific_value]"]').val();
                    
                    if (subfieldValue && requirementType && specificValue) {
                        subfieldRules.push({
                            subfield_value: subfieldValue,
                            requirement_type: requirementType,
                            specific_value: specificValue
                        });
                    }
                });
                
                if (subfieldRules.length > 0) {
                    groupData.subfield_rules = subfieldRules;
                }
            }
            
            if (Object.keys(groupData).length > 1) { // Has more than just stid
                categoryGroups.push(groupData);
            }
        });
        
        console.log('   Category groups collected:', categoryGroups);
        
        // If we have category groups, store them as JSON in field_value
        if (categoryGroups.length > 0) {
            var categoryGroupsJson = JSON.stringify({ category_groups: categoryGroups });
            console.log('   Category groups JSON:', categoryGroupsJson);
            
            // Add or update hidden field
            var $existingField = $('input[name="category_groups_json"]');
            if ($existingField.length) {
                $existingField.val(categoryGroupsJson);
            } else {
                $('form#standardForm').append(`<input type="hidden" name="category_groups_json" value="">`);
                $('input[name="category_groups_json"]').val(categoryGroupsJson);
            }
        }
        
        // Custom validation for TOTAL_HOURS_COMBINED requirement type
        if ($('#requirement_type').val() === 'TOTAL_HOURS_COMBINED') {
            console.log('🔍 Processing TOTAL_HOURS_COMBINED requirement type');
            
            // Validate and collect hour sources
            var hourSources = [];
            var hourSourcesValid = true;
            
            $('.hour-source-row').each(function(index) {
                var $row = $(this);
                var categoryStid = $row.find('.source-category-select').val();
                
                // Get value from either select or text input
                var $valueElement = $row.find('.source-value-select, .manual-category-value');
                var categoryValue = $valueElement.val();
                var maxValue = $row.find('.source-max-value').val();
                
                console.log(`   Source ${index + 1}: category=${categoryStid}, value=${categoryValue}, max=${maxValue}`);
                
                if (!categoryStid) {
                    console.log(`   ❌ Source ${index + 1} missing category`);
                    hourSourcesValid = false;
                    return;
                }
                
                // categoryValue can be empty (meaning "all values")
                var sourceData = {
                    category_stid: categoryStid,
                    category_value: categoryValue || ''  // Empty string means "all values"
                };
                
                // Add max_value if provided
                if (maxValue && maxValue !== '') {
                    sourceData.max_value = parseFloat(maxValue);
                }
                
                hourSources.push(sourceData);
            });
            
            if (!hourSourcesValid) {
                console.log('❌ Hour sources validation failed');
                alert('Please complete all source fields properly.');
                return;
            }
            
            if (hourSources.length === 0) {
                console.log('❌ No hour sources defined');
                alert('Please add at least one source.');
                return;
            }
            
            console.log('   Sources to save:', hourSources);
            
            // Store as JSON in field_value
            var sourcesJson = JSON.stringify({ total_of: hourSources });
            console.log('   Sources JSON:', sourcesJson);
            
            // Add or update hidden field for field_value
            if ($('#combinedHoursFieldValue').length) {
                $('#combinedHoursFieldValue').val(sourcesJson);
            } else {
                $('form#standardForm').append('<input type="hidden" name="field_value" id="combinedHoursFieldValue" value="">');
                $('#combinedHoursFieldValue').val(sourcesJson);
            }
        }

        // Custom validation for subfield rules
        // Only validate editable rows (not read-only preview sections)
        // Check which mode we're in and only validate rows in that mode's container
        // IMPORTANT: Exclude the preview section which contains read-only displays
        var isCategoryGroupsMode = $('#logicCategoryGroups').is(':checked');
        var isSingleMode = $('#logicSingle').is(':checked');
        var $editableSubfieldRows;
        
        if (isCategoryGroupsMode) {
            // Only validate category group rules (exclude preview section)
            $editableSubfieldRows = $('#categoryGroupsContainer .subfield-rule-row').not('.subfield-rule-row:has(span.badge)');
            // Also exclude any rows inside the preview fieldset
            $editableSubfieldRows = $editableSubfieldRows.not('fieldset:has(legend:contains("Read-only")) .subfield-rule-row');
            console.log('🔍 Category Groups mode active - validating only category group rules');
        } else if (isSingleMode) {
            // Validate subfield rules in single category mode
            $editableSubfieldRows = $('#singleSubfieldContainer .subfield-rule-row');
            console.log('🔍 Single Category mode active - validating subfield rules');
        } else {
            // Multiple categories mode or other - no subfield rules to validate
            $editableSubfieldRows = $();
            console.log('🔍 Not in Category Groups or Single mode - no subfield rules to validate');
        }
        
        // Final filter: exclude any rows that don't have form inputs (read-only preview rows)
        $editableSubfieldRows = $editableSubfieldRows.filter(function() {
            var $row = $(this);
            // Preview rows don't have form inputs, only text/divs
            var hasFormInputs = $row.find('select, input[type="text"], input[type="number"]').length > 0;
            return hasFormInputs;
        });
        
        var hasSubfieldRules = $editableSubfieldRows.length > 0;
        console.log(`🔍 Checking subfield rules: ${hasSubfieldRules ? 'Yes' : 'No'} (${$editableSubfieldRows.length} editable rules)`);
        
        if (hasSubfieldRules) {
            var validSubfieldRules = true;
            $editableSubfieldRows.each(function(index) {
                var $row = $(this);
                
                // Skip if this row is in a hidden container
                if (!$row.is(':visible') || $row.closest(':hidden').length > 0) {
                    console.log(`   Rule ${index + 1} is hidden, skipping validation`);
                    return true; // continue to next
                }
                
                // Try multiple selectors for subfield value (category groups use different names)
                var $subfieldSelect = $row.find('.subfield-select');
                var subfieldValue = null;
                
                // If .subfield-select exists, get its value (works even with Select2)
                if ($subfieldSelect.length > 0) {
                    subfieldValue = $subfieldSelect.val();
                }
                
                // If no .subfield-select or no value, try finding by name pattern
                if (!$subfieldSelect.length || !subfieldValue) {
                    var $selectByName = $row.find('select[name*="[subfield_values]"], select[name*="[subfield_value]"]');
                    if ($selectByName.length > 0) {
                        subfieldValue = $selectByName.val();
                        $subfieldSelect = $selectByName;
                    }
                }
                
                // Also try getting from Select2 data if the select is hidden
                if ($subfieldSelect.length > 0 && $subfieldSelect.data('select2')) {
                    var select2Data = $subfieldSelect.data('select2');
                    if (!subfieldValue && select2Data && select2Data.data) {
                        // Try to get from Select2 internal data
                        var currentSelection = $subfieldSelect.select2('data');
                        if (currentSelection && currentSelection.length > 0) {
                            subfieldValue = currentSelection[0].id;
                        }
                    }
                }
                
                // Debug: Find all selects in the row (including hidden Select2 selects)
                var allSelects = $row.find('select');
                var visibleSelects = $row.find('select').filter(':not(.select2-hidden-accessible)');
                console.log(`   Rule ${index + 1} - Found ${allSelects.length} total select elements (${visibleSelects.length} visible):`);
                allSelects.each(function() {
                    var $sel = $(this);
                    var isHidden = $sel.hasClass('select2-hidden-accessible');
                    console.log(`     - name="${$sel.attr('name')}", val="${$sel.val()}", class="${$sel.attr('class')}", hidden=${isHidden}`);
                });
                
                // Also check Select2 hidden selects specifically
                var select2Selects = $row.find('select.select2-hidden-accessible');
                if (select2Selects.length > 0) {
                    console.log(`   Rule ${index + 1} - Found ${select2Selects.length} Select2 hidden selects:`);
                    select2Selects.each(function() {
                        var $sel = $(this);
                        try {
                            var select2Data = $sel.select2('data');
                            console.log(`     - name="${$sel.attr('name')}", val="${$sel.val()}", Select2 data:`, select2Data);
                        } catch(e) {
                            console.log(`     - name="${$sel.attr('name')}", val="${$sel.val()}", Select2 error:`, e.message);
                        }
                    });
                }
                
                var requirementType = $row.find('select[name*="[requirement_type]"]').val();
                var specificValue = $row.find('input[name*="[specific_value]"]').val();
                var minThreshold = $row.find('input[name*="[minimum_threshold]"]').val();
                var $orWrapper = $row.next('.or-alts-wrapper');
                var orCount = $orWrapper.length ? $orWrapper.find('.or-alts .or-alt').length : 0;
                
                console.log(`   Rule ${index + 1}: subfield=${subfieldValue}, type=${requirementType}, value=${specificValue}, threshold=${minThreshold}, orCount=${orCount}`);
                
                // Only validate if this row is actually in use (has some data)
                // Skip empty rows that might be placeholders or read-only preview rows
                var hasFormElements = allSelects.length > 0 || $row.find('input[type="text"], input[type="number"]').length > 0;
                
                if (!hasFormElements) {
                    console.log(`   Rule ${index + 1} has no form elements (might be preview/placeholder), skipping validation`);
                    return true; // continue to next - don't fail on rows without form elements
                }
                
                if (orCount === 0 && !subfieldValue && !requirementType && !specificValue) {
                    console.log(`   Rule ${index + 1} appears empty, skipping validation`);
                    return true; // continue to next - don't fail on empty placeholder rows
                }
                
                if ((!subfieldValue || !requirementType || !specificValue) && orCount === 0) {
                    console.log(`   ❌ Rule ${index + 1} missing required fields (subfield=${subfieldValue}, type=${requirementType}, value=${specificValue})`);
                    validSubfieldRules = false;
                    return false;
                }
                
                if (requirementType === 'PER_CASE_MINIMUM' && (!minThreshold || parseFloat(minThreshold) <= 0)) {
                    console.log(`   ❌ Rule ${index + 1} missing valid minimum threshold`);
                    validSubfieldRules = false;
                    return false;
                }

                // Validate OR alternatives if present
                if (orCount > 0) {
                    var orValid = true;
                    var hasAtLeastOneCompleteAlt = false;
                    $orWrapper.find('.or-alts .or-alt').each(function() {
                        var $alt = $(this);
                        var altSub = $alt.find('select[name*="[subfield_value]"]').val();
                        var altType = $alt.find('select[name*="[requirement_type]"]').val();
                        var altVal  = $alt.find('input[name*="[specific_value]"]').val();
                        var altMin  = $alt.find('input[name*="[minimum_threshold]"]').val();

                        // Skip empty placeholder OR rows
                        if (!altSub && !altType && !altVal) {
                            return; // continue
                        }

                        // Mark that we have a usable OR alternative
                        hasAtLeastOneCompleteAlt = true;

                        // Validate required fields for non-empty OR rows
                        if (!altSub || !altType || !altVal) { orValid = false; return false; }
                        if (altType === 'PER_CASE_MINIMUM' && (!altMin || parseFloat(altMin) <= 0)) { orValid = false; return false; }
                    });

                    // If no complete OR alts exist and base is incomplete, it will be caught by base validation above.
                    // Only fail here if we had at least one non-empty OR row and it was invalid.
                    if (!orValid) {
                        console.log(`   ❌ Rule ${index + 1} has invalid OR alternative`);
                        validSubfieldRules = false;
                        return false;
                    }
                }
            });
            
            if (!validSubfieldRules) {
                console.log('❌ Subfield rules validation failed');
                alert('Please complete all subfield rules properly.');
                return;
            } else {
                console.log('✅ Subfield rules validation passed');
            }
        }
        
        // CRITICAL: Disable hidden required fields BEFORE validation runs
        // This prevents HTML5 validation from failing on invisible required fields
        console.log('🔧 Disabling hidden required fields...');
        
        $('#minimum_threshold').prop('disabled', $('#minimumThresholdContainer').is(':hidden'));
        $('.subfield-rule-row input[name*="[minimum_threshold]"]').each(function() {
            $(this).prop('disabled', $(this).is(':hidden'));
        });
        
        // Disable hour sources section if hidden
        var isCombinedHoursHidden = $('#combinedHoursSources').is(':hidden');
        console.log('   combinedHoursSources is hidden?', isCombinedHoursHidden);
        if (isCombinedHoursHidden) {
            var $hourSourceFields = $('.hour-source-row select[required], .hour-source-row input[required]');
            console.log('   Found hour source fields to disable:', $hourSourceFields.length);
            $hourSourceFields.prop('disabled', true);
        }
        
        // Also disable any other hidden required fields
        $(this).find('input[required], select[required], textarea[required]').each(function() {
            if ($(this).is(':hidden') || $(this).closest(':hidden').length > 0) {
                $(this).prop('disabled', true);
            }
        });
        
        console.log('   Disabling hidden fields complete');
        
        if (this.checkValidity() === false) {
            console.log('❌ Form validation failed');
            
            // Log which fields are invalid
            var invalidFields = [];
            $(this).find('input:invalid, select:invalid, textarea:invalid').each(function() {
                var fieldName = $(this).attr('name') || $(this).attr('id') || 'unknown';
                var fieldValue = $(this).val();
                var fieldType = $(this).attr('type') || $(this).prop('tagName').toLowerCase();
                var isRequired = $(this).prop('required');
                var isDisabled = $(this).prop('disabled');
                var isVisible = $(this).is(':visible');
                
                console.log(`   Invalid field: ${fieldName} (${fieldType})`);
                console.log(`     Value: "${fieldValue}"`);
                console.log(`     Required: ${isRequired}, Disabled: ${isDisabled}, Visible: ${isVisible}`);
                
                invalidFields.push({
                    name: fieldName,
                    type: fieldType,
                    value: fieldValue,
                    required: isRequired,
                    disabled: isDisabled,
                    visible: isVisible
                });
            });
            
            console.log('   All invalid fields:', invalidFields);
            $(this).addClass('was-validated');
            return;
        }
        console.log('✅ Form validation passed');

        // Before serializing, disable the field input that is not active
        if ($('#logicSingle').is(':checked')) {
            console.log('   Disabling multiple fields input (single mode)');
            $('#stids').prop('disabled', true);
            
            // Ensure stid value is included (it should already be in the form)
            // No need to add hidden field - the #stid select is already in the form
        } else if ($('#logicMultiple').is(':checked')) {
            console.log('   Disabling single field input (multiple mode)');
            $('#stid').prop('disabled', true);
            $('#stids').prop('disabled', false);
        } else if ($('#logicCategoryGroups').is(':checked')) {
            // Category groups mode - stid might be null
            $('#stid').prop('disabled', true);
            $('#stids').prop('disabled', true);
        }
        
        // AND/OR transform: consolidate base + OR alts into any_of[] with continuous indices
        $('.subfield-rule-row').each(function(index) {
            var $row = $(this);
            var $orWrapper = $row.next('.or-alts-wrapper');
            var $orContainer = $orWrapper.find('.or-alts');
            if (!($orContainer.length && $orContainer.children('.or-alt').length > 0)) return;

            var anyOfItems = [];
            var baseSub = $row.find('select.subfield-select').first().val();
            var baseType = $row.find('select[name*="[requirement_type]"]').first().val();
            var baseVal  = $row.find('input[name*="[specific_value]"]').first().val();
            var baseMin  = $row.find('input[name*="[minimum_threshold]"]').first().val();
            if (baseSub && baseType && baseVal) {
                var baseObj = { subfield_value: baseSub, requirement_type: baseType, specific_value: baseVal };
                if (baseType === 'PER_CASE_MINIMUM' && baseMin) baseObj.minimum_threshold = baseMin;
                anyOfItems.push(baseObj);
            }
            $orContainer.find('.or-alt').each(function() {
                var $alt = $(this);
                var altSub = $alt.find('select[name*="[subfield_value]"]').val();
                var altType = $alt.find('select[name*="[requirement_type]"]').val();
                var altVal  = $alt.find('input[name*="[specific_value]"]').val();
                var altMin  = $alt.find('input[name*="[minimum_threshold]"]').val();
                if (altSub && altType && altVal) {
                    var altObj = { subfield_value: altSub, requirement_type: altType, specific_value: altVal };
                    if (altType === 'PER_CASE_MINIMUM' && altMin) altObj.minimum_threshold = altMin;
                    anyOfItems.push(altObj);
                }
            });

            // Remove any pre-existing any_of hidden inputs
            $row.find('input[name^="subfield_rules['+index+'][any_of]"]').remove();
            // Disable visible inputs to prevent duplicate serialization
            $row.find('select, input').prop('disabled', true);

            // Append consolidated any_of hidden inputs with continuous indices
            var hiddenHtml = '';
            for (var i = 0; i < anyOfItems.length; i++) {
                var it = anyOfItems[i];
                hiddenHtml += `<input type="hidden" name="subfield_rules[${index}][any_of][${i}][subfield_value]" value="${it.subfield_value}">`;
                hiddenHtml += `<input type="hidden" name="subfield_rules[${index}][any_of][${i}][requirement_type]" value="${it.requirement_type}">`;
                hiddenHtml += `<input type="hidden" name="subfield_rules[${index}][any_of][${i}][specific_value]" value="${String(it.specific_value).replace(/\"/g,'&quot;')}">`;
                if (it.requirement_type === 'PER_CASE_MINIMUM' && it.minimum_threshold) {
                    hiddenHtml += `<input type="hidden" name="subfield_rules[${index}][any_of][${i}][minimum_threshold]" value="${it.minimum_threshold}">`;
                }
            }
            $row.append(hiddenHtml);
        });
        
        // Handle minimum threshold validation
        if ($('#requirement_type').val() === 'PER_CASE_MINIMUM') {
            var minThreshold = $('#minimum_threshold').val();
            if (!minThreshold || parseFloat(minThreshold) <= 0) {
                console.log('❌ Minimum threshold validation failed');
                alert('Please enter a valid minimum threshold value for per-case minimum requirements.');
                return;
            }
        }

        console.log('📊 Serializing form data...');
        var formData = $(this).serialize();
        console.log('   Form data:', formData);
        
        // Log subcategory rules specifically
        var subfieldRules = [];
        $('.subfield-rule-row').each(function(index) {
            var rule = {
                subfield_value: $(this).find('.subfield-select').val(),
                requirement_type: $(this).find('select[name*="[requirement_type]"]').val(),
                specific_value: $(this).find('input[name*="[specific_value]"]').val()
            };
            subfieldRules.push(rule);
        });
        console.log('   Subcategory rules to be sent:', subfieldRules);
        console.log('   Number of subcategory rule rows found:', $('.subfield-rule-row').length);
        console.log('   Subcategory rules container HTML:', $('#subfieldRulesContainer').html());
        
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