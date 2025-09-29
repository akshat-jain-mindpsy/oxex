<?php
/**
 * Admin Email Viewer for OXEX
 * 
 * Simple page to view saved emails (for production use)
 */

$mail_dir = 'public_html/mail_logs/';
$emails = [];

if (is_dir($mail_dir)) {
    $files = glob($mail_dir . 'email_*.txt');
    foreach ($files as $file) {
        $emails[] = [
            'file' => basename($file),
            'time' => filemtime($file),
            'content' => file_get_contents($file)
        ];
    }
    rsort($emails); // Most recent first
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OXEX Admin - Email Logs</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .email { border: 1px solid #ddd; margin: 15px 0; padding: 15px; border-radius: 4px; }
        .email-header { background: #f8f9fa; padding: 10px; margin: -15px -15px 10px -15px; border-radius: 4px 4px 0 0; }
        .email-content { white-space: pre-wrap; font-family: monospace; font-size: 12px; }
        .btn { background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-box { background: #e9ecef; padding: 15px; border-radius: 4px; text-align: center; flex: 1; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 OXEX Admin - Email Logs</h1>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo count($emails); ?></div>
                <div>Total Emails</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count(array_filter($emails, function($e) { return strpos($e['content'], 'StackCP account disabled') !== false; })); ?></div>
                <div>Saved to File</div>
            </div>
        </div>
        
        <p>
            <a href="public_html/reset2.php" class="btn">🔗 Test Password Reset</a>
            <a href="?clear=all" class="btn btn-danger" onclick="return confirm('Clear all emails?')">🗑️ Clear All</a>
        </p>
        
        <?php if (isset($_GET['clear']) && $_GET['clear'] === 'all'): ?>
            <?php
            foreach ($emails as $email) {
                unlink($mail_dir . $email['file']);
            }
            echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0;'>✅ All emails cleared</div>";
            $emails = [];
            ?>
        <?php endif; ?>
        
        <h2>Recent Emails</h2>
        
        <?php if (empty($emails)): ?>
            <p>No emails found. Try the password reset functionality!</p>
        <?php else: ?>
            <?php foreach (array_slice($emails, 0, 20) as $email): ?>
                <div class="email">
                    <div class="email-header">
                        <strong>📅 <?php echo date('Y-m-d H:i:s', $email['time']); ?></strong>
                        <span style="float: right;">📁 <?php echo $email['file']; ?></span>
                    </div>
                    <div class="email-content"><?php echo htmlspecialchars($email['content']); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <hr>
        <p><strong>Note:</strong> Emails are saved to file when StackCP account is disabled. Contact StackCP support to reactivate the admin@oxex.co.uk account for real email delivery.</p>
    </div>
</body>
</html>
