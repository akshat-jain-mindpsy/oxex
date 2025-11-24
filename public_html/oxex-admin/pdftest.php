<?php
// We use http://www.fpdf.org as our pdf creator
// We use https://www.setasign.com/products/fpdi/about/ for pdf template import
use setasign\Fpdi\Fpdi;
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
require 'fpdf/fpdf.php';
require_once('fpdi2/src/autoload.php');

// Ensure $today is defined (format: YYYYMMDD)
if (!isset($today)) {
    $today = date('Ymd');
}
$todaydisp = strtotime($today);
$disptoday = date('D jS F Y', $todaydisp);
 
// Get the trainee and supervisor from traineedetail.php
$trainee = isset($_GET['trainee']) ? trim($_GET['trainee']) : ''; # get id for trainee
// Initialize variables to avoid undefined variable warnings
$name = '';
$cohort = '';
$uid = '';
$university = '';
$who_by = '';
$super_pass = '';
$date_added = '';
$supername = '';
$tab_name = '';

$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo && !empty($trainee)) {
   $stmt = $pdo->prepare("SELECT name, year, uid FROM trainee_tbl WHERE trainkey = ?");
   $stmt->execute([$trainee]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC);
   if ($row) {
       $name = $row['name'];
       $cohort = $row['year'];
       $uid = $row['uid'];
   }
   // get uni
   if (!empty($uid)) {
       $stmt = $pdo->prepare("SELECT university FROM uni_tbl WHERE uid = ?");
       $stmt->execute([$uid]);
       $row = $stmt->fetch(PDO::FETCH_ASSOC);
       if ($row) {
           $university = $row['university'];
       }
   }
}

$trid = isset($_GET['trid']) ? trim($_GET['trid']) : ''; # get id for report pass data

// Debug: Log parameters to help diagnose issues
error_log("pdftest.php - trainee: '$trainee', trid: '$trid', is_numeric: " . (is_numeric($trid) ? 'yes' : 'no'));

// Validate that we have required parameters and they are not empty
if ($pdo && !empty($trid) && !empty($trainee) && is_numeric($trid)) {
   $stmt = $pdo->prepare("SELECT who_by, super_pass, date_added FROM trainee_report_ok WHERE trid = ? AND trainkey = ?");
   $stmt->execute([(int)$trid, $trainee]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC);
   
   // Debug: Log query result
   error_log("pdftest.php - Query found row: " . ($row ? 'yes' : 'no'));
   if ($row) {
       error_log("pdftest.php - who_by: " . ($row['who_by'] ?? 'null') . ", super_pass: " . ($row['super_pass'] ?? 'null') . ", date_added: " . ($row['date_added'] ?? 'null'));
   } else {
       // Try querying without trainkey condition to see if trid exists
       $stmt2 = $pdo->prepare("SELECT who_by, super_pass, date_added, trainkey FROM trainee_report_ok WHERE trid = ?");
       $stmt2->execute([(int)$trid]);
       $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
       if ($row2) {
           error_log("pdftest.php - Record found but trainkey mismatch. Expected: '$trainee', Found: " . ($row2['trainkey'] ?? 'null'));
       } else {
           error_log("pdftest.php - No record found with trid: " . (int)$trid);
       }
   }
   
   if ($row) {
       $who_by = $row['who_by'];
       $super_pass = $row['super_pass'];
       $date_added_raw = $row['date_added'];
       
       // Format date - handle YYYYMMDD integer format or standard date strings
       $date_added = '';
       if (!empty($date_added_raw)) {
           $date_added_timestamp = null;
           // Check if it's in YYYYMMDD integer format
           if (is_numeric($date_added_raw) && strlen((string)$date_added_raw) == 8) {
               // Convert YYYYMMDD to YYYY-MM-DD for strtotime
               $date_str = (string)$date_added_raw;
               $date_added_timestamp = strtotime(substr($date_str, 0, 4) . '-' . substr($date_str, 4, 2) . '-' . substr($date_str, 6, 2));
           } else {
               $date_added_timestamp = strtotime($date_added_raw);
           }
           
           if ($date_added_timestamp !== false) {
               $date_added = date('D jS F Y', $date_added_timestamp);
           } else {
               $date_added = 'Unknown date';
           }
       }
   }

   // get supervisor details who added pass
   if (!empty($who_by)) {
       $stmt = $pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
       $stmt->execute([$who_by]);
       $row = $stmt->fetch(PDO::FETCH_ASSOC);
       if ($row) {
           $supername = $row['realname'];
       }
   }

   // get which competency
   if (!empty($super_pass)) {
       $stmt = $pdo->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
       $stmt->execute([$super_pass]);
       $row = $stmt->fetch(PDO::FETCH_ASSOC);
       if ($row) {
           $tab_name = $row['tab_name'];
       }
   }
}

//echo "$name $supername";
$pdftemplate = "oxexpdf1.pdf";

// *** DRAW PAGE ***
// now start preparing data for printing

// initiate FPDI
$pdf = new Fpdi();
// add a page
$pdf->AddPage();
// set the source file
$pdf->setSourceFile($pdftemplate);
// import page 1
$tplIdx = $pdf->importPage(1);
// use the imported page and place it at position 0,0 with a width of 210 mm
$pdf->useTemplate($tplIdx, 0, 0, 210);

// now write some text onto the imported page
// importrant information is:
// The Competency
// Supervisor
// Trainee, cohort and uni
// Dates

$pdf->SetFont('Helvetica', 'B', '14');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(20, 62); # first number if horizontal, second vertical mm
$pdf->Write(0, 'Recording a Pass with the following details: ');
$pdf->SetXY(20, 72);
$pdf->Write(0, "Name: $name");
$pdf->SetXY(20, 82);
$pdf->Write(0, "University: $university");
$pdf->SetXY(20, 92);
$pdf->Write(0, "Cohort: $cohort");
$pdf->SetXY(20, 102);
$pdf->Write(0, "Competency: $tab_name");
$pdf->SetXY(20, 112);
$pdf->Write(0, "Supervisor: $supername");
$pdf->SetFont('Helvetica', '', '14');
$pdf->SetXY(20, 132);
$pdf->Write(0, 'Certificate Issued : ');
$pdf->SetXY(20, 138);
$pdf->Write(0, $date_added);


// Finished! Output completed page
// I: send the file inline to the browser. 'true' forces UTF-8
$pdf->Output('I', 'generated.pdf', true);

?>