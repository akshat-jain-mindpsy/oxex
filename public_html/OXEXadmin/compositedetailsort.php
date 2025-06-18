<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
// update table field sort order from draggable tabledetail.php

$position = $_POST['position'];
$i = 1;
foreach($position as $k => $v){
    $stmt = $mysqli->prepare("UPDATE compositesearch SET sort_order = ? WHERE csid = ?"); 
	$stmt->bind_param("ii", $i, $v);
	$stmt->execute();
	$stmt->close();
	$i++;
}
?>