<?php
// Prevent any output before headers
ob_start();

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
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Prefer Supabase (Postgres) only; disable mysqli fallback
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);

// Check if the template table exists and if the requested template exists
$template_exists = false;
if ($template_id > 0) {
    if ($usingSupabase) {
        try {
            $check_stmt = $supabase_pdo->prepare("select 1 from information_schema.tables where table_name = 'csv_templates' limit 1");
            $check_stmt->execute();
            if ($check_stmt->fetch(PDO::FETCH_NUM)) {
                $template_check = $supabase_pdo->prepare("select id from csv_templates where id = ? limit 1");
                $template_check->execute([$template_id]);
                if ($template_check->fetch(PDO::FETCH_NUM)) {
                    $template_exists = true;
                }
            }
        } catch (Throwable $e) {
            error_log('csv-logbook: Supabase template check failed: ' . $e->getMessage());
        }
    }
}

// Use template if it exists, otherwise use default CSV
if ($template_exists) {
    generateTemplateCSV_PDO($supabase_pdo, $which, $template_id, $start_date, $end_date);
} else {
    generateDefaultCSV_PDO($supabase_pdo, $which);
}
exit(); // Add exit to prevent any additional output


/**
 * Generate CSV using a selected template (PDO/Postgres)
 */
function generateTemplateCSV_PDO($pdo, $trainee_key, $template_id, $start_date, $end_date) {
	$today = date("Ymd");
	$todaydisp = strtotime($today);
	if (empty($trainee_key)) {
		generateDefaultCSV_PDO($pdo, $trainee_key);
		return;
	}
	try {
		// Trainee name
		$stmt = $pdo->prepare('select name from trainee_tbl where trainkey = ? limit 1');
		$stmt->execute([$trainee_key]);
		$name = ($r = $stmt->fetch(PDO::FETCH_ASSOC)) ? $r['name'] : '';

		// Template
		$tq = $pdo->prepare('select id, template_name, description from csv_templates where id = ? limit 1');
		$tq->execute([$template_id]);
		$template = $tq->fetch(PDO::FETCH_ASSOC);
		if (!$template) {
			generateDefaultCSV_PDO($pdo, $trainee_key);
			return;
		}

		$delimiter = ',';
		$enclosure = '"';
		$include_header = true;
		$max_rows = 1000;

		// Columns for template
		$cq = $pdo->prepare('select c.id, c.field_id, c.table_id, c.display_order, t.tab_name as table_name, s.str as field_name from csv_template_columns c left join tabs_tbl t on c.table_id = t.tbid left join select_types s on c.field_id = s.stid where c.template_id = ? order by c.display_order');
		$cq->execute([$template_id]);
		$columns = $cq->fetchAll(PDO::FETCH_ASSOC);
		if (!$columns) {
			generateDefaultCSV_PDO($pdo, $trainee_key);
			return;
		}

		// Get logkeys with optional date filtering
		$params = [$trainee_key];
		$w = ' where trainkey = ?';
		if ($start_date) { $w .= ' and date_added >= ?'; $params[] = (int)str_replace('-', '', $start_date); }
		if ($end_date) { $w .= ' and date_added <= ?'; $params[] = (int)str_replace('-', '', $end_date); }
		$params[] = 1000;
		$dq = $pdo->prepare('select logkey from trainee_log' . $w . ' group by logkey order by max(date_added) desc limit ?');
		$dq->execute($params);

		// Group columns by table
		$tables = [];
		foreach ($columns as $column) {
			$table_id = $column['table_id'];
			if (!isset($tables[$table_id])) {
				$tables[$table_id] = [ 'name' => $column['table_name'], 'columns' => [] ];
			}
			$tables[$table_id]['columns'][] = $column;
		}

		$sanitized_template_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template['template_name']);
		$filename = "OXEX_{$sanitized_template_name}_{$today}.csv";
		$csv_output = "";
		$csv_output .= "$name," . date("D jS M Y", $todaydisp) . "\n";
		$csv_output .= "Template: {$template['template_name']}\n";
		if (!empty($template['description'])) { $csv_output .= "Description: {$template['description']}\n"; }
		$csv_output .= "\n";

		$all_row_data = [];
		while ($row = $dq->fetch(PDO::FETCH_ASSOC)) {
			$logkey = $row['logkey'];
			$entry_data = ['logkey' => $logkey];
			foreach ($columns as $column) {
				$field_id = (int)$column['field_id'];
				$table_id = (int)$column['table_id'];
				$vq = $pdo->prepare('select tl.select_val, tl.pid, st.single from trainee_log tl join select_types st on tl.stid = st.stid where tl.trainkey = ? and tl.logkey = ? and tl.stid = ? limit 1');
				$vq->execute([$trainee_key, $logkey, $field_id]);
				$field_data = $vq->fetch(PDO::FETCH_ASSOC);
				$field_value = '';
				if ($field_data) {
					switch ((int)$field_data['single']) {
						case 0:
							if (!empty($field_data['pid'])) {
								$lq = $pdo->prepare('select select_val from select_gen where pid = ? limit 1');
								$lq->execute([(int)$field_data['pid']]);
								$tmp = $lq->fetch(PDO::FETCH_ASSOC);
								$field_value = $tmp ? $tmp['select_val'] : '';
							}
							break;
						case 1:
							$mq = $pdo->prepare('select sg.select_val from select_gen sg join trainee_log tl on sg.pid = tl.pid where tl.trainkey = ? and tl.logkey = ? and tl.stid = ?');
							$mq->execute([$trainee_key, $logkey, $field_id]);
							$vals = [];
							while ($mr = $mq->fetch(PDO::FETCH_ASSOC)) { $vals[] = $mr['select_val']; }
							$field_value = implode(', ', $vals);
							break;
						case 3:
							if (!empty($field_data['select_val'])) {
								$ts = strtotime($field_data['select_val']);
								if ($ts) { $field_value = date('d-m-Y', $ts); }
							}
							break;
						default:
							$field_value = $field_data['select_val'] ?? '';
					}
				}
				if ($field_value === null || $field_value === false) { $field_value = ''; }
				$key = $table_id . '_' . $field_id;
				$entry_data[$key] = $field_value;
			}
			$all_row_data[] = $entry_data;
		}

		foreach ($tables as $table_id => $table) {
			$csv_output .= $table['name'] . "\n";
			if ($include_header) {
				foreach ($table['columns'] as $column) {
					$csv_output .= $enclosure . str_replace($enclosure, $enclosure.$enclosure, $column['field_name']) . $enclosure . $delimiter;
				}
				$csv_output = rtrim($csv_output, $delimiter) . "\n";
			}
			foreach ($all_row_data as $row_data) {
				$has_data = false;
				foreach ($table['columns'] as $column) {
					$key = $table_id . '_' . $column['field_id'];
					$value = isset($row_data[$key]) ? $row_data[$key] : '';
					if (!empty($value)) { $has_data = true; }
					$csv_output .= $enclosure . str_replace($enclosure, $enclosure.$enclosure, $value) . $enclosure . $delimiter;
				}
				if ($has_data) { $csv_output = rtrim($csv_output, $delimiter) . "\n"; }
			}
			$csv_output .= "\n\n";
		}

		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Length: " . strlen($csv_output));
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=$filename");
		echo $csv_output;
		exit();
	} catch (Throwable $e) {
		error_log('csv-logbook PDO template export failed: ' . $e->getMessage());
		generateDefaultCSV_PDO($pdo, $trainee_key);
	}
}

