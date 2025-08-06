<meta name="author" content="OXEX">
<meta property="og:type" content="website">
<meta property="og:url" content="https://www.oxex.co.uk/<?php echo $thispage ?>">
<meta property="og:title" content="OXEX">
<meta property="og:description" content="OXEX">
<meta property="og:image" content="https://www.oxex.co.uk/assets/img/Oxford_Health_correct_logo.jfif">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css" integrity="sha384-zCbKRCUGaJDkqS1kPbPd7TveP5iyJE0EjAuZQTgFLD2ylzuqKfdKlfG/eSrtxUkn" crossorigin="anonymous">
<link href="assets/css/style.css" rel="stylesheet">
<meta name="theme-color" content="#ffffff">
<?php 
// remove index.php for canonical
if (!isset($canonical)) {
    $canonical = $thispage ?? '';
}
if ($canonical == 'index.php') {
	$canonical = '';
}
?>
<link rel="canonical" href="https://www.oxex.co.uk/<?php echo $canonical ?>">
