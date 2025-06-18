<?php
// No output before session starts
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$mysqli = new mysqli(
    'localhost:8889',
    'root',
    'root',
    'OXICPTR-3136399cca'
);

if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
// Remove or comment out any echo statements here
?>