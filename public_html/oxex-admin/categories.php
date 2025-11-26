<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Categories";

// Set variables needed by adminjs.php
setAdminVars(3); // Tables section
$subtitle = "Categories";
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
$select_type = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
   // delete all field links
   $stmt = $pdo->prepare("DELETE FROM tab_fields WHERE stid = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();
  // delete row from detail page
  $stmt = $pdo->prepare("DELETE FROM select_types WHERE stid = ? LIMIT 1");
  $stmt->execute([$which]); 
  if ($stmt->rowCount() > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->closeCursor();

}

if ($newadmin == 'newadmin') {
  $str = isset($_POST['str']) ? trim($_POST['str']) : '';
  $single = isset($_POST['single']) ? (int)$_POST['single'] : 0;
  $musthave = isset($_POST['musthave']) ? (int)$_POST['musthave'] : 0;
  $wouldlike = isset($_POST['wouldlike']) ? (int)$_POST['wouldlike'] : 0;

  // Determine the next available STID since Supabase is not auto-incrementing this column
  $stid_stmt = $pdo->prepare("SELECT COALESCE(MAX(stid) + 1, 1) AS next_stid FROM select_types");
  $stid_stmt->execute();
  $next_stid = (int)$stid_stmt->fetchColumn();
  $stid_stmt->closeCursor();
  
  // Determine the next sort order
  $sort_order_stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order) + 1, 1) AS next_sort_order FROM select_types");
  $sort_order_stmt->execute();
  $next_sort_order = (int)$sort_order_stmt->fetchColumn();
  $sort_order_stmt->closeCursor();

  // Write new record, explicitly persisting the computed STID
  $insert_stmt = $pdo->prepare("INSERT INTO select_types (stid, str, single, musthave, wouldlike, sort_order) VALUES (?, ?, ?, ?, ?, ?) RETURNING stid");
  $insert_stmt->execute([$next_stid, $str, $single, $musthave, $wouldlike, $next_sort_order]);
  
  // Get the newly created stid from RETURNING clause
  $new_record = $insert_stmt->fetch(PDO::FETCH_ASSOC);
  $newid = $new_record['stid'] ?? null;
  $insert_stmt->closeCursor();
}
function getSimilarExistingValues($type, $value) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT select_val FROM select_gen 
                               WHERE select_type = ? 
                               ORDER BY SIMILARITY(select_val, ?) 
                               LIMIT 5");
    $stmt->execute([$type, $value]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <div class="row">
               <div class="col-12">
                  <div class="card border-info mb-4">
                     <div class="card-header bg-info">
                        <div class="card-title">Field List</div>
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table class="table table-striped w-100" id="maintable">
                              <thead>
                                 <tr>
                                    <th class="sort-alpha" data-priority="1">List Type</th>
                                    <th>Single / Multi choice</th>
                                    <th>No. of Values</th>
                                    <th>Must Complete?</th>
                                    <th>Values to complete</th>
                                 </tr>
                              </thead>
                              <tbody>
<?PHP
$tableset = $pdo->prepare("SELECT stid, str, single, musthave, wouldlike, sort_order FROM select_types ");
$tableset->execute();
// 'listselect_type' to avoid intereference with retaining selection
while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
  $stid = $row['stid'];
  $str = $row['str'];
  $single = $row['single'];
  $musthave = $row['musthave'];
  $wouldlike = $row['wouldlike'];
  $sort_order = (int)$row['sort_order'];
   if ($musthave == 0) {
      $musthave = '<div class="badge badge-secondary">No</div>';
   } else {
      $musthave = '<div class="badge badge-purple">Yes</div>';
   }
   if ($wouldlike == 0) {
      $wouldlikeVal = '<div class="badge badge-secondary">0</div>';
   } else {
      $wouldlikeVal = "<div class=\"badge badge-purple\">$wouldlike</div>";
   }
   if ($sort_order == 0) {
      $sort_order = '<div class="badge badge-danger">Not displayed</div>';
   }
   $listtype = 'Single Selection';
   if ($single == 1) {
      $listtype = 'Multiple Selection';
   }
   if ($single == 2) {
      $listtype = 'Text';
   }
   if ($single == 3) {
      $listtype = 'Date';
   }
   if ($single == 6) {
      $listtype = 'Time';
   }
   if ($single == 4) {
      $listtype = 'Numeric (step 0.1)';
   }
   if ($single == 5) {
      $listtype = 'Numeric (step integer)';
   }
   // how many values
   $vids = $pdo->prepare("SELECT pid FROM select_gen WHERE stid = ? ");
   $vids->execute([$stid]);
   $numlinks = $vids->rowCount();
   $vids->closeCursor();
?>
<tr>
   <td><a href="listtypedetail.php?which=<?php echo $stid ?>"><?php echo $str?></a></td>
   <td><?php echo $listtype ?></td>
   <td><?php echo $numlinks ?></td>
   <td><?php echo $musthave ?></td>
   <td><?php echo $wouldlikeVal ?></td>
</tr>
 <?php
 }
$tableset->closeCursor();
?>
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div><!-- /.card -->
               </div><!-- /.col-12 -->
            </div><!-- /.row -->

            <div class="row" id="newform">
               <div class="col-12">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info mb-4">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Category</div>
                        </div>
                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="str">Category</label>
                              <input class="form-control" type="text" id="str" name="str" required>
                           </div>
                           <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="col-form-label" for="single">Field Value Type</label>
                                        <select class="custom-select custom-select mb-3" id="single" name="single">
                                          <option selected value="0">Single Select</option>
                                          <option value="1">Multi Select</option>
                                          <option value="2">Text</option>
                                          <option value="4">Numeric (step 0.1)</option>
                                          <option value="5">Numeric (step integer)</option>
                                          <option value="3">Date</option>
                                          <option value="6">Time</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <h6>Reporting Data</h6>
                            <p>here we select competencies trainees must achieve/complete by the end of their three years of training. We also record how many different values are required for completion, if applicable (0 otherwise). NOTE; This section may be superceded by an improved method!!</p>
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="col-form-label" for="musthave">Must Complete?</label>
                                        <select class="custom-select custom-select mb-3" id="musthave" name="musthave">
                                          <option selected value="0">No</option>
                                          <option value="1">Yes</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col">
                                 <div class="form-group">
                                    <label class="col-form-label" for="wouldlike">Qty  values to reach</label>
                                    <input class="form-control" type="number" id="wouldlike" name="wouldlike" min="0" step="1" value="0" >
                                 </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right">
                              <button class="btn btn-info" type="submit">Add</button>
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