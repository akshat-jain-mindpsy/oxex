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


// Prefer Supabase (Postgres) first, then fall back to MySQL (AWS RDS)

// --- Supabase (Postgres) setup (optional) ---
$supabase_host = getenv('SUPABASE_DB_HOST') ?: getenv('SUPABASE_HOST');
$supabase_port = getenv('SUPABASE_DB_PORT') ?: 5432;
$supabase_db = getenv('SUPABASE_DB_NAME') ?: getenv('SUPABASE_DATABASE');
$supabase_user = getenv('SUPABASE_DB_USER') ?: getenv('SUPABASE_USER');
$supabase_password = getenv('SUPABASE_DB_PASSWORD') ?: getenv('SUPABASE_PASSWORD');
$supabase_sslmode = getenv('SUPABASE_DB_SSLMODE') ?: 'require';

$supabase_pdo = null;
if ($supabase_host && $supabase_db && $supabase_user && $supabase_password) {
    try {
        $dsn = 'pgsql:host=' . $supabase_host . ';port=' . $supabase_port . ';dbname=' . $supabase_db . ';sslmode=' . $supabase_sslmode;
        $supabase_pdo = new PDO(
            $dsn,
            $supabase_user,
            $supabase_password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
    } catch (PDOException $e) {
        error_log('Supabase connection error: ' . $e->getMessage());
        $supabase_pdo = null;
    }
}

// Helper to quickly ping Supabase
if (!function_exists('testSupabaseConnection')) {
function testSupabaseConnection($pdo) {
    if (!$pdo) {
        return false;
    }
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (Exception $e) {
        error_log('Supabase test failed: ' . $e->getMessage());
        return false;
    }
}}

// Set $pdo to point to the Supabase connection for backward compatibility
$pdo = $supabase_pdo;

// MySQL configuration removed; using Supabase/Postgres exclusively
// $mysqli and ensureConnection intentionally omitted
?>