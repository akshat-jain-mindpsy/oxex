<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Report Layouts";

setAdminVars(0); // Dashboard section
$subtitle = "Reports";
$listurl = "reports.php";
$listname = "Reports";
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
  $stmt = $supabase_pdo->prepare("DELETE FROM tab_fields WHERE stid = ? AND tbid = ? LIMIT 1");
  $stmt->execute([$stid, $which]);
}
if ($del == "deldata" && ($admintype == 'AT' || $admintype == 'DV')) {
  // deleting a data value by index rdid
  $rdid = isset($_GET['data']) ? $_GET['data'] : 0;
  $stmt = $supabase_pdo->prepare("DELETE FROM report_data WHERE rdid = ? LIMIT 1");
  $stmt->execute([$rdid]);
}

if ($newadmin == "newfield" && ($admintype == 'AT' || $admintype == 'DV')) {
   // add new report data to table
   $which = isset($_POST['which']) ? $_POST['which'] : 0;
      $which = (int)$which;
   $valuea = isset($_POST['valuea']) ? $_POST['valuea'] : 0;
   $valueb = isset($_POST['valueb']) ? $_POST['valueb'] : ''; # blank if not used
   // find the report valtype
  $stmt = $supabase_pdo->prepare("SELECT situation FROM report_manager WHERE rmid = ?");
  $stmt->execute([$which]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $situation = $row ? (int)$row['situation'] : 0;
   //echo "situation $situation";
   if ($situation == 7) {
      // for this situation, we use valueB as the No of hours to check
      // so need to add it to all report data
      // find the first report_data
      $stmt = $supabase_pdo->prepare("SELECT valueb FROM report_data WHERE rmid = ? ORDER BY rdid DESC LIMIT 1");
      $stmt->execute([$which]);
      $valueb = $stmt->fetchColumn();
   }
   
  $insert_stmt = $supabase_pdo->prepare("INSERT INTO report_data (rmid, valuea, valueb) VALUES (?, ?, ?)");
  $insert_stmt->execute([$which, $valuea, $valueb]);
   
}

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $report_title = isset($_POST['report_title']) ? $_POST['report_title'] : ''; #report
  $report_notes = isset($_POST['report_notes']) ? $_POST['report_notes'] : ''; #report
  $tbid = isset($_POST['tbid']) ? $_POST['tbid'] : 0; #report
  $stid = isset($_POST['stid']) ? $_POST['stid'] : 0; #report
  $$value0 = 0; // Default value for sort_order
$subtitle = "_POST['sort_order'] : 0; #report
  $valtype = isset($_POST['valtype']) ? $_POST['valtype'] : 0; #report
  $situation = isset($_POST['situation']) ? $_POST['situation'] : 0; #report

  $elldee = isset($_POST['elldee']) ? $_POST['elldee'] : 0; #report
  $age = isset($_POST['age']) ? $_POST['age'] : 0; #report
  $agefrom = isset($_POST['agefrom']) ? $_POST['agefrom'] : ''; #report
  $ageto = isset($_POST['ageto']) ? $_POST['ageto'] : ''; #report
  
  // Update record
  $stmt = $supabase_pdo->prepare("UPDATE report_manager SET tbid = ?, stid = ?, valtype = ?, report_title = ?, report_notes = ?, who_by = ?, date_modified = ?, situation = ?, elldee = ?, age = ?, agefrom = ?, ageto = ? WHERE rmid = ? "); 
  $stmt->execute([$tbid, $stid, $valtype, $report_title, $report_notes, $usrkey, $today, $situation, $elldee, $age, $agefrom, $ageto, $which]);
}
?>
<?php
  // find the required record
