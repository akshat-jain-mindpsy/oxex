<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Documentation";
$subtitle = "Docs";
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
  $stmt = $mysqli->prepare("DELETE FROM docs_tbl WHERE did = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();

}
// doc_section, sect_title, sect_txt, sort_order, who_by, date_added, date_modified
if ($newadmin == 'newadmin') {
   $doc_section = isset($_POST['doc_section']) ? $_POST['doc_section'] : 0;
   $sect_title = isset($_POST['sect_title']) ? $_POST['sect_title'] : '';
   $sect_txt = isset($_POST['sect_txt']) ? $_POST['sect_txt'] : '';
   $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  
  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO docs_tbl (doc_section, sect_title, sect_txt, sort_order, who_by, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("issisii", $doc_section, $sect_title, $sect_txt, $sort_order, $usrkey, $today, $today);
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
                                 <th class="sort-alpha" data-priority="1">Title</th>
                                 <th>Section</th>
                                 <th>Sort Order</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$tableset = $mysqli->prepare("SELECT did, sect_title, doc_section, sort_order FROM docs_tbl ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($did, $sect_title, $doc_section, $sort_order);
while ($tableset->fetch()){
   if ($sort_order == 0) {
      $sort_order = '<div class="badge badge-danger">Not displayed</div>';
   }
   // which section?
   switch ($doc_section) {
      case 0:
      $section = 'Dashboard';
      break;
      case 1:
      $section = 'Pages';
      break;
      case 2:
      $section = 'Trainees';
      break;
      case 3:
      $section = 'Tables';
      break;
      case 4:
      $section = 'Blog';
      break;
      case 5:
     $section = 'Admin';
     break;
   }
?>
<tr>
   <td><a href="docdetail.php?which=<?php echo $did ?>"><?php echo $sect_title ?></a></td>
   <td><?php echo $section ?></td>

   <td><?php echo $sort_order ?></td>
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
                           <div class="card-title">Add new Documentation</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="sect_title">Title</label>
                              <input class="form-control" type="text" id="sect_title" name="sect_title" required>
                           </div>
                           <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label class="col-form-label" for="doc_section">Admin Section</label>
                                        <select class="custom-select custom-select mb-3" id="doc_section" name="doc_section" required>
                                          <option <?php if ($doc_section == '0') echo "selected" ?> value="0">Dashboard</option>
                                          <option <?php if ($doc_section == '1') echo "selected" ?> value="1">Pages</option>
                                          <option <?php if ($doc_section == '2') echo "selected" ?> value="2">Trainees</option>
                                          <option <?php if ($doc_section == '3') echo "selected" ?> value="3">Tables</option>
                                          <option <?php if ($doc_section == '4') echo "selected" ?> value="4">Blog</option>
                                          <option <?php if ($doc_section == '5') echo "selected" ?> value="5">Admin</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                           <div class="form-group">
                            <label class="col-form-label" for="sect_txt">Text</label>
                                 <textarea name="sect_txt" id="sect_txt" class="form-control summernote" required></textarea>
                             </div>
                           <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
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
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>