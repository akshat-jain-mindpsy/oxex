<?php
// Standalone Supabase connectivity test with browser console logs
// Does not depend on OXEX config to avoid fatal die() on missing env

error_reporting(E_ALL);
ini_set('display_errors', 1);

function loadEnvStandalone($paths) {
    foreach ($paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            continue;
        }
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim(trim($value), "'\"");
                if (!getenv($key)) {
                    putenv($key . '=' . $value);
                    $_ENV[$key] = $value;
                }
            }
        }
        // Stop at the first .env found
        break;
    }
}

$envPaths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    '/var/www/html/.env',
];
loadEnvStandalone($envPaths);

// Collect env variables
$supabase_host = getenv('SUPABASE_DB_HOST') ?: getenv('SUPABASE_HOST');
$supabase_port = getenv('SUPABASE_DB_PORT') ?: 5432;
$supabase_db = getenv('SUPABASE_DB_NAME') ?: getenv('SUPABASE_DATABASE');
$supabase_user = getenv('SUPABASE_DB_USER') ?: getenv('SUPABASE_USER');
$supabase_password = getenv('SUPABASE_DB_PASSWORD') ?: getenv('SUPABASE_PASSWORD');
$supabase_sslmode = getenv('SUPABASE_DB_SSLMODE') ?: 'require';

$result = [
    'env' => [
        'host_present' => (bool) $supabase_host,
        'port' => (int) $supabase_port,
        'db_present' => (bool) $supabase_db,
        'user_present' => (bool) $supabase_user,
        'sslmode' => $supabase_sslmode,
    ],
    'connected' => false,
    'details' => null,
    'error' => null,
];

// Try connect if we have minimum env
if ($supabase_host && $supabase_db && $supabase_user && $supabase_password) {
    try {
        $dsn = 'pgsql:host=' . $supabase_host . ';port=' . $supabase_port . ';dbname=' . $supabase_db . ';sslmode=' . $supabase_sslmode;
        $pdo = new PDO($dsn, $supabase_user, $supabase_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        // Sanity check
        $pdo->query('SELECT 1');
        $result['connected'] = true;
        $stmt = $pdo->query('select current_database() as db, current_user as user_name');
        $row = $stmt ? $stmt->fetch() : null;
        if ($row) {
            $result['details'] = $row;
        }
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }
} else {
    $result['error'] = 'Missing required env: host, db, user or password';
}

// Output simple HTML with JSON block and console logs
?><!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Supabase Connectivity Test</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 16px;">
    <h2>Supabase Connectivity Test</h2>
    <p>Status: <strong><?php echo $result['connected'] ? 'Connected' : 'Not Connected'; ?></strong></p>
    <pre id="result" style="background:#f6f8fa; padding:12px; border-radius:6px; overflow:auto; max-width:100%;
        border:1px solid #e1e4e8;">
<?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)); ?>
    </pre>
    <script>
        (function () {
            try {
                var data = <?php echo json_encode($result); ?>;
                console.group('Supabase Test');
                console.log('Env:', data.env);
                console.log('Connected:', data.connected);
                if (data.details) console.log('Details:', data.details);
                if (data.error) console.error('Error:', data.error);
                console.groupEnd();
            } catch (e) {
                console.error('Failed to print console logs:', e);
            }
        })();
    </script>
</body>
</html>
