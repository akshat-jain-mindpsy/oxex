<?php
// Include necessary configuration and database connection
include_once __DIR__ . '/../../OXEXfolder/config.php';
include_once __DIR__ . '/../../OXEXfolder/u_functions.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Logging function
function custom_log($message) {
	error_log('[SESSION DEBUG] ' . $message);
}

// Robust redirect helper with header + JS/meta fallbacks
function redirect_to_login($reason) {
	$login_url = 'login.html?error=' . urlencode($reason);
	// Prefer HTTP redirect if headers aren't sent yet
	if (!headers_sent()) {
		http_response_code(302);
		header('Location: ' . $login_url);
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
		// Echo minimal HTML fallback for overly strict clients
		echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') . '"><script>window.location.replace(' . json_encode($login_url) . ');</script></head><body><noscript><a href="' . htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') . '">Continue to login</a></noscript></body></html>';
		session_write_close();
		exit();
	}
	// Headers already sent: use JS/meta fallback only
	echo '<script>window.location.replace(' . json_encode($login_url) . ');</script>';
	echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
	session_write_close();
	exit();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
	sec_session_start();
}

// Log session status and configuration
custom_log('Session status: ' . session_status());
custom_log('Session ID: ' . session_id());
custom_log('Session name: ' . session_name());
custom_log('Session save path: ' . session_save_path());

// Check if session cookie is being sent
if (isset($_COOKIE[session_name()])) {
	custom_log('Session cookie found: ' . $_COOKIE[session_name()]);
} else {
	custom_log('Session cookie not found in request');
}

// Log all cookies for debugging
custom_log('All cookies: ' . print_r($_COOKIE, true));

// Log all session variables for debugging
custom_log('Full Session Data: ' . print_r($_SESSION, true));

// Validate required session variables
$required_session_vars = ['user_id', 'username', 'login_string', 'admintype', 'usrkey'];
$missing_vars = [];

foreach ($required_session_vars as $var) {
	if (!isset($_SESSION[$var]) || empty($_SESSION[$var])) {
		$missing_vars[] = $var;
	}
}

if (!empty($missing_vars)) {
	custom_log('Missing session variables: ' . implode(', ', $missing_vars));
	
	// Check if this is an AJAX request
	if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		header('Content-Type: application/json');
		echo json_encode(['status' => 'error', 'message' => 'Session incomplete', 'redirect' => 'login.html?error=session_incomplete']);
		exit();
	} else {
		redirect_to_login('session_incomplete');
	}
}

// Perform login check
if (!login_check($mysqli)) {
	custom_log('Login check failed');
	
	// Check if this is an AJAX request
	if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		header('Content-Type: application/json');
		echo json_encode(['status' => 'error', 'message' => 'Session expired', 'redirect' => 'login.html?error=session_expired']);
		exit();
	} else {
		redirect_to_login('session_expired');
	}
}

// Additional admin type validation
$allowed_admin_types = ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'];
if (!in_array($_SESSION['admintype'], $allowed_admin_types)) {
	custom_log('Unauthorized admin type: ' . $_SESSION['admintype']);
	
	// Check if this is an AJAX request
	if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		header('Content-Type: application/json');
		echo json_encode(['status' => 'error', 'message' => 'Unauthorized access', 'redirect' => 'login.html?error=unauthorized']);
		exit();
	} else {
		redirect_to_login('unauthorized');
	}
}

// Fetch additional user details for verification
$usrkey = $_SESSION['usrkey'];
$stmt = $mysqli->prepare("SELECT realname, email, isonline, photo, admintype FROM who_there WHERE usrkey = ?");
$stmt->bind_param("s", $usrkey);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
	custom_log('No user found with usrkey: ' . $usrkey);
	
	// Check if this is an AJAX request
	if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		header('Content-Type: application/json');
		echo json_encode(['status' => 'error', 'message' => 'User not found', 'redirect' => 'login.html?error=user_not_found']);
		exit();
	} else {
		redirect_to_login('user_not_found');
	}
}

$stmt->bind_result($realname, $email, $isonline, $photo, $admintype);
$stmt->fetch();
$stmt->close();

// Verify admin type consistency
if ($admintype !== $_SESSION['admintype']) {
	custom_log('Admin type mismatch. Session: ' . $_SESSION['admintype'] . ', Database: ' . $admintype);
	
	// Check if this is an AJAX request
	if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		header('Content-Type: application/json');
		echo json_encode(['status' => 'error', 'message' => 'Admin type mismatch', 'redirect' => 'login.html?error=type_mismatch']);
		exit();
	} else {
		redirect_to_login('type_mismatch');
	}
}

// Set additional variables
$adminname = $realname;
$adminphoto = $photo;

// Additional page-specific logic remains the same
$thispage = $_SERVER['REQUEST_URI'];
$thispage = str_replace("/oxex-admin/", "", $thispage);
$querystr = '?' . $_SERVER['QUERY_STRING'];
$thispage = str_replace($querystr, "", $thispage);

// Logging successful session validation
custom_log('Session validation passed for user: ' . $adminname);

// Add these lines near the end of the file
$global_short_name = 'OXEX Admin';
$adminstatus = $isonline ? 'Active' : 'Offline';
$whichDocModal = ''; // Default empty, can be set by specific pages
?>