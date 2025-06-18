<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Customers";
$subtitle = "Customers";
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
$sort_order = 1;
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AD' || $admintype == 'DV')) {
    // get id
    $stmt = $mysqli->prepare("SELECT custusr FROM cust_tbl WHERE cid = ?");
   $stmt->bind_param('i', $cid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($custusr);
   $stmt->fetch();
   $stmt->close();
  // delete row from detail page
  $stmt = $mysqli->prepare("DELETE FROM cust_tbl WHERE cid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();
  // delete all images
  $stmt = $mysqli->prepare("DELETE FROM customer_gallery WHERE custusr = ?");
  $stmt->bind_param("s", $custusr); 
  $stmt->execute();
  $stmt->close();
}

if ($newadmin == 'newadmin') {
  $bus_name = isset($_POST['bus_name']) ? $_POST['bus_name'] : '';
  $vehicle = isset($_POST['vehicle']) ? $_POST['vehicle'] : '';
  $contact = isset($_POST['contact']) ? $_POST['contact'] : '';
  $email = isset($_POST['email']) ? $_POST['email'] : '';
  $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
  $address = isset($_POST['address']) ? $_POST['address'] : '';
  $town = isset($_POST['town']) ? $_POST['town'] : '';
  $zip = isset($_POST['zip']) ? $_POST['zip'] : '';
  $cust_txt = isset($_POST['cust_txt']) ? $_POST['cust_txt'] : '';
  $admin_txt = isset($_POST['admin_txt']) ? $_POST['admin_txt'] : '';
  $numids = 1;
    while (!$numids == 0) {
       // make 20 digit hex string identifier
    // https://www.classicperformanceengineering.co.uk/cust/[$custusr]
       $custusr = substr(md5(rand()), 0, 20);
       $stmt = $mysqli->prepare("SELECT cid FROM cust_tbl WHERE custusr = ? LIMIT 1");
       $stmt->bind_param('s', $custusr);
       $stmt->execute();
       $stmt->store_result();
       $stmt->bind_result($cid);
       $stmt->fetch();
       $numids = $stmt->num_rows;
       $stmt->close();
    }

  $insert_stmt = $mysqli->prepare("INSERT INTO cust_tbl (custusr, bus_name, contact, vehicle, email, phone, address, town, zip, cust_txt, admin_txt, who_by, date_added, date_modified, last_used) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("ssssssssssssiii", $custusr, $bus_name, $contact, $vehicle, $email, $phone, $address, $town, $zip, $cust_txt, $admin_txt, $usrkey, $today, $today, $value0);
  $insert_stmt->execute();
  $newid =  $insert_stmt->insert_id;
  $insert_stmt->close();
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
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                      <table class="table table-striped my-4 w-100" id="maintable">
                         <thead>
                            <tr>
                                <th class="sort-alpha" data-priority="1">Customer</th>
                                <th>Vehicle</th>
                                <th>Last Accessed</th>
                                <th>Images</th>
                            </tr>
                         </thead>
                         <tbody>
<?PHP
$stmt = $mysqli->prepare("SELECT cid, contact, last_used, custusr, vehicle FROM cust_tbl");
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($cid, $contact, $last_used, $custusr, $vehicle);
while ($stmt->fetch()){
  $last_used = strtotime($last_used);
    $vids = $mysqli->prepare("SELECT gid FROM customer_gallery WHERE custusr = ? ");
    $vids->bind_param("s", $custusr);
    $vids->execute();
    $vids->store_result();
    $numlinks = $vids->num_rows;
    $vids->close();
  if ($numlinks == '0') {
    $vidqty = '<span class="btn btn-default">No</span>';
  } else {
    $vidqty = "<span class=\"btn btn-success\">$numlinks</span>";
  }

?>
<tr>
  <td valign="top"><a href="customerdetail.php?which=<?php echo $cid ?>&amp;change=no"><?php echo $contact ?></a></td>
  <td valign="top"><?php echo $vehicle ?></td>
  <td valign="top"><?php echo date('j M Y',$last_used) ?></td>
  <td valign="top"><?php echo $vidqty ?></td>
</tr>
<?php
}
$numrows = $stmt->num_rows;
$stmt->close();
?>
                         </tbody>
                      </table>
                   </div>
               </div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-xl-8">
                  <form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Create new Customer Area</div>
                        </div>

                        <div class="card-body">
                          <div class="form-group">
                              <label class="col-form-label" for="contact">Customer Name</label>
                              <input class="form-control" type="text" id="contact" name="contact" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="bus_name">Business Name</label>
                              <input class="form-control" type="text" id="bus_name" name="bus_name">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="vehicle">Vehicle</label>
                              <input class="form-control" type="text" id="vehicle" name="vehicle">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="phone">Tel</label>
                              <input class="form-control" type="text" id="phone" name="phone">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="company_name">Email</label>
                              <input class="form-control" type="text" id="company_name" name="company_name">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="address">Address</label>
                              <textarea class="form-control" type="text" id="address" name="address"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="town">Town</label>
                              <input class="form-control" type="text" id="town" name="town">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="zip">Post Code</label>
                              <input class="form-control" type="text" id="zip" name="zip">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="cust_txt">Displayed Text</label>
                              <textarea class="form-control summernote" type="text" id="cust_txt" name="cust_txt"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="admin_txt">Hidden Admin Text</label>
                              <textarea class="form-control" type="text" id="admin_txt" name="admin_txt"></textarea>
                           </div>
                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
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
            xmlhttp.open("GET","createperfstring.php?q="+str,true);
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