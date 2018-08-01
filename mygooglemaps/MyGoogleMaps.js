/******************************************************************************
 *
 * 全体的にロジックが重複気味なのを何とかせねば。
 *
 *****************************************************************************/

var mygmap_map;
var mygmap_list;
var mygmap_keys;

/*****************************************************************************/

GMarker.prototype.point = undefined;
GMarker.prototype.loc_id = undefined;

/*****************************************************************************/

/* 地図の初期化 */
function init(lng, lat, zoom, map_moveend) {
  if (!lng && !lat) {
    lng = 138;
    lat = 38;
  }
  if (zoom != 0 && !zoom) {
    zoom = 12;
  }
  mygmap_map = new GMap(document.getElementById('map'));
  var point = new GPoint(lng, lat);
  mygmap_map.centerAndZoom(point, zoom);
  mygmap_map.addControl(new GLargeMapControl());
  GEvent.addListener(mygmap_map, "moveend", map_moveend);

  return;
}

/*  */
function request_xml(url, parse_xml) {
  var request = GXmlHttp.create();
  request.open("GET", url, true);
  request.onreadystatechange = request_state_change(request, parse_xml);
  request.send(null);
  return;
}

/*  */
function request_state_change(request, parse_xml) {
  return function() {
    if (request.readyState == 4) {
      parse_xml(request.responseXML.documentElement);
    }
  };
}

/*  */
function create_marker(loc_id, lng, lat, marker_click) {
  var point = new GPoint(lng, lat);
  var marker = new GMarker(point);
  marker.point = point;
  marker.loc_id = loc_id;
  GEvent.addListener(marker, "click", marker_click(marker));
  return marker;
}

/*****************************************************************************/

/*  */
function onLoadForView(loc_id, lng, lat, zoom, actionURL) {
  init(lng, lat, zoom, view_map_moveend);

  mygmap_map["init_loc_id"] = loc_id; // 定数
  mygmap_map["action_url"] = actionURL; // 定数
  mygmap_list = new Object();
  mygmap_keys = new Array();

  var bounds = mygmap_map.getBoundsLatLng();
  // 画面内のマーカを取得、表示
  request_xml("action.php?action=plugin&name=MyGoogleMaps&type=bounds"
              + "&max_x=" + bounds.maxX
              + "&min_x=" + bounds.minX
              + "&max_y=" + bounds.maxY
              + "&min_y=" + bounds.minY,
              view_parse_xml);
  return;
}

/*  */
function view_parse_xml(element) {
  var markers = element.getElementsByTagName("marker");

  for (var i = 0; i < markers.length; i++) {
    var marker = markers[i];
    if (marker.getAttribute("deleted") == "true") {
      continue;
    }
    var loc_id = marker.getAttribute("id");
    var marker = create_marker(loc_id,
                               parseFloat(marker.getAttribute("lng")),
                               parseFloat(marker.getAttribute("lat")),
                               view_marker_click);
    mygmap_map.addOverlay(marker);
    mygmap_list[loc_id] = marker;
    mygmap_keys.push(loc_id);
  }

  if (mygmap_list[mygmap_map["init_loc_id"]]) {
    view_marker_click(mygmap_list[mygmap_map["init_loc_id"]])(); // 定数
  }
  return;
}

/*  */
function view_marker_click(marker) {
  return function() {
    request_xml("action.php?action=plugin&name=MyGoogleMaps&type=detail"
                + "&loc_id="
                + marker.loc_id,
                view_detail_parse_xml);
  };
}

/*  */
function view_detail_parse_xml(element) {
  mygmap_list[element.getAttribute("id")].openInfoWindowHtml(make_info_from_detail(element));
}

