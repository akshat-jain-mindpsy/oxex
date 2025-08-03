<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Trainee Subsets";
$subtitle = "Subsets";
$listurl = "subsets.php";
$listname = "Subsets";
// this page lists subsets and allows trainees to be added or removed
// Supervisors/tutors can only use Trainees allocated to them
// Oxford Admins can see any Oxford Trainee
// Exeter Admins can see any Exeter Trainee
// Super Admins & Devs can see all
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
$del = isset($_GET['del']) ? $_GET['del'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : '';
$addset = isset($_GET['addset']) ? $_GET['addset'] : ''; # add trainee to set

if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row
  $stmt = $mysqli->prepare("DELETE FROM subset_tbl WHERE setkey = ? LIMIT 1");
  $stmt->bind_param("s", $which); 
  $stmt->execute();
  $stmt->close();
  // delete all trainee links for this subset
  $stmt = $mysqli->prepare("DELETE FROM subset_link_tbl WHERE setkey = ?");
  $stmt->bind_param("s", $which); 
  $stmt->execute();
  $stmt->close();
}
$subsetmsg = "";
if ($addset == "addset") {
   // add trainee to a subset
   // access controlled by listing of links
   $setkey = isset($_GET['setkey']) ? $_GET['setkey'] : '';
   $trainkey = isset($_GET['trainkey']) ? $_GET['trainkey'] : '';
   if ($setkey != '' && $trainkey != '') {
      // check if this trainee is alreday in the subset
      $vids = $mysqli->prepare("SELECT slid FROM subset_link_tbl WHERE setkey = ? AND trainkey = ? ");
      $vids->bind_param("ss", $setkey, $trainkey);
      $vids->execute();
      $vids->store_result();
      $numlinks = $vids->num_rows;
      $vids->close();
      if ($numlinks == 0) {
         // ok to create
         $subsetmsg = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-success\" role=\"alert\">Trainee added to the selected Subset</div></div></div>";
         $insert_stmt = $mysqli->prepare("INSERT INTO subset_link_tbl (setkey, trainkey, who_by, date_added) VALUES (?, ?, ?, ?)");
         $insert_stmt->bind_param("sssi", $setkey, $trainkey, $usrkey, $today);
         $insert_stmt->execute();
            //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
         $newid = $insert_stmt->insert_id;
         $insert_stmt->close();
      } else {
         $subsetmsg = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\">Not added - Trainee is already in the selected Subset</div></div></div>";
      }
   }
   $which = $setkey; # to set up next view
}
if ($addset == "delete") {
   // delete trainee from subset
   $setkey = isset($_GET['setkey']) ? $_GET['setkey'] : '';
   $trainkey = isset($_GET['trainkey']) ? $_GET['trainkey'] : '';
   $stmt = $mysqli->prepare("DELETE FROM subset_link_tbl WHERE setkey = ? AND trainkey = ? LIMIT 1");
   $stmt->bind_param("ss", $setkey, $trainkey); 
   $stmt->execute();
   $stmt->close();
   $subsetmsg = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\">Trainee deleted from Subset</div></div></div>";
   $which = $setkey; # to set up next view
}

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : ''; # 32 char str
  $subset = isset($_POST['subset']) ? $_POST['subset'] : '';
  $description = isset($_POST['description']) ? $_POST['description'] : '';
  
  // Update record
  $stmt = $mysqli->prepare("UPDATE subset_tbl SET subset = ?, description = ?, who_by = ?, date_modified = ? WHERE setkey = ? "); 
  $stmt->bind_param("sssis", $subset, $description, $usrkey, $today, $which);
  $stmt->execute();
  $stmt->close();
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT subset, description, who_by, date_added, date_modified FROM subset_tbl WHERE setkey = ?");
$stmt->bind_param("s", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($subset, $description, $who_by, $date_added, $date_modified);
$stmt->fetch();
$stmt->close();
   $date_added = strtotime($date_added);
   $date_modified = strtotime($date_modified);
   // who changed last?
   $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->bind_param("s", $who_by);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($who_by);
   $stmt->fetch();
   $stmt->close();
   
// whatever the record name is
  $changename = "$subset";
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
               <div class="content-title"><?php echo $pagetitle ?></div> <a href="subsetstats.php?group=<?php echo $which ?>" class="btn btn-success ml-5">View Stats</a> <a href="compositestats.php?group=<?php echo $which ?>" class="btn btn-success ml-5">View Composite Results</a>
            </div>
            <?php echo $subsetmsg ?>
            <div class="row my-5">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?></div>
                        </div>
                        <div class="card-body">
                           <p>Record created on <?php echo date("D jS M Y", $date_added) ?> and last modified on <?php echo date("D jS M Y", $date_modified) ?> by <?php echo $who_by ?></p>
                           <div class="form-group">
                              <label class="col-form-label" for="subset">Subset Name</label>
                              <input class="form-control" type="text" id="subset" name="subset" value="<?php echo $subset ?>" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="description">Description</label>
                              <textarea class="form-control summernote" type="text" id="description" name="description"><?php echo $description ?></textarea>
                           </div>
                        </div>
                        
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>

                  <div class="card border-purple mt-3s">
                        <div class="card-header bg-purple text-white">
                           <div class="card-title">Trainees Available: Add to this subset</div>
                        </div>
                        <div class="card-body">
<?php
// if Supervisor, only list trainees allocated
if ($admintype == "SO" || $admintype == "SE") {
   $tableset = $mysqli->prepare("SELECT trainkey, name, year FROM trainee_tbl WHERE supervisor = ? OR supervisor2 = ? OR supervisor3 = ? OR tutor = ?");
   $tableset->bind_param("ssss", $usrkey, $usrkey, $usrkey, $usrkey);
   $tableset->execute();
   $tableset->store_result();
   $tableset->bind_result($trainkey, $name, $year);
   while ($tableset->fetch()){
      echo "<p><a href=\"subsetdetail.php?addset=addset&amp;setkey=$which&amp;trainkey=$trainkey\" class=\"btn btn-purple\">$name ($year)</a></p>";
   }
   $numrows = $tableset->num_rows;
   $tableset->close();
}
// For Admins, loop through Possible Unis
$tableset = $mysqli->prepare("SELECT uid, ident FROM uni_tbl ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($uid, $ident);
while ($tableset->fetch()){
   if ($ident == 'OX' && $admintype == "AO") {
      // loop through all this course trainees
      $xtraset = $mysqli->prepare("SELECT trainkey, name, year FROM trainee_tbl WHERE uid = ? ");
      $xtraset->bind_param("i", $uid);
      $xtraset->execute();
      $xtraset->store_result();
      $xtraset->bind_result($trainkey, $name, $year);
      while ($xtraset->fetch()){
         echo "<p><a href=\"subsetdetail.php?addset=addset&amp;setkey=$which&amp;trainkey=$trainkey\" class=\"btn btn-purple\">$name ($year)</a></p>";
      }
      $numrows = $xtraset->num_rows;
      $xtraset->close();
   }
}
$tableset->close();
// For Super-Admins or Devs, list all Trainees
if ($admintype == "AT" || $admintype == "DV") {
   // loop through all this course trainees
   $xtraset = $mysqli->prepare("SELECT trainkey, name, year, uid FROM trainee_tbl ");
   $xtraset->execute();
   $xtraset->store_result();
   $xtraset->bind_result($trainkey, $name, $year, $uid);
   while ($xtraset->fetch()){
      // find uni
      $stmt = $mysqli->prepare("SELECT ident, university FROM uni_tbl");
      $stmt->execute();
      $stmt->store_result();
      $stmt->bind_result($ident, $university);
      $stmt->fetch();
      $stmt->close();
      echo "<p><a href=\"subsetdetail.php?addset=addset&amp;setkey=$which&amp;trainkey=$trainkey\" class=\"btn btn-purple\">$name ($ident - $year)</a></p>";
   }
   $numrows = $xtraset->num_rows;
   $xtraset->close();
}

?>
                        </div>
                        <div class="card-footer">
                           <div class="float-right">
                              <p><?php echo $numrows ?> Trainees</p>
                          </div>
                          <div class="float-left">
                              <p><small>Available trainees depend on your Admin Status</small></p>
                          </div>
                        </div>
                     </div>
               </div>
               <div class="col-xl-4">
                  <div class="card border-purple">
                     <div class="card-header bg-purple">
                        <div class="card-title">Trainee List</div>
                     </div>
                     <div class="card-body">
<?php
$tableset = $mysqli->prepare("SELECT trainkey, who_by, date_added FROM subset_link_tbl WHERE setkey = ?");
$tableset->bind_param("s", $which);
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($trainkey, $who_by, $date_added);
while ($tableset->fetch()){
   $date_modified = strtotime($date_modified);
   // who?
   $stmt = $mysqli->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ?");
   $stmt->bind_param("s", $trainkey);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($name);
   $stmt->fetch();
   $stmt->close();
   echo "<div class=\"btn-group mb-1\">";
   echo "   <a class=\"btn btn-purple\" href=\"traineedetail.php?which=$trainkey\">$name</a>";
   echo "   <button type=\"button\" class=\"btn btn-purple dropdown-toggle dropdown-toggle-split\" data-toggle=\"dropdown\" aria-expanded=\"false\">";
   echo "    <span class=\"sr-only\">Toggle Dropdown</span>";
   echo "   </button>";
   echo "   <div class=\"dropdown-menu\">";
   echo "      <a class=\"dropdown-item\" href=\"traineedetail.php?which=$trainkey\">Show $name</a>";
   echo "      <div class=\"dropdown-divider\"></div>";
   echo "      <a class=\"dropdown-item\" href=\"subsetdetail.php?addset=delete&amp;setkey=$which&amp;trainkey=$trainkey\">Delete Trainee from Subset</a>";
   echo "   </div>";
   echo "</div>";
}
$numrows = $tableset->num_rows;
$tableset->close();
?>
                     </div>
                     <div class="card-footer">
                        <div class="float-left">
                           <p><small>(View or delete from dropdown)</small></p>
                       </div>
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
   <script>
    $(document).ready(function (e) {
 $("#ProcessIcon").on('submit',(function(e) {
  e.preventDefault();
  $.ajax({
         url: "catphotoprocess.php",
   type: "POST",
   data:  new FormData(this),
   contentType: false,
         cache: false,
   processData:false,
   beforeSend : function()
   {
    $("#ProcessIconOutput").fadeOut();
    $("#err").fadeOut();
   },
   success: function(data)
      {
    if(data=='invalid')
    {
     // invalid file format.
     $("#err").html("Invalid File !").fadeIn();
    }
    else
    {
     // view uploaded file.
     $("#ProcessIconOutput").html(data).fadeIn();
 
    }
      },
     error: function(e) 
      {
    $("#err").html(e).fadeIn();
      }          
    });
 }));
});
</script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>