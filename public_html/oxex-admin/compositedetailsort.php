<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
// update table field sort order from draggable sheetdetail.php

$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
$position = $_POST['position'];
$i = 1;
foreach($position as $k => $v){
    if ($usingSupabase) {
        $stmt = $supabase_pdo->prepare("UPDATE compositesearch SET sort_order = ? WHERE csid = ?"); 
        $stmt->execute([$i, $v]);
    }
    $i++;
}
?>