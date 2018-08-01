<?php
class NP_MyGoogleMaps extends NucleusPlugin {
  /*
   * NP_MyGoogleMaps.php
   *
   * KAMAGASAKO Masatoshi <kamagasako@yoshidakamagasako.com>
   * http://blog.yoshidakamagasako.com/kamagaskao/
   *
   * ライセンスはNucleus CMSに準拠します。
   *
   * Todo: requestVar()を呼ぶ位置の整理
   */

  /* 名称を取得 */
  function getName() {
    return 'MyGoogleMaps';
  }

  /* 著作者を取得 */
  function getAuthor() {
    return 'kamagasako';
  }

  /* URLを取得 */
  function getURL() {
    return 'http://blog.yoshidakamagasako.com/kamagasako/';
  }

  /* 説明文を取得 */
  function getDescription() {
    return 'Google Maps APIを利用して、記事と地図の関連を作成します。';
  }

  /* バージョンを取得 */
  function getVersion() {
    return '0.8.2';
  }

  /* 対応Nucluesの最小バージョン */
  function getMinNucleusVersion() {
    return '320';
  }

  /* サポートしている機能のチェック */
  function supportsFeature($what) {
    switch ($what) {
    case 'SqlTablePrefix':
      return 1;

    case 'HelpPage':
      return 1;

    default:
      break;
    }
    return 0;
  }

  /* 管理エリアの有無 */
  function hasAdminArea() {
    return 1;
  }

  /* 初期化 */
  function init() {
    return;
  }

  /* インストール */
  function install() {
    // テーブル作成
    // 位置情報マスタ
    sql_query('create table if not exists '
	      . sql_table('gmap_loc')
	      . '(loc_id int not null auto_increment,'
	      . ' lat varchar(20) not null,'
	      . ' lng varchar(20) not null,'
	      . ' deleteFlag bool not null,'
	      . ' primary key(loc_id)'
	      . ') type=InnoDB;');
    // 位置情報属性
    sql_query('create table if not exists '
	      . sql_table('gmap_loc_attr')
	      . '(loc_id int not null'
	      . ', title varchar(80) not null'
	      . ', description tinytext'
	      . ', uri varchar(100)'
	      . ', img varchar(100)'
	      . ', img_width smallint'
	      . ', img_height smallint'
	      . ', primary key(loc_id)'
	      . ', foreign key(loc_id) references '
	      . sql_table('gmap_loc') . '(loc_id)'
	      . ') type=InnoDB;');
    // 位置情報-記事
    sql_query('create table if not exists '
	      . sql_table('gmap_loc_item')
	      . '(loc_id int not null'
	      . ', item_id int not null'
	      . ', primary key(loc_id, item_id)'
	      . ', foreign key(loc_id) references ' 
	      . sql_table('gmap_loc') . '(loc_id)'
	      . ') type=InnoDB;');
    // 位置情報-トラックバック
    sql_query('create table if not exists '
	      . sql_table('gmap_loc_tb')
	      . '(loc_id int not null'
	      . ', title varchar(100)'
	      . ', excerpt varchar(255)'
	      . ', url varchar(100) not null'
	      . ', blog_name varchar(100)'
	      . ', primary key(loc_id, url)'
	      . ', foreign key(loc_id) references '
	      . sql_table('gmap_loc') . '(loc_id)'
	      . ') type=InnoDB;');

    // オプション
    $this->createOption("drop_uninstall",
			"Drop table on uninstall?",
			"yesno",
			"no");
    $this->createOption("gmap_api_key",
			"Google Map API Key",
			"text",
			"");
    $this->createOption("gmap_width",
			"Width of Map [pixel]",
			"text",
			"640");
    $this->createOption("gmap_height",
			"Height of Map [pixel]",
			"text",
			"480");
    $this->createOption("gmap_zoomlevel",
			"Google Map zoom level (0-17)",
			"text",
			"5");
    return;
  }

