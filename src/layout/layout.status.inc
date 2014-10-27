<?php
  include_once "config.php";
  include_once "classes/ServersList.php";
  include_once "classes/RejikServer.php";
  include_once "classes/HealthPanel.php";

  //Фильтруем входные данные
  $id = filter_input(INPUT_GET, 'switch_master_to', FILTER_VALIDATE_INT);
  if ($id===False) throw new InvalidArgumentException ("Неверно задан входящий параметр 'switch_master_to'", 1);

try {
  //Инициализируем главный обьект
  $hp = new HealthPanel();

  //Если пришла команда смены мастера

  if ($id!==NULL) {
    $hp->switch_master($id);
    exit;
  }

  echo "<div class='page-header'>\n";
  echo "<h1>\nЗдоровье сервера</br>\n";

  echo "<small>".$hp->get_local_server_name()."</small>\n</h1>\n";
  echo "</div>\n";

  echo "<h3>Статус работы текущего сервера:</h3>\n";
  echo "<ul>\n";
  echo "<li><b>SQUID:</b> Работает</li>\n";
  echo "<li><b>SAMS:</b> Работает</li>\n";
  echo "</ul>\n";

  echo "<h3>Статус репликации:</h3>\n";
  //echo "<pre>\n"; print_r($_SESSION); echo "</pre>\n";
  //Стартуем проверку доступности серверов
  $hp->check_availability();
  
  echo "<div class='panel panel-default'>\n";
  echo "<table class='table table-condensed'>\n";
  echo "<thead>\n";
  echo "<tr>\n
        <th width='40%'>Имя сервера</th>\n
        <th>Доступность</th>\n
        <th>Статус</th>\n
        <th width='10%'> </th>\n
        </tr>\n";
  echo "</thead>\n";
  //echo "<pre>\n"; print_r($hp->get_servers()); echo "</pre>\n";
  foreach ($hp->get_servers() as $value) {
    echo "<tr>\n";
     echo "<td><span>{$value}</span>\n";
     $srv_id = $value->get_id();
     if ($value->get_work_mode()==WORK_MODE_MASTER) echo " <span class='label label-danger'>МАСТЕР</span>"; 
     echo "</td>\n";

     if ($avaiable = $value->is_connected()) {
       echo "<td class='text-success'>ДОСТУПЕН</td>";

       $err = $hp->check_for_errors($value);
       if (empty($err)) {
         echo "<td class='text-success'>OK</td>";  
       } else {
         echo "<td class='text-danger'>\n";
         echo "<a href='#srv{$srv_id}'
         data-toggle='collapse'
         data-target='#srv{$srv_id}'
         >\n";
         echo "<span class='glyphicon glyphicon-remove'></span> ОШИБКА\n";
         echo "</a>\n";
         echo "</td>\n";
         
       }
     } else {
       echo "<td class='text-danger'>НЕ ДОСТУПЕН</td>\n";  
       echo "<td> </td>\n";
     }
     
     if ($value->get_work_mode()==WORK_MODE_SLAVE) {
       echo "<td><button type='button' onClick='location.href=\"?action=status&switch_master_to=".$value->get_id()."\"' class='btn btn-primary btn-xs'> Сделать мастером</button></td>\n";  
     } else {
       echo "<td> </td>\n";
     }
     
    echo "</tr>\n";
    
    if (!empty($err)) {
        $data_content='';
        foreach ($err as $k => $v) $data_content.="{$k}: {$v}</br>\n";
      echo "<tr class='hiddenRow'><td colspan=4>
        <div id='srv{$srv_id}' class='collapse'>
          <div class='alert alert-danger hiddenAlert'>
            <small>{$data_content}</small>
          </div>
        </div>
      </td></tr>\n";
    }
    //echo "<tr><td colspan=4>"; foreach ($err as $k => $v) echo "{$k} = {$v}</br>"; echo "</td></tr>\n";
  }

  echo "</table>\n";
  echo "</div>\n"; // of panel

} catch (Exception $e) {
  echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
}
?>