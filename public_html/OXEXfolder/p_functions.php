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

function checkbrute($user_id, $unused = null) {
   global $supabase_pdo;
   $now = time();
   $valid_attempts = $now - (7 * 60 * 60); 
   try {
      $stmt = $supabase_pdo->prepare('select date_attempt from logins where pid = ? and date_attempt > ?');
      $stmt->execute([$user_id, $valid_attempts]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return count($rows) > 10;
   } catch (Throwable $e) {
      error_log('checkbrute (Supabase) failed: ' . $e->getMessage());
      return false;
   }
}

function login($email, $password, $unused = null) {
   global $supabase_pdo;
   // Supabase via PDO only
   if ($supabase_pdo instanceof PDO) {
      try {
         $stmt = $supabase_pdo->prepare('select tid, trainkey, email, password, salt, txtpw from trainee_tbl where email = ? limit 1');
         $stmt->execute([$email]);
         $row = $stmt->fetch(PDO::FETCH_ASSOC);
         if (!$row) {
            return false;
         }
         $user_id = $row['tid'] ?? null;
         $trainkey = $row['trainkey'] ?? '';
         $username = $row['email'] ?? '';
         $db_password = $row['password'] ?? '';
         $salt = $row['salt'] ?? '';
         $txtpw = $row['txtpw'] ?? '';

         if ($trainkey && (!is_numeric($user_id) || $user_id === null)) {
            $repairedId = ensure_trainee_tid($trainkey);
            if ($repairedId !== null) {
               $user_id = $repairedId;
               error_log("login(): repaired missing tid for trainkey {$trainkey} -> {$repairedId}");
            } else {
               error_log("login(): unable to repair missing tid for trainkey {$trainkey}");
            }
         }

         $passwordHashed = hash('sha512', $password.$salt);
         $_SESSION['neednewpw'] = ($txtpw != '') ? 1 : 0;

         if (checkbrute((int)$user_id, null) === true) {
            return false;
         }

         if ($db_password === $passwordHashed) {
            $user_browser = $_SERVER['HTTP_USER_AGENT'];
            $user_id = preg_replace("/[^0-9]+/", "", (string)$user_id);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['trainkey'] = $trainkey;
            $username = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username);
            $_SESSION['username'] = $username;
            $_SESSION['login_string'] = hash('sha512', $passwordHashed.$user_browser);
            return true;
         }

         // Record failed attempt
         try {
            $now = time();
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $ins = $supabase_pdo->prepare('insert into logins (pid, date_attempt, ip_attempt) values (?, ?, ?)');
            $ins->execute([(int)$user_id, $now, $ip_address]);
         } catch (Throwable $e) {
            error_log('Supabase login attempt record failed: ' . $e->getMessage());
         }
         return false;
      } catch (Throwable $e) {
         error_log('login (Supabase) failed: ' . $e->getMessage());
         return false;
      }
   }

   // No backend available
   error_log('login() called without available DB backend');
   return false;
}


