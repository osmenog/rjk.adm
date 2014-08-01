<?php
  include_once "config.php";
  global $config;

  try{
		$prx = new proxy_worker ($config['sams_db']);
		$rejik = new rejik_worker ($config['rejik_db']);
		$bl_info = $rejik->get_banlist_info ($banlist);

		if ($bl_info == 0) {
			echo "<div class='alert alert-danger'><b>Ошибка!</b> Банлист <i>$banlist</i> не найден в базе.</div>\n";
			return; //Выходим, если бан лист пустой.
		}

		//--------------- Инициализация модального окна ---------------
		echo "<!-- Modal -->\n";
		echo "<div class='modal fade' id='superModal'>\n";
		echo "  <div class='modal-dialog'>\n";
		echo "    <div class='modal-content'>\n";
		echo "      <div class='modal-header'>\n";
		echo "        <button type='button' class='close' data-dismiss='modal'><span aria-hidden='true'>&times;</span><span class='sr-only'>Close</span></button>\n";
		echo "        <h4 class='modal-title'>Редактирование URL адреса</h4>\n";
		echo "      </div>\n";
		echo "      <div class='modal-body'>\n";
		echo "        <p>Укажите новый адрес:</p>\n";
		echo "				<div class='input-group'>\n";
		echo "					<input type='text' class='form-control' placeholder='Введите сюда URL адрес' value='<Тут будет редактируемая ссылка>'>";
		echo "					<span class='input-group-addon'>@</span>\n";
		echo "				</div>\n";
		echo "      </div>\n";
		echo "      <div class='modal-footer'>\n";
		echo "        <button id='btn_close' type='button' class='btn btn-default' data-dismiss='modal'>Закрыть</button>\n";
		echo "        <button id='btn_save' type='button' class='btn btn-primary'>Сохранить</button>\n";
		echo "      </div>\n";
		echo "    </div>\n";
		echo "  </div>\n";
		echo "</div>\n";

		$users_in_banlist = $rejik->get_banlist_users ($banlist);
		//--------------- Заголовок страницы ---------------
		echo "<div class='page-header'>\n";
		echo "<h2>Банлист: <b>{$banlist}</b><br/><small>{$bl_info['full_desc']}</small></h2>\n";
		echo "</div>\n";
		
		//--------------- Раздел пользователей ---------------
		$users_count = ($users_in_banlist!=0) ? count($users_in_banlist) : 0;
		//Заголовок панельки
		echo "<div id='user_panel' class='panel panel-default'>\n";
		echo "  <div class='panel-heading'>";
		echo "    <div class='row' style='line-height: 27px;'>\n";
		echo "      <div class='col-sm-7'>\n";
		echo "        <span>Пользователи ";
		if ($users_count!=0) echo "<small>(всего <b>{$users_count}</b> ".num_case ($users_count, 'человек', 'человека', 'человек').")</small>";
		echo "        </span>\n";	
		echo "      </div>\n";
		echo "      <div class='col-sm-5'>\n";
		echo "        <div class='search-box-small input-group'>\n";
		echo "          <input type='text' class='form-control' placeholder='Поиск'>\n";
		echo "          <span class='input-group-btn'>\n";
		echo "            <button class='btn btn-default' type='button'><span class='glyphicon glyphicon-search'></button>\n";
		echo "          </span>\n";
		echo "        </div>\n"; // of search-box
		echo "      </div>\n"; //of col-sm-5
		echo "    </div>\n"; //of row
		echo "  </div>\n"; //of panel-heading
		
		//Тело панельки
		echo "  <div class='panel-body'>\n";
		if ($users_in_banlist!=0) {			
			echo "<div class='panel panel-default'>\n";
			echo "<table class='table table-striped table-hover'>\n";
		    echo "  <tr><th>Логин</th><th>ФИО</th></tr>\n";
			foreach ($users_in_banlist as $value) {
				echo "<tr>\n";
				echo "  <td><a href='?action=getuser&user={$value}'>{$value}</a></td>\n";
				$usr_info = $prx->get_userinfo ($value);
				echo "  <td>{$usr_info['family']} {$usr_info['name']} {$usr_info['soname']}</td>\n";
				echo "</tr>\n";
			};
			echo "</table>\n";
			echo "</div>\n"; //of table panel
		} else { //Если нет связанных пользователей.
			echo "<p>К данной категории не относится ни одного пользователя</p>\n";
		}
		echo "  </div>\n"; //of panel
		echo "</div>\n"; //of panel-body

		//====================================================== Раздел адресов (URLs) ========================================================================
		$bl_count = $rejik->get_banlist_urls_count ($banlist);
		$urls_per_page = $config ['urls_per_page']; // Определяем, сколько УРЛов выводить на страницу 

		//Высчитываем кольчество страниц и количество групп страниц
		$pages_count = ($bl_count>$urls_per_page) ? floor($bl_count/$urls_per_page) : 1; //Всего страниц
		if ($bl_count>$urls_per_page and $bl_count%$urls_per_page!=0) $pages_count++; //Остаток
		
		//Вычисляем текущую страницу
		$current_page = (isset($_GET['p']) && intval($_GET['p'])) ? intval($_GET['p']) : 1;
		if ($current_page>$pages_count || $current_page<1) $current_page=1;

		echo "<div id='urls_panel' class='panel panel-default' data-pagescount='{$pages_count}' data-urls_per_page='{$urls_per_page}'>\n";
		echo "  <div class='panel-heading'>";
		echo "    <div class='row' style='line-height: 27px;'>\n";
		echo "      <div class='col-sm-7'>\n";
		echo "        <span>URL Адреса <small>(всего <b><span id='url_counter'>{$bl_count}</span></b> ".num_case ($bl_count, 'сайт', 'сайта', 'сайтов').")</small></span>\n";	
		echo "      </div>\n";
		echo "      <div class='col-sm-5'>\n";
		echo "        <div class='search-box-small input-group'>\n";
		echo "          <input type='text' class='form-control' placeholder='Поиск'>\n";
		echo "          <span class='input-group-btn'>\n";
		echo "            <button class='btn btn-default' type='button'><span class='glyphicon glyphicon-search'></button>\n";
		echo "          </span>\n";
		echo "        </div>\n"; // of search-box
		echo "      </div>\n"; //of col-sm-5
		echo "    </div>\n"; //of row
		echo "  </div>\n"; //of panel-heading
		echo "  <div class='panel-body'>\n";
		
		function get_page ($banlist, $page=0, $urls_per_page=100) {
			global $config;
			$rejik = new rejik_worker ($config['rejik_db']);

			return $rejik->get_banlist_urls($banlist, true, ($page-1)*$urls_per_page, $urls_per_page);
		}

		function print_pagenator($pages_count, $current_page=1) {
			echo "  <ul id='pagination-demo' class='pagination pagination-sm' style='margin: 0 0 10px 0;'>\n";
			echo "    <li><a href='#'>&laquo;</a></li>\n";
			
			$tmp = ($pages_count<=10) ? $pages_count : 10;
			for ($i=1; $i<=$tmp; $i++) {
				echo "<li".(($i==$current_page) ? " class='active' " : "")."><a href='#'>{$i}</a></li>\n";	
			}

			echo "    <li><a href='#'>&raquo;</a></li>\n";
			echo "  </ul>\n";
		}

		echo "<div id='addurl_box' class='input-group'>\n";
		echo "   <input type='text' class='form-control'>\n";
		echo "   <span class='input-group-btn'>\n";
		echo "      <button id='btn_addurl' class='btn btn-success' type='button'><span class='glyphicon glyphicon-plus'></span> Добавить URL</button>\n";
		echo "   </span>\n";
		echo "</div><br/>\n";

		//echo "<h1>{$pages_count}|curr={$current_page}</h1>\n";

		//Выводим "пейдженатор" если кол-во страниц больше одной
		if ($pages_count>1) print_pagenator($pages_count, $current_page);

		echo "<div class='panel panel-default'>\n";
		echo "<table id='urls_table' class='table table-striped table-hover table-condensed' cols='3' data-banlist='{$banlist}' data-urlscount='{$bl_count}'>\n";
		if ($bl_count!=0) {
			$bl_urls = get_page ($banlist, $current_page, $urls_per_page);
			foreach ($bl_urls as $value) {
				echo "<tr data-url-id='{$value[0]}'>\n";
				echo "  <td>{$value[1]}</td>\n";
				echo "  <td width='5%'><a href='#' class='ctrl editurl'><span class='glyphicon glyphicon-pencil'></span></a></td>\n";
				echo "  <td width='5%'><a href='#' class='ctrl removeurl'><span class='glyphicon glyphicon-trash'></span></a></td>\n";
				echo "</tr>\n";
			};	
		}
		echo "</table>\n";
		echo "</div>\n"; //panel
		echo "<span id='empty_table_label' ".
				 (($bl_count!=0) ? "style='display: none'" : "").
				 "><p>К данной категории не относится ни одного сайта</p></span>\n";
		echo "  </div> <!-- pb -->\n"; //panel-body
		echo "</div>\n";
	} catch (mysql_exception $e) {
		echo "<div class='alert alert-danger'><b>Ошибка SQL!</b> {$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
	} catch (exception $e) {
		echo "<div class='alert alert-danger'><b>Неопознанная ошибка!</b><br/><pre>{$e->getTraceAsString()}</pre></div>\n";
	}
?>