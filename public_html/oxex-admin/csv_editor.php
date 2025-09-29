<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check user permissions
if (!login_check($mysqli) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    die("Unauthorized Access");
}

// CSV Template Management
$action = isset($_GET['action']) ? $_GET['action'] : '';
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

// Check and create CSV templates table if not exists
$check_templates_table_query = "SHOW TABLES LIKE 'csv_templates'";
$templates_table_result = $mysqli->query($check_templates_table_query);

if ($templates_table_result->num_rows == 0) {
    // Table doesn't exist, attempt to create
    $create_templates_table_sql = "
    CREATE TABLE csv_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(255) NOT NULL,
        description TEXT,
        created_by VARCHAR(100),
        delimiter VARCHAR(10) DEFAULT ',',
        enclosure VARCHAR(10) DEFAULT '\"',
        header_row BOOLEAN DEFAULT TRUE,
        max_rows INT DEFAULT 1000,
        export_type ENUM('download', 'save') DEFAULT 'download',
        date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE
    )";
    
    if (!$mysqli->query($create_templates_table_sql)) {
        die("Error creating csv_templates table: " . $mysqli->error);
    }
}

// Check and create CSV template columns table if not exists
$check_columns_table_query = "SHOW TABLES LIKE 'csv_template_columns'";
$columns_table_result = $mysqli->query($check_columns_table_query);

if ($columns_table_result->num_rows == 0) {
    // Table doesn't exist, attempt to create
    $create_columns_table_sql = "
    CREATE TABLE csv_template_columns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        table_id INT NOT NULL,
        field_id INT NOT NULL,
        display_order INT DEFAULT 0
    )";
    
    if (!$mysqli->query($create_columns_table_sql)) {
        die("Error creating csv_template_columns table: " . $mysqli->error);
    }
}

// Fetch existing CSV templates
$templates = [];
try {
    $stmt = $mysqli->prepare("SELECT id, template_name, description, created_by, date_created FROM csv_templates ORDER BY date_created DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // Log the error and continue with an empty templates array
    error_log("CSV Templates Query Error: " . $e->getMessage());
}

$current_template = null;
if ($template_id > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM csv_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_template = $result->fetch_assoc();
    $stmt->close();
}

// Fetch available tables
$tables_query = "SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name";
$tables_result = $mysqli->query($tables_query);
$tables = [];
while ($row = $tables_result->fetch_assoc()) {
    $tables[] = $row;
}

// Fetch fields for a specific table (to be used in AJAX)
function getFieldsForTable($mysqli, $tbid) {
    $stmt = $mysqli->prepare("SELECT st.stid, st.str 
                             FROM tab_fields tf 
                             JOIN select_types st ON tf.stid = st.stid 
                             WHERE tf.tbid = ? 
                             ORDER BY tf.sort_order ASC");
    $stmt->bind_param("i", $tbid);
    $stmt->execute();
    $result = $stmt->get_result();
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        $fields[] = $row;
    }
    $stmt->close();
    return $fields;
}

// Fetch existing columns for the current template
$existing_columns = [];
if ($current_template) {
    // First, let's check if the table has the expected columns
    $check_columns_structure = $mysqli->query("DESCRIBE csv_template_columns");
    $columns_exist = false;
    $column_names = [];
    
    if ($check_columns_structure) {
        while ($col = $check_columns_structure->fetch_assoc()) {
            $column_names[] = $col['Field'];
        }
        
        // Check if all required columns exist
        $required_columns = ['id', 'template_id', 'table_id', 'field_id', 'display_order'];
        $columns_exist = true;
        foreach ($required_columns as $col) {
            if (!in_array($col, $column_names)) {
                $columns_exist = false;
                break;
            }
        }
    }
    
    if ($columns_exist) {
        // Columns exist, proceed with the query
    $columns_stmt = $mysqli->prepare("
        SELECT 
            ctc.id, 
            ctc.table_id, 
            ctc.field_id, 
                tt.tab_name AS table_name, 
            st.str AS field_name 
        FROM csv_template_columns ctc
            JOIN tabs_tbl tt ON ctc.table_id = tt.tbid
        JOIN select_types st ON ctc.field_id = st.stid
        WHERE ctc.template_id = ?
        ORDER BY ctc.display_order
    ");
        
        if ($columns_stmt) {
    $columns_stmt->bind_param("i", $current_template['id']);
    $columns_stmt->execute();
    $columns_result = $columns_stmt->get_result();
    
    while ($row = $columns_result->fetch_assoc()) {
        $existing_columns[] = $row;
    }
    $columns_stmt->close();
        } else {
            // Failed to prepare statement, log the error
            error_log("CSV Editor - Error preparing columns query: " . $mysqli->error);
        }
    } else {
        // Table doesn't have the expected structure, recreate it
        $mysqli->query("DROP TABLE IF EXISTS csv_template_columns");
        
        $create_columns_table_sql = "
        CREATE TABLE csv_template_columns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_id INT NOT NULL,
            table_id INT NOT NULL,
            field_id INT NOT NULL,
            display_order INT DEFAULT 0
        )";
        
        if (!$mysqli->query($create_columns_table_sql)) {
            error_log("CSV Editor - Error recreating csv_template_columns table: " . $mysqli->error);
        }
    }
}

$pagetitle = "CSV Editor";
$subtitle = "Manage CSV Templates";

// Fix for session timeout issue by providing the correct login path
// This script will be included properly in the head section
$session_timeout_js = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $pagetitle; ?> - <?php echo $adminname; ?></title>
    <?php include 'incl/admincss.php'; ?>
    
    <!-- Session timeout handler -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if jQuery is loaded
        if (typeof jQuery !== 'undefined') {
            // Check for session timeout errors
            function checkSessionTimeout() {
                if (window.location.href.indexOf('error=session_incomplete') > -1) {
                    window.location.href = '../../public_html/login.php';
                }
            }

            // Run on page load
            checkSessionTimeout();

            // Add global AJAX error handler for 403 status (session expired)
            jQuery(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
                if (jqXHR.status === 403) {
                    window.location.href = '../../public_html/login.php';
                }
            });
        } else {
            console.error('jQuery is not loaded. Session timeout handling is disabled.');
        }
    });
    </script>
    
    <style>
        .template-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .template-card:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        #csvEditorGrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        .field-type-select {
            width: 100%;
        }
    </style>
