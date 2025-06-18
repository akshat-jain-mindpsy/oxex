<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
// update table report sort order from draggable reportdetail.php

$position = $_POST['position'];
$i = 1;
foreach($position as $k => $v){
    $stmt = $mysqli->prepare("UPDATE report_manager SET sort_order = ? WHERE rmid = ?"); 
	$stmt->bind_param("ii", $i, $v);
	$stmt->execute();
	$stmt->close();
	$i++;
}
?>