function login_check($unused = null) {
   global $supabase_pdo;
   // Check if all session variables are set
   if(isset($_SESSION['user_id'], $_SESSION['login_string'])) {
     $user_id = $_SESSION['user_id'];
     $login_string = $_SESSION['login_string'];
     $ip_address = $_SERVER['REMOTE_ADDR']; // Get the IP address of the user. 
     $user_browser = $_SERVER['HTTP_USER_AGENT']; // Get the user-agent string of the user.
     $session_trainkey = $_SESSION['trainkey'] ?? '';
 
    if ($supabase_pdo instanceof PDO) {
        try {
           $lookupColumn = 'tid';
           $lookupValue = null;

           if ($user_id !== '' && $user_id !== null && is_numeric($user_id)) {
              $lookupValue = (int)$user_id;
           } elseif ($session_trainkey !== '') {
              $lookupColumn = 'trainkey';
              $lookupValue = $session_trainkey;
              $repairedId = ensure_trainee_tid($session_trainkey);
              if ($repairedId !== null) {
                 $_SESSION['user_id'] = (string)$repairedId;
                 $lookupColumn = 'tid';
                 $lookupValue = $repairedId;
                 error_log("login_check: repaired session user_id using trainkey {$session_trainkey}");
              } else {
                 error_log("login_check: proceeding with trainkey fallback for {$session_trainkey}");
              }
           } else {
              error_log('login_check blocked: session user_id missing and no trainkey');
              return false;
           }

           if ($lookupValue === null || $lookupValue === '') {
              error_log('login_check blocked: unable to determine lookup value');
              return false;
           }

           if ($lookupColumn === 'tid') {
              $stmt = $supabase_pdo->prepare('select password from trainee_tbl where tid = ? limit 1');
           } else {
              $stmt = $supabase_pdo->prepare('select password from trainee_tbl where trainkey = ? limit 1');
           }

           $stmt->execute([$lookupValue]);
           $row = $stmt->fetch(PDO::FETCH_ASSOC);
           if (!$row) { return false; }
           $password = $row['password'];
           $login_check = hash('sha512', $password.$user_browser);
           return $login_check == $login_string;
        } catch (Throwable $e) {
           error_log('login_check (Supabase) failed: ' . $e->getMessage());
           return false;
        }
     } else {
        return false;
     }
   } else {
     // Not logged in
     return false;
   }
   
}

function escapeString($value){
  global $supabase_pdo;
  if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()){
    $value=stripslashes($value);
  }
  if ($supabase_pdo instanceof PDO) {
    $quoted = $supabase_pdo->quote($value);
    if ($quoted !== false && strlen($quoted) >= 2) {
      return substr($quoted, 1, -1);
    }
    return $value;
  }
  return $value;
}

$reset_salt = '93b97938d3249309c9f30a25318671e911d0d5f6c06820fc0ee9d92fc3bb1f19fedf55735142864a93ff4e5c128c41c4c613728cf247cc2eab1ecad9e4402448';

function ensure_trainee_tid($trainkey) {
  global $supabase_pdo;
  if (!$supabase_pdo instanceof PDO) {
     return null;
  }
  if (!$trainkey) {
     return null;
  }
  try {
     if (!$supabase_pdo->inTransaction()) {
        $supabase_pdo->beginTransaction();
        $startedTxn = true;
     } else {
        $startedTxn = false;
     }

     $stmt = $supabase_pdo->prepare('select tid from trainee_tbl where trainkey = ? limit 1 for update');
     $stmt->execute([$trainkey]);
     $row = $stmt->fetch(PDO::FETCH_ASSOC);
     $stmt->closeCursor();

     if (!$row) {
        if ($startedTxn) { $supabase_pdo->commit(); }
        return null;
     }

     if (isset($row['tid']) && is_numeric($row['tid']) && (int)$row['tid'] > 0) {
        if ($startedTxn) { $supabase_pdo->commit(); }
        return (int)$row['tid'];
     }

     $nextStmt = $supabase_pdo->query('select coalesce(max(tid), 0) + 1 as next_tid from trainee_tbl');
     $nextTid = (int)$nextStmt->fetchColumn();
     $nextStmt->closeCursor();

     if ($nextTid <= 0) {
        throw new RuntimeException('ensure_trainee_tid: invalid nextTid calculated');
     }

     $update = $supabase_pdo->prepare('update trainee_tbl set tid = ? where trainkey = ?');
     $update->execute([$nextTid, $trainkey]);
     $update->closeCursor();

     if ($startedTxn) { $supabase_pdo->commit(); }

     error_log("ensure_trainee_tid: backfilled tid {$nextTid} for trainkey {$trainkey}");
     return $nextTid;
  } catch (Throwable $e) {
     if (isset($startedTxn) && $startedTxn && $supabase_pdo->inTransaction()) {
        $supabase_pdo->rollBack();
     }
     error_log('ensure_trainee_tid failed: ' . $e->getMessage());
     return null;
  }
}
?>