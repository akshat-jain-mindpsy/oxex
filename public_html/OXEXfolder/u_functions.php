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

function checkbrute($user_id, $pdo) {
   // Get timestamp of current time
   $now = time();
   // All login attempts are counted from the past 2 hours. 
   $valid_attempts = $now - (2 * 60 * 60); 
 
   try {
      // Check if logins table exists first
      $table_check = $pdo->query("SELECT 1 FROM logins LIMIT 1");
      if ($table_check === false) {
         error_log("checkbrute: logins table does not exist, skipping brute force check");
         return false;
      }
      
      $stmt = $pdo->prepare("SELECT date_attempt FROM logins WHERE pid = ? AND date_attempt > ?"); 
      $stmt->execute([$user_id, $valid_attempts]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      // If there has been more than 5 failed logins
      if(count($rows) > 5) {
         return true;
      } else {
         return false;
      }
   } catch (Exception $e) {
      error_log("checkbrute error: " . $e->getMessage());
      return false;
   }
}

function login($email, $password, $pdo) {
   // Using prepared Statements means that SQL injection is not possible. 
   $stmt = $pdo->prepare("SELECT whid, usrkey, email, password, salt, admintype, photo, isdev FROM who_there WHERE email = ? LIMIT 1"); 
   if ($stmt) {
      $stmt->execute([$email]); // Execute the prepared query.
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if($row) { // If the user exists
         $user_id = $row['whid'];
         $usrkey = $row['usrkey'];
         $username = $row['email'];
         $db_password = $row['password'];
         $salt = $row['salt'];
         $admintype = $row['admintype'];
         $adminphoto = $row['photo'];
         $adminisdev = $row['isdev'];
         $password = hash('sha512', $password.$salt); // hash the password with the unique salt.
	  	// and if ther are approved
         // We check if the account is locked from too many login attempts
         if(checkbrute($user_id, $pdo) == true) { 
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
			$login_stmt = $pdo->prepare("INSERT INTO logins (lid, date_attempt, ip_attempt, pid) VALUES (?, ?, ?, ?)");
			$pid = 0; // Default value if not specified
			$login_stmt->execute([$user_id, $now, $ip_address, $pid]);
	
            return false;
         }
      }
      } else {
         // No user exists. 
         return false;
      }
   }
}


function login_check($pdo) {
   // Check if all session variables are set
   if(isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['login_string'], $_SESSION['admintype'])) {
     $user_id = $_SESSION['user_id'];
     $login_string = $_SESSION['login_string'];
     $user_browser = $_SERVER['HTTP_USER_AGENT']; // Get the user-agent string of the user.

     try {
        $stmt = $pdo->prepare("SELECT password, admintype FROM who_there WHERE whid = ? LIMIT 1"); 
        $stmt->execute([$user_id]); // Execute the prepared query.
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
 
        if($row) { // If the user exists
           $password = $row['password'];
           $db_admintype = $row['admintype'];
           
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
     } catch (Exception $e) {
        error_log("login_check error: " . $e->getMessage());
        return false;
     }
   } else {
     // Not logged in
     return false;
   }
}

function escapeString($value){
  if(get_magic_quotes_gpc()){
    $value=stripslashes($value);
  }
  // PDO handles escaping automatically with prepared statements
  // This function is kept for backward compatibility but should use prepared statements instead
  return $value;
}

$reset_salt = '93b97938d3249309c9f30a25318671e911d0d5f6c06820fc0ee9d92fc3bb1f19fedf55735142864a93ff4e5c128c41c4c613728cf247cc2eab1ecad9e4402448';

?>