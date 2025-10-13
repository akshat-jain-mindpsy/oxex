<?php
## STILL TO DO : ##
// update badges on days added
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
sec_session_start();
check_session_timeout();

include 'incl/sess.php';
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
// v3 https://codingshiksha.com/javascript/jquery-fullcalendar-integration-using-php-mysql-ajax/
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $googleTitle ?></title>
    <meta name="description" content="<?php echo $googleDesc ?>">
    <meta name="keywords" content="<?php echo $googleKeywords ?>">
    <?php include 'incl/meta.php' ?>
    <link href="fullcalendar-3.10.5/fullcalendar.css" rel="stylesheet">
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
            // list the coloured task labels
            // drag  to calender to store in timesheet table
            try {
            $taskRows = [];
            $stmtTasks = $supabase_pdo->query('select dtid, task, colour, textcolor from tasks');
            while ($row = $stmtTasks->fetch(PDO::FETCH_ASSOC)) { $taskRows[] = $row; }
            foreach ($taskRows as $row) {
              $dtid = (int)$row['dtid'];
              $task = $row['task'];
              $colour = $row['colour'];
              $textcolor = (int)$row['textcolor'];
              // how many this year for this trainee
              $numtasks = 0;
              $stmtCount = $supabase_pdo->prepare('select count(*) as c from timesheet where trainkey = ? and dtid = ? and taskdate >= ? and taskdate <= ?');
              $stmtCount->execute([$trainkey, $dtid, $valueyearstart, $valueyearend]);
              $countRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
              $numtasks = (int)($countRow['c'] ?? 0);
              if ($textcolor == 1) {
                $task = "<span class=\"text-white\">$task</span>";
              } else {
                $task = "<span class=\"text-dark\">$task</span>";
              }
              // task totals now on account.php
              //echo "<div id=\"d$dtid\" class=\"fc-event rounded px-3 py-2 mr-1 mb-2\" style=\"background-color:#$colour\" data-id=\"$dtid\" data-color=\"#$colour\">$task <span class=\"badge badge-dark float-right mr-2\" id=\"taskqty$dtid\">$numtasks</span></div>";
              echo "<div id=\"d$dtid\" class=\"fc-event rounded px-3 py-2 mr-1 mb-2\" style=\"background-color:#$colour\" data-id=\"$dtid\" data-color=\"#$colour\">$task</div>";
            }
            } catch (Throwable $e) { error_log('attendance tasks PDO error: ' . $e->getMessage()); }
            ?>
            </div>
            <!--<div id="calendarTrash" class="alert alert-secondary p3-5 px-3 mt-3"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Drag Events here to Delete</div>-->
            <?php echo $page_txt5; ?>
            <!--<a href="attendance.php" class="btn btn-block btn-nhs mt-2">Refresh Calendar</a>-->
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
    <script src="fullcalendar-3.10.5/lib/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>
    <script src="fullcalendar-3.10.5/lib/jquery-ui.min.js"></script>
    <script src="fullcalendar-3.10.5/lib/jquery.ui.touch-punch.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.2/moment.min.js"></script>
    <script src="fullcalendar-3.10.5/fullcalendar.js"></script>
    <script>

$(function() {
// needs touch punch to enable finger drap/drop
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

    // this sets the dropped event color and text
    $(this).data('event', {
      title: $.trim($(this).text()), // use the element's text as the event title
      stick: true, // maintain when user navigates (see docs on the renderEvent method)
      color: $(this).data("color")
    });

  });

  $('#calendar').fullCalendar({
    // options and callbacks 
    defaultView: 'month',
    longPressDelay: 100,
    eventOverlap: false,
    editable: true,
    eventDurationEditable:false,
    droppable: true,
    drop: function(date) {
      // for drag & drop from the task list
      var dropColor = $(this).data('color'); // the task colour
      var dropID = $(this).data('id'); // the task id to post
      var dropDate = date.format('YYYYMMDD'); // the selected date to post
      var trainee = "<?php echo $trainkey ?>"; // who
      //$(this).css('color', '#f09');
      $.ajax({
        url: "addevent.php",
        type: "POST",
        data: ({
          id: dropID,
          date: dropDate,
          trainee: trainee
        }),
        success: function(json) {
          //myJSON = JSON.stringify(json); // for testing
          //alert(myJSON);
        }
      });
      myColor = $(this).data("color");
      //console.log(dropColor);
    },
    eventDrop: function(event, delta, revertFunc) {
      // for moving a task from one date to another
      var dropDate = event.start.format('YYYYMMDD'); // new date to which we're moving
      var dropID = event.ID; // the task id to post
      var trainee = "<?php echo $trainkey ?>"; // who
      $.ajax({
        url: "amendevent.php",
        type: "POST",
        data: ({
          id: dropID,
          date: dropDate,
          trainee: trainee
        }),
        success: function(json) {
          //myJSON = JSON.stringify(json); // for testing
          //alert(myJSON);
        }
      });

      //console.log(dropDate," ",dropID)

    },
    eventClick: function(calEvent, jsEvent, view) {
      var trainee = "<?php echo $trainkey ?>"; // who
      var dropID = calEvent.ID; // the task id to post

      var answer = confirm("Delete this Task from this date?")
      if (answer){
        $('#calendar').fullCalendar('removeEvents' , function(ev){  
          return (ev._id == calEvent._id);
        });
        $.ajax({
          url: "deleteevent.php",
          type: "POST",
          data: ({
            id: dropID,
            trainee: trainee
          }),
          success: function(json) {
            //myJSON = JSON.stringify(json); // for testing
            //alert(myJSON);
          }
        });

      }
    //alert('Event: ' + calEvent.title + ' id ' + calEvent.ID);
  

    // change the border color just for fun
    //$(this).css('border-color', 'red');

  },
     events: [
      <?php
      // find all tasks for this trainee and show on calendar
      try {
        $stmt = $supabase_pdo->prepare('select t.tsid, t.taskdate, s.task, s.colour, s.textcolor from timesheet t join tasks s on t.dtid = s.dtid where t.trainkey = ?');
        $stmt->execute([$trainkey]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $tsid = $row['tsid'];
          $taskdate = strtotime($row['taskdate']);
          $task = $row['task'];
          $colour = $row['colour'];
          $textcolor = (int)$row['textcolor'];
          $showdate = date('Y-m-d', $taskdate);
          $writecolor = '#212b32'; # default text
          if ($textcolor == 1) { $writecolor = '#fff'; }
          echo "{";
          echo "ID : '$tsid',";
          echo "title : '$task',";
          echo "start : '$showdate',";
          echo "backgroundColor : '#$colour',";
          echo "borderColor : '#$colour',";
          echo "textColor : '$writecolor',";
          echo "},";
        }
      } catch (Throwable $e) { error_log('attendance events PDO error: ' . $e->getMessage()); }
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