<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Report Layouts";
$subtitle = "Reports";
$listurl = "reports.php";
$listname = "Reports";
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
   <style>
      .draggable {cursor: grabbing;}
   </style>
</head>
<?php 
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
if ($del == "delfield" && ($admintype == 'AT' || $admintype == 'DV')) {
   // deleting a Field Name from this Table (from a yellow bar)
   $stid = isset($_GET['stid']) ? $_GET['stid'] : '';
   $stmt = $mysqli->prepare("DELETE FROM tab_fields WHERE stid = ? AND tbid = ? LIMIT 1");
   $stmt->bind_param("ii", $stid, $which); 
   $stmt->execute();
   $stmt->close();
}
if ($del == "deldata" && ($admintype == 'AT' || $admintype == 'DV')) {
   // deleting a data value by index rdid
   $rdid = isset($_GET['data']) ? $_GET['data'] : 0;
   $stmt = $mysqli->prepare("DELETE FROM report_data WHERE rdid = ? LIMIT 1");
   $stmt->bind_param("i", $rdid); 
   $stmt->execute();
   $stmt->close();
}

if ($newadmin == "newfield" && ($admintype == 'AT' || $admintype == 'DV')) {
   // add new report data to table
   $which = isset($_POST['which']) ? $_POST['which'] : 0;
      $which = (int)$which;
   $valuea = isset($_POST['valuea']) ? $_POST['valuea'] : 0;
   $valueb = isset($_POST['valueb']) ? $_POST['valueb'] : ''; # blank if not used
   // find the report valtype
   $stmt = $mysqli->prepare("SELECT situation FROM report_manager WHERE rmid = ?");
   $stmt->bind_param("i", $which);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($situation);
   $stmt->fetch();
   $stmt->close();
   //echo "situation $situation";
   if ($situation == 7) {
      // for this situation, we use valueB as the No of hours to check
      // so need to add it to all report data
      // find the first report_data
      $stmt = $mysqli->prepare("SELECT valueb FROM report_data WHERE rmid = ? ORDER BY rdid DESC LIMIT 1");
      $stmt->bind_param("i", $which);
      $stmt->execute();
      $stmt->store_result();
      $stmt->bind_result($valueb);
      $stmt->fetch();
      $stmt->close();
   }
   
   $insert_stmt = $mysqli->prepare("INSERT INTO report_data (rmid, valuea, valueb) VALUES (?, ?, ?)");
   $insert_stmt->bind_param("iss", $which, $valuea, $valueb);
   $insert_stmt->execute();
   //   printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
   $insert_stmt->close();
   
}

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $report_title = isset($_POST['report_title']) ? $_POST['report_title'] : ''; #report
  $report_notes = isset($_POST['report_notes']) ? $_POST['report_notes'] : ''; #report
  $tbid = isset($_POST['tbid']) ? $_POST['tbid'] : 0; #report
  $stid = isset($_POST['stid']) ? $_POST['stid'] : 0; #report
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0; #report
  $valtype = isset($_POST['valtype']) ? $_POST['valtype'] : 0; #report
  $situation = isset($_POST['situation']) ? $_POST['situation'] : 0; #report

  $elldee = isset($_POST['elldee']) ? $_POST['elldee'] : 0; #report
  $age = isset($_POST['age']) ? $_POST['age'] : 0; #report
  $agefrom = isset($_POST['agefrom']) ? $_POST['agefrom'] : ''; #report
  $ageto = isset($_POST['ageto']) ? $_POST['ageto'] : ''; #report
  
  // Update record
  $stmt = $mysqli->prepare("UPDATE report_manager SET tbid = ?, stid = ?, valtype = ?, report_title = ?, report_notes = ?, who_by = ?, date_modified = ?, situation = ?, elldee = ?, age = ?, agefrom = ?, ageto = ? WHERE rmid = ? "); 
  $stmt->bind_param("iiisssiiiiiii", $tbid, $stid, $valtype, $report_title, $report_notes, $usrkey, $today, $situation, $elldee, $age, $agefrom, $ageto, $which);
  $stmt->execute();
  $stmt->close();
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT report_title, report_notes, tbid, stid, valtype, sort_order, who_by, date_added, date_modified, situation, elldee, age, agefrom, ageto FROM report_manager WHERE rmid = ?");
$stmt->bind_param("i", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($report_title, $report_notes, $thistbid, $thisstid, $valtype, $sort_order, $who_by, $date_added, $date_modified, $situation, $elldee, $age, $agefrom, $ageto);
$stmt->fetch();
$stmt->close();
$date_modified = strtotime($date_modified);
$date_added = strtotime($date_added);
 // who changed last?
  $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->bind_param("s", $who_by);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($who_by);
   $stmt->fetch();
   $stmt->close();
// whatever the record name is
  $changename = "$report_title";
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small></div>
            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?></div>
                        </div>
                        <div class="card-body">
                            <p>Created on: <?php echo date("D jS M Y", $date_added) ?> and last modified on <?php echo date("D jS M Y", $date_modified) ?> by <?php echo $who_by ?></p>
                           <div class="form-group">
                              <label class="col-form-label" for="report_title">Graph Title</label>
                              <input class="form-control" type="text" id="report_title" name="report_title" value="<?php echo $report_title ?>" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="report_notes">Report subtitle (not on graph)</label>
                              <input class="form-control" type="text" id="report_notes" name="report_notes" value="<?php echo $report_notes ?>" >
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
                                    if ($thistbid == $tbid) {
                                       echo ' selected';
                                    }
                                    echo ">$tab_name</option>";
                                     }
                                    $tableset->close();
                                     ?>
                                 </select>
                              </div>
                              <div class="col form-group">
                                  <label class="col-form-label" for="stid">Field Name</label>
                                  <select class="custom-select custom-select mb-3" id="stid" name="stid" required>
                                    <option  value="">Select...</option>
                                    <?php

                                    $tableset = $mysqli->prepare("SELECT stid, str FROM select_types ORDER BY  str");
                                    $tableset->execute();
                                    $tableset->store_result();
                                    $tableset->bind_result($stid, $str);
                                    while ($tableset->fetch()){
                                    echo "<option value=\"$stid\"";
                                    if ($thisstid == $stid) {
                                       echo ' selected';
                                    }
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
                                       <option <?php if ($valtype == 0) echo 'selected' ?> value="0">Exact Value 'A'</option>
                                       <option <?php if ($valtype == 1) echo 'selected' ?> value="1" >Range between 'A' and 'B'</option>
                                       <option <?php if ($valtype == 2) echo 'selected' ?> value="2" >Hours spent &gt; value 'B'</option>
                                     </select>
                                       <span class="form-text"><strong>Exact value</strong> are a selected  Field Value<br><strong>Range</strong> will be a pair of numbers</span>
                                 </div>
                                 <div class="col form-group">
                                  <label class="col-form-label" for="situation">Pass Requirement</label>
                                  <select class="custom-select custom-select mb-3" id="situation" name="situation">
                                    <option value="0" <?php if ($situation == 0) echo 'selected' ?> >Info Only</option>
                                    <option value="1" <?php if ($situation == 1) echo 'selected' ?> >Any value</option>
                                    <option value="2" <?php if ($situation == 2) echo 'selected' ?> >Some of each</option>
                                    <option value="5" <?php if ($situation == 5) echo 'selected' ?> >Good Range (&gt; half)</option>
                                    <option value="7" <?php if ($situation == 7) echo 'selected' ?> >Hours</option>

                                    <option value="11" <?php if ($situation == 11) echo 'selected' ?> >&ge; 4 results</option>
                                    <option value="6" <?php if ($situation == 6) echo 'selected' ?> >&ge; 6 results</option>
                                    <option value="9" <?php if ($situation == 9) echo 'selected' ?> >&ge; 8 results</option>

                                    <option value="3" <?php if ($situation == 3) echo 'selected' ?> >At least 2 values</option>
                                    <option value="10" <?php if ($situation == 10) echo 'selected' ?> >At least 3 values</option>
                                    <option value="12" <?php if ($situation == 12) echo 'selected' ?> >At least 4 values</option>
                                    <option value="8" <?php if ($situation == 8) echo 'selected' ?> >At least 6 values</option>
                                    
                                  </select>



                                    <span class="form-text"><strong>Info Only</strong> - no requirement to complete any<br><strong>Any value</strong> - must have at least one value across all options <strong>At least X values </strong>A value in at least X columns in graph <strong>Some of each</strong> - Must have at least one record for each option <strong>Good Range</strong> - more than half the options with at least one value <strong>&ge; X results</strong> - more than X results in total <strong>Hours</strong> Min hours set as ValueB</span>
                              </div>
                            
                            </div>
                            <div class="row">
                              <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="elldee">Clinical Specialism Constraint</label>
                                    <select class="custom-select custom-select mb-3" id="elldee" name="elldee">
                                       <option value="0" <?php if ($elldee == 0) echo 'selected' ?> >None</option>
                                       <option value="1" <?php if ($elldee == 1) echo 'selected' ?>>LD</option>
                                       <option value="2" <?php if ($elldee == 2) echo 'selected' ?>>CYP</option>
                                       <option value="3" <?php if ($elldee == 3) echo 'selected' ?>>OA</option>
                                       <option value="4" <?php if ($elldee == 4) echo 'selected' ?>>WAA</option>
                                     </select>
                                 </div>
                              </div>
                                <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="age">Age Constraint</label>
                                    <select class="custom-select custom-select mb-3" id="age" name="age">
                                       <option value="0" <?php if ($age == 0) echo 'selected' ?> >None</option>
                                       <option value="1" <?php if ($age == 1) echo 'selected' ?>>Yes</option>
                                     </select>
                                 </div>
                              </div>
                            </div>
                            <div class="row">
                              <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="agefrom">Min age if constrained</label>
                                    <input class="form-control" type="number" id="agefrom" name="agefrom" value="<?php echo $agefrom ?>" step="1">
                                 </div>
                              </div>
                                <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="ageto">Max age if constrained</label>
                                    <input class="form-control" type="number" id="ageto" name="ageto" value="<?php echo $ageto ?>" step="1">
                                 </div>
                              </div>
                            </div>
                            
                            
                            
                        </div>
                        
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
                  <div class="card border-info mt-3">
                  <div class="card-header bg-info">
                     <div class="card-title">Drag to sort reports for the selected Table <small>(can do this on any report title)</small></div>
                  </div>
                  <div class="card-body">
                     <ul class="list-unstyled" id="post_list">
<?php
// Table tbid = $rmid
// find all reports for this table

$tableset = $mysqli->prepare("SELECT rmid, sort_order, report_title FROM report_manager WHERE tbid = ? ORDER BY sort_order ASC");
$tableset->bind_param("i", $thistbid);
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($rmid, $sort_order, $report_title);
while ($tableset->fetch()){
   // show draggable bar with an edit button on the right
   echo "<li class=\"bg-warning rounded my-1 pl-1 py-1 draggable\" id=\"$rmid\">$report_title <span class=\"bg-green float-right px-3 mr-1 rounded\"><a href=\"reportdetail.php?which=$rmid\"> edit </a></span></li>";
}
$tableset->close();

?>
                     </ul>
                  </div>
                  <div class="card-footer">
                  </div>
                </div>
                  
               </div>

               <div class="col-xl-4">
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card 
                         1. Add new Data
                         2. Show existing Data
                     -->
                     <div class="card border-info">
                        <div class="card-header bg-purple">
                           <div class="card-title">Add New Report data</div>
                        </div>

                        <div class="card-body">
                           <div class="row">
                             <div class="col">
                              <?php
                              if ($valtype == 1) {
                                 echo "<p>Enter the next new low value in 'Value A' and the next new higher value in 'Value B'.</p>";
                                 $labelA = "Value A";
                                 $labelB = "Value B";
                              } else {
                                 echo "<p>Enter the next new value in 'Value A'.</p>";
                              }
                              ?>
                              <?php
                              if ($valtype == 0) {
                                 // single 'equals' value
                                 $labelA = "Value A";
                                 $labelB = "";
                              }
                              if ($valtype == 0) {
                                 // range
                                 $labelA = "Value A for Range";
                                 $labelB = "Value B for Range";
                              }
                              if ($valtype == 2) {
                                 // single 'equals' value
                                 $labelA = "Value A";
                                 $labelB = "Hours";
                              }
                              ?>
                              
                                 <div class="row">
                                    <?php
                                    if ($valtype == 0 || $valtype == 2) {
                                       // select a field value for valueA
                                    ?>
                                    <div class="col">
                                       <div class="form-group">
                                        <label class="col-form-label" for="valuea">Value A for Exact Value</label>
                                        <select class="custom-select custom-select mb-3" id="valuea" name="valuea" >
                                          <option  value="">Select...</option>
                                          <?php
                                          // The 'pid' value is the id relating to the answer required in table select_gen
                                          // The 'stid' value is which field 
                                          // 'select_types' says the name of the field
                                          $tableset = $mysqli->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = ? AND select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                          $tableset->bind_param("i", $thisstid);
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
                                    </div>
                                    <?php
                                    }
                                    ?>
                                    <?php
                                    if ($valtype == 1) {
                                       // a range of numeric values
                                    ?>
                                    <div class="col">
                                       <div class="form-group">
                                          <label class="col-form-label" for="valuea"><?php echo $labelA ?></label>
                                          <input class="form-control" type="text" id="valuea" name="valuea">
                                       </div>
                                    </div>
                                      <div class="col">
                                       <div class="form-group">
                                          <label class="col-form-label" for="valueb"><?php echo $labelB ?></label>
                                          <input class="form-control" type="text" id="valueb" name="valueb">
                                       </div>
                                    </div>
                                    <?php
                                    }
                                    ?>
                                  </div>
                                </div>
                             </div>
                             

                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newfield">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-purple" type="submit">Add</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
                  <div class="card border-info">
                        <div class="card-header bg-purple">
                           <div class="card-title">Existing Report data</div>
                        </div>

                        <div class="card-body">
                           <div class="row">
                             <div class="col">
                              <?php
                              $tableset = $mysqli->prepare("SELECT rdid, valuea, valueb FROM report_data WHERE rmid = ?");
                              $tableset->bind_param("i", $which);
                              $tableset->execute();
                              $tableset->store_result();
                              $tableset->bind_result($rdid, $valuea, $valueb);
                              while ($tableset->fetch()){
                                 if ($valtype == 1) {
                                 echo "<p class=\"mb-2\">$valuea - $valueb</p>";
                                 } else {
                                    // will be a field value
                                    $stmt = $mysqli->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                                    $stmt->bind_param("i", $valuea);
                                    $stmt->execute();
                                    $stmt->store_result();
                                    $stmt->bind_result($select_val);
                                    $stmt->fetch();
                                    $stmt->close();

                                    echo "<p class=\"mb-2\">$select_val <span class=\"bg-danger float-right px-3 mr-1 rounded\"><a href=\"reportdetail.php?which=$rmid&amp;data=$rdid&amp;del=deldata\"> del </a></span></p>";
                                 }
                              }
                              $numab = $tableset->num_rows;
                              $tableset->close();
                              if ($situation == 7) {
                                 echo "<p><small>$valueb hrs</small></p>";
                              }
                              if ($numab == 0) {
                                 echo "<p class=\"mb-2\">No data entered</p>";
                              }
                              ?>
                              
                                 
                                </div>
                             </div>
                             

                        </div>
                     </div>



                
               </div>



            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                     <!-- START card-->
                     <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                           <div class="card-title">Delete <?php echo $changename ?></div>
                        </div>
                        <div class="card-footer">
                           <div class="float-right">
                            <a href="<?php echo $listurl ?>?del=del&amp;which=<?php echo $which ?>" class="btn btn-labeled btn-danger" role="button" onclick="return confirm('Are you sure you want to delete this record and all associated data?')"><span class="btn-label"><i class="fa fa-times"></i></span>Delete now!</a>
                          </div>
                        </div>
                     </div>--><!-- END card-->
               </div>
            </div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js" integrity="sha256-eTyxS0rkjpLEo16uXTS0uVCS4815lc40K2iVpWDvdSY=" crossorigin="anonymous"></script>
<script type="text/javascript">
   /* sort table field list and update table */
   /* oxex admin tabledetail.php */
 $( "#post_list" ).sortable({
     delay: 150,
     opacity: 0.6, 
      cursor: 'move',
     stop: function() {
         var selectedData = new Array();
         $('#post_list>li').each(function() {
             selectedData.push($(this).attr("id"));
         });
         updateOrder(selectedData);
     }
 });

 function updateOrder(data) {
     $.ajax({
         url:"reportdetailsort.php",
         type:'post',
         data:{position:data},
     })
 }
</script>
<script>
   $(document).ready(function() {
      $('#maintable').dataTable( {
        "pageLength": 10
      });
     $('.summernote').summernote({
        tabsize: 2,
        height: 160,
        spellCheck: true,
        dialogsInBody: true,
        cleaner:{
              action: 'both', // both|button|paste 'button' only cleans via toolbar button, 'paste' only clean when pasting content, both does both options.
              newline: '<br>', // Summernote's default is to use '<p><br></p>'
              notStyle: 'position:absolute;top:0;left:0;right:0', // Position of Notification
              icon: '<i class="note-icon">[Your Button]</i>',
              keepHtml: true, // Remove all Html formats
              keepOnlyTags: ['<p>', '<br>', '<ul>', '<li>', '<b>', '<strong>','<i>', '<a>'], // If keepHtml is true, remove all tags except these
              keepClasses: false, // Remove Classes
              badTags: ['style', 'script', 'applet', 'embed', 'noframes', 'noscript', 'html'], // Remove full tags with contents
              badAttributes: ['style', 'start'], // Remove attributes from remaining tags
              limitChars: false, // 0/false|# 0/false disables option
              limitDisplay: 'both', // text|html|both
              limitStop: false // true/false
        },
        toolbar: [
           ['style', ['style']],
           ['font', ['bold', 'underline', 'superscript', 'subscript']],
           ['color', ['color']],
           ['para', ['ul', 'ol', 'paragraph']],
           ['table', ['table']],
           ['insert', ['link', 'picture', 'video']],
           ['view', ['codeview', 'help']],
         ]
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