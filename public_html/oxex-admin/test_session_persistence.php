<?php
// Test script to check session persistence across requests
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Logging function
function custom_log($message) {
    error_log('[SESSION PERSISTENCE TEST] ' . $message);
}

custom_log('Starting session persistence test');

// Start session
sec_session_start();

custom_log('Session ID: ' . session_id());

// Check if this is the first request or a follow-up request
if (isset($_GET['test'])) {
    custom_log('Follow-up request detected');
    
    // Check if session variable exists
    if (isset($_SESSION['test_persistence'])) {
        custom_log('Session variable found: ' . $_SESSION['test_persistence']);
        echo "SUCCESS: Session variable persisted across requests<br>";
        echo "Session ID: " . session_id() . "<br>";
        echo "Session variable: " . $_SESSION['test_persistence'] . "<br>";
    } else {
        custom_log('Session variable not found');
        echo "FAILURE: Session variable did not persist<br>";
        echo "Session ID: " . session_id() . "<br>";
        echo "All session variables: " . print_r($_SESSION, true) . "<br>";
    }
} else {
    custom_log('First request - setting session variable');
    
    // Set a test session variable
    $_SESSION['test_persistence'] = 'test_value_' . time();
    custom_log('Set session variable: ' . $_SESSION['test_persistence']);
    
    echo "Session variable set. <a href='?test=1'>Click here to test persistence</a><br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Session variable: " . $_SESSION['test_persistence'] . "<br>";
}
?> 