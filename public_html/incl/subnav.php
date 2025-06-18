<?php
// only appears on What We Do pages. White otherwise
// set up flags to denote which nav link is 'active'
$loginA = ''; 
$loginSR = '';
$contactA = ''; 
$contactSR = '';
$accountA = ''; 
$accountSR = '';
$notesA = ''; 
$notesSR = '';

if ($thispage == 'login.php') {
  $loginA = 'active';
  $loginSR = '<span class="sr-only">(current)</span>';
  $crumbs = 'Login';
}
if ($thispage == 'account.php') {
  $accountA = 'active';
  $accountSR = '<span class="sr-only">(current)</span>';
  $crumbs = 'Account';
}
if ($thispage == 'tabs.php') {
  $notesA = 'active';
  $notesSR = '<span class="sr-only">(current)</span>';
  $crumbs = 'Notes';
}
if ($thispage == 'contact.php') {
  $contactA = 'active';
  $contactSR = '<span class="sr-only">(current)</span>';
  $crumbs = 'Contact';
}
?>

<div class="container-fluid bg-white py-3">
  <div class="container">
    <div class="row">
      <div class="col-xs-12 col-sm-8">
        <nav class="navbar navbar-expand-lg navbar-light">
          <div class="justify-content-md-center" id="subnavbar">
            <ul class="navbar-nav">
<?php
if(login_check($mysqli) == true) {
?>
<li class="nav-item <?php echo $accountA ?>">
  <a class="nav-link" href="account.php">Account Home <?php echo $accountSR ?></a>
</li>
<li class="nav-item <?php echo $notesA ?>">
  <a class="nav-link" href="tabs.php">Notes <?php echo $notesSR ?></a>
</li>
<li class="nav-item">
  <a class="nav-link" href="logout.php">Logout</a>
</li>
<?php
} else {
?>
<li class="nav-item <?php echo $loginA ?>">
  <a class="nav-link" href="login.php">Login <?php echo $loginSR ?></a>
</li>
<?php
}
?>

            </ul>
          </div>
        </nav>
      </div>
      <div class="col-sm-2 offset-sm-2">
        <img src="assets/img/Oxford_Health_correct_logo.jfif" class="img-fluid" alt="Oxford Health NHS Trust">    
      </div>
    </div>
  </div>
</div>