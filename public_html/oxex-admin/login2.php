<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start(); // Our custom secure way of starting a php session. 

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Logging function
function custom_log($message) {
    error_log('[LOGIN DEBUG] ' . $message);
}

if(isset($_POST['email'], $_POST['p'])) { 
   $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
   $password = $_POST['p']; // The password.

   custom_log("Login attempt for email: $email");

   // Validate email format
   if (!$email) {
      header('Location: login.html?error=invalid_email');
      exit();
   }

   // Prepare statement to check if email exists
   $stmt = $mysqli->prepare("SELECT whid, usrkey, email, password, salt, admintype, photo, isdev, realname FROM who_there WHERE email = ? LIMIT 1");
   $stmt->bind_param('s', $email);
   $stmt->execute();
   $stmt->store_result();

   custom_log("Number of rows found: " . $stmt->num_rows);

   if ($stmt->num_rows == 0) {
      custom_log("No user found with email: $email");
      header('Location: login.html?error=email_not_found');
      exit();
   }

   $stmt->bind_result($user_id, $usrkey, $db_email, $db_password, $salt, $admintype, $adminphoto, $adminisdev, $realname);
   $stmt->fetch();
   $stmt->close();

   // Verify password
   $hashed_password = hash('sha512', $password.$salt);
   
   custom_log("Stored hashed password: $db_password");
   custom_log("Computed hashed password: $hashed_password");

   if ($hashed_password !== $db_password) {
      custom_log("Password mismatch for email: $email");
      
      // Record failed login attempt
      $now = time();
      $ip_address = $_SERVER['REMOTE_ADDR'];

      // Validate and sanitize IP address
      $sanitized_ip = filter_var($ip_address, FILTER_VALIDATE_IP);

      if ($sanitized_ip === false) {
          $sanitized_ip = '0.0.0.0';
          custom_log("Invalid IP address: " . $ip_address);
      }

      try {
          // Prepare the statement with error handling
          $login_stmt = $mysqli->prepare("INSERT INTO logins (pid, date_attempt, ip_attempt) VALUES (?, ?, ?)");
          
          if ($login_stmt === false) {
              throw new Exception("Failed to prepare statement: " . $mysqli->error);
          }

          $login_stmt->bind_param("iis", $user_id, $now, $sanitized_ip);
          
          if (!$login_stmt->execute()) {
              throw new Exception("Failed to execute statement: " . $login_stmt->error);
          }
          
          $login_stmt->close();
      } catch (Exception $e) {
          custom_log("Login attempt recording error: " . $e->getMessage());
      }

      header('Location: login.html?error=incorrect_password');
      exit();
   }

   // Check for brute force attempts
   if(checkbrute($user_id, $mysqli) == true) { 
      custom_log("Brute force detected for user: $user_id");
      header('Location: login.html?error=account_locked');
      exit();
   }

   // Successful login
   $_SESSION['user_id'] = $user_id; 
   $_SESSION['usrkey'] = $usrkey; 
   $_SESSION['admintype'] = $admintype;
   $_SESSION['adminphoto'] = $adminphoto;
   $_SESSION['adminisdev'] = $adminisdev;
   $_SESSION['username'] = $realname;
   $_SESSION['login_string'] = hash('sha512', $hashed_password.$_SERVER['HTTP_USER_AGENT']); 

   custom_log("Login successful for user: $realname");

   // Update login status
   $now = time();
   $value2 = 2;
   $stmt = $mysqli->prepare("UPDATE who_there SET isonline = ?, lastlogin = ? WHERE usrkey = ?"); 
   $stmt->bind_param("iss", $value2, $now, $usrkey);
   $stmt->execute();
   $stmt->close();

   // Redirect based on admin type
   $allowed_admin_types = ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'];
   if (in_array($admintype, $allowed_admin_types)) {
      custom_log("Redirecting to indextable.php for admin type: $admintype");
      header('Location: indextable.php');
      exit();
   } else {
      custom_log("Unauthorized admin type: $admintype");
      header('Location: login.html?error=unauthorized');
      exit();
   }
} else { 
   // Incorrect POST variables
   custom_log("Invalid login request");
   header('Location: login.html?error=invalid_request');
   exit();
}
?>