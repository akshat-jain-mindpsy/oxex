<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
sec_session_start();
check_session_timeout();
include 'incl/sess.php';

// Get trainee information
$name = '';
if (isset($trainkey) && !empty($trainkey)) {
    $trainee_stmt = $mysqli->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
    if ($trainee_stmt) {
        $trainee_stmt->bind_param("s", $trainkey);
        $trainee_stmt->execute();
        $trainee_stmt->bind_result($name);
        $trainee_stmt->fetch();
        $trainee_stmt->close();
    }
}

// If name is still empty, set a default
if (empty($name)) {
    $name = 'Unknown User';
}

// Define the checkPassFailStatus function at the top of the file
function checkPassFailStatus($situation, $value, $valtype = 0) {
    $status = ['pass' => false, 'message' => '', 'class' => 'secondary', 'text' => 'No Requirement'];
    
    switch ($situation) {
        case 1: // At least one
            $status['pass'] = ($value > 0);
            $status['message'] = $status['pass'] ? 
                "Passed: Has at least one entry" : 
                "Failed: Needs at least one entry";
            $status['class'] = $status['pass'] ? 'success' : 'danger';
            $status['text'] = $status['pass'] ? 'Pass' : 'Fail';
            break;
            
        case 2: // At least three
            $status['pass'] = ($value >= 3);
            $status['message'] = $status['pass'] ? 
                "Passed: Has 3 or more entries" : 
                "Failed: Needs at least 3 entries (current: $value)";
            $status['class'] = $status['pass'] ? 'success' : 'danger';
            $status['text'] = $status['pass'] ? 'Pass' : 'Fail';
            break;
            
        case 3: // At least five
            $status['pass'] = ($value >= 5);
            $status['message'] = $status['pass'] ? 
                "Passed: Has 5 or more entries" : 
                "Failed: Needs at least 5 entries (current: $value)";
            $status['class'] = $status['pass'] ? 'success' : 'danger';
            $status['text'] = $status['pass'] ? 'Pass' : 'Fail';
            break;
            
        case 4: // At least ten
            $status['pass'] = ($value >= 10);
            $status['message'] = $status['pass'] ? 
                "Passed: Has 10 or more entries" : 
                "Failed: Needs at least 10 entries (current: $value)";
            $status['class'] = $status['pass'] ? 'success' : 'danger';
            $status['text'] = $status['pass'] ? 'Pass' : 'Fail';
            break;
            
        case 5: // At least twenty hours
            if ($valtype == 2) { // Hours count
                $status['pass'] = ($value >= 20);
                $status['message'] = $status['pass'] ? 
                    "Passed: Has 20 or more hours" : 
                    "Failed: Needs at least 20 hours (current: " . number_format($value, 1) . ")";
                $status['class'] = $status['pass'] ? 'success' : 'danger';
                $status['text'] = $status['pass'] ? 'Pass' : 'Fail';
            } else {
                $status['message'] = "No pass/fail requirement for this value type";
                $status['class'] = 'secondary';
                $status['text'] = 'N/A';
            }
            break;
            
        default:
            $status['message'] = "No pass/fail requirement";
            $status['class'] = 'secondary';
            $status['text'] = 'No Requirement';
            break;
    }
    
    return $status;
}

// Helper function to get field type name
function getFieldTypeName($type) {
    switch ($type) {
        case 0: return 'Single Selection';
        case 1: return 'Multiple Selection';
        case 2: return 'Text';
        case 3: return 'Date';
        case 4: return 'Numeric (0.1)';
        case 5: return 'Numeric (Integer)';
        case 6: return 'Time';
        default: return 'Unknown';
    }
}

$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
// set up dates for stats panel js array data
$thisyear = date("Y");
$graph = isset($_GET['graph']) ? $_GET['graph'] : 'bar';
$start = isset($_GET['start']) ? $_GET['start'] : $thisyear;
$end = isset($_GET['end']) ? $_GET['end'] : $thisyear;