  /* アンインストール */
  function unInstall() {
    if ($this->getOption("drop_uninstall") == "yes") {
      sql_query("drop table if exists " . sql_table('gmap_loc_tb') . ";");
      sql_query("drop table if exists " . sql_table('gmap_loc_item') . ";");
      sql_query("drop table if exists " . sql_table('gmap_loc_attr') . ";");
      sql_query("drop table if exists " . sql_table('gmap_loc') . ";");
    }
    return;
  }

  /* スキン変数 */
  function doSkinVar($skinType) {
    global $CONF;
    $p = func_get_args();
    $param1 = $p[1];
    $param2 = $p[2];
    $param3 = $p[3];
    $loc_id = $param3 ? $param3 : 1;
    $zoom = $this->getOption('gmap_zoomlevel');

    switch ($param1) {
    case '':
    case 'view':
      // 閲覧ポップアップ
      $url = $CONF['ActionURL']
	. '?action=plugin&name=MyGoogleMaps&type=view&loc_id='
	. $loc_id
	. '&zoom='
	. $zoom;
      echo $this->make_handle_child_link($loc_id,
					 $url,
					 $this->getOption('gmap_width') + 20,
					 $this->getOption('gmap_height') + 20,
					 $param2,
					 $zoom);
      break;

    case 'edit':
      // 編集ポップアップ
      $url = $CONF['ActionURL']
	. '?action=plugin&name=MyGoogleMaps&type=edit&loc_id='
	. $loc_id
	. '&zoom='
	. $zoom;
      echo $this->make_handle_child_link($loc_id,
					 $url,
					 $this->getOption('gmap_width') + 20,
					 $this->getOption('gmap_height') + 240,
					 $param2,
					 $zoom);
      break;

    case 'script':
      // 記事画面用スクリプト
      echo <<<EOD
<script type="text/javascript">
//<![CDATA[
var mygmap_child;
function handle_child(loc_id, url, zoom, param) {
  if (mygmap_child && !mygmap_child.closed) {
    mygmap_child.mygmap_hoge(loc_id, zoom);
  } else {
    mygmap_child = window.open(url, 'child', param);
  }
  mygmap_child.focus();
  return;
}
//]]>
</script>
EOD;
      break;

    case 'list':
      // ポイント一覧
      echo $this->make_marker_list($param2, $param3);
      break;
    }
    return;
  }

  /* ポイント一覧作成 */
  function make_marker_list($type, $count) {
    global $CONF;
    $str = '';
    $zoom = $this->getOption('gmap_zoomlevel');

    switch ($type) {
    case 'old':
    case 'new':
      $sql = 'select loc.loc_id, attr.title from '
	. sql_table('gmap_loc')
	. ' loc, '
	. sql_table('gmap_loc_attr')
	. ' attr'
	. ' where loc.loc_id = attr.loc_id'
	. ' and loc.deleteFlag = 0'
	. ' order by loc_id';
      if ($type == 'new') {
	$sql = $sql . ' desc';
      }
      break;

    case 'link':
    default:
      $sql = 'select item.item_id, loc.loc_id, attr.title'
	. ' from '
	. sql_table('gmap_loc_item') // 不要
	. ' item, '                  // 不要
	. sql_table('gmap_loc')
	. ' loc, '
	. sql_table('gmap_loc_attr')
	. ' attr'
	. ' where item.loc_id = loc.loc_id'
	. ' and loc.loc_id = attr.loc_id'
	. ' and loc.deleteFlag = 0'
	. ' order by item.item_id desc'
	. ', item.loc_id desc';
      break;
    }
    if ($count) {
      $sql = $sql . ' limit ' . $count;
    }
    $result = mysql_query($sql);
    while ($row = mysql_fetch_assoc($result)) {
      $loc_id = $row['loc_id'];
      $title = $row['title'];
      $url = $CONF['ActionURL']
	. '?action=plugin&name=MyGoogleMaps&type=view&loc_id='
	. $loc_id
	. '&zoom='
	. $zoom;
      $str = $str . '<dd>'
	. $this->make_handle_child_link($loc_id,
					$url,
					$this->getOption('gmap_width') + 20,
					$this->getOption('gmap_height') + 20,
					$title,
					$zoom)
	. '</dd>';
    }
    return $str;
  }

