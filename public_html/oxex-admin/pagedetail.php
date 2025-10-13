<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Page Content";

$subtitle = "Page content";
$listurl = "pages.php"; # where the delete script is found
$listname = "Pages";
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
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
if ($delicon == "delicon" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete image ref
  $stmt = $pdo->prepare("UPDATE pages_tbl SET image = ? WHERE pid = ?"); 
  $stmt->execute([$valueblank, $which]);
  $stmt->closeCursor();
}
if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $page_name = isset($_POST['page_name']) ? $_POST['page_name'] : '';
  $metatext = isset($_POST['metatext']) ? $_POST['metatext'] : '';
  $googleTitle = isset($_POST['googleTitle']) ? $_POST['googleTitle'] : '';
  $googleDesc = isset($_POST['googleDesc']) ? $_POST['googleDesc'] : '';
  $googleKeywords = isset($_POST['googleKeywords']) ? $_POST['googleKeywords'] : '';
  $bannerTitle = isset($_POST['bannerTitle']) ? $_POST['bannerTitle'] : '';
  $bannerTxt = isset($_POST['bannerTxt']) ? $_POST['bannerTxt'] : '';
  $justification = isset($_POST['justification']) ? $_POST['justification'] : '';
  $page_txt1 = isset($_POST['page_txt1']) ? $_POST['page_txt1'] : '';
  $page_txt2 = isset($_POST['page_txt2']) ? $_POST['page_txt2'] : '';
  $page_txt3 = isset($_POST['page_txt3']) ? $_POST['page_txt3'] : '';
  $page_txt4 = isset($_POST['page_txt4']) ? $_POST['page_txt4'] : '';
  $page_txt5 = isset($_POST['page_txt5']) ? $_POST['page_txt5'] : '';
  $page_txt6 = isset($_POST['page_txt6']) ? $_POST['page_txt6'] : '';
  $page_txt7 = isset($_POST['page_txt7']) ? $_POST['page_txt7'] : '';
  $page_txt8 = isset($_POST['page_txt8']) ? $_POST['page_txt8'] : '';
  $page_txt9 = isset($_POST['page_txt9']) ? $_POST['page_txt9'] : '';
  $page_txt10 = isset($_POST['page_txt10']) ? $_POST['page_txt10'] : '';
  $page_txt11 = isset($_POST['page_txt11']) ? $_POST['page_txt11'] : '';
  $page_txt12 = isset($_POST['page_txt12']) ? $_POST['page_txt12'] : '';
  $page_title = isset($_POST['page_title']) ? $_POST['page_title'] : '';
  $google_priority = isset($_POST['google_priority']) ? $_POST['google_priority'] : 0;
  
  // Update record
  $stmt = $pdo->prepare("UPDATE pages_tbl SET page_name = ?, metatext = ?, \"googleTitle\" = ?, \"googleDesc\" = ?, \"googleKeywords\" = ?, \"bannerTitle\" = ?, \"bannerTxt\" = ?, page_title = ?, page_txt1 = ?, page_txt2 = ?, page_txt3 = ?, page_txt4 = ?, page_txt5 = ?, page_txt6 = ?, page_txt7 = ?, page_txt8 = ?, page_txt9 = ?, page_txt10 = ?, page_txt11= ?, page_txt12 = ?, date_modified = ?, google_priority = ?, justification = ? WHERE pid = ? "); 
  $stmt->execute([$page_name, $metatext, $googleTitle, $googleDesc, $googleKeywords, $bannerTitle, $bannerTxt, $page_title, $page_txt1, $page_txt2, $page_txt3, $page_txt4, $page_txt5, $page_txt6, $page_txt7, $page_txt8, $page_txt9, $page_txt10, $page_txt11, $page_txt12, $today, $google_priority, $justification, $which]);
  $stmt->closeCursor();
}
?>
<?php
  // find the required record
