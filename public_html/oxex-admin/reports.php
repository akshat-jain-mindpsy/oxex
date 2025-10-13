<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Report Layouts";

setAdminVars(0); // Dashboard section
$subtitle = "Reports";
if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
</head>
<?php
// •• Note, this version uses report_manager db with field data in report_data. Older version used report_maker db with field data in report_maker_det •• //
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row from detail page
   // first remove report data
  $stmt = $supabase_pdo->prepare("DELETE FROM report_data WHERE rmid = ?");
  $stmt->execute([$which]);
   // now remove report
  $stmt = $supabase_pdo->prepare("DELETE FROM report_manager WHERE rmid = ? LIMIT 1");
  $stmt->execute([$which]);
  if ($stmt->rowCount() > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }

}

if ($newadmin == 'newadmin') {
  $report_title = isset($_POST['report_title']) ? $_POST['report_title'] : ''; #report
  $report_notes = isset($_POST['report_notes']) ? $_POST['report_notes'] : ''; #report
  $tbid = isset($_POST['tbid']) ? $_POST['tbid'] : 0; #report
  $stid = isset($_POST['stid']) ? $_POST['stid'] : 0; #report
  $valtype = isset($_POST['valtype']) ? $_POST['valtype'] : 0; #report
  $situation = isset($_POST['situation']) ? $_POST['situation'] : 0; #report

  $elldee = isset($_POST['elldee']) ? $_POST['elldee'] : 0; #report
  $age = isset($_POST['age']) ? $_POST['age'] : 0; #report
  $agefrom = isset($_POST['agefrom']) ? $_POST['agefrom'] : ''; #report
  $ageto = isset($_POST['ageto']) ? $_POST['ageto'] : ''; #report
  
  $valuea = isset($_POST['valuea']) ? $_POST['valuea'] : 0; # data
  $valueAalt = isset($_POST['valueAalt']) ? $_POST['valueAalt'] : 0; # data
  if ($valueAalt > 0) {
   $valuea = $valueAalt;
  }
  $valueb = isset($_POST['valueb']) ? $_POST['valueb'] : 0; # data
  $valuetxt = isset($_POST['valuetxt']) ? $_POST['valuetxt'] : ''; # data

  // find highest sort_order for this table
  $stmt = $supabase_pdo->prepare("SELECT sort_order FROM report_manager WHERE tbid = ? ORDER BY sort_order DESC LIMIT 1");
  $stmt->execute([$tbid]);
  $currentMaxSortOrder = (int)$stmt->fetchColumn();
  $sort_order = $currentMaxSortOrder + 1; # next sort_order

  // write new record
  $insert_stmt = $supabase_pdo->prepare("INSERT INTO report_manager (report_title, report_notes, tbid, stid, sort_order, valtype, situation, elldee, age, agefrom, ageto, who_by, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->execute([$report_title, $report_notes, $tbid, $stid, $sort_order, $valtype, $situation, $elldee, $age, $agefrom, $ageto, $usrkey, $today, $today]);
  $rmid = (int)$supabase_pdo->lastInsertId();
  // create first field
  $insert_stmt = $supabase_pdo->prepare("INSERT INTO report_data (rmid, valuea, valueb, valuetxt) VALUES (?, ?, ?, ?)");
  $insert_stmt->execute([$rmid, $valuea, $valueb, $valuetxt]);
}
?>
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
               <div class="content-title"><?php echo $pagetitle ?>  <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Graph Title (&amp; notes)</th>
                                 <th>Table</th>
                                 <th>Field &amp; Pass Requirement</th>
                                 <th>LD/CYP</th>
                                 <th>Age</th>
                                 <th>Report Data</th>
                              </tr>
                           </thead>
                           <tbody id="reportsBody">
<?php
$sort_order = 0;
// Initial page load: first page
$pageSize = 25;
$offset = 0;
// Total rows for pagination UI
$countStmt = $supabase_pdo->prepare("SELECT COUNT(*) FROM report_manager");
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $pageSize));

