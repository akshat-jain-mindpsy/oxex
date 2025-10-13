<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Trainee Subsets";

// Ensure $today is defined (fallback if not set by config)
if (!isset($today) || empty($today)) {
   $today = date('Ymd');
}

setAdminVars(3); // Tables section
$subtitle = "Subsets";
$listurl = "subsets.php";
$listname = "Subsets";
// this page lists subsets and allows trainees to be added or removed
// Supervisors/tutors can only use Trainees allocated to them
// Oxford Admins can see any Oxford Trainee
// Exeter Admins can see any Exeter Trainee
// Super Admins & Devs can see all
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
$del = isset($_GET['del']) ? $_GET['del'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : '';
$addset = isset($_GET['addset']) ? $_GET['addset'] : ''; # add trainee to set

if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row (PostgreSQL: no LIMIT in DELETE)
  $stmt = $pdo->prepare("DELETE FROM subset_tbl WHERE setkey = ?");
  $stmt->execute([$which]); 
  $stmt->closeCursor();
  // delete all trainee links for this subset
  $stmt = $pdo->prepare("DELETE FROM subset_link_tbl WHERE setkey = ?");
  $stmt->execute([$which]); 
  $stmt->closeCursor();
}
$subsetmsg = "";
if ($addset == "addset") {
   // add trainee to a subset
   // access controlled by listing of links
   $setkey = isset($_GET['setkey']) ? $_GET['setkey'] : '';
   $trainkey = isset($_GET['trainkey']) ? $_GET['trainkey'] : '';
   if ($setkey != '' && $trainkey != '') {
      // check if this trainee is alreday in the subset
      $vids = $pdo->prepare("SELECT slid FROM subset_link_tbl WHERE setkey = ? AND trainkey = ? ");
      $vids->execute([$setkey, $trainkey]);
      $numlinks = $vids->rowCount();
      $vids->closeCursor();
      if ($numlinks == 0) {
         // ok to create
         $subsetmsg = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-success\" role=\"alert\">Trainee added to the selected Subset</div></div></div>";
         $insert_stmt = $pdo->prepare("INSERT INTO subset_link_tbl (setkey, trainkey, who_by, date_added) VALUES (?, ?, ?, ?)");
         $insert_stmt->execute([$setkey, $trainkey, $usrkey, $today]);
         $insert_stmt->closeCursor();
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
  // PostgreSQL: no LIMIT in DELETE
  $stmt = $pdo->prepare("DELETE FROM subset_link_tbl WHERE setkey = ? AND trainkey = ?");
   $stmt->execute([$setkey, $trainkey]); 
   $stmt->closeCursor();
   $subsetmsg = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\">Trainee deleted from Subset</div></div></div>";
   $which = $setkey; # to set up next view
}

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : ''; # 32 char str
  $subset = isset($_POST['subset']) ? $_POST['subset'] : '';
  $description = isset($_POST['description']) ? $_POST['description'] : '';
  
  // Update record
  $stmt = $pdo->prepare("UPDATE subset_tbl SET subset = ?, description = ?, who_by = ?, date_modified = ? WHERE setkey = ? "); 
  $stmt->execute([$subset, $description, $usrkey, $today, $which]);
  $stmt->closeCursor();
}
?>
<?php
  // find the required record
$stmt = $pdo->prepare("SELECT subset, description, who_by, date_added, date_modified FROM subset_tbl WHERE setkey = ?");
$stmt->execute([$which]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
  $subset = $row['subset'];
  $description = $row['description'];
  $who_by = $row['who_by'];
  $date_added = $row['date_added'];
  $date_modified = $row['date_modified'];
}
$stmt->closeCursor();
   $date_added = strtotime($date_added);
   $date_modified = strtotime($date_modified);
   // who changed last?
   $stmt = $pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->execute([$who_by]);
   $who_by = $stmt->fetchColumn();
   $stmt->closeCursor();
   
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
                           <button class="btn btn-purple" type="button" data-toggle="collapse" data-target="#addTraineeCollapse" aria-expanded="false" aria-controls="addTraineeCollapse">
                              Add trainee...
                           </button>
                           <div class="collapse mt-3" id="addTraineeCollapse">
                              <div class="form-group">
                                 <input class="form-control" type="text" id="traineeSearch" placeholder="Search trainee by name...">
                                 <small class="form-text text-muted">Start typing to search. Results are limited to 20.</small>
                              </div>
                              <div id="traineeResults">
                                 <p class="text-muted"><small>Type a name to search.</small></p>
                              </div>
                           </div>
                        </div>
                        <div class="card-footer">
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
$tableset = $pdo->prepare("SELECT trainkey, who_by, date_added FROM subset_link_tbl WHERE setkey = ?");
$tableset->execute([$which]);
while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
   $trainkey = $row['trainkey'];
   $who_by = $row['who_by'];
   $date_added = $row['date_added'];
   $date_modified = strtotime($date_modified);
   // who?
   $stmt = $pdo->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ?");
   $stmt->execute([$trainkey]);
   $name = $stmt->fetchColumn();
   $stmt->closeCursor();
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
$numrows = $tableset->rowCount();
$tableset->closeCursor();
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
                     </div><!-- END card-->
</div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script>
   $(document).ready(function() {
      if ($('#maintable').length) {
         if ($.fn.DataTable) {
            $('#maintable').DataTable({ "pageLength": 10 });
         } else if ($.fn.dataTable) {
            $('#maintable').dataTable({ "pageLength": 10 });
         }
      }
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
      // Lazy search - debounce input and fetch
      var searchTimeout;
      $('#addTraineeCollapse').on('shown.bs.collapse', function() {
         $('#traineeSearch').trigger('focus');
      });
      $('#traineeSearch').on('input', function() {
         var query = $(this).val().trim();
         clearTimeout(searchTimeout);
         if (query.length < 2) {
            $('#traineeResults').html('<p class="text-muted"><small>Type at least 2 characters to search.</small></p>');
            return;
         }
         $('#traineeResults').html('<p class="text-info"><small>Searching...</small></p>');
         searchTimeout = setTimeout(function() {
            $.ajax({
               url: 'trainee_search.php',
               type: 'GET',
               dataType: 'json',
               cache: false,
               data: { q: query },
               success: function(resp) {
                  if (!resp || !resp.success) {
                     $('#traineeResults').html('<p class="text-danger"><small>Error searching trainees.</small></p>');
                     return;
                  }
                  var results = resp.results || [];
                  if (results.length === 0) {
                     $('#traineeResults').html('<p class="text-muted"><small>No trainees found.</small></p>');
                     return;
                  }
                  var html = '<div class="list-group">';
                  results.forEach(function(item){
                     var addUrl = 'subsetdetail.php?addset=addset&setkey=<?php echo $which ?>' + '&trainkey=' + encodeURIComponent(item.trainkey);
                     html += '<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="' + addUrl + '"><span>' + item.name + ' (' + item.year + ')</span><span class="badge badge-purple">Add</span></a>';
                  });
                  html += '</div>';
                  $('#traineeResults').html(html);
               },
               error: function() {
                  $('#traineeResults').html('<p class="text-danger"><small>Network error while searching.</small></p>');
               }
            });
         }, 300);
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