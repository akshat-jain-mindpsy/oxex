<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Events";

setAdminVars(0); // Dashboard section
$subtitle = "Page content";
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
   <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
</head>
<?php 
$sort_order = 1;
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AD' || $admintype == 'DV')) {
  // delete row from detail page
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdo) {
    $stmt = $pdo->prepare("DELETE FROM links_tbl WHERE lid = ? LIMIT 1");
    $stmt->execute([$which]);
    if ($stmt->rowCount() > 0) {
      // show message when deleting, not refreshing
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
    }
  }
}

if ($newadmin == 'newadmin') {
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

  // write new record
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdo) {
    $insert_stmt = $pdo->prepare("INSERT INTO events_tbl (e_title, date_start, date_end, date_text, e_loc, e_txt, e_url, e_link, isdev) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert_stmt->execute([$e_title, $datefrom, $dateto, $date_text, $e_loc, $e_txt, $e_url, $e_link, $isdev]);
    $newid = $pdo->lastInsertId();
  }  
}
$todaydisp = strtotime($today); # default 'to' date for datepicker
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
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New <?php echo $subtitle ?></a></div>

setAdminVars(0); // Dashboard section            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Event Title</th>
                                  <th>Date Start</th>
                                  <th>Date End</th>
                                  <th>Location</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
  $stmt = $pdo->prepare("SELECT eid, e_title, date_start, date_end, e_loc FROM events_tbl");
  $stmt->execute();
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $eid = $row['eid'];
    $e_title = $row['e_title'];
    $date_start = $row['date_start'];
    $date_end = $row['date_end'];
    $e_loc = $row['e_loc'];
    $date_start = strtotime($date_start);
    $date_end = strtotime($date_end);
    if ($sort_order == 0) {
     $sort_order = '<div class="badge badge-danger">Not displayed</div>';
   }
?>
<tr>
   <td><a href="eventdetail.php?which=<?php echo $eid ?>"><?php echo $e_title ?></a></td>
   <td><?php echo date('d/m/Y', $date_start) ?></td>
   <td><?php echo date('d/m/Y', $date_end) ?></td>
   <td><?php echo $e_loc ?></td>

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
               <div class="col-xl-8">
                  <form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Event</div>
                        </div>

                        <div class="card-body">
                          <div class="form-group">
                              <label class="col-form-label" for="e_title">Event Title</label>
                              <input class="form-control" type="text" id="e_title" name="e_title" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="e_url">Event URL</label>
                              <input class="form-control" type="text" id="e_url" name="e_url" >
                              <span class="form-text" id="txtHint">Include the prefix http:// or https://</span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="e_link">Text for Link</label>
                              <input class="form-control" type="text" id="e_link" name="e_link" >
                              <span class="form-text">e.g. 'More Information' or can be the URL</span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="e_txt">Further Information</label>
                              <textarea class="form-control" type="text" id="e_txt" name="e_txt"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="e_loc">Location</label>
                              <textarea class="form-control" type="text" id="e_loc" name="e_loc"></textarea>
                           </div>
                           <div class="form-group">
                            <label for="datepicker">Date Start</label>
                            <input type="text" class="form-control" id="datepicker" name="datefrom" placeholder="dd-mm-yyyy" value="<?php echo date('d-m-Y', $todaydisp) ?>" required>
                          </div>
                          <div class="form-group">
                            <label for="datepicker2">Date End</label>
                            <input type="text" class="form-control" id="datepicker2" name="dateto" placeholder="dd-mm-yyyy" value="<?php echo date('d-m-Y', $todaydisp) ?>" required>
                          </div>
                           <div class="form-group">
                              <label class="col-form-label" for="date_text">Date/Time Text</label>
                              <textarea class="form-control" type="text" id="date_text" name="date_text"><?php echo $date_text ?></textarea>
                           </div>
                           <div class="form-group row">
                            <label class="col-md-2 col-form-label" for="isdev">Public View?</label>
                             <div class="col-md-10">
                                <select class="custom-select custom-select-lg mb-3" id="isdev" name="isdev" required>
                                  <option value="1">Dev only</option>
                                  <option selected="selected" value="0">Public</option>
                                </select>
</div>
                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
</div><!-- END card-->
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
    function showUser(str) {
        if (str == "") {
            document.getElementById("txtHint").innerHTML = "";
            return;
        } else { 
            if (window.XMLHttpRequest) {
                // code for IE7+, Firefox, Chrome, Opera, Safari
                xmlhttp = new XMLHttpRequest();
            } 
            xmlhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("txtHint").innerHTML = this.responseText;
                }
            };
            xmlhttp.open("GET","querystrcase.php?q="+str,true);
            xmlhttp.send();
        }
    }
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