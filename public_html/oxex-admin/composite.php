<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Composite Searches";

$subtitle = "Composite Search";
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
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row from detail page
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdo) {
    $stmt = $pdo->prepare("DELETE FROM compositesearch WHERE csid = ? LIMIT 1");
    $stmt->execute([$which]);
    if ($stmt->rowCount() > 0) {
      // show message when deleting, not refreshing
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
    }
  }
}


if ($newadmin == 'newadmin') {
  $csname = isset($_POST['csname']) ? $_POST['csname'] : '';
  $pid1 = isset($_POST['pid1']) ? $_POST['pid1'] : 0;
  $pid2 = isset($_POST['pid2']) ? $_POST['pid2'] : 0;
  $pid3 = isset($_POST['pid3']) ? $_POST['pid3'] : 0;
  $target = isset($_POST['target']) ? $_POST['target'] : 0;
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if (!$pdo) {
    echo "<p>Error: No database connection available.</p>";
    exit();
  }
  
  // find $stid1 and $stid
  $stmt = $pdo->prepare("SELECT stid FROM select_gen WHERE pid = ?");
  $stmt->execute([$pid1]);
  $stid1 = $stmt->fetchColumn();
  
  $stmt = $pdo->prepare("SELECT stid FROM select_gen WHERE pid = ?");
  $stmt->execute([$pid2]);
  $stid2 = $stmt->fetchColumn();
  
  $stmt = $pdo->prepare("SELECT stid FROM select_gen WHERE pid = ?");
  $stmt->execute([$pid3]);
  $stid3 = $stmt->fetchColumn();
  
  // write new record
  $insert_stmt = $pdo->prepare("INSERT INTO compositesearch (csname, stid1, pid1, stid2, pid2, stid3, pid3, sort_order, target, who_by, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->execute([$csname, $stid1, $pid1, $stid2, $pid2, $stid3, $pid3, $sort_order, $target, $adminname, date("Y-m-d H:i:s"), date("Y-m-d H:i:s")]);
  //printf("[%d] %s\n", $pdo->errorInfo());
  $newid = $pdo->lastInsertId();
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
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
  $tableset = $pdo->prepare("SELECT csid, csname, sort_order FROM compositesearch ");
  $tableset->execute();
  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
    $csid = $row['csid'];
    $csname = $row['csname'];
    $sort_order = $row['sort_order'];
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
}
?>
                           </tbody>
                        </table>
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
                                 $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
                                 if ($pdo) {
                                   $tableset = $pdo->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                   $tableset->execute();
                                   while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
                                     $pid = $row['pid'];
                                     $stid = $row['stid'];
                                     $select_val = $row['select_val'];
                                     $str = $row['str'];
                                     echo "<option value=\"$pid\"";
                                     echo ">$select_val ($str) ($pid)</option>";
                                   }
                                 }                                   
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
                                 $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
                                 if ($pdo) {
                                   $tableset = $pdo->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                   $tableset->execute();
                                   while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
                                     $pid = $row['pid'];
                                     $stid = $row['stid'];
                                     $select_val = $row['select_val'];
                                     $str = $row['str'];
                                     echo "<option value=\"$pid\"";
                                     echo ">$select_val ($str) ($pid)</option>";
                                   }
                                 }                                   
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
                                 $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
                                 if ($pdo) {
                                   $tableset = $pdo->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                   $tableset->execute();
                                   while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
                                     $pid = $row['pid'];
                                     $stid = $row['stid'];
                                     $select_val = $row['select_val'];
                                     $str = $row['str'];
                                     echo "<option value=\"$pid\"";
                                     echo ">$select_val ($str) ($pid)</option>";
                                   }
                                 }                                   
                                  ?>
                              </select>
</div>
                            <div class="row">
                              <div class="col form-group">
                                 <label class="col-form-label" for="target">Target Hours (decimal or integer)</label>
                                 <input class="form-control" type="number" id="target" name="target" step="any" min="0">
                              </div>
                              <div class="col form-group">
                                 <label class="col-form-label" for="sort_order">Sort order</label>
                                 <input class="form-control" type="number" id="$value0 = 0; // Default value for sort_order
$subtitle = "sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
</div>
                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
</div><!-- END card-->
                  </form>
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