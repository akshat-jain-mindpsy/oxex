<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Trainee Subsets";

// Set variables needed by adminjs.php
setAdminVars(3); // Tables section
$subtitle = "Subset";
// This page allows the user to create a subset of trainees
// Trinees are added in the detail page
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
$which = isset($_GET['which']) ? $_GET['which'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
    // delete row
  $stmt = $pdo->prepare("DELETE FROM subset_tbl WHERE setkey = ? LIMIT 1");
  $stmt->execute([$which]); 
  if ($stmt->rowCount() > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->closeCursor();
  // delete all trainee links for this subset
  $stmt = $pdo->prepare("DELETE FROM subset_link_tbl WHERE setkey = ?");
  $stmt->execute([$which]); 
  $stmt->closeCursor();
}

if ($newadmin == 'newadmin') {
  $subset = isset($_POST['subset']) ? $_POST['subset'] : '';
  $description = isset($_POST['description']) ? $_POST['description'] : '';
  
  // Ensure $today is defined (format: YYYYMMDD)
  if (!isset($today)) {
    $today = date('Ymd');
  }
    
    $numids = 1;
    while (!$numids == 0) {
      // make 32 digit hex string
      $setkey = substr(md5(rand()), 0, 32);   
      $stmt = $pdo->prepare("SELECT sd FROM subset_tbl WHERE setkey = ? LIMIT 1");
      $stmt->execute([$setkey]);
      $numids = $stmt->rowCount();
      $stmt->closeCursor();
    }

  // write new record
  $insert_stmt = $pdo->prepare("INSERT INTO subset_tbl (subset, description, usrkey, setkey, date_added, date_modified, who_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->execute([$subset, $description, $usrkey, $setkey, $today, $today, $usrkey]);
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
                                 <th class="sort-alpha" data-priority="1">Subset</th>
                                 <th>No. Trainees</th>
                                 <th>Added</th>
                                 <th>Modified</th>
                                 <th>Admin</th>
                                 <th>Combined Stats</th>
                              </tr>
                           </thead>
                           <tbody>
<?php
// show all if AT or DV, or just show own
$tableset = $pdo->prepare("SELECT setkey, usrkey, subset, date_added, date_modified FROM subset_tbl");
//$tableset->bind_param("s", $usrkey);
$tableset->execute();
while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
   $setkey = $row['setkey'];
   $who_there = $row['usrkey'];
   $subset = $row['subset'];
   $date_added = $row['date_added'];
   $date_modified = $row['date_modified'];
   $date_added = $date_added !== null ? strtotime($date_added) : false;
   $date_modified = $date_modified !== null ? strtotime($date_modified) : false;
   // count trainees
   $vids = $pdo->prepare("SELECT slid FROM subset_link_tbl WHERE setkey = ? ");
   $vids->execute([$setkey]);
   $numlinks = $vids->rowCount();
   $vids->closeCursor();
   // get tutor
   $stmt = $pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->execute([$who_there]);
   $realname = $stmt->fetchColumn();
   $stmt->closeCursor();
   if ($usrkey == $who_there || ($admintype == 'AT' || $admintype == 'DV')) {
?>
<tr>
   <td><a href="subsetdetail.php?which=<?php echo $setkey ?>"><?php echo $subset?></a></td>
   <td><?php echo $numlinks ?></td>
   <td><?php echo $date_added !== false ? date('d/m/Y', $date_added) : 'N/A' ?></td>
   <td><?php echo $date_modified !== false ? date('d/m/Y', $date_modified) : 'N/A' ?></td>
   <td><?php echo $realname ?></td>
   <td><a href="subsetstats.php?group=<?php echo $setkey ?>" class="btn btn-success">View</a></td>
</tr>
 <?php
   } # who allow to see?
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
                           <div class="card-title">Add new Subset</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="subset">Subset Name</label>
                              <input class="form-control" type="text" id="subset" name="subset" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="description">Description</label>
                              <textarea class="form-control summernote" type="text" id="description" name="description"></textarea>
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