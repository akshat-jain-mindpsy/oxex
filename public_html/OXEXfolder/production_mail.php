<?php
/**
 * Production Mail Handler for OXEX
 * 
 * This handles email sending for production with fallback options
 */

require_once 'PHPMailerAutoload.php';

class ProductionMail {
    
    /**
     * Send email with StackCP configuration and fallback
     */
    public function send($to, $subject, $message, $from_email = 'admin@oxex.co.uk', $from_name = 'E-log Website') {
        // Try StackCP first
        if ($this->sendWithStackCP($to, $subject, $message, $from_email, $from_name)) {
            return true;
        }
        
        // If StackCP fails, try alternative methods
        error_log("StackCP email failed, trying alternative methods...");
        
        // For now, save to file as fallback
        return $this->saveToFile($to, $subject, $message, $from_email, $from_name);
    }
    
    /**
     * Send email using StackCP SMTP with security measures
     */
    private function sendWithStackCP($to, $subject, $message, $from_email, $from_name) {
        // Security check: Only send to valid email addresses
        if (!$this->isValidEmail($to)) {
            error_log("❌ Invalid email address blocked: $to");
            return false;
        }
        
        // Rate limiting: Check if too many emails sent recently
        if ($this->isRateLimited($to)) {
            error_log("❌ Rate limit exceeded for: $to");
            return false;
        }
        
        $mail = new PHPMailer(true);
        
        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = 'smtp.stackmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'admin@oxex.co.uk';
            $mail->Password = '3u(**zL€bWA[';
            $mail->Port = 465; // Use SSL port as per StackCP settings
            $mail->SMTPSecure = 'ssl'; // Use SSL as per StackCP settings
            $mail->setFrom($from_email, $from_name);
            $mail->isHTML(false);
            $mail->AddAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Add security headers
            $mail->addCustomHeader('X-Mailer', 'OXEX-PasswordReset-1.0');
            $mail->addCustomHeader('X-Priority', '3');
            
            $mail->send();
            $this->logEmailSent($to);
            error_log("✅ StackCP email sent successfully to: $to");
            return true;
            
        } catch (Exception $e) {
            error_log("❌ StackCP email failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate email address
     */
    private function isValidEmail($email) {
        // Basic email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Block suspicious domains
        $blocked_domains = ['tiscali.it', 'example.com', 'test.com'];
        $domain = substr(strrchr($email, "@"), 1);
        
        if (in_array($domain, $blocked_domains)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check rate limiting
     */
    private function isRateLimited($email) {
        $rate_file = __DIR__ . '/../mail_logs/rate_limit.json';
        $rate_data = [];
        
        if (file_exists($rate_file)) {
            $rate_data = json_decode(file_get_contents($rate_file), true) ?: [];
        }
        
        $current_time = time();
        $email_key = md5($email);
        
        // Clean old entries (older than 1 hour)
        foreach ($rate_data as $key => $data) {
            if ($current_time - $data['time'] > 3600) {
                unset($rate_data[$key]);
            }
        }
        
        // Check if email has sent too many requests recently
        if (isset($rate_data[$email_key])) {
            if ($rate_data[$email_key]['count'] >= 3 && $current_time - $rate_data[$email_key]['time'] < 3600) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log email sent for rate limiting
     */
    private function logEmailSent($email) {
        $rate_file = __DIR__ . '/../mail_logs/rate_limit.json';
        $rate_data = [];
        
        if (file_exists($rate_file)) {
            $rate_data = json_decode(file_get_contents($rate_file), true) ?: [];
        }
        
        $current_time = time();
        $email_key = md5($email);
        
        if (isset($rate_data[$email_key])) {
            $rate_data[$email_key]['count']++;
            $rate_data[$email_key]['time'] = $current_time;
        } else {
            $rate_data[$email_key] = ['count' => 1, 'time' => $current_time];
        }
        
        file_put_contents($rate_file, json_encode($rate_data));
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
        $filename = 'email_' . date('Y-m-d_H-i-s') . '_' . substr(md5($to), 0, 8) . '.txt';
        $filepath = $mail_dir . $filename;
        
        $email_content = "=== EMAIL SAVED (StackCP Failed) ===\n";
        $email_content .= "Time: $timestamp\n";
        $email_content .= "To: $to\n";
        $email_content .= "From: $from_name <$from_email>\n";
        $email_content .= "Subject: $subject\n";
        $email_content .= "Status: StackCP account disabled - saved to file\n";
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
