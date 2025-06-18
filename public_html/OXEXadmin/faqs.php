<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "FAQs";
$subtitle = "Page content";
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
if ($del == "del" && ($admintype == 'AD' || $admintype == 'DV')) {
  // delete row from detail page
  $stmt = $mysqli->prepare("DELETE FROM faq_tbl WHERE fid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();

}

if ($newadmin == 'newadmin') {
  $fa = isset($_POST['fa']) ? $_POST['fa'] : '';
  $fq = isset($_POST['fq']) ? $_POST['fq'] : '';
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  
  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO faq_tbl (fq, sort_order, fa) VALUES (?, ?, ?)");
  $insert_stmt->bind_param("sis", $fq, $sort_order, $fa);
  $insert_stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $newid = $insert_stmt->insert_id;
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
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New <?php echo $subtitle ?></a></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Question</th>
                                 <th class="sort-numeric">Sort Order</th>
                                 <th class="sort-alpha" data-priority="2">Answer</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$tableset = $mysqli->prepare("SELECT fid, fq, sort_order, fa FROM faq_tbl ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($fid, $fq, $sort_order, $fa);
while ($tableset->fetch()){   
   if ($sort_order == 0) {
     $sort_order = '<div class="badge badge-danger">Not displayed</div>';
   }

?>
<tr>
   <td><a href="faqdetail.php?which=<?php echo $fid ?>"><?php echo $fq ?></a></td>
   <td><?php echo $sort_order ?></td>
   <td><?php echo $fa ?></td>
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
                           <div class="card-title">Add new FAQ</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="fq">Question *</label>
                              <textarea class="form-control" type="text" id="fq" name="fq" required></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="sort_order" name="sort_order" value="0" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="fa">Answer *</label>
                              <textarea class="form-control" rows="6" type="text" id="fa" name="fa" required></textarea>
                           </div>
                           <div class="required">* Required fields</div>
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
        "pageLength": 10
      });
     $('#fa').summernote({
        tabsize: 2,
        height: 160,
        spellCheck: true,
        dialogsInBody: true,
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