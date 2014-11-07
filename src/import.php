<?php
include_once "config.php";
require_once "classes/Classes.php";
?>

<!DOCTYPE html>
<html>
<head>
  <title>Rejik 2.0</title>
  <meta charset='UTF-8'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body>
	<?php

//Скрипт может выполняться долгое время. Увеличиваем таймаут.
set_time_limit(120);

$imp = new importer();
$imp->start();
?>

	<!-- Boostrap -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>

<?php
class importer
{
  public $rejik_path = 'C:\\MyFiles\\xampp\\htdocs\\rejik2\\';
  private $csv_file_path;
  static $csv_import_count = 0;
  
  private function dir_contents($full_path) {
    
    // Description ...: Возвращает содержимое каталога в виде массива
    // Parameters ....: $full_path - полный путь до каталога
    // Return values .: Успех - Возвращает массив обьекта [name] [file|dir]
    //                        - Возвращает NULL если каталог пустой
    //                  Неудача - Возвращает FALSE если каталога не существует
    // -------------------------------------------------------------------------
    if (is_dir($full_path) && $dh = opendir($full_path)) {
      $entry = array();
      while (($file = readdir($dh)) !== false) {
        if ($file != "." && $file != "..") {
          
          //&& filetype($full_path . $file) != "file"
          $fp = $full_path . $file;
          
          //echo "<p>$fp</p>\n";
          $entry[$file] = filetype($fp);
          
          //print_r ($entry);
          
        }
      }
      if (count($entry) == 0) return NULL;
      
      closedir($dh);
      return $entry;
    } else {
      return FALSE;
    }
  }
  
  private function import_urls($file_path, $bl) {
    if (!file_exists($file_path)) return False;
    
    $res = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($res === false) {
      echo "Не могу открыть файл: {$file_path}\n";
      return FALSE;
    }
    
    //Открываем csv файл
    $hdl = fopen($this->csv_file_path, "a");
    if (!$hdl) {
      echo "Не могу открыть файл {$this->csv_file_path}\n";
      return FALSE;
    }
    
    //Колисество УРЛов в файле
    $this::$csv_import_count+= count($res);
    
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    // Получаем URL
    // все, что идет после ? обрезаем.
    // кодируем URL
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    $csv_create_time_start = microtime(true);
     // Замеряем время
    //Добавляем все УРЛы в CSV файл
    $buffer = "";
    foreach ($res as $k => $v) {
      
      // Выполняем преобразование УРЛ. и сохраняем его в БД.
      if (strpos($v, ';') !== false) {
        echo "$v;$bl\n";
        continue;
      }
      $buffer.= "$v;$bl\n";
    }
    fwrite($hdl, $buffer);
    
    $csv_create_time_start = microtime(true) - $csv_create_time_start;
    echo "<h4>Банлист - {$bl}; Затраченное время: " . round($csv_create_time_start, 3) . "</h4>\n";
    fclose($hdl);
  }
  
  private function import_db() {
    
    // Инициируем соединение с режикомю
    global $config;
    $rejik = new rejik_worker($config['rejik_db']);
    $rejik->import_db($this->csv_file_path);
  }
  
  public function start() {
    $this->csv_file_path = $_SERVER['DOCUMENT_ROOT'] . "/rejik2/" . "import.csv";
    if (file_exists($this->csv_file_path)) {
      if (!unlink($this->csv_file_path)) {
        echo "<p>Не могу удалить файл: {$this->csv_file_path}</p>\n";
        return;
      };
    }
    
    //echo "<h1>{$this->csv_file_path}</h1>\n";
    
    $dir_path = $this->rejik_path . 'banlists\\';
    $root_dir_content = $this->dir_contents($dir_path);
    if (!$root_dir_content) {
      echo "<p>Немогу открыть каталог {$dir_path}</p>\n";
      return;
    }
    
    //echo "<ul>\n";
    
    //Последовательно открываем все каталоги в родительском.
    foreach ($root_dir_content as $k => $v) {
      if ($v == 'dir') {
        
        //echo "<li><b>{$k}</b></li>";
        
        //Получаем содержимого дочернего каталога
        $tmp_dir_content = $this->dir_contents($dir_path . '\\' . $k . '\\');
        
        //Проверяем, если каталог пустой
        if (is_null($tmp_dir_content)) {
          echo "Каталог {$tmp_dir_content} пустой\n";
          continue;
        }
        
        //Проверяем наличие файла urls и prce
        if (!array_key_exists('urls', $tmp_dir_content) && !array_key_exists('pcre', $tmp_dir_content)) {
          echo "Файлы urls или pcre отсутствуют!\n";
          continue;
        }
        
        //Выполняем обработку файлов
        if (array_key_exists('urls', $tmp_dir_content)) $this->import_urls($dir_path . '\\' . $k . '\\' . 'urls', $k);
        
        //if (array_key_exists('pcre', $tmp_dir_content)) $this->import_urls ($dir_path.'\\'.$k.'\\'.'pcre');
        
      }
    }
    
    //echo "</ul>\n";
    
    echo "<h1>Импортировано в CSV файл: {$this::$csv_import_count} записей</h1>\n";
    flush();
    
    //Импорт в SQL
    $this->import_db();
    return;
  }
}
?>