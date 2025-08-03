<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "News";
$subtitle = "News/Blog";
if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
</head>
<?php 
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';

if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete tags
  $stmt = $mysqli->prepare("DELETE FROM blog_link_tbl WHERE kbid = ?");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  $stmt->close();
  // delete row from detail page
  $stmt = $mysqli->prepare("DELETE FROM semantic_blog WHERE sbid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();
}

if ($newadmin == 'newadmin') {
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
    $sort_order = (int)$sort_order;
    $lastsort = $sort_order + 1;

  $author = isset($_POST['author']) ? $_POST['author'] : '';
    $author = preg_replace('/[^\p{Latin}\d\s\p{P}]/u', '', $author);
  $ptid = isset($_POST['ptid']) ? $_POST['ptid'] : '0';
  $gid = isset($_POST['gid']) ? $_POST['gid'] : '0';
  $blog_title = $_POST['blog_title'];
  $semantic_title = isset($_POST['semantic_title']) ? $_POST['semantic_title'] : '';
  $blog_abstract = isset($_POST['blog_abstract']) ? $_POST['blog_abstract'] : '';
  $page_txt1 = isset($_POST['page_txt1']) ? $_POST['page_txt1'] : '';
  $page_txt2 = isset($_POST['page_txt2']) ? $_POST['page_txt2'] : '';
  $page_txt3 = isset($_POST['page_txt3']) ? $_POST['page_txt3'] : '';
  $bannerTitle = isset($_POST['bannerTitle']) ? $_POST['bannerTitle'] : '';
  $bannerTxt = isset($_POST['bannerTxt']) ? $_POST['bannerTxt'] : '';
  $justification = isset($_POST['justification']) ? $_POST['justification'] : 1;
  $actioncall1 = isset($_POST['actioncall1']) ? $_POST['actioncall1'] : '';
  $actioncall2 = isset($_POST['actioncall2']) ? $_POST['actioncall2'] : '';
  $buttonlink1 = isset($_POST['buttonlink1']) ? $_POST['buttonlink1'] : '';
  $buttonlink2 = isset($_POST['buttonlink2']) ? $_POST['buttonlink2'] : '';
  $buttonname1 = isset($_POST['buttonname1']) ? $_POST['buttonname1'] : '';
  $buttonname2 = isset($_POST['buttonname2']) ? $_POST['buttonname2'] : '';
  $metatext = isset($_POST['metatext']) ? $_POST['metatext'] : '';
  $googleTitle = isset($_POST['googleTitle']) ? $_POST['googleTitle'] : '';
  $googleDesc = isset($_POST['googleDesc']) ? $_POST['googleDesc'] : '';
  $googleKeywords = isset($_POST['googleKeywords']) ? $_POST['googleKeywords'] : '';
  $schematype = isset($_POST['schematype']) ? $_POST['schematype'] : '';
  if ($semantic_title == '') {
    // create it
    $semantic_title = $blog_title;
  }

  // format the string to be used as a URL
  $semantic_url = preg_replace('/[^A-Za-z0-9-]+/', '-', $semantic_title);# replace non alphanumeric with a dash
  $semantic_url = preg_replace('/-+/', '-', $semantic_url);# remove repeated dashes
  $semantic_url = rtrim($semantic_url, '-');# remove trailing dash
  // look for any blog titles with the same url in semantic_url
  $semantic_title = $semantic_url; # not used
  $stmt = $mysqli->prepare("SELECT sbid FROM semantic_blog WHERE semantic_url = ?");
  $stmt->bind_param("s", $semantic_url);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($sbid);
  $stmt->fetch();
  $numrows = $stmt->num_rows;
  $stmt->close();

  $urllen = strlen($semantic_url);
  if ($urllen > 255 && $numrows == 0) {
    $semantic_url = substr($semantic_url, 0, 255); # don't want more than 255 chars
  }
  if ($urllen > 250 && $numrows > 0) {
    $semantic_url = substr($semantic_url, 0, 250); # don't want more than 251 chars as we need to append a suffix
  }
  if ($numrows > 0) {
    $randsuffix = substr(md5(rand()), 0, 4);
    $semantic_url = $semantic_url.'-'.$randsuffix;# add suffix to make it different to existing blog
  }

  
  if ($googleTitle == '') {
    $googleTitle = $blog_title;# set googleTitle = blog_title if nothing entered
  }
  if ($googleDesc == '') {
    $googleDesc = $blog_title;# set googleDesc = blog_title if nothing entered
  }
  if ($googleKeywords == '') {
    $commonWords = array('a','able','about','above','abroad','according','accordingly','across','actually','adj','after','afterwards','again','against','ago','ahead','ain\'t','all','allow','allows','almost','alone','along','alongside','already','also','although','always','am','amid','amidst','among','amongst','an','and','another','any','anybody','anyhow','anyone','anything','anyway','anyways','anywhere','apart','appear','appreciate','appropriate','are','aren\'t','around','as','a\'s','aside','ask','asking','associated','at','available','away','awfully','b','back','backward','backwards','be','became','because','become','becomes','becoming','been','before','beforehand','begin','behind','being','believe','below','beside','besides','best','better','between','beyond','both','brief','but','by','c','came','can','cannot','cant','can\'t','caption','cause','causes','certain','certainly','changes','clearly','c\'mon','co','co.','com','come','comes','concerning','consequently','consider','considering','contain','containing','contains','corresponding','could','couldn\'t','course','c\'s','currently','d','dare','daren\'t','definitely','described','despite','did','didn\'t','different','directly','do','does','doesn\'t','doing','done','don\'t','down','downwards','during','e','each','edu','eg','eight','eighty','either','else','elsewhere','end','ending','enough','entirely','especially','et','etc','even','ever','evermore','every','everybody','everyone','everything','everywhere','ex','exactly','example','except','f','fairly','far','farther','few','fewer','fifth','first','five','followed','following','follows','for','forever','former','formerly','forth','forward','found','four','from','further','furthermore','g','get','gets','getting','given','gives','go','goes','going','gone','got','gotten','greetings','h','had','hadn\'t','half','happens','hardly','has','hasn\'t','have','haven\'t','having','he','he\'d','he\'ll','hello','help','hence','her','here','hereafter','hereby','herein','here\'s','hereupon','hers','herself','he\'s','hi','him','himself','his','hither','hopefully','how','howbeit','however','hundred','i','i\'d','ie','if','ignored','i\'ll','i\'m','immediate','in','inasmuch','inc','inc.','indeed','indicate','indicated','indicates','inner','inside','insofar','instead','into','inward','is','isn\'t','it','it\'d','it\'ll','its','it\'s','itself','i\'ve','j','just','k','keep','keeps','kept','know','known','knows','l','last','lately','later','latter','latterly','least','less','lest','let','let\'s','like','liked','likely','likewise','little','look','looking','looks','low','lower','ltd','m','made','mainly','make','makes','many','may','maybe','mayn\'t','me','mean','meantime','meanwhile','merely','might','mightn\'t','mine','minus','miss','more','moreover','most','mostly','mr','mrs','much','must','mustn\'t','my','myself','n','name','namely','nd','near','nearly','necessary','need','needn\'t','needs','neither','never','neverf','neverless','nevertheless','new','next','nine','ninety','no','nobody','non','none','nonetheless','noone','no-one','nor','normally','not','nothing','notwithstanding','novel','now','nowhere','o','obviously','of','off','often','oh','ok','okay','old','on','once','one','ones','one\'s','only','onto','opposite','or','other','others','otherwise','ought','oughtn\'t','our','ours','ourselves','out','outside','over','overall','own','p','particular','particularly','past','per','perhaps','placed','please','plus','possible','presumably','probably','provided','provides','q','que','quite','qv','r','rather','rd','re','really','reasonably','recent','recently','regarding','regardless','regards','relatively','respectively','right','round','s','said','same','saw','say','saying','says','second','secondly','see','seeing','seem','seemed','seeming','seems','seen','self','selves','sensible','sent','serious','seriously','seven','several','shall','shan\'t','she','she\'d','she\'ll','she\'s','should','shouldn\'t','since','six','so','some','somebody','someday','somehow','someone','something','sometime','sometimes','somewhat','somewhere','soon','sorry','specified','specify','specifying','still','sub','such','sup','sure','t','take','taken','taking','tell','tends','th','than','thank','thanks','thanx','that','that\'ll','thats','that\'s','that\'ve','the','their','theirs','them','themselves','then','thence','there','thereafter','thereby','there\'d','therefore','therein','there\'ll','there\'re','theres','there\'s','thereupon','there\'ve','these','they','they\'d','they\'ll','they\'re','they\'ve','thing','things','think','third','thirty','this','thorough','thoroughly','those','though','three','through','throughout','thru','thus','till','to','together','too','took','toward','towards','tried','tries','truly','try','trying','t\'s','twice','two','u','un','under','underneath','undoing','unfortunately','unless','unlike','unlikely','until','unto','up','upon','upwards','us','use','used','useful','uses','using','usually','v','value','various','versus','very','via','viz','vs','w','want','wants','was','wasn\'t','way','we','we\'d','welcome','well','we\'ll','went','were','we\'re','weren\'t','we\'ve','what','whatever','what\'ll','what\'s','what\'ve','when','whence','whenever','where','whereafter','whereas','whereby','wherein','where\'s','whereupon','wherever','whether','which','whichever','while','whilst','whither','who','who\'d','whoever','whole','who\'ll','whom','whomever','who\'s','whose','why','will','willing','wish','with','within','without','wonder','won\'t','would','wouldn\'t','x','y','yes','yet','you','you\'d','you\'ll','your','you\'re','yours','yourself','yourselves','you\'ve','z','zero');
    // remove common words to create keywords if nothing entered
    $googleKeywords = preg_replace('/\b('.implode('|',$commonWords).')\b/','',$blog_title);
      $googleKeywords = preg_replace('/[^A-Za-z0-9-\s]+/', '', $googleKeywords); # remove punctuation
     $googleKeywords = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $googleKeywords)));
  }
  // write new user record
  $ptid = 0;
  $schematype = 0;
  $insert_stmt = $mysqli->prepare("INSERT INTO semantic_blog (sort_order, date_published, date_modified, author, ptid, gid, blog_title, semantic_title, semantic_url, blog_abstract, page_txt1, page_txt2, page_txt3, bannerTitle, bannerTxt, justification, banner, actioncall1, buttonlink1, buttonname1, actioncall2, buttonlink2, buttonname2, metatext, googleTitle, googleDesc, googleKeywords, schematype) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("iiisiisssssssssssssssssssssi", $sort_order, $today, $today, $author, $ptid, $gid, $blog_title, $semantic_title, $semantic_url, $blog_abstract, $page_txt1, $page_txt2, $page_txt3, $bannerTitle, $bannerTxt, $value0, $valueblank, $actioncall1, $buttonlink1, $buttonname1, $actioncall2, $buttonlink2, $buttonname2, $metatext, $googleTitle, $googleDesc, $googleKeywords, $schematype);
  $insert_stmt->execute();
  printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $newid = $insert_stmt->insert_id;
  $insert_stmt->close();

  // create subject links
  $tagstmt = $mysqli->prepare("SELECT btagid FROM blog_subject_tags");
  $tagstmt->execute();
  $tagstmt->store_result();
  $tagstmt->bind_result($btagid);
  while ($tagstmt->fetch()){
    $posmarker = 'q'.$btagid;
    $clicked = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
    if ($clicked == $btagid)  {
      // if checkbox has same value add to db
      $insert_stmt = $mysqli->prepare("INSERT INTO blog_link_tbl (kbid, btagid) VALUES (?, ?)");
      $insert_stmt->bind_param("ii", $newid, $btagid);
      $insert_stmt->execute();
      $insert_stmt->close();
      // kbid now sbid
    }
  }
  $tagstmt->close();
  if (is_uploaded_file($_FILES['Image1']['tmp_name'])) {
      // create images and add filename
      $generateID = substr(md5(rand()), 0, 16); 
      ini_set('max_execution_time', 300);
     function resize_save_jpeg( $source_image_path, $target_image_path, $target_image_width, $target_image_height ) {
      list( $source_image_width, $source_image_height, $source_image_type ) = getimagesize( $source_image_path );
      switch ( $source_image_type ) {
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

      if ( $source_gd_image === false ) {
       return false;
      }

      $source_aspect_ratio = $source_image_width / $source_image_height;
      $target_aspect_ratio = $target_image_width / $target_image_height;
      if ( ($source_image_width <= $target_image_width) && ($source_image_height <= $target_image_height) )  {
       $target_image_width = $source_image_width;
       $target_image_height = $source_image_height;
      }  elseif ( $target_aspect_ratio > $source_aspect_ratio )   {
       $target_image_width = ( int ) ( $target_image_height * $source_aspect_ratio );
      }   else  {
       $target_image_height = ( int ) ( $target_image_width / $source_aspect_ratio );
      }

      $target_gd_image = imagecreatetruecolor( $target_image_width, $target_image_height );
      imagecopyresampled( $target_gd_image, $source_gd_image, 0, 0, 0, 0, $target_image_width, $target_image_height, $source_image_width, $source_image_height );
      imagejpeg( $target_gd_image, $target_image_path, 80 );
      imagedestroy( $source_gd_image );
      imagedestroy( $target_gd_image );
      return true;
     }

     $temp_image_path = $_FILES[ 'Image1' ][ 'tmp_name' ];
     $temp_image_name = $_FILES[ 'Image1' ][ 'name' ];
     list( , , $temp_image_type ) = getimagesize( $temp_image_path );
     if ( $temp_image_type === NULL ) {
      return false;
     }

     switch ( $temp_image_type ) {
      case IMAGETYPE_GIF:
       break;
      case IMAGETYPE_JPEG:
       break;
      case IMAGETYPE_PNG:
       break;
      default:
       return false;
     }
     
     $large_image_path = '../blogbanner/'.$generateID.'.jpg';
        $actualname = $generateID.'.jpg';

     $result = resize_save_jpeg( $temp_image_path, $large_image_path, 1900, 500 );
     if ( $result )
     {
      $stmt = $mysqli->prepare("UPDATE semantic_blog SET banner = ? WHERE sbid = ?"); 
      $stmt->bind_param("si", $actualname, $newid);
      $stmt->execute();
      $stmt->close();
     }
  }
}
?>
<body>
   <div class="wrapper">
      <!-- top, left side and right navbars -->
      <?php include 'incl/topbar.php' ?>
      <?php include 'incl/sidebar.php' ?>
      <?php include 'incl/offsidebar.php' ?>

      <!-- Main section-->
      <section class="section-container">
         <!-- Page content-->
         <div class="content-wrapper">
            <div class="content-header">
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                          <thead> 
                        <tr>
                          <th>Blog Title</th>
                          <th>Tags</th>
                          <th>Date Created</th>
                          <th>Banner Photo?</th>
                          <th>Sort Order</th>
                        </tr>
                        </thead>
                          <tbody>
                          <?PHP
                            // list resources
                        $stmt = $mysqli->prepare("SELECT sbid, blog_title, date_published, banner, sort_order FROM semantic_blog");
                        //$stmt->bind_param("i", $value1);
                        $stmt->execute();
                        $stmt->store_result();
                        $stmt->bind_result($sbid, $blog_title, $date_published, $banner, $sort_order);
                        while ($stmt->fetch()){
                          $date_published = strtotime($date_published);
                          // get how many tags
                          $vids = $mysqli->prepare("SELECT blid FROM blog_link_tbl WHERE kbid = ? ");
                          $vids->bind_param("i", $sbid);
                          $vids->execute();
                          $vids->store_result();
                          $vids->bind_result($blid);
                          $vidrows = $vids->num_rows;
                          $vids->close();
                          $imgqty = $vidrows;
                          ?>
                            <tr>
                              <td valign="top"><a href="blogdetail.php?which=<?php echo $sbid ?>&amp;change=no"><?php echo $blog_title ?></a></td>
                          <td valign="top">
                            <?php 
                            if ($vidrows == 0) {
                              echo '<span class="btn btn-danger"> <i class="fa fa-times-circle"></i> </span>';
                            } else {
                              echo '<span class="btn btn-success"> <i class="fa fa-check"></i> '.$vidrows.'</span>';
                            }
                            ?>
                          </td>
                          <td valign="top"><?php echo date('j M Y',$date_published) ?></td>
                          <td valign="top"><?php
                          if ($banner == '') {
                            echo '<span class="btn btn-danger">N</span>';
                          } else {
                            echo '<span class="btn btn-success">Y</span>';
                          }
                          
                          ?></td>
                          <td valign="top">
                          <?php
                          if ($sort_order == '0') {
                            echo '<span class="btn btn-danger">Not displayed</span>';
                          } else {
                            echo "<span class=\"btn btn-success\">$sort_order</span>";
                          }
                          ?>
                          </td>
                        </tr>     
                          <?php
                          }
                        $numrows = $stmt->num_rows;
                          $stmt->close();
                          ?>
                          </tbody>
                      </table>
                     </div>
               </div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate=""  enctype="multipart/form-data">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Blog</div>
                        </div>

                        <div class="card-body">
                          <div class="form-group">
                           <label class="col-form-label" for="blog_title">Blog Post Title</label>
                           <input type="text" class="form-control" id="blog_title" name="blog_title" required  onchange="showUser(this.value)">
                              <span class="form-text" id="txtHint">Blog URL will be displayed here...</span>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="blog_abstract">Teaser Text</label>
                            <textarea class="form-control" rows="6" id="blog_abstract" name="blog_abstract"></textarea>
                          </div>
                          <div class="form-group">
                              <label class="col-form-label" for="page_txt1">Main Text</label>
                              <textarea id="page_txt1" name="page_txt1" rows="16" class="form-control summernote"></textarea>
                              <span class="form-text"><small>Local links should be absolute, e.g. https://www.thelggroup.co.uk/contact.php not contact.php</small></span>
                            </div>
                          <div class="form-group">
                              <label class="col-form-label" for="page_txt2">Second Text Block</label>
                              <textarea id="page_txt2" name="page_txt2" rows="16" class="form-control summernote" ></textarea>
                              <span class="form-text"><small>Local links should be absolute, e.g. https://www.thelggroup.co.uk/contact.php not contact.php</small></span>
                            </div>
                            <div class="form-group">
                              <label class="col-form-label" for="page_txt3">Third Text Block</label>
                              <textarea id="page_txt3" name="page_txt3" rows="16" class="form-control summernote"></textarea>
                              <span class="form-text"><small>Local links should be absolute, e.g. https://www.thelggroup.co.uk/contact.php not contact.php</small></span>
                            </div>
                          <div class="form-group">
                            <label class="col-form-label" for="sort_order"> Sort order</label>
                              <input type="number" class="form-control" id="sort_order" name="sort_order" value="12">
                              <span class="form-text"><small>Enter 0 if not to be displayed</small></span>
                          </div>
                          <div class="form-group">
                             <label class="col-form-label" for="googleTitle">Meta Title</label>
                              <input type="text" class="form-control" id="googleTitle" name="googleTitle">
                             <span class="form-text"><small>Optional - system will create this from the Blog Title if blank</small></span>
                          </div>
                          <div class="form-group">
                              <label class="col-form-label" for="googleDesc">Meta Description</label>
                                <textarea id="googleDesc" name="googleDesc" rows="3" class="form-control"></textarea>
                                <span class="form-text"><small>Optional - system will create this from the Blog Title if blank.</small></span>
                            </div>
                            <div class="form-group">
                               <label class="col-form-label" for="googleKeywords">Meta Keywords</label>
                              <input type="text" class="form-control" id="googleKeywords" name="googleKeywords">
                             <span class="form-text"><small>Optional - system will create this from the Blog Title if blank.</small></span>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="author">Blog Author</label>
                            <input type="text" class="form-control" id="author" name="author">
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="actioncall1">Action text #1</label>
                            <input type="text" class="form-control" id="actioncall1" name="actioncall1">
                          </div>
                          <div class="row">
                            <div class="col form-group">
                              <label class="col-form-label" for="buttonlink1">Button Link for #1</label>
                              <input type="text" class="form-control" id="buttonlink1" name="buttonlink1">
                            </div>
                            <div class="col form-group">
                              <label class="col-form-label" for="buttonname1">Button Label for #1</label>
                              <input type="text" class="form-control" id="buttonname1" name="buttonname1">
                            </div>
                          </div>
                          <div class="form-group">
                              <label class="col-form-label" for="actioncall2">Action text #2</label>
                              <input type="text" class="form-control" id="actioncall2" name="actioncall2">
                            </div>
                          <div class="row">
                            <div class="col form-group">
                              <label class="col-form-label" for="buttonlink2">Button Link for #2</label>
                              <input type="text" class="form-control" id="buttonlink2" name="buttonlink2">
                                <span class="form-text"><small>Local links should be absolute, e.g. /index.php not index.php</small></span>
                            </div>
                            <div class="col form-group">
                            <label class="col-form-label" for="buttonname2">Button Label for #2</label>
                              <input type="text" class="form-control" id="buttonname2" name="buttonname2">
                          </div>
                          </div>
                          
                          <h6>Search &amp; Filter Tags</h6>
                          <div class="form-group">
                            <label class="col-form-label">&nbsp;</label>
                            <div class="col-lg-12">
                              <?PHP
                              // Loop through tags 
                              $loopstmt = $mysqli->prepare("SELECT btagid, btag FROM blog_subject_tags");
                              $loopstmt->execute();
                              $loopstmt->store_result();
                              $loopstmt->bind_result($btagid, $btag);
                              while ($loopstmt->fetch()) {  
                                echo "<label class=\"checkbox-inline\"><input name=\"q$btagid\" type=\"checkbox\" id=\"$btagid\" value=\"$btagid\"/>$btag</label><br>\r";
                              }
                              $loopstmt->close();
                              ?>
                            </div>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Banner Image</label>
                              <input type="file" class="form-control" name="Image1" id="Image1">
                          </div>
                          <h4>Put images through <a href="https://squoosh.app/" target="_blank">Sqoosh!</a> before uploading to minimise size</h4>
                          <p>Size ratio 1900 x 500px</p>
                          <div class="form-group">
                            <label class="col-form-label"> Justification</label>
                              <select name="justification" class="form-control">
                                 <option value="0">Left</option>
                                 <option value="1" selected="selected">Centre</option>
                                 <option value="2">Right</option>
                              </select>
                          </div>
                          <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="sort_order" name="sort_order" value="0" min="0" max="999"><span class="form-text">Sort order after date sorting. Enter 0 if not to be displayed.</span>
                           </div>
                          <div class="form-group">
                            <label class="col-form-label" for="bannerTitle">Banner Title</label>
                            <input type="text" class="form-control" id="bannerTitle" name="bannerTitle">
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="bannerTxt" >Banner Subtitle</label>
                              <textarea name="bannerTxt" id="bannerTxt" rows="2" class="form-control" ></textarea>
                          </div>


                           
                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
               </div>
            </div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script>
  function showUser(str) {
      if (str == "") {
          document.getElementById("txtHint").innerHTML = "";
          return;
      } else { 
          if (window.XMLHttpRequest) {
              // code for IE7+, Firefox, Chrome, Opera, Safari
              xmlhttp = new XMLHttpRequest();
          } 
          xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                  document.getElementById("txtHint").innerHTML = this.responseText;
              }
          };
          xmlhttp.open("GET","createblogquerystring.php?q="+str,true);
          xmlhttp.send();
      }
  }
  </script>
   <script>
   $(document).ready(function() {
      $('#maintable').dataTable( {
        "pageLength": 10
      });
     $('.summernote').summernote({
        tabsize: 2,
        height: 160,
        spellCheck: true,
        dialogsInBody: true,
        toolbar: [
           ['style', ['style']],
           ['font', ['bold', 'underline', 'superscript', 'subscript']],
           ['color', ['color']],
           ['para', ['ul', 'ol', 'paragraph']],
           ['table', ['table']],
           ['insert', ['link', 'picture', 'video']],
           ['view', ['codeview', 'help']],
         ]
      });
   });
   </script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>