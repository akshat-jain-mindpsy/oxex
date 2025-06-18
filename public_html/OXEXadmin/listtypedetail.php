<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = 'Table Fields (Column Headings)';
$subtitle = "Admin";
$listurl = "listtypes.php"; # where the delete script is found
$listname = "Table Field";
if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
</head>
<?php 
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;

   $str = isset($_POST['str']) ? $_POST['str'] : '';
   $single = isset($_POST['single']) ? $_POST['single'] : 0;
   $musthave = isset($_POST['musthave']) ? $_POST['musthave'] : 0;
   $wouldlike = isset($_POST['wouldlike']) ? $_POST['wouldlike'] : 0;
   $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
   $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : null;
   

  // Update record including section_id
  $stmt = $mysqli->prepare("UPDATE select_types SET str = ?, single = ?, musthave = ?, wouldlike = ?, sort_order = ?, section_id = ? WHERE stid = ?"); 
    $stmt->bind_param("siiiisi", $str, $single, $musthave, $wouldlike, $sort_order, $section_id, $which);
    $stmt->execute();
    $anyerror = $mysqli->errno." ".$mysqli->error;
    $stmt->close();

}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT single, str, musthave, wouldlike, sort_order, section_id FROM select_types WHERE stid = ?");
$stmt->bind_param("i", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($single, $str, $musthave, $wouldlike, $sort_order, $current_section_id);
$stmt->fetch();
$stmt->close();

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

            <div class="row">
                <div class="col-12">
                    <!-- Amend Card -->
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                        <div class="card border-info mb-4">
                            <div class="card-header bg-info">
                                <div class="card-title">Amend "<?php echo $str ?>"</div>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="col-form-label" for="str">Field Name</label>
                                    <input class="form-control" type="text" id="str" name="str" value="<?php echo $str ?>" required>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <div class="form-group">
                                            <label class="col-form-label" for="single">Field Value Type</label>
                                            <select class="custom-select custom-select mb-3" id="single" name="single">
                                              <option <?php if ($single == '0') echo "selected" ?> value="0">Single Select</option>
                                              <option <?php if ($single == '1') echo "selected" ?> value="1">Multi Select</option>
                                              <option <?php if ($single == '2') echo "selected" ?> value="2">Text</option>
                                              <option <?php if ($single == '4') echo "selected" ?> value="4">Numeric (step 0.1)</option>
                                              <option <?php if ($single == '5') echo "selected" ?> value="5">Numeric (step integer)</option>
                                              <option <?php if ($single == '3') echo "selected" ?> value="3">Date</option>
                                              <option <?php if ($single == '6') echo "selected" ?> value="6">Time</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <h6>Reporting Data</h6>
                                <p>here we select competencies trainees must achieve/complete by the end of their three years of training. We also record how many different values are required for completion, if applicable (0 otherwise)</p>
                                <div class="row">
                                    <div class="col">
                                        <div class="form-group">
                                            <label class="col-form-label" for="musthave">Must Complete?</label>
                                            <select class="custom-select custom-select mb-3" id="musthave" name="musthave">
                                              <option <?php if ($musthave == '0') echo "selected" ?> value="0">No</option>
                                              <option <?php if ($musthave == '1') echo "selected" ?> value="1">Yes</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col">
                                     <div class="form-group">
                                        <label class="col-form-label" for="wouldlike">Qty  values to reach</label>
                                        <input class="form-control" type="number" id="wouldlike" name="wouldlike" min="0" step="1" value="<?php echo $wouldlike ?>" >
                                     </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <div class="form-group">
                                            <label class="col-form-label" for="section_id">Field Section</label>
                                            <select class="custom-select custom-select mb-3" id="section_id" name="section_id">
                                                <option value="">No Section</option>
                                                <?php
                                                $section_stmt = $mysqli->prepare("SELECT section_id, section_name FROM field_sections ORDER BY section_order ASC");
                                                $section_stmt->execute();
                                                $section_stmt->bind_result($section_id, $section_name);
                                                while ($section_stmt->fetch()) {
                                                    $selected = ($current_section_id == $section_id) ? 'selected' : '';
                                                    echo "<option value=\"$section_id\" $selected>$section_name</option>";
                                                }
                                                $section_stmt->close();
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Assign this field to a section in the logbook form</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <input type="hidden" name="done" value="done">
                                <input type="hidden" name="which" value="<?PHP echo $which ?>">
                                <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                            </div>
                        </div>
                    </form>

                    <!-- Values List Card -->
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info">
                            <div class="card-title">Values for this list</div>
                        </div>
                        <div class="card-body">
                            <?php
                            // find all values for this list
                            $tableset = $mysqli->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ? ORDER BY select_val");
                            $tableset->bind_param("i", $which);
                            $tableset->execute();
                            $tableset->store_result();
                            $tableset->bind_result($pid, $select_val);
                            while ($tableset->fetch()){
                                echo "<a href=\"listdetail.php?which=$pid\" class=\"btn btn-primary mb-1 mr-1 d-inline-block\">$select_val</a>";
                            }
                            $tableset->close();
                            ?>
                            <div id="err"></div>
                        </div>
                        <div class="card-footer">
                        </div>
                    </div>

                    <!-- Delete Card -->
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <div class="card-title">Delete <?php echo $listname ?></div>
                        </div>
                        <div class="card-footer">
                            <div class="float-right">
                                <a href="<?php echo $listurl ?>?del=del&amp;which=<?php echo $which ?>" 
                                   class="btn btn-labeled btn-danger" 
                                   role="button" 
                                   onclick="return confirm('Are you sure you want to delete this report and all associated data?')">
                                    <span class="btn-label"><i class="fa fa-times"></i></span>Delete now!
                                </a>
                            </div>
                        </div>
                    </div>
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
              keepHtml: false, // Remove all Html formats
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
         url: "testimonialphotoprocess.php",
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

<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>

</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>