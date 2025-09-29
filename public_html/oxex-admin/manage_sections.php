<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check authorization
if (!(login_check($mysqli) == true && 
     in_array($admintype, ['AT', 'DV']))) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $tbid = isset($_POST['tbid']) ? (int)$_POST['tbid'] : 0;
    
    // Validate table ID
    if ($tbid <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid table ID'
        ]);
        exit();
    }
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        switch ($action) {
            case 'add_section':
                $section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';
                $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
                
                if (empty($section_name)) {
                    throw new Exception("Section name cannot be empty");
                }
                
                // Insert new section
                $insert_stmt = $mysqli->prepare("
                    INSERT INTO table_sections (tbid, section_name, sort_order) 
                    VALUES (?, ?, ?)
                ");
                $insert_stmt->bind_param("isi", $tbid, $section_name, $sort_order);
                $insert_stmt->execute();
                $section_id = $insert_stmt->insert_id;
                
                $mysqli->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Section added successfully',
                    'section_id' => $section_id,
                    'section_name' => $section_name
                ]);
                break;
                
            case 'update_section':
                $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
                $section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';
                
                if ($section_id <= 0) {
                    throw new Exception("Invalid section ID");
                }
                
                if (empty($section_name)) {
                    throw new Exception("Section name cannot be empty");
                }
                
                // Update section
                $update_stmt = $mysqli->prepare("
                    UPDATE table_sections 
                    SET section_name = ? 
                    WHERE id = ? AND tbid = ?
                ");
                $update_stmt->bind_param("sii", $section_name, $section_id, $tbid);
                $update_stmt->execute();
                
                $mysqli->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Section updated successfully'
                ]);
                break;
                
            case 'delete_section':
                $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
                
                if ($section_id <= 0) {
                    throw new Exception("Invalid section ID");
                }
                
                // Delete section
                $delete_stmt = $mysqli->prepare("
                    DELETE FROM table_sections 
                    WHERE id = ? AND tbid = ?
                ");
                $delete_stmt->bind_param("ii", $section_id, $tbid);
                $delete_stmt->execute();
                
                // Reset section_id for fields in this section
                $reset_stmt = $mysqli->prepare("
                    UPDATE tab_fields 
                    SET section_id = 0 
                    WHERE section_id = ? AND tbid = ?
                ");
                $reset_stmt->bind_param("ii", $section_id, $tbid);
                $reset_stmt->execute();
                
                $mysqli->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Section deleted successfully'
                ]);
                break;
                
            case 'update_section_order':
                $sections = isset($_POST['sections']) ? $_POST['sections'] : [];
                
                if (empty($sections)) {
                    throw new Exception("No sections provided");
                }
                
                // Update section order
                $update_stmt = $mysqli->prepare("
                    UPDATE table_sections 
                    SET sort_order = ? 
                    WHERE id = ? AND tbid = ?
                ");
                
                foreach ($sections as $index => $section_id) {
                    $sort_order = $index + 1;
                    $update_stmt->bind_param("iii", $sort_order, $section_id, $tbid);
                    $update_stmt->execute();
                }
                
                $mysqli->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Section order updated successfully'
                ]);
                break;
                
            case 'assign_field_to_section':
                $field_id = isset($_POST['field_id']) ? (int)$_POST['field_id'] : 0;
                $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
                
                if ($field_id <= 0) {
                    throw new Exception("Invalid field ID");
                }
                
                // Update field's section
                $update_stmt = $mysqli->prepare("
                    UPDATE tab_fields 
                    SET section_id = ? 
                    WHERE stid = ? AND tbid = ?
                ");
                $update_stmt->bind_param("iii", $section_id, $field_id, $tbid);
                $update_stmt->execute();
                
                $mysqli->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Field assigned to section successfully'
                ]);
                break;
                
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        // Rollback on error
        $mysqli->rollback();
        
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// If not an AJAX request, show the sections management UI
$pagetitle = "Manage Sections";
$tbid = isset($_GET['tbid']) ? (int)$_GET['tbid'] : 0;