  /* ポップアップ表示用リンク作成 */
  function make_handle_child_link($loc_id, $url,
				  $width, $height,
				  $link_str,
				  $zoom) {
    return '<a href="javascript:void(0)" onclick="handle_child('
      . $loc_id
      . ', \''
      . $url
      . '\', '
      . $zoom
      . ', \'width='
      . $width
      . ',height='
      . $height
      . ',resizable=yes\')" class="mygooglemaps">'
      . $link_str
      . '</a>';
  }

  /* イベント名のリストを取得 */
  function getEventList() {
    return array('PostAddItem',
                 'PreUpdateItem',
                 'PreDeleteItem',
		 'PreItem',
		 'QuickMenu');
  }

  /* 管理メニューの追加 */
  function event_QuickMenu(&$data) {
    array_push($data['options'],
	       array('title' => 'MyGoogleMaps',
		     'url' => $this->getAdminURL(),
		     'tooltip' => 'MyGoogleMapsの管理'));
  }

  /* テーブル名のリストを取得 */
  function getTableList() {
    return array(sql_table('gmap_loc'),
		 sql_table('gmap_loc_attr'),
		 sql_table('gmap_loc_item'),
		 sql_table('gmap_loc_tb'));
  }

  /* 記事追加後の処理 */
  function event_PostAddItem($data) {
    global $manager;
    $item_id = $data['itemid'];
    $item =& $manager->getItem($item_id, 0, 0);
    if (!$item) {
      return;
    }

    $this->insertLocItem($item_id, $item['body']);
    $this->insertLocItem($item_id, $item['more']);

    return;
  }

  /* 記事更新前の処理 (PostUpdateItemがない!) */
  function event_PreUpdateItem($data) {
    $this->deleteLocItem($data['itemid']);

    $this->insertLocItem($data['itemid'], $data['body']);
    $this->insertLocItem($data['itemid'], $data['more']);

    return;
  }

  /* 記事削除前の処理 */
  function event_PreDeleteItem($data) {
    $this->deleteLocItem($data['itemid']);
    return;
  }

  /* 位置と記事の関連を登録 */
  function insertLocItem($item_id, $str) {
    $matches = array();
    preg_match_all("/<\%MyGoogleMaps\((.*?)\)%\>/",
                   $str,
                   $matches,
                   PREG_SET_ORDER);

    for ($i = 0; $i < count($matches); $i++) {
      list ($loc_id, $link_str) = explode(',', $matches[$i][1]);
      if (!preg_match("/^[1-9][0-9]*$/", $loc_id) || !$link_str) {
	doError('invalid value was set in MyGoogleMaps tag.');
	return;
      }
      $sql = 'insert into '
	. sql_table('gmap_loc_item')
	. '(loc_id, item_id) values('
	. $loc_id

	. ', '
	. $item_id
	. ');';
      mysql_query($sql);
    }
    return;
  }

  /* 位置と記事の関連を削除 */
  function deleteLocItem($item_id) {
    $sql = 'delete from '
      . sql_table('gmap_loc_item')
      . ' where item_id = '
      . $item_id . ';';
    mysql_query($sql);
    return;
  }

  /* 表示(パース)前の処理 */
  function event_PreItem(&$data) {
    $data["item"]->body =
      preg_replace_callback("/<\%MyGoogleMaps\((.*?)\)%\>/",
                            array(&$this, 'make_link'),
                            $data["item"]->body);
    $data["item"]->more =
      preg_replace_callback("/<\%MyGoogleMaps\((.*?)\)%\>/",
                            array(&$this, 'make_link'),
                            $data["item"]->more);
    return;
  }

