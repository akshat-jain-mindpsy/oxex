<?php
ob_start(); // Start output buffering

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Manage Sections";

// Set variables needed by adminjs.php
setAdminVars(3); // Tables section
$subtitle = "Sections";

// Process ALL form submissions first, before ANY HTML output
// Check for flash messages from previous request
$message = '';
$alertType = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $alertType = $_SESSION['flash_alert_type'] ?? 'info';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_alert_type']);
}

// Process forms and handle redirects
if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
    
    // Add new section
    if (isset($_POST['new_section'])) {
        $section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';
        $section_description = isset($_POST['section_description']) ? trim($_POST['section_description']) : '';
        
        // Determine the next order position
        $order_stmt = $pdo->prepare("SELECT COALESCE(MAX(section_order) + 1, 1) FROM field_sections");
        $order_stmt->execute();
        $next_order = (int)$order_stmt->fetchColumn();
        $order_stmt->closeCursor();
        
        if (!empty($section_name)) {
            // Get the next available section_id if auto-increment isn't working
            $next_id_stmt = $pdo->prepare("SELECT COALESCE(MAX(section_id), 0) + 1 AS next_id FROM field_sections");
            $next_id_stmt->execute();
            $next_id_result = $next_id_stmt->fetch(PDO::FETCH_ASSOC);
            $next_section_id = $next_id_result['next_id'] ?? 1;
            $next_id_stmt->closeCursor();
            
            // Insert new section with explicit section_id to ensure it's set
            $insert_stmt = $pdo->prepare("INSERT INTO field_sections (section_id, section_name, section_order, section_description) VALUES (?, ?, ?, ?) RETURNING section_id");
            $insert_stmt->execute([$next_section_id, $section_name, $next_order, $section_description]);
            
            // Get the newly created section_id from RETURNING clause
            $new_section = $insert_stmt->fetch(PDO::FETCH_ASSOC);
            $new_section_id = $new_section['section_id'] ?? $next_section_id;
            
            if ($new_section_id) {
                error_log("New section created with ID: " . $new_section_id);
                $_SESSION['flash_message'] = "Section added successfully!";
                $_SESSION['flash_alert_type'] = "success";
            } else {
                $err = $pdo->errorInfo()[2] ?? 'Unknown error';
                error_log("Error creating section: " . $err);
                $_SESSION['flash_message'] = "Error adding section: " . $err;
                $_SESSION['flash_alert_type'] = "danger";
            }
            $insert_stmt->closeCursor();
        } else {
            error_log("Section name validation failed - received: '" . var_export($_POST['section_name'] ?? 'NOT SET', true) . "'");
            $_SESSION['flash_message'] = "Section name cannot be empty!";
            $_SESSION['flash_alert_type'] = "warning";
        }
        
        // Redirect and exit
        header("Location: sections.php");
        exit;
    }
    
    // Edit section
    if (isset($_POST['edit_section'])) {
        error_log("Edit section form submitted");
        error_log("POST data: " . print_r($_POST, true));
        
        // Check if section_id exists in POST
        $section_id_raw = $_POST['section_id'] ?? 'NOT SET';
        error_log("Section ID (raw from POST): '" . var_export($section_id_raw, true) . "'");
        error_log("Section ID key exists in POST: " . (isset($_POST['section_id']) ? 'YES' : 'NO'));
        
        $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
        $section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';
        $section_description = isset($_POST['section_description']) ? trim($_POST['section_description']) : '';
        
        error_log("Section ID (after conversion): " . $section_id);
        error_log("Section name (raw): '" . var_export($_POST['section_name'] ?? 'NOT SET', true) . "'");
        error_log("Section name (trimmed): '" . $section_name . "'");
        error_log("Section name empty check: " . (empty($section_name) ? 'YES' : 'NO'));
        
        // Handle table links
        $table_ids = '';
        $filtered_ids = [];
        if (isset($_POST['table_ids']) && is_array($_POST['table_ids'])) {
            // Filter out empty values and convert to integers
            $filtered_ids = array_filter($_POST['table_ids'], function($id) {
                return !empty($id);
            });
            
            if (!empty($filtered_ids)) {
                $table_ids = implode(',', array_map('intval', $filtered_ids));
            }
            
            error_log("Table IDs to save: " . $table_ids);
        }
        
        if (!empty($section_name) && $section_id > 0) {
            // Start a transaction to ensure data consistency
            $pdo->beginTransaction();
            
            try {
                // Update section details
                $update_stmt = $pdo->prepare("UPDATE field_sections SET section_name = ?, section_description = ? WHERE section_id = ?");
                $update_stmt->execute([$section_name, $section_description, $section_id]);
                
                // Remove existing table links
                $delete_stmt = $pdo->prepare("DELETE FROM section_table_link WHERE section_id = ?");
                $delete_stmt->execute([$section_id]);
                
                // Insert new table links if any
                if (!empty($filtered_ids)) {
                    $insert_stmt = $pdo->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, ?)");
                    
                    foreach ($filtered_ids as $order => $table_id) {
                        $display_order = $order + 1; // Start at 1
                        $insert_stmt->execute([$section_id, $table_id, $display_order]);
                    }
                    $insert_stmt->closeCursor();
                }
                
                // Commit the transaction
                $pdo->commit();
                
                $_SESSION['flash_message'] = "Section updated successfully!";
                $_SESSION['flash_alert_type'] = "success";
            } catch (Exception $e) {
                // Rollback the transaction on error
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                
                $_SESSION['flash_message'] = "Error updating section: " . $e->getMessage();
                $_SESSION['flash_alert_type'] = "danger";
                error_log("Error updating section: " . $e->getMessage());
            }
            
            // Redirect and exit
            header("Location: sections.php");
            exit;
        } else {
            $error_msg = "Section name cannot be empty!";
            if (empty($section_name)) {
                $error_msg .= " (Section name is empty or contains only whitespace)";
            }
            if ($section_id <= 0) {
                $error_msg .= " (Invalid section ID: " . $section_id . ")";
            }
            error_log("Validation failed: " . $error_msg);
            $_SESSION['flash_message'] = $error_msg;
            $_SESSION['flash_alert_type'] = "warning";
            
            // Redirect and exit
            header("Location: sections.php");
            exit;
        }
    }
    
    // Delete section
    if (isset($_GET['delete']) && isset($_GET['id'])) {
        $section_id = (int)$_GET['id'];
        
        // First check if there are any fields using this section
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM select_types WHERE section_id = ?");
        $check_stmt->execute([$section_id]);
        $field_count = (int)$check_stmt->fetchColumn();
        $check_stmt->closeCursor();
        
        if ($field_count > 0) {
            $message = "Cannot delete: This section is used by $field_count fields. Please reassign those fields first.";
            $alertType = "warning";
        } else {
            $delete_stmt = $pdo->prepare("DELETE FROM field_sections WHERE section_id = ? LIMIT 1");
            $delete_stmt->execute([$section_id]);
            
            if ($delete_stmt->rowCount() > 0) {
                $message = "Section deleted successfully!";
                $alertType = "success";
                
                // Reorder remaining sections
                $pdo->query("SET @rank = 0");
                $pdo->query("UPDATE field_sections SET section_order = (@rank:=@rank+1) ORDER BY section_order");
            } else {
                $err = $pdo->errorInfo()[2] ?? 'Unknown error';
                $message = "Error deleting section: " . $err;
                $alertType = "danger";
            }
            $delete_stmt->closeCursor();
        }
    }
    
    // Reorder sections (AJAX endpoint)
    if (isset($_POST['reorder']) && isset($_POST['sections'])) {
        $sections = $_POST['sections'];
        $success = true;
        
        foreach ($sections as $order => $id) {
            $id = (int)$id;
            $order = (int)$order + 1; // Start at 1, not 0
            
            $update_stmt = $pdo->prepare("UPDATE field_sections SET section_order = ? WHERE section_id = ?");
            $update_stmt->execute([$order, $id]);
            
            if ($update_stmt->rowCount() < 0) {
                $success = false;
            }
            $update_stmt->closeCursor();
        }
        
        if ($success) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => ($pdo->errorInfo()[2] ?? 'Unknown error')]);
        }
        exit;
    }
    
    // Fetch categories for a section (AJAX endpoint)
    if (isset($_POST['fetch_section_categories']) && isset($_POST['section_id'])) {
        $section_id = (int)$_POST['section_id'];
        
        $stmt = $pdo->prepare("SELECT stid, str, single FROM select_types WHERE section_id = ? ORDER BY str ASC");
        $stmt->execute([$section_id]);
        
        $categories = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $typeText = 'Unknown';
            switch ((int)$row['single']) {
                case 0: $typeText = 'Single Select'; break;
                case 1: $typeText = 'Multi Select'; break;
                case 2: $typeText = 'Text'; break;
                case 3: $typeText = 'Date'; break;
                case 4: $typeText = 'Numeric (0.1)'; break;
                case 5: $typeText = 'Numeric (Integer)'; break;
                case 6: $typeText = 'Time'; break;
            }
            $categories[] = [
                'id' => (int)$row['stid'],
                'name' => $row['str'],
                'type' => $typeText
            ];
        }
        $stmt->closeCursor();
        
        // Fetch sheet names this section is assigned to
        $sheets = [];
        $sheet_stmt = $pdo->prepare("SELECT t.tbid, t.tab_name FROM section_table_link stl JOIN tabs_tbl t ON stl.tbid = t.tbid WHERE stl.section_id = ? ORDER BY t.sort_order ASC, t.tab_name ASC");
        $sheet_stmt->execute([$section_id]);
        while ($s = $sheet_stmt->fetch(PDO::FETCH_ASSOC)) {
            $sheets[] = [ 'id' => (int)$s['tbid'], 'name' => $s['tab_name'] ];
        }
        $sheet_stmt->closeCursor();
        
        echo json_encode(['status' => 'success', 'categories' => $categories, 'sheets' => $sheets]);
        exit;
    }
    
    // Remove field from section
    if (isset($_GET['remove_field']) && isset($_GET['field_id']) && isset($_GET['section_id'])) {
        $field_id = (int)$_GET['field_id'];
        $section_id = (int)$_GET['section_id'];
        
        // Verify the field belongs to this section
        $check_stmt = $pdo->prepare("SELECT str FROM select_types WHERE stid = ? AND section_id = ?");
        $check_stmt->execute([$field_id, $section_id]);
        $row = $check_stmt->fetch(PDO::FETCH_NUM);
        
        if ($row) {
            $field_name = $row[0];
            $check_stmt->closeCursor();
            
            // Remove the section association by setting section_id to NULL
            $update_stmt = $pdo->prepare("UPDATE select_types SET section_id = NULL WHERE stid = ? LIMIT 1");
            $update_stmt->execute([$field_id]);
            
            if ($update_stmt->rowCount() > 0) {
                $message = "Field \"$field_name\" removed from section successfully.";
                $alertType = "success";
            } else {
                $err = $pdo->errorInfo()[2] ?? 'Unknown error';
                $message = "Error removing field from section: " . $err;
                $alertType = "danger";
            }
            $update_stmt->closeCursor();
        } else {
            $check_stmt->closeCursor();
            $message = "Field not found in this section.";
            $alertType = "warning";
        }
    }
}

