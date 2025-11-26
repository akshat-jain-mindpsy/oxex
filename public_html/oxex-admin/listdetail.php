<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = 'Dropdown List Values';

setAdminVars(0); // Dashboard section
$subtitle = "Admin";
$listurl = "items.php"; # list overview + delete handler
$listname = "List value";
$which = isset($_GET['which']) ? (int)$_GET['which'] : 0;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$hasAccess = login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV');

if ($del === 'del' && $hasAccess && $which > 0) {
  $pdoDelete = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdoDelete) {
    $stmt = $pdoDelete->prepare("DELETE FROM select_gen WHERE pid = ?");
    $stmt->execute([$which]);
  }
  header("Location: " . $listurl . "?deleted=1");
  exit;
}

if($hasAccess) {
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
// initialise defaults to avoid undefined variable notices
$thisstid = 0;
$select_type = '';
$select_val = '';

// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
if ($delicon == "delicon" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete image ref
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdo) {
    $stmt = $pdo->prepare("UPDATE select_gen SET image = ? WHERE pid = ?"); 
    $stmt->execute([$valueblank, $which]);
  }
}

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;

  $select_type = isset($_POST['select_type']) ? $_POST['select_type'] : '';
  $select_val = isset($_POST['select_val']) ? $_POST['select_val'] : 0;
  // get single/multiple from select_types

  // Update record
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdo) {
    $stmt = $pdo->prepare("UPDATE select_gen SET select_type = ?, select_val = ? WHERE pid = ?"); 
    $stmt->execute([$select_type, $select_val, $which]);
    $anyerror = $stmt->errorInfo();
  }

}
?>
<?php
  // find the required record
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
  $stmt = $pdo->prepare("SELECT stid, select_type, select_val FROM select_gen WHERE pid = ?");
  $stmt->execute([$which]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $thisstid = $row['stid'];
    $select_type = $row['select_type'];
    $select_val = $row['select_val'];
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
           <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small></div>
            </div>

            <div class="row">
                <div class="col-12">
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                        <!-- START card-->
                        <div class="card border-info mb-4">
                            <div class="card-header bg-info">
                                <div class="card-title">Amend <?php echo $listname ?></div>
                            </div>
                            <div class="card-body">
                               <div class="form-group">
                                  <label class="col-form-label" for="select_val">List Value</label>
                                  <input class="form-control" type="text" id="select_val" name="select_val" value="<?php echo $select_val ?>" required>
                               </div>
                               <div class="row">

                                    <div class="col">
                                        <div class="form-group">
                                            <label class="col-form-label" for="select_type">Dropdown Name</label>
                                            <select class="custom-select custom-select-lg mb-3" id="select_type" name="select_type" required>
                                              <option <?php if ($select_type == '') echo "selected='selected'" ?> value="">Select...</option>
                                              <?php
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
  $tableset = $pdo->prepare("SELECT str, single, stid FROM select_types ORDER BY str ");
  $tableset->execute();
  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
    $str = $row['str'];
    $single = $row['single'];
    $stid = $row['stid'];
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
   echo "<option value=\"$str\"";
   if ($thisstid == $stid) {
      echo "selected='selected'";
   }
   echo ">$str ($listtype)</option>";
  }
}                                         
                                          
                                           ?>
                                       </select>
</div>
</div>
                            
                            </div><!-- /.row -->
                            </div><!-- /.card-body -->
                            <div class="card-footer">
                               <input type="hidden" name="done" value="done">
                               <input type="hidden" name="which" value="<?PHP echo $which ?>">
                               <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
</div><!-- END card-footer -->
                        </div><!-- END card -->
                    </form>
                <!-- keep column open for subsequent cards -->

            <!-- Values card -->
            <div class="card border-info mb-4">
                <div class="card-header bg-info">
                    <div class="card-title">Values for this list</div>
                </div>
                <div class="card-body">
                    <?php
                    // find all values for this list
                    $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
                    if ($pdo) {
                      $tableset = $pdo->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ? ORDER BY select_val");
                      $tableset->execute([$which]);
                      while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
                        $pid = $row['pid'];
                        $select_val = $row['select_val'];
                        echo "<a href=\"listdetail.php?which=$pid\" class=\"btn btn-primary mb-1 mr-1 d-inline-block\">$select_val</a>";
                      }
                    }
                    ?>
                    <div id="err"></div>
                </div>
                <div class="card-footer"></div>
            </div><!-- END Values card -->

            <!-- Delete card -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <div class="card-title">Delete <?php echo $listname ?></div>
                </div>
                <div class="card-footer">
                    <div class="float-right">
                        <a href="<?php echo $listurl ?>?del=del&amp;which=<?php echo $which ?>" class="btn btn-labeled btn-danger" role="button" onclick="return confirm('Are you sure you want to delete this report and all associated data?')">
                            <span class="btn-label"><i class="fa fa-times"></i></span>Delete now!
                        </a>
                    </div>
                </div>
            </div><!-- END Delete card -->

            </div><!-- /.col-12 -->
        </div><!-- /.row -->
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