<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Composite Searches";
$subtitle = "Searches";
$listurl = "composite.php";
$listname = "Searches";
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
   <style>
      .draggable {cursor: grabbing;}
   </style>
</head>
<?php 
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
   $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
   $csname = isset($_POST['csname']) ? $_POST['csname'] : '';
   $pid1 = isset($_POST['pid1']) ? $_POST['pid1'] : 0;
   $pid2 = isset($_POST['pid2']) ? $_POST['pid2'] : 0;
   $pid3 = isset($_POST['pid3']) ? $_POST['pid3'] : 0;
   $target = isset($_POST['target']) ? $_POST['target'] : 0;
   $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
   $stmt = $mysqli->prepare("SELECT stid FROM select_gen WHERE pid = ?");
   $stmt->bind_param("i", $pid1);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($stid1);
   $stmt->fetch();
   $stmt->close();
   $stmt = $mysqli->prepare("SELECT stid FROM select_gen WHERE pid = ?");
   $stmt->bind_param("i", $pid2);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($stid2);
   $stmt->fetch();
   $stmt->close();
   $stmt = $mysqli->prepare("SELECT stid FROM select_gen WHERE pid = ?");
   $stmt->bind_param("i", $pid3);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($stid3);
   $stmt->fetch();
   $stmt->close();
  
  // Update record
  $stmt = $mysqli->prepare("UPDATE compositesearch SET csname = ?, stid1 = ?, pid1 = ?, stid2 = ?, pid2 = ?, stid3 = ?, pid3 = ?, sort_order = ?, target = ?, who_by = ?, date_modified = ? WHERE csid = ? "); 
  $stmt->bind_param("siiiiiiiisii", $csname, $stid1, $pid1, $stid2, $pid2, $stid3, $pid3, $sort_order, $target, $usrkey, $today, $which);
  $stmt->execute();
  $stmt->close();
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT csname, stid1, pid1, stid2, pid2, stid3, pid3, sort_order, target, who_by, date_added, date_modified FROM compositesearch WHERE csid = ?");
$stmt->bind_param("i", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($csname, $stid1, $pid1, $stid2, $pid2, $stid3, $pid3, $sort_order, $target, $usrkey, $date_added, $date_modified);
$stmt->fetch();
$stmt->close();
// whatever the record name is
  $changename = "$csname";
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small></div>
            </div>

            <div class="row my-5">
               <div class="col-xl-7">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?></div>
                        </div>
                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="csname">Search Name</label>
                              <input class="form-control" type="text" id="csname" name="csname" value="<?php echo $csname ?>" required>
                           </div>
                           <span class="form-text">2nd and 3rd Search Values are Optional</span>
                           <div class="row">
                              <div class="col form-group">
                                  <label class="col-form-label" for="pid1">Search Value</label>
                                  <select class="custom-select custom-select mb-3" id="pid1" name="pid1" required>
                                 <option  value="">Select...</option>
                                 <?php
                                 // The 'pid' value is the id relating to the answer required in table select_gen
                                 // The 'stid' value is which field 
                                 // 'select_types' says the name of the field
                                 echo "<option value=\"0\" selected";
                                 echo ">No Selection</option>";
                                 $tableset = $mysqli->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                 $tableset->execute();
                                 $tableset->store_result();
                                 $tableset->bind_result($pid, $stid, $select_val, $str);
                                 while ($tableset->fetch()){
                                 echo "<option value=\"$pid\"";
                                 if ($pid1 == $pid) {
                                    echo ' selected';
                                 }
                                 echo ">$select_val ($str) ($pid)</option>";
                                 }
                                 $numrows = $tableset->num_rows;
                                 $tableset->close();                                   
                                  ?>
                              </select>
                              </div>
                              <div class="col form-group">
                                 <label class="col-form-label" for="pid2">Criteria Value 1 <small>(Opt)</small></label>
                                 <select class="custom-select custom-select mb-3" id="pid2" name="pid2">
                                 <option  value="">Select...</option>
                                 <?php
                                 // The 'pid' value is the id relating to the answer required in table select_gen
                                 // The 'stid' value is which field 
                                 // 'select_types' says the name of the field
                                 echo "<option value=\"0\" selected";
                                 echo ">No Selection</option>";
                                 $tableset = $mysqli->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                 $tableset->execute();
                                 $tableset->store_result();
                                 $tableset->bind_result($pid, $stid, $select_val, $str);
                                 while ($tableset->fetch()){
                                 echo "<option value=\"$pid\"";
                                 if ($pid2 == $pid) {
                                    echo ' selected';
                                 }
                                 echo ">$select_val ($str) ($pid)</option>";
                                 }
                                 $numrows = $tableset->num_rows;
                                 $tableset->close();                                   
                                  ?>
                              </select>
                              </div>
                              <div class="col form-group">
                                 <label class="col-form-label" for="pid3">Criteria Value 2 <small>(Opt)</small></label>
                                 <select class="custom-select custom-select mb-3" id="pid3" name="pid3">
                                 <option  value="">Select...</option>
                                 <?php
                                 // The 'pid' value is the id relating to the answer required in table select_gen
                                 // The 'stid' value is which field 
                                 // 'select_types' says the name of the field
                                 echo "<option value=\"0\" selected";
                                 echo ">No Selection</option>";
                                 $tableset = $mysqli->prepare("SELECT select_gen.pid, select_gen.stid, select_gen.select_val, select_types.str FROM select_gen, select_types WHERE select_gen.stid = select_types.stid ORDER BY select_types.str, select_gen.select_val");
                                 $tableset->execute();
                                 $tableset->store_result();
                                 $tableset->bind_result($pid, $stid, $select_val, $str);
                                 while ($tableset->fetch()){
                                 echo "<option value=\"$pid\"";
                                 if ($pid3 == $pid) {
                                    echo ' selected';
                                 }
                                 echo ">$select_val ($str) ($pid)</option>";
                                 }
                                 $numrows = $tableset->num_rows;
                                 $tableset->close();                                   
                                  ?>
                              </select>
                              </div>
                            </div>
                            <div class="row">
                              <div class="col form-group">
                                 <label class="col-form-label" for="target">Target Hours (decimal or integer)</label>
                                 <input class="form-control" type="number" id="target" name="target"  step="any" min="0" value="<?php echo $target ?>">
                              </div>
                              <div class="col form-group">
                                 <label class="col-form-label" for="sort_order">Sort order</label>
                                 <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
                              </div>
                            </div>

                        </div>
                        
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
               </div>



               <div class="col-xl-5">




                <div class="card border-info mt-3">
                  <div class="card-header bg-info">
                     <div class="card-title">Drag to sort Searches</div>
                  </div>
                  <div class="card-body">
                     <ul class="list-unstyled" id="post_list">
<?php
// find all values for this list
$tableset = $mysqli->prepare("SELECT csid, csname FROM compositesearch WHERE sort_order != ? ORDER BY sort_order");
$tableset->bind_param("i", $value0);
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($csid, $csname);
while ($tableset->fetch()){
   echo "<li class=\"bg-warning rounded my-1 pl-1 py-1 draggable\" id=\"$csid\">$csname  </li>";
}
$tableset->close();
?>
                     </ul>
                  </div>
                  <div class="card-footer">
                  </div>
                </div>
               </div>



            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                     <!-- START card-->
                     <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                           <div class="card-title">Delete <?php echo $changename ?></div>
                        </div>
                        <div class="card-footer">
                           <div class="float-right">
                            <a href="<?php echo $listurl ?>?del=del&amp;which=<?php echo $which ?>" class="btn btn-labeled btn-danger" role="button" onclick="return confirm('Are you sure you want to delete this record and all associated data?')"><span class="btn-label"><i class="fa fa-times"></i></span>Delete now!</a>
                          </div>
                        </div>
                     </div><!-- END card-->
               </div>
            </div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js" integrity="sha256-eTyxS0rkjpLEo16uXTS0uVCS4815lc40K2iVpWDvdSY=" crossorigin="anonymous"></script>
<script type="text/javascript">
   /* sort table field list and update table */
   /* oxex admin tabledetail.php */
 $( "#post_list" ).sortable({
     delay: 150,
     opacity: 0.6, 
      cursor: 'move',
     stop: function() {
         var selectedData = new Array();
         $('#post_list>li').each(function() {
             selectedData.push($(this).attr("id"));
         });
         updateOrder(selectedData);
     }
 });

 function updateOrder(data) {
     $.ajax({
         url:"compositedetailsort.php",
         type:'post',
         data:{position:data},
     })
 }
</script>
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