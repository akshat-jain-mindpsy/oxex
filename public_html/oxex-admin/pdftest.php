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

$todaydisp = strtotime($today);
$disptoday = date('D jS F Y', $todaydisp);
 
// Get the trainee and supervisor from traineedetail.php
$trainee = isset($_GET['trainee']) ? $_GET['trainee'] : ''; # get id for trainee
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
   $stmt = $pdo->prepare("SELECT name, year, uid FROM trainee_tbl WHERE trainkey = ?");
   $stmt->execute([$trainee]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC);
   if ($row) {
       $name = $row['name'];
       $cohort = $row['year'];
       $uid = $row['uid'];
   }
   // get uni
   $stmt = $pdo->prepare("SELECT university FROM uni_tbl WHERE uid = ?");
   $stmt->execute([$uid]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC);
   if ($row) {
       $university = $row['university'];
   }
}

$trid = isset($_GET['trid']) ? $_GET['trid'] : 0; # get id for report pass data
if ($pdo) {
   $stmt = $pdo->prepare("SELECT who_by, super_pass, date_added FROM trainee_report_ok WHERE trid = ? AND trainkey = ?");
   $stmt->execute([$trid, $trainee]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC);
   if ($row) {
       $who_by = $row['who_by'];
       $super_pass = $row['super_pass'];
       $date_added = $row['date_added'];
   }
   $date_added = strtotime($date_added);
   $date_added = date('D jS F Y', $date_added);

   // get supervisor details who added pass
   $stmt = $pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->execute([$who_by]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC);
   if ($row) {
       $supername = $row['realname'];
   }

   // get which competency
   $stmt = $pdo->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
   $stmt->execute([$super_pass]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC);
   if ($row) {
       $tab_name = $row['tab_name'];
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