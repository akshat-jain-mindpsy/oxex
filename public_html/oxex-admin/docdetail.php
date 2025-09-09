<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Documentataion";
$subtitle = "Docs";
$listurl = "docs.php";
$listname = "Docs";
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
   $doc_section = isset($_POST['doc_section']) ? $_POST['doc_section'] : 0;
   $sect_title = isset($_POST['sect_title']) ? $_POST['sect_title'] : '';
   $sect_txt = isset($_POST['sect_txt']) ? $_POST['sect_txt'] : '';
   $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  
  // Update record
  $stmt = $mysqli->prepare("UPDATE docs_tbl SET doc_section = ?, sect_title = ?, sect_txt = ?,  sort_order = ?, date_modified = ?, who_by = ? WHERE did = ? "); 
  $stmt->bind_param("sssiisi", $doc_section, $sect_title, $sect_txt, $sort_order, $today, $usrkey, $which);
  $stmt->execute();
  $stmt->close();
}
// doc_section, sect_title, sect_txt, sort_order, who_by, date_added, date_modified
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT doc_section, sect_title, sect_txt, sort_order, who_by, date_added, date_modified FROM docs_tbl WHERE did = ?");
$stmt->bind_param("i", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($doc_section, $sect_title, $sect_txt, $sort_order, $who_by, $date_added, $date_modified);
$stmt->fetch();
$stmt->close();
// whatever the record name is
  $changename = "$sect_title";
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
                           <p>Created on: <?php echo date("D jS M Y", $date_added) ?> and last modified on <?php echo date("D jS M Y", $date_modified) ?> by <?php echo $who_by ?></p>
                           <div class="form-group">
                              <label class="col-form-label" for="sect_title">Title</label>
                              <input class="form-control" type="text" id="sect_title" name="sect_title" value="<?php echo $sect_title ?>" required>
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
                                 <textarea name="sect_txt" id="sect_txt" class="form-control summernote" required><?php echo $sect_txt ?></textarea>
                             </div>


                           
                           <div class="form-group">
                              <label class="col-form-label" for="sort_order"> Sort order</label>
                              <div class="col-lg-3">
                                <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>">
                              </div>
                              <div class="col-lg-12">
                                <p><small>(Enter 0 if post is not to be immediately displayed)</small></p>
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
   <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js" integrity="sha256-eTyxS0rkjpLEo16uXTS0uVCS4815lc40K2iVpWDvdSY=" crossorigin="anonymous"></script>
<script type="text/javascript">
   /* sort table field list and update table */
   /* oxex admin sheetdetail.php */
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
         url:"sheetdetailsort.php",
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