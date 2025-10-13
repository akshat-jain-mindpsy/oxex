<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Graph Key Colours";

setAdminVars(0); // Dashboard section
$subtitle = "Reports";
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);

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
   <link rel = "stylesheet" href = "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.2.0/css/bootstrap-colorpicker.min.css"/>
   <link rel = "stylesheet" href = "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.2.0/css/bootstrap-colorpicker.css"/>
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
  if ($usingSupabase) {
    $stmt = $supabase_pdo->prepare("DELETE FROM report_colour WHERE rcid = ? LIMIT 1");
    $stmt->execute([$which]); 
    if ($stmt->rowCount() > 0) {
      // show message when deleting, not refreshing
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
    }
  }
}
if ($newadmin == 'newadmin') {
  $colour = isset($_POST['colour']) ? $_POST['colour'] : '';
  
  # strip hash
  $colour = str_replace("#", "", $colour);
  
  // write new record
  if ($usingSupabase) {
    $insert_stmt = $supabase_pdo->prepare("INSERT INTO report_colour (colour) VALUES (?)");
    $insert_stmt->execute([$colour]);
    $newid = $supabase_pdo->lastInsertId();
  } else {
    $newid = 0;
  }

  
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

setAdminVars(0); // Dashboard section            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Key#</th>
                                 <th>Colour Label</th>
                                 <th>RGB</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
if ($usingSupabase) {
    $tableset = $supabase_pdo->prepare("SELECT rcid, colour FROM report_colour ");
    $tableset->execute();
    $colours = $tableset->fetchAll(PDO::FETCH_ASSOC);
} else {
    $colours = [];
}

foreach ($colours as $colour_item) {
    $rcid = $colour_item['rcid'];
    $colour = $colour_item['colour'];
   $hex      = str_replace('#', '', $colour);
   $length   = strlen($hex);
   $rgbr = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
   $rgbg = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
   $rgbb = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));
?>
<tr>
   <td><a href="graphdetail.php?which=<?php echo $rcid ?>">Graph Key #<?php echo $rcid ?></a></td>
   <td style="background-color: #<?php echo $colour ?>;"></td>
   <td><?php echo "rgba($rgbr, $rgbg, $rgbb, 1)" ?></td>
</tr>
 <?php
 }
$numrows = count($colours);
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
                           <div class="card-title">Add new colour key</div>
                        </div>

                        <div class="card-body">

                           
                           <div class="row">
                             <div class="col">
                                <div class="form-group">
                                    <label class="col-form-label" for="colour">Key Colour</label>
                                    <input class="form-control" type="text" id="color-picker" name="colour" required>
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
   <script src = "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.2.0/js/bootstrap-colorpicker.js" > </script>
<script src = "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.2.0/js/bootstrap-colorpicker.min.js" > </script>
<script>
$(function () {
   $('#color-picker').colorpicker(
      {
         format:
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