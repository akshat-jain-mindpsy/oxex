<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
// update their last status to logged out
$value0 = 0;
$usrkey = $_SESSION['usrkey'];
$stmt = $supabase_pdo->prepare("UPDATE who_there SET isonline = ?, lastlogin = ? WHERE usrkey = ?"); 
$stmt->execute([$value0, $value0, $usrkey]);

// Unset all session values
$_SESSION = array();
// get session parameters 
$params = session_get_cookie_params();
// Delete the actual cookie.
setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
// Destroy session
session_destroy();
header('Location: login.html');
?>