  /* リンク作成 */
  function make_link($matches) {
    global $CONF;
    $p = explode(',', $matches[1]);
    $loc_id = $p[0];
    $link_str = isset($p[1]) ? $p[1] : '';
    $zoom     = isset($p[2]) ? $p[2] : '';
    
    if ($zoom == '') {
      $zoom = $this->getOption('gmap_zoomlevel');
    }
    $url = $CONF['ActionURL']
      . '?action=plugin&name=MyGoogleMaps&type=view&loc_id='
      . $loc_id
      . '&zoom='
      . $zoom;

    return $this->make_handle_child_link($loc_id,
					 $url,
					 $this->getOption('gmap_width') + 20,
					 $this->getOption('gmap_height') + 20,
					 $link_str,
					 $zoom);
  }

  /* アクションディスパッチ */
  function doAction($type) {
    global $CONF, $member;

    switch ($type) {
    case '':
    case 'view': // 閲覧ウィンドウ表示
      $this->select(requestVar('loc_id'), requestVar('zoom'));
      break;

    case 'bounds': // 範囲内のマーカ取得
      $this->select_bounds(requestVar('max_x'),
			   requestVar('min_x'),
                           requestVar('max_y'),
                           requestVar('min_y'));
      break;

    case 'detail': // 詳細情報取得
      $this->select_detail(requestVar('loc_id'));
      break;

    case 'edit': // 編集ウィンドウ表示
    case 'edit_admin': // 編集ウィンドウ表示(管理画面)
      if (!$member->isLoggedIn()) {
        doError('You\'re not logged in.');
	return;
      }
      $loc_id = requestVar("loc_id");
      if (!$loc_id) {
	$loc_id = 1;
      }
      $this->edit($loc_id, $type);
      break;

    case 'store': // 登録更新処理
    case 'store_admin': // 登録更新処理(管理画面)
      if (!$member->isLoggedIn()) {
        doError('You\'re not logged in.');
        return;
      }
      if (!$this->validate()) {
	doError('Invalid value was passed.');
	return;
      }
      $loc_id = $this->store();

      if ($type == 'store_admin') { // 管理画面へ転送
	header('Refresh: 0; URL='
	       . $CONF['PluginURL']
	       . $this->getShortName()
	       . '/index.php?loc_id='
	       . $loc_id);
	return;
      }
      $this->edit($loc_id, 'edit');
      break;

    case 'upgrade': // テーブル更新等
      $this->upgrade(requestVar("code"));
      break;

    case 'ping': // トラックバックping登録
      $this->ping();
      break;

    default:
      break;
    }
    return;
  }

  /* トラックバックping登録 */
  function ping() {
    header("Content-Type: text/xml; charset=iso-8859-1");

    $loc_id = requestVar("loc_id");
    $title = requestVar("title") ? requestVar("title") : requestVar("url");
    $excerpt = requestVar("excerpt");
    $url = requestVar("url");
    $blog_name = requestVar("blog_name");

    // 文字コードは自動検出 (大丈夫?)
    $title = mb_convert_encoding($title, _CHARSET, "auto");
    $excerpt = mb_convert_encoding($excerpt, _CHARSET, "auto");
    $blog_name = mb_convert_encoding($blog_name, _CHARSET, "auto");

    $error = "";
    if (!$loc_id) {
      $error = "No location is passed";
    }
    if (!$url) {
      $error = !$error ? "No URL is passed" : $error . "," + "No URL is passed";
    }
    // $loc_idの存在チェックもしておく?

    if ($error) {
      print <<<EOD
<?xml version="1.0" encoding="iso-8859-1"?>
<response>
  <error>1</error>
  <message>$error</message>
</response>
EOD;
      return;
    }

    $sql = "delete from "
      . sql_table('gmap_loc_tb')
      . " where loc_id = "
      . $loc_id
      . " and url = '"
      . mysql_escape_string($url)
      . "';";
    mysql_query($sql);

    $sql = "insert into "
      . sql_table("gmap_loc_tb")
      . "(loc_id, title, excerpt, url, blog_name) values("
      . $loc_id
      . ", '"
      . mysql_escape_string($title)
      . "', '"
      . mysql_escape_string($excerpt)
      . "', '"
      . mysql_escape_string($url)
      . "', '"
      . mysql_escape_string($blog_name)
      . "');";
    mysql_query($sql);

    print <<<EOD
<?xml version="1.0" encoding="iso-8859-1"?>
<response>
  <error>0</error>
</response>
EOD;
    return;
  }

