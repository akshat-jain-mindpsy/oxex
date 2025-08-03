<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Report Layouts";
$subtitle = "Reports";
if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
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
   $stmt = $mysqli->prepare("DELETE FROM report_data WHERE rmid = ?");
   $stmt->bind_param("i", $which); 
   $stmt->execute();
   $stmt->close();
   // now remove report
  $stmt = $mysqli->prepare("DELETE FROM report_manager WHERE rmid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();

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
  $stmt = $mysqli->prepare("SELECT sort_order FROM report_manager WHERE tbid = ? ORDER BY sort_order DESC LIMIT 1");
   $stmt->bind_param("i", $tbid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($sort_order);
   $stmt->fetch();
   $stmt->close();
   $sort_order = $sort_order + 1; # this is the next sort_order

  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO report_manager (report_title, report_notes, tbid, stid,  sort_order, valtype, situation, elldee, age, agefrom, ageto, who_by, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("ssiiiiiiiiisii", $report_title, $report_notes, $tbid, $stid, $sort_order, $valtype, $situation, $elldee, $age, $agefrom, $ageto, $usrkey, $today, $today);
  $insert_stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $rmid = $insert_stmt->insert_id;
  $insert_stmt->close();
  // create first field
  $insert_stmt = $mysqli->prepare("INSERT INTO report_data (rmid, valuea, valueb, valuetxt) VALUES (?, ?, ?, ?)");
  $insert_stmt->bind_param("isss", $rmid, $valuea, $valueb, $valuetxt);
  $insert_stmt->execute();
  $insert_stmt->close();
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
                           <tbody>
<?php
$sort_order = 0;
$tableset = $mysqli->prepare("SELECT rmid, tbid, report_title, date_modified, situation, stid, valtype, report_notes, elldee, age FROM report_manager ORDER BY tbid, sort_order");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($rmid, $tbid, $report_title, $date_modified, $situation, $stid, $valtype, $report_notes, $elldee, $age);
while ($tableset->fetch()){
   $date_modified = strtotime($date_modified);
   $tab_name = "Unknown"; # because they accidently deleted some table records!!
   // what field ($stid)
   $stmt = $mysqli->prepare("SELECT str FROM select_types WHERE stid = ?");
   $stmt->bind_param("i", $stid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($str);
   $stmt->fetch();
   $stmt->close();
   // which table
   $stmt = $mysqli->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
   $stmt->bind_param("i", $tbid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($tab_name);
   $stmt->fetch();
   $stmt->close();
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
   $dataset = $mysqli->prepare("SELECT valuetxt, valuea, valueb FROM report_data WHERE rmid = ? ");
   $dataset->bind_param("i", $rmid);
   $dataset->execute();
   $dataset->store_result();
   $dataset->bind_result($valuetxt, $valuea, $valueb);
   while ($dataset->fetch()){
      // is it a value or reference to field values
      if ($valtype == 0 || $valtype == 2) {
         $stmt = $mysqli->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
         $stmt->bind_param("i", $valuea);
         $stmt->execute();
         $stmt->store_result();
         $stmt->bind_result($select_val);
         $stmt->fetch();
         $stmt->close();
         echo "<small>$select_val</small><br>";
      } else {
         echo "<small>$valuea - $valueb</small><br>";
      } 
   }
   $dataset->close();
   ?>
 </td>
</tr>
 <?php
 }
$tableset->close();

$nextsort_order = $sort_order + 2;
?>
                           </tbody>
                        </table>
                     </div>
               </div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Report (click the report in above table to add more data)</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="report_title">Graph Title</label>
                              <input class="form-control" type="text" id="report_title" name="report_title" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="report_notes">Report subtitle (not on graph)</label>
                              <input class="form-control" type="text" id="report_notes" name="report_notes" >
                           </div>
                           <div class="row">
                                 <div class="col form-group">
                                     <label class="col-form-label" for="tbid">Table</label>
                                     <select class="custom-select custom-select mb-3" id="tbid" name="tbid" required>
                                       <option  value="">Select...</option>
                                       <?php
                                       $tableset = $mysqli->prepare("SELECT tbid, tab_name, sort_order FROM tabs_tbl ");
                                       $tableset->execute();
                                       $tableset->store_result();
                                       $tableset->bind_result($tbid, $tab_name, $sort_order);
                                       while ($tableset->fetch()){
                                       echo "<option value=\"$tbid\"";
                                       echo ">$tab_name</option>";
                                        }
                                       $tableset->close();
                                        ?>
                                    </select>
                                 </div>
                                 <div class="col form-group">
                                     <label class="col-form-label" for="stid">Field Name</label>
                                     <select class="custom-select custom-select mb-3" id="stid" name="stid" >
                                       <option  value="">Select...</option>
                                       <?php
                                       $tableset = $mysqli->prepare("SELECT stid, str FROM select_types ORDER BY  str");
                                       $tableset->execute();
                                       $tableset->store_result();
                                       $tableset->bind_result($stid, $str);
                                       while ($tableset->fetch()){
                                       echo "<option value=\"$stid\"";
                                       echo ">$str</option>";
                                        }
                                       $tableset->close();
                                        ?>
                                    </select>
                                 </div>
                                 
                             </div>

                                    
                             <div class="row">
                                 <div class="col form-group">
                                     <label class="col-form-label" for="valtype">Competency Type</label>
                                     <select class="custom-select custom-select mb-3" id="valtype" name="valtype">
                                       <option selected value="0">Exact Value 'A'</option>
                                       <option value="1" >Range between 'A' and 'B'</option>
                                       <option value="2" >Hours spent &gt; value 'B'</option>
                                     </select>
                                       <span class="form-text"><strong>Exact value</strong>; e.g. for "contact type", select from the values shown for Contact Type<br><strong>Range</strong>; e.g. for Age, entering 1 in 'Value A' and 9 in 'Value B' would record ages between 1 and 9. Do not enter values where not required. Value B must be larger than Value A.<br><strong>Hours Spent</strong> is greater than the value in 'Value B'.</span>
                                 </div>

                                <div class="col form-group">
                                  <label class="col-form-label" for="situation">Pass Requirement</label>
                                  <select class="custom-select custom-select mb-3" id="situation" name="situation">
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
                                    <span class="form-text"><strong>Info Only</strong> - no requirement to complete any<br><strong>Any value</strong> - must have at least one value across all options <strong>At least X values</strong> A value in at least X columns in graph <strong>Some of each</strong> - Must have at least one record for each option <strong>Good Range</strong> - more than half the options with at least one value <strong>&ge; X results</strong> - more than X results in total <strong>Hours</strong> Min hours set as ValueB</span>
                              </div>
                            </div>
                            
                            <div class="form-group">
                               <label class="col-form-label" for="valueAalt">Value A for Exact Value</label>
                               <select class="custom-select custom-select mb-3" id="valueAalt" name="valueAalt" required>
                                 <option  value="">Select...</option>
                                 <?php
                                 // The 'pid' value is the id relating to the answer required in table select_gen
                                 // The 'stid' value is which field 
                                 // 'select_types' says the name of the field
                                 echo "<option value=\"0\" selected";
                                 echo ">No Selection</option>";
                                 $tableset = $mysqli->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                 $tableset->execute();
                                 $tableset->store_result();
                                 $tableset->bind_result($pid, $stid, $select_val, $str);
                                 while ($tableset->fetch()){
                                 echo "<option value=\"$pid\"";
                                 echo ">$select_val ($str) ($pid)</option>";
                                 }
                                 $numrows = $tableset->num_rows;
                                 $tableset->close();                                   
                                  ?>
                              </select>
                           </div>
                           <div class="row">
                              <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="valuea">Value A for Range</label>
                                    <input class="form-control" type="text" id="valuea" name="valuea">
                                 </div>
                              </div>
                                <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="valueb">Value B for Range (or Hours)</label>
                                    <input class="form-control" type="text" id="valueb" name="valueb">
                                 </div>
                              </div>
                            </div>

                            <div class="row">
                              <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="elldee">Clinical Specialism Constraint</label>
                                    <select class="custom-select custom-select mb-3" id="elldee" name="elldee">
                                       <option value="0" selected>None</option>
                                       <option value="1">LD</option>
                                       <option value="2">CYP</option>
                                       <option value="3">OA</option>
                                       <option value="4">WAA</option>
                                     </select>
                                 </div>
                              </div>
                                <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="age">Age Constraint</label>
                                    <select class="custom-select custom-select mb-3" id="age" name="age">
                                       <option value="0" selected>None</option>
                                       <option value="1">Yes</option>
                                     </select>
                                 </div>
                              </div>
                            </div>
                            <div class="row">
                              <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="agefrom">Min age if constrained</label>
                                    <input class="form-control" type="number" id="agefrom" name="agefrom" step="1">
                                 </div>
                              </div>
                                <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="ageto">Max age if constrained</label>
                                    <input class="form-control" type="number" id="ageto" name="ageto" step="1">
                                 </div>
                              </div>
                            </div>
                             

                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
               </div>
            </div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script>
   $(document).ready(function() {
      $('#maintable').dataTable( {
        "pageLength": 100,
        "ordering": false
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