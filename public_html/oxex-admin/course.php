<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "University Course";

$subtitle = "Courses";
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
    $stmt = $supabase_pdo->prepare("DELETE FROM uni_tbl WHERE uid = ? LIMIT 1");
    $stmt->execute([$which]);
    if ($stmt->rowCount() > 0) {
      // show message when deleting, not refreshing
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
    }
  }

}

if ($newadmin == 'newadmin') {
  $university = isset($_POST['university']) ? $_POST['university'] : '';
  $ident = isset($_POST['ident']) ? $_POST['ident'] : '';
  
  // write new record
  if ($usingSupabase) {
    $insert_stmt = $supabase_pdo->prepare("INSERT INTO uni_tbl (university, ident) VALUES (?, ?)");
    $insert_stmt->execute([$university, $ident]);
    $newid = $supabase_pdo->lastInsertId();
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
            </div>
            <?php echo $delalert ?>
           <div class="row">
               <div class="col-12 col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">University</th>
                                 <th>Identifier</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
if ($usingSupabase) {
    $tableset = $supabase_pdo->prepare("SELECT uid, university, ident FROM uni_tbl ");
    $tableset->execute();
    $courses = $tableset->fetchAll(PDO::FETCH_ASSOC);
} else {
    $courses = [];
}

foreach ($courses as $course) {
    $uid = (int)$course['uid'];
    $university = $course['university'];
    $ident = $course['ident'];
?>
<tr>
   <td><a href="coursedetail.php?which=<?php echo $uid ?>"><?php echo $university ?></a></td>
   <td><?php echo $ident ?></td>
</tr>
 <?php
 }
?>
                           </tbody>
                        </table>
                     </div>
                  </div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-12 col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new University Course</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="university">University Course</label>
                              <input class="form-control" type="text" id="university" name="university" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="ident">2-Character Identifier</label>
                              <input class="form-control" type="text" id="ident" name="ident" maxlength="2" required>
                           </div>
                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
                        </div><!-- END card-footer -->
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