/*  */
function make_info_from_detail(element) {
  var str = '<div style="white-space: nowrap; font-size: 13px; color: #393939;">';
  str += '<b>';
  if (element.getAttribute("uri")) {
    str += '<a href="javascript:void(0)" onclick="window.opener.location.href=\'' + element.getAttribute("uri") + '\'">' + element.getAttribute("title") + '</a>';
  } else {
    str += element.getAttribute("title");
  }
  str += '</b>';

  // トラックバックURL
  var tb_url = mygmap_map["action_url"]
    + "?action=plugin&name=MyGoogleMaps&type=ping&loc_id="
    + element.getAttribute("id");
  str += ' [<a href="javascript:void(0)" onclick="window.prompt(\'MyGoogleMaps TrackBack URL: \', \''
         + tb_url
         + '\')">TB</a>]';

  // 説明
  if (element.getAttribute("description")) {
    str += '<br />' + element.getAttribute("description");
  }

  // 画像
  if (element.getAttribute("img")) {
    str += '<br /><img src="' + element.getAttribute("img") + '"';
    if (element.getAttribute("width") > 0) {
      str += ' width="' + element.getAttribute("width") + '"';
    }
    if (element.getAttribute("height") > 0) {
      str += ' height="' + element.getAttribute("height") + '"';
    }
    str += ' />';
  }

  // 記事
  var items = element.getElementsByTagName("item");
  if (items.length > 0) {
    str += '<br /><ul style="margin: 1px; padding-left: 1em;">';
    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      str += '<li>';
      str += '<a href="javascript:void(0)" onclick="window.opener.location.href=\'' + item.getAttribute("uri") + '\'">' + item.getAttribute("title") + '</a>';
      str += '</li>';
    }
    str += '</ul>';
  }

  // トラックバック表示
  var tbs = element.getElementsByTagName("trackback");
  if (tbs.length > 0) {
    str += '<ul style="margin: 1px; padding-left: 2em;">';
    for (var i = 0; i < tbs.length; i++) {
      var tb = tbs[i];
      str += '<li>';
      str += '<a href="" onclick="window.opener.location.href=\'' + tb.getAttribute("url") + '\'">' + tb.getAttribute("title") + '</a>';
      str += ' (' + tb.getAttribute("blog_name") + ')';
      str += '</li>';
    }
    str += '</ul>';
  }

  str += '</div>';
  return str;
}

/*  */
function view_map_moveend() {
  var bounds = mygmap_map.getBoundsLatLng();

  // 表示の範囲外のマーカを削除
  for (var i = 0; i < mygmap_keys.length; i++) {
    var marker = mygmap_list[mygmap_keys[i]];
    var lng = marker.point.x;
    var lat = marker.point.y;
    if (lng < bounds.minX
        || lng > bounds.maxX
        || lat < bounds.minY
        || lat > bounds.maxY) {
      mygmap_map.removeOverlay(marker);
      mygmap_list[mygmap_keys[i]] = undefined;
    }
  }

  mygmap_keys = new Array();
  request_xml("action.php?action=plugin&name=MyGoogleMaps&type=bounds"
              + "&max_x=" + bounds.maxX
              + "&min_x=" + bounds.minX
              + "&max_y=" + bounds.maxY
              + "&min_y=" + bounds.minY,
              view_map_moveend_parse_xml);
}

/*  */
function view_map_moveend_parse_xml(element) {
  var markers = element.getElementsByTagName("marker");

  for (var i = 0; i < markers.length; i++) {
    var marker = markers[i];
    if (marker.getAttribute("deleted") == "true") {
      continue;
    }
    var loc_id = marker.getAttribute("id");
    mygmap_keys.push(loc_id);
    if (typeof(mygmap_list[loc_id]) != "undefined") {
      continue;
    }
    var marker = create_marker(loc_id,
                               parseFloat(marker.getAttribute("lng")),
                               parseFloat(marker.getAttribute("lat")),
                               view_marker_click);
    mygmap_map.addOverlay(marker);
    mygmap_list[loc_id] = marker;
  }
  return;
}

/*****************************************************************************/

function onLoadForEdit(loc_id, lng, lat, zoom, form) {
  init(lng, lat, zoom, edit_map_moveend); // 定数

  mygmap_map["init_loc_id"] = loc_id;
  mygmap_map["gmap_form"] = form;

  mygmap_list = new Object();
  mygmap_keys = new Array();

  var bounds = mygmap_map.getBoundsLatLng();
  // 画面内のマーカを取得、表示
  request_xml("action.php?action=plugin&name=MyGoogleMaps&type=bounds"
              + "&max_x=" + bounds.maxX
              + "&min_x=" + bounds.minX
              + "&max_y=" + bounds.maxY
              + "&min_y=" + bounds.minY,
              edit_parse_xml);

  return;
}

/*  */
function edit_parse_xml(element) {
  var markers = element.getElementsByTagName("marker");

  for (var i = 0; i < markers.length; i++) {
    var marker = markers[i];
    var loc_id = marker.getAttribute("id");
    var marker = create_marker(loc_id,
                               parseFloat(marker.getAttribute("lng")),
                               parseFloat(marker.getAttribute("lat")),
                               edit_marker_click);
    mygmap_map.addOverlay(marker);
    mygmap_list[loc_id] = marker;
    mygmap_keys.push(loc_id);
  }

  if (mygmap_list[mygmap_map["init_loc_id"]]) {
    edit_marker_click(mygmap_list[mygmap_map["init_loc_id"]])();
  }
  return;
}

/*  */
function edit_marker_click(marker) {
  return function() {
    request_xml("action.php?action=plugin&name=MyGoogleMaps&type=detail"
                + "&loc_id="
                + marker.loc_id,
                edit_detail_parse_xml);
  };
}

