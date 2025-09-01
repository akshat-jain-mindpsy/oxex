<?php
// No output before session starts
error_reporting(E_ALL);
ini_set('display_errors', 1);



// Function to load .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found at: ' . $path);
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Load .env file (adjust path as needed)
try {
    // Try multiple possible .env file locations
    $env_paths = [
        __DIR__ . '/../../.env',           // Local development
        __DIR__ . '/../.env',              // Docker container
        '/var/www/html/.env'               // Docker container alternative
    ];
    
    $env_loaded = false;
    foreach ($env_paths as $env_path) {
        if (file_exists($env_path)) {
            loadEnv($env_path);
            $env_loaded = true;
            break;
        }
    }
    
    if (!$env_loaded) {
        throw new Exception('No .env file found in any expected location');
    }
} catch (Exception $e) {
    die('Error loading .env file: ' . $e->getMessage());
}


// Database configuration for AWS RDS
// Load from environment variables in .env file
$mysql_host = getenv('MYSQL_HOST');
$mysql_port = getenv('MYSQL_PORT') ?: 3306; // Default to 3306 if not set
$mysql_user = getenv('MYSQL_USER');
$mysql_password = getenv('MYSQL_PASSWORD');
$mysql_database = getenv('MYSQL_DATABASE');

// Validate required environment variables
if (!$mysql_host || !$mysql_user || !$mysql_password || !$mysql_database) {
    die('Error: Missing required database environment variables. Please check your .env file.');
}

// Connect to AWS RDS MySQL database with persistent connection
$mysqli = new mysqli('p:' . $mysql_host, $mysql_user, $mysql_password, $mysql_database, $mysql_port);

if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

// Set charset to ensure proper encoding
$mysqli->set_charset("utf8mb4");

// Set connection timeout and other settings to prevent "server has gone away"
$mysqli->query("SET SESSION wait_timeout=28800"); // 8 hours
$mysqli->query("SET SESSION interactive_timeout=28800"); // 8 hours
$mysqli->query("SET SESSION net_read_timeout=60"); // 60 seconds
$mysqli->query("SET SESSION net_write_timeout=60"); // 60 seconds

// Function to check and reconnect if needed
function ensureConnection($mysqli) {
    if (!$mysqli->ping()) {
        error_log("Database connection lost, attempting to reconnect...");
        $mysqli->close();
        
        // Reconnect
        global $mysql_host, $mysql_user, $mysql_password, $mysql_database, $mysql_port;
        $mysqli = new mysqli('p:' . $mysql_host, $mysql_user, $mysql_password, $mysql_database, $mysql_port);
        
        if ($mysqli->connect_error) {
            error_log("Reconnection failed: " . $mysqli->connect_error);
            return false;
        }
        
        $mysqli->set_charset("utf8mb4");
        $mysqli->query("SET SESSION wait_timeout=28800");
        $mysqli->query("SET SESSION interactive_timeout=28800");
        $mysqli->query("SET SESSION net_read_timeout=60");
        $mysqli->query("SET SESSION net_write_timeout=60");
        
        error_log("Database reconnected successfully");
    }
    return $mysqli;
}
?>