$stmt = $pdo->prepare("SELECT filename, page_name, metatext, pages_tbl.\"googleTitle\", pages_tbl.\"googleDesc\", pages_tbl.\"googleKeywords\", pages_tbl.\"bannerTitle\", pages_tbl.\"bannerTxt\", justification, page_title, page_txt1, page_txt2, page_txt3, page_txt4, page_txt5, page_txt6, page_txt7, page_txt8, page_txt9, page_txt10, page_txt11, page_txt12, image, date_added, date_modified, google_priority FROM pages_tbl WHERE pid = ?");
$stmt->execute([$which]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$filename = $row['filename'];
$page_name = $row['page_name'];
$metatext = $row['metatext'];
$googleTitle = $row['googleTitle'];
$googleDesc = $row['googleDesc'];
$googleKeywords = $row['googleKeywords'];
$bannerTitle = $row['bannerTitle'];
$bannerTxt = $row['bannerTxt'];
$justification = $row['justification'];
$page_title = $row['page_title'];
$page_txt1 = $row['page_txt1'];
$page_txt2 = $row['page_txt2'];
$page_txt3 = $row['page_txt3'];
$page_txt4 = $row['page_txt4'];
$page_txt5 = $row['page_txt5'];
$page_txt6 = $row['page_txt6'];
$page_txt7 = $row['page_txt7'];
$page_txt8 = $row['page_txt8'];
$page_txt9 = $row['page_txt9'];
$page_txt10 = $row['page_txt10'];
$page_txt11 = $row['page_txt11'];
$page_txt12 = $row['page_txt12'];
$image = $row['image'];
$date_added = $row['date_added'];
$date_modified = $row['date_modified'];
$google_priority = $row['google_priority'];
$stmt->closeCursor();
  $date_added = strtotime($date_added);
  $date_modified = strtotime($date_modified);
// whatever the record name is
  $changename = " this Page";
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
               <div class="col-12 col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?></div>
                        </div>
                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="page_name">Page Name</label>
                              <input type="text" class="form-control" name="page_name" value="<?php echo $page_name ?>" required>
                              <span class="form-text">This is for filename <?php echo $filename ?></span>
                            </div>
                          <div class="form-group">
                              <label class="col-form-label" for="page_title">Page Title (H1 Font)</label>
                              <input type="text" class="form-control"  name="page_title" value="<?php echo $page_title ?>">
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Larger Fixed Size Text (if used)</label>
                              <textarea id="page_txt1" name="page_txt1" class="form-control"><?php echo $page_txt1 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Second Text Block</label>
                              <textarea name="page_txt2" id="page_txt2" class="form-control summernote"><?php echo $page_txt2 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Third Text Block</label>
                              <textarea name="page_txt3" id="page_txt3" class="form-control summernote"><?php echo $page_txt3 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Fourth Text Block</label>
                              <textarea name="page_txt4" id="page_txt4" class="form-control summernote"><?php echo $page_txt4 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Fifth Text Block</label>
                              <textarea name="page_txt5" id="page_txt5" class="form-control summernote"><?php echo $page_txt5 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Sixth Text Block</label>
                              <textarea name="page_txt6" id="page_txt6" class="form-control summernote"><?php echo $page_txt6 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Seventh Text Block</label>
                              <textarea name="page_txt7" id="page_txt7" class="form-control summernote"><?php echo $page_txt7 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Eighth Text Block</label>
                              <textarea name="page_txt8" id="page_txt8" class="form-control summernote"><?php echo $page_txt8 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Ninth Text Block</label>
                              <textarea name="page_txt9" id="page_txt9" class="form-control summernote"><?php echo $page_txt9 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Tenth Text Block</label>
                              <textarea name="page_txt10" id="page_txt10" class="form-control summernote"><?php echo $page_txt10 ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Eleventh Text Block</label>
                              <textarea name="page_txt11" id="page_txt11" class="form-control summernote"><?php echo $page_txt11 ?></textarea>
                          </div>
                          
                        <h6 class="mt-3">SEO Stuff</h6>
                          <div class="form-group">
                            <label class="col-form-label" for="googleTitle">Meta Title</label>
                            <input type="text" class="form-control" name="googleTitle" value="<?php echo $googleTitle ?>" required>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="googleDesc">Meta Description</label>
                            <input type="text" class="form-control" name="googleDesc" value="<?php echo $googleDesc ?>" required>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="googleKeywords">Meta Keywords</label>
                              <input type="text" class="form-control" name="googleKeywords" value="<?php echo $googleKeywords ?>" required>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="google_priority">Google Priority (0.1 to 1.0)</label>
                              <input type="number" class="form-control" name="google_priority" value="<?php echo $google_priority ?>" min="0" max="1.0" step="0.1" required>
                              <span class="form-text">Enter '0' to omit page from site-map</span>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Admin Comments</label>
                              <textarea name="page_txt12" id="page_txt12" class="form-control summernote"><?php echo $page_txt12 ?></textarea>
                          </div>
                          <h6 class="mt-3">Banner Text</h6>
                          <div class="form-group">
                            <label class="col-form-label" for="bannerTitle">Banner Title</label>
                            <input type="text" class="form-control" id="bannerTitle"  name="bannerTitle" value="<?php echo $bannerTitle ?>">
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="bannerTxt">Banner Sub-text</label>
                            <input type="text" class="form-control" id="bannerTxt" name="bannerTxt" value="<?php echo $bannerTxt ?>">
                          </div>
                          <div class="form-group">
                             <label class="col-form-label" for="justification">Justification</label>
                              <div class="col-md-10">
                                 <select class="custom-select" id="justification" name="justification">
                                   <option <?php if ($justification == 1) echo 'selected="selected"' ?> value="1">Centre</option>
                                   <option <?php if ($justification == 0) echo 'selected="selected"' ?> value="0">Left</option>
                                   <option <?php if ($justification == 2) echo 'selected="selected"' ?> value="2">Right</option>
                                 </select>
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

               <div class="col-12 col-xl-4">
                  <div class="card border-info">
                     <div class="card-header bg-info">
                        <div class="card-title">Banner </div>
                     </div>
                     <div class="card-body">
                        <form action="pagephoto.php" method="post" id="ProcessIcon" name="ProcessIcon" class="form-horizontal" enctype="multipart/form-data">
                           <?PHP
                           echo '<div class="form-group">';
                           echo '<div class="col-lg-8">';
                           echo '<input type="file" name="Image1" id="Image1" />';
                           echo '</div>';
                           echo '</div>';
                           echo '<div class="form-group">';
                           echo '<input name="-Nothing" type="submit" class="btn btn-success" value="Load" />';
                           echo "<input name=\"which\" type=\"hidden\" id=\"which\" value=\"$which\" />";
                           echo '</div>';
                           ?>
                        </form>
                        <div id="ProcessIconOutput">
                           <?PHP
                           if ($image != '') {
                              echo "<p><a href=\"pagedetail.php?which=$which&amp;delicon=delicon\"><span class=\"btn btn-danger\">Delete Image</span></a></p>";
                              echo "<p><img src=\"../banner/$image\" width=\"200px\"></p>";
                           }
                           ?>
                           <!-- space for Ajax result -->
                        </div>
                        <div id="err"></div>
                     </div>
                     <div class="card-footer">
                        <small class="text-muted">Upload banner image for this page</small>
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
            url: "pageprocess.php",
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