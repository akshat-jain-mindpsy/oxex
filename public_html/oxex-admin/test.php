<?PHP
include '../OXEXfolder/config.php';
//include '../OXEXfolder/u_functions.php';
$value2 = 2;
$value3 = 3;
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title>test</title>
   
</head>
<body>

<?php
//echo "<p>$value2</p>";
$ansarr = array();
$valarr = array();
$valBarr = array();
$elldee = 1;
$age = 0;
$stid = 10; # ethnicity
//$valueA = 17; # white british
$trainkey = '62964670bbcc04fd96d854e0ba56ba16'; # Ali Marg
$rmid = 84; # Range of backgrounds and complexity (Ethnicity) LD
//$rmid = 145; # Range of backgrounds and complexity (Ethnicity) non-LD

$dataset = $supabase_pdo->prepare("SELECT select_gen.select_val, report_data.valuea FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
$dataset->execute([$rmid]); 
$rows = $dataset->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
   $select_val = $row['select_val'];
   $valuea = $row['valuea'];
   array_push($valarr,$valuea); # the id's to look for in Trainee's data
}
print_r($valarr);

foreach ($valarr as $valueA) {
    //$valueA = (int)$valueA;


    $numages = 0;
    $vids = $supabase_pdo->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ?"); 
    $vids->execute([$trainkey, $stid, $valueA]);
    $log_rows = $vids->fetchAll(PDO::FETCH_ASSOC);
    foreach ($log_rows as $log_row) {
        $checklogkey = $log_row['logkey'];
        if ($elldee == 1) {
//echo "($stid $valueA $checklogkey) ";
         // Any data must have Learning Disability selected, else data not used
         // For this $logkey, check the LD 'Clinical Specialism' field data
         // this is $stid = 2, $pid = 3
         $ldstmt = $supabase_pdo->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
         $ldstmt->execute([$value2, $value3, $checklogkey]);
         $numld = $ldstmt->rowCount();
echo "$checklogkey ($numld)<br>";
         if ($numld == 0) { # no LD 'Placement Type'
            //$numages = 0; # clear data if 'LD' not selected in Clinical Specialism question for the same data set specified by $checklogkey
         } else {
            //$numages++; # count the valid data
            $numages++;
         }
      }
      if ($elldee == 0 && $age == 0) {
                                 $numages++; # count all the data as LD/age N/A
                              }
        
    }
    array_push($ansarr, $numages);
}
print_r($ansarr);
?>

</body>
</html>