$tableset = $supabase_pdo->prepare("SELECT rmid, tbid, report_title, date_modified, situation, stid, valtype, report_notes, elldee, age FROM report_manager ORDER BY tbid, sort_order LIMIT $pageSize OFFSET $offset");
$tableset->execute();
$rows = $tableset->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r){
  $rmid = $r['rmid'];
  $tbid = $r['tbid'];
  $report_title = $r['report_title'];
  $date_modified = strtotime($r['date_modified']);
  $situation = (int)$r['situation'];
  $stid = $r['stid'];
  $valtype = (int)$r['valtype'];
  $report_notes = $r['report_notes'];
  $elldee = (int)$r['elldee'];
  $age = (int)$r['age'];
   $date_modified = strtotime($date_modified);
   $tab_name = "Unknown"; # because they accidently deleted some table records!!
   // what field ($stid)
  $stmt = $supabase_pdo->prepare("SELECT str FROM select_types WHERE stid = ?");
  $stmt->execute([$stid]);
  $str = $stmt->fetchColumn();
   // which table
  $stmt = $supabase_pdo->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
  $stmt->execute([$tbid]);
  $tab_name = $stmt->fetchColumn() ?: 'Unknown';
   $elldeetxt = "<span class=\"badge badge-secondary\">None</span>";
   if ($elldee == 1) {
      $elldeetxt = "<span class=\"badge badge-info\">LD</span>";
   }
   if ($elldee == 2) {
      $elldeetxt = "<span class=\"badge badge-green\">CYP</span>";
   }
   if ($elldee == 3) {
      $elldeetxt = "<span class=\"badge badge-green\">OA</span>";
   }
   if ($elldee == 4) {
      $elldeetxt = "<span class=\"badge badge-green\">WAA</span>";
   }
   if ($age == 0) {
      $age = "<span class=\"badge badge-secondary\">None</span>";
   } else {
      $age = "<span class=\"badge badge-success\">Age</span>";
   }

   // work out requirement text from $situation
   if ($situation == 0) {
      //$situation = 'Info only';
      $situationtxt = "<span class=\"badge badge-info\">Info only</span>";
   }
   if ($situation == 1) {
      //$situation = 'Any value';
      $situationtxt = "<span class=\"badge badge-primary\">Any value</span>";
   }
   if ($situation == 2) {
      //$situation = 'Some of each';
      $situationtxt = "<span class=\"badge badge-purple\">Some of each</span>";
   }
   if ($situation == 5) {
      //$situation = '> 50% of values';
      $situationtxt = "<span class=\"badge badge-green\">Good range (&gt; half)</span>";
   }
   if ($situation == 7) {
      //$situation = 'min hours set';
      $situationtxt = "<span class=\"badge badge-pink\">Hours</span>";
   }
   if ($situation == 3) {
      //$situation = 'some values in at least 2 columns';
      $situationtxt = "<span class=\"badge badge-primary\">At least 2 values</span>";
   }
   if ($situation == 10) {
      //$situation = 'some values in at least 3 columns';
      $situationtxt = "<span class=\"badge badge-pink\">At least 3 values</span>";
   }
   if ($situation == 12) {
      //$situation = 'some values in at least 4 columns';
      $situationtxt = "<span class=\"badge badge-pink\">At least 4 values</span>";
   }
   if ($situation == 8) {
      //$situation = 'some values in at least 6 columns';
      $situationtxt = "<span class=\"badge badge-primary\">At least 6 values</span>";
   }
   
   if ($situation == 11) {
      //$situation = '>=4 results in total across all columns';
      $situationtxt = "<span class=\"badge badge-primary\">At least 4 results</span>";
   }
   if ($situation == 6) {
      //$situation = '>=6 results in total across all columns';
      $situationtxt = "<span class=\"badge badge-pink\">&ge; 6 results</span>";
   }
   if ($situation == 9) {
      //$situation = '>=8 results in total across all columns';
      $situationtxt = "<span class=\"badge badge-primary\">At least 8 results</span>";
   }
   if ($report_title == '') {
      $report_title = '{AMEND}';
   }

?>
<tr>
 <td><a href="reportdetail.php?which=<?php echo $rmid ?>"><?php echo $report_title ?></a><br><?php echo $report_notes ?></td>
 <td><?php echo $tab_name ?></td>
 <td><?php echo $str ?><br><?php echo $situationtxt ?></td>
 <td><?php echo $elldeetxt ?></td>
 <td><?php echo $age ?></td>
 <td>
   <?php
  $dataset = $supabase_pdo->prepare("SELECT valuetxt, valuea, valueb FROM report_data WHERE rmid = ? ");
  $dataset->execute([$rmid]);
  $data_rows = $dataset->fetchAll(PDO::FETCH_ASSOC);
  foreach ($data_rows as $d){
     $valuetxt = $d['valuetxt'];
     $valuea = $d['valuea'];
     $valueb = $d['valueb'];
      // is it a value or reference to field values
      if ($valtype == 0 || $valtype == 2) {
        $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
        $stmt->execute([$valuea]);
        $select_val = $stmt->fetchColumn();
         echo "<small>$select_val</small><br>";
      } else {
         echo "<small>$valuea - $valueb</small><br>";
      } 
  }
   ?>
 </td>
</tr>
 <?php
}
// no explicit close needed for PDO
?>
                           </tbody>
                        </table>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                           <div>
                              <button id="prevPage" class="btn btn-outline-secondary btn-sm" disabled>Prev</button>
                              <button id="nextPage" class="btn btn-outline-secondary btn-sm" <?php echo ($totalPages > 1 ? '' : 'disabled'); ?>>Next</button>
                           </div>
                           <div>
                              <small>Page <span id="currentPage">1</span> of <span id="totalPages"><?php echo $totalPages ?></span> • <span id="totalRows"><?php echo $totalRows ?></span> rows</small>
                           </div>
                        </div>
