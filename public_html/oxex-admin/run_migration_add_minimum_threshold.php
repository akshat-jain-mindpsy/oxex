<?php
/**
 * Migration script: Add minimum_threshold column to pass_standards table
 * Run this file once to add the missing column to your database
 */

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Only allow authorized admins
if (!(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'DV'))) {
    die('Unauthorized. Only AT and DV admins can run migrations.');
}

$migration_name = 'Add minimum_threshold column to pass_standards';
$success = false;
$error = null;

try {
    // Check if column already exists
    $check_query = "
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'pass_standards' 
        AND column_name = 'minimum_threshold'
    ";
    $check_stmt = $supabase_pdo->query($check_query);
    $column_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column_exists) {
        $message = "Column 'minimum_threshold' already exists. Migration not needed.";
        $success = true;
    } else {
        // Add the column
        $alter_query = "
            ALTER TABLE pass_standards 
            ADD COLUMN minimum_threshold NUMERIC(10,2) NULL
        ";
        
        $supabase_pdo->exec($alter_query);
        
        // Add comment
        $comment_query = "
            COMMENT ON COLUMN pass_standards.minimum_threshold IS 
            'Minimum value that each case must meet when requirement_type is PER_CASE_MINIMUM'
        ";
        $supabase_pdo->exec($comment_query);
        
        $message = "Migration completed successfully! Column 'minimum_threshold' has been added to pass_standards table.";
        $success = true;
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
    $message = "Migration failed: " . $error;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Migration: <?php echo htmlspecialchars($migration_name); ?></title>
    <?php include 'incl/admincss.php' ?>
    <style>
        body { padding: 20px; }
        .result-box {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
        }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <div class="result-box <?php echo $success ? 'success' : 'error'; ?>">
        <h2><?php echo $migration_name; ?></h2>
        <p><?php echo htmlspecialchars($message); ?></p>
        <?php if ($error): ?>
            <pre style="background: rgba(0,0,0,0.1); padding: 10px; border-radius: 4px; overflow-x: auto;"><?php echo htmlspecialchars($error); ?></pre>
        <?php endif; ?>
        <p><a href="pass_standards.php" class="btn btn-primary">Return to Pass Standards</a></p>
    </div>
</body>
</html>


