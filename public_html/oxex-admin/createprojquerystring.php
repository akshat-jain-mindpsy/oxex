<?php
include '../OXEXfolder/config.php';
$semantic_title = $_GET['q'];
$semantic_url = preg_replace('/[^A-Za-z0-9-]+/', '-', $semantic_title);# replace non alphanumeric with a dash
  $semantic_url = preg_replace('/-+/', '-', $semantic_url);# remove repeated dashes
  $semantic_url = rtrim($semantic_url, '-');# remove trailing dash
  $urllen = strlen($semantic_url);
  if ($urllen > 255) {
  	$semantic_url = substr($semantic_url, 0, 255); # don't want more than 255 chars
  }
  // now look for anything with the same url in semantic_url
  $stmt = $mysqli->prepare("SELECT pid FROM proj_tbl WHERE semantic_url = ?");
	$stmt->bind_param("s", $semantic_url);
	$stmt->execute();
	$stmt->store_result();
	$stmt->bind_result($pid);
	$stmt->fetch();
	$numrows = $stmt->num_rows;
	$stmt->close();

echo "Project URL will be: https://www.classicperformanceengineeringclassicperformanceengineering.co.uk/classic/[manufacturer]/".$semantic_url;
if ($numrows > 0) {
	echo " <strong>(Warning! This url is already in use)</strong>";
}
if ($urllen < 5) {
	echo " <strong>(Title is very short)</strong>";
}
if ($urllen > 255) {
	echo "  <strong>Title has been truncated to 255 characters</strong>.";
}
?>