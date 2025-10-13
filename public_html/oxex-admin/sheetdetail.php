<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    header("Location: index.php");
    exit();
}

// Page setup
$pagetitle = "Sheet Details";
$subtitle = "Sheet Categories";
$listurl = "sheets.php";
$listname = "Sheets";

// Set variables needed by adminjs.php
setAdminVars(3); // Tables section

// Page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_POST['which']) ? (int)$_POST['which'] : (isset($_GET['which']) ? (int)$_GET['which'] : 0);

// Debug: Log all POST data
if (!empty($_POST)) {
    error_log("SHEETDETAIL POST DATA: " . print_r($_POST, true));
}

// Handle field deletion
if ($del == "delfield" && ($admintype == 'AT' || $admintype == 'DV')) {
   $stid = isset($_GET['stid']) ? (int)$_GET['stid'] : '';
   $stmt = $supabase_pdo->prepare("DELETE FROM tab_fields WHERE stid = ? AND tbid = ? LIMIT 1");
   $stmt->execute([$stid, $which]);
   
   // Add a success message or redirect
   header("Location: sheetdetail.php?which=$which&msg=field_deleted");
   exit();
}

// Handle table update
if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $tab_name = isset($_POST['tab_name']) ? $_POST['tab_name'] : '';
  $tab_notes = isset($_POST['tab_notes']) ? $_POST['tab_notes'] : '';
  $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
  $isvis = isset($_POST['isvis']) ? (int)$_POST['isvis'] : 1;
  
  // Validate required parameters
  if (empty($which) || $which <= 0) {
    error_log("SHEETDETAIL VALIDATION: which=$which, empty=" . (empty($which) ? 'true' : 'false'));
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Invalid table ID ($which). Cannot update record.</strong></div></div></div>";
  } else {
    // Debug logging
    error_log("SHEETDETAIL UPDATE: tab_name='$tab_name', tab_notes='$tab_notes', isvis=$isvis, sort_order=$sort_order, which=$which");
    
    // First check if the record exists
    $check_stmt = $supabase_pdo->prepare("SELECT tbid, tab_name FROM tabs_tbl WHERE tbid = ?");
    $check_stmt->execute([$which]);
    $record_exists = $check_stmt->rowCount() > 0;
    
    if (!$record_exists) {
      error_log("SHEETDETAIL ERROR: Record with ID $which does not exist");
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Record with ID $which does not exist in the database.</strong></div></div></div>";
    } else {
      // Update record
    try {
      $stmt = $supabase_pdo->prepare("UPDATE tabs_tbl SET tab_name = ?, tab_notes = ?, sort_order = ?, isvis = ? WHERE tbid = ?"); 
      $result = $stmt->execute([$tab_name, $tab_notes, $sort_order, $isvis, $which]);
      $affected_rows = $stmt->rowCount();
      
      // Debug logging
      error_log("SHEETDETAIL UPDATE RESULT: result=$result, affected_rows=$affected_rows");
      error_log("SHEETDETAIL UPDATE VALUES: tab_name='$tab_name', tab_notes='$tab_notes', sort_order=$sort_order, isvis=$isvis, tbid=$which");
      
      if ($result && $affected_rows > 0) {
        $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-success\" role=\"alert\"><strong>Sheet updated successfully!</strong></div></div></div>";
      } else {
        $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Failed to update sheet. No rows affected. Check the error log for details.</strong></div></div></div>";
      }
    } catch (PDOException $e) {
      error_log("SHEETDETAIL PREPARE ERROR: " . $e->getMessage());
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Failed to prepare statement: " . $e->getMessage() . "</strong></div></div></div>";
    }
    }
  }
  
  // Don't redirect - let the success message display
  // header("Location: sheetdetail.php?which=$which&msg=updated");
  // exit();
}

// Handle new field addition
if ($newadmin == "newfield" && ($admintype == 'AT' || $admintype == 'DV')) {
   $stid = isset($_POST['stid']) ? (int)$_POST['stid'] : 0;
   $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
   
   // Check if field already exists
   $vids = $supabase_pdo->prepare("SELECT COUNT(*) FROM tab_fields WHERE tbid = ? AND stid = ?");
   $vids->execute([$which, $stid]);
   $numlinks = (int)$vids->fetchColumn();
   
   if ($numlinks == 0) {
      $insert_stmt = $supabase_pdo->prepare("INSERT INTO tab_fields (tbid, stid, sort_order) VALUES (?, ?, ?)");
      $insert_stmt->execute([$which, $stid, $sort_order]);
      $newid = (int)$supabase_pdo->lastInsertId();
      
      // Check if this is an AJAX request
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
          // Get field details for the response
          $field_stmt = $supabase_pdo->prepare("SELECT str, single FROM select_types WHERE stid = ?");
          $field_stmt->execute([$stid]);
          $field_row = $field_stmt->fetch(PDO::FETCH_ASSOC);
          $str = $field_row ? $field_row['str'] : '';
          $single = $field_row ? (int)$field_row['single'] : 0;
          
          // Field type mapping
          $fieldTypes = [
              0 => 'Single Selection',
              1 => 'Multiple Selection',
              2 => 'Text',
              3 => 'Date',
              4 => 'Numeric (step 0.1)',
              5 => 'Numeric (step integer)',
              6 => 'Time'
          ];
          $listtype = $fieldTypes[$single] ?? 'Unknown';
          
          // Return JSON response
          header('Content-Type: application/json');
          echo json_encode([
              'status' => 'success',
              'message' => 'Field added successfully',
              'stid' => $stid,
              'str' => $str,
              'listtype' => $listtype,
              'tbid' => $which
          ]);
          exit();
      } else {
          // Redirect for regular form submission
          header("Location: sheetdetail.php?which=$which&msg=field_added");
          exit();
      }
   } else {
      // Field already exists
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
          header('Content-Type: application/json');
          echo json_encode([
              'status' => 'error',
              'message' => 'This field already exists in the table'
          ]);
          exit();
      } else {
          header("Location: sheetdetail.php?which=$which&msg=field_exists");
          exit();
      }
   }
}

// Fetch table details
$stmt = $supabase_pdo->prepare("SELECT tab_name, tab_notes, sort_order, isvis FROM tabs_tbl WHERE tbid = ?");
$stmt->execute([$which]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$tab_name = $row ? $row['tab_name'] : '';
$tab_notes = $row ? $row['tab_notes'] : '';
$sort_order = $row ? (int)$row['sort_order'] : 0;
$isvis = $row ? (int)$row['isvis'] : 1;

// Prepare page variables
$changename = htmlspecialchars($tab_name);
?>
<!DOCTYPE html>
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
   <style>
      /* Main styling for logbook preview */
      .card-header.bg-info {
         background-color: #09c !important;
         color: white;
      }
      
      /* Field preview styling */
      .field-item {
         height: 100%;
         display: flex;
         flex-direction: column;
         background-color: #fff;
         border: 1px solid #dee2e6;
         margin-bottom: 0;
         padding: 10px;
      }
      
      .col-md-4 {
         padding: 6px;
      }
      
      .field-preview {
         flex-grow: 1;
         margin-top: 5px;
      }
      
      /* Field name styling */
      .field-name {
         font-weight: 500;
      }
      
      /* Field wrapper styling */
      .field-wrapper {
         transition: all 0.2s;
      }
      
      /* Add a subtle hover effect to field items */
      .field-wrapper:hover {
         transform: translateY(-2px);
         box-shadow: 0 2px 5px rgba(0,0,0,0.08);
         z-index: 10;
      }
      
      /* Add sort animation helper */
      @keyframes sortableHighlight {
        0% { background: rgba(0, 123, 255, 0.2); }
        100% { background: transparent; }
      }
      
      .sortable-highlight {
        animation: sortableHighlight 1s ease;
      }
      
      /* Section styling */
      .section-header {
         border-left: 4px solid #09c;
         background-color: #f8f9fa;
         margin-bottom: 0;
         padding: 10px 15px;
      }
      
      .section-header h5 {
         font-size: 1.1rem;
         margin-bottom: 0;
      }
      
      .section-container {
         border-bottom: 1px solid #eaeaea;
      }
      
      .section-container:first-child {
         border-top: none;
      }
      
      /* Badge styling for field types */
      .badge {
         padding: 4px 8px;
         font-weight: normal;
         border-radius: 3px;
         font-size: 85%;
      }
      
      .badge-primary {
         background-color: #007bff;
      }
      
      .badge-secondary {
         background-color: #6c757d;
      }
      
      .badge-info {
         background-color: #17a2b8;
      }
      
      .badge-warning {
         background-color: #ffc107;
         color: #212529;
      }
      
      /* Field input styling */
      .field-preview select,
      .field-preview input,
      .field-preview textarea {
         background-color: #f8f9fa;
         border: 1px solid #ced4da;
      }
      
      /* Container styling */
      .section-fields, .unsectioned-fields {
         background-color: #fff;
         padding: 0;
      }
      
      .sortable-section .row {
         margin: 0;
      }
      
      /* Edit/delete buttons */
      .field-actions {
         margin-top: 8px;
         display: flex;
         justify-content: flex-end;
      }
      
      .btn-outline-info, .btn-outline-danger {
         padding: 3px 6px;
         font-size: 0.8rem;
      }
      
      /* Card body padding */
      .card-body.p-0 {
         padding: 0 !important;
      }
      
      /* Styles for option management */
      #currentSelectionsList {
          max-height: 350px;
          overflow-y: auto;
          margin-bottom: 15px;
      }
      
      .option-text {
          flex: 1;
      }
      
      .edit-option-input {
          width: 100%;
      }
      
      .edit-option-btn, .delete-option-btn, .move-up-btn, .move-down-btn {
          margin-left: 5px;
      }
      
      .delete-option-btn:hover {
          background-color: #dc3545;
          color: white;
      }
      
      .edit-option-btn:hover {
          background-color: #17a2b8;
          color: white;
      }
      
      .move-up-btn:hover, .move-down-btn:hover {
          background-color: #6c757d;
          color: white;
      }
      
      .section-move-up-btn, .section-move-down-btn {
          margin-right: 5px;
      }
      
      .section-move-up-btn:hover, .section-move-down-btn:hover {
          background-color: #007bff;
          color: white;
      }
      
      .save-option-edit:hover {
          background-color: #28a745;
      }
      
              /* Suggestions dropdown styling */
        #suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 9999; /* Ensure it appears above modal */
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background-color: white;
            margin-top: -1px; /* Overlap the border */
            display: none; /* Hidden by default */
        }
        
        #suggestions-dropdown.show {
            display: block !important;
        }
      
      #suggestions-dropdown .dropdown-item {
          padding: 0.5rem 1rem;
          border-bottom: 1px solid #f8f9fa;
          cursor: pointer;
      }
      
      #suggestions-dropdown .dropdown-item:hover {
          background-color: #f8f9fa;
      }
      
      #suggestions-dropdown .dropdown-item:last-child {
          border-bottom: none;
      }
      
      #suggestions-dropdown .dropdown-divider {
          margin: 0.25rem 0;
      }
      
      #suggestions-dropdown .add-new-option {
          color: #28a745;
          font-weight: 500;
      }
      
      #suggestions-dropdown .add-new-option:hover {
          background-color: #d4edda;
          color: #155724;
      }
      
      /* Input field styling for suggestions */
      #newSelectionInput {
          position: relative;
      }
      
      .input-group {
          position: relative;
      }
      
      /* Ensure the input group container has relative positioning for dropdown */
      .input-group:has(#newSelectionInput) {
          position: relative;
      }
      
      /* Fallback for browsers that don't support :has() */
      .input-group {
          position: relative;
      }
      
      /* Ensure the modal body has proper positioning context */
      .modal-body {
          position: relative;
      }
      
      /* Invalid feedback styling */
      .invalid-feedback {
          display: block;
          color: #dc3545;
          font-size: 0.875rem;
          margin-top: 0.25rem;
      }
      
              .is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        

      
      
   </style>
</head>
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
            <!-- Page header -->
            <div class="content-header">
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small></div>
            </div>

            <!-- Notification Area -->
            <div class="row">
               <div class="col-12">
                <?php 
                if (isset($_GET['msg'])) {
                    $messages = [
                        'updated' => 'Table details updated successfully.',
                        'field_added' => 'New field added to the table.',
                        'field_deleted' => 'Field removed from the table.',
                        'field_exists' => 'This field already exists in the table.'
                    ];
                    $msgType = in_array($_GET['msg'], array_keys($messages)) ? $_GET['msg'] : '';
                    if ($msgType) {
                        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                                {$messages[$msgType]}
                                <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                                    <span aria-hidden='true'>&times;</span>
                                </button>
                              </div>";
                    }
                }
                
                // Display form submission alerts
                if (isset($delalert) && !empty($delalert)) {
                    echo $delalert;
                }
                ?>
</div>

            <!-- Amend Card -->
            <div class="row">
               <div class="col-12">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                     <div class="card border-info mb-4">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend "<?php echo $changename ?>"</div>
                        </div>
                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="tab_name">Table Name</label>
                              <input class="form-control" type="text" id="tab_name" name="tab_name" value="<?php echo $tab_name ?>" required>
                           </div>
                           <div class="form-group">
                            <label class="col-form-label" for="tab_notes">Notes</label>
                              <textarea name="tab_notes" id="tab_notes" class="form-control summernote"><?php echo $tab_notes ?></textarea>
                          </div>
                          <div class="row">
                              <div class="col form-group">
                                  <label class="col-form-label" for="isvis">For all Trainees by default</label>
                                  <select class="custom-select custom-select mb-3" id="isvis" name="isvis">
                                    <option <?php if ($isvis == '0') echo "selected" ?> value="0">No</option>
                                    <option <?php if ($isvis == '1') echo "selected" ?> value="1">Yes</option>
                                  </select>
                              </div>
                              <div class="col form-group">
                                 <label class="col-form-label" for="sort_order">Sort order</label>
                                 <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
</div>
                        </div>
                        
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?php echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
</div><!-- END card-->
                  </form>
</div>

            <!-- Sheet Category Management Section -->
            <div class="row">
               <div class="col-12">
                           <div class="form-group mt-2 w-100" style="margin-bottom: 1.5rem; padding: 1.5rem; background: #AEB7BD; border-radius: 8px;">
                              <h4 class="mb-3">Sheet Category Management</h4>
                              <p class="text-dark">Manage the categories that appear in this table. You can add new categories, change their order, or remove existing categories.</p>
                              <div class="alert alert-info mt-2 mb-3">
                                 <i class="fas fa-info-circle"></i> <strong>Note:</strong> If you need to create a new category, please go to the <a href="sections.php" class="alert-link">Categories section</a> first.
                              </div>
                              <div class="d-flex">
                                <button class="btn btn-success mr-2" data-toggle="modal" data-target="#addFieldModal">
                                   <i class="fa fa-plus"></i> Add New Category
                                </button>
