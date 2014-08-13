$(document).ready (function() {

  panel_urls = $('#panel_urls');
  table_urls = $('table#urls_table');
  
  Rejik.banlist_editor_init();



});

var panel_urls, table_urls;

var Rejik = {
  rejik_url: '/rejik2/ajax.php?v=1',    // Путь до AJAX API
  visible_urls: 0,                      // Количество ссылок, отображаемых на экране (меняется при удалении или добавлении ссылок)
  real_urls_count: 0,                   // Общее количество ссылок в банлисте
  urls_per_page: 200,                   // Макс. количество ссылок на одной странице
  current_banlist: '',                  // Текущий просматриваемый банлист (необходимо для ajax)
  pages_num: 0,                         // Количество отображаемых страниц

  sign: function (data) {
    //Функция добавляет подпись к массиву data
    if ("sig" in data) {
      delete data['sig'];
    }
  
    //Тут находится ненадежный код, т.к. массив data не сортируется по убыванию.
    
    var str_data='';
    for (var i in data) str_data+=i+'='+data[i];
    var md5_data = MD5(str_data);
    return md5_data;
  },

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
      var json_data = {
        action: 'banlist.changeURL',
        banlist: Rejik.current_banlist,
        id: id,
        url: new_url
      };
      json_data['sig'] = Rejik.sign(json_data);

      //Отправляем AJAX
      $.ajax(Rejik.rejik_url, {
        type: "POST",
        dataType: 'json',
        data: json_data,
        success: function(response) {
          if ("error" in response) {
            console.log("API ErrorMsg: "+response.error.error_msg);
            return;
          }
            row_elem.find('td:first').text(new_url);

        },
        error: function(request, err_t, err_m) {
          console.log("AJAX ErrorMsg: "+err_t+' '+err_m);
        },
        complete: function() {
          modal.off('click.editurl');
          modal.modal('hide');    
        },
        timeout: 10000
      });
    })

    //Отображаем модальное окно
    modal.modal('show');
  },
  
  banlist_delete_url: function(row_elem) {
    //Вставляем старый УРЛ в поле для ввода
    var id = row_elem.data('url-id');


    var json_data = {
        action: 'banlist.removeURL',
        banlist: Rejik.current_banlist,
        id: id,
    };
    json_data['sig'] = Rejik.sign(json_data);

    //Отправляем AJAX
    $.ajax(Rejik.rejik_url, {
      type: "POST",
      dataType: 'json',
      data: json_data,
      success: function(response) {
        if ("error" in response) {
          console.log("API ErrorMsg: "+response.error.error_msg);
          return;
        }
        row_elem.fadeOut(150).detach();
          
        //Уменьшаем счетчик ссылок и посылаем событие
        Rejik.visible_urls--;
        Rejik.real_urls_count--; 
        table_urls.trigger("rowchange");
      },
      error: function(request, err_t, err_m) {
        console.log("AJAX ErrorMsg: "+err_t+' '+err_m);
      },
      timeout: 10000
    });
  },

  banlist_add_url: function (new_url) {
    var deferer = $.Deferred();

    //Готовим данные
    var json_data = {
        action: 'banlist.addURL',
        banlist: Rejik.current_banlist,
        url: new_url
    };
    json_data.sig = Rejik.sign(json_data)

    //Отправляем AJAX
    $.ajax(Rejik.rejik_url, {
      type: "POST",
      dataType: 'json',
      data: json_data,
      success: function(response) {
        if ("error" in response) {
          deferer.reject(response.error.error_msg);
          return deferer;
        }
        deferer.resolve(response.id);
      },
      error: function(request, err_t, err_m) {
        deferer.reject("AJAX ErrorMsg: "+err_t+' '+err_m);
      },
      // complete: function() {
      //   table_urls.trigger("rowchange");
      // },
      timeout: 10000
    });

    return deferer;
  },

  banlist_create_showpopup: function() {
    $('input#bl_name').popover('show');  
  },

  banlist_search_url: function(query) {
    var json_data = {
        action: 'banlist.searchURL',
        banlist: Rejik.current_banlist,
        query: query
    };
    json_data.sig = Rejik.sign(json_data)

    $.ajax(Rejik.rejik_url, {
      type: "POST",
      dataType: 'json',
      data: json_data,
      beforeSend: function() {
        $('table#urls_table').fadeOut(200);
      },
      success: function(response) {
        if ("error" in response) {
          console.log("API ErrorMsg: "+response.error.error_msg);
        } else {
          table_urls.find('tbody').detach();
          
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
          table_urls.append (tmp);
        }
      },
      error: function(request, err_t, err_m) {
        console.log("AJAX ErrorMsg: "+err_t+' '+err_m);
      },
      complete: function() {
        table_urls.fadeIn(200);
      },
      timeout: 10000
    });
  },

  banlist_editor_init: function() {
    //Инициализируем глобальные переменные
    Rejik.visible_urls = table_urls.find('tr').length;
    Rejik.real_urls_count = table_urls.data('urlscount');
    Rejik.urls_per_page = panel_urls.data("urls_per_page");
    Rejik.current_banlist = table_urls.data('banlist');
    Rejik.pages_num = panel_urls.data("pagescount");

    table_urls.on('mouseenter','tr', function() {
      $(this).find('.ctrl').show();
      //console.log ('mouseenter');
    });

    table_urls.on('mouseleave','tr', function() {
      $(this).find('.ctrl').hide();
      //console.log ('mouseenter');
    });

    table_urls.on('click', '.editurl', function (e){
      e.preventDefault();
      Rejik.banlist_edit_url($(this).closest('tr'));
    });

    table_urls.on('click', '.removeurl', function (e){
      e.preventDefault();
      var row = $(this).closest('tr');
      Rejik.banlist_delete_url(row);
    });

    table_urls.on("rowchange", function (e){
      //Функция обновляет счетчики ссылок, и отображает, либо скрывает таблицу
      e.preventDefault;
      
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

    $('#addurl_box').on("click", "button", function (e){
      e.preventDefault();
      var url_box = $(this).closest(".input-group").find("input");

      
      //Если поле пустое, то ничего не добавляем
      var url = (url_box.val()).trim();
      if (url.length == 0) return false;  
      
      var add_url = Rejik.banlist_add_url(url);
      
      //Обработчик событий в случае успеха
      add_url.done(function(id){
        var tmp = $("<tr data-url-id='"+id+"'>\n"+
                "<td>"+url+"</td>\n"+
                "<td width='5%'><a href='#' class='ctrl editurl'><span class='glyphicon glyphicon-pencil'></span></a></td>\n"+
                "<td width='5%'><a href='#' class='ctrl removeurl'><span class='glyphicon glyphicon-trash'></span></a></td>\n"+
                "</tr>\n");
        $('#urls_table').prepend(tmp);
    
        url_box.val('');

        //Если добавление новой ссылки выполнилось успешно, то увеличиваем счетчик ссылок
        Rejik.real_urls_count++;
        Rejik.visible_urls++;
      });

      //Обработчик событий в случае ошибки
      add_url.fail(function(err_msg){
        console.log("API ErrorMsg: "+err_msg);
        $('#addurl_box').addClass("has-error has-feedback");
        var url_box = $('#addurl_box');
        url_box.data({toggle:"popover", content: err_msg, placement: "left"});
        url_box.attr("title", "Ошибка");
        url_box.popover('show'); 
      });

      ////Обработчик событий при исполнении
      add_url.always(function(){
        table_urls.trigger("rowchange");
      })
              
    });

    // $('#addurl_box').on("click", "input", function (e){
    //   e.preventDefault();
    //   //var url_box = $(this).closest(".input-group").find("input");
    //   //url_box.popover('hide');
    // });

    // $('#btn_searchurl').on('click', function (e){
    //   e.preventDefault();
    //   var query = $('#inpt_search_url').val();
    //   Rejik.banlist_search_url(query);
    // });

    $('#pagination-demo').twbsPagination({
      totalPages: Rejik.pages_num,
      visiblePages: 10,
      onPageClick: function (event, page) {
        event.preventDefault;
        Rejik.get_page(page);
      }
    });
  },

  get_page: function(page) {
    //console.log (page);
    var offset = (page-1) * Rejik.urls_per_page;

    var data = {
        action: 'banlist.getURLlist',
        banlist: Rejik.current_banlist,
        limit: Rejik.urls_per_page,
        offset: offset};

    data.sig = Rejik.sign(data);
    //Отправляем AJAX
    $.ajax(Rejik.rejik_url, {
      type: "POST",
      dataType: 'json',
      data: data,
      beforeSend: function() {
        $('table#urls_table').fadeOut(200);
      },
      success: function(response) {
        if ("error" in response) {
          console.log("API ErrorMsg: "+response.error.error_msg);
        } else {
          table_urls.find('tbody').detach();
          
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
          table_urls.append (tmp);
          

        }
      },
      error: function(request, err_t, err_m) {
        console.log("AJAX ErrorMsg: "+err_t+' '+err_m);
      },
      complete: function() {
        table_urls.fadeIn(200);
      },
      timeout: 10000
    });
  }
};

function ksort(w) {
  var sArr = [], tArr = [], n = 0;

  for (i in w){
    tArr[n++] = i;
  }

  tArr = tArr.sort();
  for (var i=0, n = tArr.length; i<n; i++) {
    sArr[tArr[i]] = w[tArr[i]];
  }
  return sArr;
}

var MD5 = function (string) {
    function RotateLeft(lValue, iShiftBits) {
        return (lValue<<iShiftBits) | (lValue>>>(32-iShiftBits));
    }
    function AddUnsigned(lX,lY) {
        var lX4,lY4,lX8,lY8,lResult;
        lX8 = (lX & 0x80000000);
        lY8 = (lY & 0x80000000);
        lX4 = (lX & 0x40000000);
        lY4 = (lY & 0x40000000);
        lResult = (lX & 0x3FFFFFFF)+(lY & 0x3FFFFFFF);
        if (lX4 & lY4) {
            return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
        }
        if (lX4 | lY4) {
            if (lResult & 0x40000000) {
                return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
            } else {
                return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
            }
        } else {
            return (lResult ^ lX8 ^ lY8);
        }
    }
    function F(x,y,z) { return (x & y) | ((~x) & z); }
    function G(x,y,z) { return (x & z) | (y & (~z)); }
    function H(x,y,z) { return (x ^ y ^ z); }
    function I(x,y,z) { return (y ^ (x | (~z))); }
    function FF(a,b,c,d,x,s,ac) {
        a = AddUnsigned(a, AddUnsigned(AddUnsigned(F(b, c, d), x), ac));
        return AddUnsigned(RotateLeft(a, s), b);
    };
    function GG(a,b,c,d,x,s,ac) {
        a = AddUnsigned(a, AddUnsigned(AddUnsigned(G(b, c, d), x), ac));
        return AddUnsigned(RotateLeft(a, s), b);
    };
    function HH(a,b,c,d,x,s,ac) {
        a = AddUnsigned(a, AddUnsigned(AddUnsigned(H(b, c, d), x), ac));
        return AddUnsigned(RotateLeft(a, s), b);
    };
    function II(a,b,c,d,x,s,ac) {
        a = AddUnsigned(a, AddUnsigned(AddUnsigned(I(b, c, d), x), ac));
        return AddUnsigned(RotateLeft(a, s), b);
    };
    function ConvertToWordArray(string) {
        var lWordCount;
        var lMessageLength = string.length;
        var lNumberOfWords_temp1=lMessageLength + 8;
        var lNumberOfWords_temp2=(lNumberOfWords_temp1-(lNumberOfWords_temp1 % 64))/64;
        var lNumberOfWords = (lNumberOfWords_temp2+1)*16;
        var lWordArray=Array(lNumberOfWords-1);
        var lBytePosition = 0;
        var lByteCount = 0;
        while ( lByteCount < lMessageLength ) {
            lWordCount = (lByteCount-(lByteCount % 4))/4;
            lBytePosition = (lByteCount % 4)*8;
            lWordArray[lWordCount] = (lWordArray[lWordCount] | (string.charCodeAt(lByteCount)<<lBytePosition));
            lByteCount++;
        }
        lWordCount = (lByteCount-(lByteCount % 4))/4;
        lBytePosition = (lByteCount % 4)*8;
        lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80<<lBytePosition);
        lWordArray[lNumberOfWords-2] = lMessageLength<<3;
        lWordArray[lNumberOfWords-1] = lMessageLength>>>29;
        return lWordArray;
    };
    function WordToHex(lValue) {
        var WordToHexValue="",WordToHexValue_temp="",lByte,lCount;
        for (lCount = 0;lCount<=3;lCount++) {
            lByte = (lValue>>>(lCount*8)) & 255;
            WordToHexValue_temp = "0" + lByte.toString(16);
            WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length-2,2);
        }
        return WordToHexValue;
    };
    function Utf8Encode(string) {
        string = string.replace(/\r\n/g,"\n");
        var utftext = "";
        for (var n = 0; n < string.length; n++) {
            var c = string.charCodeAt(n);
            if (c < 128) {
                utftext += String.fromCharCode(c);
            }
            else if((c > 127) && (c < 2048)) {
                utftext += String.fromCharCode((c >> 6) | 192);
                utftext += String.fromCharCode((c & 63) | 128);
            }
            else {
                utftext += String.fromCharCode((c >> 12) | 224);
                utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                utftext += String.fromCharCode((c & 63) | 128);
            }
        }
        return utftext;
    };
    var x=Array();
    var k,AA,BB,CC,DD,a,b,c,d;
    var S11=7, S12=12, S13=17, S14=22;
    var S21=5, S22=9 , S23=14, S24=20;
    var S31=4, S32=11, S33=16, S34=23;
    var S41=6, S42=10, S43=15, S44=21;
    string = Utf8Encode(string);
    x = ConvertToWordArray(string);
    a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;
    for (k=0;k<x.length;k+=16) {
        AA=a; BB=b; CC=c; DD=d;
        a=FF(a,b,c,d,x[k+0], S11,0xD76AA478);
        d=FF(d,a,b,c,x[k+1], S12,0xE8C7B756);
        c=FF(c,d,a,b,x[k+2], S13,0x242070DB);
        b=FF(b,c,d,a,x[k+3], S14,0xC1BDCEEE);
        a=FF(a,b,c,d,x[k+4], S11,0xF57C0FAF);
        d=FF(d,a,b,c,x[k+5], S12,0x4787C62A);
        c=FF(c,d,a,b,x[k+6], S13,0xA8304613);
        b=FF(b,c,d,a,x[k+7], S14,0xFD469501);
        a=FF(a,b,c,d,x[k+8], S11,0x698098D8);
        d=FF(d,a,b,c,x[k+9], S12,0x8B44F7AF);
        c=FF(c,d,a,b,x[k+10],S13,0xFFFF5BB1);
        b=FF(b,c,d,a,x[k+11],S14,0x895CD7BE);
        a=FF(a,b,c,d,x[k+12],S11,0x6B901122);
        d=FF(d,a,b,c,x[k+13],S12,0xFD987193);
        c=FF(c,d,a,b,x[k+14],S13,0xA679438E);
        b=FF(b,c,d,a,x[k+15],S14,0x49B40821);
        a=GG(a,b,c,d,x[k+1], S21,0xF61E2562);
        d=GG(d,a,b,c,x[k+6], S22,0xC040B340);
        c=GG(c,d,a,b,x[k+11],S23,0x265E5A51);
        b=GG(b,c,d,a,x[k+0], S24,0xE9B6C7AA);
        a=GG(a,b,c,d,x[k+5], S21,0xD62F105D);
        d=GG(d,a,b,c,x[k+10],S22,0x2441453);
        c=GG(c,d,a,b,x[k+15],S23,0xD8A1E681);
        b=GG(b,c,d,a,x[k+4], S24,0xE7D3FBC8);
        a=GG(a,b,c,d,x[k+9], S21,0x21E1CDE6);
        d=GG(d,a,b,c,x[k+14],S22,0xC33707D6);
        c=GG(c,d,a,b,x[k+3], S23,0xF4D50D87);
        b=GG(b,c,d,a,x[k+8], S24,0x455A14ED);
        a=GG(a,b,c,d,x[k+13],S21,0xA9E3E905);
        d=GG(d,a,b,c,x[k+2], S22,0xFCEFA3F8);
        c=GG(c,d,a,b,x[k+7], S23,0x676F02D9);
        b=GG(b,c,d,a,x[k+12],S24,0x8D2A4C8A);
        a=HH(a,b,c,d,x[k+5], S31,0xFFFA3942);
        d=HH(d,a,b,c,x[k+8], S32,0x8771F681);
        c=HH(c,d,a,b,x[k+11],S33,0x6D9D6122);
        b=HH(b,c,d,a,x[k+14],S34,0xFDE5380C);
        a=HH(a,b,c,d,x[k+1], S31,0xA4BEEA44);
        d=HH(d,a,b,c,x[k+4], S32,0x4BDECFA9);
        c=HH(c,d,a,b,x[k+7], S33,0xF6BB4B60);
        b=HH(b,c,d,a,x[k+10],S34,0xBEBFBC70);
        a=HH(a,b,c,d,x[k+13],S31,0x289B7EC6);
        d=HH(d,a,b,c,x[k+0], S32,0xEAA127FA);
        c=HH(c,d,a,b,x[k+3], S33,0xD4EF3085);
        b=HH(b,c,d,a,x[k+6], S34,0x4881D05);
        a=HH(a,b,c,d,x[k+9], S31,0xD9D4D039);
        d=HH(d,a,b,c,x[k+12],S32,0xE6DB99E5);
        c=HH(c,d,a,b,x[k+15],S33,0x1FA27CF8);
        b=HH(b,c,d,a,x[k+2], S34,0xC4AC5665);
        a=II(a,b,c,d,x[k+0], S41,0xF4292244);
        d=II(d,a,b,c,x[k+7], S42,0x432AFF97);
        c=II(c,d,a,b,x[k+14],S43,0xAB9423A7);
        b=II(b,c,d,a,x[k+5], S44,0xFC93A039);
        a=II(a,b,c,d,x[k+12],S41,0x655B59C3);
        d=II(d,a,b,c,x[k+3], S42,0x8F0CCC92);
        c=II(c,d,a,b,x[k+10],S43,0xFFEFF47D);
        b=II(b,c,d,a,x[k+1], S44,0x85845DD1);
        a=II(a,b,c,d,x[k+8], S41,0x6FA87E4F);
        d=II(d,a,b,c,x[k+15],S42,0xFE2CE6E0);
        c=II(c,d,a,b,x[k+6], S43,0xA3014314);
        b=II(b,c,d,a,x[k+13],S44,0x4E0811A1);
        a=II(a,b,c,d,x[k+4], S41,0xF7537E82);
        d=II(d,a,b,c,x[k+11],S42,0xBD3AF235);
        c=II(c,d,a,b,x[k+2], S43,0x2AD7D2BB);
        b=II(b,c,d,a,x[k+9], S44,0xEB86D391);
        a=AddUnsigned(a,AA);
        b=AddUnsigned(b,BB);
        c=AddUnsigned(c,CC);
        d=AddUnsigned(d,DD);
    }
    var temp = WordToHex(a)+WordToHex(b)+WordToHex(c)+WordToHex(d);
    return temp.toLowerCase();
}