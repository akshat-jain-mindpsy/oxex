<?PHP
function sec_session_start() {
	$session_name = 'oxi_session_id'; // Set a custom session name
	$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; // Set to true if using https.
	$httponly = true; // This stops javascript being able to access the session id. 

	ini_set('session.use_only_cookies', 1); // Forces sessions to only use cookies. 
	$cookieParams = session_get_cookie_params(); // Gets current cookies params.
	
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
	
	session_set_cookie_params($cookieParams["lifetime"], $cookiePath, $cookieDomain, $secure, $httponly); 
	session_name($session_name); // Sets the session name to the one set above.
	
	// Debug logging
	error_log('[SESSION DEBUG] Cookie params - lifetime: ' . $cookieParams["lifetime"] . ', path: ' . $cookiePath . ', domain: ' . $cookieDomain . ', secure: ' . ($secure ? 'true' : 'false') . ', httponly: ' . ($httponly ? 'true' : 'false'));
	error_log('[SESSION DEBUG] HTTP_HOST: ' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'not set'));
	
	// Only start session if not already started
	if (session_status() === PHP_SESSION_NONE) {
		session_start(); // Start the php session
		error_log('[SESSION DEBUG] Session started with ID: ' . session_id());
		
		// Only regenerate ID if this is a completely new session (no user_id set)
		// and we're not in the middle of a login process
		// and we're not in a redirect after login
		if (!isset($_SESSION['user_id']) && !isset($_POST['email']) && !isset($_SESSION['login_string'])) {
			$old_session_id = session_id();
			session_regenerate_id(true); // regenerated the session, delete the old one.     
			error_log('[SESSION DEBUG] Session ID regenerated from ' . $old_session_id . ' to ' . session_id());
		} else {
			error_log('[SESSION DEBUG] Session ID not regenerated - user_id: ' . (isset($_SESSION['user_id']) ? 'set' : 'not set') . ', POST email: ' . (isset($_POST['email']) ? 'set' : 'not set') . ', login_string: ' . (isset($_SESSION['login_string']) ? 'set' : 'not set'));
		}
	} else {
		error_log('[SESSION DEBUG] Session already active with ID: ' . session_id());
	}
}

// for admin registration and logins

function checkbrute($user_id, $mysqli) {
   // Get timestamp of current time
   $now = time();
   // All login attempts are counted from the past 2 hours. 
   $valid_attempts = $now - (2 * 60 * 60); 
 
   if ($stmt = $mysqli->prepare("SELECT date_attempt FROM logins WHERE lid = ? AND date_attempt > '$valid_attempts'")) { 
      $stmt->bind_param('i', $user_id); 
      // Execute the prepared query.
      $stmt->execute();
      $stmt->store_result();
      // If there has been more than 5 failed logins
      if($stmt->num_rows > 5) {
         return true;
      } else {
         return false;
      }
   }
}

function login($email, $password, $mysqli) {
   // Using prepared Statements means that SQL injection is not possible. 
   if ($stmt = $mysqli->prepare("SELECT whid, usrkey, email, password, salt, admintype, photo, isdev FROM who_there WHERE email = ? LIMIT 1")) { 
      $stmt->bind_param('s', $email); // Bind "$email" to parameter.
      $stmt->execute(); // Execute the prepared query.
      $stmt->store_result();
      $stmt->bind_result($user_id, $usrkey, $username, $db_password, $salt, $admintype, $adminphoto, $adminisdev); // get variables from result.
      $stmt->fetch();
      $password = hash('sha512', $password.$salt); // hash the password with the unique salt.
 
      if($stmt->num_rows == 1) { // If the user exists
	  	// and if ther are approved
         // We check if the account is locked from too many login attempts
         if(checkbrute($user_id, $mysqli) == true) { 
            // Account is locked
            // Send an email to user saying their account is locked
            return false;
         } else {
         if($db_password == $password) { // Check if the password in the database matches the password the user submitted. 
            // Password is correct!
 
               $ip_address = $_SERVER['REMOTE_ADDR']; // Get the IP address of the user. 
               $user_browser = $_SERVER['HTTP_USER_AGENT']; // Get the user-agent string of the user.
 
               $user_id = preg_replace("/[^0-9]+/", "", $user_id); // XSS protection as we might print this value
               $_SESSION['user_id'] = $user_id; 
      			   $_SESSION['usrkey'] = $usrkey; 
      			   $_SESSION['admintype'] = $admintype;
                  $_SESSION['adminphoto'] = $adminphoto;
                  $_SESSION['adminisdev'] = $adminisdev;
               $username = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username); // XSS protection as we might print this value
               $_SESSION['username'] = $username;
               //$_SESSION['login_string'] = hash('sha512', $password.$ip_address.$user_browser); removed ip check
               $_SESSION['login_string'] = hash('sha512', $password.$user_browser);
               // Login successful.
               return true;    
         } else {
            // Password is not correct
            // We record this attempt in the database
            $now = time();
			$ip_address = $_SERVER['REMOTE_ADDR']; // Get the IP address of the user. 
			$login_stmt = $mysqli->prepare("INSERT INTO logins (lid, date_attempt, ip_attempt, pid) VALUES (?, ?, ?, ?)");
			$pid = 0; // Default value if not specified
			$login_stmt->bind_param("issi", $user_id, $now, $ip_address, $pid);
			$login_stmt->execute();
	
            return false;
         }
      }
      } else {
         // No user exists. 
         return false;
      }
   }
}


function login_check($mysqli) {
   // Check if all session variables are set
   if(isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['login_string'], $_SESSION['admintype'])) {
     $user_id = $_SESSION['user_id'];
     $login_string = $_SESSION['login_string'];
     $user_browser = $_SERVER['HTTP_USER_AGENT']; // Get the user-agent string of the user.

     if ($stmt = $mysqli->prepare("SELECT password, admintype FROM who_there WHERE whid = ? LIMIT 1")) { 
        $stmt->bind_param('i', $user_id); // Bind "$user_id" to parameter.
        $stmt->execute(); // Execute the prepared query.
        $stmt->store_result();
 
        if($stmt->num_rows == 1) { // If the user exists
           $stmt->bind_result($password, $db_admintype); // get variables from result.
           $stmt->fetch();
           
           // Validate admin type
           $allowed_admin_types = ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'];
           if (!in_array($db_admintype, $allowed_admin_types)) {
              return false;
           }

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