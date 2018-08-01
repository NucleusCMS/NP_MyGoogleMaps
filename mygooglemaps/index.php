<?php
// if your 'plugin' directory is not in the default location,
// edit this variable to point to your site directory
// (where config.php is)
$strRel = '../../../';

include($strRel . 'config.php');
if (!$member->isLoggedIn()) {
  doError('You\'re not logged in.');
}

include($DIR_LIBS . 'PLUGINADMIN.php');

// create the admin area page
$oPluginAdmin = new PluginAdmin('MyGoogleMaps');
$oPluginAdmin->start();

$loc_id = requestVar('loc_id');
$action = $CONF['ActionURL'];
$width = $oPluginAdmin->plugin->getOption('gmap_width') + 20;
$height = $oPluginAdmin->plugin->getOption('gmap_height') + 20;

$table_gmap_loc = sql_table("gmap_loc");
$table_gmap_loc_attr = sql_table("gmap_loc_attr");

print <<<EOD

<h2>MyGoogleMaps</h2>
<p>MyGoogleMapsの管理画面です。<p>

<h3>位置情報登録</h3>

<p>下記フォームから、位置情報を登録します。*の付いている項目は必須です。IDが入力されていない場合は新規登録、IDが入力されている場合は更新されます。</p>

<p><a href="javascript:void(0)" onclick="child = window.open('$action?action=plugin&name=MyGoogleMaps&type=edit_admin&loc_id=$loc_id', 'child', 'width=$width,height=$height')" >入力支援</a>(マップを別ウィンドウで開きます)</p>

<script type="text/javascript">
//<![CDATA[
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
    msg += '更新します.';
  } else {
    msg += '新規登録します.';
  }
  msg += 'よろしいですか?';
  return window.confirm(msg);
}
//]]>
</script>

<form name="form0" action="$action" method="POST" onsubmit="return onSubmit(document.form0)">
  <input type="hidden" name="action" value="plugin" />
  <input type="hidden" name="name" value="MyGoogleMaps" />
  <input type="hidden" name="type" value="store_admin" />
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

<h3>ALTER TABLE</h3>

<p>各バージョンからのアップグレード時には、下記リンクをクリックしてALTER TABLEを実行して下さい。このリンクを利用した実行では、ALTER権限が必要です。Nucleusが利用するMySQLユーザにALTER権限がない場合、下記リンクの文字列(ALTER TABLE文)をALTER権限のあるMySQLユーザで実行して下さい。</p>

<h4>～0.6 -> 0.7</h4>

<ul>
<li>手順通りアップグレードすれば問題ありません。</li>
</ul>

<h4>～0.5 -> 0.6</h4>

<ul>
<li><a href="$action?action=plugin&amp;name=MyGoogleMaps&amp;type=upgrade&amp;code=3">3: ALTER TABLE $table_gmap_loc_attr ADD img_height SMALLINT;</a></li>
<li><a href="$action?action=plugin&amp;name=MyGoogleMaps&amp;type=upgrade&amp;code=2">2: ALTER TABLE $table_gmap_loc_attr ADD img_width SMALLINT;</a></li>
</ul>

<h4>～0.4 -> 0.5</h4>

<ul>
<li><a href="$action?action=plugin&amp;name=MyGoogleMaps&amp;type=upgrade&amp;code=1">1: ALTER TABLE $table_gmap_loc MODIFY deleteFlag BOOL DEFAULT 0 NOT NULL;</a></li>
</ul>

EOD;

$oPluginAdmin->end();
?>