</head>
<body class="wrapper">
    <?php 
    include 'incl/topbar.php';
    include 'incl/sidebar.php';
    include 'incl/offsidebar.php';
    ?>

    <section class="section-container">
        <div class="content-wrapper">
            <div class="content-header">
                <div class="content-title">
                    <?php echo $pagetitle; ?> 
                    <a href="#newTemplateModal" class="btn btn-sm btn-info ml-3" data-toggle="modal">
                        Create New Template
                    </a>
                    <small><?php echo $subtitle; ?></small>
                </div>
            </div>

            <!-- Database Tables and Fields Section -->
            <div class="container mt-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>Database Tables and Categories</h4>
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#selectTableModal">
                            <i class="fa fa-plus"></i> Add Existing Table to Template
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Categories</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Get all tables
                                    $tables_query = "SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name";
                                    $tables_result = $mysqli->query($tables_query);
                                    
                                    while ($table = $tables_result->fetch_assoc()): 
                                        // Get fields for each table
                                        $fields_query = "SELECT st.stid, st.str 
                                                      FROM tab_fields tf 
                                                      JOIN select_types st ON tf.stid = st.stid 
                                                      WHERE tf.tbid = ? 
                                                      ORDER BY tf.sort_order ASC";
                                        $fields_stmt = $mysqli->prepare($fields_query);
                                        $fields_stmt->bind_param("i", $table['tbid']);
                                        $fields_stmt->execute();
                                        $fields_result = $fields_stmt->get_result();
                                        $fields = [];
                                        while ($field = $fields_result->fetch_assoc()) {
                                            $fields[] = $field;
                                        }
                                        $fields_stmt->close();
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($table['tab_name']); ?></td>
                                        <td>
                                            <ul class="list-group">
                                                <?php foreach ($fields as $field): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><?php echo htmlspecialchars($field['str']); ?></span>
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-success add-field-to-template-btn" 
                                                                   data-field-id="<?php echo $field['stid']; ?>" 
                                                                   data-field-name="<?php echo htmlspecialchars($field['str']); ?>"
                                                                   data-table-id="<?php echo $table['tbid']; ?>"
                                                                   data-table-name="<?php echo htmlspecialchars($table['tab_name']); ?>">
                                                                <i class="fa fa-plus"></i> Add to Template
                                                            </button>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-primary add-table-to-template-btn" 
                                                       data-id="<?php echo $table['tbid']; ?>" 
                                                       data-name="<?php echo htmlspecialchars($table['tab_name']); ?>">
                                                    <i class="fa fa-plus"></i> Add to Template
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <h4>Available Templates</h4>
                    <div id="csvEditorGrid">
                        <?php foreach ($templates as $template): ?>
                            <div class="card template-card" onclick="location.href='csv_editor.php?template_id=<?php echo $template['id']; ?>'">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($template['template_name']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($template['description']); ?></p>
                                    <small class="text-muted">
                                        Created by <?php echo htmlspecialchars($template['created_by']); ?> 
                                        on <?php echo date('d M Y', strtotime($template['date_created'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>CSV Template Tables and Categories</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Table Name</th>
                                            <th>Categories</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="template-tables-body">
                                        <?php if (!empty($existing_columns)): 
                                            // Group columns by table
                                            $grouped_columns = [];
                                            foreach ($existing_columns as $column) {
                                                if (!isset($grouped_columns[$column['table_id']])) {
                                                    $grouped_columns[$column['table_id']] = [
                                                        'table_id' => $column['table_id'],
                                                        'table_name' => $column['table_name'],
                                                        'fields' => []
                                                    ];
                                                }
                                                $grouped_columns[$column['table_id']]['fields'][] = [
                                                    'field_id' => $column['field_id'],
                                                    'field_name' => $column['field_name']
                                                ];
                                            }
                                            
                                            // Display grouped columns
                                            foreach ($grouped_columns as $table):
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($table['table_name']); ?></td>
                                            <td>
                                                <div class="field-list pl-3">
                                                    <?php foreach ($table['fields'] as $field): ?>
                                                        <div class="field-item d-flex justify-content-between align-items-center mb-2">
                                                            <span><?php echo htmlspecialchars($field['field_name']); ?></span>
                                                            <input type="hidden" name="table[]" value="<?php echo $table['table_id']; ?>">
                                                            <input type="hidden" name="field[]" value="<?php echo $field['field_id']; ?>">
                                                            <button type="button" class="btn btn-sm btn-danger remove-field-btn" 
                                                                data-table-id="<?php echo $table['table_id']; ?>" 
                                                                data-field-id="<?php echo $field['field_id']; ?>">
                                                                <i class="fa fa-times"></i> Remove
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger remove-table-from-template-btn" 
                                                       data-table-id="<?php echo $table['table_id']; ?>"
                                                       data-template-id="<?php echo $template_id; ?>">
                                                    <i class="fa fa-trash"></i> Remove from Template
                                                </button>
                                            </td>
                                        </tr>
                                        <?php 
                                            endforeach;
                                        endif; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($current_template): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4><?php echo htmlspecialchars($current_template['template_name']); ?> - CSV Template Editor</h4>
                        </div>
                        <div class="card-body">
                            <div class="container">
                                <h2><?php echo $current_template['template_name'] ? 'Edit CSV Template' : 'Create New CSV Template'; ?></h2>
                                
                                <form id="csv-template-form" method="POST">
                                    <div class="form-group">
                                        <label for="template-name">Template Name</label>
                                        <input type="text" class="form-control" id="template-name" name="template_name" 
                                               value="<?php echo htmlspecialchars($current_template['template_name']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                            echo htmlspecialchars($current_template['description']); 
                                        ?></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label>Columns Configuration</label>
                                        <div id="columns-container">
                                            <?php if (!empty($existing_columns)): 
                                                // Group columns by table
                                                $grouped_columns = [];
                                                foreach ($existing_columns as $column) {
                                                    if (!isset($grouped_columns[$column['table_id']])) {
                                                        $grouped_columns[$column['table_id']] = [
                                                            'table_id' => $column['table_id'],
                                                            'table_name' => $column['table_name'],
                                                            'fields' => []
                                                        ];
                                                    }
                                                    $grouped_columns[$column['table_id']]['fields'][] = [
                                                        'field_id' => $column['field_id'],
                                                        'field_name' => $column['field_name']
                                                    ];
                                                }
                                                
                                                // Display grouped columns
                                                foreach ($grouped_columns as $table):
                                            ?>
                                                <div class="table-group mb-3 border p-3 rounded">
                                                    <h5 class="mb-2"><?php echo htmlspecialchars($table['table_name']); ?></h5>
                                                    <div class="field-list pl-3">
                                                        <?php foreach ($table['fields'] as $field): ?>
                                                            <div class="field-item d-flex justify-content-between align-items-center mb-2">
                                                                <span><?php echo htmlspecialchars($field['field_name']); ?></span>
                                                                <input type="hidden" name="table[]" value="<?php echo $table['table_id']; ?>">
                                                                <input type="hidden" name="field[]" value="<?php echo $field['field_id']; ?>">
                                                                <button type="button" class="btn btn-sm btn-danger remove-field-btn" 
                                                                    data-table-id="<?php echo $table['table_id']; ?>" 
                                                                    data-field-id="<?php echo $field['field_id']; ?>">
                                                                    <i class="fa fa-times"></i> Remove
                                                                </button>
                                                    </div>
                                                    <?php endforeach; ?>
                                            </div>
                                            </div>
                                        <?php 
                                            endforeach;
                                        endif; 
                                        ?>
                                    </div>
                                    <button type="button" class="btn btn-success mt-2" data-toggle="modal" data-target="#selectTableModal">Add Categories to Template</button>
                                </div>

                                <div class="form-group">
                                    <label for="delimiter">CSV Delimiter</label>
                                    <select class="form-control" id="delimiter" name="delimiter">
                                        <option value="," <?php echo (isset($current_template['delimiter']) && $current_template['delimiter'] == ',') ? 'selected' : ''; ?>>Comma (,)</option>
                                        <option value=";" <?php echo (isset($current_template['delimiter']) && $current_template['delimiter'] == ';') ? 'selected' : ''; ?>>Semicolon (;)</option>
                                        <option value="\t" <?php echo (isset($current_template['delimiter']) && $current_template['delimiter'] == "\t") ? 'selected' : ''; ?>>Tab (\t)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="enclosure">Text Enclosure</label>
                                    <select class="form-control" id="enclosure" name="enclosure">
                                        <option value='"' <?php echo (isset($current_template['enclosure']) && $current_template['enclosure'] == '"') ? 'selected' : ''; ?>>Double Quote (")</option>
                                        <option value="'" <?php echo (isset($current_template['enclosure']) && $current_template['enclosure'] == "'") ? 'selected' : ''; ?>>Single Quote (')</option>
                                        <option value="" <?php echo (isset($current_template['enclosure']) && empty($current_template['enclosure'])) ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="header-row">Header Row</label>
                                    <select class="form-control" id="header-row" name="header_row">
                                        <option value="1" <?php echo (isset($current_template['header_row']) && $current_template['header_row'] == 1) ? 'selected' : ''; ?>>Yes</option>
                                        <option value="0" <?php echo (isset($current_template['header_row']) && $current_template['header_row'] == 0) ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="max-rows">Maximum Rows to Export</label>
                                    <input type="number" class="form-control" id="max-rows" name="max_rows" 
                                           value="<?php echo isset($current_template['max_rows']) ? $current_template['max_rows'] : 1000; ?>" min="1" max="100000">
                                </div>

                                <div class="form-group">
                                    <label for="export-type">Export Type</label>
                                    <select class="form-control" id="export-type" name="export_type">
                                        <option value="download" <?php echo (isset($current_template['export_type']) && $current_template['export_type'] == 'download') ? 'selected' : ''; ?>>Download</option>
                                        <option value="save" <?php echo (isset($current_template['export_type']) && $current_template['export_type'] == 'save') ? 'selected' : ''; ?>>Save to Server</option>
                                    </select>
                                </div>

                                <?php if ($template_id): ?>
                                    <input type="hidden" name="template_id" value="<?php echo $template_id; ?>">
                                <?php endif; ?>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Template</button>
                                    <?php if ($template_id): ?>
                                        <button type="button" id="delete-template-btn" class="btn btn-danger">Delete Template</button>
                                    <?php endif; ?>
                                    <a href="csv_templates.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- New Template Modal -->
    <div class="modal fade" id="newTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New CSV Template</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="newTemplateForm">
                        <div class="form-group">
                            <label>Template Name</label>
                            <input type="text" class="form-control" name="template_name" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" class="form-control" name="description">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="createTemplateBtn">Create Template</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Select Table Modal -->
    <div class="modal fade" id="selectTableModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Table to Add to Template</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Table</label>
                        <select class="form-control" id="select-table-dropdown">
                            <option value="">-- Select a Table --</option>
                            <?php
                            // Get all tables again for the dropdown
                            $tables_query = "SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name";
                            $tables_result = $mysqli->query($tables_query);
                            
                            while ($table = $tables_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $table['tbid']; ?>" 
                                        data-name="<?php echo htmlspecialchars($table['tab_name']); ?>">
                                    <?php echo htmlspecialchars($table['tab_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="add-selected-table-btn">Add Table to Template</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Select Field Modal -->
    <div class="modal fade" id="selectFieldModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Categories for <span id="selectedTableName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="selected-table-id">
                    <div id="field-selection-container">
                        <!-- Fields will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="add-selected-fields-btn">Add Selected Categories</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Delete CSV Template</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this template? This action cannot be undone.</p>
                    <p><strong>Warning:</strong> This will permanently delete the template and all its field configurations.</p>
                    <input type="hidden" id="deleteTemplateId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteTemplateBtn">Delete Template</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Item Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Confirm Removal</h5>
                    <button type="button" class="close text-dark" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="deleteConfirmMessage">Are you sure you want to remove this item from the template?</p>
                    <input type="hidden" id="deleteItemId">
                    <input type="hidden" id="deleteTableId">
                    <input type="hidden" id="deleteTemplateId">
                    <input type="hidden" id="deleteItemType">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmDeleteItemBtn">Remove Item</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'incl/adminjs.php'; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Safe element selection function
        function safeSelect(selector) {
            const element = document.querySelector(selector);
            if (!element) {
                console.warn(`Element not found: ${selector}`);
            }
            return element;
        }

        // Table and column configuration
        const columnsContainer = safeSelect('#columns-container');
        const addColumnBtn = safeSelect('#add-column-btn');
        const csvTemplateForm = safeSelect('#csv-template-form');

        // Function to fetch table fields dynamically
        function fetchTableFields(tableSelect, fieldSelect) {
            if (!tableSelect || !fieldSelect) return;

            const tableId = tableSelect.value;
            const loadingOption = document.createElement('option');
            loadingOption.text = 'Loading fields...';
            loadingOption.disabled = true;
            loadingOption.selected = true;

            // Clear existing options and add loading indicator
            fieldSelect.innerHTML = '';
            fieldSelect.appendChild(loadingOption);

            // Fetch fields for the selected table
            fetch(`ajax/get_table_fields.php?table_id=${tableId}`)
                .then(response => {
                    if (!response.ok) {
                        // Throw an error with the status
                        return response.text().then(text => {
                            throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    // Clear previous options
                    fieldSelect.innerHTML = '';

                    // Add default option
                    const defaultOption = document.createElement('option');
                    defaultOption.text = 'Select a Field';
                    defaultOption.value = '';
                    fieldSelect.appendChild(defaultOption);

                    // Populate fields
                    const fields = data.fields || [];
                    
                    if (fields.length > 0) {
                        fields.forEach(field => {
                            const option = document.createElement('option');
                            option.value = field.stid;
                            option.text = field.str;
                            fieldSelect.appendChild(option);
                        });
                    } else {
                        const noFieldsMsg = document.createElement('p');
                        noFieldsMsg.className = 'text-danger';
                        noFieldsMsg.textContent = 'No fields available for this table. Please check the table configuration.';
                        fieldSelect.appendChild(noFieldsMsg);
                    }
                })
                .catch(error => {
                    console.error('Error fetching fields:', error);
                    
                    // Create an error message element
                    const fieldsContainer = document.getElementById('field-selection-container');
                    fieldsContainer.innerHTML = '';
                    
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'alert alert-danger';
                    errorMsg.innerHTML = `
                        <strong>Error Loading Categories</strong>
                        <p>${error.message}</p>
                        <small>Please contact support or check the server logs.</small>
                    `;
                    
                    fieldsContainer.appendChild(errorMsg);
                    
                    // Show the modal with the error message
                    $('#selectFieldModal').modal('show');
                });
        }

        // Function to create a new column configuration row
        function createColumnConfigRow(tables = []) {
            const columnConfig = document.createElement('div');
            columnConfig.className = 'column-config';

            // Table select
            const tableSelect = document.createElement('select');
            tableSelect.className = 'form-control table-select';
            tableSelect.name = 'table[]';

            // Default option
            const defaultTableOption = document.createElement('option');
            defaultTableOption.text = 'Select a Table';
            defaultTableOption.value = '';
            tableSelect.appendChild(defaultTableOption);

            // Populate tables
            tables.forEach(table => {
                const option = document.createElement('option');
                option.value = table.tbid;
                option.text = table.tab_name;
                tableSelect.appendChild(option);
            });

            // Field select
            const fieldSelect = document.createElement('select');
            fieldSelect.className = 'form-control field-select';
            fieldSelect.name = 'field[]';

            const defaultFieldOption = document.createElement('option');
            defaultFieldOption.text = 'Select a Field';
            defaultFieldOption.value = '';
            fieldSelect.appendChild(defaultFieldOption);

            // Remove column button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-danger remove-column';
            removeBtn.textContent = 'Remove';

            // Event listener for table selection to fetch fields
            tableSelect.addEventListener('change', () => {
                fetchTableFields(tableSelect, fieldSelect);
            });

            // Event listener for remove button
            removeBtn.addEventListener('click', () => {
                columnConfig.remove();
            });

            // Append elements
            columnConfig.appendChild(tableSelect);
            columnConfig.appendChild(fieldSelect);
            columnConfig.appendChild(removeBtn);

            return columnConfig;
        }

        // Add column button event listener
        if (addColumnBtn && columnsContainer) {
            addColumnBtn.addEventListener('click', () => {
                // Fetch tables via AJAX to populate the new row
                fetch('ajax/get_tables.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(tables => {
                        const newColumnRow = createColumnConfigRow(tables);
                        columnsContainer.appendChild(newColumnRow);
                    })
                    .catch(error => {
                        console.error('Error fetching tables:', error);
                        alert('Failed to load tables. Please try again.');
                    });
            });
        }

        // Form submission handler
        if (csvTemplateForm) {
            csvTemplateForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Prevent default form submission

                const tableInputs = document.querySelectorAll('input[name="table[]"]');
                const fieldInputs = document.querySelectorAll('input[name="field[]"]');

                // Validate columns
                let isValid = true;
                const columns = [];
                    
                if (tableInputs.length === 0) {
                    alert('Please add at least one field to the template.');
                        return;
                    }

                for (let i = 0; i < tableInputs.length; i++) {
                    const tableId = tableInputs[i].value;
                    const fieldId = fieldInputs[i].value;

                    columns.push({
                        table_id: parseInt(tableId),
                        field_id: parseInt(fieldId)
                    });
                }

                // Prepare template data
                const templateData = {
                    template_id: document.querySelector('input[name="template_id"]')?.value || null,
                    template_name: document.getElementById('template-name').value,
                    description: document.getElementById('description').value,
                    columns: columns,
                    delimiter: document.getElementById('delimiter').value,
                    enclosure: document.getElementById('enclosure').value,
                    header_row: parseInt(document.getElementById('header-row').value),
                    max_rows: parseInt(document.getElementById('max-rows').value),
                    export_type: document.getElementById('export-type').value
                };

                // Send AJAX request to save template
                fetch('ajax/save_csv_template.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(templateData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Show success message
                        alert('Template saved successfully!');
                        
                        // Redirect or update page as needed
                        window.location.href = `csv_editor.php?template_id=${data.template_id}`;
                    } else {
                        // Show error message
                        alert(`Error: ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the template.');
                });
            });
        }

        // New Template Modal Creation
        const createTemplateBtn = document.getElementById('createTemplateBtn');
        const newTemplateForm = document.getElementById('newTemplateForm');

        if (createTemplateBtn && newTemplateForm) {
            createTemplateBtn.addEventListener('click', function() {
                // Validate form
                const templateName = newTemplateForm.querySelector('input[name="template_name"]').value.trim();
                
                if (!templateName) {
                    alert('Please enter a template name');
                    return;
                }

                // Prepare data - simplified to match our database structure
                const templateData = {
                    template_name: templateName,
                    description: newTemplateForm.querySelector('input[name="description"]').value.trim() || ''
                };

                // Send AJAX request
                fetch('ajax/create_csv_template.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(templateData)
                })
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 403) {
                            // Session timeout - fix path to login.php
                            window.location.href = '../../public_html/login.php';
                            throw new Error('Session expired. Please log in again.');
                        }
                        return response.text().then(text => {
                            throw new Error('Server error: ' + text);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Redirect to the new template's edit page
                        window.location.href = `csv_editor.php?template_id=${data.template_id}`;
                    } else {
                        // Show error message
                        alert(`Error: ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while creating the template: ' + error.message);
                });
                
                // Hide the modal
                $('#newTemplateModal').modal('hide');
            });
        }

        // Add table to template
        document.querySelectorAll('.add-table-to-template-btn').forEach(button => {
            button.addEventListener('click', function() {
                const tableId = this.getAttribute('data-id');
                const tableName = this.getAttribute('data-name');
                
                // If we have a current template, add this table to it
                <?php if ($template_id): ?>
                
                // Show the field selection modal
                document.getElementById('selectedTableName').textContent = tableName;
                document.getElementById('selected-table-id').value = tableId;
                
                // Fetch and display the fields for this table
                fetch(`ajax/get_table_fields.php?table_id=${tableId}`)
                    .then(response => {
                        if (!response.ok) {
                            // Throw an error with the status
                            return response.text().then(text => {
                                throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        const fieldsContainer = document.getElementById('field-selection-container');
                        fieldsContainer.innerHTML = '';
                        
                        // Check if the response has a status and fields
                        if (data.status !== 'success') {
                            throw new Error(data.message || 'Unknown error fetching fields');
                        }
                        
                        const fields = data.fields || [];
                        
                        if (fields.length > 0) {
                            const fieldsList = document.createElement('div');
                            fieldsList.className = 'list-group';
                            
                            fields.forEach(field => {
                                const fieldItem = document.createElement('div');
                                fieldItem.className = 'list-group-item';
                                
                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.className = 'mr-2 field-checkbox';
                                checkbox.value = field.stid;
                                checkbox.setAttribute('data-name', field.str);
                                
                                const label = document.createElement('label');
                                label.className = 'form-check-label';
                                label.appendChild(checkbox);
                                label.append(field.str);
                                
                                fieldItem.appendChild(label);
                                fieldsList.appendChild(fieldItem);
                            });
                            
                            fieldsContainer.appendChild(fieldsList);
                        } else {
                            const noFieldsMsg = document.createElement('p');
                            noFieldsMsg.className = 'text-danger';
                            noFieldsMsg.textContent = 'No fields available for this table. Please check the table configuration.';
                            fieldsContainer.appendChild(noFieldsMsg);
                        }
                        
                        $('#selectFieldModal').modal('show');
                    })
                    .catch(error => {
                        console.error('Error fetching fields:', error);
                        
                        // Create an error message element
                        const fieldsContainer = document.getElementById('field-selection-container');
                        fieldsContainer.innerHTML = '';
                        
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'alert alert-danger';
                        errorMsg.innerHTML = `
                            <strong>Error Loading Categories</strong>
                            <p>${error.message}</p>
                            <small>Please contact support or check the server logs.</small>
                        `;
                        
                        fieldsContainer.appendChild(errorMsg);
                        
                        // Show the modal with the error message
                        $('#selectFieldModal').modal('show');
                    });
                
                <?php else: ?>
                alert('Please create or select a template first.');
                <?php endif; ?>
            });
        });
        
        // Add selected fields to template
        document.getElementById('add-selected-fields-btn')?.addEventListener('click', function() {
            const tableId = document.getElementById('selected-table-id').value;
            const checkedFields = document.querySelectorAll('.field-checkbox:checked');
            
            if (checkedFields.length === 0) {
                alert('Please select at least one field.');
                return;
            }
            
            const selectedFields = Array.from(checkedFields).map(checkbox => ({
                field_id: checkbox.value,
                field_name: checkbox.getAttribute('data-name')
            }));
            
            // Send to server
            fetch('ajax/add_fields_to_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    template_id: <?php echo $template_id ?: 0; ?>,
                    table_id: tableId,
                    fields: selectedFields
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Categories added to template successfully!');
                    location.reload();
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding categories to the template.');
            });
            
            $('#selectFieldModal').modal('hide');
        });
        
        // Add a field directly to template
        document.querySelectorAll('.add-field-to-template-btn').forEach(button => {
            button.addEventListener('click', function() {
                const fieldId = this.getAttribute('data-field-id');
                const fieldName = this.getAttribute('data-field-name');
                const tableId = this.getAttribute('data-table-id');
                const tableName = this.getAttribute('data-table-name');
                
                <?php if ($template_id): ?>
                // Add this field to the template
                fetch('ajax/add_field_to_template.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        template_id: <?php echo $template_id; ?>,
                        table_id: tableId,
                        field_id: fieldId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(`Field "${fieldName}" from table "${tableName}" added to template successfully!`);
                        location.reload();
                    } else {
                        alert(`Error: ${data.message}`);
                    }
            })
            .catch(error => {
                console.error('Error:', error);
                    alert('An error occurred while adding the field to the template.');
                });
                <?php else: ?>
                alert('Please create or select a template first.');
                <?php endif; ?>
            });
        });

        // Remove field from template
        const removeFieldButtons = document.querySelectorAll('.remove-field-btn');
        console.log('Found', removeFieldButtons.length, 'remove field buttons');
        
        // Check if modal exists
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        console.log('Delete confirm modal element:', deleteConfirmModal);
        
        // Check if Bootstrap is available
        if (typeof $ !== 'undefined' && $.fn && $.fn.modal) {
            console.log('Bootstrap modal is available');
        } else {
            console.error('Bootstrap modal is NOT available');
        }
        
        removeFieldButtons.forEach(button => {
            console.log('Adding event listener to remove field button:', button);
            button.addEventListener('click', function() {
                console.log('Remove field button clicked');
                const fieldId = this.getAttribute('data-field-id');
                const tableId = this.getAttribute('data-table-id');
                
                console.log('Field ID:', fieldId, 'Table ID:', tableId);
                
                document.getElementById('deleteItemId').value = fieldId;
                document.getElementById('deleteTableId').value = tableId;
                document.getElementById('deleteTemplateId').value = <?php echo $template_id ?: 0; ?>;
                document.getElementById('deleteItemType').value = 'field';
                document.getElementById('deleteConfirmMessage').textContent = 'Are you sure you want to remove this field from the template?';
                
                $('#deleteConfirmModal').modal('show');
            });
        });
        
        // Remove table from template
        const removeTableButtons = document.querySelectorAll('.remove-table-from-template-btn');
        console.log('Found', removeTableButtons.length, 'remove table buttons');
        removeTableButtons.forEach(button => {
            console.log('Adding event listener to remove table button:', button);
            button.addEventListener('click', function() {
                console.log('Remove table button clicked');
                const tableId = this.getAttribute('data-table-id');
                const templateId = this.getAttribute('data-template-id');
                
                console.log('Table ID:', tableId, 'Template ID:', templateId);
                
                document.getElementById('deleteTableId').value = tableId;
                document.getElementById('deleteTemplateId').value = templateId;
                document.getElementById('deleteItemType').value = 'table';
                document.getElementById('deleteConfirmMessage').textContent = 'Are you sure you want to remove this table and all its fields from the template?';
                
                $('#deleteConfirmModal').modal('show');
                console.log('Modal should be shown now');
            });
        });
        
        // Add event delegation as fallback for dynamically added buttons
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-field-btn') || event.target.closest('.remove-field-btn')) {
                const button = event.target.classList.contains('remove-field-btn') ? event.target : event.target.closest('.remove-field-btn');
                console.log('Event delegation: Remove field button clicked');
                
                const fieldId = button.getAttribute('data-field-id');
                const tableId = button.getAttribute('data-table-id');
                
                console.log('Field ID:', fieldId, 'Table ID:', tableId);
                
                document.getElementById('deleteItemId').value = fieldId;
                document.getElementById('deleteTableId').value = tableId;
                document.getElementById('deleteTemplateId').value = <?php echo $template_id ?: 0; ?>;
                document.getElementById('deleteItemType').value = 'field';
                document.getElementById('deleteConfirmMessage').textContent = 'Are you sure you want to remove this field from the template?';
                
                $('#deleteConfirmModal').modal('show');
                console.log('Modal should be shown now (event delegation)');
            }
            
            if (event.target.classList.contains('remove-table-from-template-btn') || event.target.closest('.remove-table-from-template-btn')) {
                const button = event.target.classList.contains('remove-table-from-template-btn') ? event.target : event.target.closest('.remove-table-from-template-btn');
                console.log('Event delegation: Remove table button clicked');
                
                const tableId = button.getAttribute('data-table-id');
                const templateId = button.getAttribute('data-template-id');
                
                console.log('Table ID:', tableId, 'Template ID:', templateId);
                
                document.getElementById('deleteTableId').value = tableId;
                document.getElementById('deleteTemplateId').value = templateId;
                document.getElementById('deleteItemType').value = 'table';
                document.getElementById('deleteConfirmMessage').textContent = 'Are you sure you want to remove this table and all its fields from the template?';
                
                $('#deleteConfirmModal').modal('show');
                console.log('Modal should be shown now (event delegation)');
            }
        });
        
        // Add selected table from dropdown
        document.getElementById('add-selected-table-btn')?.addEventListener('click', function() {
            const tableSelect = document.getElementById('select-table-dropdown');
            const tableId = tableSelect.value;
            
            if (!tableId) {
                alert('Please select a table.');
                return;
            }
            
            // Get table name from the selected option
            const selectedOption = tableSelect.options[tableSelect.selectedIndex];
            const tableName = selectedOption.getAttribute('data-name');
            
            document.getElementById('selectedTableName').textContent = tableName;
            document.getElementById('selected-table-id').value = tableId;
            
            // Fetch and display the fields for this table
            fetch(`ajax/get_table_fields.php?table_id=${tableId}`)
                .then(response => {
                    if (!response.ok) {
                        // Throw an error with the status
                        return response.text().then(text => {
                            throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    const fieldsContainer = document.getElementById('field-selection-container');
                    fieldsContainer.innerHTML = '';
                    
                    // Check if the response has a status and fields
                    if (data.status !== 'success') {
                        throw new Error(data.message || 'Unknown error fetching fields');
                    }
                    
                    const fields = data.fields || [];
                    
                    if (fields.length > 0) {
                        const fieldsList = document.createElement('div');
                        fieldsList.className = 'list-group';
                        
                        fields.forEach(field => {
                            const fieldItem = document.createElement('div');
                            fieldItem.className = 'list-group-item';
                            
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.className = 'mr-2 field-checkbox';
                            checkbox.value = field.stid;
                            checkbox.setAttribute('data-name', field.str);
                            
                            const label = document.createElement('label');
                            label.className = 'form-check-label';
                            label.appendChild(checkbox);
                            label.append(field.str);
                            
                            fieldItem.appendChild(label);
                            fieldsList.appendChild(fieldItem);
                        });
                        
                        fieldsContainer.appendChild(fieldsList);
                    } else {
                        const noFieldsMsg = document.createElement('p');
                        noFieldsMsg.className = 'text-danger';
                        noFieldsMsg.textContent = 'No fields available for this table. Please check the table configuration.';
                        fieldsContainer.appendChild(noFieldsMsg);
                    }
                    
                    $('#selectFieldModal').modal('show');
                })
                .catch(error => {
                    console.error('Error fetching fields:', error);
                    
                    // Create an error message element
                    const fieldsContainer = document.getElementById('field-selection-container');
                    fieldsContainer.innerHTML = '';
                    
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'alert alert-danger';
                    errorMsg.innerHTML = `
                        <strong>Error Loading Categories</strong>
                        <p>${error.message}</p>
                        <small>Please contact support or check the server logs.</small>
                    `;
                    
                    fieldsContainer.appendChild(errorMsg);
                    
                    // Show the modal with the error message
                    $('#selectFieldModal').modal('show');
                });
        });
        
        // Confirm delete/remove
        document.getElementById('confirmDeleteItemBtn')?.addEventListener('click', function() {
            const itemType = document.getElementById('deleteItemType').value;
            const fieldId = document.getElementById('deleteItemId').value;
            const tableId = document.getElementById('deleteTableId').value;
            const templateId = document.getElementById('deleteTemplateId').value;
            
            let endpoint = '';
            let data = {};
            
            if (itemType === 'field') {
                endpoint = 'ajax/remove_field_from_template.php';
                data = { 
                    template_id: templateId,
                    table_id: tableId,
                    field_id: fieldId
                };
            } else if (itemType === 'table') {
                endpoint = 'ajax/remove_table_from_template.php';
                data = {
                    template_id: templateId,
                    table_id: tableId
                };
            } else {
                alert('Invalid item type');
                return;
            }
            
            fetch(endpoint, {
                    method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
                })
                .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Item removed from template successfully!');
                    location.reload();
                    } else {
                    alert(`Error: ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                alert('An error occurred while removing the item from the template.');
            });
            
            $('#deleteConfirmModal').modal('hide');
        });

        // Delete template
        document.getElementById('delete-template-btn')?.addEventListener('click', function(e) {
            e.preventDefault();
            const templateId = <?php echo $template_id ?: 0; ?>;
            document.getElementById('deleteTemplateId').value = templateId;
            $('#deleteTemplateModal').modal('show');
        });
        
        // Confirm template deletion
        document.getElementById('confirmDeleteTemplateBtn')?.addEventListener('click', function() {
            const templateId = document.getElementById('deleteTemplateId').value;
            
            // Send AJAX request to delete the template
            fetch('ajax/delete_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ template_id: templateId })
            })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 403) {
                        window.location.href = '../../public_html/login.php';
                        throw new Error('Session expired. Please log in again.');
                    }
                    return response.json().then(data => {
                        throw new Error(data.message || 'Server error');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Show success message and redirect
                    alert(data.message);
                    window.location.href = 'csv_editor.php';
                } else {
                    // Show error message
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the template: ' + error.message);
            });
            
            // Hide the modal
            $('#deleteTemplateModal').modal('hide');
        });
    });
    </script>

    
</body>
</html> 