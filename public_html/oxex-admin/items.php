<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Items (Dropdown List) ";

setAdminVars(0); // Dashboard section
$subtitle = "Items";
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
  // delete row from detail page
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdo) {
    $stmt = $pdo->prepare("DELETE FROM select_gen WHERE pid = ? LIMIT 1");
    $stmt->execute([$which]);
    if ($stmt->rowCount() > 0) {
      // show message when deleting, not refreshing
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
    }
  }
}

if ($newadmin == 'newadmin') {
  $select_type = isset($_POST['select_type']) ? $_POST['select_type'] : '';
  $select_val = isset($_POST['select_val']) ? $_POST['select_val'] : 0;
  
  // get single/multiple from select_types
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdo) {
    $stmt = $pdo->prepare("SELECT stid, single FROM select_types WHERE str = ?");
    $stmt->execute([$select_type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $stid = $row['stid'];
      $single = $row['single'];
    }

    // write new record
    $insert_stmt = $pdo->prepare("INSERT INTO select_gen (select_type, single, select_val, stid) VALUES (?, ?, ?, ?) RETURNING pid");
    $insert_stmt->execute([$select_type, $single, $select_val, $stid]);
    
    // Get the newly created pid from RETURNING clause
    $new_record = $insert_stmt->fetch(PDO::FETCH_ASSOC);
    $newid = $new_record['pid'] ?? null;
    $insert_stmt->closeCursor();
  }

  
}

function getSimilarExistingValues($type) {
    global $supabase_pdo;
    $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT select_val FROM select_gen 
                               WHERE select_type = ? 
                               ORDER BY SIMILARITY(select_val, ?) 
                               LIMIT 5");
        $stmt->execute([$type, $newValue]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
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
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
  $tableset = $pdo->prepare("SELECT pid, select_type, single, select_val FROM select_gen ");
  $tableset->execute();
  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
  $pid = $row['pid'];
  $listselect_type = $row['select_type'];
  $single = $row['single'];
  $select_val = $row['select_val'];
 if ($single == 1) {
    $listtype = 'Multiple Selection';
 }
 // Ensure $listtype is always defined and handle all known $single values
 $listtype = 'Unknown';
 if ($single == 0) {
    $listtype = 'Single Selection';
 }
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
}
?>
                           </tbody>
                        </table>
</div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-12">
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
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
  $tableset = $pdo->prepare("SELECT str, single FROM select_types ORDER BY str ");
  $tableset->execute();
  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
  $str = $row['str'];
  $single = $row['single'];
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
}                                         
                                           ?>
                                       </select>
</div>
</div>
                            </div>
                         </div><!-- /card-body -->
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
                        </div><!-- /card-footer -->
                     </div><!-- END card -->
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