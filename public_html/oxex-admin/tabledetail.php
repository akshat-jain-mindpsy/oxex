<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Ensure proper access control
if(!(login_check($mysqli) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    header("Location: index.php");
    exit();
}

// Page setup
$pagetitle = "Sheet Details";
$subtitle = "Sheet Fields";
$listurl = "sheets.php";
$listname = "Sheets";

// Page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? (int)$_GET['which'] : 0;

// Handle field deletion
if ($del == "delfield" && ($admintype == 'AT' || $admintype == 'DV')) {
   $stid = isset($_GET['stid']) ? (int)$_GET['stid'] : '';
   $stmt = $mysqli->prepare("DELETE FROM tab_fields WHERE stid = ? AND tbid = ? LIMIT 1");
   $stmt->bind_param("ii", $stid, $which); 
   $stmt->execute();
   $stmt->close();
   
   // Add a success message or redirect
   header("Location: tabledetail.php?which=$which&msg=field_deleted");
   exit();
}

// Handle table update
if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $tab_name = isset($_POST['tab_name']) ? $_POST['tab_name'] : '';
  $tab_notes = isset($_POST['tab_notes']) ? $_POST['tab_notes'] : '';
  $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
  $isvis = isset($_POST['isvis']) ? (int)$_POST['isvis'] : 1;
  
  // Update record
  $stmt = $mysqli->prepare("UPDATE tabs_tbl SET tab_name = ?, tab_notes = ?, sort_order = ?, isvis = ? WHERE tbid = ? "); 
  $stmt->bind_param("ssiii", $tab_name, $tab_notes, $sort_order, $isvis, $which);
  $stmt->execute();
  $stmt->close();
  
  // Redirect to prevent form resubmission
  header("Location: tabledetail.php?which=$which&msg=updated");
  exit();
}

// Handle new field addition
if ($newadmin == "newfield" && ($admintype == 'AT' || $admintype == 'DV')) {
   $stid = isset($_POST['stid']) ? (int)$_POST['stid'] : 0;
   $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
   
   // Check if field already exists
   $vids = $mysqli->prepare("SELECT COUNT(*) FROM tab_fields WHERE tbid = ? AND stid = ?");
   $vids->bind_param("ii", $which, $stid);
   $vids->execute();
   $vids->bind_result($numlinks);
   $vids->fetch();
   $vids->close();
   
   if ($numlinks == 0) {
      $insert_stmt = $mysqli->prepare("INSERT INTO tab_fields (tbid, stid, sort_order) VALUES (?, ?, ?)");
      $insert_stmt->bind_param("iii", $which, $stid, $sort_order);
      $insert_stmt->execute();
      $newid = $insert_stmt->insert_id;
      $insert_stmt->close();
      
      // Check if this is an AJAX request
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
          // Get field details for the response
          $field_stmt = $mysqli->prepare("SELECT str, single FROM select_types WHERE stid = ?");
          $field_stmt->bind_param("i", $stid);
          $field_stmt->execute();
          $field_stmt->bind_result($str, $single);
          $field_stmt->fetch();
          $field_stmt->close();
          
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
          header("Location: tabledetail.php?which=$which&msg=field_added");
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
          header("Location: tabledetail.php?which=$which&msg=field_exists");
          exit();
      }
   }
}