  /* プラグインのアップグレード後の処理 */
  function upgrade($code) {
    global $CONF;
    $sql;

    switch ($code) {
    case 1:
      $sql = 'ALTER TABLE '
	. sql_table('gmap_loc')
	. ' MODIFY deleteFlag BOOL DEFAULT 0 NOT NULL;';
      break;

    case 2:
      $sql = 'ALTER TABLE '
	. sql_table('gmap_loc_attr')
	. ' ADD img_width SMALLINT;';
      break;

    case 3:
      $sql = 'ALTER TABLE '
	. sql_table('gmap_loc_attr')
	. ' ADD img_height SMALLINT;';
      break;

    default:
      break;
    }
    if ($sql) {
      mysql_query($sql);
    }
    header('Refresh: 0; URL='
	   . $CONF['PluginURL'] . $this->getShortName() . '/index.php');
    return;
  }

  /* 編集ウィンドウ表示 */
  function edit($loc_id, $type) {
    global $CONF;
    $key = $this->getOption('gmap_api_key');
    $width = $this->getOption('gmap_width') . 'px';
    $height = $this->getOption('gmap_height') . 'px';
    $script = $CONF['PluginURL'] . $this->getShortName() . '/MyGoogleMaps.js';
    $zoom = $this->getOption('gmap_zoomlevel');
    if ($zoom != 0 && !$zoom) {
      $zoom = 5;
    }

    $lng = 0;
    $lat = 0;

    $sql = "select lng, lat from "
      . sql_table('gmap_loc')
      . " where loc_id = "
      . $loc_id
      . ";";
    $result = mysql_query($sql);
    if (mysql_affected_rows() > 0) {
      $row = mysql_fetch_assoc($result);
      $lng = $row['lng'];
      $lat = $row['lat'];
    }

    $onload = "onLoadForEdit($loc_id, $lng, $lat, $zoom, document.form0)";
    if ($type == 'edit_admin') {
      $onload = "onLoadForEdit($loc_id, $lng, $lat, $zoom, window.opener.document.form0)";
    }

    $response = <<<EOD
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <script src="http://maps.google.com/maps?file=api&v=1&key=$key" type="text/javascript"></script>
    <script src="$script" type="text/javascript" charset="utf-8"></script>
    <title>MyGoogleMaps Edit</title>
  </head>
  <body onload="$onload">
    <div id="map" style="width: $width; height: $height;"></div>
EOD;

    if ($type != 'edit_admin') {
      $action = $CONF['ActionURL'];
      $response = $response . <<<EOD
    <div style="white-space: nowrap; margin-top: 10px;">
      <form name="form0" action="$action" method="POST" onsubmit="return onSubmit(document.form0)">
        <input type="hidden" name="action" value="plugin" />
        <input type="hidden" name="name" value="MyGoogleMaps" />
        <input type="hidden" name="type" value="store" />
        ID: <input type="text" name="id" value="" size="10" maxlength="10" />
        緯度*: <input type="text" name="lat" value="" size="20" maxlength="20" />
        経度*: <input type="text" name="lng" value="" size="20" maxlength="20" /><br />
        名前*: <input type="text" name="title" value="" size="80" maxlength="80" /><br />
        説明: <input type="text" name="description" value="" size="80" maxlength="256"/><br />
        リンクURL: <input type="text" name="uri" value="" size="80" maxlength="100" /><br />
        画像URL: <input type="text" name="img" value="" size="80" maxlength="100" /><br />
        画像 幅: <input type="text" name="img_width" value="" size="8" maxlength="6" />
        高さ: <input type="text" name="img_height" value="" size="8" maxlength="6" /><br />
        <input type="checkbox" name="deleteFlag" value="true" />削除<br />
        <input type="submit" value="登録更新" />
        <input type="reset" value="リセット" />
      </form>
    </div>
EOD;
    }

    $response = $response . <<<EOD
    <div style="clear: both;"></div>
  </body>
</html>
EOD;

    header("Content-Type: text/html; charset=utf-8");
    if (!$this->useUnicode()) {
      echo mb_convert_encoding($response, "utf-8", _CHARSET);
    } else {
      echo $response;
    }
    return;
  }

