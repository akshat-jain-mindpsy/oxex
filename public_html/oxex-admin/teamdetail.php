<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = 'Team/Staff';
$subtitle = "Page content";
$listurl = "team.php"; # where the delete script is found
$listname = "Team/Staff";
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
  $stmt = $mysqli->prepare("UPDATE stafflist SET photo = ? WHERE slil = ?"); 
  $stmt->bind_param("si", $valueblank, $which);
  $stmt->execute();
  $stmt->close();
}

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;

  $name = isset($_POST['name']) ? $_POST['name'] : '';
  $position = isset($_POST['position']) ? $_POST['position'] : '';
  $title = isset($_POST['title']) ? $_POST['title'] : '';
  $email = isset($_POST['email']) ? $_POST['email'] : '';
  $maintext = isset($_POST['maintext']) ? $_POST['maintext'] : '';
  $photocaption = isset($_POST['photocaption']) ? $_POST['photocaption'] : '';
  $soc_fb = isset($_POST['soc_fb']) ? $_POST['soc_fb'] : '';
  $soc_tw = isset($_POST['soc_tw']) ? $_POST['soc_tw'] : '';
  $soc_lk = isset($_POST['soc_lk']) ? $_POST['soc_lk'] : '';
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;

  // Update record
  $stmt = $mysqli->prepare("UPDATE stafflist SET name = ?, position = ?, email = ?, maintext = ?, sort_order = ?, soc_fb = ?, soc_tw = ?, soc_lk = ? WHERE slil = ?"); 
    $stmt->bind_param("ssssisssi", $name, $position, $email, $maintext, $sort_order, $soc_fb, $soc_tw, $soc_lk, $which);
    $stmt->execute();
    $anyerror = $mysqli->errno." ".$mysqli->error;
    $stmt->close();

}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT name, position, title, email, phone, photo, photocaption, maintext, sort_order, soc_fb, soc_tw, soc_lk FROM stafflist WHERE slil = ?");
$stmt->bind_param("i", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($name, $position, $title, $email, $phone, $trusteephoto, $photocaption, $maintext, $sort_order, $soc_fb, $soc_tw, $soc_lk);
$stmt->fetch();
$stmt->close();
// whatever the record name is
  $changename = " this person";
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
                              <label class="col-form-label" for="name">Name</label>
                              <input class="form-control" type="text" id="name" value="<?php echo $name ?>" name="name" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="position">Position</label>
                              <input class="form-control" type="text" id="position" value="<?php echo $position ?>" name="position">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="email">Email</label>
                              <input class="form-control" type="text" id="email" value="<?php echo $email ?>" name="email">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="soc_fb">Facebook Link</label>
                              <div class="input-group">
                                 <div class="input-group-prepend"><span class="input-group-text" id="basic-addon1">https://</span></div><input class="form-control" type="text" id="soc_fb" value="<?php echo $soc_fb ?>" name="soc_fb">
                              </div>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="soc_tw">Twitter Link</label>
                              <div class="input-group">
                                 <div class="input-group-prepend"><span class="input-group-text" id="basic-addon1">https://</span></div><input class="form-control" type="text" id="soc_tw" value="<?php echo $soc_tw ?>" name="soc_tw">
                              </div>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="soc_lk">LinkedIn Link</label>
                              <div class="input-group">
                                 <div class="input-group-prepend"><span class="input-group-text" id="basic-addon1">https://</span></div><input class="form-control" type="text" id="soc_lk" value="<?php echo $soc_lk ?>" name="soc_lk">
                              </div>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="maintext">Text</label>
                              <textarea class="form-control summernote" type="text" id="maintext" name="maintext"><?php echo $maintext ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
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
                     <div class="card-title">Photo </div>
                  </div>
                  <div class="card-body">
                    <form action="teamphotoprocess.php" method="post" id="ProcessIcon" name="ProcessIcon" class="form-horizontal" enctype="multipart/form-data">
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
                      if ($trusteephoto != '') {
                        echo "<p><a href=\"teamdetail.php?which=$which&amp;delicon=delicon\"<span class=\"btn btn-danger\">Delete Image</span></a></p>";
                        echo "<p><img src=\"../team/$trusteephoto\" width=\"200px\"></p>";
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
         url: "teamphotoprocess.php",
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