$start = isset($_GET['start']) ? $_GET['start'] : $thisyear;
$end = isset($_GET['end']) ? $_GET['end'] : $thisyear;
$datestart = $start.'0101';
$dateend = $end.'1231';
// create common array of colours for reports
$dispcolorarr = array();
$dispborderarr = array();
$datestart = 20200101; #TEMP DATES
$dateend = 20991231; #TEMP DATES
$valueyearstart = 20200101;
$valueyearend = 20991231;
$value59 = 59; # $stid for age in trainee log

// Define badges once at the top of the file
$badges = [
    1 => ['code' => 'LD', 'name' => 'Learning Disability'],
    2 => ['code' => 'CYP', 'name' => 'Children and Young People'],
    3 => ['code' => 'OA', 'name' => 'Older Adults'],
    4 => ['code' => 'WAA', 'name' => 'Working Age Adults']
];

// At the top of your file, set a default value for the tab
$defaultTab = 'bar'; // Set default to Bar plot
$selectedTab = isset($_GET['tab']) ? $_GET['tab'] : $defaultTab; // Use the default if 'tab' is not set

// Fetch table details for graph data sources
$tables_query = "SELECT tbid, tab_name FROM tabs_tbl WHERE isvis = 1 ORDER BY sort_order ASC";
$tables_result = $mysqli->query($tables_query);