  /* 必須項目チェック */
  function validate() {
    $lat = requestVar('lat');
    if (!preg_match("/^-?[0-9]+(.[0-9]*)?$/", $lat)) {
      return false;
    }
    if ($lat < -90 || $lat > 90) {
      return false;
    }

    $lng = requestVar('lng');
    if (!preg_match("/^-?[0-9]+(.[0-9]*)?$/", $lng)) {
      return false;
    }
    if ($lng < -180 || $lng > 180) {
      return false;
    }

    $title = requestVar('title');
    if (!$title) {
      return false;
    }

    return true;
  }

  /* 登録更新 */
  function store() {
    if (!requestVar('id')) {
      return $this->insert();
    }
    return $this->update();
  }

  /* 新規登録 */
  function insert() {
    $sql = 'insert into '
      . sql_table('gmap_loc')
      . '(lat, lng, deleteFlag)'
      . " values('" . mysql_escape_string(requestVar("lat"))
      . "', '" . mysql_escape_string(requestVar("lng"))
      . "', "
      . (requestVar("deleteFlag") == "true" ? "1" : "0")
      . ");";
    mysql_query($sql);
    $loc_id = mysql_insert_id();

    $title = requestVar('title');
    $description = requestVar('description');
    // 編集画面(utf-8)
    if (requestVar('type') == 'store' && !$this->useUnicode()) {
      $title = mb_convert_encoding($title, _CHARSET, "utf-8");
      $description = mb_convert_encoding($description, _CHARSET, "utf-8");
    }
    $img_url = requestVar("img");
    $img_width = requestVar("img_width") ? requestVar("img_width") : 0;
    $img_height = requestVar("img_height") ? requestVar("img_height") : 0;
    $this->getImageSize($img_url, $img_width, $img_height);

    $sql = 'insert into '
      . sql_table('gmap_loc_attr')
      . '(loc_id, title, description, uri, img, img_width, img_height)'
      . " values(" . $loc_id
      . ", '" . mysql_escape_string($title)
      . "', '" . mysql_escape_string($description)
      . "', '" . mysql_escape_string(requestVar("uri"))
      . "', '" . mysql_escape_string($img_url)
      . "', " . mysql_escape_string($img_width)
      . ", " . mysql_escape_string($img_height)
      . ");";
    mysql_query($sql);

    return $loc_id;
  }

