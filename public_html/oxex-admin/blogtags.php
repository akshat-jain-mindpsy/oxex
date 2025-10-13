<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Blog Tags";

setAdminVars(4); // Blog section
$subtitle = "Blog";
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
  $stmt = $pdo->prepare("DELETE FROM blog_subject_tags WHERE btagid = ? LIMIT 1");
  $stmt->execute([$which]); 
  if ($stmt->rowCount() > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->closeCursor();

}

if ($newadmin == 'newadmin') {
  $btag = isset($_POST['btag']) ? $_POST['btag'] : '';
  
  // write new record
  $insert_stmt = $pdo->prepare("INSERT INTO blog_subject_tags (btag) VALUES (?)");
  $insert_stmt->execute([$btag]);
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

setAdminVars(4); // Blog section            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Tag</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$tableset = $pdo->prepare("SELECT btagid, btag FROM blog_subject_tags ");
$tableset->execute();
while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
  $btagid = $row['btagid'];
  $btag = $row['btag'];
?>
<tr>
   <td><a href="blogtagdetail.php?which=<?php echo $btagid ?>"><?php echo $btag ?></a></td>
</tr>
 <?php
 }
$tableset->closeCursor();
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
                           <div class="card-title">Add new Tag</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="btag">Tag</label>
                              <input class="form-control" type="text" id="btag" name="btag">
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
        "pageLength": 10
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