/*  */
function edit_detail_parse_xml(element) {
  var loc_id = element.getAttribute("id");
  var form = mygmap_map["gmap_form"];

  form.id.value = loc_id;
  form.lat.value = mygmap_list[loc_id].point.y;
  form.lng.value = mygmap_list[loc_id].point.x;
  form.title.value = element.getAttribute("title");
  form.description.value = element.getAttribute("description");
  form.uri.value = element.getAttribute("uri");
  form.img.value = element.getAttribute("img");
  form.img_width.value = element.getAttribute("width");
  form.img_height.value = element.getAttribute("height");
  if (element.getAttribute("deleted") == "true") {
    form.deleteFlag.checked = true;
  } else {
    form.deleteFlag.checked = false;
  }

  // リンク記事の表示?
}

/*  */
function edit_map_moveend() {
  var center = mygmap_map.getCenterLatLng();
  var form = mygmap_map["gmap_form"];

  form.lat.value = center.y;
  form.lng.value = center.x;

  // 表示の範囲外のマーカを削除
  var bounds = mygmap_map.getBoundsLatLng();
  for (var i = 0; i < mygmap_keys.length; i++) {
    var marker = mygmap_list[mygmap_keys[i]];
    var lng = marker.point.x;
    var lat = marker.point.y;
    if (lng < bounds.minX
        || lng > bounds.maxX
        || lat < bounds.minY
        || lat > bounds.maxY) {
      mygmap_map.removeOverlay(marker);
      mygmap_list[mygmap_keys[i]] = undefined;
    }
  }

  mygmap_keys = new Array();
  request_xml("action.php?action=plugin&name=MyGoogleMaps&type=bounds"
              + "&max_x=" + bounds.maxX
              + "&min_x=" + bounds.minX
              + "&max_y=" + bounds.maxY
              + "&min_y=" + bounds.minY,
              edit_map_moveend_parse_xml);
  return;
}

/*  */
function edit_map_moveend_parse_xml(element) {
  var markers = element.getElementsByTagName("marker");

  for (var i = 0; i < markers.length; i++) {
    var marker = markers[i];
    var loc_id = marker.getAttribute("id");
    mygmap_keys.push(loc_id);
    if (typeof(mygmap_list[loc_id]) != "undefined") {
      continue;
    }
    var marker = create_marker(loc_id,
                               parseFloat(marker.getAttribute("lng")),
                               parseFloat(marker.getAttribute("lat")),
                               edit_marker_click);
    mygmap_map.addOverlay(marker);
    mygmap_list[loc_id] = marker;
  }
  return;
}

/*****************************************************************************/

// 同様のコードがindex.phpにもあり
function onSubmit(form) {
  var id = form.id.value;
  if (id && !id.match(/^[1-9][0-9]*$/)) {
    window.alert('IDの入力値を確認して下さい: ' + id);
    return false;
  }

  var lat = form.lat.value;
  if (!lat.match(/^-?[0-9]+(.[0-9]*)?$/)
      || parseFloat(lat) < -90.0
      || parseFloat(lat) > 90.0) {
    window.alert('緯度の入力値を確認して下さい: ' + lat);
    return false;
  }
  var lng = form.lng.value;
  if (!lng.match(/^-?[0-9]+(.[0-9]*)?$/)
      || parseFloat(lng) < -180.0
      || parseFloat(lng) > 180.0) {
    window.alert('経度の入力値を確認して下さい: ' + lng);
    return false;
  }
  if (!form.title.value) {
    window.alert('名前が入力されていません.');
    return false;
  }

  var msg = '';
  if (form.id.value) {
    msg += '更新します.\n';
  } else {
    msg += '新規登録します.\n';
  }
  msg += 'よろしいですか?'
  return window.confirm(msg);
}

/*****************************************************************************/

/*  */
function window_hoge(loc_id, zoom) {

  mygmap_map.zoomTo(zoom);

  if (typeof(mygmap_list[loc_id]) == "undefined") {
    request_xml("action.php?action=plugin&name=MyGoogleMaps&type=detail"
                + "&loc_id="
                + loc_id,
                window_hoge_detail_parse_xml);
  } else {
    view_marker_click(mygmap_list[loc_id])();
  }

  return;
}
window.mygmap_hoge = window_hoge;

/*  */
function window_hoge_detail_parse_xml(element) {
  if (!element.attributes.length) {
    return;
  }

  var loc_id = element.getAttribute("id");
  var point = new GPoint(parseFloat(element.getAttribute("lng")),
                         parseFloat(element.getAttribute("lat")));

  var marker = create_marker(loc_id, point.x, point.y, view_marker_click);
  mygmap_map.addOverlay(marker);
  mygmap_list[loc_id] = marker;
  mygmap_keys.push(loc_id);
  marker.openInfoWindowHtml(make_info_from_detail(element));

  return;
}
