<?PHP
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

header('Content-Type: text/html; charset=utf-8');

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = isset($_GET['page_size']) ? max(1, min(200, (int)$_GET['page_size'])) : 25;
$offset = ($page - 1) * $pageSize;

$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if (!$pdo) {
  http_response_code(500);
  echo '<tr><td colspan="6">Database unavailable</td></tr>';
  exit;
}

$tableset = $pdo->prepare("SELECT rmid, tbid, report_title, date_modified, situation, stid, valtype, report_notes, elldee, age FROM report_manager ORDER BY tbid, sort_order LIMIT :limit OFFSET :offset");
$tableset->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$tableset->bindValue(':offset', $offset, PDO::PARAM_INT);
$tableset->execute();
$rows = $tableset->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r){
  $rmid = $r['rmid'];
  $tbid = $r['tbid'];
  $report_title = $r['report_title'];
  $date_modified = strtotime($r['date_modified']);
  $situation = (int)$r['situation'];
  $stid = $r['stid'];
  $valtype = (int)$r['valtype'];
  $report_notes = $r['report_notes'];
  $elldee = (int)$r['elldee'];
  $age = (int)$r['age'];

  $tab_name = "Unknown";
  $stmt = $pdo->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
  $stmt->execute([$tbid]);
  $tab_name = $stmt->fetchColumn() ?: $tab_name;

  $stmt = $pdo->prepare("SELECT str FROM select_types WHERE stid = ?");
  $stmt->execute([$stid]);
  $str = $stmt->fetchColumn();

  echo "<tr>\n";
  echo " <td><a href=\"reportdetail.php?which=$rmid\">$report_title <br><small>".date('D jS M Y', $date_modified)."</small><div><small>".htmlspecialchars($report_notes)."</small></div></a></td>\n";
  echo " <td><small>".htmlspecialchars($tab_name)."</small></td>\n";
  echo " <td><small>".htmlspecialchars($str)."<br>(".$stid.")</small></td>\n";
  echo " <td><small>".($elldee == 1 ? 'LD' : 'CYP')."</small></td>\n";
  echo " <td><small>".($age == 0 ? 'All' : ($age == 1 ? 'Children' : 'Adults'))."</small></td>\n";
  echo " <td>\n";
  $dataset = $pdo->prepare("SELECT valuetxt, valuea, valueb FROM report_data WHERE rmid = ? ");
  $dataset->execute([$rmid]);
  $data_rows = $dataset->fetchAll(PDO::FETCH_ASSOC);
  foreach ($data_rows as $d){
    $valuetxt = $d['valuetxt'];
    $valuea = $d['valuea'];
    $valueb = $d['valueb'];
    if ($valtype == 0 || $valtype == 2) {
      $stmt = $pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
      $stmt->execute([$valuea]);
      $select_val = $stmt->fetchColumn();
      echo "<small>".htmlspecialchars($select_val)."</small><br>";
    } else {
      echo "<small>".htmlspecialchars($valuea)." - ".htmlspecialchars($valueb)."</small><br>";
    }
  }
  echo " </td>\n";
  echo "</tr>\n";
}
?>

