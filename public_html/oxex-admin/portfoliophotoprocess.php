<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';

$valueblank = '';
$value0 = 0;
$value1 = 1;
$value2 = 2;

$which=$_POST['which'];#main record


echo '<p class="main">Starting upload procedure...</p>';
$generateID = substr(md5(rand()), 0, 16); 
ini_set('max_execution_time', 300);
 function resize_save_jpeg( $source_image_path, $target_image_path, $target_image_width, $target_image_height )
 {
  list( $source_image_width, $source_image_height, $source_image_type ) = getimagesize( $source_image_path );
  switch ( $source_image_type )
  {
   case IMAGETYPE_GIF:
    $source_gd_image = imagecreatefromgif( $source_image_path );
    break;
   case IMAGETYPE_JPEG:
    $source_gd_image = imagecreatefromjpeg( $source_image_path );
    break;
   case IMAGETYPE_PNG:
    $source_gd_image = imagecreatefrompng( $source_image_path );
    break;
  }
  if ( $source_gd_image === false )
  {
   return false;
  }
  $source_aspect_ratio = $source_image_width / $source_image_height;
  $target_aspect_ratio = $target_image_width / $target_image_height;
  if ( $source_image_width <= $target_image_width && $source_image_height <= $target_image_height )
  {
   $target_image_width = $source_image_width;
   $target_image_height = $source_image_height;
  }
  elseif ( $target_aspect_ratio > $source_aspect_ratio )
  {
   $target_image_width = ( int ) ( $target_image_height * $source_aspect_ratio );
  }
  else
  {
   $target_image_height = ( int ) ( $target_image_width / $source_aspect_ratio );
  }
  $target_gd_image = imagecreatetruecolor( $target_image_width, $target_image_height );
  imagecopyresampled( $target_gd_image, $source_gd_image, 0, 0, 0, 0, $target_image_width, $target_image_height, $source_image_width, $source_image_height );
  imagejpeg( $target_gd_image, $target_image_path, 90 );
  imagedestroy( $source_gd_image );
  imagedestroy( $target_gd_image );
  return true;
 }
 $temp_image_path = $_FILES[ 'Image1' ][ 'tmp_name' ];
 $temp_image_name = $_FILES[ 'Image1' ][ 'name' ];
 list( , , $temp_image_type ) = getimagesize( $temp_image_path );
 if ( $temp_image_type === NULL )
 {
  return false;
 }
 switch ( $temp_image_type )
 {
  case IMAGETYPE_GIF:
   break;
  case IMAGETYPE_JPEG:
   break;
  case IMAGETYPE_PNG:
   break;
  default:
   return false;
 }
 $large_image_path = '../portfolio/'.$generateID.'.jpg';
    $actualname = $generateID.'.jpg';

 $result = resize_save_jpeg( $temp_image_path, $large_image_path, 200, 200 );
 if ( $result )
 {
	$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
	if ($pdo) {
		$stmt = $pdo->prepare("UPDATE portfolio SET logo = ? WHERE pid = ?"); 
		$stmt->execute([$actualname, $which]);
	}
	
		echo "<p><a href=\"portfoliodetail.php?which=$which&amp;del=delphoto\"<span class=\"btn btn-danger\"> <i class=\"fa fa-times-circle\"></i> Delete Image</span></a></p>";
		echo "<p><img src=\"../portfolio/$actualname\" width=\"200px\"></p>";
		echo '<p>&nbsp;</p><hr>';
	
 }
?>