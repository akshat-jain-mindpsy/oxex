<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Sheets ('Sheet names')";

// Set variables needed by adminjs.php
setAdminVars(3); // Tables section
$subtitle = "Sheets";
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
   // delete all field links
   $stmt = $pdo->prepare("DELETE FROM tab_fields WHERE tbid = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();
  // delete row from detail page
  $stmt = $pdo->prepare("DELETE FROM tabs_tbl WHERE tbid = ? LIMIT 1");
  $stmt->execute([$which]); 
  if ($stmt->rowCount() > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->closeCursor();
}

if ($newadmin == 'newadmin') {
  $tab_name = isset($_POST['tab_name']) ? $_POST['tab_name'] : '';
  $tab_notes = isset($_POST['tab_notes']) ? $_POST['tab_notes'] : '';
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  $isvis = isset($_POST['isvis']) ? $_POST['isvis'] : 1;
  
  // write new record
  $insert_stmt = $pdo->prepare("INSERT INTO tabs_tbl (tab_name, tab_notes, sort_order, isvis) VALUES (?, ?, ?, ?)");
  $insert_stmt->execute([$tab_name, $tab_notes, $sort_order, $isvis]);
  $newid = $pdo->lastInsertId();
  $insert_stmt->closeCursor();
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
                                 <th class="sort-alpha" data-priority="1">Competency</th>
                                 <th>Sort Order</th>
                                 <th>Default for Trainees?</th>
                                 <th>No. of Categories</th>
                                 <th>Actions</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$tableset = $pdo->prepare("SELECT tbid, tab_name, sort_order, isvis FROM tabs_tbl ORDER BY sort_order");
$tableset->execute();
while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
   $tbid = $row['tbid'];
   $tab_name = $row['tab_name'];
   $sort_order = $row['sort_order'];
   $isvis = $row['isvis'];
   
   // Count number of fields for this table
   $fieldstmt = $pdo->prepare("SELECT COUNT(*) FROM tab_fields WHERE tbid = ?");
   $fieldstmt->execute([$tbid]);
   $num_fields = (int)$fieldstmt->fetchColumn();
   $fieldstmt->closeCursor();

   // Determine sort order display
   $sort_order_display = $sort_order == 0 
      ? '<div class="badge badge-danger">Not displayed</div>' 
      : $sort_order;

   // Visibility display
   $visibility_display = $isvis 
      ? '<div class="badge badge-success">Visible</div>' 
      : '<div class="badge badge-secondary">Hidden</div>';
?>
<tr>
   <td>
      <a href="sheetdetail.php?which=<?php echo $tbid ?>" class="table-link">
         <?php echo htmlspecialchars($tab_name) ?>
      </a>
   </td>
   <td><?php echo $sort_order_display ?></td>
   <td><?php echo $visibility_display ?></td>
   <td><?php echo $num_fields ?></td>
   <td>
      <div class="btn-group" role="group">
         <a href="sheetdetail.php?which=<?php echo $tbid ?>" class="btn btn-sm btn-info">
            <i class="fa fa-eye"></i> View Details
         </a>
         <a href="sheets.php?del=del&which=<?php echo $tbid ?>" class="btn btn-sm btn-danger delete-table" onclick="return confirm('Are you sure you want to delete this sheet?')">
            <i class="fa fa-trash"></i> Delete
         </a>
      </div>
   </td>
</tr>
<?php
}
$tableset->closeCursor();
?>
                           </tbody>
                        </table>
</div>
            </div><!-- end table row -->
            </div>

            <div class="row my-5" id="newform">
               <div class="col-12">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Sheet</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="tab_name">Sheet Name</label>
                              <input class="form-control" type="text" id="tab_name" name="tab_name" required>
                           </div>
                           <div class="form-group">
                            <label class="col-form-label">Notes</label>
                                 <textarea name="tab_notes" id="tab_notes" class="form-control summernote"></textarea>
                             </div>
                           <div class="row">
                              <div class="col form-group">
                                  <label class="col-form-label" for="isvis">For all Trainees by default</label>
                                  <select class="custom-select custom-select mb-3" id="isvis" name="isvis">
                                    <option value="0">No</option>
                                    <option selected value="1">Yes</option>
                                  </select>
                              </div>
                              <div class="col form-group">
                                 <label class="col-form-label" for="sort_order">Sort order</label>
                                 <input class="form-control" type="number" id="sort_order" name="sort_order" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
                              </div>
                           </div>
                        </div><!-- /card-body -->
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
                        </div><!-- /card-footer -->
                     </div><!-- END card-->
                  </form>
               </div>
            </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script>
   $(document).ready(function() {
      $('#maintable').dataTable({
        "pageLength": 50,
        "columnDefs": [
            { "orderable": false, "targets": 4 }  // Disable sorting for actions column
        ]
      });

      // Optional: Add hover effect to table rows
      $('.table-link').hover(
        function() { $(this).addClass('text-primary'); },
        function() { $(this).removeClass('text-primary'); }
      );
   });
   </script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>