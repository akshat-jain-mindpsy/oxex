<?php
ob_start(); // Start output buffering

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Manage Sections";
$subtitle = "Sections";

// Process ALL form submissions first, before ANY HTML output
$message = '';
$alertType = '';

// Process forms and handle redirects
if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
    
    // Add new section
    if (isset($_POST['new_section'])) {
        $section_name = isset($_POST['section_name']) ? $_POST['section_name'] : '';
        $section_description = isset($_POST['section_description']) ? $_POST['section_description'] : '';
        
        // Determine the next order position
        $order_stmt = $mysqli->prepare("SELECT COALESCE(MAX(section_order) + 1, 1) FROM field_sections");
        $order_stmt->execute();
        $order_stmt->bind_result($next_order);
        $order_stmt->fetch();
        $order_stmt->close();
        
        if (!empty($section_name)) {
            $insert_stmt = $mysqli->prepare("INSERT INTO field_sections (section_name, section_order, section_description) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sis", $section_name, $next_order, $section_description);
            $insert_stmt->execute();
            
            if ($insert_stmt->affected_rows > 0) {
                $message = "Section added successfully!";
                $alertType = "success";
            } else {
                $message = "Error adding section: " . $mysqli->error;
                $alertType = "danger";
            }
            $insert_stmt->close();
        } else {
            $message = "Section name cannot be empty!";
            $alertType = "warning";
        }
        
        // Redirect and exit
        header("Location: sections.php");
        exit;
    }
    
    // Edit section
    if (isset($_POST['edit_section'])) {
        error_log("Edit section form submitted");
        error_log("POST data: " . print_r($_POST, true));
        
        $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
        $section_name = isset($_POST['section_name']) ? $_POST['section_name'] : '';
        $section_description = isset($_POST['section_description']) ? $_POST['section_description'] : '';
        
        // Handle table links
        $table_ids = '';
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
            $mysqli->begin_transaction();
            
            try {
                // Update section details
                $update_stmt = $mysqli->prepare("UPDATE field_sections SET section_name = ?, section_description = ? WHERE section_id = ?");
                $update_stmt->bind_param("ssi", $section_name, $section_description, $section_id);
                $update_stmt->execute();
                
                // Remove existing table links
                $delete_stmt = $mysqli->prepare("DELETE FROM section_table_link WHERE section_id = ?");
                $delete_stmt->bind_param("i", $section_id);
                $delete_stmt->execute();
                
                // Insert new table links if any
                if (!empty($filtered_ids)) {
                    $insert_stmt = $mysqli->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, ?)");
                    
                    foreach ($filtered_ids as $order => $table_id) {
                        $display_order = $order + 1; // Start at 1
                        $insert_stmt->bind_param("iii", $section_id, $table_id, $display_order);
                        $insert_stmt->execute();
                    }
                    $insert_stmt->close();
                }
                
                // Commit the transaction
                $mysqli->commit();
                
                $message = "Section updated successfully!";
                $alertType = "success";
            } catch (Exception $e) {
                // Rollback the transaction on error
                $mysqli->rollback();
                
                $message = "Error updating section: " . $e->getMessage();
                $alertType = "danger";
                error_log($message);
            }
            
            // Redirect and exit
            header("Location: sections.php");
            exit;
        } else {
            $message = "Section name cannot be empty!";
            $alertType = "warning";
        }
    }
    
    // Delete section
    if (isset($_GET['delete']) && isset($_GET['id'])) {
        $section_id = (int)$_GET['id'];
        
        // First check if there are any fields using this section
        $check_stmt = $mysqli->prepare("SELECT COUNT(*) FROM select_types WHERE section_id = ?");
        $check_stmt->bind_param("i", $section_id);
        $check_stmt->execute();
        $check_stmt->bind_result($field_count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($field_count > 0) {
            $message = "Cannot delete: This section is used by $field_count fields. Please reassign those fields first.";
            $alertType = "warning";
        } else {
            $delete_stmt = $mysqli->prepare("DELETE FROM field_sections WHERE section_id = ? LIMIT 1");
            $delete_stmt->bind_param("i", $section_id);
            $delete_stmt->execute();
            
            if ($delete_stmt->affected_rows > 0) {
                $message = "Section deleted successfully!";
                $alertType = "success";
                
                // Reorder remaining sections
                $mysqli->query("SET @rank = 0");
                $mysqli->query("UPDATE field_sections SET section_order = (@rank:=@rank+1) ORDER BY section_order");
            } else {
                $message = "Error deleting section: " . $mysqli->error;
                $alertType = "danger";
            }
            $delete_stmt->close();
        }
    }
    
    // Reorder sections (AJAX endpoint)
    if (isset($_POST['reorder']) && isset($_POST['sections'])) {
        $sections = $_POST['sections'];
        $success = true;
        
        foreach ($sections as $order => $id) {
            $id = (int)$id;
            $order = (int)$order + 1; // Start at 1, not 0
            
            $update_stmt = $mysqli->prepare("UPDATE field_sections SET section_order = ? WHERE section_id = ?");
            $update_stmt->bind_param("ii", $order, $id);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows < 0) {
                $success = false;
            }
            $update_stmt->close();
        }
        
        if ($success) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $mysqli->error]);
        }
        exit;
    }
    
    // Remove field from section
    if (isset($_GET['remove_field']) && isset($_GET['field_id']) && isset($_GET['section_id'])) {
        $field_id = (int)$_GET['field_id'];
        $section_id = (int)$_GET['section_id'];
        
        // Verify the field belongs to this section
        $check_stmt = $mysqli->prepare("SELECT str FROM select_types WHERE stid = ? AND section_id = ?");
        $check_stmt->bind_param("ii", $field_id, $section_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $check_stmt->bind_result($field_name);
            $check_stmt->fetch();
            $check_stmt->close();
            
            // Remove the section association by setting section_id to NULL
            $update_stmt = $mysqli->prepare("UPDATE select_types SET section_id = NULL WHERE stid = ? LIMIT 1");
            $update_stmt->bind_param("i", $field_id);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows > 0) {
                $message = "Field \"$field_name\" removed from section successfully.";
                $alertType = "success";
            } else {
                $message = "Error removing field from section: " . $mysqli->error;
                $alertType = "danger";
            }
            $update_stmt->close();
        } else {
            $check_stmt->close();
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
                     <div class="card-header bg-info text-white">
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
                                    <th>Fields</th>
                                    <th>Sheets</th>
                                    <th>Actions</th>
                                 </tr>
                              </thead>
                              <tbody id="sections-table-body">
                                 <?php
                                 // Get all sections with their table connections using section_table_link
                                 $query = "
                                    SELECT 
                                        fs.section_id,
                                        fs.section_name,
                                        fs.section_description,
                                        fs.section_order,
                                        GROUP_CONCAT(DISTINCT stl.tbid) AS table_ids,
                                        GROUP_CONCAT(DISTINCT t.tab_name) AS table_names,
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
                                 
                                 $sections_result = $mysqli->query($query);
                                 
                                 while ($section = $sections_result->fetch_assoc()) {
                                    $section_id = $section['section_id'];
                                    $section_name = $section['section_name'];
                                    $section_description = $section['section_description'];
                                    $section_order = $section['section_order'];
                                    $field_count = $section['field_count'];
                                    
                                    // Count tables from the section_table_link table
                                    $table_count = $section['table_count'];
                                    
                                    echo "<tr class='section-row' data-id='$section_id'>";
                                    echo "<td><span class='drag-handle'><i class='fas fa-grip-lines'></i></span> $section_order</td>";
                                    echo "<td>$section_name</td>";
                                    echo "<td>" . (empty($section_description) ? "<em class='text-muted'>No description</em>" : $section_description) . "</td>";
                                    echo "<td><span class='section-count'>$field_count</span></td>";
                                    echo "<td><span class='section-count' title='" . htmlspecialchars($section['table_names'] ?? '') . "'>$table_count</span></td>";
                                    echo "<td>
                                       <div class='btn-group btn-group-sm'>
                                          <button class='btn btn-sm btn-outline-info edit-section-btn' 
                                             data-id='$section_id' 
                                             data-name='" . htmlspecialchars($section_name, ENT_QUOTES) . "' 
                                             data-description='" . htmlspecialchars($section_description, ENT_QUOTES) . "'
                                             data-table-ids='" . htmlspecialchars(trim($section['table_ids'] ?? '')) . "'
                                             data-table-names='" . htmlspecialchars(trim($section['table_names'] ?? '')) . "'
                                             title='Edit Section'>
                                             <i class='fas fa-edit'></i>
                                          </button>
                                          <a href='?delete=1&id=$section_id' class='btn btn-sm btn-outline-danger delete-section' onclick='return confirm(\"Are you sure you want to delete this section? This cannot be undone.\");' title='Delete Section'>
                                             <i class='fas fa-trash'></i>
                                          </a>
                                       </div>
                                    </td>";
                                    echo "</tr>";
                                 }
                                 ?>
                              </tbody>
                           </table>
                        </div>
                        
                     </div>
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
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" name="new_section" class="btn btn-info">Add Section</button>
               </div>
            </form>
         </div>
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
                        $tables_result = $mysqli->query($tables_query);
                        
                        if ($tables_result && $tables_result->num_rows > 0) {
                           while ($table = $tables_result->fetch_assoc()) {
                              $tbid = $table['tbid'];
                              $tab_name = $table['tab_name'];
                              echo "<option value='$tbid'>" . htmlspecialchars($tab_name) . "</option>";
                           }
                           $tables_result->free();
                        }
                        ?>
                     </select>
                     <small class="form-text text-muted">Select which sheets should display this section</small>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" name="edit_section" class="btn btn-info">Save Changes</button>
               </div>
            </form>
         </div>
      </div>
   </div>
   
   <!-- Add Fields to Section Modal -->
   <div class="modal fade" id="addFieldsToSectionModal" tabindex="-1" role="dialog" aria-labelledby="addFieldsToSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
            <form method="post" action="" id="addFieldsToSectionForm">
               <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title" id="addFieldsToSectionModalLabel">Add Fields to Section: <span id="section-name-display"></span></h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <input type="hidden" id="target_section_id" name="target_section_id">
                  
                  <div class="form-group">
                     <label for="field_search">Search Fields</label>
                     <input type="text" class="form-control" id="field_search" placeholder="Start typing to search fields...">
                     <div id="field_search_results" class="list-group mt-2" style="max-height: 300px; overflow-y: auto;">
                        <?php
                        // Pre-load all fields
                        $field_query = "SELECT stid, str, single FROM select_types ORDER BY str ASC";
                        $field_result = $mysqli->query($field_query);
                        
                        if ($field_result && $field_result->num_rows > 0) {
                           while ($field = $field_result->fetch_assoc()) {
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
                           $field_result->free();
                        } else {
                           echo '<div class="list-group-item text-muted">No fields available</div>';
                        }
                        ?>
                     </div>
                  </div>
                  
                  <div class="form-group mt-4">
                     <label>Selected Fields</label>
                     <div id="selected_fields_list" class="list-group">
                        <div class="list-group-item text-muted text-center" id="no-fields-selected">No fields selected</div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" name="add_fields_to_section" class="btn btn-primary">Save Changes</button>
               </div>
            </form>
         </div>
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
               
               // Send the new order to the server
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
         
         // Debug function
         function debugSelect2() {
            console.log("Current select2 values:", $('#edit_table_ids').val());
            console.log("Select2 options:", $('#edit_table_ids option').length);
            console.log("Select2 selected options:", $('#edit_table_ids option:selected').length);
         }

         // Edit section button click
         $('.edit-section-btn').click(function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const description = $(this).data('description');
            const tableIds = $(this).data('table-ids') || '';
            const tableNames = $(this).data('table-names') || '';
            
            console.log("Button clicked for section:", id);
            console.log("Table IDs raw value:", tableIds);
            console.log("Table Names:", tableNames);
            
            $('#edit_section_id').val(id);
            $('#edit_section_name').val(name);
            $('#edit_section_description').val(description);
            
            // Destroy existing Select2 instance if it exists
            if ($('#edit_table_ids').hasClass('select2-hidden-accessible')) {
               $('#edit_table_ids').select2('destroy');
            }
            
            // Initialize Select2
            $('#edit_table_ids').select2({
               placeholder: 'Select sheets...',
               allowClear: true,
               width: '100%',
               templateResult: function(state) {
                  if (!state.id) { return state.text; }
                  return $(`<span>${state.text}</span>`);
               }
            });
            
            // Parse table IDs - convert to array of integers
            let tableIdArray = [];
            if (tableIds && typeof tableIds === 'string' && tableIds.trim() !== '') {
               tableIdArray = tableIds.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
               console.log("Parsed table IDs array:", tableIdArray);
            }
            
            // Important: Set the values in the select dropdown
            setTimeout(() => {
               $('#edit_table_ids').val(tableIdArray).trigger('change');
               console.log("Values after setting:", $('#edit_table_ids').val());
            }, 100);
            
            $('#editSectionModal').modal('show');
         });

         // Debug form submission
         $('#editSectionModal form').on('submit', function(e) {
            // Get all selected values from the dropdown
            const tableIds = $('#edit_table_ids').val();
            console.log("Table IDs being submitted:", tableIds);
            
            // Make sure these values are included in the form data
            if (!tableIds || tableIds.length === 0) {
               console.log("No tables selected");
            }
         });

         // Add fields to section functionality
         $(document).on('click', '.add-fields-btn', function() {
            const sectionId = $(this).data('section-id');
            const sectionName = $(this).data('section-name');
            
            $('#target_section_id').val(sectionId);
            $('#section-name-display').text(sectionName);
            $('#selected_fields_list').html('<div class="list-group-item text-muted text-center" id="no-fields-selected">No fields selected</div>');
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
                  $('#field_search_results').append('<div id="no-results-message" class="list-group-item text-muted">No matching fields found</div>');
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
               $('#selected_fields_list').html('<div class="list-group-item text-muted text-center" id="no-fields-selected">No fields selected</div>');
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
                        title: 'Fields Added',
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
                        text: response.message || 'An error occurred while adding fields to the section.'
                     });
                  }
               },
               error: function(xhr, status, error) {
                  // Reset button state
                  submitButton.html(originalText);
                  submitButton.prop('disabled', false);
                  
                  console.error("AJAX error:", status, error);
                  
                  // Try to parse any JSON in the response
                  let errorMessage = 'Failed to add fields to the section.';
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
                        title: 'Fields Added',
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
      });
   </script>
</body>
</html>
<?php
ob_end_flush(); // Send the buffered output
?>

