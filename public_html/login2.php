<?PHP
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
sec_session_start(); 

if(isset($_POST['ee'], $_POST['pp'])) { 
   $email = $_POST['ee'];
   $password = $_POST['pp'];
   if(login($email, $password, $mysqli) == true) {
      header('Location: account.php');
      exit();
   } else {
      header('Location: login.php?error=invalid');
      exit();
   }
} else { 
   header('Location: login.php?error=invalid');
   exit();
}
?>