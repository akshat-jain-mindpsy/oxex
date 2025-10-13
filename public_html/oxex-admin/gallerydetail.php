<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Gallery";

setAdminVars(0); // Dashboard section
$subtitle = "Gallery";
$listurl = "gallery.php"; # where the delete script is found
$listname = "Gallery";
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
  $stmt = $supabase_pdo->prepare("UPDATE cs_gallery SET filename = ? WHERE gid = ?"); 
  $stmt->execute([$valueblank, $which]);
}
if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $$value0 = 0; // Default value for sort_order
$subtitle = "_POST['sort_order'] : 0;
    $$value0 = 0; // Default value for sort_order
$subtitle = "sort_order;
  $category = isset($_POST['category']) ? $_POST['category'] : '';
  $caption = isset($_POST['caption']) ? $_POST['caption'] : '';
  
  // Update record
  $stmt = $supabase_pdo->prepare("UPDATE cs_gallery SET category = ?, sort_order = ?, caption = ? WHERE gid = ?"); 
  $stmt->execute([$category, $$value0 = 0; // Default value for sort_order
$subtitle = "which]);
}
?>
<?php
  // find the required record
$stmt = $supabase_pdo->prepare("SELECT category, sort_order, caption, filename, date_created FROM cs_gallery WHERE gid =  ? ");
$stmt->execute([$which]); 
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $category = $row['category'];
    $$value0 = 0; // Default value for sort_order
$subtitle = "row['sort_order'];
    $caption = $row['caption'];
    $filename = $row['filename'];
    $date_created = $row['date_created'];
}
$date_created = strtotime($date_created);
$where = substr($category, 0, 1); # first char
$pageid = ltrim($category, $where); # remaining id ref
$dispcat = "Gallery"; # default = Gallery
$dispage = '-'; # all on one page

if ($where == 'p') {
  $dispcat = "Pages";
  $stmt = $supabase_pdo->prepare("SELECT page_name FROM pages_tbl WHERE pid = ?");
  $stmt->execute([$pageid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
      $dispage = $row['page_name'];
  }
}
if ($where == 'e') {
    $dispcat = "Projects";
    $stmt = $supabase_pdo->prepare("SELECT proj_name FROM proj_tbl WHERE pid = ?");
    $stmt->execute([$pageid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $dispage = $row['proj_name'];
    }
  }

if ($where == 'b') {
  $dispcat = "Blog";
  $stmt = $supabase_pdo->prepare("SELECT blog_title FROM semantic_blog WHERE sbid = ?");
  $stmt->execute([$pageid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
      $dispage = $row['blog_title'];
  }
}

// whatever the record name is
  $changename = " this image";
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

setAdminVars(0); // Dashboard section            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?> <small class="ml-3">(created <?php echo date('j M Y',$date_created) ?>)</small></div>
                        </div>
                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="caption">Caption</label>
                              <input class="form-control" type="text" id="caption" name="caption" value="<?php echo $caption ?>" required>
                           </div>
                           <div class="form-group">
                            <label class="col-form-label" for="category">Which Page</label>
                                <select class="custom-select custom-select-lg mb-3" id="category" name="category" required>
                                  <option selected="">Select a Destination:</option>
                                  <?php
                                  echo "<option value=\"g0\"";
                                    if ($where == 'g') {
                                      echo "selected='selected'";
                                    }
                                  echo ">Gallery</option>";
                                  // list projects
                                  $tableset = $supabase_pdo->prepare("SELECT pid, proj_name FROM proj_tbl ");
                                  $tableset->execute();
                                  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                                    $pid = (int)$row['pid'];
                                    $proj_name = $row['proj_name'];
                                    echo "<option value=\"e$pid\"";
                                    if ($where == 'e' && $pageid == $pid) {
                                      echo "selected='selected'";
                                    }
                                    echo ">Project - $proj_name</option>";
                                  }
                                  // list pages
                                  $tableset = $supabase_pdo->prepare("SELECT pid, page_name FROM pages_tbl ");
                                  $tableset->execute();
                                  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                                    $pid = (int)$row['pid'];
                                    $page_name = $row['page_name'];
                                    echo "<option value=\"p$pid\"";
                                    if ($where == 'p' && $pageid == $pid) {
                                      echo "selected='selected'";
                                    }
                                    echo ">Page - $page_name</option>";
                                  }
                                  
                                  
                                  // list blog
                                  $tableset = $supabase_pdo->prepare("SELECT sbid, blog_title FROM semantic_blog ");
                                  $tableset->execute();
                                  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                                    $sbid = (int)$row['sbid'];
                                    $blog_title = $row['blog_title'];
                                    echo "<option value=\"b$sbid\"";
                                    if ($where == 'b' && $pageid == $sbid) {
                                      echo "selected='selected'";
                                    }
                                    echo ">Blog - $blog_title</option>";
                                  }
                                  ?>
                                </select>
                          </div>
                           <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="$value0 = 0; // Default value for sort_order
$subtitle = "sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
</div>
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
</div><!-- END card-->
                  </form>
               </div>
               <div class="col-xl-4">
                <div class="card border-info">
                  <div class="card-header bg-info">
                     <div class="card-title">Image</div>
                  </div>
                  <div class="card-body">
                    <form action="galleryprocess.php" method="post" id="ProcessIcon" name="ProcessIcon" class="form-horizontal" enctype="multipart/form-data">
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
                      if ($filename != '') {
                        echo "<p><a href=\"gallerydetail.php?which=$which&amp;delicon=delicon\"<span class=\"btn btn-danger\">Delete Image</span></a></p>";
                        echo "<p><img src=\"../gallery/$filename.jpg\" width=\"200px\"></p>";
                      }
                      ?>
                      <!-- space for Ajax result -->
                    </div>
                    <div id="err"></div>
                  </div>
                  <div class="card-footer">
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
                            <span class="form-text">This will immediately &amp; permanently delete this image. There is NO 'undo'!</span>
</div>
                     </div><!-- END card-->
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
     $('#fa').summernote({
        tabsize: 2,
        height: 160,
        spellCheck: true,
        dialogsInBody: true,
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
         url: "galleryprocess.php",
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