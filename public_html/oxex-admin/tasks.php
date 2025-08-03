<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Placement Attendance Tasks";
$subtitle = "Tasks";
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
  $stmt = $mysqli->prepare("DELETE FROM tasks WHERE dtid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();

}
if ($newadmin == 'newadmin') {
  $task = isset($_POST['task']) ? $_POST['task'] : '';
  $colour = isset($_POST['colour']) ? $_POST['colour'] : '';
  $textcolor = isset($_POST['textcolor']) ? $_POST['textcolor'] : 0;
  
  # strip hash
  $colour = str_replace("#", "", $colour);
  
  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO tasks (task, colour, textcolor) VALUES (?, ?, ?)");
  $insert_stmt->bind_param("ssi", $task, $colour, $textcolor);
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
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Task</th>
                                 <th>Colour Label</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$tableset = $mysqli->prepare("SELECT dtid, task, colour, textcolor FROM tasks ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($dtid, $task, $colour, $textcolor);
while ($tableset->fetch()){
   $showtxt = "$colour";
   if ($textcolor == 1) {
      $showtxt = "<span class=\"text-white\">$colour</span>";
   } 
?>
<tr>
   <td><a href="taskdetail.php?which=<?php echo $dtid ?>"><?php echo $task ?></a></td>
   <td style="background-color: #<?php echo $colour ?>;"><?php echo $showtxt ?></td>
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
                           <div class="card-title">Add new Placement Attendance Task</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="task">Task</label>
                              <input class="form-control" type="text" id="task" name="task" required>
                           </div>
                           
                           <div class="row">
                             <div class="col">
                                <div class="form-group">
                                    <label class="col-form-label" for="colour">Label Colour</label>
                                    <input class="form-control" type="text" id="color-picker" name="colour" required>
                                 </div>
                             </div>
                             <div class="col">
                                 <div class="form-group">
                                     <label class="col-form-label" for="textcolor">Text Colour</label>
                                     <select class="custom-select custom-select mb-3" id="textcolor" name="textcolor" required>
                                       <option value="0" selected>Dark Grey</option>
                                       <option value="1">White</option>
                                     </select>
                                 </div>
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
   <script src = "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.2.0/js/bootstrap-colorpicker.js" > </script>
<script src = "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.2.0/js/bootstrap-colorpicker.min.js" > </script>
<script>
$(function () {
   $('#color-picker').colorpicker();
});
</script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>