  /* 更新 */
  function update() {
    $loc_id = requestVar("id");

    $sql = 'update '
      . sql_table('gmap_loc')
      . ' set '
      . "lat = '" . mysql_escape_string(requestVar("lat"))
      . "', lng = '" . mysql_escape_string(requestVar("lng"))
      . "', deleteFlag = "
      . (requestVar("deleteFlag") == "true" ? "1" : "0")
      . " where loc_id = " . mysql_escape_string($loc_id) . ";";
    mysql_query($sql);

    $title = requestVar('title');
    $description = requestVar('description');
    // 編集画面(utf-8)
    if (requestVar('type') == 'store' && !$this->useUnicode()) {
      $title = mb_convert_encoding($title, _CHARSET, "utf-8");
      $description = mb_convert_encoding($description, _CHARSET, "utf-8");
    }
    $img_url = requestVar("img");
    $img_width = requestVar("img_width") ? requestVar("img_width") : 0;
    $img_height = requestVar("img_height") ? requestVar("img_height") : 0;
    $this->getImageSize($img_url, $img_width, $img_height);

    $sql = 'update '
      . sql_table('gmap_loc_attr')
      . ' set '
      . "title = '" . mysql_escape_string($title)
      . "', description = '" . mysql_escape_string($description)
      . "', uri = '" . mysql_escape_string(requestVar("uri"))
      . "', img = '" . mysql_escape_string($img_url)
      . "', img_width = " . mysql_escape_string($img_width)
      . ", img_height = " . mysql_escape_string($img_height)
      . " where loc_id = " . mysql_escape_string($loc_id) . ";";
    mysql_query($sql);

    return $loc_id;
  }

  /* 画像サイズの取得 */
  function getImageSize($img_url, &$img_width, &$img_height) {
    if ($img_url and (!$img_width or !$img_height)) {
      list($width, $height, $type, $attr) = getimagesize($img_url);
      if (!$img_width) {
	$img_width = $width;
      }
      if (!$img_height) {
	$img_height = $height;
      }
    }
    return;
  }

  /*  */
  function select($loc_id, $zoom) {
    global $CONF;
    $key = $this->getOption('gmap_api_key');
    $width = $this->getOption('gmap_width') . 'px';
    $height = $this->getOption('gmap_height') . 'px';
    $script = $CONF['PluginURL'] . $this->getShortName() . '/MyGoogleMaps.js';
    $action = $CONF['ActionURL'];
    if ($zoom != 0 && !$zoom) {
      $zoom = $this->getOption('gmap_zoomlevel');
    }

    $lng = 0;
    $lat = 0;

    // loc_idの位置情報をDBから取得
    $sql = "select lng, lat from "
      . sql_table('gmap_loc')
      . " where loc_id = "
      . $loc_id
      . " and deleteFlag = 0;";
    $result = mysql_query($sql);
    if (mysql_affected_rows() > 0) {
      $row = mysql_fetch_assoc($result);
      $lng = $row['lng'];
      $lat = $row['lat'];
    }

    $response = <<<EOD
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <script src="http://maps.google.com/maps?file=api&v=1&key=$key" type="text/javascript"></script>
    <script src="$script" type="text/javascript" charset="utf-8"></script>
    <title>MyGoogleMaps View</title>
  </head>
  <body onload="onLoadForView('$loc_id', $lng, $lat, $zoom, '$action')">
    <div id="map" style="width: $width; height: $height; float: left;"></div>
    <div id="empty" style="clear: both;"></div>
  </body>
</html>
EOD;

    header("Content-Type: text/html; charset=utf-8");
    if ($this->useUnicode()) {
      echo mb_convert_encoding($response, "utf-8", _CHARSET);
    } else {
      echo $response;
    }
    return;
  }

  /* 範囲内の位置情報をすべて取得し、XMLで返す */
  function select_bounds($max_x, $min_x, $max_y, $min_y) {
    $sql = "select loc_id, lng, lat, deleteFlag from "
      . sql_table('gmap_loc')
      . " where lng between "
      . $min_x
      . " and "
      . $max_x
      . " and lat between "
      . $min_y
      . " and "
      . $max_y
      . ";";
    $result = mysql_query($sql);

    $affected = mysql_affected_rows();
    $str = "<markers>";
    for ($i = 0; $i < $affected; $i++) {
      $row = mysql_fetch_assoc($result);
      $str = $str
	. '  <marker id="'
	. $row['loc_id']
	. '" lat="'
	. $row['lat']
	. '" lng="'
	. $row['lng']
	. '" deleted="'
	. ($row['deleteFlag'] ? "true" : "false")
	. '" />';
    }
    $str = $str . "</markers>";

    header("Content-Type: text/xml; charset=utf-8");
    if ($this->useUnicode()) {
      echo $str;
    } else {
      echo mb_convert_encoding($str, "utf-8", _CHARSET);
    }
    return;
  }

