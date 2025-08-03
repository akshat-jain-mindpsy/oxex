<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Composite Searches";
$subtitle = "Composite Search";
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
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row from detail page
  $stmt = $mysqli->prepare("DELETE FROM compositesearch WHERE csid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();

}


if ($newadmin == 'newadmin') {
  $csname = isset($_POST['csname']) ? $_POST['csname'] : '';
  $pid1 = isset($_POST['pid1']) ? $_POST['pid1'] : 0;
  $pid2 = isset($_POST['pid2']) ? $_POST['pid2'] : 0;
  $pid3 = isset($_POST['pid3']) ? $_POST['pid3'] : 0;
  $target = isset($_POST['target']) ? $_POST['target'] : 0;
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  // find $stid1 and $stid
   $stmt = $mysqli->prepare("SELECT stid FROM select_gen WHERE pid = ?");
   $stmt->bind_param("i", $pid1);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($stid1);
   $stmt->fetch();
   $stmt->close();
   $stmt = $mysqli->prepare("SELECT stid FROM select_gen WHERE pid = ?");
   $stmt->bind_param("i", $pid2);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($stid2);
   $stmt->fetch();
   $stmt->close();
   $stmt = $mysqli->prepare("SELECT stid FROM select_gen WHERE pid = ?");
   $stmt->bind_param("i", $pid3);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($stid3);
   $stmt->fetch();
   $stmt->close();
  
  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO compositesearch (csname, stid1, pid1, stid2, pid2, stid3, pid3, sort_order, target, who_by, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("siiiiiiissii", $csname, $stid1, $pid1, $stid2, $pid2, $stid3, $pid3, $sort_order, $target, $usrkey, $today, $today);
  $insert_stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $newid = $insert_stmt->insert_id;
  $insert_stmt->close();
  // target could be decimal
  
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
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Search</th>
                                 <th>Sort Order</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$tableset = $mysqli->prepare("SELECT csid, csname, sort_order FROM compositesearch ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($csid, $csname, $sort_order);
while ($tableset->fetch()){
   if ($sort_order == 0) {
      $sorttxt = '<div class="badge badge-danger">Not displayed</div>';
   } else {
      $sorttxt = $sort_order;
   }

?>
<tr>
   <td><a href="compositedetail.php?which=<?php echo $csid ?>"><?php echo $csname ?></a></td>
   <td><?php echo $sorttxt ?></td>
</tr>
 <?php
 }
$numrows = $tableset->num_rows;
$tableset->close();
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
                           <div class="card-title">Add new Composite Search</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="csname">Search Name</label>
                              <input class="form-control" type="text" id="csname" name="csname" required>
                           </div>
                           <span class="form-text">2nd and 3rd Search Values are Optional</span>
                           <div class="row">
                              <div class="col form-group">
                                  <label class="col-form-label" for="pid1">Search Value</label>
                                  <select class="custom-select custom-select mb-3" id="pid1" name="pid1" required>
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
                              <div class="col form-group">
                                 <label class="col-form-label" for="pid2">Criteria Value  1 <small>(Opt)</small></label>
                                 <select class="custom-select custom-select mb-3" id="pid2" name="pid2">
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
                              <div class="col form-group">
                                 <label class="col-form-label" for="pid3">Criteria Value 2 <small>(Opt)</small></label>
                                 <select class="custom-select custom-select mb-3" id="pid3" name="pid3">
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
                            </div>
                            <div class="row">
                              <div class="col form-group">
                                 <label class="col-form-label" for="target">Target Hours (decimal or integer)</label>
                                 <input class="form-control" type="number" id="target" name="target" step="any" min="0">
                              </div>
                              <div class="col form-group">
                                 <label class="col-form-label" for="sort_order">Sort order</label>
                                 <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
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
        "pageLength": 50
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