$stmt = $supabase_pdo->prepare("SELECT report_title, report_notes, tbid, stid, valtype, sort_order, who_by, date_added, date_modified, situation, elldee, age, agefrom, ageto FROM report_manager WHERE rmid = ?");
$stmt->execute([$which]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$report_title = $row ? $row['report_title'] : '';
$report_notes = $row ? $row['report_notes'] : '';
$thistbid = $row ? (int)$row['tbid'] : 0;
$thisstid = $row ? (int)$row['stid'] : 0;
$valtype = $row ? (int)$row['valtype'] : 0;
$$value0 = 0; // Default value for sort_order
$subtitle = "row['sort_order'] : 0;
$who_by = $row ? $row['who_by'] : '';
$date_added = $row ? $row['date_added'] : '';
$date_modified = $row ? $row['date_modified'] : '';
$situation = $row ? (int)$row['situation'] : 0;
$elldee = $row ? (int)$row['elldee'] : 0;
$age = $row ? (int)$row['age'] : 0;
$agefrom = $row ? $row['agefrom'] : '';
$ageto = $row ? $row['ageto'] : '';
$date_modified = strtotime($date_modified);
$date_added = strtotime($date_added);
 // who changed last?
 $stmt = $supabase_pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
  $stmt->execute([$who_by]);
  $who_by = $stmt->fetchColumn();
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

setAdminVars(0); // Dashboard section            </div>

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
                                    $tableset = $supabase_pdo->prepare("SELECT tbid, tab_name, sort_order FROM tabs_tbl ");
                                    $tableset->execute();
                                    $tabs = $tableset->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($tabs as $trow) {
                                      $tbid = $trow['tbid'];
                                      $tab_name = $trow['tab_name'];
                                      echo "<option value=\"$tbid\"";
                                      if ($thistbid == $tbid) {
                                        echo ' selected';
                                      }
                                      echo ">$tab_name</option>";
                                    }
                                     ?>
                                 </select>
                              </div>
                              <div class="col form-group">
                                  <label class="col-form-label" for="stid">Field Name</label>
                                  <select class="custom-select custom-select mb-3" id="stid" name="stid" required>
                                    <option  value="">Select...</option>
                                    <?php

                                    $tableset = $supabase_pdo->prepare("SELECT stid, str FROM select_types ORDER BY  str");
                                    $tableset->execute();
                                    $fields = $tableset->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($fields as $frow) {
                                      $stid = $frow['stid'];
                                      $str = $frow['str'];
                                      echo "<option value=\"$stid\"";
                                      if ($thisstid == $stid) {
                                        echo ' selected';
                                      }
                                      echo ">$str</option>";
                                    }
                                     ?>
                                 </select>
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
                                <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="age">Age Constraint</label>
                                    <select class="custom-select custom-select mb-3" id="age" name="age">
                                       <option value="0" <?php if ($age == 0) echo 'selected' ?> >None</option>
                                       <option value="1" <?php if ($age == 1) echo 'selected' ?>>Yes</option>
                                     </select>
</div>
                            </div>
                            <div class="row">
                              <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="agefrom">Min age if constrained</label>
                                    <input class="form-control" type="number" id="agefrom" name="agefrom" value="<?php echo $agefrom ?>" step="1">
</div>
                                <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="ageto">Max age if constrained</label>
                                    <input class="form-control" type="number" id="ageto" name="ageto" value="<?php echo $ageto ?>" step="1">
</div>
</div>
                        
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
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

$tableset = $supabase_pdo->prepare("SELECT rmid, sort_order, report_title FROM report_manager WHERE tbid = ? ORDER BY sort_order ASC");
$tableset->execute([$thistbid]);
$rows = $tableset->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $rowd) {
   $rmid = $rowd['rmid'];
   $$value0 = 0; // Default value for sort_order
$subtitle = "rowd['sort_order'];
   $report_title = $rowd['report_title'];
   // show draggable bar with an edit button on the right
   echo "<li class=\"bg-warning rounded my-1 pl-1 py-1 draggable\" id=\"$rmid\">$report_title <span class=\"bg-green float-right px-3 mr-1 rounded\"><a href=\"reportdetail.php?which=$rmid\"> edit </a></span></li>";
}

?>
                     </ul>
                  </div>
                  <div class="card-footer">
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
                                          $tableset = $supabase_pdo->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = ? AND select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                          $tableset->execute([$thisstid]);
                                          while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
                                            $pid = $row['pid'];
                                            $stid = $row['stid'];
                                            $select_val = $row['select_val'];
                                            $str = $row['str'];
                                            echo "<option value=\"$pid\"";
                                            echo ">$select_val ($str) ($pid)</option>";
                                          }                                   
                                           ?>
                                       </select>
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
                                      <div class="col">
                                       <div class="form-group">
                                          <label class="col-form-label" for="valueb"><?php echo $labelB ?></label>
                                          <input class="form-control" type="text" id="valueb" name="valueb">
</div>
                                    <?php
                                    }
                                    ?>
</div>
</div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newfield">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-purple" type="submit">Add</button></div>
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
                              $tableset = $supabase_pdo->prepare("SELECT rdid, valuea, valueb FROM report_data WHERE rmid = ?");
                              $tableset->execute([$which]);
                              $data_rows = $tableset->fetchAll(PDO::FETCH_ASSOC);
                              foreach ($data_rows as $drow){
                                 $rdid = $drow['rdid'];
                                 $valuea = $drow['valuea'];
                                 $valueb = $drow['valueb'];
                                 if ($valtype == 1) {
                                 echo "<p class=\"mb-2\">$valuea - $valueb</p>";
                                 } else {
                                    // will be a field value
                                    $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                                    $stmt->execute([$valuea]);
                                    $select_val = $stmt->fetchColumn();

                                    echo "<p class=\"mb-2\">$select_val <span class=\"bg-danger float-right px-3 mr-1 rounded\"><a href=\"reportdetail.php?which=$rmid&amp;data=$rdid&amp;del=deldata\"> del </a></span></p>";
                                 }
                              }
                              $numab = count($data_rows);
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
                     </div>--><!-- END card-->
</div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js" integrity="sha256-eTyxS0rkjpLEo16uXTS0uVCS4815lc40K2iVpWDvdSY=" crossorigin="anonymous"></script>
<script type="text/javascript">
   /* sort table field list and update table */
   /* oxex admin sheetdetail.php */
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