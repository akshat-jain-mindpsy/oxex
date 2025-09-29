<?php
/**
 * Secure Mail Handler for OXEX
 * 
 * This provides a secure email solution with multiple fallback options
 */

require_once 'PHPMailerAutoload.php';

class SecureMail {
    
    private $configs = [
        'stackcp_ssl' => [
            'host' => 'smtp.stackmail.com',
            'port' => 465,
            'secure' => 'ssl',
            'username' => 'noreply@oxex.co.uk',
            'password' => 'Wr2f3ef91',
            'from_email' => 'noreply@oxex.co.uk',
            'from_name' => 'E-log Website'
        ],
        'stackcp_tls' => [
            'host' => 'smtp.stackmail.com',
            'port' => 587,
            'secure' => 'tls',
            'username' => 'noreply@oxex.co.uk',
            'password' => 'Wr2f3ef91',
            'from_email' => 'noreply@oxex.co.uk',
            'from_name' => 'E-log Website'
        ]
    ];
    
    /**
     * Send email with multiple fallback options
     */
    public function send($to, $subject, $message, $from_email = null, $from_name = null) {
        // Security validation
        if (!$this->validateEmail($to)) {
            error_log("❌ Email validation failed for: $to");
            return false;
        }
        
        // Try different configurations
        foreach ($this->configs as $config_name => $config) {
            if ($this->sendWithConfig($to, $subject, $message, $config, $from_email, $from_name)) {
                error_log("✅ Email sent successfully using $config_name");
                return true;
            }
        }
        
        // If all SMTP methods fail, save to file
        error_log("All SMTP methods failed, saving to file");
        return $this->saveToFile($to, $subject, $message, $from_email ?: 'admin@oxex.co.uk', $from_name ?: 'E-log Website');
    }
    
    /**
     * Send email with specific configuration
     */
    private function sendWithConfig($to, $subject, $message, $config, $from_email, $from_name) {
        $mail = new PHPMailer(true);
        
        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->Port = $config['port'];
            $mail->SMTPSecure = $config['secure'];
            $mail->Timeout = 10; // Short timeout
            
            $mail->setFrom($from_email ?: $config['from_email'], $from_name ?: $config['from_name']);
            $mail->isHTML(false);
            $mail->AddAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Add security headers
            $mail->addCustomHeader('X-Mailer', 'OXEX-SecureMail-1.0');
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("❌ SMTP failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate email address with security checks
     */
    private function validateEmail($email) {
        // Basic validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Block suspicious patterns
        $suspicious_patterns = [
            '/test/i',
            '/spam/i',
            '/fake/i',
            '/noreply/i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return false;
            }
        }
        
        // Block known problematic domains
        $blocked_domains = [
            'tiscali.it',
            'example.com',
            'test.com',
            'localhost',
            'invalid.com'
        ];
        
        $domain = substr(strrchr($email, "@"), 1);
        if (in_array($domain, $blocked_domains)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Save email to file as fallback
     */
    private function saveToFile($to, $subject, $message, $from_email, $from_name) {
        $mail_dir = __DIR__ . '/../mail_logs/';
        if (!file_exists($mail_dir)) {
            mkdir($mail_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $filename = 'secure_email_' . date('Y-m-d_H-i-s') . '_' . substr(md5($to), 0, 8) . '.txt';
        $filepath = $mail_dir . $filename;
        
        $email_content = "=== SECURE EMAIL (SMTP Failed) ===\n";
        $email_content .= "Time: $timestamp\n";
        $email_content .= "To: $to\n";
        $email_content .= "From: $from_name <$from_email>\n";
        $email_content .= "Subject: $subject\n";
        $email_content .= "Status: SMTP failed - saved to file\n";
        $email_content .= "========================\n\n";
        $email_content .= $message;
        
        if (file_put_contents($filepath, $email_content)) {
            error_log("✅ Email saved to file: $filename");
            return true;
        }
        
        return false;
    }
}
?>