// Only start HTML output after all redirects
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
      .drag-handle {
         cursor: move;
         padding: 2px 5px;
         border-radius: 3px;
      }
      .drag-handle:hover {
         background-color: #f0f0f0;
      }
      .section-row {
         transition: background-color 0.3s;
      }
      .section-row:hover {
         background-color: #f8f9fa;
      }
      .section-count {
         font-size: 0.85rem;
         padding: 2px 6px;
         border-radius: 4px;
         background-color: #e9ecef;
      }
      #field_search_results.show {
         display: block;
         position: absolute;
         width: 100%;
         z-index: 1050;
         background-color: white;
         border: 1px solid rgba(0,0,0,.15);
         border-radius: .25rem;
      }
      .dropdown-item.field-result {
         padding: 0.5rem 1rem;
         cursor: pointer;
      }
      .dropdown-item.field-result:hover {
         background-color: #f8f9fa;
      }
      .dropdown-menu.show {
         display: block;
      }
      #field_search_results.show {
         display: block;
         max-height: 300px;
         overflow-y: auto;
         z-index: 1000;
      }
      .field-result {
         padding: 8px 15px;
         border-bottom: 1px solid #f0f0f0;
      }
      .field-result:hover {
         background-color: #f8f9fa;
      }
   </style>
   <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            <div class="content-header">
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?></small></div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
               <?php echo $message; ?>
               <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <?php endif; ?>
            
            <div class="row">
               <div class="col-12">
                  <div class="card mb-4">
                     <div class="card-header">
                        <div class="card-title">Sections</div>
                        <div class="float-right">
                           <button class="btn btn-sm btn-light" data-toggle="modal" data-target="#newSectionModal">
                              <i class="fa fa-plus"></i> Add New Section
                           </button>
