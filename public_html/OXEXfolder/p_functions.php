<?php
function sec_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        $session_name = 'oxi_member_id';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; // Only true if using https
        $httponly = true;

        ini_set('session.use_only_cookies', 1);
        $cookieParams = session_get_cookie_params();
        
        // Set path to root to ensure session works across all subdirectories
        $cookiePath = '/';
        
        // Set domain to current domain or leave empty for current domain
        $cookieDomain = '';
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            // Remove port number if present
            $host = preg_replace('/:\d+$/', '', $host);
            // For localhost or IP addresses, leave domain empty
            if ($host !== 'localhost' && !filter_var($host, FILTER_VALIDATE_IP)) {
                $cookieDomain = $host;
            }
        }
        
        session_set_cookie_params(
            $cookieParams["lifetime"],
            $cookiePath,
            $cookieDomain,
            $secure,
            $httponly
        );
        session_name($session_name);
        session_start();
        
        // Only regenerate ID if this is a completely new session
        if (!isset($_SESSION['trainkey']) && !isset($_POST['ee'])) {
            session_regenerate_id(true);
        }
    }
}

function check_session_timeout() {
   $max_lifetime = 3600; // 1 hour session timeout
   
   if (!isset($_SESSION['last_activity'])) {
       $_SESSION['last_activity'] = time();
       return;
   }
   
   if ((time() - $_SESSION['last_activity']) > $max_lifetime) {
       // Clear all session variables
       $_SESSION = array();
       
       // Get session parameters 
       $params = session_get_cookie_params();
       
       // Delete the cookie
       setcookie(session_name(), '', time() - 42000, 
           $params["path"], 
           $params["domain"], 
           $params["secure"], 
           $params["httponly"]
       );
       
       // Destroy the session
       session_destroy();
       
       // Redirect with error message
       header("Location: /oxex/public_html/login.php?session_timeout=1");
       exit();
   }
   
   // Update last activity time stamp
   $_SESSION['last_activity'] = time();
} 

// Move session start to the beginning
sec_session_start();
check_session_timeout();

function checkbrute($user_id, $mysqli) {
	
   // Get timestamp of current time
   $now = time();
   // All login attempts are counted from the past 2 hours. 
   $valid_attempts = $now - (7 * 60 * 60); 
 
   if ($stmt = $mysqli->prepare("SELECT date_attempt FROM logins WHERE pid = ? AND date_attempt > '$valid_attempts'")) { 
      $stmt->bind_param('i', $user_id); 
      // Execute the prepared query.
      $stmt->execute();
      $stmt->store_result();
      // If there has been more than 5 failed logins
      if($stmt->num_rows > 10) {
         return true;
      } else {
         return false;
      }
	  $stmt->close();
   }
   
   return false;
}