</div>
</div>
                  
            <!-- Categories preview -->
            <div class="card mb-0 w-100 logbook-preview-card" style="box-shadow:none;border-radius:0;">
                <div class="card-header bg-info text-white" style="border-radius:0;">
                    <div class="card-title mb-0">Logbook Preview - How categories will appear to users</div>
                </div>
                <div class="card-body p-0" style="padding:0 !important;">
                <?php
                // Get the current table ID
                $table_id = isset($_GET['which']) ? (int)$_GET['which'] : 0;
                
                // Get all sections like logbook.php does
                $sections_query = "
                SELECT fs.section_id, fs.section_name, fs.section_description, fs.section_order
                FROM field_sections fs
                JOIN section_table_link stl ON fs.section_id = stl.section_id 
                WHERE stl.tbid = ?
                GROUP BY fs.section_id, fs.section_name, fs.section_description, fs.section_order
                ORDER BY fs.section_order ASC";
                
                try {
                    $sections_stmt = $supabase_pdo->prepare($sections_query);
                    $sections_stmt->execute([$table_id]);
                    $sections_result = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $sections = [];
                    foreach ($sections_result as $section) {
                        $sections[$section['section_id']] = $section;
                    }
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger">Error preparing sections query: ' . $e->getMessage() . '</div>';
                    $sections = [];
                }
                
                // Get fields for this table with section information using section_table_link
                $fields_query = "
                  SELECT 
                    tf.tfid,
                    st.stid,
                    st.str AS field_name,
                    COALESCE(stl.section_id, st.section_id) AS section_id,
                    tf.sort_order AS field_order,
                    st.single AS field_type
                  FROM 
                    tab_fields tf
                  JOIN 
                    select_types st ON tf.stid = st.stid
                  LEFT JOIN 
                    section_table_link stl ON st.section_id = stl.section_id AND stl.tbid = ?
                  WHERE 
                    tf.tbid = ?
                  ORDER BY 
                    COALESCE(stl.section_id, st.section_id, 0) ASC,
                    tf.sort_order ASC,
                    st.str ASC
                ";
                
                $fields_stmt = $supabase_pdo->prepare($fields_query);
                $fields_stmt->execute([$table_id, $table_id]);
                $fields_result = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Group fields by section
                $sectioned_fields = [];
                $unsectioned_fields = [];
                
                foreach ($fields_result as $field) {
                  // Debug the field
                  $debug_section_id = $field['section_id'];
                  $debug_field_name = $field['field_name'];
                  
                  // Ensure section exists or create a default unsectioned group
                  if (!empty($field['section_id']) && isset($sections[$field['section_id']])) {
                    if (!isset($sectioned_fields[$field['section_id']])) {
                      $sectioned_fields[$field['section_id']] = [];
                    }
                    $sectioned_fields[$field['section_id']][] = $field;
                  } else {
                    $unsectioned_fields[] = $field;
                  }
                }
                
                // Display sections and their fields
                foreach ($sections as $section_id => $section) {
                  // Start the section container regardless of whether it has fields
                  echo '<div class="section-container m-0 p-0" style="border-bottom:1px solid #eaeaea;">';
                  echo '<div class="section-header d-flex justify-content-between align-items-center m-0 p-2" style="background:#f8f9fa;border-left:4px solid #09c;">';
                  echo '<div class="d-flex align-items-center">';
                  echo '<div class="me-3">';
                  echo '<button type="button" class="btn btn-sm btn-outline-secondary section-move-up-btn" data-section-id="' . $section_id . '" title="Move Section Up">';
                  echo '<i class="fas fa-arrow-up"></i>';
                  echo '</button>';
                  echo '<button type="button" class="btn btn-sm btn-outline-secondary section-move-down-btn" data-section-id="' . $section_id . '" title="Move Section Down">';
                  echo '<i class="fas fa-arrow-down"></i>';
                  echo '</button>';
                  echo '</div>';
                  echo '<div>';
                  echo '<h5 class="mb-1">' . htmlspecialchars($section['section_name']) . '</h5>';
                  if (!empty($section['section_description'])) {
                    echo '<p class="text-muted mb-0"><small>' . htmlspecialchars($section['section_description']) . '</small></p>';
                  }
                  echo '</div>';
                  echo '</div>';
                  echo '<div>';
                  echo '<button type="button" class="btn btn-sm btn-outline-secondary edit-section-btn" data-section-id="' . $section_id . '" data-toggle="modal" data-target="#edit_section_modal">';
                  echo '<i class="fas fa-pencil-alt"></i> Edit Section';
                  echo '</button>';
                  echo '</div>';
                  echo '</div>';
                  
                  echo '<div class="section-fields m-0 p-0" data-section-id="' . $section_id . '" data-table-id="' . $table_id . '">';
                  echo '<div class="row m-0 p-0">';
                  
                  if (isset($sectioned_fields[$section_id]) && !empty($sectioned_fields[$section_id])) {
                    // Display fields in this section
                    foreach ($sectioned_fields[$section_id] as $field) {
                      echo '<div class="col-md-4 field-wrapper p-1 m-0" data-field-id="' . $field['stid'] . '">';
                      renderFieldItem($field, $table_id);
                      echo '</div>';
                    }
                  } else {
                    // No fields in this section
                    echo '<div class="col-12 text-center py-3 empty-section-placeholder m-0">';
                    echo '<p class="text-muted mb-0">No categories assigned to this section. Add categories using the "Add New Category" button above.</p>';
                    echo '</div>';
                  }
                  
                  echo '</div>'; // End row
                  echo '</div>'; // End section-fields
                  echo '</div>'; // End section-container
                }
                
                // Display unsectioned fields
                if (!empty($unsectioned_fields)) {
                  echo '<div class="section-container m-0 p-0">';
                  echo '<div class="section-header d-flex justify-content-between align-items-center m-0 p-2" style="background:#f8f9fa;border-left:4px solid #09c;">';
                  echo '<div>';
                  echo '<h5 class="mb-1">Available Categories</h5>';
                  echo '<p class="text-muted mb-0"><small>Categories ready to be assigned to sections - use arrow buttons to organize or assign to a specific section</small></p>';
                  echo '</div>';
                  echo '</div>';
                  
                  echo '<div class="unsectioned-fields m-0 p-0" data-section-id="null" data-table-id="' . $table_id . '">';
                  echo '<div class="row m-0 p-0">';
                  
                  foreach ($unsectioned_fields as $field) {
                    echo '<div class="col-md-4 field-wrapper p-1 m-0" data-field-id="' . $field['stid'] . '">';
                    renderFieldItem($field, $table_id);
                  echo '</div>';
                }
                  
                  echo '</div>'; // End row
                  echo '</div>'; // End unsectioned-fields
                  echo '</div>'; // End section-container
                }
                
                // Show message if no fields at all
                if (empty($sectioned_fields) && empty($unsectioned_fields)) {
                  echo '<div class="section-container m-0 p-0">';
                  echo '<div class="alert alert-info m-3">';
                  echo '<h5><i class="fas fa-info-circle"></i> No Categories Found</h5>';
                  echo '<p>This table has no categories assigned to it yet. Use the "Add New Category" button above to start adding categories.</p>';
                  echo '</div>';
                  echo '</div>';
                }
                
                /**
                 * Helper function to render a field item
                 */
                function renderFieldItem($field, $table_id) {
                  $field_id = $field['stid'];
                  $field_name = htmlspecialchars($field['field_name']);
                  $field_type = $field['field_type'];
                  $section_id = $field['section_id'];
                  
                  // Define field type labels and colors
                  $type_labels = [
                    0 => ['label' => 'Single Select', 'class' => 'badge-primary'],
                    1 => ['label' => 'Multi Select', 'class' => 'badge-primary'],
                    2 => ['label' => 'Text', 'class' => 'badge-secondary'],
                    3 => ['label' => 'Date', 'class' => 'badge-info'],
                    4 => ['label' => 'Decimal', 'class' => 'badge-warning'],
                    5 => ['label' => 'Integer', 'class' => 'badge-warning'],
                    6 => ['label' => 'Date', 'class' => 'badge-info'],
                  ];
                  
                  $type_config = isset($type_labels[$field_type]) ? $type_labels[$field_type] : ['label' => 'Unknown', 'class' => 'badge-secondary'];
                  
                  // Debug the field ID to ensure it's correctly passed
                  echo "<!-- Field ID: {$field_id} -->";
                  echo '<div class="card mb-2 field-card ' . ($section_id ? 'assigned-field' : 'unassigned-field') . '">';
                  echo '<div class="card-body field-item" data-field-id="' . $field_id . '">';
                  echo '<div class="d-flex justify-content-between align-items-center">';
                  
                  // Field name - clickable for inline editing
                  echo '<div class="d-flex align-items-center">';
                  echo '<h5 class="card-title mb-0"><span class="field-name-text">' . $field_name . '</span></h5>';
                  echo '</div>';
                  
                  // Type badge - clickable for inline editing
                  echo '<span class="field-type-badge" data-type-id="' . $field_type . '"><span class="badge ' . $type_config['class'] . '">' . $type_config['label'] . '</span></span>';
                  
                              echo '</div>';
                              
                  // Render field input preview based on type
                  echo '<div class="field-preview mb-2 mt-2">';
                  switch ($field_type) {
                                 case 0: // Single Selection
                      echo '<select class="form-control form-control-sm">';
                                    echo '<option>Single selection option</option>';
                                    echo '</select>';
                                    break;
                                 case 1: // Multiple Selection
                      echo '<select class="form-control form-control-sm" multiple style="height: 60px">';
                                    echo '<option>Multiple selection option 1</option>';
                                    echo '<option>Multiple selection option 2</option>';
                                    echo '</select>';
                                    break;
                                 case 2: // Text
                      echo '<textarea class="form-control form-control-sm" rows="2" placeholder="Text input"></textarea>';
                                    break;
                                 case 3: // Date
                      echo '<div class="input-group input-group-sm">';
                      echo '<input type="text" class="form-control" value="DD/MM/YYYY">';
                                    echo '<div class="input-group-append">';
                                    echo '<span class="input-group-text"><i class="fa fa-calendar"></i></span>';
                                    echo '</div>';
                                    echo '</div>';
                                    break;
                                 case 4: // Numeric (step 0.1)
                      echo '<input type="number" class="form-control form-control-sm" step="0.1" placeholder="0.0">';
                                    break;
                    case 5: // Integer
                      echo '<input type="number" class="form-control form-control-sm" step="1" value="0">';
                                    break;
                                 case 6: // Time
                      echo '<div class="input-group input-group-sm">';
                      echo '<input type="text" class="form-control" value="HH:MM">';
                                    echo '<div class="input-group-append">';
                      echo '<span class="input-group-text"><i class="fa fa-clock"></i></span>';
                                    echo '</div>';
                                    echo '</div>';
                                    break;
                              }
                                  echo '</div>';
                                  
                  // Action buttons
                  echo '<div class="field-actions text-right">';
                  
                  // Add arrow buttons for reordering
                  echo '<div class="btn-group btn-group-sm mr-2" role="group">';
                  echo '<button type="button" class="btn btn-outline-secondary move-left-btn" data-field-id="' . $field_id . '" data-table-id="' . $table_id . '" title="Move Left">';
                  echo '<i class="fas fa-chevron-left"></i>';
                  echo '</button>';
                  echo '<button type="button" class="btn btn-outline-secondary move-right-btn" data-field-id="' . $field_id . '" data-table-id="' . $table_id . '" title="Move Right">';
                  echo '<i class="fas fa-chevron-right"></i>';
                  echo '</button>';
                  echo '</div>';
                  
                  echo '<a href="javascript:;" class="btn btn-sm btn-outline-info mr-1 edit-field-btn" data-field-id="' . $field_id . '" data-toggle="modal" data-target="#editFieldModal">';
                  echo '<i class="fas fa-edit"></i>';
                  echo '</a>';
                  
                  // Add Manage Options button for select fields (types 0 and 1)
                  if ($field_type == 0 || $field_type == 1) {
                    echo '<a href="javascript:;" class="btn btn-sm btn-outline-success mr-1 manage-options-btn" data-field-id="' . $field_id . '" data-field-type="' . $field_type . '" data-toggle="modal" data-target="#manageSelectionsModal">';
                    echo '<i class="fas fa-list"></i>';
                    echo '</a>';
                  }
                  
                  echo '<a href="javascript:;" class="btn btn-sm btn-outline-danger delete-field-btn" data-tbid="' . $table_id . '" data-stid="' . $field_id . '">';
                  echo '<i class="fas fa-trash"></i>';
                  echo '</a>';
                  echo '</div>';
                  
                  echo '</div>'; // End card-body
                  echo '</div>'; // End card
                              }
                ?>
</div>

            <!-- Delete Section -->
            <div class="row">
               <div class="col-12">
                  <div class="card border-danger mb-4">
                        <div class="card-header bg-danger text-white">
                        <div class="card-title">Delete <?php echo $listname ?></div>
                        </div>
                        <div class="card-footer">
                           <div class="float-right">
                            <a href="<?php echo $listurl; ?>?del=del&amp;which=<?php echo $which; ?>" class="btn btn-labeled btn-danger" role="button" onclick="return confirm('Are you sure you want to delete this record and all associated data?')">
                              <span class="btn-label"><i class="fa fa-times"></i></span>Delete now!</a>
</div>
</div>
</div>
      </section>
   </div>

   <!-- Delete Field Confirmation Modal -->
   <div class="modal fade" id="deleteFieldModal" tabindex="-1" role="dialog" aria-labelledby="deleteFieldModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
               <h5 class="modal-title" id="deleteFieldModalLabel">Remove Field from Sheet</h5>
               <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <div class="modal-body">
               <p id="deleteFieldMessage">Are you sure you want to remove this field from the sheet?</p>
               <p class="text-muted"><small>This will unlink the field from this sheet but keep it available for other sheets.</small></p>
               <input type="hidden" id="deleteFieldStid">
               <input type="hidden" id="deleteFieldTbid">
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-warning" id="confirmDeleteFieldBtn">
                  <i class="fas fa-trash"></i> Remove Field
               </button>
