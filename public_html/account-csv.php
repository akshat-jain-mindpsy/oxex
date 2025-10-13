<?php
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
/* CSV logbook */
$value0 = 0;
$value1 = 1;
$value2 = 2;
$value3 = 3;
$valueblank = '';
$today = date("Ymd");
$todaydisp = strtotime($today);
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
$datestart = 20200101; #TEMP DATES
$dateend = 20991231; #TEMP DATES
$valueyearstart = 20200101;
$valueyearend = 20991231;
$filename = "OXEX_elog_$today.csv";

$which = isset($_GET['which']) ? $_GET['which'] : '';
// Use PDO (Supabase/Postgres) exclusively
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
$pdo = $usingSupabase ? $supabase_pdo : null;

 // find the required trainee via PDO
try {
    if (!$pdo) { throw new RuntimeException('No PDO connection available'); }
    $stmt = $pdo->prepare('select name, who_by, date_added, date_modified, last_used from trainee_tbl where trainkey = ? limit 1');
    $stmt->execute([$which]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $name = $row['name'] ?? '';
    $who_by = $row['who_by'] ?? '';
    $date_added = $row['date_added'] ?? null;
    $date_modified = $row['date_modified'] ?? null;
    $last_used = $row['last_used'] ?? null;
} catch (Throwable $e) {
    error_log('account-csv.php trainee fetch (PDO) failed: ' . $e->getMessage());
    $name = '';
}
   $date_added = $date_added ? strtotime($date_added) : false;


$firstline = "$name,".date("D jS M Y", $todaydisp)."\n";
$nextline = "";
$tabctr = 0;
try {
    if (!$pdo) { throw new RuntimeException('No PDO connection available'); }
    $tables = [];
    $tstmt = $pdo->query('select tbid, tab_name from tabs_tbl order by tab_name');
    while ($r = $tstmt->fetch(PDO::FETCH_ASSOC)) { $tables[] = $r; }
    foreach ($tables as $trow) {
        $tbid = (int)$trow['tbid'];
        $tab_name = $trow['tab_name'];
        if ($tabctr > 0) {
            $nextline = $nextline."\n\n";
        }
        $nextline = $nextline."$tab_name\n";
        $numpass = 0;
        $who_notes = '';
        $date_signed = null;
        $ps = $pdo->prepare('select who_by, date_added from trainee_report_ok where super_pass = ? and trainkey = ? limit 1');
        $ps->execute([$tbid, $which]);
        if ($row = $ps->fetch(PDO::FETCH_ASSOC)) {
            $numpass = 1;
            $who_notes = $row['who_by'];
            $date_signed = $row['date_added'];
        }
        $date_signed = $date_signed ? strtotime($date_signed) : false;
        if ($numpass > 0) {
            $ns = $pdo->prepare('select realname from who_there where usrkey = ? limit 1');
            $ns->execute([$who_notes]);
            $supername = ($tmp = $ns->fetch(PDO::FETCH_ASSOC)) ? $tmp['realname'] : '';
            $date_display = $date_signed ? date("D jS M Y", $date_signed) : 'N/A';
            $nextline = $nextline."Signed off: , ".$supername.",".$date_display."\n";
        }

        $tabctr++;

        // headers for this table
        $hdr = $pdo->prepare('select f.stid, s.str from tab_fields f join select_types s on f.stid = s.stid where f.tbid = ? and f.sort_order != ? order by f.sort_order asc');
        $hdr->execute([$tbid, $value0]);
        while ($h = $hdr->fetch(PDO::FETCH_ASSOC)) {
            $nextline = $nextline.$h['str'].",";
        }
        $nextline = $nextline."\n";

        // dataset rows (one per logkey)
        $dq = $pdo->prepare('select date_added, logkey from trainee_log where trainkey = ? and tbid = ? and date_added >= ? and date_added <= ? group by logkey, date_added order by date_added desc');
        $dq->execute([$which, $tbid, $valueyearstart, $valueyearend]);
        while ($d = $dq->fetch(PDO::FETCH_ASSOC)) {
            $date_added = $d['date_added'];
            $tablelogkey = $d['logkey'];
            $exlogdate = strtotime($date_added);
            $exlogdate = date("d-m-Y", $exlogdate);
            $ctr = 0;
            // iterate same fields order as headers
            $rs = $pdo->prepare('select f.stid, s.single from tab_fields f join select_types s on f.stid = s.stid where f.tbid = ? and f.sort_order != ? order by f.sort_order asc');
            $rs->execute([$tbid, $value0]);
            while ($rowf = $rs->fetch(PDO::FETCH_ASSOC)) {
                $stid = (int)$rowf['stid'];
                $single = (int)$rowf['single'];
                $exselect_val = '';
                $expid = null;
                // value lookup
                $vs = $pdo->prepare('select tlogid, pid, select_val from trainee_log where trainkey = ? and stid = ? and logkey = ? limit 1');
                $vs->execute([$which, $stid, $tablelogkey]);
                if ($vr = $vs->fetch(PDO::FETCH_ASSOC)) {
                    $expid = $vr['pid'];
                    $exselect_val = $vr['select_val'];
                }

                if ($single == 2 || $single == 4 || $single == 5 || $single == 6) {
                    if ($exselect_val == '' && $ctr == 0) { $exselect_val = 'N/A'; }
                    $nextline = $nextline.$exselect_val.",";
                }
                if ($single == 0) {
                    $gs = $pdo->prepare('select select_val from select_gen where pid = ? limit 1');
                    $gs->execute([(int)$expid]);
                    $val = ($tmp = $gs->fetch(PDO::FETCH_NUM)) ? $tmp[0] : '';
                    $nextline = $nextline.$val.",";
                }
                if ($single == 3) {
                    if ($exselect_val && $exselect_val > 1) {
                        $exselect_val = strtotime($exselect_val);
                        $nextline = $nextline.date("d-m-y", $exselect_val).",";
                    } else {
                        $nextline = $nextline." ,";
                    }
                }
                if ($single == 1) {
                    $exarr = array();
                    $fs = $pdo->prepare('select pid from trainee_log where stid = ? and logkey = ?');
                    $fs->execute([$stid, $tablelogkey]);
                    while ($fr = $fs->fetch(PDO::FETCH_ASSOC)) { $exarr[] = (int)$fr['pid']; }
                    $exarruq = array_unique($exarr);
                    $exselect_val = '';
                    foreach ($exarruq as $x) {
                        $gs = $pdo->prepare('select select_val from select_gen where pid = ? limit 1');
                        $gs->execute([(int)$x]);
                        $mult_val = ($tmp = $gs->fetch(PDO::FETCH_NUM)) ? $tmp[0] : '';
                        $exselect_val = $exselect_val." ".$mult_val;
                    }
                    $nextline = $nextline.$exselect_val.",";
                    $exselect_val = '';
                    unset($exarruq);
                }
                $ctr++;
            }
            $nextline = $nextline."\n";
        }
    }
} catch (Throwable $e) {
    error_log('account-csv.php export failed (PDO): ' . $e->getMessage());
}


// create file
$finalline = $firstline.$nextline;
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Length: " . strlen($finalline));
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$filename");
echo $finalline;
?>