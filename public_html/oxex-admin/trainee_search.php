<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

header('Content-Type: application/json; charset=utf-8');

// Basic auth check
if (!login_check($pdo)) {
    http_response_code(401);
    echo json_encode([ 'success' => false, 'error' => 'Unauthorized' ]);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '' || strlen($q) < 2) {
    echo json_encode([ 'success' => true, 'results' => [] ]);
    exit;
}

$limit = 20;

try {
    // Build base query with permissions
    $where = [];
    $params = [];

    // Name search
    $where[] = 't.name LIKE ?';
    $params[] = '%' . $q . '%';

    // Permission scoping
    if ($admintype === 'SO' || $admintype === 'SE') {
        $where[] = '(t.supervisor = ? OR t.supervisor2 = ? OR t.supervisor3 = ? OR t.tutor = ?)';
        array_push($params, $usrkey, $usrkey, $usrkey, $usrkey);
    } elseif ($admintype === 'AO' || $admintype === 'AE') {
        // Oxford/Exeter Admins limited by their uni ident (AO => OX, AE => EX) if such rule applies
        $ident = ($admintype === 'AO') ? 'OX' : 'EX';
        $where[] = 'u.ident = ?';
        $params[] = $ident;
    } else {
        // AT (super admin) and DV (dev) can see all
    }

    $whereSql = '';
    if (count($where) > 0) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    $sql = "SELECT t.trainkey, t.name, t.year
            FROM trainee_tbl t
            LEFT JOIN uni_tbl u ON u.uid = t.uid
            $whereSql
            ORDER BY t.name ASC
            LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $results[] = [
            'trainkey' => $row['trainkey'],
            'name' => $row['name'],
            'year' => $row['year'],
        ];
    }

    echo json_encode([ 'success' => true, 'results' => $results ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([ 'success' => false, 'error' => 'Server error' ]);
}
?>