</div>
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table class="table table-hover">
                              <thead>
                                 <tr>
                                    <th width="50">#</th>
                                    <th>Section Name</th>
                                    <th>Description</th>
                                    <th>Categories</th>
                                    <th>Sheets</th>
                                    <th>Actions</th>
                                 </tr>
                              </thead>
                              <tbody id="sections-table-body">
                                 <?php
                                 // Get all sections with their table connections using section_table_link
                                 // Use database-agnostic query (works with both MySQL and PostgreSQL)
                                 try {
                                    // Try PostgreSQL syntax first (STRING_AGG)
                                    $query = "
                                       SELECT 
                                          fs.section_id,
                                          fs.section_name,
                                          fs.section_description,
                                          fs.section_order,
                                          STRING_AGG(DISTINCT stl.tbid::text, ',') AS table_ids,
                                          STRING_AGG(DISTINCT t.tab_name, ',') AS table_names,
                                          COUNT(DISTINCT stl.tbid) AS table_count,
                                          COUNT(DISTINCT st.stid) AS field_count
                                       FROM 
                                          field_sections fs
                                       LEFT JOIN 
                                          section_table_link stl ON fs.section_id = stl.section_id
                                       LEFT JOIN 
                                          tabs_tbl t ON stl.tbid = t.tbid
                                       LEFT JOIN 
                                          select_types st ON fs.section_id = st.section_id
                                       GROUP BY 
                                          fs.section_id, fs.section_name, fs.section_description, fs.section_order
                                       ORDER BY 
                                          fs.section_order
                                    ";
                                    
                                    $sections_result = $pdo->query($query);
                                 } catch (Exception $e) {
                                    // Fallback to MySQL syntax if PostgreSQL fails
                                    error_log("PostgreSQL query failed, trying MySQL syntax: " . $e->getMessage());
                                    $query = "
                                       SELECT 
                                          fs.section_id,
                                          fs.section_name,
                                          fs.section_description,
                                          fs.section_order,
                                          GROUP_CONCAT(DISTINCT stl.tbid SEPARATOR ',') AS table_ids,
                                          GROUP_CONCAT(DISTINCT t.tab_name SEPARATOR ',') AS table_names,
                                          COUNT(DISTINCT stl.tbid) AS table_count,
                                          COUNT(DISTINCT st.stid) AS field_count
                                       FROM 
                                          field_sections fs
                                       LEFT JOIN 
                                          section_table_link stl ON fs.section_id = stl.section_id
                                       LEFT JOIN 
                                          tabs_tbl t ON stl.tbid = t.tbid
                                       LEFT JOIN 
                                          select_types st ON fs.section_id = st.section_id
                                       GROUP BY 
                                          fs.section_id, fs.section_name, fs.section_description, fs.section_order
                                       ORDER BY 
                                          fs.section_order
                                    ";
                                    $sections_result = $pdo->query($query);
                                 }
                                 
                                 if (!$sections_result) {
                                    $error = $pdo->errorInfo();
                                    error_log("Error fetching sections: " . print_r($error, true));
                                    echo "<tr><td colspan='6' class='text-danger'>Error loading sections: " . htmlspecialchars($error[2] ?? 'Unknown error') . "</td></tr>";
                                 } else {
                                    $row_count = 0;
                                    while ($section = $sections_result->fetch(PDO::FETCH_ASSOC)) {
                                       $row_count++;
                                       
                                       // Ensure section_id is a valid integer
                                       $section_id = isset($section['section_id']) ? (int)$section['section_id'] : 0;
                                       
                                       // Skip rows without a valid section_id
                                       if ($section_id <= 0) {
                                          error_log("Warning: Skipping section row #$row_count with invalid section_id. Full row data: " . print_r($section, true));
                                          continue;
                                       }
                                    
                                    $section_name = $section['section_name'] ?? '';
                                    $section_description = $section['section_description'] ?? '';
                                    $section_order = isset($section['section_order']) ? (int)$section['section_order'] : 0;
                                    $field_count = isset($section['field_count']) ? (int)$section['field_count'] : 0;
                                    
                                    // Count tables from the section_table_link table
                                    $table_count = isset($section['table_count']) ? (int)$section['table_count'] : 0;
                                    
                                    // Ensure section_id is properly set in all data attributes
                                    $section_id_attr = htmlspecialchars((string)$section_id, ENT_QUOTES);
                                    
                                    echo "<tr class='section-row' data-id='$section_id_attr'>";
                                    echo "<td><span class='drag-handle'><i class='fas fa-grip-lines'></i></span> $section_order</td>";
                                    echo "<td><button type='button' class='btn btn-link p-0 view-categories' data-section-id='$section_id_attr' data-section-name='" . htmlspecialchars($section_name, ENT_QUOTES) . "' title='View categories in this section'>" . htmlspecialchars($section_name) . "</button></td>";
                                    echo "<td>" . (empty($section_description) ? "<em class='text-muted'>No description</em>" : htmlspecialchars($section_description)) . "</td>";
                                    echo "<td><span class='section-count'>$field_count</span></td>";
                                    echo "<td><span class='section-count' title='" . htmlspecialchars($section['table_names'] ?? '', ENT_QUOTES) . "'>$table_count</span></td>";
                                    echo "<td>
                                       <div class='btn-group btn-group-sm'>
                                          <button class='btn btn-sm btn-outline-info edit-section-btn' 
                                             data-id='$section_id_attr' 
                                             data-name='" . htmlspecialchars($section_name, ENT_QUOTES) . "' 
                                             data-description='" . htmlspecialchars($section_description, ENT_QUOTES) . "'
                                             data-table-ids='" . htmlspecialchars(trim($section['table_ids'] ?? ''), ENT_QUOTES) . "'
                                             data-table-names='" . htmlspecialchars(trim($section['table_names'] ?? ''), ENT_QUOTES) . "'
                                             title='Edit Section'>
                                             <i class='fas fa-edit'></i>
                                          </button>
                                          <a href='?delete=1&id=$section_id_attr' class='btn btn-sm btn-outline-danger delete-section' onclick='return confirm(\"Are you sure you want to delete this section? This cannot be undone.\");' title='Delete Section'>
                                             <i class='fas fa-trash'></i>
                                          </a>
                                       </div>
                                    </td>";
                                    echo "</tr>";
                                    }
                                 }
                                 ?>
                              </tbody>
                           </table>
