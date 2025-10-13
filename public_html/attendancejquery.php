<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
sec_session_start();
check_session_timeout();

include 'incl/sess.php';
// v3 https://codingshiksha.com/javascript/jquery-fullcalendar-integration-using-php-mysql-ajax/
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $googleTitle ?></title>
    <meta name="description" content="<?php echo $googleDesc ?>">
    <meta name="keywords" content="<?php echo $googleKeywords ?>">
    <?php include 'incl/meta.php' ?>
    <link href='fullcalendar-3.10.5/fullcalendar.css' rel='stylesheet' />
    
    

  </head>
  <?php
    if (login_check($pdo) != false) {
      // logged in only!
    ?>
  <body>
    <?php include 'incl/banner.php' ?>
    <?php include 'incl/subnav.php' ?>
    <div class="container-fluid py-5" id="main">
      <div class="container">
        <div class="row">
          <div class="col-xs-12 col-sm-8 offset-sm-2 text-center">
            <?php
            if ($page_title != '') {
              echo "<h1>$page_title</h1>";
            }
            ?>
          </div>
        </div>
      </div>
      <div class="container">
        <div class="row my-3">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            <?php
              echo $page_txt2;
              echo $page_txt3;
            ?>
          </div>
        </div>
      </div>
      <div class="container">
        <div class="row">
          <div class="col-xs-12 col-lg-4">
            <?php echo $page_txt4; ?>
            <div id="external-events">
            <?php
            // calender https://fullcalendar.io/docs/external-dragging
            // list the coloured event labels
            // drag  to calender to store in timesheet table
            $usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
            if ($usingSupabase) {
            	$stmt = $supabase_pdo->query('select dtid, task, colour, textcolor from tasks');
            	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            		$dtid = (int)$row['dtid'];
            		$task = $row['task'];
            		$colour = $row['colour'];
            		$textcolor = (int)$row['textcolor'];
            		if ($textcolor == 1) {
            			$task = "<span class=\"text-white\">$task</span>";
            		} else {
            			$task = "<span class=\"text-dark\">$task</span>";
            		}
            		echo "<div id=\"d$dtid\" class=\"fc-event rounded px-3 py-2 mr-1 mb-2\" style=\"background-color:#$colour\" data-id=\"$dtid\" data-color=\"#$colour\">$task</div>";
            	}
            }
            ?>
            </div>
            <div id="calendarTrash" class="alert alert-secondary p3-5 px-3 mt-3"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Drag Events here to Delete</div>
          </div>

          <div class="col-xs-12 col-lg-8">
            <div id='calendar'></div>
          </div>
        </div>
      </div>
    </div>
    <?php include 'incl/footer.php' ?>
    <?php
    if (login_check($pdo) != false) {
      include 'incl/glossary.php';
    }
    ?>
    <script src='fullcalendar-3.10.5/lib/jquery.min.js'></script>
    <script src='fullcalendar-3.10.5/lib/jquery-ui.min.js'></script>
    <script src='fullcalendar-3.10.5/lib/moment.min.js'></script>
    <script src='fullcalendar-3.10.5/fullcalendar.js'></script>
    <script>
$(function() {

  $('#external-events .fc-event').each(function() {
    // store data so the calendar knows to render an event upon drop
    $(this).data('event', {
      title: $.trim($(this).text()), // use the element's text as the event title
      stick: true // maintain when user navigates (see docs on the renderEvent method)
    });

    // make the event draggable using jQuery UI
    $(this).draggable({
      zIndex: 999,
      revert: true,      // will cause the event to go back to its
      revertDuration: 0  //  original position after the drag
    });

  });

  $('#calendar').fullCalendar({
    // put your options and callbacks here
    defaultView: 'month',
    editable: true,
    droppable: true,
    drop: function(date) {
      var dropColor = $(this).data('color');
      var dropID = $(this).data('id');
      var dropDate = date.format('YYYYMMDD');
      var trainee = "<?php echo $trainkey ?>";
      $.ajax({
        url: "addevent.php",
        type: "POST",
        //dataType: "json",
        //contentType: 'application/json',
        //data: JSON.stringify( { "id": id, "date": dropDate } ),
        data: ({
          id: dropID,
          date: dropDate,
          trainee: trainee
        }),
        success: function(json) {
          myJSON = JSON.stringify(json);
          //alert(myJSON);
        }
      });
      //console.log(dropDate);
    },
     events: [
      <?php
      $usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
      if ($usingSupabase) {
      	$stmt = $supabase_pdo->prepare('select t.tsid, t.taskdate, s.task, s.colour, s.textcolor from timesheet t join tasks s on t.dtid = s.dtid where t.trainkey = ?');
      	$stmt->execute([$trainkey]);
      	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
      		$tsid = (int)$row['tsid'];
      		$taskdate = strtotime($row['taskdate']);
      		$task = $row['task'];
      		$colour = $row['colour'];
      		$textcolor = (int)$row['textcolor'];
      		$showdate = date('Y-m-d',$taskdate);
      		$writecolor = '#212b32';
      		if ($textcolor == 1) { $writecolor = '#fff'; }
      		echo "{";
      		echo "ID : '$tsid',";
      		echo "title : '".str_replace("'", "\\'", $task)."',";
      		echo "start : '$showdate',";
      		echo "backgroundColor : '#$colour',";
      		echo "borderColor : '#$colour',";
      		echo "textColor : '$writecolor',";
      		echo "},";
      	}
      }
      ?>
      ],
  })

});

</script>
  </body>
  <?php
    // logged in only!
    }
    ?>
</html>