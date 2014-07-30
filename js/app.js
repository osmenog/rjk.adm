var Rejik = {
  rejik_url: '/rejik2/ajax.php',
  visible_urls: 0,
  real_urls_count: 0,
  urls_per_page: 200,
  banlist_edit_url: function(row_elem) {
    //Функция открывает модальное окно для редактирования ссылки

    var modal = $('#superModal');

    //Вставляем старый УРЛ в поле для ввода
    var old_url = row_elem.find('td:first').text();
    modal.find('input').val(old_url);
    var id = row_elem.data('url-id');

    //Инициализируем обработчики событий
    modal.on('click.editurl', '#btn_save', function() {
      //console.log (_rejik);
      var new_url = modal.find('input').val(); //Тут нужно выполнить ряд проверок
      
      //Отправляем запрос на изменение ссылки
      //console.log ('change_url:  #'+id+'  '+new_url);    
    
      //Отправляем AJAX
      $.ajax(Rejik.rejik_url, {
        type: "POST",
        dataType: 'json',
        data: {action: 'change_url', id: id, url_name: new_url},
        success: function(response) {
          if ("error" in response) {
            console.log("API ErrorMsg: "+response.error.error_msg);
          }else {
            row_elem.find('td:first').text(new_url);
          }
        },
        error: function(request, err_t, err_m) {
          console.log("AJAX ErrorMsg: "+err_t+' '+err_m);
        },
        complete: function() {
          modal.off('click.editurl');
          modal.modal('hide');    
        },
        timeout: 3000
      });
    })

    //Отображаем модальное окно
    modal.modal('show');
  },
  
  banlist_delete_url: function(row_elem) {
    //Вставляем старый УРЛ в поле для ввода
    var id = row_elem.data('url-id');

    //Отправляем AJAX
    $.ajax(Rejik.rejik_url, {
      type: "POST",
      dataType: 'json',
      data: {action: 'delete_url', id: id},
      success: function(response) {
        if ("error" in response) {
          console.log("API ErrorMsg: "+response.error.error_msg);
        } else {
          row_elem.fadeOut(150).detach();
          
          //Уменьшаем счетчик ссылок и посылаем событие
          Rejik.visible_urls--;
          Rejik.real_urls_count--; 
          $('table#urls_table').trigger("rowchange");
        }
      },
      error: function(request, err_t, err_m) {
        console.log("AJAX ErrorMsg: "+err_t+' '+err_m);
      },
      timeout: 3000
    });
  },

  banlist_add_url: function () {
    var url = $('#addurl_box').find('input');
    
    if ((url.val()).trim().length == 0) return false;  //Если поле пустое, то ничего не добавляем

    var tmp = $("<tr>\n"+
                "<td>"+url.val().trim()+"</td>\n"+
                "<td width='5%'><a href='#' class='ctrl editurl'><span class='glyphicon glyphicon-pencil'></span></a></td>\n"+
                "<td width='5%'><a href='#' class='ctrl removeurl'><span class='glyphicon glyphicon-trash'></span></a></td>\n"+
                "</tr>\n");
    $('#urls_table').prepend(tmp);
    url.val('');

    //Если добавление новой ссылки выполнилось успешно, то увеличиваем счетчик ссылок
    Rejik.real_urls_count++;
    Rejik.visible_urls++;
    return true; //Если все ОК - то возвращаем true
  },

  banlist_create_showpopup: function() {
    $('input#bl_name').popover('show');  
  },

  banlist_editor_init: function() {
    Rejik.visible_urls = $('table#urls_table').find('tr').length; //Инициализируем счетчик строк в таблице ссылок
    Rejik.real_urls_count = $('table#urls_table').data('urlscount');
    Rejik.urls_per_page = $('#urls_panel').data("urls_per_page");

    $('table#urls_table').on('mouseenter','tr', function() {
      $(this).find('.ctrl').show();
      //console.log ('mouseenter');
    });

    $('table#urls_table').on('mouseleave','tr', function() {
      $(this).find('.ctrl').hide();
      //console.log ('mouseenter');
    });

    $('table#urls_table').on('click', '.editurl', function (e){
      e.preventDefault();
      Rejik.banlist_edit_url($(this).closest('tr'));
    });

    $('table#urls_table').on('click', '.removeurl', function (e){
      e.preventDefault();
      var row = $(this).closest('tr');
      Rejik.banlist_delete_url(row);
    });

    $('table#urls_table').on("rowchange", function (e){
      //Функция обновляет счетчики ссылок, и отображает, либо скрывает таблицу
      var tbl = $('table#urls_table');

      tbl.data('urlscount', Rejik.real_urls_count);
      $('#url_counter').text(Rejik.real_urls_count);

      //Количество строк в таблице
      if (Rejik.visible_urls==0) { 
        tbl.hide();
        $('#empty_table_label').show();
      }else{
        tbl.show();
        $('#empty_table_label').hide();
      }
    });

    $('#btn_addurl').on("click", function (e){
      e.preventDefault();
      Rejik.banlist_add_url()
      $('table#urls_table').trigger("rowchange");
    });
  },

  get_page: function(page) {
    console.log (page);
    var offset = (page-1) * Rejik.urls_per_page;

    //Отправляем AJAX
    $.ajax(Rejik.rejik_url, {
      type: "POST",
      dataType: 'json',
      data: {action: 'banlist.geturllist', banlist: 'avto-moto', offset: offset, length: Rejik.urls_per_page},
      beforeSend: function() {
        $('table#urls_table').fadeOut(200);

      },
      success: function(response) {
        if ("error" in response) {
          console.log("API ErrorMsg: "+response.error.error_msg);
        } else {
          $('table#urls_table').find('tbody').detach();
          
          var key;
          var tmp = $('<tbody></tbody>');
          var urls = response.urls;
          for (key in urls) {
            var row = $("<tr data-url-id='"+key+"'></tr>");
            row.append('<td>'+urls[key]+'</td>');
            row.append("<td width='5%'><a href='#' class='ctrl editurl'><span class='glyphicon glyphicon-pencil'></span></a></td>");
            row.append("<td width='5%'><a href='#' class='ctrl removeurl'><span class='glyphicon glyphicon-trash'></span></a></td>");
            tmp.append (row);
          }
          $('table#urls_table').append (tmp);
          

        }
      },
      error: function(request, err_t, err_m) {
        console.log("AJAX ErrorMsg: "+err_t+' '+err_m);
      },
      complete: function() {
        $('table#urls_table').fadeIn(200);
      },
      timeout: 3000
    });
  }
};



$(document).ready (function() {
  Rejik.banlist_editor_init();

  var pages_num = $('#urls_panel').data("pagescount");

//http://esimakin.github.io/twbs-pagination/#demo
$('#pagination-demo').twbsPagination({
  totalPages: pages_num,
  visiblePages: 10,
  onPageClick: function (event, page) {
    event.preventDefault;
    Rejik.get_page(page);
  }
});

});