</div>
</div>
         </div>
      </section>
   </div>
   
   <!-- New Section Modal -->
   <div class="modal fade" id="newSectionModal" tabindex="-1" role="dialog" aria-labelledby="newSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <form method="post" action="">
               <div class="modal-header bg-info text-white">
                  <h5 class="modal-title" id="newSectionModalLabel">Add New Section</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <div class="form-group">
                     <label for="section_name">Section Name</label>
                     <input type="text" class="form-control" id="section_name" name="section_name" required>
                  </div>
                  <div class="form-group">
                     <label for="section_description">Description (Optional)</label>
                     <textarea class="form-control" id="section_description" name="section_description" rows="3"></textarea>
</div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" name="new_section" class="btn btn-info">Add Section</button>
               </div>
            </form>
</div>
   </div>
   
   <!-- Edit Section Modal -->
   <div class="modal fade" id="editSectionModal" tabindex="-1" role="dialog" aria-labelledby="editSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <form method="post" action="">
               <div class="modal-header bg-info text-white">
                  <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <input type="hidden" id="edit_section_id" name="section_id">
                  <div class="form-group">
                     <label for="edit_section_name">Section Name</label>
                     <input type="text" class="form-control" id="edit_section_name" name="section_name" required>
                  </div>
                  <div class="form-group">
                     <label for="edit_section_description">Description (Optional)</label>
                     <textarea class="form-control" id="edit_section_description" name="section_description" rows="3"></textarea>
                  </div>
                  
                  <!-- New Tables Dropdown -->
                  <div class="form-group">
                     <label for="edit_table_ids">Sheets Using This Section</label>
                     <select class="form-control select2" id="edit_table_ids" name="table_ids[]" multiple>
                        <?php
                        // Get all tables to populate the dropdown
                        $tables_query = "SELECT tbid, tab_name FROM tabs_tbl ORDER BY sort_order ASC";
                        $tables_result = $pdo->query($tables_query);
                        
                        if ($tables_result) {
                           while ($table = $tables_result->fetch(PDO::FETCH_ASSOC)) {
                              $tbid = $table['tbid'];
                              $tab_name = $table['tab_name'];
                              echo "<option value='$tbid'>" . htmlspecialchars($tab_name) . "</option>";
                           }
                           $tables_result->closeCursor();
                        }
                        ?>
                     </select>
                     <small class="form-text text-muted">Select which sheets should display this section</small>
