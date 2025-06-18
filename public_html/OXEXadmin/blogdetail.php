<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Blog";
$subtitle = "Blog";
$listurl = "blog.php";
$listname = "Blog";
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
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
if ($delicon == "delicon" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete image ref
  $stmt = $mysqli->prepare("UPDATE semantic_blog SET banner = ? WHERE sbid = ?"); 
  $stmt->bind_param("si", $valueblank, $which);
  $stmt->execute();
  $stmt->close();
}
if ($done == "done" && ($admintype == 'AD' || $admintype == 'AM' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $author = isset($_POST['author']) ? $_POST['author'] : '';
    $author = preg_replace('/[^\p{Latin}\d\s\p{P}]/u', '', $author);
  $ptid = isset($_POST['ptid']) ? $_POST['ptid'] : '0';
  $gid = isset($_POST['gid']) ? $_POST['gid'] : '0';

  $blog_title = isset($_POST['blog_title']) ? $_POST['blog_title'] : '';
  $blog_abstract = isset($_POST['blog_abstract']) ? $_POST['blog_abstract'] : '';
  $page_txt1 = isset($_POST['page_txt1']) ? $_POST['page_txt1'] : '';
  $page_txt2 = isset($_POST['page_txt2']) ? $_POST['page_txt2'] : '';
  $page_txt3 = isset($_POST['page_txt3']) ? $_POST['page_txt3'] : '';
  $bannerTitle = isset($_POST['bannerTitle']) ? $_POST['bannerTitle'] : '';
  $bannerTxt = isset($_POST['bannerTxt']) ? $_POST['bannerTxt'] : '';
  $justification = isset($_POST['justification']) ? $_POST['justification'] : 1;
  $actioncall1 = isset($_POST['actioncall1']) ? $_POST['actioncall1'] : '';
  $actioncall2 = isset($_POST['actioncall2']) ? $_POST['actioncall2'] : '';
  $buttonlink1 = isset($_POST['buttonlink1']) ? $_POST['buttonlink1'] : '';
  $buttonlink2 = isset($_POST['buttonlink2']) ? $_POST['buttonlink2'] : '';
  $buttonname1 = isset($_POST['buttonname1']) ? $_POST['buttonname1'] : '';
  $buttonname2 = isset($_POST['buttonname2']) ? $_POST['buttonname2'] : '';
  $metatext = isset($_POST['metatext']) ? $_POST['metatext'] : '';
  $googleTitle = isset($_POST['googleTitle']) ? $_POST['googleTitle'] : '';
  $googleDesc = isset($_POST['googleDesc']) ? $_POST['googleDesc'] : '';
  $googleKeywords = isset($_POST['googleKeywords']) ? $_POST['googleKeywords'] : '';
  $schematype = isset($_POST['schematype']) ? $_POST['schematype'] : '';
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
    $sort_order = (int)$sort_order;
  
  // Update record
  // note we don't change semantic_url else SEO is hurt
  $stmt = $mysqli->prepare("UPDATE semantic_blog SET sort_order = ?, date_modified = ?, author = ?, ptid = ?, gid = ?, blog_title = ?, blog_abstract = ?, page_txt1 = ?, page_txt2 = ?, page_txt3 = ?, bannerTitle = ?, bannerTxt = ?, justification = ?, actioncall1 = ?, buttonlink1 = ?, buttonname1 = ?, actioncall2 = ?, buttonlink2 = ?, buttonname2 = ?, metatext = ?, googleTitle = ?, googleDesc = ?, googleKeywords = ?, schematype = ? WHERE sbid = ?"); 
  $stmt->bind_param("iisiisssssssissssssssssii", $sort_order, $today, $author, $value0, $gid, $blog_title, $blog_abstract, $page_txt1, $page_txt2, $page_txt3, $bannerTitle, $bannerTxt, $justification, $actioncall1, $buttonlink1, $buttonname1, $actioncall2, $buttonlink2, $buttonname2, $metatext, $googleTitle, $googleDesc, $googleKeywords, $value0, $which);
  $stmt->execute();
  $anyerror = $mysqli->errno." ".$mysqli->error;
  $stmt->close();
  
  // delete existing tags before re-adding
  $stmt = $mysqli->prepare("DELETE FROM blog_link_tbl WHERE kbid = ? ");
  $stmt->bind_param("i",$which);     
  $stmt->execute();
  $stmt->close();
  
  // update subject tag links
  $tagstmt = $mysqli->prepare("SELECT btagid FROM blog_subject_tags");
  $tagstmt->execute();
  $tagstmt->store_result();
  $tagstmt->bind_result($btagid);
  while ($tagstmt->fetch()){
    $posmarker = 'q'.$btagid;
    $clicked = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
    if ($clicked == $btagid)  {
      // if checkbox has same value add to db
      $insert_stmt = $mysqli->prepare("INSERT INTO blog_link_tbl (kbid, btagid) VALUES (?, ?)");
      $insert_stmt->bind_param("ii", $which, $btagid);
      $insert_stmt->execute();
      $insert_stmt->close();
    }
  }
  $tagstmt->close();
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT sort_order, date_published, date_modified, author, ptid, gid, blog_title, semantic_title, semantic_url, blog_abstract, page_txt1, page_txt2, page_txt3, bannerTitle, bannerTxt, justification, banner, actioncall1, buttonlink1, buttonname1, actioncall2, buttonlink2, buttonname2, metatext, googleTitle, googleDesc, googleKeywords, schematype FROM semantic_blog WHERE sbid = ?");
$stmt->bind_param("i", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($sort_order, $date_published, $date_modified, $author, $ptid, $gid, $blog_title, $semantic_title, $semantic_url, $blog_abstract, $page_txt1, $page_txt2, $page_txt3, $bannerTitle, $bannerTxt, $justification, $banner, $actioncall1, $buttonlink1, $buttonname1, $actioncall2, $buttonlink2, $buttonname2, $metatext, $googleTitle, $googleDesc, $googleKeywords, $schematype);
$stmt->fetch();
$stmt->close();
  $date_published = strtotime($date_published);
  $date_modified = strtotime($date_modified);
// whatever the record name is
  $changename = " this Blog";
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
                           <p>Created on: <?php echo date("D jS M Y", $date_published) ?> and last modified on <?php echo date("D jS M Y", $date_modified) ?></p>
                           <div class="form-group">
                         <label for="blog_title">Blog Post Title</label>
                         <input type="text" class="form-control" id="blog_title" name="blog_title" value="<?php echo $blog_title ?>" required>
                            <p class="help-block"><strong>Blog URL will https://www.thelggroup.co.uk/blog/<?php echo $semantic_url ?></strong></p>
                            <p>(URL is fixed at the point the post was created)</p>
                        </div>

                        <div class="form-group">
                          <label class="col-form-label" for="blog_abstract">Teaser Text</label>
                          <textarea class="form-control" rows="6" id="blog_abstract" name="blog_abstract"><?php echo $blog_abstract ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="col-form-label" for="page_txt1">Main Text</label>
                            <textarea id="page_txt1" name="page_txt1" rows="16" class="form-control summernote"><?php echo $page_txt1 ?></textarea>
                            <p class="help-block"><strong>Local links should be absolute, e.g. https://www.thelggroup.co.uk/somepage.php not somepage.php</strong></p>
                          </div>
                        <div class="form-group">
                            <label class="col-form-label" for="page_txt2">Second Text Block</label>
                            <textarea id="page_txt2" name="page_txt2" rows="16" class="form-control summernote" ><?php echo $page_txt2 ?></textarea>
                            
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="page_txt3">Third Text Block</label>
                            <textarea id="page_txt3" name="page_txt3" rows="16" class="form-control summernote"><?php echo $page_txt3 ?></textarea>
                            
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
                        <h6>SEO</h6><hr>
                        
                        <div class="form-group">
                           <label class="col-form-label" for="googleTitle">Meta Title</label>
                            <input type="text" class="form-control" id="googleTitle" name="googleTitle" value="<?php echo $googleTitle ?>">
                           <p class="help-block"><small>Optional - system will create this from the Blog Post Title if blank</small></p>
                        </div>
                        <div class="form-group">
                            <label class="col-form-label" for="googleDesc">Meta Description</label>
                              <textarea id="googleDesc" name="googleDesc" rows="3" class="form-control"><?php echo $googleDesc ?></textarea>
                              <p class="help-block"><small>Optional - system will create this from the Blog Post Title if blank.</small></p>
                          </div>
                          <div class="form-group">
                             <label class="col-form-label" for="googleKeywords">Meta Keywords</label>
                            <input type="text" class="form-control" id="googleKeywords" name="googleKeywords" value="<?php echo $googleKeywords ?>">
                           <p class="help-block"><small>Optional - system will create this from the Blog Post Title if blank.</small></p>
                        </div>
                        <h6>Credits</h6><hr>
                        <div class="form-group">
                          <label class="col-form-label" for="author">Blog Author</label>
                            <input type="text" class="form-control" id="author" name="author" value="<?php echo $author ?>">
                        </div>
                      <h6>Call to Action</h6>
                      <div class="form-group">
                          <label class="col-form-label" for="actioncall1">Action text #1</label>
                            <input type="text" class="form-control" id="actioncall1" name="actioncall1" value="<?php echo $actioncall1 ?>">
                        </div>
                        <div class="form-group">
                          <label class="col-form-label" for="buttonlink1">Button Link for #1</label>
                            <input type="text" class="form-control" id="buttonlink1" name="buttonlink1" value="<?php echo $buttonlink1 ?>">
                            <p class="help-block"><strong>Local links should be absolute, e.g. /index.php not index.php</strong></p>
                        </div>
                        <div class="form-group">
                          <label class="col-form-label" for="buttonname1">Button Label for #1</label>
                            <input type="text" class="form-control" id="buttonname1" name="buttonname1" value="<?php echo $buttonname1 ?>">
                        </div>
                        <div class="form-group">
                          <label class="col-form-label" for="actioncall2">Action text #2</label>
                            <input type="text" class="form-control" id="actioncall2" name="actioncall2" value="<?php echo $actioncall2 ?>">
                        </div>
                        <div class="form-group">
                          <label class="col-form-label" for="buttonlink2">Button Link for #2</label>
                            <input type="text" class="form-control" id="buttonlink2" name="buttonlink2" value="<?php echo $buttonlink2 ?>">
                            <p class="help-block"><strong>Local links should be absolute, e.g. /index.php not index.php</strong></p>
                        </div>
                        <div class="form-group">
                          <label class="col-form-label" for="buttonname2">Button Label for #2</label>
                            <input type="text" class="form-control" id="buttonname2" name="buttonname2" value="<?php echo $buttonname2 ?>">
                        </div>
                        <h6>Search &amp; Filter Tags</h6>
                        <div class="form-group">
                            <label class="col-form-label">&nbsp;</label>
                              <?PHP
                              $checked = '';
                              // Loop through tags 
                              $loopstmt = $mysqli->prepare("SELECT btagid, btag FROM blog_subject_tags");
                              $loopstmt->execute();
                              $loopstmt->store_result();
                              $loopstmt->bind_result($btagid, $btag);
                              while ($loopstmt->fetch()) {  
                                
                                // see if in links table for this record
                                $whatlink = $mysqli->prepare("SELECT blid FROM blog_link_tbl WHERE kbid = ? AND btagid = ? ");
                                $whatlink->bind_param("ii", $which, $btagid);
                                $whatlink->execute(); 
                                $whatlink->bind_result($blid);
                                $whatlink->fetch();
                                if ($blid > 0) {
                                  $checked = " checked=\"checked\" ";
                                }
                                $whatlink->close();
                                echo "<label class=\"checkbox-inline mr-3\"><input name=\"q$btagid\" type=\"checkbox\" id=\"$btagid\" value=\"$btagid\" $checked/> $btag</label>\r";
                                $blid = 0;
                                $checked = '';
                              }
                              $loopstmt->close();
                              ?>
                          </div>
                          <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>" min="0" max="999"><span class="form-text">Sort order after date sorting. Enter 0 if not to be displayed.</span>
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
               <div class="col-xl-4">
                <div class="card border-info">
                  <div class="card-header bg-info">
                     <div class="card-title">Banner</div>
                  </div>
                  <div class="card-body">
                    <form action="blogphotoprocess.php" method="post" id="ProcessIcon" name="ProcessIcon" class="form-horizontal" enctype="multipart/form-data">
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
                      if ($banner != '') {
                        echo "<p><a href=\"blogdetail.php?which=$which&amp;delicon=delicon\"<span class=\"btn btn-danger\">Delete Image</span></a></p>";
                        echo "<p><img src=\"../blogbanner/$banner\" width=\"200px\"></p>";
                      }
                      ?>
                      <!-- space for Ajax result -->
                    </div>
                    <div id="err"></div>
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
         url: "blogphotoprocess.php",
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