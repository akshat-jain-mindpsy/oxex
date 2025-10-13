<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'DV'))) {
    header("Location: index.php");
    exit();
}

// Page setup
$pagetitle = "Database Update";

// Set variables needed by adminjs.php
$whichDocModal = 5; // Admin section
$value0 = 0; // Default value for $value0 = 0; // Default value for sort_order
$subtitle = "updated = false;
$messages = [];

// Get table ID from request
$table_id = isset($_GET['tbid']) ? (int)$_GET['tbid'] : 0;

// Check if field_options column exists
$check_column = $pdo->query("SHOW COLUMNS FROM select_types LIKE 'field_options'");
$column_exists = $check_column && $check_column->rowCount() > 0;

// Handle update POST request
if (isset($_POST['update_db']) && $_POST['update_db'] == 'yes') {
    if (!$column_exists) {
        // Add field_options column to select_types table right after single column
        $add_column = $pdo->query("ALTER TABLE select_types ADD COLUMN field_options TEXT NULL AFTER single");
        
        if ($add_column) {
            $messages[] = "Successfully added field_options column to select_types table.";
            $updated = true;
            $column_exists = true;
        } else {
            $messages[] = "Error adding field_options column: " . $pdo->errorInfo()[2];
        }
    } else {
        $messages[] = "field_options column already exists.";
    }
}

// Special case for handling options migration
if (isset($_POST['migrate_options']) && $_POST['migrate_options'] == 'yes') {
    if ($column_exists) {
        // First get all selection fields (type 0 or 1)
        $query = "SELECT stid, str, single FROM select_types WHERE single IN (0,1)";
        $result = $pdo->query($query);
        
        if ($result) {
            $count = 0;
            
            // Get existing options from select_gen table (as seen in listtypedetail.php)
            while ($field = $result->fetch(PDO::FETCH_ASSOC)) {
                $field_id = $field['stid'];
                
                // Get current options from the select_gen table
                $options_query = "SELECT pid, select_val FROM select_gen WHERE stid = ? ORDER BY select_val";
                $stmt = $pdo->prepare($options_query);
                $stmt->execute([$field_id]);
                $options_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($options_result) > 0) {
                    $options = [];
                    
                    foreach ($options_result as $option) {
                        $options[] = $option['select_val'];
                    }
                    
                    // Format options as pipe-delimited string
                    $options_string = implode('|', $options);
                    
                    // Update field with options
                    $update = $pdo->prepare("UPDATE select_types SET field_options = ? WHERE stid = ?");
                    
                    if ($update->execute([$options_string, $field_id])) {
                        $count++;
                    }
                    
                    $update->closeCursor();
                }
                
                $stmt->closeCursor();
            }
            
            $messages[] = "Migrated options for $count fields from select_gen table.";
            $updated = true;
        } else {
            $messages[] = "Error querying selection fields: " . $pdo->errorInfo()[2];
        }
    } else {
        $messages[] = "field_options column must exist before migrating data.";
    }
}

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title><?php echo $pagetitle ?></title>
   <?php include 'incl/admincss.php' ?>
</head>
<body>
   <div class="wrapper">
      <!-- top navbar-->
      <?php include 'incl/topbar.php' ?>
      <?php include 'incl/sidebar.php' ?>
      <?php include 'incl/offsidebar.php' ?>

      <!-- Main section-->
      <section class="section-container">
         <!-- Page content-->
         <div class="content-wrapper">
            <div class="content-heading">
               <div><?php echo $pagetitle ?></div>
            </div>
            
            <div class="row">
               <div class="col-md-8 offset-md-2">
                  <div class="card">
                     <div class="card-header bg-info text-white">
                        <h4 class="card-title mb-0">Database Structure Update</h4>
                     </div>
                     <div class="card-body">
                        <?php if (!empty($messages)): ?>
                           <div class="alert <?php echo $updated ? 'alert-success' : 'alert-warning' ?>">
                              <?php foreach ($messages as $message): ?>
                                 <p><?php echo htmlspecialchars($message) ?></p>
                              <?php endforeach; ?>
                           </div>
                        <?php endif; ?>
                        
                        <h5>Current Status</h5>
                        <ul>
                           <li>field_options column in select_types table: 
                              <?php echo $column_exists ? '<span class="text-success">EXISTS</span>' : '<span class="text-danger">MISSING</span>'; ?>
                           </li>
                        </ul>
                        
                        <?php if (!$column_exists): ?>
                           <form method="post" action="">
                              <input type="hidden" name="update_db" value="yes">
                              <p>The field_options column is required for the field options management feature.</p>
                              <button type="submit" class="btn btn-primary">Update Database Structure</button>
                           </form>
                        <?php else: ?>
                           <form method="post" action="">
                              <input type="hidden" name="migrate_options" value="yes">
                              <p>Do you want to migrate existing options from select_gen table to field_options?</p>
                              <button type="submit" class="btn btn-info">Migrate Field Options</button>
                           </form>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                           <a href="sheetdetail.php?which=<?php echo $table_id > 0 ? $table_id : 1; ?>" class="btn btn-outline-secondary">
                              Return to Table Detail
                           </a>
</div>
</div>
</div>
      </section>
   </div>
   
   <?php include 'incl/adminjs.php' ?>
</body>
</html> 