</div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" name="edit_section" class="btn btn-info">Save Changes</button>
               </div>
            </form>
</div>
   </div>
   
   <!-- Add Fields to Section Modal -->
   <div class="modal fade" id="addFieldsToSectionModal" tabindex="-1" role="dialog" aria-labelledby="addFieldsToSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
            <form method="post" action="" id="addFieldsToSectionForm">
               <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title" id="addFieldsToSectionModalLabel">Add Categories to Section: <span id="section-name-display"></span></h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <input type="hidden" id="target_section_id" name="target_section_id">
                  
                  <div class="form-group">
                     <label for="field_search">Search Categories</label>
                     <input type="text" class="form-control" id="field_search" placeholder="Start typing to search categories...">
                     <div id="field_search_results" class="list-group mt-2" style="max-height: 300px; overflow-y: auto;">
                        <?php
                        // Pre-load all fields
                        $field_query = "SELECT stid, str, single FROM select_types ORDER BY str ASC";
                        $field_result = $pdo->query($field_query);
                        
                        if ($field_result) {
                           while ($field = $field_result->fetch(PDO::FETCH_ASSOC)) {
                              $stid = $field['stid'];
                              $field_name = $field['str'];
                              $field_type = $field['single'];
                              
                              // Convert field type code to readable text
                              $type_text = '';
                              switch ($field_type) {
                                 case 0: $type_text = 'Single Select'; break;
                                 case 1: $type_text = 'Multi Select'; break;
                                 case 2: $type_text = 'Text'; break;
                                 case 3: $type_text = 'Date'; break;
                                 case 4: $type_text = 'Numeric (0.1)'; break;
                                 case 5: $type_text = 'Numeric (Integer)'; break;
                                 case 6: $type_text = 'Time'; break;
                                 default: $type_text = 'Unknown'; break;
                              }
                              
                              echo '<a href="#" class="list-group-item list-group-item-action field-item" ';
                              echo 'data-field-id="' . $stid . '" ';
                              echo 'data-field-name="' . htmlspecialchars($field_name, ENT_QUOTES) . '" ';
                              echo 'data-field-type="' . $type_text . '">';
                              echo '<strong>' . htmlspecialchars($field_name) . '</strong>';
                              echo '<span class="badge badge-secondary ml-2">' . $type_text . '</span>';
                              echo '</a>';
                           }
                           $field_result->closeCursor();
                        } else {
                           echo '<div class="list-group-item text-muted">No categories available</div>';
                        }
                        ?>
</div>
                  
                  <div class="form-group mt-4">
                     <label>Selected Categories</label>
                     <div id="selected_fields_list" class="list-group">
                        <div class="list-group-item text-muted text-center" id="no-fields-selected">No categories selected</div>
</div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" name="add_fields_to_section" class="btn btn-primary">Save Changes</button>
               </div>
            </form>
