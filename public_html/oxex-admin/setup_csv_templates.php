<?php
// Database connection
include 'public_html/OXEXfolder/config.php';

// First check if the columns table exists
$table_check = $supabase_pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'csv_template_columns')");
$table_exists = $table_check->fetchColumn();

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
    
    if ($supabase_pdo->exec($create_table) !== false) {
        echo "<p>Table created successfully!</p>";
    } else {
        echo "<p>Error creating table: " . implode(', ', $supabase_pdo->errorInfo()) . "</p>";
        exit;
    }
}

// Check if we have any templates
$check_templates = $supabase_pdo->query("SELECT id FROM csv_templates");
$templates = $check_templates->fetchAll(PDO::FETCH_ASSOC);
if (empty($templates)) {
    echo "<h3>Creating sample template...</h3>";
    
    $create_template = "INSERT INTO csv_templates (template_name, description, created_by) 
                        VALUES ('Standard Template', 'Default template with common fields', 'admin')";
    
    if ($supabase_pdo->exec($create_template) !== false) {
        echo "<p>Sample template created!</p>";
        $template_id = (int)$supabase_pdo->lastInsertId();
    } else {
        echo "<p>Error creating template: " . implode(', ', $supabase_pdo->errorInfo()) . "</p>";
        exit;
    }
} else {
    // Use the first template
    $template_id = (int)$templates[0]['id'];
}

// Get some common fields to use in the template
echo "<h3>Adding columns to template $template_id...</h3>";

// First get some field IDs from select_types
$fields_query = $supabase_pdo->query("SELECT stid, str FROM select_types LIMIT 10");
$fields = $fields_query->fetchAll(PDO::FETCH_ASSOC);

if (empty($fields)) {
    echo "<p>No fields found in select_types table!</p>";
    exit;
}

// Get a table ID to use (Generic table)
$table_query = $supabase_pdo->query("SELECT tbid, tab_name FROM tabs_tbl WHERE tbid = 1 LIMIT 1");
$table = $table_query->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    echo "<p>Could not find Generic table!</p>";
    exit;
}

$table_id = $table['tbid'];

// Check if template already has columns
$column_check = $supabase_pdo->prepare("SELECT id FROM csv_template_columns WHERE template_id = ?");
$column_check->execute([$template_id]);
$existing_columns = $column_check->fetchAll(PDO::FETCH_ASSOC);

if (count($existing_columns) > 0) {
    echo "<p>Template already has " . count($existing_columns) . " columns. No changes made.</p>";
} else {
    // Add some columns to the template
    echo "<p>Adding columns to template...</p>";
    
    $display_order = 1;
    $success_count = 0;
    
    foreach ($fields as $field) {
        $insert = $supabase_pdo->prepare("INSERT INTO csv_template_columns 
                                    (template_id, field_id, table_id, display_order) 
                                    VALUES (?, ?, ?, ?)");
        
        if ($insert->execute([$template_id, $field['stid'], $table_id, $display_order])) {
            echo "<p>Added column: {$field['str']}</p>";
            $success_count++;
        } else {
            echo "<p>Error adding column {$field['str']}: " . implode(', ', $supabase_pdo->errorInfo()) . "</p>";
        }
        
        $display_order++;
    }
    
    echo "<p>Added $success_count columns to template $template_id.</p>";
}

echo "<h3>Setup Complete</h3>";
echo "<p><a href='public_html/account.php'>Go to Account Page</a> to test the CSV download.</p>";
?> 