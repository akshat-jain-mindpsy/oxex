<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Report Competency";
$subtitle = "Reports";
//$listurl = "reports.php"; # will be specific, see later
$listname = "this Report";
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
$edit = isset($_GET['edit']) ? $_GET['edit'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';

if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
   // deleting a competency
   $rfid = isset($_GET['rfid']) ? $_GET['rfid'] : '';
   $stmt = $mysqli->prepare("DELETE FROM report_maker_det WHERE rfid = ? LIMIT 1");
   $stmt->bind_param("i", $rfid); 
   $stmt->execute();
   $stmt->close();
}
if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {
   // amend competency
   $which = isset($_POST['which']) ? $_POST['which'] : 0;
      $which = (int)$which;
   $stid = isset($_POST['stid']) ? $_POST['stid'] : 0;
   $minval = isset($_POST['minval']) ? $_POST['minval'] : 0;
   $valtype = isset($_POST['valtype']) ? $_POST['valtype'] : 0;
   
   $stmt = $mysqli->prepare("UPDATE report_maker_det SET stid = ?, minval = ?, valtype = ? WHERE rfid = ?"); 
   $stmt->bind_param("iiii", $stid, $minval, $valtype, $which);
   $stmt->execute();
   $stmt->close();
   
   // find the required competency record
   $stmt = $mysqli->prepare("SELECT rid, stid, minval, valtype FROM report_maker_det WHERE rfid = ?");
   $stmt->bind_param("i", $which);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($rid, $thisstid, $thisminval, $thisvaltype);
   $stmt->fetch();
   $stmt->close();

 // find the linked report record
   $stmt = $mysqli->prepare("SELECT report_title, who_by, date_added, date_modified FROM report_maker WHERE rid = ?");
   $stmt->bind_param("i", $rid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($report_title, $who_by, $date_added, $date_modified);
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
$listurl = "reportdetail.php?which=$rid";
   
}

if ($edit == "edit" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $rfid = isset($_GET['rfid']) ? $_GET['rfid'] : 0;
  $rfid = (int)$rfid;
  
   // find the required competency record
   $stmt = $mysqli->prepare("SELECT rid, stid, minval, valtype FROM report_maker_det WHERE rfid = ?");
   $stmt->bind_param("i", $rfid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($rid, $thisstid, $thisminval, $thisvaltype);
   $stmt->fetch();
   $stmt->close();

 // find the linked report record
   $stmt = $mysqli->prepare("SELECT report_title, who_by, date_added, date_modified FROM report_maker WHERE rid = ?");
   $stmt->bind_param("i", $rid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($report_title, $who_by, $date_added, $date_modified);
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
$listurl = "reportdetail.php?which=$rid";
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small></div>
            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend competency in <?php echo $report_title ?></div>
                        </div>
                        <div class="card-body">
                            
                           <div class="form-group">
                              <label class="col-form-label" for="report_title">Report Name</label>
                              <input class="form-control" type="text" id="report_title" name="report_title" value="<?php echo $report_title ?>" disabled>
                           </div>
                           <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="col-form-label" for="stid">Competency</label>
                                        <select class="custom-select custom-select mb-3" id="stid" name="stid" required>
                                          <option  value="">Select...</option>
                                          <?php
// loop through tabs_tbl then fields within tables:-
// 'tab_fields' links all the fields to a table
// 'select_types' says the name of the field 
$tabset = $mysqli->prepare("SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name ");
$tabset->execute();
$tabset->store_result();
$tabset->bind_result($tbid, $tab_name);
while ($tabset->fetch()){
      $tableset = $mysqli->prepare("SELECT select_types.stid, select_types.str FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order");
      $tableset->bind_param("i", $tbid);
      $tableset->execute();
      $tableset->store_result();
      $tableset->bind_result($stid, $str);
      while ($tableset->fetch()){
         echo "<option value=\"$stid\"";
         if ($thisstid == $stid) {
            echo "selected";
         }
         echo ">$str ($tab_name)</option>";
      }
      $numrows = $tableset->num_rows;
      $tableset->close();                                         
}
$tabset->close();                                       
                                           ?>
                                       </select>
                                    </div>
                                </div>
                             </div>
                             <div class="form-group">
                               <label class="col-form-label" for="valtype">Competency Type</label>
                               <select class="custom-select custom-select mb-3" id="valtype" name="valtype">
                                 <option <?if ($thisvaltype == 0) echo 'selected' ?> value="0">Minimum Value</option>
                                 <option <?if ($thisvaltype == 2) echo 'selected' ?> value="2">Exact value</option>
                                 <option <?if ($thisvaltype == 1) echo 'selected' ?> value="1">Minimum number of results</option>
                               </select>
                               <span class="form-text">Choose a minimum value, e.g. for age, '7' would mean a trainee has interviewed someone of 7 years or older. An Exact Value e.g. age 7 means exactly 7 years. Minimum number of results; '7' would mean at least 7 people of any age interviewed.</span>
                           </div>
                             <div class="form-group">
                              <label class="col-form-label" for="minval">Competency Value</label>
                              <input class="form-control" type="number" id="minval" name="minval" value="<?php echo $thisminval ?>">
                           </div>

                        </div>
                        
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $rfid ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
               </div>



               <div class="col-xl-4">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-purple">
                           <div class="card-title">Possible Values for this Competency</div>
                        </div>

                        <div class="card-body">
                           <?php
                           // find the competency field ecord
   $stmt = $mysqli->prepare("SELECT str, single FROM select_types WHERE stid = ?");
   $stmt->bind_param("i", $thisstid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($str, $single);
   $stmt->fetch();
   $stmt->close();
   $listtype = 'Single Selection Field: Use the associated number as the Competency Value for an Exact Value';
   if ($single == 1) {
      $listtype = 'Multiple Selection: Use the associated number as the Competency Value for an Exact Value';
   }
   if ($single == 2) {
      $listtype = 'Text: not possible to use this field';
   }
   if ($single == 3) {
      $listtype = 'Date: not possible to use this field';
   }
   if ($single == 6) {
      $listtype = 'Time: not possible to use this field';
   }
   if ($single == 4) {
      $listtype = 'Numeric (step 0.1): Enter a Minimum or Exact value as a decimal';
   }
   if ($single == 5) {
      $listtype = 'Numeric (step integer): Enter a Minimum or Exact value as an integer';
   }
   echo "<p><strong>$str</strong></p>";
   echo "<p>$listtype</p>";
   if ($single < 3) {
      echo "<p>";
      // loop through fields and values
      $tableset = $mysqli->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ? ");
      $tableset->bind_param("i", $thisstid);
      $tableset->execute();
      $tableset->store_result();
      $tableset->bind_result($pid, $select_val);
      while ($tableset->fetch()){
         echo "($pid) $select_val<br>";
      }
      $tableset->close();
      echo "</p>";
   }

   ?>
                           
                             

                        </div>
                        <div class="card-footer">
                 
                        </div>
                     </div><!-- END card-->


                
               </div>



            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                     <!-- START card-->
                     <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                           <div class="card-title">Delete this competency</div>
                        </div>
                        <div class="card-footer">
                           <div class="float-right">
                            <a href="reportcompetency.php?del=del&amp;rfid=<?php echo $rfid ?>" class="btn btn-labeled btn-danger" role="button"><span class="btn-label"><i class="fa fa-times"></i></span>Delete now!</a>
                          </div>
                        </div>
                     </div><!-- END card-->
               </div>
            </div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>