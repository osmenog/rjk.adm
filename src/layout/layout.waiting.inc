<?php
  include_once "config.php";
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Rejik 2.0</title>
        <meta charset='UTF-8'>
        <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <!-- <link href="css/style.css" rel="stylesheet"> -->
        <!-- <link href="css/font-awesome.min.css" rel="stylesheet"> -->

        <!--<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>-->
        <script src="js/jquery.min.js"></script>
        <script src='js/bootstrap.min.js'></script>
    </head>
    <body>
      <div class="container wrapper">
        <h1><center>Подождите, пока мы опрашиваем другие сервера...</center></h1>
      </div>
      <script type="text/javascript">
        function print_alert_box(response) {
          //Функция выводит алерт с ошибкой
          var tmp = $('<div class=\'alert alert-danger\'></div>');
          tmp.append ("<b>Произошла ошибка: </b>");
          tmp.append (response.error_msg+'</br>');
          tmp.append ("Type: "+response.error_type+'</br>');
          tmp.append ("Trace: "+response.error_trace);
          $('.container').append (tmp);
        }

        $(document).ready (function() {
          //Отправляем AJAX
          
          <?php
            global $config;
            echo "var rejik_path = '/{$config['proj_name']}';";
          ?>
          var data = {
            action: 'server.check_availability'
            };

          $.ajax(rejik_path+'/ajax.php?v=1', {
            type: "POST",
            dataType: 'json',
            data: data,
            success: function(response) {
              console.log (response);
              if ("error" in response) {
                print_alert_box(response.error);
              } else {
                location.assign(rejik_path+'/index.php?action=status');
              }
            },
            error: function(request, err_t, err_m) {
              console.log (err_t+' '+err_m);
              print_alert_box({'error_msg': err_m, 'error_type': err_t});
            },
            timeout: 10000
          });
        });
      </script>
    </body>
</html>