</div>
</div>

   <!-- JavaScript will be loaded after jQuery -->
   <script type="text/javascript">
      $(document).ready(function() {
   
           // Debug: Check if section move buttons exist
           console.log('Section move up buttons found:', $('.section-move-up-btn').length);
           console.log('Section move down buttons found:', $('.section-move-down-btn').length);
           console.log('Section containers found:', $('.section-container').length);
           
           // Debug: Check button elements
           $('.section-move-up-btn').each(function(index) {
               console.log('Button ' + index + ':', this, 'data-section-id:', $(this).data('section-id'));
           });
           
           // Debug: Check if manage options buttons exist
         const manageButtons = $('.manage-options-btn');
         
         // Debug: Check if modal exists
         const modalElement = $('#manageSelectionsModal');
         
         // Debug: Check if file is being loaded
         
         
         // Debug: Check if console.log is working
         
         
         // Debug: Check if jQuery selectors are working
         
         manageButtons.each(function(index) {
             
         });
         
         // Debug: Check if event handler is bound
         
         
         // Debug: Test manual click trigger
         setTimeout(function() {
              
             const firstButton = $('.manage-options-btn').first();
             if (firstButton.length > 0) {
                 console.log("Found first button, attempting to trigger click...");
                 firstButton.trigger('click');
             } else {
                 console.log("No manage options buttons found for testing");
             }
         }, 2000);
         
         
         

         
                  // Handle modal shown event for manage selections
         $('#manageSelectionsModal').on('shown.bs.modal', function() {
             // Get the field data from the modal data attributes
             const fieldId = $(this).data('field-id');
             const fieldType = $(this).data('field-type');
             const fieldName = $(this).data('field-name');
             
             if (fieldId && fieldName) {
                 // Set modal title
                 $('#manageSelectionsModalLabel').text(`Manage Options for ${fieldName}`);
                 
                 // Clear existing selections
                 $('#currentSelectionsList').empty();
                 
                 // Show loading state
                 $('#currentSelectionsList').html('<li class="list-group-item text-center"><i class="fa fa-spinner fa-spin"></i> Loading options...</li>');
                 
                 // Load field options via AJAX
                 const ajaxUrl = 'ajax/get_field_options.php';
                 
                 $.ajax({
                     url: ajaxUrl,
                     type: 'POST',
                     data: { field_id: fieldId },
                     dataType: 'json',
                     success: function(response) {
                         // Clear loading indicator
                         $('#currentSelectionsList').empty();
                         
                         if (response.status === 'success') {
                             const options = response.options ? response.options.split('|') : [];
                             
                             if (options.length > 0) {
                                 options.forEach(function(option, index) {
                                     if (option.trim() !== '') {
                                         const optionItem = `
                                             <li class="list-group-item d-flex justify-content-between align-items-center" data-index="${index}">
                                                 <span class="option-text">${option}</span>
                                                 <div class="btn-group btn-group-sm">
                                                     <button type="button" class="btn btn-outline-secondary move-up-btn" title="Move Up">
                                                         <i class="fas fa-arrow-up"></i>
                                                     </button>
                                                     <button type="button" class="btn btn-outline-secondary move-down-btn" title="Move Down">
                                                         <i class="fas fa-arrow-down"></i>
                                                     </button>
                                                     <button type="button" class="btn btn-outline-info edit-option-btn">
                                                         <i class="fas fa-pencil-alt"></i>
                                                     </button>
                                                     <button type="button" class="btn btn-outline-danger delete-option-btn">
                                                         <i class="fas fa-trash"></i>
                                                     </button>
                                                 </div>
                                             </li>
                                         `;
                                         $('#currentSelectionsList').append(optionItem);
                                     }
                                 });
                             } else {
                                 $('#currentSelectionsList').html('<li class="list-group-item text-center text-muted">No options available</li>');
                             }
                             
                             // Update options count after loading
                             updateOptionsCount();
                         } else {
                             $('#currentSelectionsList').html(`<li class="list-group-item text-center text-danger">Error: ${response.message}</li>`);
                         }
                     },
                     error: function(xhr, status, error) {
                         $('#currentSelectionsList').html(`<li class="list-group-item text-center text-danger">Error loading options: ${error}</li>`);
                     }
                 });
                 
                 // Clear and focus the search input
                 $('#newSelectionInput').val('').focus();
                 
                 // Remove any existing suggestions dropdown
                 $('#suggestions-dropdown').remove();
             }
                  });
         
         // Handle modal hidden event to clean up search
         $('#manageSelectionsModal').on('hidden.bs.modal', function() {
             // Clear search input and remove suggestions dropdown
             $('#newSelectionInput').val('');
             $('#suggestions-dropdown').remove();
             
             // Clear modal data
             $(this).removeData('field-id field-name field-type');
         });
         
         // Note: Edit field button click is now handled by the modal show event below

         // Note: Modal population is now handled in the click handler above

         // Clear the edit field modal when it's closed to avoid stale data
         $('#editFieldModal').on('hidden.bs.modal', function() {
            $('#edit_field_form')[0].reset();
            $('#edit_field_id').val('');
            $('#editFieldModal .alert').remove();
            // Hide options container by default
            $('#edit_field_options_container').hide();
         });

         // Initialize the section edit button click handler
         $(document).on('click', '.edit-section-btn', function() {
             const sectionId = $(this).attr('data-section-id');
             const sectionName = $(this).closest('.section-header').find('h5').text().trim();
             const sectionDescription = $(this).closest('.section-header').find('p.text-muted small').text().trim();
             
             console.log('Edit section button clicked:', { sectionId, sectionName, sectionDescription });
             
             // Store the data in the modal for when it's shown
             $('#edit_section_modal').data('section-id', sectionId);
             $('#edit_section_modal').data('section-name', sectionName);
             $('#edit_section_modal').data('section-description', sectionDescription);
             
             // Also try to populate immediately as a fallback
             setTimeout(function() {
                 if ($('#edit_section_modal').hasClass('show')) {
                     console.log('Modal is shown, populating fields immediately');
                     populateEditSectionModal(sectionId, sectionName, sectionDescription);
                 }
             }, 100);
         });
         
         // Function to populate edit section modal
         function populateEditSectionModal(sectionId, sectionName, sectionDescription) {
             console.log('Populating edit section modal:', { sectionId, sectionName, sectionDescription });
             
             // Check if elements exist
             console.log('Element existence check:', {
                 sectionId: $('#edit_section_id').length,
                 sectionName: $('#edit_section_name').length,
                 sectionDescription: $('#edit_section_description').length,
                 tableId: $('#edit_table_id').length
             });
             
             // Ensure sectionId is valid
             if (!sectionId || sectionId === 0) {
                 console.error('Invalid section ID:', sectionId);
                 showNotification('danger', 'Invalid section ID. Cannot edit this section.');
                 return;
             }
             
             // Populate the modal fields
             $('#edit_section_id').val(parseInt(sectionId, 10));
             $('#edit_section_name').val(sectionName);
             $('#edit_section_description').val(sectionDescription);
             $('#edit_table_id').val(<?php echo $table_id; ?>);
             
             console.log('Fields populated:', {
                 sectionId: $('#edit_section_id').val(),
                 sectionName: $('#edit_section_name').val(),
                 sectionDescription: $('#edit_section_description').val(),
                 tableId: $('#edit_table_id').val()
             });
             
             // Load associated tables
             $.ajax({
                 url: 'ajax/get_section_tables.php',
                 method: 'GET',
                 headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                 },
                 data: {
                     section_id: sectionId
                 },
                 dataType: 'json',
                 success: function(response) {
                     console.log('Section tables response:', response);
                     
                     // Clear existing selections
                     $('#edit_table_ids').val('');
                     
                     if (response.status === 'success' && response.tables) {
                         // Set the selected tables
                         $('#edit_table_ids').val(response.tables);
                         if (typeof $('#edit_table_ids').selectpicker === 'function') {
                             $('#edit_table_ids').selectpicker('refresh');
                         }
                     }
                 },
                 error: function(xhr, status, error) {
('Error loading section tables:', error);
('Response:', xhr.responseText);
                 }
             });
             
             // Load fields for this section and populate move fields dropdown
             loadSectionFields(sectionId, <?php echo $table_id; ?>);
         }
         
         // Handle modal shown event to populate fields
         $('#edit_section_modal').on('shown.bs.modal', function() {
             const sectionId = $(this).data('section-id');
             const sectionName = $(this).data('section-name');
             const sectionDescription = $(this).data('section-description');
             
             console.log('Modal shown, populating fields:', { sectionId, sectionName, sectionDescription });
             console.log('Modal element:', this);
             console.log('Form elements exist:', {
                 sectionId: $('#edit_section_id').length,
                 sectionName: $('#edit_section_name').length,
                 sectionDescription: $('#edit_section_description').length
             });
             
             // Use the centralized function to populate the modal
             populateEditSectionModal(sectionId, sectionName, sectionDescription);
             
             // Double-check after a short delay to ensure values are set
             setTimeout(function() {
                 console.log('Final field values after population:', {
                     sectionId: $('#edit_section_id').val(),
                     sectionName: $('#edit_section_name').val(),
                     sectionDescription: $('#edit_section_description').val(),
                     tableId: $('#edit_table_id').val()
                 });
             }, 100);
         });
         
         // Handle edit section form submission
         $('#edit_section_form').on('submit', function(e) {
             e.preventDefault();
             
             // Debug: Check form field values before serializing
             const sectionId = $('#edit_section_id').val();
             const sectionName = $('#edit_section_name').val();
             const sectionDescription = $('#edit_section_description').val();
             const tableId = $('#edit_table_id').val();
             const tableIds = $('#edit_table_ids').val();
             
             console.log('Form field values before submission:', {
                 section_id: sectionId,
                 section_name: sectionName,
                 section_description: sectionDescription,
                 table_id: tableId,
                 table_ids: tableIds
             });
             
             // Validate required fields before submission
             if (!sectionId || !sectionName) {
                 console.error('Missing required fields:', { sectionId, sectionName });
                 showNotification('danger', 'Missing required fields. Please ensure section ID and name are populated.');
                 
                 // Try to repopulate the modal if fields are missing
                 const storedSectionId = $('#edit_section_modal').data('section-id');
                 const storedSectionName = $('#edit_section_modal').data('section-name');
                 const storedSectionDescription = $('#edit_section_modal').data('section-description');
                 
                 if (storedSectionId && storedSectionName) {
                     console.log('Attempting to repopulate modal with stored data');
                     populateEditSectionModal(storedSectionId, storedSectionName, storedSectionDescription);
                 }
                 
                 return;
             }
             
             const formData = $(this).serialize();
             console.log('Serialized form data:', formData);
             
             // Show loading state
             const submitBtn = $(this).find('button[type="submit"]');
             const originalBtnText = submitBtn.html();
             submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
             
             $.ajax({
                 url: $(this).attr('action'),
                 method: 'POST',
                 data: formData,
                 dataType: 'json',
                 success: function(response) {
                     console.log('Edit section response:', response);
                     submitBtn.html(originalBtnText).prop('disabled', false);
                     
                     if (response.status === 'success') {
                         $('#edit_section_modal').modal('hide');
                         showNotification('success', 'Section updated successfully!');
                         
                         // Reload page after a short delay
                         setTimeout(function() {
                             location.reload();
                         }, 1000);
                     } else {
                         showNotification('danger', 'Error updating section: ' + (response.message || 'Unknown error'));
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error('Error updating section:', error);
                     console.error('Response:', xhr.responseText);
                     submitBtn.html(originalBtnText).prop('disabled', false);
                     
                     let errorMsg = 'Error updating section. Please try again.';
                     try {
                         const response = JSON.parse(xhr.responseText);
                         if (response.message) {
                             errorMsg = response.message;
                         }
                     } catch (e) {
                         // Parsing failed, use default message
                     }
                     
                     showNotification('danger', errorMsg);
                 }
             });
         });
         
         // Debug form button handler
         $('#debugFormBtn').on('click', function() {
             const sectionId = $('#edit_section_id').val();
             const sectionName = $('#edit_section_name').val();
             const sectionDescription = $('#edit_section_description').val();
             const tableId = $('#edit_table_id').val();
             const tableIds = $('#edit_table_ids').val();
             
             console.log('=== FORM DEBUG INFO ===');
             console.log('Form field values:', {
                 section_id: sectionId,
                 section_name: sectionName,
                 section_description: sectionDescription,
                 table_id: tableId,
                 table_ids: tableIds
             });
             
             const formData = $('#edit_section_form').serialize();
             console.log('Serialized form data:', formData);
             
             const modalData = {
                 'section-id': $('#edit_section_modal').data('section-id'),
                 'section-name': $('#edit_section_modal').data('section-name'),
                 'section-description': $('#edit_section_modal').data('section-description')
             };
             console.log('Modal stored data:', modalData);
             
             alert('Check console for debug info');
         });
         
         // Function to populate section dropdown in edit field modal
         function populateSectionDropdown(fieldId, tableId, currentSectionId) {
             console.log("🔧 populateSectionDropdown called with:", {fieldId, tableId, currentSectionId}); // Debug logging
             
             $.ajax({
                 url: 'ajax/get_sections.php',
                 method: 'GET',
                 headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                 },
                 data: {
                     table_id: tableId
                 },
                 dataType: 'json',
                 success: function(response) {
                     console.log("🔧 Sections AJAX response:", response); // Debug logging
                     
                     const $dropdown = $('#edit_field_section');
                     $dropdown.empty();
                     $dropdown.append('<option value="">No Section (Unsectioned)</option>');
                     
                     if (response.status === 'success' && response.sections && response.sections.length > 0) {
                         console.log("🔧 Populating sections dropdown with", response.sections.length, "sections"); // Debug logging
                         response.sections.forEach(function(section) {
                             const selected = (currentSectionId !== null && currentSectionId !== '' && section.section_id == currentSectionId) ? 'selected' : '';
                             $dropdown.append(`<option value="${section.section_id}" ${selected}>${section.section_name}</option>`);
                         });
                         console.log("🔧 Sections dropdown populated successfully"); // Debug logging
                     } else {
                         console.log("🔧 No sections found or error in response"); // Debug logging
                         // If no sections found, show a message
                         $dropdown.append('<option value="" disabled>No sections available</option>');
                     }
                 },
                 error: function(xhr, status, error) {
                     console.log("🔧 Sections AJAX error:", {xhr, status, error}); // Debug logging
                     
                     const $dropdown = $('#edit_field_section');
                     $dropdown.empty();
                     $dropdown.append('<option value="">No Section (Unsectioned)</option>');
                     $dropdown.append('<option value="" disabled>Error loading sections</option>');
                 }
             });
         }
         
         // Function to load fields for a section
         function loadSectionFields(sectionId, tableId) {
             console.log('Loading fields for section:', sectionId, 'table:', tableId);
             
             // Get the current fields container
             const $currentFields = $('#current_section_fields');
             
             // Show loading state
             $currentFields.html('<small class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading fields...</small>');
             
             // Load current fields in the section
             $.ajax({
                 url: 'ajax/get_section_fields.php',
                 method: 'GET',
                 headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                 },
                 data: {
                     section_id: sectionId,
                     table_id: tableId
                 },
                 dataType: 'json',
                 success: function(response) {
                     console.log('Section fields response:', response);
                     
                     if (response.status === 'success') {
                         if (response.fields && response.fields.length > 0) {
                             let fieldsHtml = '<div class="list-group list-group-flush">';
                             response.fields.forEach(function(field) {
                                 fieldsHtml += `
                                     <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                         <span>${field.field_name}</span>
                                         <small class="text-muted">${field.field_type_name}</small>
                                     </div>
                                 `;
                             });
                             fieldsHtml += '</div>';
                             $currentFields.html(fieldsHtml);
                         } else {
                             $currentFields.html('<small class="text-muted">No categories currently in this section</small>');
                         }
                     } else {
                         $currentFields.html('<small class="text-danger">Error loading categories: ' + (response.message || 'Unknown error') + '</small>');
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error('Error loading section fields:', error);
                     $currentFields.html('<small class="text-danger">Error loading categories: ' + error + '</small>');
                 }
             });
             
             // Load available fields to move to this section
             $.ajax({
                 url: 'ajax/get_available_fields.php',
                 method: 'GET',
                 headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                 },
                 data: {
                     section_id: sectionId,
                     table_id: tableId
                 },
                 dataType: 'json',
                 success: function(response) {
                     const $moveFieldsDropdown = $('#move_fields_to_section');
                     $moveFieldsDropdown.empty();
                     
                     if (response.status === 'success' && response.fields && response.fields.length > 0) {
                         response.fields.forEach(function(field) {
                             const sectionInfo = field.current_section ? ` (currently in: ${field.current_section})` : ' (unsectioned)';
                             $moveFieldsDropdown.append(`<option value="${field.stid}">${field.field_name}${sectionInfo}</option>`);
                         });
                     } else {
                         $moveFieldsDropdown.append('<option value="" disabled>No categories available to move</option>');
                     }
                 }
             });
         }
         
         // Initialize the assign to section button
         $(document).on('click', '.assign-to-section-btn', function() {
             const tableId = $(this).data('table-id');
             $('#assign_section_table_id').val(tableId);
             
             // Load available sections
             $.ajax({
                 url: 'ajax/get_sections.php',
                 method: 'GET',
                 data: {
                     table_id: tableId
                 },
                 dataType: 'json',
                 success: function(response) {
                     const selectElement = $('#assign_section_select');
                     selectElement.empty();
                     selectElement.append('<option value="">Select a Section</option>');
                     
                     if (response.status === 'success' && response.sections) {
                         response.sections.forEach(function(section) {
                             selectElement.append(`<option value="${section.section_id}">${section.section_name}</option>`);
                         });
                     }
                 }
             });
         });
         
         // Handle section assignment form submission
         $('#assign_section_form').on('submit', function(e) {
             e.preventDefault();
             
             const formData = $(this).serialize();
             
             $.ajax({
                 url: $(this).attr('action'),
                 method: 'POST',
                 data: formData,
                 dataType: 'json',
                 beforeSend: function() {
                     $('button[type="submit"]', '#assign_section_form').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
                 },
                 success: function(response) {
                     if (response.status === 'success') {
                         $('#assign_section_modal').modal('hide');
                         showNotification('success', response.message);
                         
                         // Reload page after a short delay
                         setTimeout(function() {
                             location.reload();
                         }, 1000);
                     } else {
                         showNotification('danger', response.message || 'An error occurred');
                     }
                     
                     $('button[type="submit"]', '#assign_section_form').prop('disabled', false).html('Assign Fields');
                 },
                 error: function() {
                     showNotification('danger', 'A network error occurred');
                     $('button[type="submit"]', '#assign_section_form').prop('disabled', false).html('Assign Fields');
                 }
             });
         });



         // Debounce function to limit API calls
         function debounce(func, wait) {
             let timeout;
             return function(...args) {
                 clearTimeout(timeout);
                 timeout = setTimeout(() => func.apply(this, args), wait);
             };
         }

         
         

         
         // Handle input for suggestions with improved search
         $(document).on('input', '#newSelectionInput', function() {
             console.log('🔍 SEARCH: Input event triggered - Value:', $(this).val());
             
             // Log every keystroke
             const currentValue = $(this).val();
             console.log('🔍 SEARCH: Current input value:', currentValue, 'Length:', currentValue.length);
             
             const $input = $(this);
             const fieldId = $('#manageSelectionsModal').data('field-id');
             const searchTerm = $input.val().trim();
             
             console.log('🔍 SEARCH: Field ID:', fieldId, 'Search term:', searchTerm);
             
             if (!fieldId) {
                 console.error('❌ SEARCH: No field ID found in modal data');
                 return;
             }
             
             if (searchTerm.length < 2) {
                 console.log('🔍 SEARCH: Term too short, hiding dropdown');
                 $('#suggestions-dropdown').remove();
                 return;
             }
             
             // Clear any existing suggestions dropdown
             $('#suggestions-dropdown').remove();
             
             // Create suggestions dropdown
             $input.after('<div id="suggestions-dropdown" class="dropdown-menu w-100" style="position: absolute; z-index: 9999;"></div>');
             const $suggestionsDropdown = $('#suggestions-dropdown');
             
             // Show loading state
             $suggestionsDropdown.html('<div class="dropdown-item"><i class="fa fa-spinner fa-spin"></i> Searching...</div>').addClass('show');
             
             console.log('🔍 SEARCH: Making AJAX request to get_option_suggestions.php');
             
             // Fetch suggestions from server
             $.ajax({
                 url: 'ajax/get_option_suggestions.php',
                 method: 'POST',
                 data: {
                     stid: fieldId,
                     term: searchTerm
                 },
                 dataType: 'json',
                 success: function(response) {
                     console.log('🔍 SEARCH: Response received:', response);
                     $suggestionsDropdown.empty();
                     
                     if (response.status === 'success' && response.suggestions && response.suggestions.length > 0) {
                         console.log('🔍 SEARCH: Found', response.suggestions.length, 'suggestions');
                         response.suggestions.forEach(function(suggestion) {
                             const suggestionItem = `
                                 <a class="dropdown-item suggestion-item" href="#" data-value="${suggestion.value}">
                                     <div class="d-flex justify-content-between align-items-center">
                                         <span>${suggestion.value}</span>
                                         <small class="text-muted">${suggestion.count} uses</small>
                                     </div>
                                 </a>
                             `;
                             $suggestionsDropdown.append(suggestionItem);
                         });
                         
                         $suggestionsDropdown.addClass('show');
                         console.log('🔍 SEARCH: Dropdown shown with', response.suggestions.length, 'items');
                     } else {
                         console.log('🔍 SEARCH: No suggestions found');
                         $suggestionsDropdown.html(`
                             <div class="dropdown-item text-muted">
                                 <i class="fa fa-info-circle"></i> No similar options found
                             </div>
                         `).addClass('show');
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error('❌ SEARCH: AJAX error:', { status, error, responseText: xhr.responseText });
                     
                     // Show a more helpful error message
                     if (xhr.status === 404) {
                         $suggestionsDropdown.html(`
                             <div class="dropdown-item text-warning">
                                 <i class="fa fa-exclamation-triangle"></i> Suggestions service not available
                             </div>
                             <div class="dropdown-item text-muted">
                                 <small>You can still type and add new options manually</small>
                             </div>
                         `).addClass('show');
                     } else {
                         $suggestionsDropdown.html('<div class="dropdown-item text-danger"><i class="fa fa-exclamation-triangle"></i> Error loading suggestions</div>').addClass('show');
                     }
                 }
             });
         });
         
         // Handle focus on search input to ensure proper initialization
         $(document).on('focus', '#newSelectionInput', function() {
             console.log('🔍 SEARCH: Input field focused');
             const fieldId = $('#manageSelectionsModal').data('field-id');
             if (fieldId) {
                 console.log('🔍 SEARCH: Field ID available for search:', fieldId);
             } else {
                 console.error('❌ SEARCH: No field ID available when input focused');
             }
         });
         
         // Handle keydown events for search input
         $(document).on('keydown', '#newSelectionInput', function(e) {
             if (e.key === 'Escape') {
                 // Close suggestions dropdown on Escape key
                 $('#suggestions-dropdown').remove();
                 $(this).blur();
             }
         });
         
         // Handle suggestion selection
         $(document).on('click', '.suggestion-item', function(e) {
             e.preventDefault();
             const selectedValue = $(this).data('value');
             $('#newSelectionInput').val(selectedValue);
             $('#suggestions-dropdown').removeClass('show');
             
             // Auto-click add button
             $('#addSelectionBtn').click();
         });
         
         // Hide suggestions when clicking outside
         $(document).on('click', function(e) {
             if (!$(e.target).closest('#newSelectionInput, #suggestions-dropdown').length) {
                 $('#suggestions-dropdown').removeClass('show');
             }
         });



         // Populate section dropdown when clicked
         $(document).on('click', '.section-dropdown-toggle', function() {
            const dropdown = $(this).parent().find('.section-dropdown');
            const fieldItem = $(this).closest('.field-item');
            const fieldId = fieldItem.data('field-id');
            const sectionId = fieldItem.data('section-id') || null;
            const tableId = <?php echo $which; ?>; // Use PHP to get table ID
            

            
            // Clear existing section options but keep the header and remove option
            dropdown.find('.section-option, .section-loading-placeholder').remove();
            dropdown.find('.dropdown-divider').before('<div class="dropdown-item section-loading-placeholder"><small><i>Loading sections...</i></small></div>');
            
            // Load available sections
            $.ajax({
               url: 'ajax/get_sections.php',
               type: 'GET',
                 data: {
                  table_id: tableId
                 },
               dataType: 'json',

               success: function(response) {
                  // Remove loading placeholder
                  dropdown.find('.section-loading-placeholder').remove();
                  
                  if (response.status === 'success') {
                     // Add sections to dropdown
                     let hasOptions = false;
                     $.each(response.sections, function(i, section) {
                        // Skip current section
                        if (sectionId !== null && section.section_id == sectionId) {
                           return true; // Skip to next iteration
                        }
                        
                        hasOptions = true;
                        const item = $('<a class="dropdown-item section-option" href="javascript:void(0)" data-section-id="' + section.section_id + '" data-field-id="' + fieldId + '" data-table-id="' + tableId + '">' + 
                                      section.section_name + '</a>');
                        
                        // Insert before the divider
                        dropdown.find('.dropdown-divider').before(item);
                     });
                     
                     if (!hasOptions) {
                        dropdown.find('.dropdown-divider').before('<span class="dropdown-item disabled">No other sections available</span>');
                     }
                     } else {
                     // Show error message in dropdown
                     dropdown.find('.dropdown-divider').before('<span class="dropdown-item disabled">Error: ' + response.message + '</span>');
                  }
               },
               error: function(xhr, status, error) {
                  dropdown.find('.section-loading-placeholder').remove();
                  dropdown.find('.dropdown-divider').before('<span class="dropdown-item disabled">Error loading sections</span>');
               }
            });
         });

         // Handle section assignment
         $(document).on('click', '.section-option', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const sectionId = $(this).data('section-id');
            const fieldId = $(this).data('field-id');
            const tableId = $(this).data('table-id');
            
            console.log('Assigning field ID:', fieldId, 'to section ID:', sectionId, 'in table ID:', tableId);
            
            assignFieldToSection(fieldId, tableId, sectionId);
         });
         
         // Handle remove from section
         $(document).on('click', '.remove-from-section-btn', function(e) {
                 e.preventDefault();
            e.stopPropagation();
            
            const fieldId = $(this).data('field-id');
            const tableId = $(this).data('table-id');
            
            console.log('Removing field ID:', fieldId, 'from section in table ID:', tableId);
            
            removeFieldFromSection(fieldId, tableId);
         });
         
         // Handle section move up
         $(document).on('click', '.section-move-up-btn', function(e) {
             console.log('Section move up clicked');
             e.preventDefault();
             e.stopPropagation();
             
             const $button = $(this);
             const $section = $button.closest('.section-container');
             console.log('Button element:', $button);
             console.log('Section found:', $section.length);
             console.log('Section HTML:', $section[0]);
             
             // Skip if this is the "Available Categories" section
             if ($section.find('.unsectioned-fields').length > 0) {
                 console.log('Skipping Available Categories section');
                 return;
             }
             
             const $prevSection = $section.prev('.section-container');
             console.log('Previous section found:', $prevSection.length);
             
             if ($prevSection.length > 0) {
                 console.log('Moving section up');
                 $section.insertBefore($prevSection);
                 updateSectionOrder();
                 
                 // Add visual feedback
                 $section.css('background-color', '#d4edda');
                 setTimeout(function() {
                     $section.css('background-color', '');
                 }, 500);
             } else {
                 console.log('No previous section found');
             }
         });
         
         // Handle section move down
         $(document).on('click', '.section-move-down-btn', function(e) {
             console.log('Section move down clicked');
             e.preventDefault();
             e.stopPropagation();
             
             const $section = $(this).closest('.section-container');
             console.log('Section found:', $section.length);
             
             // Skip if this is the "Available Categories" section
             if ($section.find('.unsectioned-fields').length > 0) {
                 console.log('Skipping Available Categories section');
                 return;
             }
             
             const $nextSection = $section.next('.section-container');
             console.log('Next section found:', $nextSection.length);
             
             if ($nextSection.length > 0) {
                 console.log('Moving section down');
                 $section.insertAfter($nextSection);
                 updateSectionOrder();
                 
                 // Add visual feedback
                 $section.css('background-color', '#d4edda');
                 setTimeout(function() {
                     $section.css('background-color', '');
                 }, 500);
             } else {
                 console.log('No next section found');
             }
         });
         
         // Function to update section order
         function updateSectionOrder() {
             console.log('updateSectionOrder called');
             const sections = [];
             $('.section-container').each(function() {
                 // Skip the "Available Categories" section (which has unsectioned-fields)
                 if ($(this).find('.unsectioned-fields').length > 0) {
                     console.log('Skipping Available Categories section in updateSectionOrder');
                     return;
                 }
                 
                 const sectionId = $(this).find('.section-move-up-btn').data('section-id');
                 console.log('Found section ID:', sectionId);
                 if (sectionId) {
                     sections.push(sectionId);
                 }
             });
             
             console.log('Sections to update:', sections);
             
             if (sections.length > 0) {
                 $.ajax({
                     url: 'ajax/update_section_order.php',
                     type: 'POST',
                     data: {
                         sections: sections
                     },
                     dataType: 'json',
                     success: function(response) {
                         console.log('AJAX response:', response);
                         if (response.status === 'success') {
                             console.log('Section order updated successfully');
                         } else {
                             console.error('Failed to update section order:', response.message);
                             // Optionally show user notification
                         }
                     },
                     error: function(xhr, status, error) {
                         console.error('Error updating section order:', error);
                         console.error('XHR:', xhr);
                         // Optionally show user notification
                     }
                 });
             }
         }
         
         // Function to update field order
         function updateFieldOrder(sectionId, tableId, fieldIds) {
             $.ajax({
               url: 'ajax/update_field_order.php',
               type: 'POST',
                 data: {
                  section_id: sectionId,
                  table_id: tableId,
                  field_ids: JSON.stringify(fieldIds)
                 },
                 dataType: 'json',
                 success: function(response) {
                     if (response.status === 'success') {
                     // Show success message (optional)
                     console.log('Field order updated successfully');
                     } else {
                     // Show error message
                     alert('Error updating field order: ' + response.message);
                  }
               },
               error: function(xhr, status, error) {
                  console.error('AJAX Error Details:', {
                      status: status,
                      error: error,
                      responseText: xhr.responseText,
                      statusCode: xhr.status
                  });
                  
                  let errorMsg = 'Network error when updating field order';
                  try {
                      const response = JSON.parse(xhr.responseText);
                      if (response.message) {
                          errorMsg = response.message;
                      }
                  } catch (e) {
                      errorMsg = `HTTP ${xhr.status}: ${error}`;
                  }
                  
                  alert(errorMsg);
                 }
             });
         }

         // Function to assign field to section
         function assignFieldToSection(fieldId, tableId, sectionId) {
            // Validate inputs
            if (!fieldId || !tableId || !sectionId) {
                 return;
             }
             
            // Show loading state or indicator
            const fieldItem = $('.field-item[data-field-id="' + fieldId + '"]');
            fieldItem.css('opacity', '0.7');
            
             $.ajax({
               url: 'ajax/assign_field_section.php',
               type: 'POST',
                         data: {
                  field_id: fieldId,
                  table_id: tableId,
                  section_id: sectionId
                         },
                 dataType: 'json',
                 success: function(response) {
                  fieldItem.css('opacity', '1');
                  
                  if (response.status === 'success') {
                     // Flash a success indicator
                     fieldItem.css('background-color', '#d4edda');
                         setTimeout(function() {
                        location.reload();
                     }, 500);
                         } else {
                     // Show error message
                     fieldItem.css('background-color', '#f8d7da');
                     alert('Error assigning field to section: ' + response.message);
                     setTimeout(function() {
                        fieldItem.css('background-color', '');
                     }, 1000);
                     }
                 },
                 error: function(xhr, status, error) {
                  fieldItem.css('opacity', '1');
                  fieldItem.css('background-color', '#f8d7da');
                  alert('Network error when assigning field to section. Check console for details.');
                  setTimeout(function() {
                     fieldItem.css('background-color', '');
                  }, 1000);
                 }
             });
         }

         // Function to remove field from section
         function removeFieldFromSection(fieldId, tableId) {
            // Validate inputs
            if (!fieldId || !tableId) {
                 return;
             }
             
            // Show loading state or indicator
            const fieldItem = $('.field-item[data-field-id="' + fieldId + '"]');
            fieldItem.css('opacity', '0.7');
            
                     $.ajax({
               url: 'ajax/remove_field_section.php',
                 type: 'POST',
                         data: {
                  field_id: fieldId,
                  table_id: tableId
                         },
                         dataType: 'json',
                         success: function(response) {
                  fieldItem.css('opacity', '1');
                  
                  if (response.status === 'success' || response.status === 'warning') {
                     // Flash a success indicator
                     fieldItem.css('background-color', '#d4edda');
                         setTimeout(function() {
                        location.reload();
                     }, 500);
                             } else {
                                 // Show error message
                     fieldItem.css('background-color', '#f8d7da');
                     alert('Error removing field from section: ' + response.message);
                     setTimeout(function() {
                        fieldItem.css('background-color', '');
                     }, 1000);
                             }
                         },
                         error: function(xhr, status, error) {
                  fieldItem.css('opacity', '1');
                  fieldItem.css('background-color', '#f8d7da');
                  alert('Network error when removing field from section. Check console for details.');
                  setTimeout(function() {
                     fieldItem.css('background-color', '');
                  }, 1000);
                         }
                     });
                 }
             });

        // Handle the manage options button click
        $(document).on('click', '.manage-options-btn', function(e) {
            const fieldId = $(this).data('field-id');
            const fieldType = $(this).data('field-type');
            const fieldName = $(this).closest('.card-body').find('.field-name-text').text().trim();
            
            // Set data on modal
            $('#manageSelectionsModal')
                .data('field-id', fieldId)
                .data('field-name', fieldName)
                .data('field-type', fieldType);
            
            // Set modal title
            $('#manageSelectionsModalLabel').text(`Manage Options for ${fieldName}`);
            
            // Clear existing selections
            $('#currentSelectionsList').empty();
             
            // Show loading state
            $('#currentSelectionsList').html('<li class="list-group-item text-center"><i class="fa fa-spinner fa-spin"></i> Loading options...</li>');
             
            // Load field options via AJAX
            const ajaxUrl = 'ajax/get_field_options.php';
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    field_id: fieldId
                },
                dataType: 'json',
                success: function(response) {
                    // Clear loading indicator
                    $('#currentSelectionsList').empty();
                    
                    if (response.status === 'success') {
                        // Parse options 
                        const options = response.options ? response.options.split('|') : [];
                        
                        if (options.length > 0) {
                            // Add options to list
                            options.forEach(function(option, index) {
                                if (option.trim() !== '') {
                                    const optionItem = `
                                        <li class="list-group-item d-flex justify-content-between align-items-center" data-index="${index}">
                                            <span class="option-text">${option}</span>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary move-up-btn" title="Move Up">
                                                    <i class="fas fa-arrow-up"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary move-down-btn" title="Move Down">
                                                    <i class="fas fa-arrow-down"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info edit-option-btn">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger delete-option-btn">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </li>
                                    `;
                                    $('#currentSelectionsList').append(optionItem);
                                }
                            });
                        } else {
                            $('#currentSelectionsList').html('<li class="list-group-item text-center text-muted">No options available</li>');
                        }
                    } else {
                        // Check if redirect is needed for database update
                        if (response.redirect) {
                            alert('Database update needed. Please run the database update script first.');
                            window.location.href = 'db_update.php';
                        } else {
                            showNotification('danger', 'Error updating options: ' + response.message);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $('#currentSelectionsList').html(`<li class="list-group-item text-center text-danger">Error loading options: ${error}</li>`);
                }
            });
        });

        // Function to update options count
        function updateOptionsCount() {
            const count = $('#currentSelectionsList').find('li').filter(function() {
                const optionText = $(this).find('.option-text').text().trim();
                return optionText !== '' && !$(this).hasClass('text-muted') && !$(this).hasClass('text-danger');
            }).length;
            
            $('#optionsCount').text(count).removeClass('badge-secondary badge-warning badge-success');
            
            if (count === 0) {
                $('#optionsCount').addClass('badge-warning').text('0');
            } else if (count > 10) {
                $('#optionsCount').addClass('badge-success').text(count);
            } else {
                $('#optionsCount').addClass('badge-secondary').text(count);
            }
        }
        
        // Handle adding new options
        $('#addSelectionBtn').on('click', function() {
            const newOption = $('#newSelectionInput').val().trim();
            
            if (newOption === '') {
                // Highlight input if empty
                $('#newSelectionInput').addClass('is-invalid');
                setTimeout(() => $('#newSelectionInput').removeClass('is-invalid'), 2000);
                return;
            }
            
            // Check if option already exists
            const existingOptions = [];
            $('#currentSelectionsList').find('li').each(function() {
                const optionText = $(this).find('.option-text').text().trim();
                if (optionText !== '' && !$(this).hasClass('text-muted') && !$(this).hasClass('text-danger')) {
                    existingOptions.push(optionText.toLowerCase());
                }
            });
            
            if (existingOptions.includes(newOption.toLowerCase())) {
                // Option already exists
                $('#newSelectionInput').addClass('is-invalid');
                $('#newSelectionInput').next('.invalid-feedback').remove();
                $('#newSelectionInput').after('<div class="invalid-feedback">This option already exists</div>');
                setTimeout(() => {
                    $('#newSelectionInput').removeClass('is-invalid');
                    $('#newSelectionInput').next('.invalid-feedback').remove();
                }, 3000);
                return;
            }
            
            // Remove "no options" message if present
            $('#currentSelectionsList').find('li.text-muted, li.text-danger').remove();
            
            // Add to list
            const index = $('#currentSelectionsList').children().length;
            const optionItem = `
                <li class="list-group-item d-flex justify-content-between align-items-center" data-index="${index}">
                    <span class="option-text">${newOption}</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary move-up-btn" title="Move Up">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary move-down-btn" title="Move Down">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-outline-info edit-option-btn">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger delete-option-btn">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </li>
            `;
            $('#currentSelectionsList').append(optionItem);
            
            // Clear input and hide suggestions
            $('#newSelectionInput').val('').focus();
            $('#suggestions-dropdown').removeClass('show').empty();
            
            // Show success indicator
            const $newItem = $('#currentSelectionsList').find('li').last();
            $newItem.css('background-color', '#d4edda');
            setTimeout(() => {
                $newItem.css('background-color', '');
            }, 1000);
            
            // Update options count
            updateOptionsCount();
        });
        
        // Handle editing options
        $(document).on('click', '.edit-option-btn', function() {
            const $item = $(this).closest('li');
            const $optionText = $item.find('.option-text');
            const currentText = $optionText.text();
            
            // Save original text for cancelling
            $optionText.data('original-text', currentText);
            
            // Replace text with input
            $optionText.html(`
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control edit-option-input" value="${currentText}">
                    <div class="input-group-append">
                        <button class="btn btn-success save-option-edit" type="button">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-secondary cancel-option-edit" type="button">
                            <i class="fas fa-times"></i>
                        </button>
</div>
            `);
            
            // Focus on input and select all text
            const $input = $optionText.find('input');
            $input.focus().select();
            
            // Handle Enter key in edit mode
            $input.on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    $item.find('.save-option-edit').click();
                }
            });
            
            // Handle Escape key in edit mode
            $input.on('keydown', function(e) {
                if (e.which === 27) { // Escape key
                    e.preventDefault();
                    $item.find('.cancel-option-edit').click();
                }
            });
        });
        
        // Save option edit
        $(document).on('click', '.save-option-edit', function() {
            const $item = $(this).closest('li');
            const $optionText = $item.find('.option-text');
            const newText = $optionText.find('input').val().trim();
            
            if (newText !== '') {
                // Check if the new text already exists (case-insensitive)
                const existingOptions = [];
                $('#currentSelectionsList').find('li').each(function() {
                    if ($(this) !== $item) { // Exclude current item
                        const optionText = $(this).find('.option-text').text().trim();
                        if (optionText !== '' && !$(this).hasClass('text-muted') && !$(this).hasClass('text-danger')) {
                            existingOptions.push(optionText.toLowerCase());
                        }
                    }
                });
                
                if (existingOptions.includes(newText.toLowerCase())) {
                    // Option already exists, show error
                    $optionText.html(`
                        <span class="text-danger">${newText}</span>
                        <small class="d-block text-danger">Option already exists</small>
                    `);
                    setTimeout(() => {
                        $optionText.text(newText);
                    }, 2000);
                    return;
                }
                
                $optionText.text(newText);
                
                // Show success indicator
                $item.css('background-color', '#d4edda');
                setTimeout(() => {
                    $item.css('background-color', '');
                }, 1000);
                
                // Update options count
                updateOptionsCount();
            } else {
                // If empty, restore original text
                const originalText = $optionText.data('original-text') || 'Option';
                $optionText.text(originalText);
            }
        });
        
        // Cancel option edit
        $(document).on('click', '.cancel-option-edit', function() {
            const $item = $(this).closest('li');
            const $optionText = $item.find('.option-text');
            const originalText = $optionText.data('original-text');
            
            // Restore original text
            $optionText.text(originalText);
        });
        
        // Handle deleting options
        $(document).on('click', '.delete-option-btn', function() {
            const $item = $(this).closest('li');
            const optionText = $item.find('.option-text').text().trim();
            
            // Show confirmation dialog
            if (confirm(`Are you sure you want to delete the option "${optionText}"?`)) {
                $item.fadeOut(300, function() {
                    $(this).remove();
                    
                    // If no options left, show message
                    if ($('#currentSelectionsList').children().length === 0) {
                        $('#currentSelectionsList').html('<li class="list-group-item text-center text-muted">No options available</li>');
                    }
                    
                    // Update options count
                    updateOptionsCount();
                });
            }
        });
        
        // Handle moving options up
        $(document).on('click', '.move-up-btn', function() {
            const $item = $(this).closest('li');
            const $prevItem = $item.prev('li');
            
            if ($prevItem.length > 0 && !$prevItem.hasClass('text-muted') && !$prevItem.hasClass('text-danger')) {
                $item.insertBefore($prevItem);
                updateOptionsCount();
                
                // Add visual feedback
                $item.css('background-color', '#d4edda');
                setTimeout(function() {
                    $item.css('background-color', '');
                }, 500);
            }
        });
        
        // Handle moving options down
        $(document).on('click', '.move-down-btn', function() {
            const $item = $(this).closest('li');
            const $nextItem = $item.next('li');
            
            if ($nextItem.length > 0 && !$nextItem.hasClass('text-muted') && !$nextItem.hasClass('text-danger')) {
                $item.insertAfter($nextItem);
                updateOptionsCount();
                
                // Add visual feedback
                $item.css('background-color', '#d4edda');
                setTimeout(function() {
                    $item.css('background-color', '');
                }, 500);
            }
        });
        


        // Handle input keypress for adding options
        $('#newSelectionInput').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $('#addSelectionBtn').click();
            }
        });

         
         
         

         
        
        
        
                 // Toggle field options visibility based on field type
         $('#edit_field_type').on('change', function() {
             const fieldType = $(this).val();
             
             // Show options container only for select fields (types 0 and 1)
             if (fieldType == 0 || fieldType == 1) {
                 $('#edit_field_options_container').show();
             } else {
                 $('#edit_field_options_container').hide();
             }
         });
         
         

        

   </script>

   <!-- Edit Section Modal -->
   <div class="modal fade" id="edit_section_modal" tabindex="-1" role="dialog" aria-labelledby="editSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <form id="edit_section_form" method="post" action="ajax/update_section.php">
               <div class="modal-header bg-info text-white">
                  <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
               <div class="modal-body">
                  <input type="hidden" id="edit_section_id" name="section_id">
                  <input type="hidden" id="edit_table_id" name="table_id">
                  
                  <div class="form-group">
                     <label for="edit_section_name">Section Name</label>
                     <input type="text" class="form-control" id="edit_section_name" name="section_name" required>
                  </div>
                  
                  <div class="form-group">
                     <label for="edit_section_description">Section Description</label>
                     <textarea class="form-control" id="edit_section_description" name="section_description" rows="3"></textarea>
                  </div>
                  
                  <div class="form-group">
                     <label for="edit_table_ids">Associated Tables</label>
                     <select class="form-control select2" id="edit_table_ids" name="table_ids[]" multiple>
                        <?php
                        // Fetch all tables for selection
                        $tables_query = "SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name";
                        $tables_result = $supabase_pdo->query($tables_query);
                        $tables = $tables_result->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($tables as $table) {
                           echo "<option value='" . $table['tbid'] . "'>" . htmlspecialchars($table['tab_name']) . "</option>";
                        }
                        ?>
                     </select>
                  </div>
                  
                  <hr>
                  <h6>Field Management</h6>
                  <div class="form-group">
                     <label>Current Categories in This Section</label>
                     <div id="current_section_fields" class="border rounded p-2" style="min-height: 50px;">
                        <small class="text-muted">Loading fields...</small>