</div>
   </div>
   
   <!-- View Section Categories Modal -->
   <div class="modal fade" id="viewSectionCategoriesModal" tabindex="-1" role="dialog" aria-labelledby="viewSectionCategoriesModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
               <h5 class="modal-title" id="viewSectionCategoriesModalLabel">Categories in Section: <span id="view-section-name"></span></h5>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
               </button>
            </div>
            <div class="modal-body">
               <div id="section-categories-loading" class="text-center my-3" style="display:none;">
                  <i class="fas fa-spinner fa-spin"></i> Loading...
               </div>
               <div id="section-categories-empty" class="alert alert-info" style="display:none;">No categories are assigned to this section.</div>
               <div class="table-responsive">
                  <table class="table table-sm table-striped" id="section-categories-table" style="display:none;">
                     <thead>
                        <tr>
                           <th style="width: 80px;">ID</th>
                           <th>Name</th>
                           <th style="width: 180px;">Type</th>
                           <th>Sheets</th>
                        </tr>
                     </thead>
                     <tbody></tbody>
                  </table>
</div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
</div>
</div>
   
   <?php include 'incl/adminjs.php' ?>
   <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.13.0/Sortable.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
   <script>
      $(document).ready(function() {
         // Initialize sortable for section reordering
         const sectionsList = document.getElementById('sections-table-body');
         const sortable = new Sortable(sectionsList, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function(evt) {
               // Get the new order of sections
               const items = sectionsList.querySelectorAll('.section-row');
               const newOrder = Array.from(items).map(item => item.dataset.id);
               $.ajax({
                  url: 'sections.php',
                  type: 'POST',
                  data: {
                     reorder: true,
                     sections: newOrder
                  },
                  success: function(response) {
                     try {
                        const data = JSON.parse(response);
                        if (data.status === 'success') {
                           // Update row numbers
                           items.forEach((item, index) => {
                              const orderCell = item.querySelector('td:first-child');
                              const dragHandle = orderCell.querySelector('.drag-handle');
                              orderCell.innerHTML = '';
                              orderCell.appendChild(dragHandle);
                              orderCell.appendChild(document.createTextNode(' ' + (index + 1)));
                           });
                           
                           // Show success message
                           Swal.fire({
                              icon: 'success',
                              title: 'Order Updated',
                              text: 'The section order has been updated successfully.',
                              toast: true,
                              position: 'top-end',
                              showConfirmButton: false,
                              timer: 3000
                           });
                        } else {
                           Swal.fire({
                              icon: 'error',
                              title: 'Error',
                              text: data.message || 'An error occurred while updating the order.'
                           });
                        }
                     } catch (e) {
                        console.error('Error parsing response:', e);
                     }
                  },
                  error: function(xhr, status, error) {
                     Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to update section order: ' + error
                     });
                  }
               });
            }
         });
         

         // Edit section button click - use event delegation for reliability
         $(document).on('click', '.edit-section-btn', function() {
            // Use attr() instead of data() for more reliable numeric value retrieval
            const id = parseInt($(this).attr('data-id')) || 0;
            const name = $(this).attr('data-name') || '';
            const description = $(this).attr('data-description') || '';
            const tableIds = $(this).attr('data-table-ids') || '';
            const tableNames = $(this).attr('data-table-names') || '';
            
            console.log('Edit section clicked - ID:', id, 'Name:', name);
            
            if (!id || id === 0) {
               console.error('ERROR: Invalid section ID from button:', id);
               Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: 'Invalid section ID. Please refresh the page and try again.'
               });
               return;
            }
            
            // Ensure the hidden field is set with the section ID
            $('#edit_section_id').val(id);
            $('#edit_section_name').val(name);
            $('#edit_section_description').val(description);
            
            // Verify the value was set
            const verifyId = $('#edit_section_id').val();
            console.log('Section ID set in form:', verifyId);
            
            if (!verifyId || verifyId === '0') {
               console.error('ERROR: Failed to set section ID in form field');
               Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: 'Failed to set section ID. Please refresh the page and try again.'
               });
               return;
            }
            
            // Destroy existing Select2 instance if it exists
            if ($('#edit_table_ids').hasClass('select2-hidden-accessible')) {
               $('#edit_table_ids').select2('destroy');
            }
            
            // Initialize Select2 (ensure dropdown renders inside the modal to avoid z-index issues)
            $('#edit_table_ids').select2({
               placeholder: 'Select sheets...',
               allowClear: true,
               width: '100%',
               dropdownParent: $('#editSectionModal'),
               templateResult: function(state) {
                  if (!state.id) { return state.text; }
                  return $(`<span>${state.text}</span>`);
               }
            });
            
            // Parse table IDs - convert to array of integers
            let tableIdArray = [];
            if (tableIds && typeof tableIds === 'string' && tableIds.trim() !== '') {
               tableIdArray = tableIds.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
            }
            
            // Important: Set the values in the select dropdown
            setTimeout(() => {
               $('#edit_table_ids').val(tableIdArray).trigger('change');
            }, 100);
            
            $('#editSectionModal').modal('show');
         });

         // Debug form submission
         $('#editSectionModal form').on('submit', function(e) {
            // Get all selected values from the dropdown
            const tableIds = $('#edit_table_ids').val();
            
            // Verify section ID is set before submission
            const sectionId = $('#edit_section_id').val();
            const sectionName = $('#edit_section_name').val();
            
            console.log('Form submitting - Section ID:', sectionId, 'Section Name:', sectionName);
            
            if (!sectionId || sectionId === '0' || sectionId === '') {
               console.error('ERROR: Section ID is missing or invalid:', sectionId);
               e.preventDefault();
               Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: 'Section ID is missing. Please refresh the page and try again.'
               });
               return false;
            }
            
            if (!sectionName || sectionName.trim() === '') {
               console.error('ERROR: Section name is empty');
               // Let the server-side validation handle this
            }
         });

         // Add fields to section functionality
         $(document).on('click', '.add-fields-btn', function() {
            const sectionId = $(this).data('section-id');
            const sectionName = $(this).data('section-name');
            
            $('#target_section_id').val(sectionId);
            $('#section-name-display').text(sectionName);
            $('#selected_fields_list').html('<div class="list-group-item text-muted text-center" id="no-fields-selected">No categories selected</div>');
            $('#field_search').val('');
            $('#field_search_results').removeClass('show');
            
            $('#addFieldsToSectionModal').modal('show');
         });

         // Replace the AJAX search with client-side filtering
         $('#field_search').on('input', function() {
            const searchTerm = $(this).val().trim().toLowerCase();
            
            $('.field-item').each(function() {
               const fieldName = $(this).data('field-name').toLowerCase();
               if (searchTerm.length < 2 || fieldName.includes(searchTerm)) {
                  $(this).show();
               } else {
                  $(this).hide();
               }
            });
            
            // Show a message if no results
            const visibleItems = $('.field-item:visible').length;
            if (visibleItems === 0 && searchTerm.length >= 2) {
               if ($('#no-results-message').length === 0) {
                  $('#field_search_results').append('<div id="no-results-message" class="list-group-item text-muted">No matching categories found</div>');
               }
            } else {
               $('#no-results-message').remove();
            }
         });

         // Handle field selection (keep this part)
         $(document).on('click', '.field-item', function(e) {
            e.preventDefault();
            
            const fieldId = $(this).data('field-id');
            const fieldName = $(this).data('field-name');
            const fieldType = $(this).data('field-type');
            
            // Remove "no fields selected" message if present
            $('#no-fields-selected').remove();
            
            // Check if field is already selected
            if ($(`#selected-field-${fieldId}`).length === 0) {
               $('#selected_fields_list').append(`
                  <div class="list-group-item d-flex justify-content-between align-items-center" id="selected-field-${fieldId}">
                     <div>
                        <strong>${fieldName}</strong>
                        <span class="badge badge-secondary ml-2">${fieldType}</span>
                     </div>
                     <input type="hidden" name="field_ids[]" value="${fieldId}">
                     <button type="button" class="btn btn-sm btn-outline-danger remove-field" data-field-id="${fieldId}">
                        <i class="fas fa-times"></i>
                     </button>
                  </div>
               `);
            }
         });

         // Remove field from selection
         $(document).on('click', '.remove-field', function() {
            const fieldId = $(this).data('field-id');
            $(`#selected-field-${fieldId}`).remove();
            
            // Show "no fields selected" message if list is empty
            if ($('#selected_fields_list .list-group-item').length === 0) {
               $('#selected_fields_list').html('<div class="list-group-item text-muted text-center" id="no-fields-selected">No categories selected</div>');
            }
         });

         // Submit form to add fields to section (modify this in sections.php)
         $('#addFieldsToSectionForm').on('submit', function(e) {
            e.preventDefault();
            
            const sectionId = $('#target_section_id').val();
            const fieldIds = [];
            
            $('input[name="field_ids[]"]').each(function() {
               fieldIds.push($(this).val());
            });
            
            if (fieldIds.length === 0) {
               Swal.fire({
                  icon: 'warning',
                  title: 'No Fields Selected',
                  text: 'Please select at least one field to add to this section.'
               });
               return;
            }
            
            // Show loading state
            const submitButton = $(this).find('button[type="submit"]');
            const originalText = submitButton.html();
            submitButton.html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            submitButton.prop('disabled', true);
            
            $.ajax({
               url: 'ajax/add_fields_to_section.php',
               type: 'POST',
               data: {
                  section_id: sectionId,
                  field_ids: fieldIds
               },
               dataType: 'json',
               success: function(response) {
                  // Reset button state
                  submitButton.html(originalText);
                  submitButton.prop('disabled', false);
                  
                  console.log("Response:", response);
                  
                  // Check for success flag in the response regardless of warnings
                  if (response && response.status === 'success') {
                     $('#addFieldsToSectionModal').modal('hide');
                     
                     Swal.fire({
                        icon: 'success',
                        title: 'Categories Added',
                        text: response.message,
                        confirmButtonText: 'OK'
                     }).then((result) => {
                        if (result.isConfirmed) {
                           // Reload the page to show updated field assignments
                           location.reload();
                        }
                     });
                  } else {
                     Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'An error occurred while adding categories to the section.'
                     });
                  }
               },
               error: function(xhr, status, error) {
                  // Reset button state
                  submitButton.html(originalText);
                  submitButton.prop('disabled', false);
                  
                  console.error("AJAX error:", status, error);
                  
                  // Try to parse any JSON in the response
                  let errorMessage = 'Failed to add categories to the section.';
                  let successMessage = '';
                  let isSuccess = false;
                  
                  try {
                     // Check if the response contains JSON at the end (after warnings)
                     const responseText = xhr.responseText;
                     const jsonStartPos = responseText.indexOf('{');
                     if (jsonStartPos >= 0) {
                        const jsonText = responseText.substring(jsonStartPos);
                        const response = JSON.parse(jsonText);
                        
                        if (response.status === 'success') {
                           isSuccess = true;
                           successMessage = response.message;
                        } else if (response.message) {
                           errorMessage = response.message;
                        }
                     }
                  } catch (e) {
                     console.error("Failed to parse response:", e);
                  }
                  
                  if (isSuccess) {
                     $('#addFieldsToSectionModal').modal('hide');
                     
                     Swal.fire({
                        icon: 'success',
                        title: 'Categories Added',
                        text: successMessage,
                        confirmButtonText: 'OK'
                     }).then(() => {
                        location.reload();
                     });
                  } else {
                     Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMessage,
                        footer: 'Please check the console for more details.'
                     });
                  }
               }
            });
         });

         // Hide dropdown when clicking outside
         $(document).on('click', function(e) {
            if (!$(e.target).closest('#field_search, #field_search_results').length) {
               $('#field_search_results').removeClass('show');
            }
         });

         // Confirm removal of fields from sections
         $(document).on('click', '.remove-from-section', function(e) {
            e.preventDefault();
            
            const fieldId = $(this).data('field-id');
            const sectionId = $(this).data('section-id');
            const fieldName = $(this).data('field-name');
            
            Swal.fire({
               title: 'Remove Field from Section?',
               text: `Are you sure you want to remove "${fieldName}" from this section? The field itself will not be deleted.`,
               icon: 'warning',
               showCancelButton: true,
               confirmButtonColor: '#dc3545',
               cancelButtonColor: '#6c757d',
               confirmButtonText: 'Yes, remove it'
            }).then((result) => {
               if (result.isConfirmed) {
                  window.location.href = `?remove_field=1&field_id=${fieldId}&section_id=${sectionId}`;
               }
            });
         });

         // Initialize Select2 Elements
         if ($.fn.select2) {
            $('.select2').select2({
               placeholder: 'Select options...',
               allowClear: true,
               width: '100%'
            });
         }
         
         // View categories in a section
         $(document).on('click', '.view-categories', function() {
            const sectionId = $(this).data('section-id');
            const sectionName = $(this).data('section-name');
            
            $('#view-section-name').text(sectionName);
            $('#section-categories-table').hide();
            $('#section-categories-empty').hide();
            $('#section-categories-loading').show();
            $('#section-categories-table tbody').empty();
            
            $('#viewSectionCategoriesModal').modal('show');
            
            $.ajax({
               url: 'sections.php',
               type: 'POST',
               data: { fetch_section_categories: true, section_id: sectionId },
               success: function(response) {
                  let data;
                  try {
                     data = typeof response === 'string' ? JSON.parse(response) : response;
                  } catch (e) {
                     console.error('Invalid JSON', e, response);
                     Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load categories.' });
                     return;
                  }
                  $('#section-categories-loading').hide();
                  
                  if (data.status === 'success' && Array.isArray(data.categories)) {
                     if (data.categories.length === 0) {
                        $('#section-categories-empty').show();
                     } else {
                        const tbody = $('#section-categories-table tbody');
                        const sheets = Array.isArray(data.sheets) ? data.sheets : [];
                        const sheetsText = sheets.length ? sheets.map(s => $('<div>').text(s.name).html()).join(', ') : '<span class="text-muted">None</span>';
                        data.categories.forEach(item => {
                           const row = `<tr><td>${item.id}</td><td>${$('<div>').text(item.name).html()}</td><td>${item.type}</td><td>${sheetsText}</td></tr>`;
                           tbody.append(row);
                        });
                        $('#section-categories-table').show();
                     }
                  } else {
                     $('#section-categories-empty').show();
                  }
               },
               error: function(xhr, status, error) {
                  $('#section-categories-loading').hide();
                  Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load categories: ' + error });
               }
            });
         });
      });
   </script>
</body>
</html>
<?php
ob_end_flush(); // Send the buffered output
?>

