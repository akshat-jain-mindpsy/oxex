<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title>Admin</title><!-- =============== VENDOR STYLES ===============-->
   <!-- FONT AWESOME-->
   <link rel="stylesheet" href="assets/vendor/@fortawesome/fontawesome-free/css/brands.css">
   <link rel="stylesheet" href="assets/vendor/@fortawesome/fontawesome-free/css/regular.css">
   <link rel="stylesheet" href="assets/vendor/@fortawesome/fontawesome-free/css/solid.css">
   <link rel="stylesheet" href="assets/vendor/@fortawesome/fontawesome-free/css/fontawesome.css"><!-- ANIMATE.CSS-->
   <link rel="stylesheet" href="assets/vendor/animate.css/animate.css"><!-- =============== BOOTSTRAP STYLES ===============-->
   <link rel="stylesheet" href="assets/css/bootstrap.css" id="bscss"><!-- =============== APP STYLES ===============-->
   <link rel="stylesheet" href="assets/css/app.css" id="maincss">
</head>

<body>
   <div class="wrapper">
      <div class="full-page-background bg-darker"></div>
      <div class="d-flex align-items-center justify-content-center h-100 w-100 flex-column">
         <!-- START card-->
         <div class="card card-flat" style="min-width: 300px">
            <div class="card-header text-center bg-transparent border-0">
               <img src="assets/img/Oxford_Health_correct_logo.jfif" alt="NHS logo" class="img-fluid">
               <h3 class="py-3">OXICPTR Admin</h3></div>
            <div class="card-body">
               <h3>Reset your password</h3>
               <form method="post" action="reset2.php" class="py-3 pz-3">
                 <div class="form-group">
                   <label for="email" class="sr-only">Email address</label>
                   <input type="email" class="form-control" name="email" id="email" aria-describedby="emailHelp" placeholder="Email address">
                 </div>
                   <input type="hidden" name="done" value="done">
                   <button type="submit" class="btn btn-primary">Reset/Change Password</button>
               </form>
            </div>
         </div>
      </div>
   </div><!-- =============== VENDOR SCRIPTS ===============-->
   <!-- STORAGE API-->
   <script src="assets/vendor/js-storage/js.storage.js"></script><!-- i18next-->
   <script src="assets/vendor/i18next/i18next.js"></script>
   <script src="assets/vendor/i18next-xhr-backend/i18nextXHRBackend.js"></script><!-- JQUERY-->
   <script src="assets/vendor/jquery/dist/jquery.js"></script><!-- BOOTSTRAP-->
   <script src="assets/vendor/popper.js/dist/umd/popper.js"></script>
   <script src="assets/vendor/bootstrap/dist/js/bootstrap.js"></script><!-- PARSLEY-->
   <script src="assets/vendor/parsleyjs/dist/parsley.js"></script><!-- =============== APP SCRIPTS ===============-->
   <script src="assets/js/app.js"></script>
</body>

</html>