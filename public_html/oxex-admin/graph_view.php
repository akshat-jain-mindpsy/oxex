<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    header("Location: index.php");
    exit();
}

// Page setup
$pagetitle = "Graph View";

setAdminVars(0); // Dashboard section
$subtitle = "Data Visualization";
$listurl = "indextable.php";
$listname = "Dashboard";

// Fetch table details for graph data sources
if ($usingSupabase) {
    $tables_stmt = $supabase_pdo->prepare("SELECT tbid, tab_name FROM tabs_tbl WHERE isvis = 1 ORDER BY sort_order ASC");
    $tables_stmt->execute();
    $tables_result = $tables_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $tables_result = [];
}

// Fetch trainee data for user filtering
$trainees = array();
$canViewAll = ($admintype == 'AT' || $admintype == 'DV');

// Fetch trainee subsets (groups)
$subsets = [];
if ($usingSupabase) {
    $subsets_stmt = $supabase_pdo->prepare("SELECT setkey, subset, usrkey FROM subset_tbl ORDER BY subset ASC");
    $subsets_stmt->execute();
    $subsets_data = $subsets_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($subsets_data as $subset) {
        if ($canViewAll || $subset['usrkey'] == $usrkey) {
            $subsets[] = $subset;
        }
    }
}

// Determine which trainees this admin can view
if ($usingSupabase) {
    $trainee_stmt = $supabase_pdo->prepare("SELECT t.trainkey, t.name, t.uid, t.year, t.supervisor, t.supervisor2, t.supervisor3, t.tutor, u.university 
                     FROM trainee_tbl t 
                     LEFT JOIN uni_tbl u ON t.uid = u.uid
                     ORDER BY t.name ASC");
    $trainee_stmt->execute();
    $trainee_data = $trainee_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($trainee_data as $trainee) {
        $canView = $canViewAll;
        
        // Check if user is supervisor or tutor for this trainee
        if (!$canView) {
            if ($trainee['supervisor'] == $usrkey || 
                $trainee['supervisor2'] == $usrkey || 
                $trainee['supervisor3'] == $usrkey || 
                $trainee['tutor'] == $usrkey) {
                $canView = true;
            }
        }
        
        if ($canView) {
            $trainees[] = $trainee;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
   
   <!-- Cache busting meta tags to prevent js/css caching issues -->
   <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
   <meta http-equiv="Pragma" content="no-cache">
   <meta http-equiv="Expires" content="0">

   <!-- Additional styles for graph page -->
   <style>
       .graph-container {
           background-color: #fff;
           border-radius: 4px;
           box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
           margin-bottom: 30px;
           padding: 20px;
           position: relative;
           min-height: 400px;
       }
       
       .graph-filters {
           background-color: #f8f9fa;
           border-radius: 4px;
           padding: 15px;
           margin-bottom: 20px;
       }
       
       .graph-loading {
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           bottom: 0;
           background-color: rgba(255,255,255,0.7);
           display: flex;
           align-items: center;
           justify-content: center;
           z-index: 10;
       }
       
       .no-data-message {
           text-align: center;
           padding: 100px 0;
           color: #6c757d;
       }
   </style>

   <!-- Load Chart.js for data visualization -->
   <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
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
               <!-- Page header -->
              <div class="content-header">
                  <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small></div>
              </div>

               <!-- Filters section -->
               <div class="row">
                   <div class="col-12">
                       <div class="card border-info mb-4">
                           <div class="card-header bg-info">
                               <div class="card-title">Data Visualization Options</div>
                           </div>
                           <div class="card-body">
                               <form id="graphFilterForm">
                                  <div class="row">
                                      <div class="col-12 col-md-4">
                                           <div class="form-group">
                                               <label class="col-form-label" for="traineeSelect">Select User or Group <span class="text-danger">*</span></label>
                                               <select class="custom-select custom-select-lg mb-3" id="traineeSelect" name="trainee_key" required>
                                                   <option value="">-- Select a User or Group --</option>
                                                   <option value="ALL_USERS">📊 All Users (Aggregated Data)</option>
                                                   <?php if (!empty($subsets)): ?>
                                                   <optgroup label="Trainee Groups">
                                                       <?php foreach ($subsets as $subset): ?>
                                                       <option value="subset_<?php echo $subset['setkey']; ?>">
                                                           <?php echo htmlspecialchars($subset['subset']); ?> (Group)
                                                       </option>
                                                       <?php endforeach; ?>
                                                   </optgroup>
                                                   <?php endif; ?>

                                                   <?php if (!empty($trainees)): ?>
                                                   <optgroup label="Individual Trainees">
                                                   <?php
                                                       foreach ($trainees as $trainee) {
                                                           echo '<option value="' . $trainee['trainkey'] . '">' . 
                                                                htmlspecialchars($trainee['name']) . ' (' . 
                                                                htmlspecialchars($trainee['university']) . ' - ' . 
                                                                $trainee['year'] . ')</option>';
                                                       }
                                                   ?>
                                                   </optgroup>
                                                   <?php endif; ?>
                                               </select>
                                               <small class="form-text text-muted">Select a specific trainee, a group of trainees, or "All Users" to view aggregated data across all accessible users</small>
</div>
                                      <div class="col-12 col-md-4">
                                           <div class="form-group">
                                               <label class="col-form-label" for="dataSource">Data Source</label>
                                               <select class="custom-select custom-select-lg mb-3" id="dataSource" name="data_source">
                                                   <option value="">Select a data source</option>
                                                   <?php
                                                   if (!empty($tables_result)) {
                                                       foreach ($tables_result as $table) {
                                                           echo '<option value="' . $table['tbid'] . '">' . htmlspecialchars($table['tab_name']) . '</option>';
                                                       }
                                                   }
                                                   ?>
                                               </select>
</div>
                                      <div class="col-12 col-md-4">
                                           <div class="form-group">
                                               <label class="col-form-label" for="chartType">Chart Type</label>
                                               <select class="custom-select custom-select-lg mb-3" id="chartType" name="chart_type">
                                                   <option value="bar">Bar Chart</option>
                                                   <option value="line">Line Chart</option>
                                                   <option value="pie">Pie Chart</option>
                                                   <option value="doughnut">Doughnut Chart</option>
                                                   <option value="radar">Radar Chart</option>
                                               </select>
</div>
                                   </div>
                                  <div class="row">
                                      <div class="col-12 col-md-4">
                                           <div class="form-group">
                                               <label class="col-form-label" for="timeFrame">Time Frame</label>
                                               <select class="custom-select custom-select-lg mb-3" id="timeFrame" name="time_frame">
                                                   <option value="last30">Last 30 Days</option>
                                                   <option value="last90">Last 90 Days</option>
                                                   <option value="last180">Last 180 Days</option>
                                                   <option value="lastyear">Last Year</option>
                                                   <option value="custom">Custom Date Range</option>
                                                   <option value="all">All Time</option>
                                               </select>
                                           </div>
                                           <div id="customDateRange" style="display: none;">
                                               <div class="form-group">
                                                   <label class="col-form-label" for="startDate">Start Date</label>
                                                   <input type="date" class="form-control" id="startDate" name="start_date">
                                               </div>
                                               <div class="form-group">
                                                   <label class="col-form-label" for="endDate">End Date</label>
                                                   <input type="date" class="form-control" id="endDate" name="end_date">
</div>
                                       </div>
                                      <div class="col-12 col-md-8">
                                           <div class="form-group">
                                               <label class="col-form-label">Field Selection</label>
                                               <div class="alert alert-info">
                                                   <i class="fas fa-info-circle"></i> Select multiple fields for both X and Y axes to create comprehensive visualizations.
</div>
</div>
                               </form>
</div>
</div>

               <!-- Auto-generate all graphs section -->
               <div class="row">
                   <div class="col-12">
                       <div class="card border-info mb-4">
                           <div class="card-header bg-info">
                               <div class="card-title">Field Combination Visualizations</div>
                           </div>
                           <div class="card-body">
                               <p>Select X-axis and Y-axis fields to generate graphs for all combinations in the selected time frame.</p>
                               <div class="alert alert-info">
                                   <i class="fas fa-info-circle"></i> Select multiple fields for both X and Y axes to create comprehensive visualizations.
                               </div>
                               <div class="alert alert-warning trainee-filter-alert" style="display:none;">
                                   <i class="fas fa-filter"></i> <strong>User Filter Active:</strong> 
                                   Data will be filtered for <span class="selected-trainee-name"></span>
                               </div>
                               
                               <!-- Field Selection Section -->
                               <div id="customFieldSelectionSection" style="display:none;">
                                   <div class="row">
                                       <div class="col-md-6">
                                           <div class="form-group">
                                               <label class="col-form-label"><strong>X-Axis Categories (select multiple)</strong></label>
                                               <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                                   <div id="xAxisFieldsList">
                                                       <!-- Checkboxes will be populated here -->
</div>
                                               <small class="form-text text-muted">Select one or more fields for X-axis</small>
</div>
                                       <div class="col-md-6">
                                           <div class="form-group">
                                               <label class="col-form-label"><strong>Y-Axis Categories (select multiple)</strong></label>
                                               <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                                   <div id="yAxisFieldsList">
                                                       <!-- Checkboxes will be populated here -->
</div>
                                               <small class="form-text text-muted">Select one or more fields for Y-axis</small>
</div>
                                   </div>
                                   
                                   <div class="row">
                                       <div class="col-12">
                                           <div class="alert alert-secondary">
                                               <strong>Selected Combinations:</strong>
                                               <div id="combinationPreview">
                                                   <em>Select fields above to see combinations preview</em>
</div>
</div>
                               </div>
                               
                               <div id="fieldSelectionPrompt" class="text-center py-4">
                                   <i class="fas fa-table fa-2x mb-3 text-muted"></i>
                                   <p class="text-muted">Please select a data source first to load available fields</p>
</div>
                           <div class="card-footer">
                               <div class="float-right">
                                   <button type="button" id="generateAllGraphsBtn" class="btn btn-success">Generate Selected Combinations</button>
                               </div>
                               <div class="float-left">
                                   <button type="button" id="selectAllXFields" class="btn btn-outline-secondary btn-sm" style="display:none;">Select All X</button>
                                   <button type="button" id="clearAllXFields" class="btn btn-outline-secondary btn-sm ml-1" style="display:none;">Clear All X</button>
                                   <button type="button" id="selectAllYFields" class="btn btn-outline-secondary btn-sm ml-3" style="display:none;">Select All Y</button>
                                   <button type="button" id="clearAllYFields" class="btn btn-outline-secondary btn-sm ml-1" style="display:none;">Clear All Y</button>
</div>
</div>
               </div>

               <!-- Multiple graphs container -->
               <div id="allGraphsContainer" style="display:none;">
                   <div class="row">
                       <div class="col-12">
                           <div class="card border-info mb-4">
                               <div class="card-header bg-info">
                                   <div class="card-title">All Field Visualizations</div>
                               </div>
                               <div class="card-body">
                                   <div id="graphsLoadingIndicator" class="text-center py-5" style="display:none;">
                                       <div class="spinner-border text-primary" role="status">
                                           <span class="sr-only">Loading all graphs...</span>
                                       </div>
                                       <p class="mt-2">Generating visualizations... This may take a moment.</p>
                                   </div>
                                   <div id="allGraphsList" class="row">
                                       <!-- Graphs will be populated here -->
</div>
</div>
</div>

               

               <!-- Data tables (tabbed) section -->
               <div class="row">
                   <div class="col-12">
                       <div class="card border-info mb-4">
                           <div class="card-header bg-info">
                               <div class="card-title">Data Tables</div>
                           </div>
                           <div class="card-body">
                               <div id="noTableDataMessage" class="text-center py-5">
                                   <p class="text-muted">Generate graphs to see data tables per combination</p>
                               </div>
                               <ul class="nav nav-tabs" id="graphTabsNav" role="tablist" style="display:none;"></ul>
                               <div class="tab-content" id="graphTabsContent"></div>
</div>
</div>
           </div>
       </section>
   </div>

   <?php include 'incl/adminjs.php' ?>

   <script type="text/javascript">
       // Global variables
       
       $(document).ready(function() {
           // Handle time frame change to show/hide custom date range
           $('#timeFrame').on('change', function() {
               const selectedTimeFrame = $(this).val();
               if (selectedTimeFrame === 'custom') {
                   $('#customDateRange').show();
                   // Clear any previous date values
                   $('#startDate, #endDate').val('');
               } else {
                   $('#customDateRange').hide();
                   // Clear date values when switching to other time frames
                   $('#startDate, #endDate').val('');
               }
           });
           
           // Update trainee filter indicator when user selection changes
           $('#traineeSelect').on('change', function() {
               const traineeKey = $(this).val();
               const traineeName = traineeKey ? $(this).find('option:selected').text() : '';
               
               if (traineeKey === 'ALL_USERS') {
                   $('#selectedTraineeName').text('All Users (Aggregated)');
                   $('.selected-trainee-name').text('All Users (Aggregated)');
                   $('#traineeFilterIndicator').show();
                   $('.trainee-filter-alert').show();
                   $('.trainee-filter-alert').removeClass('alert-warning').addClass('alert-info');
                   $('.trainee-filter-alert i').removeClass('fa-filter').addClass('fa-users');
                   $('.trainee-filter-alert').html('<i class="fas fa-users"></i> <strong>All Users Mode:</strong> Data will be aggregated across all accessible users');
               } else if (traineeKey.startsWith('subset_')) {
                   const groupName = traineeName.replace('(Group)', '').trim();
                   $('#selectedTraineeName').text(groupName);
                   $('.selected-trainee-name').text(groupName);
                   $('#traineeFilterIndicator').show();
                   $('.trainee-filter-alert').show();
                   $('.trainee-filter-alert').removeClass('alert-info').addClass('alert-warning');
                   $('.trainee-filter-alert i').removeClass('fa-users').addClass('fa-filter');
                   $('.trainee-filter-alert').html(`<i class="fas fa-users-cog"></i> <strong>Group Filter Active:</strong> Data is filtered for the "${groupName}" group.`);
               } else if (traineeKey) {
                   $('#selectedTraineeName').text(traineeName);
                   $('.selected-trainee-name').text(traineeName);
                   $('#traineeFilterIndicator').show();
                   $('.trainee-filter-alert').show();
                   $('.trainee-filter-alert').removeClass('alert-info').addClass('alert-warning');
                   $('.trainee-filter-alert i').removeClass('fa-users').addClass('fa-filter');
                   $('.trainee-filter-alert').html('<i class="fas fa-filter"></i> <strong>User Filter Active:</strong> Data will be filtered for <span class="selected-trainee-name">' + traineeName + '</span>');
               } else {
                   $('#traineeFilterIndicator').hide();
                   $('.trainee-filter-alert').hide();
               }
           });
           
           // Handle data source change
           $('#dataSource').on('change', function() {
               const tableId = $(this).val();
               
               if (tableId) {
                   // Clear and hide single field selection
                   $('#fieldSelectionRow').hide();
                   
                   // Clear and hide custom field selection
                   $('#xAxisFieldsList, #yAxisFieldsList').empty();
                   $('#customFieldSelectionSection').hide();
                   $('#fieldSelectionPrompt').show();
                   $('#generateAllGraphsBtn').prop('disabled', true);
                   $('.btn-outline-secondary').hide();
                   
                   // Load fields for this table
                   $.ajax({
                       url: 'ajax/get_table_fields.php',
                       type: 'GET',
                       data: {
                           table_id: tableId
                       },
                       dataType: 'json',
                       success: function(response) {
                           console.log('Fields response:', response);
                           
                           let fields = [];
                           
                           if (Array.isArray(response)) {
                               // For backward compatibility with older API
                               fields = response;
                               response.forEach(function(field) {
                                   // No need to populate single field selects
                               });
                           } else if (response.status === 'success' && response.fields) {
                               // New API format
                               fields = response.fields;
                               response.fields.forEach(function(field) {
                                   // No need to populate single field selects
                               });
                           } else {
                               alert('Error loading categories: ' + (response.message || 'Unknown error'));
                               return;
                           }
                           
                           // Populate custom field selection checkboxes
                           if (fields.length > 0) {
                               populateFieldCheckboxes(fields);
                               $('#fieldSelectionPrompt').hide();
                               $('#customFieldSelectionSection').show();
                               $('.btn-outline-secondary').show();
                           }
                       },
                       error: function(xhr, status, error) {
                           console.error('AJAX Error:', xhr.responseText);
                           alert('Network error when loading fields');
                       }
                   });
               } else {
                   // Hide and clear field selection
                   $('#fieldSelectionRow').hide();
                   $('#customFieldSelectionSection').hide();
                   $('#fieldSelectionPrompt').show();
                   $('#generateAllGraphsBtn').prop('disabled', true);
                   $('.btn-outline-secondary').hide();
               }
           });
           
           // Function to populate field checkboxes
           function populateFieldCheckboxes(fields) {
               $('#xAxisFieldsList, #yAxisFieldsList').empty();
               
               fields.forEach(function(field) {
                   const id = field.id || field.stid;
                   const name = field.name || field.str;
                   
                   if (id && name) {
                       // Create checkbox for X-axis
                       const xCheckbox = `
                           <div class="form-check">
                               <input class="form-check-input x-field-checkbox" type="checkbox" value="${id}" id="x_field_${id}">
                               <label class="form-check-label" for="x_field_${id}">
                                   ${name}
                               </label>
                           </div>
                       `;
                       
                       // Create checkbox for Y-axis
                       const yCheckbox = `
                           <div class="form-check">
                               <input class="form-check-input y-field-checkbox" type="checkbox" value="${id}" id="y_field_${id}">
                               <label class="form-check-label" for="y_field_${id}">
                                   ${name}
                               </label>
                           </div>
                       `;
                       
                       $('#xAxisFieldsList').append(xCheckbox);
                       $('#yAxisFieldsList').append(yCheckbox);
                   }
               });
               
               // Add change event listeners to update preview
               $('.x-field-checkbox, .y-field-checkbox').on('change', updateCombinationPreview);
           }
           
           // Function to update combination preview
           function updateCombinationPreview() {
               const selectedXFields = $('.x-field-checkbox:checked').map(function() {
                   return {
                       id: $(this).val(),
                       name: $(this).next('label').text().trim()
                   };
               }).get();
               
               const selectedYFields = $('.y-field-checkbox:checked').map(function() {
                   return {
                       id: $(this).val(),
                       name: $(this).next('label').text().trim()
                   };
               }).get();
               
               let previewHtml = '';
               let combinationCount = 0;
               
               if (selectedXFields.length === 0 || selectedYFields.length === 0) {
                   previewHtml = '<em>Select fields from both X and Y axes to see combinations</em>';
                   $('#generateAllGraphsBtn').prop('disabled', true);
               } else {
                   const combinations = [];
                   selectedXFields.forEach(function(xField) {
                       selectedYFields.forEach(function(yField) {
                           combinations.push(`${xField.name} vs ${yField.name}`);
                           combinationCount++;
                       });
                   });
                   
                   if (combinations.length > 0) {
                       previewHtml = `<strong>${combinationCount} graphs will be generated:</strong><br>`;
                       previewHtml += combinations.slice(0, 10).join(', ');
                       if (combinations.length > 10) {
                           previewHtml += `, ... and ${combinations.length - 10} more`;
                       }
                       $('#generateAllGraphsBtn').prop('disabled', false);
                   }
               }
               
               $('#combinationPreview').html(previewHtml);
           }
           
           // Select/Clear all buttons functionality
           $('#selectAllXFields').on('click', function() {
               $('.x-field-checkbox').prop('checked', true);
               updateCombinationPreview();
           });
           
           $('#clearAllXFields').on('click', function() {
               $('.x-field-checkbox').prop('checked', false);
               updateCombinationPreview();
           });
           
           $('#selectAllYFields').on('click', function() {
               $('.y-field-checkbox').prop('checked', true);
               updateCombinationPreview();
           });
           
           $('#clearAllYFields').on('click', function() {
               $('.y-field-checkbox').prop('checked', false);
               updateCombinationPreview();
           });
           
           // Handle the Generate All Graphs button
           $('#generateAllGraphsBtn').on('click', function() {
               const tableId = $('#dataSource').val();
               const timeFrame = $('#timeFrame').val();
               const traineeKey = $('#traineeSelect').val();
               
               if (!tableId) {
                   alert('Please select a data source first');
                   return;
               }
               
               // For admin interface, trainee selection is required
               if (!traineeKey) {
                   alert('Please select a trainee or "All Users" to view data');
                   return;
               }
               
               // Get selected fields
               const selectedXFields = $('.x-field-checkbox:checked').map(function() {
                   return {
                       id: $(this).val(),
                       name: $(this).next('label').text().trim()
                   };
               }).get();
               
               const selectedYFields = $('.y-field-checkbox:checked').map(function() {
                   return {
                       id: $(this).val(),
                       name: $(this).next('label').text().trim()
                   };
               }).get();
               
               if (selectedXFields.length === 0 || selectedYFields.length === 0) {
                   alert('Please select at least one field from both X and Y axes');
                   return;
               }
               
               // Validate custom date range if selected
               if (timeFrame === 'custom') {
                   const startDate = $('#startDate').val();
                   const endDate = $('#endDate').val();
                   
                   if (!startDate || !endDate) {
                       alert('Please select both start and end dates');
                       return;
                   }
                   
                   if (new Date(startDate) > new Date(endDate)) {
                       alert('Start date cannot be after end date');
                       return;
                   }
               }
               
               // Show loading indicator and container
               $('#graphsLoadingIndicator').show();
               $('#allGraphsContainer').show();
               $('#allGraphsList').empty();
               // Reset tabbed data tables for new generation
               $('#graphTabsNav').empty().hide();
               $('#graphTabsContent').empty();
               $('#noTableDataMessage').show();
               
               // Generate field combinations and create graphs
               generateSelectedFieldCombinations(tableId, selectedXFields, selectedYFields, traineeKey, timeFrame);
           });
           
           function generateSelectedFieldCombinations(tableId, selectedXFields, selectedYFields, traineeKey, timeFrame) {
               const chartType = $('#chartType').val() || 'bar';
               const traineeName = traineeKey ? $('#traineeSelect option:selected').text() : '';
               
               // Update section title to include trainee if selected
               if (traineeKey) {
                   $('.card-title:contains("All Field Visualizations")').text(`Custom Field Combinations for ${traineeName}`);
               }
               
               // Generate all combinations of selected X and Y fields
               const graphsToGenerate = [];
               
               selectedXFields.forEach(function(xField) {
                   selectedYFields.forEach(function(yField) {
                       graphsToGenerate.push({
                           fieldX: xField.id,
                           fieldXName: xField.name,
                           fieldY: yField.id,
                           fieldYName: yField.name,
                           chartType: chartType
                       });
                   });
               });
               
               if (graphsToGenerate.length === 0) {
                   $('#graphsLoadingIndicator').hide();
                   $('#allGraphsList').html('<div class="col-12 text-center py-5"><p>No field combinations to generate.</p></div>');
                   return;
               }
               
               // Update loading message with actual count
               $('#graphsLoadingIndicator p').text(`Generating ${graphsToGenerate.length} visualizations... This may take a moment.`);
               
               // Now generate all the graphs
               generateGraphsSequentially(tableId, graphsToGenerate, timeFrame, 0, traineeKey, traineeName);
           }
           
           function generateGraphsSequentially(tableId, graphConfigs, timeFrame, index, traineeKey, traineeName) {
               if (index >= graphConfigs.length) {
                   // All graphs have been generated
                   $('#graphsLoadingIndicator').hide();
                   return;
               }
               
               const config = graphConfigs[index];
               const graphId = `graph-${config.fieldX}-${config.fieldY}`;
               
               // Create a placeholder for this graph
               $('#allGraphsList').append(`
                   <div class="col-md-6 col-lg-4 mb-4">
                       <div class="card">
                           <div class="card-header">
                               <h5>${config.fieldXName} vs ${config.fieldYName}</h5>
                               <div class="small text-muted">${config.chartType.charAt(0).toUpperCase() + config.chartType.slice(1)} chart${traineeKey ? ' for ' + traineeName.split('(')[0] : ''}</div>
                           </div>
                           <div class="card-body">
                               <div class="graph-loading-mini" id="loading-${graphId}">
                                   <div class="spinner-border spinner-border-sm text-primary" role="status">
                                       <span class="sr-only">Loading...</span>
</div>
                               <canvas id="${graphId}" height="250"></canvas>
</div>
                   </div>
               `);
               
               // Prepare request data
               const requestData = {
                   table_id: tableId,
                   field_x: config.fieldX,
                   field_y: config.fieldY,
                   chart_type: config.chartType,
                   time_frame: timeFrame,
                   trainee_key: traineeKey
               };
               
               // Add custom date range if selected
               if (timeFrame === 'custom') {
                   requestData.start_date = $('#startDate').val();
                   requestData.end_date = $('#endDate').val();
               }
               
               // Fetch data for this graph
               $.ajax({
                   url: 'ajax/get_graph_data.php',
                   type: 'POST',
                   data: requestData,
                   dataType: 'json',
                   success: function(response) {
                       $(`#loading-${graphId}`).hide();
                       
                       if (response.status === 'success') {
                           // Render the graph
                           renderSmallGraph(graphId, response.data, config.chartType, response.labels);
                           // Add/update a tabbed data table for this graph
                           upsertGraphDataTab(graphId, `${config.fieldXName} vs ${config.fieldYName}`.trim(), response.data, response.labels);
                       } else {
                           // Show error message
                           $(`#${graphId}`).parent().html(`<div class="alert alert-warning">Unable to generate graph: ${response.message}</div>`);
                       }
                       
                       // Process the next graph
                       setTimeout(function() {
                           generateGraphsSequentially(tableId, graphConfigs, timeFrame, index + 1, traineeKey, traineeName);
                       }, 100);
                   },
                   error: function(xhr, status, error) {
                       $(`#loading-${graphId}`).hide();
                       
                       let errorMessage = error;
                       // Try to parse error response if it's JSON
                       try {
                           const errorResponse = JSON.parse(xhr.responseText);
                           if (errorResponse && errorResponse.message) {
                               errorMessage = errorResponse.message;
                           }
                       } catch (e) {
                           // If it's not valid JSON, use the xhr.responseText as is
                           if (xhr.responseText) {
                               errorMessage = 'Server error';
                               console.error('AJAX Error:', xhr.responseText);
                           }
                       }
                       
                       $(`#${graphId}`).parent().html(`<div class="alert alert-danger">Error: ${errorMessage}</div>`);
                       
                       // Process the next graph despite the error
                       setTimeout(function() {
                           generateGraphsSequentially(tableId, graphConfigs, timeFrame, index + 1, traineeKey, traineeName);
                       }, 100);
                   }
               });
           }
           
           function renderSmallGraph(canvasId, data, chartType, labels) {
               const ctx = document.getElementById(canvasId).getContext('2d');
               
               // Get trainee info for title if applicable
               const traineeName = $('#traineeSelect option:selected').text();
               const traineeKey = $('#traineeSelect').val();
               const titleSuffix = traineeKey && traineeKey !== '' ? ` (${traineeName.split(' ')[0]})` : '';
               
               // Format data for Chart.js
               const chartData = {
                   labels: labels || data.map(item => item.label),
                   datasets: [{
                       label: 'Data Values',
                       data: data.map(item => item.value),
                       backgroundColor: [
                           'rgba(54, 162, 235, 0.6)',
                           'rgba(255, 99, 132, 0.6)',
                           'rgba(255, 206, 86, 0.6)',
                           'rgba(75, 192, 192, 0.6)',
                           'rgba(153, 102, 255, 0.6)',
                           'rgba(255, 159, 64, 0.6)'
                       ],
                       borderColor: [
                           'rgba(54, 162, 235, 1)',
                           'rgba(255, 99, 132, 1)',
                           'rgba(255, 206, 86, 1)',
                           'rgba(75, 192, 192, 1)',
                           'rgba(153, 102, 255, 1)',
                           'rgba(255, 159, 64, 1)'
                       ],
                       borderWidth: 1
                   }]
               };
               
               // Create chart with simplified options for the small graphs
               new Chart(ctx, {
                   type: chartType,
                   data: chartData,
                   options: {
                       responsive: true,
                       maintainAspectRatio: false,
                       scales: {
                           y: {
                               beginAtZero: true,
                               display: chartType !== 'pie' && chartType !== 'doughnut' && chartType !== 'radar'
                           }
                       },
                       plugins: {
                           legend: {
                               display: chartType === 'pie' || chartType === 'doughnut',
                               position: 'bottom',
                               labels: {
                                   boxWidth: 10,
                                   font: {
                                       size: 10
                                   }
                               }
                           },
                           title: {
                               display: titleSuffix !== '',
                               text: titleSuffix,
                               font: {
                                   size: 12
                               }
                           }
                       }
                   }
               });
           }

        // Create or update a tab for a graph's data table
        function upsertGraphDataTab(graphId, title, data, labels) {
            if (!Array.isArray(data) || data.length === 0) {
                return;
            }

            const safeId = graphId.replace(/[^a-zA-Z0-9_-]/g, '');
            const tabId = 'tab-' + safeId;
            const paneId = 'pane-' + safeId;

            // Build rows
            const total = data.reduce(function(sum, item) {
                const val = Number(item.value) || 0;
                return sum + val;
            }, 0);

            const rowsHtml = data.map(function(item, idx) {
                const label = (labels && labels[idx]) || item.label || '';
                const value = Number(item.value) || 0;
                const pct = total ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                return '<tr>' +
                    '<td>' + label + '</td>' +
                    '<td>' + value + '</td>' +
                    '<td>' + pct + '</td>' +
                '</tr>';
            }).join('');

            // If first tab, show nav and hide empty message
            if ($('#graphTabsNav li').length === 0) {
                $('#noTableDataMessage').hide();
                $('#graphTabsNav').show();
            }

            // Upsert nav tab
            const existingTab = $('#' + tabId);
            if (existingTab.length === 0) {
                const isActive = $('#graphTabsNav li').length === 0 ? 'active' : '';
                $('#graphTabsNav').append(
                    '<li class="nav-item" role="presentation">' +
                        '<a class="nav-link ' + isActive + '" id="' + tabId + '" data-toggle="tab" href="#' + paneId + '" role="tab" aria-controls="' + paneId + '" aria-selected="' + (isActive ? 'true' : 'false') + '">' +
                            title +
                        '</a>' +
                    '</li>'
                );

                // Upsert content pane
                $('#graphTabsContent').append(
                    '<div class="tab-pane fade ' + (isActive ? 'show active' : '') + '" id="' + paneId + '" role="tabpanel" aria-labelledby="' + tabId + '">' +
                        '<div class="table-responsive mt-3">' +
                            '<table class="table table-striped table-hover">' +
                                '<thead>' +
                                    '<tr><th>Category</th><th>Value</th><th>Percentage</th></tr>' +
                                '</thead>' +
                                '<tbody>' + rowsHtml + '</tbody>' +
                            '</table>' +
                        '</div>' +
                    '</div>'
                );
            } else {
                // Update rows in existing pane
                $('#' + paneId + ' tbody').html(rowsHtml);
                // Update title if changed
                existingTab.text(title);
            }
        }
        
    });
   </script>
   <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
</body>
</html>