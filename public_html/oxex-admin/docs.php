<?PHP
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Documentation";

$subtitle = "Docs";
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);

// Debug: Check if variables are set
echo "<!-- DEBUG: Admin type: " . (isset($admintype) ? $admintype : 'NOT SET') . " -->";
echo "<!-- DEBUG: Admin name: " . (isset($adminname) ? $adminname : 'NOT SET') . " -->";
echo "<!-- DEBUG: Using Supabase: " . ($usingSupabase ? 'YES' : 'NO') . " -->";

// Debug: Check login and admin type
$login_check_result = login_check($pdo);
echo "<!-- DEBUG: Login check result: " . ($login_check_result ? 'TRUE' : 'FALSE') . " -->";
echo "<!-- DEBUG: Admin type check: " . (isset($admintype) ? $admintype : 'NOT SET') . " -->";

if($login_check_result == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
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
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row from detail page
  if ($usingSupabase) {
    $stmt = $supabase_pdo->prepare("DELETE FROM docs_tbl WHERE did = ? LIMIT 1");
    $stmt->execute([$which]); 
    if ($stmt->rowCount() > 0) {
      // show message when deleting, not refreshing
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
    }
  }
}
// doc_section, sect_title, sect_txt, sort_order, who_by, date_added, date_modified
if ($newadmin == 'newadmin') {
   $doc_section = isset($_POST['doc_section']) ? $_POST['doc_section'] : 0;
   $sect_title = isset($_POST['sect_title']) ? $_POST['sect_title'] : '';
   $sect_txt = isset($_POST['sect_txt']) ? $_POST['sect_txt'] : '';
   $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  
  // write new record
  if ($usingSupabase) {
    $insert_stmt = $supabase_pdo->prepare("INSERT INTO docs_tbl (doc_section, sect_title, sect_txt, sort_order, who_by, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert_stmt->execute([$doc_section, $sect_title, $sect_txt, $sort_order, $adminname, date("Y-m-d H:i:s"), date("Y-m-d H:i:s")]);
    $newid = $supabase_pdo->lastInsertId();
  } else {
    $newid = 0;
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
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>

            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Title</th>
                                 <th>Section</th>
                                 <th>Sort Order</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
if ($usingSupabase) {
    $tableset = $supabase_pdo->prepare("SELECT did, sect_title, doc_section, sort_order FROM docs_tbl ");
    $tableset->execute();
    $docs = $tableset->fetchAll(PDO::FETCH_ASSOC);
} else {
    $docs = [];
}

foreach ($docs as $doc) {
    $did = $doc['did'];
    $sect_title = $doc['sect_title'];
    $doc_section = $doc['doc_section'];
    $sort_order = $doc['sort_order'];
   if ($sort_order == 0) {
      $sort_order = '<div class="badge badge-danger">Not displayed</div>';
   }
   // which section?
   switch ($doc_section) {
      case 0:
      $section = 'Dashboard';
      break;
      case 1:
      $section = 'Pages';
      break;
      case 2:
      $section = 'Trainees';
      break;
      case 3:
      $section = 'Tables';
      break;
      case 4:
      $section = 'Blog';
      break;
      case 5:
     $section = 'Admin';
     break;
   }
?>
<tr>
   <td><a href="docdetail.php?which=<?php echo $did ?>"><?php echo $sect_title ?></a></td>
   <td><?php echo $section ?></td>

   <td><?php echo $sort_order ?></td>
</tr>
 <?php
 }
$numrows = count($docs);
?>
                           </tbody>
                        </table>
</div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Documentation</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="sect_title">Title</label>
                              <input class="form-control" type="text" id="sect_title" name="sect_title" required>
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
                           <div class="form-group">
                            <label class="col-form-label" for="sect_txt">Text</label>
                                 <textarea name="sect_txt" id="sect_txt" class="form-control summernote" required></textarea>
                             </div>
                           <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo isset($sort_order) ? $sort_order : 0; ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
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
   
   <!-- DEBUGGING SCRIPT FOR SIDEBAR -->
   <script>
   console.log('=== SIDEBAR DEBUGGING (DOCS PAGE) ===');
   console.log('jQuery version:', typeof $ !== 'undefined' ? $.fn.jquery : 'jQuery not loaded');
   console.log('Bootstrap version:', typeof $.fn.collapse !== 'undefined' ? 'Bootstrap loaded' : 'Bootstrap collapse not available');
   console.log('Admin type:', '<?php echo $admintype; ?>');
   console.log('Admin name:', '<?php echo $adminname; ?>');
   
   // Test sidebar toggle functionality
   console.log('Testing sidebar toggle...');
   console.log('Body classes:', document.body.className);
   console.log('Toggle elements found:', $('[data-toggle-state]').length);
   console.log('Sidebar toggle elements:', $('[data-toggle-state="aside-collapsed"]').length);
   console.log('Mobile sidebar toggle elements:', $('[data-toggle-state="aside-toggled"]').length);

   // Check if collapse elements exist
   $(document).ready(function() {
       console.log('Document ready - checking sidebar elements on docs page');
       
       // Check for collapse elements
       var collapseElements = $('[data-toggle="collapse"]');
       console.log('Found collapse elements:', collapseElements.length);
       
       collapseElements.each(function(index) {
           var $this = $(this);
           var target = $this.attr('data-target');
           var targetElement = $(target);
           console.log('Element ' + index + ':', {
               text: $this.text().trim(),
               target: target,
               targetExists: targetElement.length > 0,
               targetClasses: targetElement.attr('class')
           });
       });
       
       // Test manual collapse
       console.log('Testing manual collapse on docs page...');
       console.log('Data element before show:', $('#data').attr('class'));
       console.log('Data element visibility:', $('#data').is(':visible'));
       console.log('Data element display:', $('#data').css('display'));
       $('#data').collapse('show');
       console.log('Data element after show:', $('#data').attr('class'));
       console.log('Data element visibility after:', $('#data').is(':visible'));
       console.log('Data element display after:', $('#data').css('display'));
       
       // Test users element
       console.log('Users element classes:', $('#users').attr('class'));
       console.log('Users element visibility:', $('#users').is(':visible'));
       console.log('Users element display:', $('#users').css('display'));
       
       // Test all collapse elements
       $('.collapse').each(function() {
           console.log('Collapse element:', this.id, 'Classes:', this.className);
           console.log('Collapse element content:', $(this).html().substring(0, 200) + '...');
       });
       
       // Check what's actually in the sidebar
       console.log('Sidebar content length:', $('.sidebar').html().length);
       console.log('Sidebar subnav elements:', $('.sidebar-subnav').length);
       console.log('Sidebar subnav headers:', $('.sidebar-subnav-header').length);
       console.log('Sidebar subnav header sub elements:', $('.sidebar-subnav-header-sub').length);
       
       // Add click handlers for debugging
       $('[data-toggle="collapse"]').on('click', function(e) {
           console.log('Collapse clicked:', $(this).text().trim());
           console.log('Event:', e);
           console.log('Target:', $(this).attr('data-target'));
       });
       
       // Listen for collapse events
       $('.collapse').on('show.bs.collapse', function() {
           console.log('Collapse showing:', this.id);
       });
       
       $('.collapse').on('shown.bs.collapse', function() {
           console.log('Collapse shown:', this.id);
       });
       
       $('.collapse').on('hide.bs.collapse', function() {
           console.log('Collapse hiding:', this.id);
       });
       
       $('.collapse').on('hidden.bs.collapse', function() {
           console.log('Collapse hidden:', this.id);
       });
       
       // Test sidebar toggle manually
       console.log('Testing manual sidebar toggle...');
       $('[data-toggle-state="aside-collapsed"]').on('click', function(e) {
           console.log('Sidebar toggle clicked!');
           e.preventDefault();
           document.body.classList.toggle('aside-collapsed');
           console.log('Body classes after toggle:', document.body.className);
       });
       
       $('[data-toggle-state="aside-toggled"]').on('click', function(e) {
           console.log('Mobile sidebar toggle clicked!');
           e.preventDefault();
           document.body.classList.toggle('aside-toggled');
           console.log('Body classes after toggle:', document.body.className);
       });
       
       // Test modal functionality
       console.log('Testing modal functionality...');
       console.log('Docs modal exists:', $('#docsModal').length);
       console.log('Docs button exists:', $('[data-target="#docsModal"]').length);
       
       $('[data-target="#docsModal"]').on('click', function(e) {
           console.log('Docs button clicked!');
           e.preventDefault();
           $('#docsModal').modal('show');
       });
   });
   </script>
</body>
</html>
<?PHP
} else {
   echo "<!-- DEBUG: User not authorized. Login check: " . ($login_check_result ? 'TRUE' : 'FALSE') . ", Admin type: " . (isset($admintype) ? $admintype : 'NOT SET') . " -->";
   echo "Not authorised";
}
?>