</div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-12">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info shadow-sm">
                        <div class="card-header bg-info text-white">
                           <div class="card-title mb-0">
                              Add New Report
                              <small class="d-block text-white-50 mt-1">Click on any report in the table above to add more data to existing reports</small>
                           </div>
                        </div>

                        <div class="card-body p-4">
                           <!-- Basic Information Section -->
                           <div class="form-section mb-4">
                              <div class="section-header mb-3">
                                 <h5 class="mb-0 text-primary">Basic Information</h5>
                              </div>
                              <div class="row">
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="report_title">
                                          Graph Title <span class="text-danger">*</span>
                                       </label>
                                       <input class="form-control" type="text" id="report_title" name="report_title" required 
                                              placeholder="Enter the title for your report graph">
                                       <small class="form-text text-muted">This will be displayed as the main title on the generated graph</small>
                                    </div>
                                 </div>
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="report_notes">Report Subtitle</label>
                                       <input class="form-control" type="text" id="report_notes" name="report_notes"
                                              placeholder="Enter additional notes or subtitle">
                                       <small class="form-text text-muted">Additional information that won't appear on the graph</small>
                                    </div>
                                 </div>
                              </div>
                           </div>

                           <hr class="my-4">

                           <!-- Data Source Section -->
                           <div class="form-section mb-4">
                              <div class="section-header mb-3">
                                 <h5 class="mb-0 text-success">Data Source</h5>
                              </div>
                              <div class="row">
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="tbid">
                                          Data Table <span class="text-danger">*</span>
                                       </label>
                                       <select class="form-control" id="tbid" name="tbid" required>
                                          <option value="">Select a table...</option>
                                          <?php
                                          $tableset = $supabase_pdo->prepare("SELECT tbid, tab_name, sort_order FROM tabs_tbl ORDER BY sort_order, tab_name");
                                          $tableset->execute();
                                          while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
                                            $tbid = $row['tbid'];
                                            $tab_name = $row['tab_name'];
                                            echo "<option value=\"$tbid\">$tab_name</option>";
                                          }
                                           ?>
                                       </select>
                                       <small class="form-text text-muted">Choose the table that contains the data for this report</small>
                                    </div>
                                 </div>
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="stid">Field Name</label>
                                       <select class="form-control" id="stid" name="stid">
                                          <option value="">Select a field...</option>
                                          <?php
                                          $tableset = $supabase_pdo->prepare("SELECT stid, str FROM select_types ORDER BY str");
                                          $tableset->execute();
                                          while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
                                            $stid = $row['stid'];
                                            $str = $row['str'];
                                            echo "<option value=\"$stid\">$str</option>";
                                          }
                                           ?>
                                       </select>
                                       <small class="form-text text-muted">Select the specific field to analyze in this report</small>
                                    </div>
                                 </div>
                              </div>
                           </div>

                           <hr class="my-4">

                           <!-- Competency Configuration Section -->
                           <div class="form-section mb-4">
                              <div class="section-header mb-3">
                                 <h5 class="mb-0 text-warning">Competency Configuration</h5>
                              </div>
                              <div class="row">
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="valtype">Competency Type</label>
                                       <select class="form-control" id="valtype" name="valtype">
                                          <option selected value="0">Exact Value 'A'</option>
                                          <option value="1">Range between 'A' and 'B'</option>
                                          <option value="2">Hours spent &gt; value 'B'</option>
                                       </select>
                                       <div class="mt-2">
                                          <small class="text-muted">
                                             <strong>Exact value:</strong> Select from predefined values (e.g., contact type)<br>
                                             <strong>Range:</strong> Enter min/max values (e.g., age 1-9)<br>
                                             <strong>Hours Spent:</strong> Greater than specified value
                                          </small>
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="situation">Pass Requirement</label>
                                       <select class="form-control" id="situation" name="situation">
                                          <option value="0">Info Only</option>
                                          <option value="1" selected>Any value</option>
                                          <option value="2">Some of each</option>
                                          <option value="5">Good Range (&gt; half)</option>
                                          <option value="7">Hours</option>
                                          <option value="11">&ge; 4 results</option>
                                          <option value="6">&ge; 6 results</option>
                                          <option value="9">&ge; 8 results</option>
                                          <option value="3">At least 2 values</option>
                                          <option value="10">At least 3 values</option>
                                          <option value="12">At least 4 values</option>
                                          <option value="8">At least 6 values</option>
                                       </select>
                                       <div class="mt-2">
                                          <small class="text-muted">
                                             <strong>Info Only:</strong> No completion requirement<br>
                                             <strong>Any value:</strong> At least one value required<br>
                                             <strong>At least X values:</strong> Value in X columns minimum<br>
                                             <strong>Some of each:</strong> At least one record per option
                                          </small>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                           
                           <hr class="my-4">

                           <!-- Value Configuration Section -->
                           <div class="form-section mb-4">
                              <div class="section-header mb-3">
                                 <h5 class="mb-0 text-info">Value Configuration</h5>
                              </div>
                              <div class="form-group mb-3">
                                 <label class="form-label font-weight-bold" for="valueAalt">Value A for Exact Value</label>
                                 <select class="form-control" id="valueAalt" name="valueAalt">
                                    <option value="">Select a value...</option>
                                    <?php
                                    echo "<option value=\"0\" selected>No Selection</option>";
                                    $tableset = $supabase_pdo->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                    $tableset->execute();
                                    while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
                                      $pid = $row['pid'];
                                      $stid = $row['stid'];
                                      $select_val = $row['select_val'];
                                      $str = $row['str'];
                                      echo "<option value=\"$pid\">$select_val ($str) ($pid)</option>";
                                    }                                   
                                     ?>
                                 </select>
                                 <small class="form-text text-muted">Choose the exact value for this competency type</small>
                              </div>
                              <div class="row">
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="valuea">Value A for Range</label>
                                       <input class="form-control" type="number" id="valuea" name="valuea" 
                                              placeholder="Enter minimum value">
                                       <small class="form-text text-muted">Minimum value for range-based competencies</small>
                                    </div>
                                 </div>
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="valueb">Value B for Range (or Hours)</label>
                                       <input class="form-control" type="number" id="valueb" name="valueb" 
                                              placeholder="Enter maximum value or hours">
                                       <small class="form-text text-muted">Maximum value for range or minimum hours required</small>
                                    </div>
                                 </div>
                              </div>
                           </div>

                           <hr class="my-4">

                           <!-- Constraints Section -->
                           <div class="form-section mb-4">
                              <div class="section-header mb-3">
                                 <h5 class="mb-0 text-secondary">Constraints</h5>
                              </div>
                              <div class="row">
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="elldee">Clinical Specialism</label>
                                       <select class="form-control" id="elldee" name="elldee">
                                          <option value="0" selected>None</option>
                                          <option value="1">LD (Learning Disabilities)</option>
                                          <option value="2">CYP (Children & Young People)</option>
                                          <option value="3">OA (Older Adults)</option>
                                          <option value="4">WAA (Working Age Adults)</option>
                                        </select>
                                        <small class="form-text text-muted">Restrict report to specific clinical specialism</small>
                                    </div>
                                 </div>
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="age">Age Constraint</label>
                                       <select class="form-control" id="age" name="age">
                                          <option value="0" selected>None</option>
                                          <option value="1">Yes - Apply age restrictions</option>
                                        </select>
                                        <small class="form-text text-muted">Enable age-based filtering for this report</small>
                                    </div>
                                 </div>
                              </div>
                              <div class="row" id="ageConstraints" style="display: none;">
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="agefrom">Minimum Age</label>
                                       <input class="form-control" type="number" id="agefrom" name="agefrom" step="1" min="0" 
                                              placeholder="Enter minimum age">
                                    </div>
                                 </div>
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="ageto">Maximum Age</label>
                                       <input class="form-control" type="number" id="ageto" name="ageto" step="1" min="0" 
                                              placeholder="Enter maximum age">
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="card-footer bg-light border-top">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="d-flex justify-content-between align-items-center">
                              <small class="text-muted">
                                 All fields marked with <span class="text-danger">*</span> are required
                              </small>
                              <button class="btn btn-info btn-lg px-4" type="submit">
                                 Add Report
                              </button>
                           </div>
                        </div>
                     </div><!-- END card-->
                  </form>
               </div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script>
   $(document).ready(function() {
     // Disable DataTables paging; using custom async pagination
     $('#maintable').dataTable( {
       "paging": false,
       "ordering": false,
       "info": false,
       "searching": false
     });

     var currentPage = 1;
     var totalPages = parseInt($('#totalPages').text(), 10) || 1;

     function loadPage(page) {
       $.ajax({
         url: 'ajax/get_report_rows.php',
         type: 'GET',
         dataType: 'html',
         data: { page: page, page_size: 25 },
         success: function(html) {
           $('#reportsBody').html(html);
           currentPage = page;
           $('#currentPage').text(currentPage);
           $('#prevPage').prop('disabled', currentPage <= 1);
           $('#nextPage').prop('disabled', currentPage >= totalPages);
         },
         error: function(xhr) {
           console.error('Failed to load page', xhr.responseText);
         }
       });
     }

     $('#prevPage').on('click', function() {
       if (currentPage > 1) loadPage(currentPage - 1);
     });
     $('#nextPage').on('click', function() {
       if (currentPage < totalPages) loadPage(currentPage + 1);
     });

     // Age constraint toggle functionality
     $('#age').on('change', function() {
       var ageValue = $(this).val();
       if (ageValue == '1') {
         $('#ageConstraints').slideDown(300);
         $('#agefrom, #ageto').prop('required', true);
       } else {
         $('#ageConstraints').slideUp(300);
         $('#agefrom, #ageto').prop('required', false).val('');
       }
     });

     // Form validation enhancement
     $('form').on('submit', function(e) {
       var isValid = true;
       var errorMessages = [];

       // Check required fields
       $('input[required], select[required]').each(function() {
         if (!$(this).val()) {
           $(this).addClass('is-invalid');
           isValid = false;
         } else {
           $(this).removeClass('is-invalid');
         }
       });

       // Check age constraints if age is enabled
       if ($('#age').val() == '1') {
         var ageFrom = parseInt($('#agefrom').val());
         var ageTo = parseInt($('#ageto').val());
         
         if (isNaN(ageFrom) || isNaN(ageTo)) {
           errorMessages.push('Please enter valid age constraints');
           $('#agefrom, #ageto').addClass('is-invalid');
           isValid = false;
         } else if (ageFrom >= ageTo) {
           errorMessages.push('Minimum age must be less than maximum age');
           $('#agefrom, #ageto').addClass('is-invalid');
           isValid = false;
         }
       }

       // Check value ranges for competency type
       var valType = $('#valtype').val();
       if (valType == '1') { // Range type
         var valueA = parseFloat($('#valuea').val());
         var valueB = parseFloat($('#valueb').val());
         
         if (!isNaN(valueA) && !isNaN(valueB) && valueA >= valueB) {
           errorMessages.push('Value A must be less than Value B for range type');
           $('#valuea, #valueb').addClass('is-invalid');
           isValid = false;
         }
       }

       if (!isValid) {
         e.preventDefault();
         var alertHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
           '<strong>Please fix the following errors:</strong><ul class="mb-0 mt-2">';
         errorMessages.forEach(function(msg) {
           alertHtml += '<li>' + msg + '</li>';
         });
         alertHtml += '</ul><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
         
         // Remove existing alerts
         $('.alert-danger').remove();
         // Add new alert at top of form
         $('.card-body').prepend(alertHtml);
         
         // Scroll to top of form
         $('html, body').animate({
           scrollTop: $('#newform').offset().top - 100
         }, 500);
       }
     });

     // Remove validation classes on input
     $('input, select').on('input change', function() {
       $(this).removeClass('is-invalid');
     });

     // Smooth scroll to form when clicking "Add New" button
     $('a[href="#newform"]').on('click', function(e) {
       e.preventDefault();
       $('html, body').animate({
         scrollTop: $('#newform').offset().top - 100
       }, 500);
     });
   });
   </script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>