  /* 位置IDに該当する位置情報を取得し、XMLで返す */
  function select_detail($loc_id) {
    global $manager;
    global $CONF;

    header("Content-Type: text/xml; charset=utf-8");

    $sql = "select loc.loc_id loc_id"
      . ", loc.lng"
      . ", loc.lat"
      . ", loc.deleteFlag"
      . ", attr.title"
      . ", attr.description"
      . ", attr.uri"
      . ", attr.img"
      . ", attr.img_width"
      . ", attr.img_height"
      . " from " . sql_table('gmap_loc') . " loc"
      . ", " . sql_table('gmap_loc_attr') . " attr "
      . "where loc.loc_id = attr.loc_id"
      . " and loc.loc_id = " . $loc_id .";";
    $result = mysql_query($sql);

    $affected = mysql_affected_rows();
    if ($affected != 1) {
      echo "<locate />";
      return;
    }
    $row = mysql_fetch_assoc($result);
    $str = '<locate id="'
      . $row['loc_id']
      . '" lat="'
      . $row['lat']
      . '" lng="'
      . $row['lng']
      . '" deleted="'
      . ($row['deleteFlag'] ? "true" : "false")
      . '" title="'
      . htmlspecialchars($row['title'])
      . '" description="'
      . htmlspecialchars($row['description'])
      . '" uri="'
      . $row['uri']
      . '" img="'
      . $row['img']
      . '" width="'
      . $row['img_width']
      . '" height="'
      . $row['img_height']
      .'">';

    $sql = "select li.item_id item_id"
      . ", item.iblog blog_id"
      . ", item.ititle title"
      . " from " . sql_table('gmap_loc') ." loc"
      . ", " . sql_table('gmap_loc_item') . " li"
      . ", " . sql_table('item') . " item"
      . " where loc.loc_id = li.loc_id"
      . " and li.item_id = item.inumber"
      . " and loc.loc_id = " . $loc_id . ";";
    $result = mysql_query($sql);

    $affected = mysql_affected_rows();
    for ($i = 0; $i < $affected; $i++) {
      $row = mysql_fetch_assoc($result);

      $blog =& $manager->getBlog($row['blog_id']);
      if (!$blog) {
	continue;
      }
      $CONF['ItemURL'] = $blog->getURL(); // こんなんでいいのか?
      if (preg_match("|^.*://.*/$|", $CONF['ItemURL']))
        $CONF['ItemURL'] = substr($CONF['ItemURL'], 0, -1);
      $uri = createItemLink($row['item_id']);

      $str = $str . '  <item title="'
	. htmlspecialchars($row['title'])
	. '" uri="'
	. $uri
	. '" />';
    }

    $sql = 'select tb.loc_id loc_id'
      . ', tb.title title'
      . ', tb.excerpt excerpt'
      . ', tb.url url'
      . ', tb.blog_name blog_name'
      . ' from '
      . sql_table("gmap_loc_tb")
      . ' tb where tb.loc_id = '
      . $loc_id
      . ';';
    $result = mysql_query($sql);

    $affected = mysql_affected_rows();
    for ($i = 0; $i < $affected; $i++) {
      $row = mysql_fetch_assoc($result);
      $str = $str . '  <trackback title="'
	. htmlspecialchars($row["title"])
	. '" url="'
	. htmlspecialchars($row["url"])
	. '" blog_name="'
	. htmlspecialchars($row["blog_name"])
	. '" />';
    }
    $str = $str . "</locate>";

    if ($this->useUnicode()) {
      echo $str;
    } else {
      echo mb_convert_encoding($str, "utf-8", _CHARSET);
    }
    return;
  }

  /* _CHARSETがutf-8の場合、true */
  function useUnicode() {
    return !strcasecmp(_CHARSET, "utf-8");
  }
}
?>