/**
 * Generate CSV using the default format (PDO/Postgres)
 */
function generateDefaultCSV_PDO($pdo, $which) {
	$value0 = 0;
	$value1 = 1;
	$today = date("Ymd");
	$todaydisp = strtotime($today);
	$filename = "OXEX_elog_$today.csv";

	// Trainee
	$stmt = $pdo->prepare('select name, who_by, date_added, date_modified, last_used from trainee_tbl where trainkey = ? limit 1');
	$stmt->execute([$which]);
	$trow = $stmt->fetch(PDO::FETCH_ASSOC);
	$name = $trow['name'] ?? '';

	$firstline = "$name,".date("D jS M Y", $todaydisp)."\n";
	$nextline = "";
	$tabctr = 0;

	$generic_tbid = 1;
	$CSarr = array(11, 14, 15, 16);

	// Generic tab header
	$tableset = $pdo->prepare('select tbid, tab_name from tabs_tbl where tbid = ?');
	$tableset->execute([$generic_tbid]);
	while ($t = $tableset->fetch(PDO::FETCH_ASSOC)) {
		$tbid = (int)$t['tbid'];
		$tab_name = $t['tab_name'];
		if ($tabctr > 0) { $nextline .= "\n\n"; }
		$nextline .= "$tab_name\n\n";
		// pass status
		$ps = $pdo->prepare('select who_by, date_added from trainee_report_ok where super_pass = ? and trainkey = ? limit 1');
		$ps->execute([$tbid, $which]);
		if ($pr = $ps->fetch(PDO::FETCH_ASSOC)) {
			$who_notes = $pr['who_by'];
			$date_signed = $pr['date_added'] ? strtotime($pr['date_added']) : '';
			$ns = $pdo->prepare('select realname from who_there where usrkey = ? limit 1');
			$ns->execute([$who_notes]);
			$supername = ($tmp = $ns->fetch(PDO::FETCH_ASSOC)) ? $tmp['realname'] : '';
			$nextline .= "Signed off: , ".$supername.",".($date_signed ? date("D jS M Y", $date_signed) : 'N/A')."\n\n";
		}
		// headers
		$tabset = $pdo->prepare('select f.stid, s.str from tab_fields f join select_types s on f.stid = s.stid where f.tbid = ? and f.sort_order != ? order by f.sort_order asc');
		$tabset->execute([$generic_tbid, $value0]);
		while ($tr = $tabset->fetch(PDO::FETCH_ASSOC)) { $nextline .= $tr['str'] . ','; }
		$nextline .= "\n";
		// dataset rows
		$dataset = $pdo->prepare('select max(date_added) as date_added, logkey from trainee_log where trainkey = ? and tbid = ? group by logkey order by max(date_added) desc');
		$dataset->execute([$which, $generic_tbid]);
		while ($dr = $dataset->fetch(PDO::FETCH_ASSOC)) {
			$date_added = $dr['date_added'];
			$tablelogkey = $dr['logkey'];
			$exlogdate = date('d-m-Y', strtotime($date_added));
			$ctr = 0;
			$resultset = $pdo->prepare('select f.stid, s.single from tab_fields f join select_types s on f.stid = s.stid where f.tbid = ? and f.sort_order != ? order by f.sort_order asc');
			$resultset->execute([$generic_tbid, $value0]);
			while ($rr = $resultset->fetch(PDO::FETCH_ASSOC)) {
				$stid = (int)$rr['stid'];
				$single = (int)$rr['single'];
				$exselect_val = '';
				$expid = null;
				$sv = $pdo->prepare('select tlogid, pid, select_val from trainee_log where trainkey = ? and stid = ? and logkey = ? limit 1');
				$sv->execute([$which, $stid, $tablelogkey]);
				if ($svr = $sv->fetch(PDO::FETCH_ASSOC)) { $expid = $svr['pid']; $exselect_val = $svr['select_val']; }
				if ($single == 2 || $single == 4 || $single == 5 || $single == 6) {
					if ($exselect_val == '' && $ctr == 0) { $exselect_val = 'N/A'; }
					$nextline .= $exselect_val . ',';
				}
				if ($single == 0) {
					$gs = $pdo->prepare('select select_val from select_gen where pid = ? limit 1');
					$gs->execute([(int)$expid]);
					$val = ($tmp = $gs->fetch(PDO::FETCH_NUM)) ? $tmp[0] : '';
					$nextline .= $val . ',';
				}
				if ($single == 3) {
					if (!empty($exselect_val) && $exselect_val > 1) {
						$ts = strtotime($exselect_val);
						$nextline .= date('d-m-y', $ts) . ',';
					} else {
						$nextline .= ' ,';
					}
				}
				$ctr++;
			}
			$nextline .= "\n";
		}
		$nextline .= "\n\n\n";
		$tabctr++;
	}

	// Special case tabs
	foreach ($CSarr as $CSvalue) {
		$stmt = $pdo->prepare('select tbid, tab_name from tabs_tbl where tbid = ?');
		$stmt->execute([$CSvalue]);
		if ($tr = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$tbid = (int)$tr['tbid'];
			$tab_name = $tr['tab_name'];
			$nextline .= "$tab_name\n\n";
			$ps = $pdo->prepare('select who_by, date_added from trainee_report_ok where super_pass = ? and trainkey = ? limit 1');
			$ps->execute([$tbid, $which]);
			if ($pr = $ps->fetch(PDO::FETCH_ASSOC)) {
				$who_notes = $pr['who_by'];
				$date_signed = $pr['date_added'] ? strtotime($pr['date_added']) : '';
				$ns = $pdo->prepare('select realname from who_there where usrkey = ? limit 1');
				$ns->execute([$who_notes]);
				$supername = ($tmp = $ns->fetch(PDO::FETCH_ASSOC)) ? $tmp['realname'] : '';
				$nextline = $nextline."Signed off: , ".$supername.",".($date_signed ? date("D jS M Y", $date_signed) : 'N/A')."\n\n";
			}
			$tabset = $pdo->prepare('select f.stid, s.str, s.single from tab_fields f join select_types s on f.stid = s.stid where f.tbid = ? and f.sort_order != ? order by f.sort_order asc');
			$tabset->execute([$value1, $value0]);
			while ($tr2 = $tabset->fetch(PDO::FETCH_ASSOC)) { $nextline .= $tr2['str'] . ','; }
			$nextline .= "\n";
			if ($CSvalue == 11) { $stidchk1 = 2; $pidchk1 = 4; $stidchk2 = 75; $pidchk2 = 654; }
			if ($CSvalue == 14) { $stidchk1 = 2; $pidchk1 = 1; $stidchk2 = 75; $pidchk2 = 651; }
			if ($CSvalue == 15) { $stidchk1 = 2; $pidchk1 = 2; $stidchk2 = 75; $pidchk2 = 652; }
			if ($CSvalue == 16) { $stidchk1 = 2; $pidchk1 = 3; $stidchk2 = 75; $pidchk2 = 653; }
			$dataset = $pdo->prepare('select max(date_added) as date_added, logkey from trainee_log where trainkey = ? and (stid = ? and pid = ?) group by logkey order by max(date_added) desc');
			$dataset->execute([$which, $stidchk1, $pidchk1]);
			while ($dr = $dataset->fetch(PDO::FETCH_ASSOC)) {
				$date_added = $dr['date_added'];
				$tablelogkey = $dr['logkey'];
				$ctr = 0;
				$resultset = $pdo->prepare('select f.stid, s.single from tab_fields f join select_types s on f.stid = s.stid where f.tbid = ? and f.sort_order != ? order by f.sort_order asc');
				$resultset->execute([$value1, $value0]);
				while ($rr = $resultset->fetch(PDO::FETCH_ASSOC)) {
					$stid = (int)$rr['stid'];
					$single = (int)$rr['single'];
					$exselect_val = '';
					$expid = null;
					$sv = $pdo->prepare('select tlogid, pid, select_val from trainee_log where trainkey = ? and stid = ? and logkey = ? limit 1');
					$sv->execute([$which, $stid, $tablelogkey]);
					if ($svr = $sv->fetch(PDO::FETCH_ASSOC)) { $expid = $svr['pid']; $exselect_val = $svr['select_val']; }
					if ($single == 2 || $single == 4 || $single == 5 || $single == 6) {
						if ($exselect_val == '' && $ctr == 0) { $exselect_val = 'N/A'; }
						$nextline .= $exselect_val . ',';
					}
					if ($single == 0) {
						$gs = $pdo->prepare('select select_val from select_gen where pid = ? limit 1');
						$gs->execute([(int)$expid]);
						$val = ($tmp = $gs->fetch(PDO::FETCH_NUM)) ? $tmp[0] : '';
						$nextline .= $val . ',';
					}
					if ($single == 3) {
						if (!empty($exselect_val) && $exselect_val > 1) {
							$ts = strtotime($exselect_val);
							$nextline .= date('d-m-y', $ts) . ',';
						} else {
							$nextline .= ' ,';
						}
					}
					$ctr++;
				}
				$nextline .= "\n";
			}
			$nextline .= "\n\n\n";
		}
	}

	$finalline = $firstline.$nextline;
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Content-Length: " . strlen($finalline));
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=$filename");
	echo $finalline;
}

?>