?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $googleTitle ?></title>
    <meta name="description" content="<?php echo $googleDesc ?>">
    <meta name="keywords" content="<?php echo $googleKeywords ?>">
    <?php include 'incl/meta.php' ?>
    
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
        
        #allCombinationsContainer {
            background-color: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            margin-bottom: 30px;
            padding: 20px;
        }
        
        .combination-graph .card {
            height: 100%;
            border: 1px solid #dee2e6;
            transition: box-shadow 0.15s ease-in-out;
        }
        
        .combination-graph .card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
        }
        
        .combination-graph .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
        }
        
        .combination-graph .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
        }
    </style>

    <!-- Load Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
  </head>
  <?php
    if (login_check($mysqli) != false) {
      // logged in only!
    ?>
  <body>
    <?php include 'incl/banner.php' ?>
    <?php include 'incl/subnav.php' ?>
    <div class="container-fluid py-5" id="main">
      <div class="container">
        <div class="row">
          <div class="col-xs-12 col-sm-8 offset-sm-2 text-center">
            <?php
            if ($page_title != '') {
              echo "<h1>$page_title</h1>";
              //echo "<p>trainkey $trainkey</p>";
            }
            ?>
          </div>
        </div>
      </div>
      <div class="container">
        <div class="row my-3">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            <?php
              echo $page_txt2;
            ?>
            <?php
              echo $page_txt3;
            ?>
            <?php echo $page_txt4; ?>
          </div>
        </div>
      </div>
      
        <!-- Custom Graph Creation Section -->
        <div class="row mt-5">
          <div class="col-12">
            <div class="card mb-4" style="border-color: #025DB8;">
              <div class="card-header text-white" style="background-color: #025DB8;">
                <div class="card-title">Custom Data Visualization</div>
              </div>
              <div class="card-body">
                <form id="customGraphFilterForm">
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label class="col-form-label" for="customDataSource">Data Source</label>
                        <select class="form-control" id="customDataSource" name="data_source">
                          <option value="">Select a data source</option>
                          <?php
                          if ($tables_result && $tables_result->num_rows > 0) {
                            // Reset the result pointer for reuse
                            $tables_result->data_seek(0);
                            while ($table = $tables_result->fetch_assoc()) {
                              echo '<option value="' . $table['tbid'] . '">' . htmlspecialchars($table['tab_name']) . '</option>' . "\n";
                            }
                          } else {
                            echo '<option value="">No tables available</option>';
                          }
                          ?>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label class="col-form-label" for="customChartType">Chart Type</label>
                        <select class="form-control" id="customChartType" name="chart_type">
                          <option value="bar">Bar Chart</option>
                          <option value="line">Line Chart</option>
                          <option value="pie">Pie Chart</option>
                          <option value="doughnut">Doughnut Chart</option>
                          <option value="radar">Radar Chart</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label class="col-form-label" for="customTimeFrame">Time Frame</label>
                        <select class="form-control" id="customTimeFrame" name="time_frame">
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
                          <label class="col-form-label" for="customStartDate">Start Date</label>
                          <input type="date" class="form-control" id="customStartDate" name="start_date">
                        </div>
                        <div class="form-group">
                          <label class="col-form-label" for="customEndDate">End Date</label>
                          <input type="date" class="form-control" id="customEndDate" name="end_date">
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label class="col-form-label" for="customGraphMode">Graph Mode</label>
                        <select class="form-control" id="customGraphMode" name="graph_mode">
                          <option value="single">Single Graph</option>
                          <option value="selected_combinations">Selected Field Combinations</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-8" id="customFieldSelectionRow" style="display:none;">
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <label class="col-form-label" for="customFieldX">X-Axis Field</label>
                            <select class="form-control" id="customFieldX" name="field_x">
                              <option value="">Select field first</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <label class="col-form-label" for="customFieldY">Y-Axis Field</label>
                            <select class="form-control" id="customFieldY" name="field_y">
                              <option value="">Select field first</option>
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </form>
                
                <!-- Field Selection Section for Combinations -->
                <div id="combinationFieldSelectionSection" style="display:none;">
                  <hr>
                  <h5>Select Fields for Graph Combinations</h5>
                  <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Select multiple fields to create comprehensive visualizations of your data.
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label class="col-form-label"><strong>X-Axis Fields (select multiple)</strong></label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                          <div id="xAxisFieldsList">
                            <!-- Checkboxes will be populated here -->
                          </div>
                        </div>
                        <small class="form-text text-muted">Select one or more fields for X-axis</small>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label class="col-form-label"><strong>Y-Axis Fields (select multiple)</strong></label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                          <div id="yAxisFieldsList">
                            <!-- Checkboxes will be populated here -->
                          </div>
                        </div>
                        <small class="form-text text-muted">Select one or more fields for Y-axis</small>
                      </div>
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
                  </div>
                  
                  <div class="row">
                    <div class="col-12">
                      <button type="button" id="selectAllXFields" class="btn btn-outline-secondary btn-sm">Select All X</button>
                      <button type="button" id="clearAllXFields" class="btn btn-outline-secondary btn-sm ml-1">Clear All X</button>
                      <button type="button" id="selectAllYFields" class="btn btn-outline-secondary btn-sm ml-3">Select All Y</button>
                      <button type="button" id="clearAllYFields" class="btn btn-outline-secondary btn-sm ml-1">Clear All Y</button>
                    </div>
                  </div>
                </div>
                
                <div id="fieldSelectionPrompt" class="text-center py-4" style="display:none;">
                  <i class="fas fa-table fa-2x mb-3 text-muted"></i>
                  <p class="text-muted">Please select a data source first to load available fields</p>
                </div>
              </div>
              <div class="card-footer">
                <div class="float-right">
                  <button type="button" id="generateCustomGraphBtn" class="btn text-white" style="background-color: #025DB8; border-color: #025DB8;">Generate Custom Graph</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Multiple graphs container -->
        <div id="allGraphsContainer" style="display:none;">
          <div class="row">
            <div class="col-12">
              <div class="card mb-4" style="border-color: #025DB8;">
                <div class="card-header text-white" style="background-color: #025DB8;">
                  <div class="card-title">Field Combination Visualizations</div>
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
            </div>
          </div>
        </div>

        <!-- Custom Graph Display -->
        <div class="row">
          <div class="col-12">
            <div class="card mb-4" style="border-color: #025DB8;">
              <div class="card-header text-white" style="background-color: #025DB8;">
                <div class="card-title">Custom Graph Visualization</div>
              </div>
              <div class="card-body p-0">
                <div class="graph-container">
                  <div id="customGraphLoadingIndicator" class="graph-loading" style="display:none;">
                    <div class="spinner-border text-primary" role="status">
                      <span class="sr-only">Loading...</span>
                    </div>
                  </div>
                  <div id="customNoDataMessage" class="no-data-message">
                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                    <h4>Select data source and generate a custom graph</h4>
                    <p class="text-muted">Use the filters above to select your data and visualization options</p>
                  </div>
                  <canvas id="customGraphCanvas" style="display:none;"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Custom Data table section -->
        <div class="row">
          <div class="col-12">
            <div class="card mb-4" style="border-color: #025DB8;">
              <div class="card-header text-white" style="background-color: #025DB8;">
                <div class="card-title">Custom Data Table</div>
              </div>
              <div class="card-body">
                <div id="customDataTableContainer" style="display:none;">
                  <div class="table-responsive">
                    <table id="customGraphDataTable" class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th>Category</th>
                          <th>Value</th>
                          <th>Percentage</th>
                        </tr>
                      </thead>
                      <tbody id="customGraphDataTableBody">
                        <!-- Data will be populated here -->
                      </tbody>
                    </table>
                  </div>
                </div>
                <div id="customNoTableDataMessage" class="text-center py-5">
                  <p class="text-muted">Generate a custom graph to see the data table</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-5" id="reports">
        
<?php
// Function to check if the graph has data
function hasGraphData($ansarr) {
    return !empty($ansarr) && array_sum($ansarr) > 0; // Check if the array is not empty and has non-zero values
}
$tableset = $mysqli->prepare("SELECT rcid, colour FROM report_colour ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($rcid, $colour);
while ($tableset->fetch()){
    $hex = str_replace('#', '', $colour);
    $length = strlen($hex);
    $rgbr = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
    $rgbg = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
    $rgbb = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));
    array_push($dispcolorarr, "rgba($rgbr, $rgbg, $rgbb, 0.4)");
    array_push($dispborderarr, "rgba($rgbr, $rgbg, $rgbb, 1)");
}