// Fetch table details
$stmt = $mysqli->prepare("SELECT tab_name, tab_notes, sort_order, isvis FROM tabs_tbl WHERE tbid = ?");
$stmt->bind_param("i", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($tab_name, $tab_notes, $sort_order, $isvis);
$stmt->fetch();
$stmt->close();

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
      
      /* Drag and drop styling */
      .drag-handle {
         cursor: move;
         color: #adb5bd;
         opacity: 0.6;
         transition: opacity 0.2s;
         margin-right: 10px;
      }
      
      .drag-handle:hover {
         opacity: 1;
         color: #495057;
      }
      
      .sortable-drag {
         opacity: 0.8 !important;
         transform: scale(1.05);
         background-color: #fff;
         box-shadow: 0 5px 15px rgba(0,0,0,0.15);
         z-index: 1100;
      }
      
      .sortable-fallback {
         box-shadow: 0 5px 15px rgba(0,0,0,0.15);
         border-radius: 4px;
         overflow: hidden;
         pointer-events: none;
      }
      
      .sortable-ghost {
         opacity: 0.3;
         background-color: #f8f9fa;
         border: 2px dashed #007bff !important;
      }
      
      .dragging {
         z-index: 1000;
      }
      
      .field-wrapper {
         transition: all 0.2s;
         cursor: move; /* Show the move cursor on the entire wrapper for better UX */
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
      
      .edit-option-btn, .delete-option-btn {
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
                ?>
               </div>
            </div>

            <!-- Amend Card -->
            <div class="row">
               <div class="col-12">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
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
                        </div>
                        
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?php echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
               </div>
            </div>

            <!-- Table Field Management Section -->
            <div class="row">
               <div class="col-12">
                           <div class="form-group mt-2 w-100" style="margin-bottom: 1.5rem; padding: 1.5rem; background: #AEB7BD; border-radius: 8px;">
                              <h4 class="mb-3">Table Field Management</h4>
                              <p class="text-dark">Manage the fields that appear in this table. You can add new fields, change their order, or remove existing fields.</p>
                              <div class="d-flex">
                                <button class="btn btn-success mr-2" data-toggle="modal" data-target="#addFieldModal">
                                   <i class="fa fa-plus"></i> Add New Field
                                </button>
                                <button class="btn btn-primary" data-toggle="modal" data-target="#manageSectionsModal">
                                   <i class="fa fa-layer-group"></i> Manage Table Sections
                                </button>
                                <button class="btn btn-warning ml-2" id="testSchemaBtn">
                                   <i class="fa fa-database"></i> Test Schema
                                </button>
                              </div>
                        </div>
                     </div>
                  </div>
                  
            <!-- Fields preview -->
            <div class="card mb-0 w-100 logbook-preview-card" style="box-shadow:none;border-radius:0;">
                <div class="card-header bg-info text-white" style="border-radius:0;">
                    <div class="card-title mb-0">Logbook Preview - How fields will appear to users</div>
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
                
                $sections_stmt = $mysqli->prepare($sections_query);
                if ($sections_stmt === false) {
                    // Handle query preparation error
                    echo '<div class="alert alert-danger">Error preparing sections query: ' . $mysqli->error . '</div>';
                } else {
                    $sections_stmt->bind_param("i", $table_id);
                    $result = $sections_stmt->execute();
                    
                    if ($result === false) {
                        // Handle execution error
                        echo '<div class="alert alert-danger">Error executing sections query: ' . $sections_stmt->error . '</div>';
                        $sections_result = null;
                    } else {
                        $sections_result = $sections_stmt->get_result();
                    }
                    
                    $sections = [];
                    if ($sections_result) {
                        while ($section = $sections_result->fetch_assoc()) {
                            $sections[$section['section_id']] = $section;
                        }
                    }
                    $sections_stmt->close();
                }
                
                // Get fields for this table with section information using section_table_link
                $fields_query = "
                  SELECT 
                    tf.tfid,
                    st.stid,
                    st.str AS field_name,
                    COALESCE(stl.section_id, st.section_id) AS section_id,
                    COALESCE(stl.display_order, tf.sort_order) AS field_order,
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
                    COALESCE(stl.display_order, tf.sort_order) ASC,
                    st.str ASC
                ";
                
                $fields_stmt = $mysqli->prepare($fields_query);
                $fields_stmt->bind_param("ii", $table_id, $table_id);
                $fields_stmt->execute();
                $fields_result = $fields_stmt->get_result();
                
                // Group fields by section
                $sectioned_fields = [];
                $unsectioned_fields = [];
                
                while ($field = $fields_result->fetch_assoc()) {
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
                $fields_stmt->close();
                
                // Display sections and their fields
                foreach ($sections as $section_id => $section) {
                  // Start the section container regardless of whether it has fields
                  echo '<div class="section-container m-0 p-0" style="border-bottom:1px solid #eaeaea;">';
                  echo '<div class="section-header d-flex justify-content-between align-items-center m-0 p-2" style="background:#f8f9fa;border-left:4px solid #09c;">';
                  echo '<div>';
                  echo '<h5 class="mb-1">' . htmlspecialchars($section['section_name']) . '</h5>';
                  if (!empty($section['section_description'])) {
                    echo '<p class="text-muted mb-0"><small>' . htmlspecialchars($section['section_description']) . '</small></p>';
                  }
                  echo '</div>';
                  echo '<div>';
                  echo '<button type="button" class="btn btn-sm btn-outline-secondary edit-section-btn" data-section-id="' . $section_id . '" data-toggle="modal" data-target="#edit_section_modal">';
                  echo '<i class="fas fa-pencil-alt"></i> Edit Section';
                  echo '</button>';
                  echo '</div>';
                  echo '</div>';
                  
                  echo '<div class="section-fields sortable-section m-0 p-0" data-section-id="' . $section_id . '" data-table-id="' . $table_id . '">';
                  echo '<div class="row sortable-fields m-0 p-0">';
                  
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
                    echo '<p class="text-muted mb-0">No fields assigned to this section. Add fields using the "Add New Field" button above.</p>';
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
                  echo '<h5 class="mb-1">Unsectioned Fields</h5>';
                  echo '<p class="text-muted mb-0"><small>Fields not assigned to any section</small></p>';
                  echo '</div>';
                  echo '</div>';
                  
                  echo '<div class="unsectioned-fields sortable-section m-0 p-0" data-section-id="null" data-table-id="' . $table_id . '">';
                  echo '<div class="row sortable-fields m-0 p-0">';
                  
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
                  echo '<h5><i class="fas fa-info-circle"></i> No Fields Found</h5>';
                  echo '<p>This table has no fields assigned to it yet. Use the "Add New Field" button above to start adding fields.</p>';
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
                  
                  // Add drag handle
                  echo '<div class="d-flex align-items-center">';
                  echo '<span class="drag-handle mr-2"><i class="fas fa-grip-vertical"></i></span>';
                  
                  // Field name - clickable for inline editing
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
               </div>
            </div>
         </div>
      </section>
   </div>
   <!-- JavaScript will be loaded after jQuery -->
   <script type="text/javascript">
    $(document).ready(function() {
 
         
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
         
         // Debug: Test modal event binding
         setTimeout(function() {
             console.log("=== TESTING MODAL EVENT BINDING ===");
             console.log("Modal element:", $('#manageSelectionsModal'));
             console.log("Modal event handlers:", $('#manageSelectionsModal').data('events'));
             
             // Try to manually trigger the modal event
             try {
                 $('#manageSelectionsModal').trigger('shown.bs.modal');
                 console.log("Modal event manually triggered");
             } catch (error) {
                 console.error("Error triggering modal event:", error);
             }
         }, 3000);
         
         // Test modal functionality
         console.log("Testing modal elements:");
         console.log("manageSectionsModal element:", $('#manageSectionsModal').length);
         
         // Debug: Check for JavaScript errors
         console.log("=== CHECKING FOR JAVASCRIPT ERRORS ===");
         window.addEventListener('error', function(e) {
             console.error("JavaScript error detected:", e.error);
             console.error("Error details:", {
                 message: e.message,
                 filename: e.filename,
                 lineno: e.lineno,
                 colno: e.colno
             });
         });
         console.log("manageSectionsModal HTML:", $('#manageSectionsModal').html());
         
         // Check if modal exists in body
         console.log("Modal in body:", $('body #manageSectionsModal').length);
         console.log("All modals in page:", $('.modal').length);
         console.log("Modal classes:", $('#manageSectionsModal').attr('class'));
         
         // Check for any Bootstrap modal attributes
         console.log("Modal attributes:", {
            'data-backdrop': $('#manageSectionsModal').attr('data-backdrop'),
            'data-keyboard': $('#manageSectionsModal').attr('data-keyboard'),
            'data-focus': $('#manageSectionsModal').attr('data-focus'),
            'tabindex': $('#manageSectionsModal').attr('tabindex')
         });
         
         // Test button elements
         console.log("Manage Table Sections button:", $('[data-target="#manageSectionsModal"]').length);
         console.log("Button HTML:", $('[data-target="#manageSectionsModal"]').html());
         
         // Add click handler for debugging
         $('[data-target="#manageSectionsModal"]').on('click', function(e) {
            console.log("Manage Table Sections button clicked!");
            console.log("Event:", e);
            console.log("Button data:", $(this).data());
            console.log("Modal element exists:", $('#manageSectionsModal').length);
            console.log("Modal is visible:", $('#manageSectionsModal').is(':visible'));
            console.log("Modal has 'show' class:", $('#manageSectionsModal').hasClass('show'));
            
            // Try manual modal show
            console.log("Attempting to show modal manually...");
            try {
               $('#manageSectionsModal').modal('show');
               console.log("Modal show command executed");
            } catch (error) {
               console.error("Error showing modal:", error);
            }
         });
         
         // Test Bootstrap modal functionality
         console.log("Testing Bootstrap modal methods:");
         console.log("$.fn.modal available:", typeof $.fn.modal);
         console.log("$.fn.modal function:", $.fn.modal);
         
         // Test if we can manually trigger the modal
         setTimeout(function() {
            console.log("Testing manual modal trigger in 2 seconds...");
            console.log("Modal element:", $('#manageSectionsModal'));
            console.log("Modal methods:", $('#manageSectionsModal').modal);
            
            // Try to manually show the modal
            try {
               $('#manageSectionsModal').modal('show');
               console.log("Manual modal show successful");
            } catch (error) {
               console.error("Manual modal show failed:", error);
            }
         }, 2000);
         
         let originalOrder = [];
         let currentOrder = [];
         let isDragging = false;

         // Initialize empty section placeholders
         refreshEmptySections();
         
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
         
         // Add a handler for the edit field button click
         $(document).on('click', '.edit-field-btn', function(e) {
            e.preventDefault();
            
            const fieldId = $(this).data('field-id');
            const tableId = <?php echo $which; ?>;
            
            // Show loading state in the modal
            $('#editFieldModal .modal-body').append('<div class="text-center field-loading"><i class="fa fa-spinner fa-spin"></i> Loading field data...</div>');
            
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
                  // Remove loading indicator
                  $('#editFieldModal .field-loading').remove();
                  
                  if (response.status === 'success') {
                     // Populate the form
                     $('#edit_field_id').val(fieldId);
                     $('#edit_field_name').val(response.field_name);
                     $('#edit_field_type').val(response.field_type);
                     
                     // Handle options for select fields
                     if (response.field_type == 0 || response.field_type == 1) {
                        $('#edit_field_options_container').show();
                        $('#edit_field_options').val(response.options ? response.options.join('\n') : '');
                     } else {
                        $('#edit_field_options_container').hide();
                     }
                     
                     // Populate sections dropdown
                     populateSectionDropdown(fieldId, tableId, response.current_section_id);
                  } else {
                     // Show error in modal
                     $('#editFieldModal .modal-body').prepend(
                        `<div class="alert alert-danger">Error loading field data: ${response.message}</div>`
                     );
                  }
               },
               error: function(xhr, status, error) {
                  // Remove loading indicator
                  $('#editFieldModal .field-loading').remove();
                  
                  // Show error in modal
                  $('#editFieldModal .modal-body').prepend(
                     `<div class="alert alert-danger">Error: Could not load field data. Please try again.</div>`
                  );
               }
            });
         });

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
             const sectionId = $(this).data('section-id');
             const sectionName = $(this).closest('.section-header').find('h5').text().trim();
             const sectionDescription = $(this).closest('.section-header').find('p.text-muted small').text().trim();
             
             // Populate the modal fields
             $('#edit_section_id').val(sectionId);
             $('#edit_section_name').val(sectionName);
             $('#edit_section_description').val(sectionDescription);
             $('#edit_table_id').val(<?php echo $table_id; ?>);
             
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
                     // Clear existing selections
                     $('#edit_table_ids').val('');
                     
                     if (response.status === 'success' && response.tables) {
                         // Set the selected tables
                         $('#edit_table_ids').val(response.tables);
                         if (typeof $('#edit_table_ids').selectpicker === 'function') {
                             $('#edit_table_ids').selectpicker('refresh');
                         }
                     }
                 }
             });
             
             // Load fields for this section and populate move fields dropdown
             loadSectionFields(sectionId, <?php echo $table_id; ?>);
         });
         
         // Function to populate section dropdown in edit field modal
         function populateSectionDropdown(fieldId, tableId, currentSectionId) {
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
                     const $dropdown = $('#edit_field_section');
                     $dropdown.empty();
                     $dropdown.append('<option value="">No Section (Unsectioned)</option>');
                     
                     if (response.status === 'success' && response.sections) {
                         response.sections.forEach(function(section) {
                             const selected = (currentSectionId && section.section_id == currentSectionId) ? 'selected' : '';
                             $dropdown.append(`<option value="${section.section_id}" ${selected}>${section.section_name}</option>`);
                         });
                     }
                 },
                 error: function(xhr, status, error) {
                     // Handle error silently
                 }
             });
         }
         
         // Function to load fields for a section
         function loadSectionFields(sectionId, tableId) {
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
                     const $currentFields = $('#current_section_fields');
                     
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
                             $currentFields.html('<small class="text-muted">No fields currently in this section</small>');
                         }
                     } else {
                         $currentFields.html('<small class="text-danger">Error loading fields</small>');
                     }
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
                         $moveFieldsDropdown.append('<option value="" disabled>No fields available to move</option>');
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

         // Capture original order when page loads
         function captureOriginalOrder() {
             originalOrder = [];
             $('#post_list>li').each(function() {
                 originalOrder.push($(this).attr("id"));
             });
         }
         captureOriginalOrder();

         // Sortable with modified behavior
         $("#post_list").sortable({
             delay: 150,
             opacity: 0.6, 
             cursor: 'move',
             handle: '.fa-bars',
             containment: 'parent',  // Restrict movement within parent container
             start: function(event, ui) {
                 isDragging = true;
                 // Capture current order before dragging
                 currentOrder = [];
                 $('#post_list>li').each(function() {
                     currentOrder.push($(this).attr("id"));
                 });
                 
                 // Add a visual indicator during drag
                 ui.item.addClass('bg-light');
             },
             stop: function(event, ui) {
                 // Remove visual indicator
                 ui.item.removeClass('bg-light');
                 
                 isDragging = false;
             },
             update: function(event, ui) {
                 // Maintain the new order without saving
                 var newOrder = [];
                 $('#post_list>li').each(function() {
                     newOrder.push($(this).attr("id"));
                 });

                 // Visual indication that changes are unsaved
                 $('#saveFieldOrder').addClass('btn-warning').removeClass('btn-primary');
                 $('#post_list').addClass('border border-warning');
             }
         });

         // Save Order Button
         $('#saveFieldOrder').on('click', function() {
             // Capture current order
             var newOrder = [];
             $('#post_list>li').each(function() {
                 newOrder.push($(this).attr("id"));
             });

             // Check if order has actually changed
             if (JSON.stringify(newOrder) === JSON.stringify(originalOrder)) {
                 Swal.fire({
                     icon: 'info',
                     title: 'No Changes',
                     text: 'The field order remains the same.'
                 });
                 return;
             }

             // Send to server with full details
             $.ajax({
                 url: "tabledetailsort.php",
                 type: 'post',
                 data: {
                     save_order: true,
                     tbid: <?php echo $which; ?>,
                     positions: newOrder
                 },
                 dataType: 'json',
                 beforeSend: function() {
                     // Disable button during save
                     $('#saveFieldOrder')
                         .prop('disabled', true)
                         .html('<i class="fa fa-spinner fa-spin"></i> Saving...');
                 },
                 success: function(response) {
                     if (response.status === 'success') {
                         // Update original order
                         originalOrder = newOrder;
                         
                         // Remove visual unsaved indicators
                         $('#saveFieldOrder')
                             .prop('disabled', false)
                             .removeClass('btn-warning')
                             .addClass('btn-primary')
                             .html('Save Field Order');
                         $('#post_list').removeClass('border border-warning');
                         
                         // Show success message
                         Swal.fire({
                             icon: 'success',
                             title: 'Success',
                             text: 'Field order saved successfully.',
                             timer: 2000,
                             showConfirmButton: false
                         });
                     } else {
                         // Show error
                         $('#saveFieldOrder')
                             .prop('disabled', false)
                             .html('Save Field Order');
                     
                         Swal.fire({
                             icon: 'error',
                             title: 'Error',
                             text: response.message || 'An error occurred while saving the field order.'
                         });
                     }
                 },
                 error: function() {
                     // Handle AJAX error
                     $('#saveFieldOrder')
                         .prop('disabled', false)
                         .html('Save Field Order');
                     
                     Swal.fire({
                         icon: 'error',
                         title: 'Network Error',
                         text: 'Could not save field order'
                     });
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

         // Initialize sortable for each section
         document.querySelectorAll('.section-fields, .unsectioned-fields').forEach(function(el) {
           new Sortable(el, {
             group: 'fields', // Set the same group for all containers to allow dragging between them
             animation: 300, // Increased animation duration for smoother transitions
             handle: '.drag-handle',
             ghostClass: 'sortable-ghost',
             dragClass: 'sortable-drag',
             chosenClass: 'sortable-chosen',
             forceFallback: true, // This helps with consistent behavior across browsers
             fallbackClass: 'sortable-fallback',
             fallbackOnBody: true, // This can help with smoother dragging
             swapThreshold: 0.65, // Adjusting when items swap for better positioning
             fallbackTolerance: 5, // Moves pixel threshold before fallback activates
             scroll: true, // Enable scrolling when near the edges
             scrollSensitivity: 80, // Distance from edge to start scrolling
             scrollSpeed: 20, // Scrolling speed
             delay: 150, // Delay before drag starts, helps with unintentional drags
             delayOnTouchOnly: true, // Only delay for touch devices
             onStart: function(evt) {
               $(evt.item).addClass('dragging');
               // Hide empty section placeholders during drag
               $('.empty-section-placeholder').hide();
               
               // Scroll to ensure the dragged element is well in view
               const container = evt.item.closest('.sortable-section');
               container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
             },
             onEnd: function(evt) {
               $(evt.item).removeClass('dragging');
               // Add a highlight effect briefly
               $(evt.item).addClass('sortable-highlight');
               setTimeout(() => {
                 $(evt.item).removeClass('sortable-highlight');
               }, 1000);
               
               const fieldId = evt.item.dataset.fieldId;
               const tableId = evt.to.dataset.tableId;
               const newSectionId = evt.to.dataset.sectionId;
               
               // Get all field items in the new container to determine order
               const items = evt.to.querySelectorAll('.field-item');
               const fieldOrder = Array.from(items).indexOf(evt.item) + 1;
               
               // Save the change via AJAX
               $.ajax({
                 url: 'ajax/update_field_section.php', 
                 type: 'POST',
                 data: {
                   field_id: fieldId,
                   table_id: tableId,
                   section_id: newSectionId === 'null' ? null : newSectionId,
                   field_order: fieldOrder,
                   use_link_table: true // Flag to indicate we're using the new structure
                 },
                 dataType: 'json',
                 success: function(response) {
                   if (response.status === 'success') {
                     // Show success toast
                     Swal.fire({
                       icon: 'success',
                       title: 'Updated',
                       text: 'Field position updated successfully',
                       toast: true,
                       position: 'top-end',
                       showConfirmButton: false,
                       timer: 3000
                     });
                   } else {
                     // Show error message
                     Swal.fire({
                       icon: 'error',
                       title: 'Error',
                       text: response.message || 'An error occurred while updating the field position'
                     });
                   }
                 },
                 error: function(xhr, status, error) {
                   Swal.fire({
                     icon: 'error',
                     title: 'Error',
                     text: 'Failed to update field position: ' + error
                   });
                 }
               });
             }
           });
         });

         // Replace the second Sortable initialization with improved configuration
         $('.sortable-section > .row').each(function() {
           new Sortable(this, {
             handle: '.drag-handle',
             animation: 300, // Increased animation duration for smoother transitions
             draggable: '.col-md-4',
             ghostClass: 'sortable-ghost',
             dragClass: 'sortable-drag',
             chosenClass: 'sortable-chosen',
             forceFallback: true, // This helps with consistent behavior across browsers
             fallbackClass: 'sortable-fallback',
             fallbackOnBody: true, // This can help with smoother dragging
             fallbackTolerance: 5, // Moves pixel threshold before fallback activates
             scroll: true, // Enable scrolling when near the edges
             scrollSensitivity: 80, // Distance from edge to start scrolling
             scrollSpeed: 20, // Scrolling speed
             delay: 100,
             onStart: function(evt) {
               $(evt.item).addClass('dragging');
             },
             onEnd: function(evt) {
               $(evt.item).removeClass('dragging');
               // Add a highlight effect briefly
               $(evt.item).addClass('sortable-highlight');
               setTimeout(() => {
                 $(evt.item).removeClass('sortable-highlight');
               }, 1000);
               
               const sectionElement = $(evt.to).closest('.sortable-section');
               const sectionId = sectionElement.data('section-id');
               const tableId = sectionElement.data('table-id');
               const fieldIds = sectionElement.find('.field-item').map(function() {
                 return $(this).data('field-id');
               }).get();
               
               // Send AJAX request to update field order
               updateFieldOrder(sectionId, tableId, fieldIds);
             }
           });
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
         
         // Function to update field order
         function updateFieldOrder(sectionId, tableId, fieldIds) {
             $.ajax({
               url: 'ajax/update_field_order.php',
               type: 'POST',
                 data: {
                  section_id: sectionId,
                  table_id: tableId,
                  field_ids: fieldIds
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
               error: function() {
                  alert('Network error when updating field order');
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
        


        // Handle input keypress for adding options
        $('#newSelectionInput').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $('#addSelectionBtn').click();
            }
        });

         // Initialize Sortable for each section
         document.querySelectorAll('.sortable-fields').forEach(function(el) {
           new Sortable(el, {
             group: 'fields', // Set the same group for all containers to allow dragging between them
             animation: 300, // Increased animation duration for smoother transitions
             handle: '.drag-handle',
             ghostClass: 'sortable-ghost',
             dragClass: 'sortable-drag',
             chosenClass: 'sortable-chosen',
             forceFallback: true,
             fallbackClass: 'sortable-fallback',
             fallbackOnBody: true, // This helps with smoother dragging
             swapThreshold: 0.65, // Adjusting when items swap for better positioning
             fallbackTolerance: 5, // Moves pixel threshold before fallback activates
             scroll: true, // Enable scrolling when near the edges
             scrollSensitivity: 80, // Distance from edge to start scrolling
             scrollSpeed: 20, // Scrolling speed
             delay: 100,
             onStart: function(evt) {
               $(evt.item).addClass('dragging');
               // Hide empty section placeholders during drag
               $('.empty-section-placeholder').hide();
               
               // Scroll to ensure the dragged element is well in view
               const container = evt.item.closest('.sortable-section');
               container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
             },
             onEnd: function(evt) {
               $(evt.item).removeClass('dragging');
               // Add a highlight effect briefly
               $(evt.item).addClass('sortable-highlight');
               setTimeout(() => {
                 $(evt.item).removeClass('sortable-highlight');
               }, 1000);
               
               const fieldId = evt.item.dataset.fieldId;
               const newSectionId = $(evt.to).closest('.sortable-section').data('section-id');
               const tableId = $(evt.to).closest('.sortable-section').data('table-id');
               
               // Get the new order of all fields in the section
               const sectionFields = [];
               $(evt.to).find('.field-wrapper').each(function(index) {
                 sectionFields.push({
                   fieldId: $(this).data('field-id'),
                   order: index + 1
                 });
               });
               
               // Handle display of empty section placeholders
               $('.sortable-fields').each(function() {
                 const hasFields = $(this).find('.field-wrapper').length > 0;
                 const placeholder = $(this).find('.empty-section-placeholder');
                 
                 if (placeholder.length) {
                   if (hasFields) {
                     placeholder.remove(); // Remove the placeholder completely, not just hide
                   } else {
                     placeholder.show();
                   }
                 } else if (!hasFields) {
                   // If there's no placeholder and no fields, add the placeholder
                   $(this).append(`
                     <div class="col-12 text-center py-3 empty-section-placeholder">
                       <p class="text-muted mb-0">No fields assigned to this section. Add fields using the "Add New Field" button above.</p>
                     </div>
                   `);
                 }
               });
               
               // Save the changes to the database
               saveFieldSectionOrder(fieldId, newSectionId, tableId, sectionFields);
             }
           });
         });
         
         // Function to save field section order
         function saveFieldSectionOrder(fieldId, sectionId, tableId, sectionFields) {
           // Show a loading indicator
           const loadingToast = showNotification('info', '<i class="fa fa-spinner fa-spin"></i> Updating field order...', false);
           
           // Ensure sectionId is properly formatted - either a number or null
           const formattedSectionId = sectionId === 'null' ? null : parseInt(sectionId, 10);
           
           $.ajax({
             url: 'ajax/update_field_section_order.php',
             type: 'POST',
             data: {
               field_id: fieldId,
               section_id: formattedSectionId,
               table_id: tableId,
               section_fields: JSON.stringify(sectionFields)
             },
             dataType: 'json',
             success: function(response) {
               // Remove loading indicator
               loadingToast.alert('close');
               
               if (response.status === 'success') {
                 showNotification('success', 'Field order updated successfully', true);
                 
                 // Do another check of empty sections just to be sure
                 refreshEmptySections();
               } else {
                 showNotification('danger', 'Error updating field order: ' + response.message, true);
               }
             },
             error: function(xhr, status, error) {
               // Remove loading indicator
               loadingToast.alert('close');
               
               try {
                 const response = JSON.parse(xhr.responseText);
                 showNotification('danger', 'Network error: ' + (response.message || error), true);
               } catch (e) {
                 showNotification('danger', 'Network error when updating field order', true);
               }
             }
           });
         }
         
         // Function to refresh empty section placeholders
         function refreshEmptySections() {
           $('.sortable-fields').each(function() {
             const hasFields = $(this).find('.field-wrapper').length > 0;
             const placeholder = $(this).find('.empty-section-placeholder');
             
             if (hasFields && placeholder.length) {
               // If there are fields but a placeholder exists, remove it
               placeholder.remove();
             } else if (!hasFields && !placeholder.length) {
               // If there are no fields and no placeholder, add one
               $(this).append(`
                 <div class="col-12 text-center py-3 empty-section-placeholder">
                   <p class="text-muted mb-0">No fields assigned to this section. Add fields using the "Add New Field" button above.</p>
                 </div>
               `);
             }
           });
         }
         
         // Initialize Select2 for the sections dropdown
         if (typeof $.fn.select2 !== 'undefined') {
           $('#table_sections').select2({
             placeholder: 'Select sections for this table',
             allowClear: true,
             width: '100%'
           });
         }
         
         // Save table sections
         $('#saveTableSections').on('click', function() {
           const $btn = $(this);
           const $form = $('#manageSectionsForm');
           const formData = $form.serialize();
           
           // Change button state
           $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
           
           $.ajax({
             url: 'ajax/update_table_sections.php',
             type: 'POST',
             data: formData,
             dataType: 'json',
             success: function(response) {
               $btn.prop('disabled', false).html('Save Changes');
               
               if (response.status === 'success') {
                 // Close modal
                 $('#manageSectionsModal').modal('hide');
                 
                 // Show success message
                 showNotification('success', response.message || 'Sections updated successfully');
                 
                 // Reload the page after a slight delay
                 setTimeout(function() {
                   location.reload();
                 }, 1000);
               } else {
                 // Show error message
                 showNotification('danger', response.message || 'An error occurred');
               }
             },
             error: function(xhr, status, error) {
               $btn.prop('disabled', false).html('Save Changes');
               
               // Show error notification
               showNotification('danger', 'An error occurred while saving: ' + error);
               console.error('AJAX Error:', xhr.responseText);
             }
           });
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
         
         // Test schema button handler
         $('#testSchemaBtn').on('click', function() {
             $.ajax({
                 url: 'ajax/test_schema.php',
                 method: 'GET',
                 headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                 },
                 dataType: 'json',
                 success: function(response) {
                     if (response.status === 'success') {
                         const message = `Schema test results:
                         - section_id column exists: ${response.section_id_exists}
                         - Admin type: ${response.admin_type}
                         - Total columns: ${response.columns.length}`;
                         
                         alert(message);
                     } else {
                         alert('Schema test failed: ' + response.message);
                     }
                 },
                 error: function(xhr, status, error) {
                     alert('Schema test network error: ' + error);
                 }
             });
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
                        $tables_result = $mysqli->query($tables_query);
                        
                        while ($table = $tables_result->fetch_assoc()) {
                           echo "<option value='" . $table['tbid'] . "'>" . htmlspecialchars($table['tab_name']) . "</option>";
                        }
                        ?>
                     </select>
                  </div>
                  
                  <hr>
                  <h6>Field Management</h6>
                  <div class="form-group">
                     <label>Current Fields in This Section</label>
                     <div id="current_section_fields" class="border rounded p-2" style="min-height: 50px;">
                        <small class="text-muted">Loading fields...</small>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Save Changes</button>
               </div>
            </form>
         </div>
      </div>
   </div>
   
   <!-- Assign Unsectioned Fields Modal -->
   <div class="modal fade" id="assign_section_modal" tabindex="-1" role="dialog" aria-labelledby="assignSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <form id="assign_section_form" method="post" action="ajax/assign_section.php">
               <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title" id="assignSectionModalLabel">Assign Unsectioned Fields</h5>
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
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Assign Fields</button>
               </div>
            </form>
         </div>
      </div>
   </div>

   <!-- Add Field Modal -->
   <div class="modal fade" id="addFieldModal" tabindex="-1" role="dialog" aria-labelledby="addFieldModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title" id="addFieldModalLabel">Add New Field</h5>
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
                        $fieldstmt = $mysqli->prepare("
                           SELECT st.stid, st.str, st.single 
                           FROM select_types st 
                           LEFT JOIN tab_fields tf ON st.stid = tf.stid AND tf.tbid = ?
                           WHERE tf.stid IS NULL
                        ");
                        $fieldstmt->bind_param("i", $which);
                        $fieldstmt->execute();
                        $result = $fieldstmt->get_result();
                        
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

                        while ($row = $result->fetch_assoc()) {
                           $listtype = $fieldTypes[$row['single']] ?? 'Unknown';
                           echo "<option value=\"{$row['stid']}\">{$row['str']} ({$listtype})</option>";
                        }
                        $fieldstmt->close();
                        ?>
                     </select>
                  </div>
                  
                  <div class="form-group">
                     <label for="sort_order">Sort Order</label>
                     <input type="number" class="form-control" id="sort_order" name="sort_order" 
                            value="<?php echo $sort_order; ?>" 
                            required>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Add Field</button>
               </div>
            </form>
         </div>
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
                        <small>Click <i class="fas fa-pencil-alt"></i> to edit an option, or <i class="fas fa-trash"></i> to delete it</small>
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
               </div>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-primary" id="saveSelectionsBtn">Save Selections</button>
            </div>
         </div>
      </div>
   </div>

   <!-- Edit Field Modal -->
   <div class="modal fade" id="editFieldModal" tabindex="-1" role="dialog" aria-labelledby="editFieldModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFieldModalLabel">Edit Field</h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <div class="modal-body">
                <form id="edit_field_form">
                    <input type="hidden" id="edit_field_id" name="field_id">
                    
                    <div class="form-group">
                        <label for="edit_field_name">Field Name</label>
                        <input type="text" class="form-control" id="edit_field_name" name="field_name" required>
                  </div>
                    
                    <div class="form-group">
                        <label for="edit_field_type">Field Type</label>
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
                    
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
                </form>
         </div>
      </div>
   </div>

   <!-- Manage Table Sections Modal -->
   <div class="modal fade" id="manageSectionsModal" tabindex="-1" role="dialog" aria-labelledby="manageSectionsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
            <div class="modal-header bg-primary text-white">
               <h5 class="modal-title" id="manageSectionsModalLabel">Manage Table Sections</h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <div class="modal-body">
               <form id="manageSectionsForm" method="post" action="ajax/update_table_sections.php">
                  <input type="hidden" name="table_id" value="<?php echo $which; ?>">
                  
                  <p class="alert alert-info">
                     <i class="fas fa-info-circle"></i> Select which sections should be displayed in this table. Only selected sections will be available for field assignments.
                  </p>
                  
                  <div class="form-group">
                     <label for="table_sections">Available Sections</label>
                     <select class="form-control select2" id="table_sections" name="section_ids[]" multiple data-placeholder="Select sections for this table">
                        <?php
                        // Get all available sections
                        $available_sections_query = "SELECT section_id, section_name FROM field_sections ORDER BY section_order ASC";
                        $available_sections_result = $mysqli->query($available_sections_query);
                        
                        // Get existing section links for this table
                        $current_sections_query = "SELECT section_id FROM section_table_link WHERE tbid = ?";
                        $current_sections_stmt = $mysqli->prepare($current_sections_query);
                        $current_sections_stmt->bind_param("i", $which);
                        $current_sections_stmt->execute();
                        $current_sections_result = $current_sections_stmt->get_result();
                        
                        $current_section_ids = [];
                        while ($row = $current_sections_result->fetch_assoc()) {
                           $current_section_ids[] = $row['section_id'];
                        }
                        $current_sections_stmt->close();
                        
                        // Display all sections with current ones selected
                        while ($section = $available_sections_result->fetch_assoc()) {
                           $section_id = $section['section_id'];
                           $section_name = htmlspecialchars($section['section_name']);
                           $is_selected = in_array($section_id, $current_section_ids) ? 'selected' : '';
                           
                           echo "<option value=\"$section_id\" $is_selected>$section_name</option>";
                        }
                        ?>
                     </select>
                     <small class="form-text text-muted">Use CTRL+click (or CMD+click on Mac) to select multiple sections</small>
                  </div>
                  
                  <div class="form-group mt-4">
                     <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="mb-0">Currently Assigned Sections</label>
                        <a href="sections.php" target="_blank" class="btn btn-sm btn-outline-secondary">
                           <i class="fas fa-external-link-alt"></i> Manage All Sections
                        </a>
                     </div>
                     
                     <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                           <thead class="thead-light">
                              <tr>
                                 <th>Section</th>
                                 <th>Description</th>
                                 <th width="150">Field Count</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              if (empty($current_section_ids)) {
                                 echo '<tr><td colspan="3" class="text-center text-muted">No sections assigned to this table yet</td></tr>';
                              } else {
                                 foreach ($current_section_ids as $section_id) {
                                    // Get section details
                                    $section_query = "SELECT section_name, section_description FROM field_sections WHERE section_id = ?";
                                    $section_stmt = $mysqli->prepare($section_query);
                                    $section_stmt->bind_param("i", $section_id);
                                    $section_stmt->execute();
                                    $section_stmt->bind_result($section_name, $section_description);
                                    $section_stmt->fetch();
                                    $section_stmt->close();
                                    
                                    // Count fields in this section for this table
                                    $fields_count_query = "
                                       SELECT COUNT(*) 
                                       FROM select_types st
                                       JOIN tab_fields tf ON st.stid = tf.stid
                                       WHERE st.section_id = ? AND tf.tbid = ?
                                    ";
                                    $fields_count_stmt = $mysqli->prepare($fields_count_query);
                                    $fields_count_stmt->bind_param("ii", $section_id, $which);
                                    $fields_count_stmt->execute();
                                    $fields_count_stmt->bind_result($field_count);
                                    $fields_count_stmt->fetch();
                                    $fields_count_stmt->close();
                                    
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($section_name) . '</td>';
                                    echo '<td>' . (empty($section_description) ? '<em class="text-muted">No description</em>' : htmlspecialchars($section_description)) . '</td>';
                                    echo '<td class="text-center"><span class="badge badge-' . ($field_count > 0 ? 'primary' : 'secondary') . '">' . $field_count . ' fields</span></td>';
                                    echo '</tr>';
                                 }
                              }
                              ?>
                           </tbody>
                        </table>
                     </div>
                  </div>
               </form>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-primary" id="saveTableSections">Save Changes</button>
            </div>
         </div>
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
        
        .edit-option-btn, .delete-option-btn {
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
    </style>
    
    <!-- JavaScript for handling field edits -->
    <script>
    $(document).ready(function() {
        // Handle edit field form submission
        $('#edit_field_form').on('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const fieldId = $('#edit_field_id').val();
            const fieldName = $('#edit_field_name').val();
            const fieldType = $('#edit_field_type').val();
            const fieldOptions = $('#edit_field_options').val();
            const fieldSection = $('#edit_field_section').val();
            
            // Basic validation
            if (!fieldId || !fieldName || fieldType === undefined) {
                showNotification('danger', 'Please fill out all required fields');
                return;
            }
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
            
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
                    field_section: fieldSection
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Edit field response:', response);
                    submitBtn.html(originalBtnText).prop('disabled', false);
                    
                    if (response.status === 'success') {
                        $('#editFieldModal').modal('hide');
                        showNotification('success', 'Field updated successfully!');
                        
                        // Reload the current page with the correct 'which' parameter
                        setTimeout(function() {
                            // Get the current 'which' parameter
                            const urlParams = new URLSearchParams(window.location.search);
                            const whichParam = urlParams.get('which');
                            
                            // Construct the correct URL
                            const redirectUrl = `tabledetail.php?which=${whichParam}`;
                            
                            // Navigate to the URL
                            window.location.href = redirectUrl;
                        }, 1000);
                    } else {
                        showNotification('danger', 'Error updating field: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.html(originalBtnText).prop('disabled', false);
                    
                    console.error('AJAX Error Details:');
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
    });
    </script>
    
    <!-- Essential JavaScript functions that depend on jQuery -->
    <script>
    // Test that jQuery is working
    $(document).ready(function() {
        
    });
    
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
    

    </script>
    
    <!-- Additional scripts that depend on jQuery -->
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js" integrity="sha256-eTyxS0rkjpLEo16uXTS0uVCS4815lc40K2iVpWDvdSY=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.13.0/Sortable.min.js"></script>
</body>
</html>