</div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-info" id="debugFormBtn">Debug Form</button>
                  <button type="submit" class="btn btn-primary">Save Changes</button>
               </div>
            </form>
</div>
   </div>
   
   <!-- Assign Unsectioned Fields Modal -->
   <div class="modal fade" id="assign_section_modal" tabindex="-1" role="dialog" aria-labelledby="assignSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <form id="assign_section_form" method="post" action="ajax/assign_section.php">
               <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title" id="assignSectionModalLabel">Assign Available Categories</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <input type="hidden" id="assign_section_table_id" name="table_id">
                  
                  <div class="form-group">
                     <label for="assign_section_select">Select Section</label>
                     <select class="form-control" id="assign_section_select" name="section_id" required>
                        <option value="">Select a Section</option>
                     </select>
                  </div>
                  
                  <div class="alert alert-info">
                     <p>This will move all unsectioned fields for this table into the selected section.</p>
</div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Assign Categories</button>
               </div>
            </form>
</div>
   </div>

   <!-- Add Field Modal -->
   <div class="modal fade" id="addFieldModal" tabindex="-1" role="dialog" aria-labelledby="addFieldModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title" id="addFieldModalLabel">Add New Category</h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <form method="post" action="" id="addFieldForm">
               <div class="modal-body">
                  <input type="hidden" name="newadmin" value="newfield">
                  <input type="hidden" name="which" value="<?php echo $which; ?>">
                  
                  <div class="form-group">
                     <label for="stid">Select Field</label>
                     <select class="form-control" id="stid" name="stid" required>
                        <option value="">Choose a field</option>
                        <?php
                        // Fetch fields not already in this table
                        $fieldstmt = $supabase_pdo->prepare("
                           SELECT st.stid, st.str, st.single 
                           FROM select_types st 
                           LEFT JOIN tab_fields tf ON st.stid = tf.stid AND tf.tbid = ?
                           WHERE tf.stid IS NULL
                        ");
                        $fieldstmt->execute([$which]);
                        $result = $fieldstmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Field type mapping
                        $fieldTypes = [
                            0 => 'Single Selection',
                            1 => 'Multiple Selection',
                            2 => 'Text',
                            3 => 'Date',
                            4 => 'Numeric (step 0.1)',
                            5 => 'Numeric (step integer)',
                            6 => 'Time'
                        ];

                        foreach ($result as $row) {
                           $listtype = $fieldTypes[$row['single']] ?? 'Unknown';
                           echo "<option value=\"{$row['stid']}\">{$row['str']} ({$listtype})</option>";
                        }
                        ?>
                     </select>
                  </div>
                  
                  <div class="form-group">
                     <label for="sort_order">Sort Order</label>
                     <input type="number" class="form-control" id="sort_order" name="sort_order" 
                            value="<?php echo $sort_order; ?>" 
                            required>
</div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Add Field</button>
               </div>
            </form>
</div>
   </div>

   <!-- Add this modal for managing selections -->
   <div class="modal fade" id="manageSelectionsModal" tabindex="-1" role="dialog" aria-labelledby="manageSelectionsModalLabel">
      <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
            <div class="modal-header bg-primary text-white">
               <h5 class="modal-title" id="manageSelectionsModalLabel">Manage Selections</h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <div class="modal-body">
               <div class="row">
                  <div class="col-md-6">
                     <h6 class="mb-3">Current Selections <span id="optionsCount" class="badge badge-secondary ml-2">0</span></h6>
                     <div class="alert alert-info mb-2">
                        <small>Click <i class="fas fa-pencil-alt"></i> to edit, <i class="fas fa-trash"></i> to delete, or <i class="fas fa-arrow-up"></i>/<i class="fas fa-arrow-down"></i> to reorder options</small>
                     </div>
                     <ul class="list-group" id="currentSelectionsList">
                        <!-- Dynamically populated selections will go here -->
                        <li class="list-group-item text-center text-muted">Loading options...</li>
                     </ul>
                  </div>
                  <div class="col-md-6">
                     <h6 class="mb-3">Add New Selection</h6>
                     <div class="alert alert-info mb-3">
                        <small>Type to search existing options, select one, then click Add. Repeat for each option, then Save.</small>
                     </div>
                     <div class="input-group mb-3" style="position: relative;">
                        <input type="text" class="form-control" id="newSelectionInput" placeholder="Type to search existing options">
                        <div class="input-group-append">
                           <button class="btn btn-success" id="addSelectionBtn" type="button">
                              <i class="fa fa-plus"></i> Add
                           </button>
                        </div>
                        <!-- Suggestions dropdown will be dynamically inserted here -->
</div>
</div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-primary" id="saveSelectionsBtn">Save Selections</button>
</div>
</div>

   <!-- Edit Field Modal -->
   <div class="modal fade" id="editFieldModal" tabindex="-1" role="dialog" aria-labelledby="editFieldModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFieldModalLabel">Edit Category</h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <div class="modal-body">
                <form id="edit_field_form">
                    <input type="hidden" id="edit_field_id" name="field_id">
                    
                    <div class="form-group">
                        <label for="edit_field_name">Category Name</label>
                        <input type="text" class="form-control" id="edit_field_name" name="field_name" required>
                  </div>
                    
                    <div class="form-group">
                        <label for="edit_field_type">Category Type</label>
                        <select class="form-control" id="edit_field_type" name="field_type" required>
                            <option value="0">Select</option>
                            <option value="1">Multi-Select</option>
                            <option value="2">Text Area</option>
                            <option value="3">Date</option>
                            <option value="4">Numeric</option>
                            <option value="5">Integer</option>
                            <option value="6">Time</option>
                        </select>
                        </div>
                    
                    <div class="form-group">
                        <label for="edit_field_section">Assign to Section</label>
                        <select class="form-control" id="edit_field_section" name="field_section">
                            <option value="">No Section (Unsectioned)</option>
                            <!-- Sections will be populated dynamically -->
                        </select>
                        <small class="form-text text-muted">Choose which section this field should belong to</small>
                    </div>
                    
                    <div class="form-group field-type-options" id="edit_field_options_container">
                        <label for="edit_field_options">Options (one per line)</label>
                        <textarea class="form-control" id="edit_field_options" name="field_options" rows="5"></textarea>
                        <small class="form-text text-muted">Enter each option on a new line</small>
                     </div>
                </form>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
               <button type="submit" class="btn btn-primary" form="edit_field_form">Save Changes</button>
</div>
</div>


    <!-- CSS for notifications -->
    <style>
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .inline-edit-container,
        .inline-type-edit-container {
            margin-top: 5px;
            margin-bottom: 5px;
        }
        
        .field-name-text,
        .field-type-badge {
            cursor: pointer;
        }
        
        .field-name-text:hover {
            text-decoration: underline;
            color: #007bff;
        }
        
        .field-type-badge:hover .badge {
            opacity: 0.8;
        }
        
        /* Styles for option management */
        #currentSelectionsList {
            max-height: 350px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        
        .option-text {
            flex: 1;
        }
        
        .edit-option-input {
            width: 100%;
        }
        
        .edit-option-btn, .delete-option-btn, .move-up-btn, .move-down-btn {
            margin-left: 5px;
        }
        
        .delete-option-btn:hover {
            background-color: #dc3545;
            color: white;
        }
        
        .edit-option-btn:hover {
            background-color: #17a2b8;
            color: white;
        }
        
        .move-up-btn:hover, .move-down-btn:hover {
            background-color: #6c757d;
            color: white;
        }
        
        .save-option-edit:hover {
            background-color: #28a745;
        }
        
        /* Section dropdown styling */
        .section-dropdown {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .section-dropdown .dropdown-header {
            font-weight: 600;
            color: #495057;
            padding: 8px 16px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .section-dropdown .dropdown-item {
            padding: 8px 16px;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .section-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        .section-dropdown .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .section-loading-placeholder {
            color: #6c757d;
            font-style: italic;
        }
        
        .section-option {
            cursor: pointer;
        }
        
        .section-option:hover {
            background-color: #e9ecef;
        }
      
      /* Arrow button styling */
      .move-left-btn, .move-right-btn {
         padding: 2px 6px;
         font-size: 0.75rem;
         border-radius: 3px;
         transition: all 0.2s ease;
      }
      
      .move-left-btn:hover, .move-right-btn:hover {
         background-color: #007bff;
         color: white;
         border-color: #007bff;
      }
      
      .move-left-btn:disabled, .move-right-btn:disabled {
         opacity: 0.5;
         cursor: not-allowed;
      }
      
      .btn-group-sm .btn {
         padding: 0.25rem 0.4rem;
         font-size: 0.75rem;
         line-height: 1.2;
        }
    </style>
    
    <!-- JavaScript for handling field edits -->
    <script>
    $(document).ready(function() {
        // Note: Form submission is now handled in the main script block below
    });
    </script>
    
    <?php include 'incl/adminjs.php' ?>
    
    <!-- MOVE THE EXISTING JAVASCRIPT BLOCK HERE AFTER JQUERY IS LOADED -->
    
    <!-- Original JavaScript code moved here after jQuery is loaded -->
    <script type="text/javascript">
    $(document).ready(function() {
         
         // Debug: Check if manage options buttons exist
         const manageButtons = $('.manage-options-btn');
         
         // Debug: Check if modal exists
         const modalElement = $('#manageSelectionsModal');

         // Handle the manage options button click
         $(document).on('click', '.manage-options-btn', function(e) {
             
             const fieldId = $(this).data('field-id');
             const fieldType = $(this).data('field-type');
             const fieldName = $(this).closest('.card-body').find('.field-name-text').text().trim();
             
             // Set data on modal
             $('#manageSelectionsModal')
                 .data('field-id', fieldId)
                 .data('field-name', fieldName)
                 .data('field-type', fieldType);
             
             // Set modal title
             $('#manageSelectionsModalLabel').text(`Manage Options for ${fieldName}`);
             
             // Clear existing selections
             $('#currentSelectionsList').empty();
            
            // Show loading state
             $('#currentSelectionsList').html('<li class="list-group-item text-center"><i class="fa fa-spinner fa-spin"></i> Loading options...</li>');
            
             // Load field options via AJAX
             const ajaxUrl = 'ajax/get_field_options.php';
             
            $.ajax({
                 url: ajaxUrl,
                type: 'POST',
                 data: { field_id: fieldId },
                dataType: 'json',
                success: function(response) {
                     
                     // Clear loading indicator
                     $('#currentSelectionsList').empty();
                    
                    if (response.status === 'success') {
                         const options = response.options ? response.options.split('|') : [];
                         
                         if (options.length > 0) {
                             options.forEach(function(option, index) {
                                 if (option.trim() !== '') {
                                     const optionItem = `
                                         <li class="list-group-item d-flex justify-content-between align-items-center" data-index="${index}">
                                             <span class="option-text">${option}</span>
                                             <div class="btn-group btn-group-sm">
                                                 <button type="button" class="btn btn-outline-secondary move-up-btn" title="Move Up">
                                                     <i class="fas fa-arrow-up"></i>
                                                 </button>
                                                 <button type="button" class="btn btn-outline-secondary move-down-btn" title="Move Down">
                                                     <i class="fas fa-arrow-down"></i>
                                                 </button>
                                                 <button type="button" class="btn btn-outline-info edit-option-btn">
                                                     <i class="fas fa-pencil-alt"></i>
                                                 </button>
                                                 <button type="button" class="btn btn-outline-danger delete-option-btn">
                                                     <i class="fas fa-trash"></i>
                                                 </button>
                                             </div>
                                         </li>
                                     `;
                                     $('#currentSelectionsList').append(optionItem);
                                 }
                             });
                    } else {
                             $('#currentSelectionsList').html('<li class="list-group-item text-center text-muted">No options available</li>');
                         }
                     } else {
                         $('#currentSelectionsList').html(`<li class="list-group-item text-center text-danger">Error: ${response.message}</li>`);
                     }
                     
                     // Update options count after loading
                     updateOptionsCount();
                     
                },
                error: function(xhr, status, error) {
                     
                     $('#currentSelectionsList').html(`<li class="list-group-item text-center text-danger">Error loading options: ${error}</li>`);
                }
            });
        });

         // Handle input for suggestions with improved search
         $(document).on('input', '#newSelectionInput', function() {
             console.log('🔍 SEARCH: Input event triggered - Value:', $(this).val());
             
             // Log every keystroke
             const currentValue = $(this).val();
             console.log('🔍 SEARCH: Current input value:', currentValue, 'Length:', currentValue.length);
             
             const $input = $(this);
             const fieldId = $('#manageSelectionsModal').data('field-id');
             const searchTerm = $input.val().trim();
             
             console.log('🔍 SEARCH: Field ID:', fieldId, 'Search term:', searchTerm);
             
             if (!fieldId) {
                 console.error('❌ SEARCH: No field ID found in modal data');
                 return;
             }
             
             if (searchTerm.length < 2) {
                 console.log('🔍 SEARCH: Term too short, hiding dropdown');
                 $('#suggestions-dropdown').remove();
                 return;
             }
             
             // Clear any existing suggestions dropdown
             $('#suggestions-dropdown').remove();
             
             // Create suggestions dropdown
             $input.after('<div id="suggestions-dropdown" class="dropdown-menu w-100" style="position: absolute; z-index: 9999;"></div>');
             const $suggestionsDropdown = $('#suggestions-dropdown');
             
             // Show loading state
             $suggestionsDropdown.html('<div class="dropdown-item"><i class="fa fa-spinner fa-spin"></i> Searching...</div>').addClass('show');
             
             console.log('🔍 SEARCH: Making AJAX request to get_option_suggestions.php');
             
             // Fetch suggestions from server
             $.ajax({
                 url: 'ajax/get_option_suggestions.php',
                 method: 'POST',
                 data: {
                     stid: fieldId,
                     term: searchTerm
                 },
                 dataType: 'json',
                 success: function(response) {
                     console.log('🔍 SEARCH: Response received:', response);
                     $suggestionsDropdown.empty();
                     
                     if (response.status === 'success' && response.suggestions && response.suggestions.length > 0) {
                         console.log('🔍 SEARCH: Found', response.suggestions.length, 'suggestions');
                         response.suggestions.forEach(function(suggestion) {
                             const suggestionItem = `
                                 <a class="dropdown-item suggestion-item" href="#" data-value="${suggestion.value}">
                                     <div class="d-flex justify-content-between align-items-center">
                                         <span>${suggestion.value}</span>
                                         <small class="text-muted">${suggestion.count} uses</small>
                                     </div>
                                 </a>
                             `;
                             $suggestionsDropdown.append(suggestionItem);
                         });
                         
                         $suggestionsDropdown.addClass('show');
                         console.log('🔍 SEARCH: Dropdown shown with', response.suggestions.length, 'items');
                     } else {
                         console.log('🔍 SEARCH: No suggestions found');
                         $suggestionsDropdown.html(`
                             <div class="dropdown-item text-muted">
                                 <i class="fa fa-info-circle"></i> No similar options found
                             </div>
                         `).addClass('show');
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error('❌ SEARCH: AJAX error:', { status, error, responseText: xhr.responseText });
                     
                     // Show a more helpful error message
                     if (xhr.status === 404) {
                         $suggestionsDropdown.html(`
                             <div class="dropdown-item text-warning">
                                 <i class="fa fa-exclamation-triangle"></i> Suggestions service not available
                             </div>
                             <div class="dropdown-item text-muted">
                                 <small>You can still type and add new options manually</small>
                             </div>
                         `).addClass('show');
                     } else {
                         $suggestionsDropdown.html('<div class="dropdown-item text-danger"><i class="fa fa-exclamation-triangle"></i> Error loading suggestions</div>').addClass('show');
                     }
                 }
             });
         });

         // Handle focus on search input to ensure proper initialization
         $(document).on('focus', '#newSelectionInput', function() {
             // Ensure field ID is available
             const fieldId = $('#manageSelectionsModal').data('field-id');
         });

         // Handle keydown for suggestions navigation
         $(document).on('keydown', '#newSelectionInput', function(e) {
             const $suggestionsDropdown = $('#suggestions-dropdown');
             
             if (!$suggestionsDropdown.length || !$suggestionsDropdown.hasClass('show')) {
                 return;
             }
             
             const $items = $suggestionsDropdown.find('.suggestion-item');
             const $activeItem = $suggestionsDropdown.find('.suggestion-item.active');
             
             switch(e.keyCode) {
                 case 38: // Up arrow
                     e.preventDefault();
                     if ($activeItem.length === 0) {
                         $items.last().addClass('active');
                     } else {
                         $activeItem.removeClass('active').prev('.suggestion-item').addClass('active');
                         if ($activeItem.prev('.suggestion-item').length === 0) {
                             $items.last().addClass('active');
                         }
                     }
                     break;
                     
                 case 40: // Down arrow
                     e.preventDefault();
                     if ($activeItem.length === 0) {
                         $items.first().addClass('active');
                     } else {
                         $activeItem.removeClass('active').next('.suggestion-item').addClass('active');
                         if ($activeItem.next('.suggestion-item').length === 0) {
                             $items.first().addClass('active');
                         }
                     }
                     break;
                     
                 case 13: // Enter
                     e.preventDefault();
                     if ($activeItem.length > 0) {
                         const selectedValue = $activeItem.data('value');
                         $('#newSelectionInput').val(selectedValue);
                         $('#addSelectionBtn').click();
                     }
                     break;
                     
                 case 27: // Escape
                     e.preventDefault();
                     $suggestionsDropdown.removeClass('show').empty();
                     break;
             }
         });

         // Handle clicking outside to close suggestions
         $(document).on('click', function(e) {
             if (!$(e.target).closest('#newSelectionInput, #suggestions-dropdown').length) {
                 $('#suggestions-dropdown').remove();
             }
         });

         // Handle suggestion item clicks
         $(document).on('click', '.suggestion-item', function(e) {
             e.preventDefault();
             const selectedValue = $(this).data('value');
             $('#newSelectionInput').val(selectedValue);
             $('#addSelectionBtn').click();
         });

         // Handle adding new options
         $('#addSelectionBtn').on('click', function() {
             const newOption = $('#newSelectionInput').val().trim();
             
             if (newOption === '') {
                 // Highlight input if empty
                 $('#newSelectionInput').addClass('is-invalid');
                 setTimeout(() => $('#newSelectionInput').removeClass('is-invalid'), 2000);
                 return;
             }
             
             // Check if option already exists
             const existingOptions = [];
             $('#currentSelectionsList').find('li').each(function() {
                 const optionText = $(this).find('.option-text').text().trim();
                 if (optionText !== '' && !$(this).hasClass('text-muted') && !$(this).hasClass('text-danger')) {
                     existingOptions.push(optionText.toLowerCase());
                 }
             });
             
             if (existingOptions.includes(newOption.toLowerCase())) {
                 // Option already exists
                 $('#newSelectionInput').addClass('is-invalid');
                 $('#newSelectionInput').next('.invalid-feedback').remove();
                 $('#newSelectionInput').after('<div class="invalid-feedback">This option already exists</div>');
                 setTimeout(() => {
                     $('#newSelectionInput').removeClass('is-invalid');
                     $('#newSelectionInput').next('.invalid-feedback').remove();
                 }, 3000);
                 return;
             }
             
             // Remove "no options" message if present
             $('#currentSelectionsList').find('li.text-muted, li.text-danger').remove();
             
             // Add to list
             const index = $('#currentSelectionsList').children().length;
             const optionItem = `
                 <li class="list-group-item d-flex justify-content-between align-items-center" data-index="${index}">
                     <span class="option-text">${newOption}</span>
                     <div class="btn-group btn-group-sm">
                         <button type="button" class="btn btn-outline-info edit-option-btn">
                             <i class="fas fa-pencil-alt"></i>
                         </button>
                         <button type="button" class="btn btn-outline-danger delete-option-btn">
                             <i class="fas fa-trash"></i>
                         </button>
                     </div>
                 </li>
             `;
             $('#currentSelectionsList').append(optionItem);
             
             // Clear input and hide suggestions
             $('#newSelectionInput').val('').focus();
             $('#suggestions-dropdown').removeClass('show').empty();
             
             // Show success indicator
             const $newItem = $('#currentSelectionsList').find('li').last();
             $newItem.css('background-color', '#d4edda');
             setTimeout(() => {
                 $newItem.css('background-color', '');
             }, 1000);
             
             // Update options count
             updateOptionsCount();
         });

         // Handle editing options
         $(document).on('click', '.edit-option-btn', function() {
             const $item = $(this).closest('li');
             const $optionText = $item.find('.option-text');
             const currentText = $optionText.text();
             
             // Save original text for cancelling
             $optionText.data('original-text', currentText);
             
             // Replace text with input
             $optionText.html(`
                 <div class="input-group input-group-sm">
                     <input type="text" class="form-control edit-option-input" value="${currentText}">
                     <div class="input-group-append">
                         <button class="btn btn-success save-option-edit" type="button">
                             <i class="fas fa-check"></i>
                         </button>
                         <button class="btn btn-secondary cancel-option-edit" type="button">
                             <i class="fas fa-times"></i>
                         </button>
</div>
             `);
             
             // Focus on input and select all text
             const $input = $optionText.find('input');
             $input.focus().select();
         });

         // Handle saving option edits
         $(document).on('click', '.save-option-edit', function() {
             const $item = $(this).closest('li');
             const $optionText = $item.find('.option-text');
             const newText = $optionText.find('input').val().trim();
             
             if (newText === '') {
                 return;
             }
             
             // Check if option already exists
             const existingOptions = [];
             $('#currentSelectionsList').find('li').each(function() {
                 if ($(this) !== $item) {
                     const optionText = $(this).find('.option-text').text().trim();
                     if (optionText !== '' && !$(this).hasClass('text-muted') && !$(this).hasClass('text-danger')) {
                         existingOptions.push(optionText.toLowerCase());
                     }
                 }
             });
             
             if (existingOptions.includes(newText.toLowerCase())) {
                 // Option already exists
                 $optionText.find('input').addClass('is-invalid');
                 return;
             }
             
             // Update text
             $optionText.text(newText);
         });

         // Handle cancelling option edits
         $(document).on('click', '.cancel-option-edit', function() {
             const $item = $(this).closest('li');
             const $optionText = $item.find('.option-text');
             const originalText = $optionText.data('original-text');
             
             $optionText.text(originalText);
         });

         // Handle deleting options
         $(document).on('click', '.delete-option-btn', function() {
             const $item = $(this).closest('li');
             
             // Confirm deletion
             if (confirm('Are you sure you want to delete this option?')) {
                 $item.remove();
                 updateOptionsCount();
             }
         });
         
         // Handle moving options up
         $(document).on('click', '.move-up-btn', function() {
             const $item = $(this).closest('li');
             const $prevItem = $item.prev('li');
             
             if ($prevItem.length > 0 && !$prevItem.hasClass('text-muted') && !$prevItem.hasClass('text-danger')) {
                 $item.insertBefore($prevItem);
                 updateOptionsCount();
                 
                 // Add visual feedback
                 $item.css('background-color', '#d4edda');
                 setTimeout(function() {
                     $item.css('background-color', '');
                 }, 500);
             }
         });
         
         // Handle moving options down
         $(document).on('click', '.move-down-btn', function() {
             const $item = $(this).closest('li');
             const $nextItem = $item.next('li');
             
             if ($nextItem.length > 0 && !$nextItem.hasClass('text-muted') && !$nextItem.hasClass('text-danger')) {
                 $item.insertAfter($nextItem);
                 updateOptionsCount();
                 
                 // Add visual feedback
                 $item.css('background-color', '#d4edda');
                 setTimeout(function() {
                     $item.css('background-color', '');
                 }, 500);
             }
         });

         // Handle Enter key in edit input
         $(document).on('keypress', '.edit-option-input', function(e) {
             if (e.which === 13) {
                 $(this).closest('.input-group').find('.save-option-edit').click();
             }
         });

         // Update options count function
         function updateOptionsCount() {
             const count = $('#currentSelectionsList').find('li:not(.text-muted):not(.text-danger)').length;
             $('#optionsCount').text(count);
             
             if (count === 0) {
                 $('#optionsCount').removeClass().addClass('badge badge-secondary').text('0');
             } else if (count > 10) {
                 $('#optionsCount').addClass('badge-success').text(count);
             } else {
                 $('#optionsCount').addClass('badge-secondary').text(count);
             }
         }

         // Handle Enter key in new selection input
         $('#newSelectionInput').on('keypress', function(e) {
             if (e.which === 13) {
                 $('#addSelectionBtn').click();
             }
         });

         // Clear input when modal is hidden
         $('#manageSelectionsModal').on('hidden.bs.modal', function() {
             $('#newSelectionInput').val('');
             $('#suggestions-dropdown').remove();
         });

         // Handle saving all options
         $('#saveSelectionsBtn').on('click', function() {
             // Get all option values
             const options = [];
             $('#currentSelectionsList').find('li').each(function() {
                 const optionText = $(this).find('.option-text').text().trim();
                 if (optionText !== '' && !$(this).hasClass('text-muted') && !$(this).hasClass('text-danger')) {
                     options.push(optionText);
                 }
             });
             
             if (options.length === 0) {
                 showNotification('warning', 'No options to save. Please add at least one option.');
                 return;
             }
             
             const fieldId = $('#manageSelectionsModal').data('field-id');
             const fieldType = $('#manageSelectionsModal').data('field-type');
             
             // Show loading state
             const $btn = $(this);
             const originalBtnText = $btn.html();
             $btn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
             
             // Send options to server
             $.ajax({
                 url: 'ajax/update_field_options.php',
                 type: 'POST',
                 data: {
                     field_id: fieldId,
                     field_type: fieldType,
                     options: options.join('|')
                 },
                 dataType: 'json',
                 success: function(response) {
                     $btn.html(originalBtnText).prop('disabled', false);
                     
                     if (response.status === 'success') {
                         // Show success message
                         showNotification('success', 'Options updated successfully');
                         
                         // Close modal
                         $('#manageSelectionsModal').modal('hide');
                         
                         // Reload page after success
                         setTimeout(function() {
                             location.reload();
                         }, 1000);
                     } else {
                         // Check if redirect is needed for database update
                         if (response.redirect) {
                             showNotification('warning', 'Database update needed. Redirecting...');
                             setTimeout(() => {
                                 window.location.href = 'db_update.php';
                             }, 2000);
                         } else {
                             // Show error message
                             showNotification('danger', 'Error updating options: ' + response.message);
                         }
                     }
                 },
                 error: function(xhr, status, error) {
                     $btn.html(originalBtnText).prop('disabled', false);
                     
                     let errorMsg = 'Error saving options. Please try again.';
                     try {
                         const response = JSON.parse(xhr.responseText);
                         if (response.message) {
                             errorMsg = response.message;
                         }
                     } catch (e) {
                         // Parsing failed, use default message
                     }
                     
                     showNotification('danger', errorMsg);
                 }
             });
         });
         
         // Handle move left button clicks
         $(document).on('click', '.move-left-btn', function(e) {
             e.preventDefault();
             e.stopPropagation();
             
             const fieldId = $(this).data('field-id');
             const tableId = $(this).data('table-id');
             const $fieldCard = $(this).closest('.field-wrapper');
             const $section = $fieldCard.closest('.section-fields, .unsectioned-fields');
             const sectionId = $section.data('section-id');
             
             // Find the previous field in the same section
             const $prevField = $fieldCard.prev('.field-wrapper');
             
             if ($prevField.length === 0) {
                 showNotification('info', 'Field is already at the beginning of the section');
                 return;
             }
             
             // Swap positions
             $fieldCard.insertBefore($prevField);
             
             // Update the sort order in the database
             updateFieldOrder(sectionId, tableId, $section);
             
             // Show success feedback
             $fieldCard.addClass('sortable-highlight');
             setTimeout(() => {
                 $fieldCard.removeClass('sortable-highlight');
             }, 1000);
         });
         
         // Handle move right button clicks
         $(document).on('click', '.move-right-btn', function(e) {
             e.preventDefault();
             e.stopPropagation();
             
             const fieldId = $(this).data('field-id');
             const tableId = $(this).data('table-id');
             const $fieldCard = $(this).closest('.field-wrapper');
             const $section = $fieldCard.closest('.section-fields, .unsectioned-fields');
             const sectionId = $section.data('section-id');
             
             // Find the next field in the same section
             const $nextField = $fieldCard.next('.field-wrapper');
             
             if ($nextField.length === 0) {
                 showNotification('info', 'Field is already at the end of the section');
                 return;
             }
             
             // Swap positions
             $fieldCard.insertAfter($nextField);
             
             // Update the sort order in the database
             updateFieldOrder(sectionId, tableId, $section);
             
             // Show success feedback
             $fieldCard.addClass('sortable-highlight');
             setTimeout(() => {
                 $fieldCard.removeClass('sortable-highlight');
             }, 1000);
         });
         
         // Function to update field order after arrow button moves
         function updateFieldOrder(sectionId, tableId, $section) {
             // Get all field IDs in their current order
             const fieldIds = [];
             $section.find('.field-wrapper').each(function() {
                 fieldIds.push($(this).data('field-id'));
             });
             
             // Send AJAX request to update order
             $.ajax({
                 url: 'ajax/update_field_order.php',
                 type: 'POST',
                 data: {
                     section_id: sectionId,
                     table_id: tableId,
                     field_ids: JSON.stringify(fieldIds)
                 },
                 dataType: 'json',
                 success: function(response) {
                     if (response.status === 'success') {
                         showNotification('success', 'Field order updated successfully', true);
                         // Update arrow button states after successful reorder
                         updateArrowButtonStates($section);
                     } else {
                         showNotification('danger', 'Error updating field order: ' + response.message, true);
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error('AJAX Error Details:', {
                         status: status,
                         error: error,
                         responseText: xhr.responseText,
                         statusCode: xhr.status
                     });
                     
                     let errorMsg = 'Network error when updating field order';
                     try {
                         const response = JSON.parse(xhr.responseText);
                         if (response.message) {
                             errorMsg = response.message;
                         }
                     } catch (e) {
                         errorMsg = `HTTP ${xhr.status}: ${error}`;
                     }
                     
                     showNotification('danger', errorMsg, true);
                 }
             });
         }
         
         // Function to update arrow button states (enable/disable based on position)
         function updateArrowButtonStates($section) {
             const $fields = $section.find('.field-wrapper');
             
             $fields.each(function(index) {
                 const $field = $(this);
                 const $leftBtn = $field.find('.move-left-btn');
                 const $rightBtn = $field.find('.move-right-btn');
                 
                 // Disable left button if this is the first field
                 if (index === 0) {
                     $leftBtn.prop('disabled', true);
                 } else {
                     $leftBtn.prop('disabled', false);
                 }
                 
                 // Disable right button if this is the last field
                 if (index === $fields.length - 1) {
                     $rightBtn.prop('disabled', true);
                 } else {
                     $rightBtn.prop('disabled', false);
                 }
             });
         }
         
         // Initialize arrow button states when page loads
         function initializeArrowButtonStates() {
             $('.section-fields, .unsectioned-fields').each(function() {
                 updateArrowButtonStates($(this));
             });
         }
         
         // Call initialization when document is ready
         $(document).ready(function() {
             initializeArrowButtonStates();
         });
    });
    </script>
    
    <!-- Essential JavaScript functions that depend on jQuery -->
    <script>
    // Immediate test - should run as soon as script loads
    console.log("🔧 SCRIPT LOADED - Testing basic JavaScript");
    
    // Test that jQuery is working
    $(document).ready(function() {
        console.log("🔧 jQuery is loaded and working!");
        console.log("🔧 Document ready fired!");
        
        // Enhanced click handler that populates the modal
        $(document).on('click', '.edit-field-btn', function(e) {
            console.log("🔧 BASIC CLICK TEST - Edit button clicked!");
            
            const fieldId = $(this).data('field-id');
            const tableId = <?php echo $which; ?>;
            
            console.log("🔧 Field ID:", fieldId);
            console.log("🔧 Table ID:", tableId);
            
            if (!fieldId) {
                console.error("🔧 No field ID found!");
                return;
            }
            
            // Clear any previous alerts and loading indicators
            $('#editFieldModal .alert').remove();
            $('#editFieldModal .field-loading').remove();
            
            // Show loading state in the modal
            $('#editFieldModal .modal-body').append('<div class="text-center field-loading"><i class="fa fa-spinner fa-spin"></i> Loading field data...</div>');
            
            console.log("🔧 AJAX request starting...");
            
            // Fetch field data via AJAX
            $.ajax({
               url: 'ajax/edit_field.php',
               type: 'GET',
               headers: {
                  'X-Requested-With': 'XMLHttpRequest'
               },
               data: {
                  field_id: fieldId,
                  table_id: tableId
               },
               dataType: 'json',
               success: function(response) {
                  console.log("🔧 AJAX success - Raw response:", response);
                  
                  // Remove loading indicator
                  $('#editFieldModal .field-loading').remove();
                  
                  if (response.status === 'success') {
                     console.log("🔧 Response status is success, populating form...");
                     
                     // Clear form first
                     $('#edit_field_form')[0].reset();
                     
                     // Populate form fields
                     $('#edit_field_id').val(fieldId);
                     $('#edit_field_name').val(response.field_name);
                     $('#edit_field_type').val(response.field_type);
                     
                     console.log("🔧 Form fields populated:", {
                        field_id: $('#edit_field_id').val(),
                        field_name: $('#edit_field_name').val(),
                        field_type: $('#edit_field_type').val()
                     });
                     
                     // Handle options for select fields
                     if (response.field_type == 0 || response.field_type == 1) {
                        console.log("🔧 Field is select type, showing options container");
                        $('#edit_field_options_container').show();
                        $('#edit_field_options').val(response.options ? response.options.join('\n') : '');
                        console.log("🔧 Options populated:", $('#edit_field_options').val());
                     } else {
                        console.log("🔧 Field is not select type, hiding options container");
                        $('#edit_field_options_container').hide();
                     }
                     
                     // Populate sections dropdown
                     console.log("🔧 Populating sections dropdown...");
                     populateSectionDropdown(fieldId, tableId, response.current_section_id);
                     
                     console.log("🔧 Form population complete!");
                  } else {
                     console.error('Error in edit field response:', response);
                     $('#editFieldModal .modal-body').prepend(
                        `<div class="alert alert-danger">Error loading field data: ${response.message}</div>`
                     );
                  }
               },
               error: function(xhr, status, error) {
                  console.log("🔧 AJAX error occurred:", {xhr, status, error});
                  
                  // Remove loading indicator
                  $('#editFieldModal .field-loading').remove();
                  
                  // Show error in modal
                  $('#editFieldModal .modal-body').prepend(
                     `<div class="alert alert-danger">Error: Could not load field data. Please try again.<br>Status: ${status}<br>Error: ${error}</div>`
                  );
               }
            });
        });
        
        // Test if edit buttons exist
        var editButtons = $('.edit-field-btn');
        console.log("🔧 Found", editButtons.length, "edit buttons on page");
        
        // Handle edit field form submission
        $('#edit_field_form').on('submit', function(e) {
            e.preventDefault();
            console.log("🔧 Form submission prevented, handling with AJAX");
            
            // Get form data
            const fieldId = $('#edit_field_id').val();
            const fieldName = $('#edit_field_name').val();
            const fieldType = $('#edit_field_type').val();
            const fieldOptions = $('#edit_field_options').val();
            const fieldSection = $('#edit_field_section').val();
            
            console.log("🔧 Form data:", {fieldId, fieldName, fieldType, fieldOptions, fieldSection});
            
            // Basic validation
            if (!fieldId || !fieldName || fieldType === undefined) {
                showNotification('danger', 'Please fill out all required fields');
                return;
            }
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
            
            console.log("🔧 Sending AJAX request to update field...");
            
            // AJAX call to update field
            $.ajax({
                url: 'ajax/edit_field.php',
                type: 'POST',
                headers: {
                   'X-Requested-With': 'XMLHttpRequest'
                },
                data: {
                    field_id: fieldId,
                    field_name: fieldName,
                    field_type: fieldType,
                    field_options: fieldOptions,
                    field_section: fieldSection,
                    table_id: <?php echo $which; ?>
                },
                dataType: 'json',
                success: function(response) {
                    console.log('🔧 Edit field response:', response);
                    submitBtn.html(originalBtnText).prop('disabled', false);
                    
                    if (response.status === 'success') {
                        $('#editFieldModal').modal('hide');
                        showNotification('success', 'Field updated successfully!');
                        
                        // Reload the current page with the correct 'which' parameter
                        setTimeout(function() {
                            console.log("🔧 Reloading page...");
                            // Get the current 'which' parameter
                            const urlParams = new URLSearchParams(window.location.search);
                            const whichParam = urlParams.get('which');
                            
                            // Construct the correct URL
                            const redirectUrl = `sheetdetail.php?which=${whichParam}`;
                            
                            // Navigate to the URL
                            window.location.href = redirectUrl;
                        }, 1000);
                    } else {
                        showNotification('danger', 'Error updating field: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.html(originalBtnText).prop('disabled', false);
                    
                    console.error('🔧 AJAX Error Details:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    
                    let errorMsg = 'Error updating field. Please try again.';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        // Parsing failed, use default message
                    }
                    
                    showNotification('danger', errorMsg);
                }
            });
        });
    });
    
    // Function to populate section dropdown in edit field modal
    function populateSectionDropdown(fieldId, tableId, currentSectionId) {
        console.log("🔧 populateSectionDropdown called with:", {fieldId, tableId, currentSectionId}); // Debug logging
        
        $.ajax({
            url: 'ajax/get_sections.php',
            method: 'GET',
            headers: {
               'X-Requested-With': 'XMLHttpRequest'
            },
            data: {
                table_id: tableId
            },
            dataType: 'json',
            success: function(response) {
                console.log("🔧 Sections AJAX response:", response); // Debug logging
                
                const $dropdown = $('#edit_field_section');
                $dropdown.empty();
                $dropdown.append('<option value="">No Section (Unsectioned)</option>');
                
                if (response.status === 'success' && response.sections && response.sections.length > 0) {
                    console.log("🔧 Populating sections dropdown with", response.sections.length, "sections"); // Debug logging
                    response.sections.forEach(function(section) {
                        const selected = (currentSectionId !== null && currentSectionId !== '' && section.section_id == currentSectionId) ? 'selected' : '';
                        $dropdown.append(`<option value="${section.section_id}" ${selected}>${section.section_name}</option>`);
                    });
                    console.log("🔧 Sections dropdown populated successfully"); // Debug logging
                } else {
                    console.log("🔧 No sections found or error in response"); // Debug logging
                    // If no sections found, show a message
                    $dropdown.append('<option value="" disabled>No sections available</option>');
                }
            },
            error: function(xhr, status, error) {
                console.log("🔧 Sections AJAX error:", {xhr, status, error}); // Debug logging
                
                const $dropdown = $('#edit_field_section');
                $dropdown.empty();
                $dropdown.append('<option value="">No Section (Unsectioned)</option>');
                $dropdown.append('<option value="" disabled>Error loading sections</option>');
            }
        });
    }
    
    // Unified notification function
    function showNotification(type, message, autoClose = true) {
        // Remove any existing notifications
        $('.notification-toast').remove();
        
        // Create notification element
        const notification = $(`
            <div class="notification-toast alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `);
        
        // Append to body
        $('body').append(notification);
        
        // Auto close after 3 seconds if requested
        if (autoClose) {
            setTimeout(function() {
                notification.alert('close');
            }, 3000);
        }
        
        return notification;
    }
    
    // Handle field deletion - show confirmation modal
    $(document).on('click', '.delete-field-btn', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $fieldCard = $button.closest('.field-card');
        const fieldName = $fieldCard.find('.field-name').text().trim() || 'this field';
        const stid = $button.data('stid');
        const tbid = $button.data('tbid');
        
        // Populate modal with field details
        $('#deleteFieldMessage').text(`Are you sure you want to remove "${fieldName}" from this sheet?`);
        $('#deleteFieldStid').val(stid);
        $('#deleteFieldTbid').val(tbid);
        
        // Show confirmation modal
        $('#deleteFieldModal').modal('show');
    });
    
    // Handle confirmation of field deletion
    $('#confirmDeleteFieldBtn').on('click', function() {
        const stid = $('#deleteFieldStid').val();
        const tbid = $('#deleteFieldTbid').val();
        const $button = $(this);
        const $fieldCard = $('.delete-field-btn[data-stid="' + stid + '"]').closest('.field-card');
        
        // Add visual feedback
        $button.html('<i class="fa fa-spinner fa-spin"></i> Processing...');
        $button.prop('disabled', true);
        
        // Make AJAX call to remove field from database
        $.ajax({
            url: 'delete_table_field.php',
            type: 'POST',
            data: {
                stid: stid,
                tbid: tbid
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Close modal
                    $('#deleteFieldModal').modal('hide');
                    
                    // Fade out the entire field card
                    $fieldCard.fadeOut(500, function() {
                        // Show success notification
                        showNotification('success', 'Field removed from sheet successfully!', true);
                    });
                } else {
                    // Show error notification
                    showNotification('danger', 'Error removing field: ' + response.message, true);
                    
                    // Reset button state
                    $button.html('<i class="fas fa-trash"></i> Remove Field');
                    $button.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                // Show error notification
                showNotification('danger', 'Network error removing field. Please try again.', true);
                
                // Reset button state
                $button.html('<i class="fas fa-trash"></i> Remove Field');
                $button.prop('disabled', false);
            }
        });
    });

    </script>
    
    <!-- Additional scripts that depend on jQuery -->
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js" integrity="sha256-eTyxS0rkjpLEo16uXTS0uVCS4815lc40K2iVpWDvdSY=" crossorigin="anonymous"></script>
</body>
</html>