?>
        </div>
        </div>
      </div>
    </div>
    <?php include 'incl/footer.php' ?>
    <?php
    if (login_check($mysqli) != false) {
      include 'incl/glossary.php';
    }
    ?>
    <?php include 'incl/jsfull.php' ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js" integrity="sha512-s+xg36jbIujB2S2VKfpGmlC3T5V2TF3lY48DX7u2r9XzGzgPsa6wTpOQA7J9iffvdeBN0q9tKzRxVxw1JviZPg==" crossorigin="anonymous"></script>

    <script type="text/javascript">
        // Global variables for custom graphs
        let customChart = null;
        
        $(document).ready(function() {
            // Handle time frame change to show/hide custom date range
            $('#customTimeFrame').on('change', function() {
                const selectedTimeFrame = $(this).val();
                
                if (selectedTimeFrame === 'custom') {
                    $('#customDateRange').show();
                    // Clear any previous date values
                    $('#customStartDate, #customEndDate').val('');
                } else {
                    $('#customDateRange').hide();
                    // Clear date values when switching to other time frames
                    $('#customStartDate, #customEndDate').val('');
                }
            });
            
            // Handle graph mode change to show/hide field selection
            $('#customGraphMode').on('change', function() {
                const selectedMode = $(this).val();
                
                if (selectedMode === 'single') {
                    $('#customFieldSelectionRow').show();
                    $('#combinationFieldSelectionSection').hide();
                    $('#generateCustomGraphBtn').text('Generate Custom Graph');
                } else if (selectedMode === 'selected_combinations') {
                    $('#customFieldSelectionRow').hide();
                    $('#combinationFieldSelectionSection').show();
                    $('#generateCustomGraphBtn').text('Generate Selected Combinations');
                    
                    // Update button state based on field selection
                    updateGenerateButtonState();
                }
            });
            
            // Handle data source change
            $('#customDataSource').on('change', function() {
                const tableId = $(this).val();
                
                if (tableId) {
                    // Show loading state
                    $('#customFieldX, #customFieldY').html('<option value="">Loading fields...</option>');
                    $('#customFieldSelectionRow').show();
                    
                    // Clear and hide combination field selection
                    $('#xAxisFieldsList, #yAxisFieldsList').empty();
                    $('#combinationFieldSelectionSection').hide();
                    $('#fieldSelectionPrompt').show();
                    
                    const ajaxData = {
                        table_id: tableId,
                        trainee_key: '<?php echo $trainkey; ?>'
                    };
                    
                    // Load fields for this table
                    $.ajax({
                        url: 'ajax/get_table_fields.php',
                        type: 'GET',
                        data: ajaxData,
                        dataType: 'json',
                        timeout: 10000, // 10 second timeout
                        cache: false,
                        success: function(response) {
                            console.log('Fields response:', response);
                            // Clear existing options
                            $('#customFieldX, #customFieldY').empty();
                            
                            // Add default option
                            $('#customFieldX').append('<option value="">Select X-Axis Field</option>');
                            $('#customFieldY').append('<option value="">Select Y-Axis Field</option>');
                            
                            let fields = [];
                            let fieldsAdded = 0;
                            
                            if (Array.isArray(response)) {
                                // For backward compatibility with older API
                                fields = response;
                                response.forEach(function(field) {
                                    const id = field.stid;
                                    const name = field.str;
                                    if (id && name) {
                                        $('#customFieldX').append(`<option value="${id}">${name}</option>`);
                                        $('#customFieldY').append(`<option value="${id}">${name}</option>`);
                                        fieldsAdded++;
                                    }
                                });
                            } else if (response.status === 'success' && response.fields) {
                                // New API format
                                fields = response.fields;
                                response.fields.forEach(function(field) {
                                    const id = field.id || field.stid;
                                    const name = field.name || field.str;
                                    if (id && name) {
                                        $('#customFieldX').append(`<option value="${id}">${name}</option>`);
                                        $('#customFieldY').append(`<option value="${id}">${name}</option>`);
                                        fieldsAdded++;
                                    }
                                });
                            } else {
                                alert('Error loading fields: ' + (response.message || 'Unknown error'));
                                return;
                            }
                            
                            if (fieldsAdded === 0) {
                                $('#customFieldX').append('<option value="">No fields available</option>');
                                $('#customFieldY').append('<option value="">No fields available</option>');
                            } else {
                                // Populate combination field selection checkboxes
                                populateFieldCheckboxes(fields);
                                $('#fieldSelectionPrompt').hide();
                                
                                // Show combination section if mode is selected
                                const currentMode = $('#customGraphMode').val();
                                if (currentMode === 'selected_combinations') {
                                    $('#combinationFieldSelectionSection').show();
                                }
                            }
                            
                            // Update field selection visibility based on current mode
                            const currentMode = $('#customGraphMode').val();
                            if (currentMode === 'single') {
                                $('#customFieldSelectionRow').show();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', xhr.responseText);
                            let errorMessage = 'Unknown error occurred';
                            
                            if (status === 'timeout') {
                                errorMessage = 'Request timed out. Please try again.';
                            } else if (xhr.status === 403) {
                                errorMessage = 'Access denied - you may not have permission to access this table';
                            } else if (xhr.status === 404) {
                                errorMessage = 'Endpoint not found - check server configuration';
                            } else if (xhr.status === 500) {
                                errorMessage = 'Server error - check server logs';
                            } else if (xhr.responseText) {
                                try {
                                    const errorResponse = JSON.parse(xhr.responseText);
                                    if (errorResponse && errorResponse.message) {
                                        errorMessage = errorResponse.message;
                                    }
                                } catch (e) {
                                    errorMessage = `HTTP Error ${xhr.status}: ${error}`;
                                }
                            } else {
                                errorMessage = `HTTP Error ${xhr.status}: ${error}`;
                            }
                            
                            alert('Error loading fields: ' + errorMessage);
                            
                            // Reset fields to error state
                            $('#customFieldX, #customFieldY').empty();
                            $('#customFieldX').append('<option value="">Error loading fields</option>');
                            $('#customFieldY').append('<option value="">Error loading fields</option>');
                        }
                    });
                } else {
                    // Hide and clear field selection
                    $('#customFieldSelectionRow').hide();
                    $('#combinationFieldSelectionSection').hide();
                    $('#customFieldX, #customFieldY').html('<option value="">Select field first</option>');
                    $('#fieldSelectionPrompt').show();
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
                    }
                }
                
                $('#combinationPreview').html(previewHtml);
                updateGenerateButtonState();
            }
            
            // Function to update generate button state
            function updateGenerateButtonState() {
                const mode = $('#customGraphMode').val();
                if (mode === 'selected_combinations') {
                    const hasXFields = $('.x-field-checkbox:checked').length > 0;
                    const hasYFields = $('.y-field-checkbox:checked').length > 0;
                    $('#generateCustomGraphBtn').prop('disabled', !(hasXFields && hasYFields));
                }
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
            
            // Generate custom graph button click
            $('#generateCustomGraphBtn').on('click', function() {
                const dataSource = $('#customDataSource').val();
                const timeFrame = $('#customTimeFrame').val();
                const chartType = $('#customChartType').val();
                const graphMode = $('#customGraphMode').val();
                
                if (!dataSource) {
                    alert('Please select a data source');
                    return;
                }
                
                // Validate custom date range if selected
                if (timeFrame === 'custom') {
                    const startDate = $('#customStartDate').val();
                    const endDate = $('#customEndDate').val();
                    
                    if (!startDate || !endDate) {
                        alert('Please select both start and end dates for custom date range');
                        return;
                    }
                    
                    if (new Date(startDate) > new Date(endDate)) {
                        alert('Start date cannot be after end date');
                        return;
                    }
                }
                
                if (graphMode === 'single') {
                    // Single graph mode - validate field selection
                    const fieldX = $('#customFieldX').val();
                    const fieldY = $('#customFieldY').val();
                    
                    if (!fieldX || !fieldY) {
                        alert('Please select both X and Y axis fields');
                        return;
                    }
                    
                    generateSingleGraph(dataSource, fieldX, fieldY, chartType, timeFrame);
                } else if (graphMode === 'selected_combinations') {
                    // Selected combinations mode
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
                    
                    generateSelectedCombinations(dataSource, selectedXFields, selectedYFields, chartType, timeFrame);
                }
            });
            
            function generateSingleGraph(dataSource, fieldX, fieldY, chartType, timeFrame) {
                // Hide combination container
                $('#allGraphsContainer').hide();
                
                // Show loading indicator
                $('#customGraphLoadingIndicator').show();
                $('#customNoDataMessage').hide();
                $('#customGraphCanvas').hide();
                $('#customDataTableContainer').hide();
                $('#customNoTableDataMessage').show();
                
                // Prepare data for AJAX request
                const requestData = {
                    table_id: dataSource,
                    field_x: fieldX,
                    field_y: fieldY,
                    chart_type: chartType,
                    time_frame: timeFrame,
                    trainee_key: '<?php echo $trainkey; ?>'
                };
                
                // Add custom date range if selected
                if (timeFrame === 'custom') {
                    requestData.start_date = $('#customStartDate').val();
                    requestData.end_date = $('#customEndDate').val();
                }
                
                // Fetch graph data
                $.ajax({
                    url: 'ajax/get_graph_data.php',
                    type: 'POST',
                    data: requestData,
                    dataType: 'json',
                    success: function(response) {
                        // Hide loading indicator
                        $('#customGraphLoadingIndicator').hide();
                        
                        if (response.status === 'success') {
                            // Show graph canvas
                            $('#customGraphCanvas').show();
                            
                            // Display data in the table
                            displayCustomDataTable(response.data);
                            
                            // Render the graph with additional info
                            renderCustomGraph(response.data, chartType, response.labels, response);
                        } else {
                            // Show no data message with error
                            $('#customNoDataMessage').show();
                            $('#customNoDataMessage').html(`
                                <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                                <h4>No data available</h4>
                                <p class="text-muted">${response.message}</p>
                                <small class="text-muted">Time period: ${timeFrame}</small>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Hide loading indicator and show error
                        $('#customGraphLoadingIndicator').hide();
                        $('#customNoDataMessage').show();
                        
                        let errorMessage = error;
                        // Try to parse error response if it's JSON
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse && errorResponse.message) {
                                errorMessage = errorResponse.message;
                            }
                        } catch (e) {
                            // If it's not valid JSON, use a generic message
                            if (xhr.responseText) {
                                errorMessage = 'Server error occurred';
                                console.error('AJAX Error:', xhr.responseText);
                            }
                        }
                        
                        $('#customNoDataMessage').html(`
                            <i class="fas fa-exclamation-circle fa-3x mb-3 text-danger"></i>
                            <h4>Error generating graph</h4>
                            <p class="text-muted">${errorMessage}</p>
                        `);
                    }
                });
            }
            
            function generateSelectedCombinations(dataSource, selectedXFields, selectedYFields, chartType, timeFrame) {
                // Hide single graph elements
                $('#customGraphCanvas').hide();
                $('#customNoDataMessage').hide();
                $('#customDataTableContainer').hide();
                
                // Show loading indicator and container
                $('#graphsLoadingIndicator').show();
                $('#allGraphsContainer').show();
                $('#allGraphsList').empty();
                
                // Generate field combinations and create graphs
                generateFieldCombinations(dataSource, selectedXFields, selectedYFields, chartType, timeFrame);
            }
            
            function generateFieldCombinations(dataSource, selectedXFields, selectedYFields, chartType, timeFrame) {
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
                generateGraphsSequentially(dataSource, graphsToGenerate, timeFrame, 0);
            }
            
            function generateGraphsSequentially(dataSource, graphConfigs, timeFrame, index) {
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
                                <div class="small text-muted">${config.chartType.charAt(0).toUpperCase() + config.chartType.slice(1)} chart for <?php echo htmlspecialchars($name); ?></div>
                            </div>
                            <div class="card-body">
                                <div class="graph-loading-mini" id="loading-${graphId}">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                                </div>
                                <canvas id="${graphId}" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                `);
                
                // Prepare request data
                    const requestData = {
                        table_id: dataSource,
                    field_x: config.fieldX,
                    field_y: config.fieldY,
                    chart_type: config.chartType,
                        time_frame: timeFrame,
                        trainee_key: '<?php echo $trainkey; ?>'
                    };
                    
                    // Add custom date range if selected
                    if (timeFrame === 'custom') {
                        requestData.start_date = $('#customStartDate').val();
                        requestData.end_date = $('#customEndDate').val();
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
                        } else {
                            // Show error message
                            $(`#${graphId}`).parent().html(`<div class="alert alert-warning">Unable to generate graph: ${response.message}</div>`);
                        }
                        
                        // Process the next graph
                        setTimeout(function() {
                            generateGraphsSequentially(dataSource, graphConfigs, timeFrame, index + 1);
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
                            generateGraphsSequentially(dataSource, graphConfigs, timeFrame, index + 1);
                        }, 100);
                    }
                });
            }
            
            function renderSmallGraph(canvasId, data, chartType, labels) {
                const ctx = document.getElementById(canvasId).getContext('2d');
                
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
                                    display: false
                                }
                            }
                        }
                    });
            }
            
            function renderCustomGraph(data, chartType, labels, response) {
                const ctx = document.getElementById('customGraphCanvas').getContext('2d');
                
                // Destroy previous chart if exists
                if (customChart) {
                    customChart.destroy();
                }
                
                // Create a more descriptive title with field type information
                let chartTitle = 'Custom Data Visualization for <?php echo htmlspecialchars($name); ?>';
                if (response && response.time_frame) {
                    const timeFrameText = {
                        'last30': 'Last 30 Days',
                        'last90': 'Last 90 Days', 
                        'last180': 'Last 180 Days',
                        'lastyear': 'Last Year',
                        'custom': 'Custom Date Range',
                        'all': 'All Time'
                    };
                    chartTitle += ` - ${timeFrameText[response.time_frame] || response.time_frame}`;
                    
                    // Add date range info for custom dates
                    if (response.time_frame === 'custom' && response.date_range) {
                        chartTitle += ` (${response.date_range})`;
                    }
                }
                
                // Add field type information to title
                if (response && response.field_type_info) {
                    const fieldInfo = response.field_type_info;
                    chartTitle += `\n${response.x_field} (${fieldInfo.x_field_type_name})`;
                }
                
                // Determine appropriate Y-axis label based on field type
                let yAxisLabel = 'Count';
                if (response && response.field_type_info) {
                    if (response.field_type_info.is_numeric) {
                        yAxisLabel = 'Average Value';
                    } else if (response.field_type_info.is_date) {
                        yAxisLabel = 'Frequency';
                    } else if (response.field_type_info.is_time) {
                        yAxisLabel = 'Frequency';
                    } else if (response.field_type_info.is_text) {
                        yAxisLabel = 'Count by Length';
                    }
                }
                
                // Format data for Chart.js
                const chartData = {
                    labels: labels || data.map(item => item.label),
                    datasets: [{
                        label: response && response.x_field ? response.x_field : 'Data Values',
                        data: data.map(item => item.value),
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(255, 206, 86, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(153, 102, 255, 0.6)',
                            'rgba(255, 159, 64, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 206, 86, 0.6)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)'
                        ],
                  borderWidth: 1
                    }]
                };
                
                // Create the chart
                customChart = new Chart(ctx, {
                    type: chartType,
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                 scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: yAxisLabel
                                }
                     }
                 },
                 plugins: {
                     legend: {
                                position: 'top',
                            },
                            title: {
                         display: true,
                                text: chartTitle
                            }
                        }
                    }
                });
            }
            
            function displayCustomDataTable(data) {
                const tableBody = $('#customGraphDataTableBody');
                tableBody.empty();
                
                if (data && data.length > 0) {
                    // Calculate total for percentage
                    const total = data.reduce((sum, item) => sum + parseFloat(item.value || 0), 0);
                    
                    // Add rows to table
                    data.forEach(function(item) {
                        const value = parseFloat(item.value || 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(2) + '%' : '0.00%';
                        
                        tableBody.append(`
                            <tr>
                                <td>${item.label}</td>
                                <td>${value}</td>
                                <td>${percentage}</td>
                            </tr>
                        `);
                    });
                    
                    // Add total row
                    tableBody.append(`
                        <tr class="table-info font-weight-bold">
                            <td><strong>Total</strong></td>
                            <td><strong>${total}</strong></td>
                            <td><strong>100.00%</strong></td>
                        </tr>
                    `);
                    
                    // Show data table
                    $('#customDataTableContainer').show();
                    $('#customNoTableDataMessage').hide();
                } else {
                    // No data available
                    $('#customDataTableContainer').hide();
                    $('#customNoTableDataMessage').show();
                }
            }
        });
    </script>

  </body>
  <?php
    // logged in only!
    }
    ?>
</html>