function login($email, $password, $mysqli) {
   // Using prepared Statements means that SQL injection is not possible. 
   if ($stmt = $mysqli->prepare("SELECT tid, trainkey, email, password, salt, txtpw FROM trainee_tbl WHERE email = ? LIMIT 1")) { 
      $stmt->bind_param('s', $email); // Bind "$email" to parameter.
      $stmt->execute(); // Execute the prepared query.
      $stmt->store_result();
      $stmt->bind_result($user_id, $trainkey, $username, $db_password, $salt, $txtpw); // get variables from result.
      $stmt->fetch();
      $loginrows = $stmt->num_rows;
      $stmt->close();
      
      $password = hash('sha512', $password.$salt); // hash the password with the unique salt.
      
      if ($txtpw != '') {
        $_SESSION['neednewpw'] = 1; 
      } else {
        $_SESSION['neednewpw'] = 0; 
      }
      
      if($loginrows == 1) { // If the user exists
         // We check if the account is locked from too many login attempts
         if(checkbrute($user_id, $mysqli) == true) { 
            // Account is locked
            return false;
         } else {
            if($db_password == $password) { // Check if the password in the database matches
               // Password is correct!
               $ip_address = $_SERVER['REMOTE_ADDR']; // Get the IP address of the user. 
               $user_browser = $_SERVER['HTTP_USER_AGENT']; // Get the user-agent string of the user.

               $user_id = preg_replace("/[^0-9]+/", "", $user_id); // XSS protection
               $_SESSION['user_id'] = $user_id; 
               $_SESSION['trainkey'] = $trainkey;
               $username = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username); // XSS protection
               $_SESSION['username'] = $username;
               //$_SESSION['login_string'] = hash('sha512', $password.$ip_address.$user_browser); removed ip check
               $_SESSION['login_string'] = hash('sha512', $password.$user_browser);
               return true;    
            } else {
               // Password is not correct
               // Record this attempt in the database
               try {
                   $now = time();
                   $ip_address = $_SERVER['REMOTE_ADDR'];
                   
                   // First verify if the table structure is correct
                   $check_table = $mysqli->query("SHOW COLUMNS FROM logins LIKE 'ip_attempt'");
                   $column = $check_table->fetch_assoc();
                   
                   // If column type is not VARCHAR, alter it
                   if ($column && strpos(strtolower($column['Type']), 'varchar') === false) {
                       $mysqli->query("ALTER TABLE logins MODIFY COLUMN ip_attempt VARCHAR(45)");
                   }
                   
                   // Now insert the login attempt
                   $login_stmt = $mysqli->prepare("INSERT INTO logins (pid, date_attempt, ip_attempt) VALUES (?, ?, ?)");
                   if ($login_stmt === false) {
                       error_log("Failed to prepare statement: " . $mysqli->error);
                       return false;
                   }
                   
                   $login_stmt->bind_param("iis", $user_id, $now, $ip_address);
                   $login_stmt->execute();
                   $login_stmt->close();
                   
               } catch (Exception $e) {
                   error_log("Login attempt recording failed: " . $e->getMessage());
               }
               return false;
            }
         }
      } else {
         // No user exists. 
         return false;
      }
   }
   return false;
}


function login_check($mysqli) {
   // Check if all session variables are set
   if(isset($_SESSION['user_id'], $_SESSION['login_string'])) {
     $user_id = $_SESSION['user_id'];
     $login_string = $_SESSION['login_string'];
     $ip_address = $_SERVER['REMOTE_ADDR']; // Get the IP address of the user. 
     $user_browser = $_SERVER['HTTP_USER_AGENT']; // Get the user-agent string of the user.
 
     if ($stmt = $mysqli->prepare("SELECT password FROM trainee_tbl WHERE tid = ? LIMIT 1")) { 
        $stmt->bind_param('i', $user_id); // Bind "$user_id" to parameter.
        $stmt->execute(); // Execute the prepared query.
        $stmt->store_result();
 
        if($stmt->num_rows == 1) { // If the user exists
           $stmt->bind_result($password); // get variables from result.
           $stmt->fetch();
           // $login_check = hash('sha512', $password.$ip_address.$user_browser); - removed ip check
           $login_check = hash('sha512', $password.$user_browser);
           if($login_check == $login_string) {
              // Logged In!!!!
              return true;
           } else {
              // Not logged in
              return false;
           }
        } else {
            // Not logged in
            return false;
        }
        $stmt->close();
     } else {
        // Not logged in
        return false;
     }
   } else {
     // Not logged in
     return false;
   }
   
}

function escapeString($value){
global $mysqli;
  if(get_magic_quotes_gpc()){
    $value=stripslashes($value);
  }
  $value=$mysqli->real_escape_string($value);
  return $value;
}

$reset_salt = '93b97938d3249309c9f30a25318671e911d0d5f6c06820fc0ee9d92fc3bb1f19fedf55735142864a93ff4e5c128c41c4c613728cf247cc2eab1ecad9e4402448';
?>