<?php
// set bg colour, blue or green

// set up flags to denote which nav link is 'active'
$homeA = ''; # home page
$homeSR = ''; # home page
$whoweareA = ''; # who we are
$whoweareSR = ''; # who we are
$whatwedoA = ''; # what we do
$whatwedoSR = ''; # what we do
$contactA = ''; # contact
$contactSR = ''; # contact

if ($thispage == 'index.php' || $thispage == '/') {
  $homeA = 'active';
  $homeSR = '<span class="sr-only">(current)</span>';
  $crumbs = 'Home';
}
if ($thispage == 'who-we-are.php') {
  $whoweareA = 'active';
  $whoweareSR = '<span class="sr-only">(current)</span>';
  $crumbs = 'Who we are';
}
if ($thispage == 'what-we-do.php' || $thispage == 'symposia-meetings-webinars.php' || $thispage == 'creative-design.php' || $thispage == 'medical-writing.php' || $thispage == 'brand-strategy.php' || $thispage == 'animation-motion-gfx.php' || $thispage == 'video-production.php' || $thispage == 'digital-marketing.php') {
  $activepatient = 'active';
  $srhpatient = '<span class="sr-only">(current)</span>';
  $crumbs = 'What we do';
}
if ($thispage == 'contact.php') {
  $contactA = 'active';
  $contactSR = '<span class="sr-only">(current)</span>';
  $crumbs = 'Contact';
}
?>
<div class="container-fluid bg-iceblue">
  <div class="container">
    <nav class="navbar navbar-expand-lg navbar-dark bg-iceblue">
      <!--<a class="navbar-brand" href="index.php">
        <picture>
          <source srcset="assets/img/iceberg-logo.avif" class="img-fluid" type="image/avif">
          <source srcset="assets/img/iceberg-logo.webp" class="img-fluid" type="image/webp">
          <img src="assets/img/iceberg-logo.png" alt="Iceberg Medical Logo" class="img-fluid">
        </picture>
      </a>-->
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-md-center" id="navbarNavDropdown">
        <ul class="navbar-nav">
          <li class="nav-item <?php echo $homeA ?>">
            <a class="nav-link" href="index.php">Home <?php echo $homeSR ?></a>
          </li>
          <li class="nav-item <?php echo $whoweareA ?>">
            <a class="nav-link" href="who-we-are.php">Who We Are <?php echo $whoweareSR ?></a>
          </li>
          <!-- hide on xss, replace with drop-down -->
          <li class="nav-item <?php echo $whatwedoA ?>">
            <a class="nav-link" href="what-we-do.php">What We Do <?php echo $whatwedoSR ?></a>
          </li>
          <li class="nav-item <?php echo $contactA ?>">
            <a class="nav-link" href="contact.php">Contact <?php echo $contactSR ?></a>
          </li>          
        </ul>
      </div>
    </nav>
  </div>
</div>