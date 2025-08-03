<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = 'Case Studies';
$subtitle = "Case Study";
$listurl = "projects.php"; # where the delete script is found
$listname = "Case Studies";
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
if ($delicon == "delicon" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete image ref
  $stmt = $mysqli->prepare("UPDATE events_tbl SET eventlogo = ? WHERE ID = ?"); 
  $stmt->bind_param("si", $valueblank, $which);
  $stmt->execute();
  $stmt->close();
}
if ($delicon == "delpdf" && ($admintype == 'AD' || $admintype == 'DV')) {
  // delete pdf ref
  $stmt = $mysqli->prepare("UPDATE events_tbl SET eventpdf = ? WHERE ID = ?"); 
  $stmt->bind_param("si", $valueblank, $which);
  $stmt->execute();
  $stmt->close();
}
if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $proj_name = isset($_POST['proj_name']) ? $_POST['proj_name'] : '';
  $page_title = isset($_POST['page_title']) ? $_POST['page_title'] : '';
  $page_txt1 = isset($_POST['page_txt1']) ? $_POST['page_txt1'] : '';
  $page_txt2 = isset($_POST['page_txt2']) ? $_POST['page_txt2'] : '';
  $page_txt3 = isset($_POST['page_txt3']) ? $_POST['page_txt3'] : '';
  $page_txt4 = isset($_POST['page_txt4']) ? $_POST['page_txt4'] : '';
  $page_txt5 = isset($_POST['page_txt5']) ? $_POST['page_txt5'] : '';
  $page_txt6 = isset($_POST['page_txt6']) ? $_POST['page_txt6'] : '';
  $page_txt12 = isset($_POST['page_txt12']) ? $_POST['page_txt12'] : '';
  $proj_vid = isset($_POST['proj_vid']) ? $_POST['proj_vid'] : '';
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  $google_priority = isset($_POST['google_priority']) ? $_POST['google_priority'] : 0;

  // Update record
  // note we don't change semantic_url else SEO is hurt
  $stmt = $mysqli->prepare("UPDATE proj_tbl SET proj_vid = ?, sort_order = ?, proj_name = ?, page_title = ?, page_txt1 = ?, page_txt2 = ?, page_txt3 = ?, page_txt4 = ?, page_txt5 = ?, page_txt6 = ?, page_txt12 = ?, google_priority = ?, date_modified = ? WHERE pid = ?"); 
  $stmt->bind_param("sissssssssssii", $proj_vid, $sort_order, $proj_name, $page_title, $page_txt1, $page_txt2, $page_txt3, $page_txt4, $page_txt5, $page_txt6, $page_txt12, $google_priority, $today, $which);
  $stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $stmt->close();

  // delete existing tags before re-adding
  $stmt = $mysqli->prepare("DELETE FROM proj_link_tbl WHERE pid = ? ");
  $stmt->bind_param("i",$which);     
  $stmt->execute();
  $stmt->close();
  
  // update subject tag links
  $tagstmt = $mysqli->prepare("SELECT catid FROM proj_cats");
  $tagstmt->execute();
  $tagstmt->store_result();
  $tagstmt->bind_result($catid);
  while ($tagstmt->fetch()){
    $posmarker = 'q'.$catid;
    $clicked = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
    if ($clicked == $catid)  {
      // if checkbox has same value add to db
      $insert_stmt = $mysqli->prepare("INSERT INTO proj_link_tbl (pid, catid) VALUES (?, ?)");
      $insert_stmt->bind_param("ii", $which, $catid);
      $insert_stmt->execute();
      $insert_stmt->close();
    }
  }
  $tagstmt->close();
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT proj_vid, sort_order, proj_name, semantic_url, page_title, page_txt1, page_txt2, page_txt3, page_txt4, page_txt5, page_txt6, page_txt12, google_priority, date_added, date_modified FROM proj_tbl WHERE pid =  ? ");
$stmt->bind_param("i", $which); 
$stmt->bind_result($proj_vid, $sort_order, $proj_name, $semantic_url, $page_title, $page_txt1, $page_txt2, $page_txt3, $page_txt4, $page_txt5, $page_txt6, $page_txt12, $google_priority, $date_added, $date_modified);
$stmt->execute();
$stmt->fetch();
//printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
$stmt->close();
$date_added = strtotime($date_added);
$date_modified = strtotime($date_modified);
// whatever the record name is
  $changename = " this Project";
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
                           <label class="col-form-label" for="proj_name">Project Name</label>
                           <input type="text" class="form-control" id="proj_name" name="proj_name" value="<?php echo $proj_name ?>" required>
                              <span class="form-text" id="txtHint">Project URL is https://www.thelggroup.co.uk/projects/<?php echo $semantic_url ?></span>
                          </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_title">Page Title</label>
                              <input class="form-control" type="text" id="page_title" name="page_title" value="<?php echo $page_title ?>">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="proj_vid">Vimeo Share Embed iFrame</label>
                              <textarea class="form-control" type="text" id="proj_vid" name="proj_vid"><?php echo $proj_vid ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt1">Text block 1</label>
                              <textarea class="form-control summernote" type="text" id="page_txt1" name="page_txt1"><?php echo $page_txt1 ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt2">Text block 2</label>
                              <textarea class="form-control summernote" type="text" id="page_txt2" name="page_txt2"><?php echo $page_txt2 ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt3">Text block 3</label>
                              <textarea class="form-control summernote" type="text" id="page_txt3" name="page_txt3"><?php echo $page_txt3 ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt4">Text block 4</label>
                              <textarea class="form-control summernote" type="text" id="page_txt4" name="page_txt4"><?php echo $page_txt4 ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt5">Text block 5</label>
                              <textarea class="form-control summernote" type="text" id="page_txt5" name="page_txt5"><?php echo $page_txt5 ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt6">Text block 6</label>
                              <textarea class="form-control summernote" type="text" id="page_txt6" name="page_txt6"><?php echo $page_txt6 ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt12">Admin Notes</label>
                              <textarea class="form-control summernote" type="text" id="page_txt12" name="page_txt12"><?php echo $page_txt12 ?></textarea>
                              <span class="form-text"><em>Admin notes not displayed on website</em></span>
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

                          <div class="form-group">
                            <label class="col-form-label">Project Categories</label>
                            <div class="col-lg-12">
                              <?PHP
                              $checked = '';
                              // Loop through cats 
                              $loopstmt = $mysqli->prepare("SELECT catid, catproj FROM proj_cats");
                              $loopstmt->execute();
                              $loopstmt->store_result();
                              $loopstmt->bind_result($catid, $catproj);
                              while ($loopstmt->fetch()) {
                                // see if in links table for this record
                                $whatlink = $mysqli->prepare("SELECT plid FROM proj_link_tbl WHERE pid = ? AND catid = ? ");
                                $whatlink->bind_param("ii", $which, $catid);
                                $whatlink->execute(); 
                                $whatlink->bind_result($plid);
                                $whatlink->fetch();
                                if ($plid > 0) {
                                  $checked = " checked=\"checked\" ";
                                } else {
                                  $checked = '';
                                }
                                $whatlink->close();
                                echo "<label class=\"checkbox-inline\"><input name=\"q$catid\" type=\"checkbox\" id=\"$catid\" value=\"$catid\" $checked> $catproj</label><br>\r";
                              }
                              $loopstmt->close();
                              ?>
                            </div>
                          </div>
                          
                        <div class="card-footer">
                           <div class="form-group">
                              <label class="col-form-label">Images</label>
                              <p>Images added in Page Content &gt; <a href="gallery">Gallery</a>. Select sort order '1' for main image on Project page.</p>
                          </div>
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
                
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
         url: "performanceprocess.php",
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
<script>
    $(document).ready(function (e) {
 $("#ProcessPDF").on('submit',(function(e) {
  e.preventDefault();
  $.ajax({
         url: "performancepdfprocess.php",
   type: "POST",
   data:  new FormData(this),
   contentType: false,
         cache: false,
   processData:false,
   beforeSend : function()
   {
    $("#ProcessPDFOutput").fadeOut();
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
     $("#ProcessPDFOutput").html(data).fadeIn();
 
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