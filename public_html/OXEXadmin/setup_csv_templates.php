<?php
// Database connection
include 'public_html/OXEXfolder/config.php';

// First check if the columns table exists
$table_check = $mysqli->query("SHOW TABLES LIKE 'csv_template_columns'");
$table_exists = ($table_check && $table_check->num_rows > 0);

if (!$table_exists) {
    echo "<h3>Creating csv_template_columns table...</h3>";
    
    $create_table = "CREATE TABLE csv_template_columns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        field_id INT NOT NULL,
        table_id INT NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (template_id) REFERENCES csv_templates(id) ON DELETE CASCADE
    )";
    
    if ($mysqli->query($create_table)) {
        echo "<p>Table created successfully!</p>";
    } else {
        echo "<p>Error creating table: " . $mysqli->error . "</p>";
        exit;
    }
}

// Check if we have any templates
$check_templates = $mysqli->query("SELECT id FROM csv_templates");
if (!$check_templates || $check_templates->num_rows == 0) {
    echo "<h3>Creating sample template...</h3>";
    
    $create_template = "INSERT INTO csv_templates (template_name, description, created_by) 
                        VALUES ('Standard Template', 'Default template with common fields', 'admin')";
    
    if ($mysqli->query($create_template)) {
        echo "<p>Sample template created!</p>";
        $template_id = $mysqli->insert_id;
    } else {
        echo "<p>Error creating template: " . $mysqli->error . "</p>";
        exit;
    }
} else {
    // Use the first template
    $template_row = $check_templates->fetch_assoc();
    $template_id = $template_row['id'];
}

// Get some common fields to use in the template
echo "<h3>Adding columns to template $template_id...</h3>";

// First get some field IDs from select_types
$fields_query = $mysqli->query("SELECT stid, str FROM select_types LIMIT 10");
$fields = [];
if ($fields_query) {
    while ($field = $fields_query->fetch_assoc()) {
        $fields[] = $field;
    }
}

if (empty($fields)) {
    echo "<p>No fields found in select_types table!</p>";
    exit;
}

// Get a table ID to use (Generic table)
$table_query = $mysqli->query("SELECT tbid, tab_name FROM tabs_tbl WHERE tbid = 1 LIMIT 1");
$table = $table_query ? $table_query->fetch_assoc() : null;

if (!$table) {
    echo "<p>Could not find Generic table!</p>";
    exit;
}

$table_id = $table['tbid'];

// Check if template already has columns
$column_check = $mysqli->prepare("SELECT id FROM csv_template_columns WHERE template_id = ?");
$column_check->bind_param("i", $template_id);
$column_check->execute();
$column_check->store_result();

if ($column_check->num_rows > 0) {
    echo "<p>Template already has " . $column_check->num_rows . " columns. No changes made.</p>";
    $column_check->close();
} else {
    // Add some columns to the template
    echo "<p>Adding columns to template...</p>";
    
    $display_order = 1;
    $success_count = 0;
    
    foreach ($fields as $field) {
        $insert = $mysqli->prepare("INSERT INTO csv_template_columns 
                                    (template_id, field_id, table_id, display_order) 
                                    VALUES (?, ?, ?, ?)");
        $insert->bind_param("iiii", $template_id, $field['stid'], $table_id, $display_order);
        
        if ($insert->execute()) {
            echo "<p>Added column: {$field['str']}</p>";
            $success_count++;
        } else {
            echo "<p>Error adding column {$field['str']}: " . $mysqli->error . "</p>";
        }
        
        $display_order++;
        $insert->close();
    }
    
    echo "<p>Added $success_count columns to template $template_id.</p>";
}

echo "<h3>Setup Complete</h3>";
echo "<p><a href='public_html/account.php'>Go to Account Page</a> to test the CSV download.</p>";

$mysqli->close();
?> 