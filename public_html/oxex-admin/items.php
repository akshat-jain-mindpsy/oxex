<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Items (Dropdown List) ";
$subtitle = "Items";
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
$select_type = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row from detail page
  $stmt = $mysqli->prepare("DELETE FROM select_gen WHERE pid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();

}

if ($newadmin == 'newadmin') {
  $select_type = isset($_POST['select_type']) ? $_POST['select_type'] : '';
  $select_val = isset($_POST['select_val']) ? $_POST['select_val'] : 0;
  
  // get single/multiple from select_types
   $stmt = $mysqli->prepare("SELECT stid, single FROM select_types WHERE str = ?");
   $stmt->bind_param("s", $select_type);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($stid, $single);
   $stmt->fetch();
   $stmt->close();


  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO select_gen (select_type, single, select_val, stid) VALUES (?, ?, ?, ?)");
  $insert_stmt->bind_param("sisi", $select_type, $single, $select_val, $stid);
  $insert_stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $newid = $insert_stmt->insert_id;
  $insert_stmt->close();

  
}

function getSimilarExistingValues($type) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT select_val FROM select_gen 
                               WHERE select_type = ? 
                               ORDER BY SIMILARITY(select_val, ?) 
                               LIMIT 5");
    $stmt->bind_param("ss", $type, $newValue);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
                                 <th>Value</th>
                                 <th class="sort-alpha" data-priority="1">List</th>
                                 <th>List Type</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$tableset = $mysqli->prepare("SELECT pid, select_type, single, select_val FROM select_gen ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($pid, $listselect_type, $single, $select_val);
// 'listselect_type' to avoid intereference with retaining selection
while ($tableset->fetch()){
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
?>
<tr>
   <td><a href="listdetail.php?which=<?php echo $pid ?>"><?php echo $select_val?></a></td>
   <td><?php echo $listselect_type ?></td>
   <td><?php echo $listtype ?></td>
</tr>
 <?php
 }
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
                           <div class="card-title">Add new Item</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="select_val">Item</label>
                              <input class="form-control" type="text" id="select_val" name="select_val" required>
                           </div>
                           <div class="row">

                                <div class="col">
                                    <div class="form-group">
                                        <label class="col-form-label" for="select_type">Category</label>
                                        <select class="custom-select custom-select-lg mb-3" id="select_type" name="select_type" required>
                                          <option <?php if ($select_type == '') echo "selected='selected'" ?> value="">Select...</option>
                                          <?php
$tableset = $mysqli->prepare("SELECT str, single FROM select_types ORDER BY str ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($str, $single);
while ($tableset->fetch()){
   if ($single == 0) {
      $listtype = 'Single Selection';
   } else {
      $listtype = 'Multiple Selection';
   }
   echo "<option value=\"$str\"";
   if ($select_type == $str) {
      echo "selected='selected'";
   }
   echo ">$str ($listtype)</option>";
}
$numrows = $tableset->num_rows;
$tableset->close();                                         
                                           ?>
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
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>