// Validate table ID
if ($tbid <= 0) {
    header("Location: sheets.php");
    exit();
}

// Get table name
$table_stmt = $mysqli->prepare("SELECT str FROM select_types WHERE stid = ?");
$table_stmt->bind_param("i", $tbid);
$table_stmt->execute();
$table_stmt->bind_result($table_name);
$table_stmt->fetch();
$table_stmt->close();
?>

<!DOCTYPE html>
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
        .section-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .section-header {
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #ddd;
            cursor: move;
        }
        
        .section-body {
            padding: 15px;
        }
        
        .field-item {
            padding: 8px;
            margin-bottom: 5px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: move;
        }
        
        .field-item:hover {
            background-color: #f8f9fa;
        }
        
        .section-placeholder {
            border: 2px dashed #ccc;
            background-color: #f9f9f9;
            height: 100px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .field-placeholder {
            border: 2px dashed #ccc;
            background-color: #f9f9f9;
            height: 40px;
            margin-bottom: 5px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'incl/adminnav.php' ?>
        
        <section>
            <div class="content-wrapper">
                <div class="content-heading">
                    <div><?php echo $pagetitle ?></div>
                </div>
                
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">Sections for <?php echo htmlspecialchars($table_name) ?></h4>
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSectionModal">
                                            <i class="fa fa-plus"></i> Add New Section
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <!-- Sections list -->
                                            <div id="sections-container">
                                                <?php
                                                // Get sections for this table
                                                $sections_stmt = $mysqli->prepare("
                                                    SELECT id, section_name, sort_order 
                                                    FROM table_sections 
                                                    WHERE tbid = ? 
                                                    ORDER BY sort_order ASC
                                                ");
                                                $sections_stmt->bind_param("i", $tbid);
                                                $sections_stmt->execute();
                                                $sections_result = $sections_stmt->get_result();
                                                
                                                while ($section = $sections_result->fetch_assoc()) {
                                                    echo '<div class="section-card" data-section-id="' . $section['id'] . '">';
                                                    echo '<div class="section-header d-flex justify-content-between align-items-center">';
                                                    echo '<h5 class="mb-0"><i class="fa fa-bars mr-2"></i> ' . htmlspecialchars($section['section_name']) . '</h5>';
                                                    echo '<div>';
                                                    echo '<button type="button" class="btn btn-sm btn-info edit-section mr-1" data-section-id="' . $section['id'] . '" data-section-name="' . htmlspecialchars($section['section_name']) . '">';
                                                    echo '<i class="fa fa-pencil-alt"></i>';
                                                    echo '</button>';
                                                    echo '<button type="button" class="btn btn-sm btn-danger delete-section" data-section-id="' . $section['id'] . '">';
                                                    echo '<i class="fa fa-trash"></i>';
                                                    echo '</button>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                    
                                                    echo '<div class="section-body">';
                                                    echo '<div class="fields-container" data-section-id="' . $section['id'] . '">';
                                                    
                                                    // Get fields for this section
                                                    $fields_stmt = $mysqli->prepare("
                                                        SELECT tab_fields.stid, select_types.str 
                                                        FROM tab_fields 
                                                        JOIN select_types ON tab_fields.stid = select_types.stid 
                                                        WHERE tab_fields.tbid = ? AND tab_fields.section_id = ? 
                                                        ORDER BY tab_fields.sort_order ASC
                                                    ");
                                                    $fields_stmt->bind_param("ii", $tbid, $section['id']);
                                                    $fields_stmt->execute();
                                                    $fields_result = $fields_stmt->get_result();
                                                    
                                                    while ($field = $fields_result->fetch_assoc()) {
                                                        echo '<div class="field-item d-flex justify-content-between align-items-center" data-field-id="' . $field['stid'] . '">';
                                                        echo '<span><i class="fa fa-arrows-alt mr-2"></i> ' . htmlspecialchars($field['str']) . '</span>';
                                                        echo '</div>';
                                                    }
                                                    
                                                    echo '</div>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                }
                                                
                                                $sections_stmt->close();
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="card-title mb-0">Unassigned Categories</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div id="unassigned-fields" class="fields-container" data-section-id="0">
                                                        <?php
                                                        // Get unassigned fields
                                                        $unassigned_stmt = $mysqli->prepare("
                                                            SELECT tab_fields.stid, select_types.str 
                                                            FROM tab_fields 
                                                            JOIN select_types ON tab_fields.stid = select_types.stid 
                                                            WHERE tab_fields.tbid = ? AND (tab_fields.section_id = 0 OR tab_fields.section_id IS NULL) 
                                                            ORDER BY tab_fields.sort_order ASC
                                                        ");
                                                        $unassigned_stmt->bind_param("i", $tbid);
                                                        $unassigned_stmt->execute();
                                                        $unassigned_result = $unassigned_stmt->get_result();
                                                        
                                                        while ($field = $unassigned_result->fetch_assoc()) {
                                                            echo '<div class="field-item d-flex justify-content-between align-items-center" data-field-id="' . $field['stid'] . '">';
                                                            echo '<span><i class="fa fa-arrows-alt mr-2"></i> ' . htmlspecialchars($field['str']) . '</span>';
                                                            echo '</div>';
                                                        }
                                                        
                                                        $unassigned_stmt->close();
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1" role="dialog" aria-labelledby="addSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSectionModalLabel">Add New Section</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addSectionForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="section_name">Section Name</label>
                            <input type="text" class="form-control" id="section_name" name="section_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1" role="dialog" aria-labelledby="editSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editSectionForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_section_id" name="section_id">
                        <div class="form-group">
                            <label for="edit_section_name">Section Name</label>
                            <input type="text" class="form-control" id="edit_section_name" name="section_name" required>
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
    
    <?php include 'incl/adminjs.php' ?>
    
    <script>
        $(document).ready(function() {
            // Make sections sortable
            $("#sections-container").sortable({
                handle: ".section-header",
                placeholder: "section-placeholder",
                update: function(event, ui) {
                    var sections = [];
                    $("#sections-container .section-card").each(function() {
                        sections.push($(this).data("section-id"));
                    });
                    
                    $.ajax({
                        url: "manage_sections.php",
                        type: "POST",
                        data: {
                            action: "update_section_order",
                            tbid: <?php echo $tbid; ?>,
                            sections: sections
                        },
                        dataType: "json",
                        success: function(response) {
                            if (response.status === "success") {
                                Swal.fire({
                                    icon: "success",
                                    title: "Success",
                                    text: response.message,
                                    toast: true,
                                    position: "top-end",
                                    showConfirmButton: false,
                                    timer: 3000
                                });
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "Failed to update section order"
                            });
                        }
                    });
                }
            });
            
            // Make fields sortable and draggable between sections
            $(".fields-container").sortable({
                connectWith: ".fields-container",
                handle: ".fa-arrows-alt",
                placeholder: "field-placeholder",
                update: function(event, ui) {
                    // Only trigger once when the update is complete
                    if (this === ui.item.parent()[0]) {
                        var fieldId = ui.item.data("field-id");
                        var sectionId = $(this).data("section-id");
                        
                        $.ajax({
                            url: "manage_sections.php",
                            type: "POST",
                            data: {
                                action: "assign_field_to_section",
                                tbid: <?php echo $tbid; ?>,
                                field_id: fieldId,
                                section_id: sectionId
                            },
                            dataType: "json",
                            success: function(response) {
                                if (response.status === "success") {
                                    Swal.fire({
                                        icon: "success",
                                        title: "Success",
                                        text: response.message,
                                        toast: true,
                                        position: "top-end",
                                        showConfirmButton: false,
                                        timer: 3000
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: response.message
                                    });
                                    // Revert the change
                                    $(".fields-container").sortable("cancel");
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: "Failed to assign field to section"
                                });
                                // Revert the change
                                $(".fields-container").sortable("cancel");
                            }
                        });
                    }
                }
            });
            
            // Add section form submission
            $("#addSectionForm").on("submit", function(e) {
                e.preventDefault();
                
                var sectionName = $("#section_name").val();
                var sortOrder = $("#sections-container .section-card").length + 1;
                
                $.ajax({
                    url: "manage_sections.php",
                    type: "POST",
                    data: {
                        action: "add_section",
                        tbid: <?php echo $tbid; ?>,
                        section_name: sectionName,
                        sort_order: sortOrder
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.status === "success") {
                            // Create new section HTML
                            var newSectionHtml = `
                                <div class="section-card" data-section-id="${response.section_id}">
                                    <div class="section-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="fa fa-bars mr-2"></i> ${response.section_name}</h5>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-info edit-section mr-1" data-section-id="${response.section_id}" data-section-name="${response.section_name}">
                                                <i class="fa fa-pencil-alt"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger delete-section" data-section-id="${response.section_id}">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="section-body">
                                        <div class="fields-container" data-section-id="${response.section_id}">
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Append new section
                            $("#sections-container").append(newSectionHtml);
                            
                            // Make the new section's fields container sortable
                            $(`[data-section-id="${response.section_id}"].fields-container`).sortable({
                                connectWith: ".fields-container",
                                handle: ".fa-arrows-alt",
                                placeholder: "field-placeholder",
                                update: function(event, ui) {
                                    // Only trigger once when the update is complete
                                    if (this === ui.item.parent()[0]) {
                                        var fieldId = ui.item.data("field-id");
                                        var sectionId = $(this).data("section-id");
                                        
                                        $.ajax({
                                            url: "manage_sections.php",
                                            type: "POST",
                                            data: {
                                                action: "assign_field_to_section",
                                                tbid: <?php echo $tbid; ?>,
                                                field_id: fieldId,
                                                section_id: sectionId
                                            },
                                            dataType: "json",
                                            success: function(response) {
                                                if (response.status === "success") {
                                                    Swal.fire({
                                                        icon: "success",
                                                        title: "Success",
                                                        text: response.message,
                                                        toast: true,
                                                        position: "top-end",
                                                        showConfirmButton: false,
                                                        timer: 3000
                                                    });
                                                } else {
                                                    Swal.fire({
                                                        icon: "error",
                                                        title: "Error",
                                                        text: response.message
                                                    });
                                                    // Revert the change
                                                    $(".fields-container").sortable("cancel");
                                                }
                                            },
                                            error: function() {
                                                Swal.fire({
                                                    icon: "error",
                                                    title: "Error",
                                                    text: "Failed to assign field to section"
                                                });
                                                // Revert the change
                                                $(".fields-container").sortable("cancel");
                                            }
                                        });
                                    }
                                }
                            });
                            
                            // Reset form and close modal
                            $("#section_name").val("");
                            $("#addSectionModal").modal("hide");
                            
                            Swal.fire({
                                icon: "success",
                                title: "Success",
                                text: response.message,
                                toast: true,
                                position: "top-end",
                                showConfirmButton: false,
                                timer: 3000
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Failed to add section"
                        });
                    }
                });
            });
            
            // Edit section button click
            $(document).on("click", ".edit-section", function() {
                var sectionId = $(this).data("section-id");
                var sectionName = $(this).data("section-name");
                
                $("#edit_section_id").val(sectionId);
                $("#edit_section_name").val(sectionName);
                
                $("#editSectionModal").modal("show");
            });
            
            // Edit section form submission
            $("#editSectionForm").on("submit", function(e) {
                e.preventDefault();
                
                var sectionId = $("#edit_section_id").val();
                var sectionName = $("#edit_section_name").val();
                
                $.ajax({
                    url: "manage_sections.php",
                    type: "POST",
                    data: {
                        action: "update_section",
                        tbid: <?php echo $tbid; ?>,
                        section_id: sectionId,
                        section_name: sectionName
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.status === "success") {
                            // Update section name in the UI
                            $(`.section-card[data-section-id="${sectionId}"] .section-header h5`).html(`<i class="fa fa-bars mr-2"></i> ${sectionName}`);
                            $(`.edit-section[data-section-id="${sectionId}"]`).data("section-name", sectionName);
                            
                            // Reset form and close modal
                            $("#editSectionModal").modal("hide");
                            
                            Swal.fire({
                                icon: "success",
                                title: "Success",
                                text: response.message,
                                toast: true,
                                position: "top-end",
                                showConfirmButton: false,
                                timer: 3000
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Failed to update section"
                        });
                    }
                });
            });
            
            // Delete section button click
            $(document).on("click", ".delete-section", function() {
                var sectionId = $(this).data("section-id");
                
                Swal.fire({
                    title: "Are you sure?",
                    text: "This will move all fields in this section to the unassigned list.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",
                    cancelButtonColor: "#3085d6",
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "manage_sections.php",
                            type: "POST",
                            data: {
                                action: "delete_section",
                                tbid: <?php echo $tbid; ?>,
                                section_id: sectionId
                            },
                            dataType: "json",
                            success: function(response) {
                                if (response.status === "success") {
                                    // Remove section from UI
                                    $(`.section-card[data-section-id="${sectionId}"]`).remove();
                                    
                                    // Move fields to unassigned
                                    $(`.field-item[data-section-id="${sectionId}"]`).each(function() {
                                        $(this).attr("data-section-id", "0");
                                        $("#unassigned-fields").append($(this));
                                    });
                                    
                                    Swal.fire({
                                        icon: "success",
                                        title: "Success",
                                        text: response.message,
                                        toast: true,
                                        position: "top-end",
                                        showConfirmButton: false,
                                        timer: 3000
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: response.message
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: "Failed to delete section"
                                });
                            }
                        });
                    }
                });
            });
            
            // Update section order
            $("#sections-container").sortable({
                handle: ".fa-bars",
                update: function(event, ui) {
                    var sectionOrder = [];
                    $(".section-card").each(function(index) {
                        sectionOrder.push({
                            id: $(this).data("section-id"),
                            order: index + 1
                        });
                    });
                    
                    $.ajax({
                        url: "manage_sections.php",
                        type: "POST",
                        data: {
                            action: "update_section_order",
                            tbid: <?php echo $tbid; ?>,
                            section_order: sectionOrder
                        },
                        dataType: "json",
                        success: function(response) {
                            if (response.status === "success") {
                                Swal.fire({
                                    icon: "success",
                                    title: "Success",
                                    text: response.message,
                                    toast: true,
                                    position: "top-end",
                                    showConfirmButton: false,
                                    timer: 3000
                                });
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "Failed to update section order"
                            });
                        }
                    });
                }
            });
            
            // Make fields sortable within and between sections
            $(".fields-container").sortable({
                connectWith: ".fields-container",
                handle: ".fa-arrows-alt",
                placeholder: "field-placeholder",
                update: function(event, ui) {
                    // Only trigger once when the update is complete
                    if (this === ui.item.parent()[0]) {
                        var fieldId = ui.item.data("field-id");
                        var sectionId = $(this).data("section-id");
                        
                        $.ajax({
                            url: "manage_sections.php",
                            type: "POST",
                            data: {
                                action: "assign_field_to_section",
                                tbid: <?php echo $tbid; ?>,
                                field_id: fieldId,
                                section_id: sectionId
                            },
                            dataType: "json",
                            success: function(response) {
                                if (response.status === "success") {
                                    Swal.fire({
                                        icon: "success",
                                        title: "Success",
                                        text: response.message,
                                        toast: true,
                                        position: "top-end",
                                        showConfirmButton: false,
                                        timer: 3000
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: response.message
                                    });
                                    // Revert the change
                                    $(".fields-container").sortable("cancel");
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: "Failed to assign field to section"
                                });
                                // Revert the change
                                $(".fields-container").sortable("cancel");
                            }
                        });
                    }
                }
            });
        });
    </script>
</div>
<?php include 'incl/footer.php'; ?>
</body>
</html>
