<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Events";
$subtitle = "Page content";
$listurl = "events.php"; # where the delete script is found
$listname = "Events";
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
   <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
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
  $e_title = isset($_POST['e_title']) ? $_POST['e_title'] : '';
  $date_text = isset($_POST['date_text']) ? $_POST['date_text'] : '';
  $isdev = isset($_POST['isdev']) ? $_POST['isdev'] : 0;
  $e_txt = isset($_POST['e_txt']) ? $_POST['e_txt'] : '';
  $e_url = isset($_POST['e_url']) ? $_POST['e_url'] : '';
  $e_loc = isset($_POST['e_loc']) ? $_POST['e_loc'] : '';
   $e_link = isset($_POST['e_link']) ? $_POST['e_link'] : '';

   // get dates, remove slashes and convert from ddmmyyyy to yyyymmdd
  $datefrom = isset($_POST['datefrom']) ? $_POST['datefrom'] : '';
    $datefrom = preg_replace("/[^0-9]/", "", $datefrom);
    $fromddd = substr($datefrom, 0, 2);
    $frommm = substr($datefrom, 2, 2);
    $fromyyyy = substr($datefrom, 4, 4);
    $date1 = $fromyyyy.$frommm.$fromddd;
  $dateto = isset($_POST['dateto']) ? $_POST['dateto'] : '';
    $dateto = preg_replace("/[^0-9]/", "", $dateto);
    $toddd = substr($dateto, 0, 2);
    $tomm = substr($dateto, 2, 2);
    $toyyyy = substr($dateto, 4, 4);
    $date2 = $toyyyy.$tomm.$toddd;
    // just in case they've got the dates the wrong way round...
    
    if ($date2 > $date1) {
      $datefrom = $date1;
      $dateto = $date2;
      $dispdatefrom = strtotime($datefrom);
      $dispdateto = strtotime($dateto);
    } else {
      $datefrom = $date2;
      $dateto = $date1;
      $dispdatefrom = strtotime($datefrom);
      $dispdateto = strtotime($dateto);
    }

  // Update record
  $stmt = $mysqli->prepare("UPDATE events_tbl SET e_title = ?, date_text = ?, isdev = ?, e_txt = ?, e_url = ?, e_loc = ?, date_start = ?, date_end = ?, e_link = ? WHERE eid = ?"); 
  $stmt->bind_param("ssisssiisi", $e_title, $date_text, $isdev, $e_txt, $e_url, $e_loc, $datefrom, $dateto, $e_link, $which);
  $stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $stmt->close();
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT e_title, date_start, date_end, date_text, e_loc, e_txt, e_url, e_link, isdev FROM events_tbl WHERE eid =  ? ");
$stmt->bind_param("i", $which); 
$stmt->bind_result($e_title, $date_start, $date_end, $date_text, $e_loc, $e_txt, $e_url, $e_link, $isdev);
$stmt->execute();
$stmt->fetch();
$stmt->close();
$dispdatefrom = strtotime($date_start);
$dispdateto = strtotime($date_end);

// whatever the record name is
  $changename = " this Link";
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
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?></div>
                        </div>
                        <div class="card-body">
                          <div class="form-group">
                              <label class="col-form-label" for="e_title">Event Title</label>
                              <input class="form-control" type="text" id="e_title" name="e_title" value="<?php echo $e_title ?>" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="e_url">Event URL</label>
                              <input class="form-control" type="text" id="e_url" name="e_url"  value="<?php echo $e_url ?>" >
                              <span class="form-text" id="txtHint">Include the prefix http:// or https://</span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="e_link">Text for Link</label>
                              <input class="form-control" type="text" id="e_link" name="e_link" value="<?php echo $e_link ?>">
                              <span class="form-text">e.g. 'More Information' or can be the URL</span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="e_txt">Further Information</label>
                              <textarea class="form-control" type="text" id="e_txt" name="e_txt"><?php echo $e_txt ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="e_loc">Location</label>
                              <textarea class="form-control" type="text" id="e_loc" name="e_loc"><?php echo $e_loc ?></textarea>
                           </div>
                           <div class="form-group">
                            <label for="datepicker">Date Start</label>
                            <input type="text" class="form-control" id="datepicker" name="datefrom" placeholder="dd-mm-yyyy" value="<?php echo date('d-m-Y', $dispdatefrom) ?>" required>
                          </div>
                          <div class="form-group">
                            <label for="datepicker2">Date End</label>
                            <input type="text" class="form-control" id="datepicker2" name="dateto" placeholder="dd-mm-yyyy" value="<?php echo date('d-m-Y', $dispdateto) ?>" required>
                          </div>
                           <div class="form-group">
                              <label class="col-form-label" for="date_text">Date/Time Text</label>
                              <textarea class="form-control" type="text" id="date_text" name="date_text"><?php echo $date_text ?></textarea>
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
                            <a href="<?php echo $listurl ?>?del=del&amp;which=<?php echo $which ?>" class="btn btn-labeled btn-danger" role="button"><span class="btn-label"><i class="fa fa-times"></i></span>Delete now!</a>
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
         url: "activityprocess.php",
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
  <script>
  jQuery(document).ready(function($) {
    $( function() {
      $( "#datepicker" ).datepicker({
        dateFormat: "dd-mm-yy"
      });
    });
    $( function() {
      $( "#datepicker2" ).datepicker({
        dateFormat: "dd-mm-yy"
      });
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