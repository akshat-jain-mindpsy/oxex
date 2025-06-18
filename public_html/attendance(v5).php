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
    <link href='fullcalendar/lib/main.css' rel='stylesheet' />
    
    

  </head>
  <?php
    if (login_check($mysqli) != false) {
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
            $tableset = $mysqli->prepare("SELECT dtid, task, colour, textcolor FROM tasks ");
            $tableset->execute();
            $tableset->store_result();
            $tableset->bind_result($dtid, $task, $colour, $textcolor);
            while ($tableset->fetch()){
              $labeltext = $task;
              if ($textcolor == 1) {
                $task = "<span class=\"text-white\">$task</span>";
              }
              echo "<div id=\"d$dtid\" class=\"fc-event rounded px-3 py-1 mr-1 mb-1\" style=\"background-color:#$colour\" data-color=\"#$colour\">$task</div>";
            }
            $tableset->close();
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
    if (login_check($mysqli) != false) {
      include 'incl/glossary.php';
    }
    ?>
    <?php include 'incl/js.php' ?>
    <script src='fullcalendar/lib/main.js'></script>
    <script>
const encodeFormData = (data) => {
  var form_data = new FormData();

  for ( var key in data ) {
    form_data.append(key, data[key]);
  }
  return form_data;   
};

async function getUsers(_data) {
    fetch('addevent.php', {
      method: "POST",
      body: JSON.stringify(_data),
      headers: {"Content-type": "application/json;charset=UTF-8"}
    })
    //.then(response => response.json()) 
    .then(json => console.log(json))
    .catch(err => console.log(err));
};
  document.addEventListener('DOMContentLoaded', function() {
    var Calendar = FullCalendar.Calendar;
    var Draggable = FullCalendar.Draggable;

    var containerEl = document.getElementById('external-events');
    var calendarEl = document.getElementById('calendar');

    // initialize the external events
    new Draggable(containerEl, {
      itemSelector: '.fc-event',
      eventData: function(eventEl) {
        return {
          title: eventEl.innerText
        };
      }
    });
    // initialize the calendar
    var calendar = new Calendar(calendarEl, {
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth'
      },

      editable: true,
      droppable: true,
      
      
      drop: function (info, data) {
        var id = info.draggedEl.id;
        var color = info.draggedEl.dataset.color;
        var seldate = info.dateStr
        var trainee = "<?php echo $trainkey ?>";
        let _data = {
          id: info.draggedEl.id,
          seldate: info.dateStr,
          trainee: "0123"
        };
        getUsers(_data);
        //alert("color is" + color +", dragged id "+ id + " date is " + seldate);

  /*      fetch('addevent.php', {
        method: 'POST',
        headers: {'Accept': 'application/json'},
        body: encodeFormData(eventData)
      });*/
//console.log(eventData);

      },

    });

    calendar.render();
  });

</script>
  </body>
  <?php
    // logged in only!
    }
    ?>
</html>