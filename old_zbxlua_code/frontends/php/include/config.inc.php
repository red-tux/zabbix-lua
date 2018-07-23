<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once('include/debug.inc.php');

function __autoload($class_name) {
	$class_name = zbx_strtolower($class_name);
	$api = array(
		'apiexception' => 1,
		'caction' => 1,
		'calert' => 1,
		'capiinfo' => 1,
		'capplication' => 1,
		'cdcheck' => 1,
		'cdhost' => 1,
		'cdiscoveryrule' => 1,
		'cdrule' => 1,
		'cdservice' => 1,
		'cevent' => 1,
		'cgraph' => 1,
		'cgraphprototype' => 1,
		'cgraphitem' => 1,
		'chistory' => 1,
		'chost' => 1,
		'chostgroup' => 1,
		'chostinterface'=> 1,
		'ciconmap' => 1,
		'cimage' => 1,
		'citem' => 1,
		'citemgeneral' => 1,
		'citemprototype' => 1,
		'cmaintenance' => 1,
		'cmap' => 1,
		'cmapelement' => 1,
		'cmediatype' => 1,
		'cproxy' => 1,
		'cscreen' => 1,
		'cscreenitem' => 1,
		'cscript' => 1,
		'ctemplate' => 1,
		'ctemplatescreen' => 1,
		'ctrigger' => 1,
		'ctriggerexpression' => 1,
		'citemkey' => 1,
		'ctriggerprototype' => 1,
		'cuser' => 1,
		'cusergroup' => 1,
		'cusermacro' => 1,
		'cusermedia' => 1,
		'cwebcheck' => 1,
		'czbxapi' => 1,
	);

	$rpc = array(
		'cjsonrpc' =>1,
		'czbxrpc' => 1,
		'cxmlrpc' => null,
		'csoap' => null,
		'csoapjr' => null
	);

	if (isset($api[$class_name])) {
		require_once('api/classes/class.'.$class_name.'.php');
	}
	elseif (isset($rpc[$class_name])) {
		require_once('api/rpc/class.'.$class_name.'.php');
	}
	else {
		require_once('include/classes/class.'.$class_name.'.php');
	}
}
?>
<?php
require_once('include/api.inc.php');
require_once('include/gettextwrapper.inc.php');
require_once('include/defines.inc.php');
require_once('include/func.inc.php');
require_once('include/html.inc.php');
require_once('include/copt.lib.php');
require_once('include/profiles.inc.php');
require_once('conf/maintenance.inc.php');
// abc sorting
require_once('include/acknow.inc.php');
require_once('include/actions.inc.php');
require_once('include/discovery.inc.php');
require_once('include/events.inc.php');
require_once('include/graphs.inc.php');
require_once('include/hosts.inc.php');
require_once('include/httptest.inc.php');
require_once('include/ident.inc.php');
require_once('include/images.inc.php');
require_once('include/items.inc.php');
require_once('include/maintenances.inc.php');
require_once('include/maps.inc.php');
require_once('include/media.inc.php');
require_once('include/nodes.inc.php');
require_once('include/services.inc.php');
require_once('include/sounds.inc.php');
require_once('include/triggers.inc.php');
require_once('include/users.inc.php');
require_once('include/valuemap.inc.php');

global $USER_DETAILS, $USER_RIGHTS, $ZBX_PAGE_POST_JS, $page;
global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID, $ZBX_CONFIGURATION_FILE, $DB;
global $ZBX_SERVER, $ZBX_SERVER_PORT;
global $ZBX_LOCALES;

$page = array();
$USER_DETAILS = array();
$USER_RIGHTS = array();
$ZBX_LOCALNODEID = 0;
$ZBX_LOCMASTERID = 0;
$ZBX_CONFIGURATION_FILE = './conf/zabbix.conf.php';
$ZBX_CONFIGURATION_FILE = realpath(dirname($ZBX_CONFIGURATION_FILE)).'/'.basename($ZBX_CONFIGURATION_FILE);

// include Tactical Overview modules
require_once('include/locales.inc.php');
require_once('include/perm.inc.php');
require_once('include/audit.inc.php');
require_once('include/js.inc.php');

// include Validation
require_once('include/validate.inc.php');

function zbx_err_handler($errno, $errstr, $errfile, $errline) {
	$pathLength = strlen(__FILE__);

	// strlen(include/config.inc.php) = 22
	$pathLength -= 22;
	$errfile = substr($errfile, $pathLength);

	error($errstr.' ['.$errfile.':'.$errline.']');
}

/*
 * start initialization
 */
set_error_handler('zbx_err_handler');
unset($show_setup);

if (defined('ZBX_DENY_GUI_ACCESS')) {
	if (isset($ZBX_GUI_ACCESS_IP_RANGE) && is_array($ZBX_GUI_ACCESS_IP_RANGE)) {
		$user_ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? ($_SERVER['HTTP_X_FORWARDED_FOR']) : ($_SERVER['REMOTE_ADDR']);
		if (!str_in_array($user_ip,$ZBX_GUI_ACCESS_IP_RANGE)) {
			$DENY_GUI = true;
		}
	}
	else {
		$DENY_GUI = true;
	}
}

if (file_exists($ZBX_CONFIGURATION_FILE) && !isset($_COOKIE['ZBX_CONFIG']) && !isset($DENY_GUI)) {
	$config = new CConfigFile($ZBX_CONFIGURATION_FILE);
	if ($config->load()) {
		$config->makeGlobal();
	}
	else {
		$show_warning = true;
		define('ZBX_DISTRIBUTED', false);
		if (!defined('ZBX_PAGE_NO_AUTHORIZATION')) {
			define('ZBX_PAGE_NO_AUTHORIZATION', true);
		}
		error($config->error);
	}

	require_once('include/db.inc.php');

	if (!isset($show_warning)) {
		$error = '';
		if (!DBconnect($error)) {
			$_REQUEST['message'] = $error;

			define('ZBX_DISTRIBUTED', false);
			if (!defined('ZBX_PAGE_NO_AUTHORIZATION')) {
				define('ZBX_PAGE_NO_AUTHORIZATION', true);
			}
			$show_warning = true;
		}
		else {
			global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID;

			// init LOCAL NODE ID
			if ($local_node_data = DBfetch(DBselect('SELECT * FROM nodes WHERE nodetype=1 ORDER BY nodeid'))) {
				$ZBX_LOCALNODEID = $local_node_data['nodeid'];
				$ZBX_LOCMASTERID = $local_node_data['masterid'];
				$ZBX_NODES[$local_node_data['nodeid']] = $local_node_data;
				define('ZBX_DISTRIBUTED', true);
			}
			else {
				define('ZBX_DISTRIBUTED', false);
			}
			unset($local_node_data);
		}
		unset($error);
	}
}
else {
	if (file_exists($ZBX_CONFIGURATION_FILE)) {
		ob_start();
		include $ZBX_CONFIGURATION_FILE;
		ob_end_clean();
	}

	require_once('include/db.inc.php');

	if (!defined('ZBX_PAGE_NO_AUTHORIZATION')) {
		define('ZBX_PAGE_NO_AUTHORIZATION', true);
	}
	define('ZBX_DISTRIBUTED', false);
	$show_setup = true;
}

if (!defined('ZBX_PAGE_NO_AUTHORIZATION') && !defined('ZBX_RPC_REQUEST')) {
	if (!CWebUser::checkAuthentication(get_cookie('zbx_sessionid'))) {
		require_once('include/locales/en_gb.inc.php');
		process_locales();

		include('index.php');
		exit();
	}

	if (function_exists('bindtextdomain')) {
		// initializing gettext translations depending on language selected by user
		$locales = zbx_locale_variants(CWebUser::$data['lang']);
		$locale_found = false;
		foreach ($locales as $locale) {
			putenv('LC_ALL='.$locale);
			putenv('LANG='.$locale);
			putenv('LANGUAGE='.$locale);

			if (setlocale(LC_ALL, $locale)) {
				$locale_found = true;
				CWebUser::$data['locale'] = $locale;
				break;
			}
		}

		if (!$locale_found && CWebUser::$data['lang'] != 'en_GB' && CWebUser::$data['lang'] != 'en_gb') {
			error('Locale for language "'.CWebUser::$data['lang'].'" is not found on the web server. Tried to set: '.implode(', ', $locales).'. Unable to translate Zabbix interface.');
		}
		bindtextdomain('frontend', 'locale');
		bind_textdomain_codeset('frontend', 'UTF-8');
		textdomain('frontend');
	}
	else {
		error('Your PHP has no gettext support. Zabbix translations are not available.');
	}
	// numeric Locale to default
	setlocale(LC_NUMERIC, array('C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8'));
}
else {
	CWebUser::$data = array(
		'alias' => ZBX_GUEST_USER,
		'userid'=> 0,
		'lang'  => 'en_gb',
		'type'  => '0',
		'node'  => array(
			'name'  => '- unknown -',
			'nodeid'=> 0)
		);

	$USER_DETAILS = CWebUser::$data;
}

require_once('include/locales/en_gb.inc.php');
process_locales();
set_zbx_locales();

// init mb strings if it's available
init_mbstrings();

// Ajax - do not need warnings or Errors
if ((isset($DENY_GUI) || isset($show_setup) || isset($show_warning)) && PAGE_TYPE_HTML <> detect_page_type()) {
	header('Ajax-response: false');
	exit();
}

if (isset($DENY_GUI)) {
	unset($show_warning);
	require_once('warning.php');
}

if (isset($show_setup)) {
	unset($show_setup);
	require_once('setup.php');
}
elseif (isset($show_warning)) {
	unset($show_warning);
	require_once('warning.php');
}

function access_deny() {
	require_once('include/page_header.php');

	if (CWebUser::$data['alias'] != ZBX_GUEST_USER) {
		show_error_message(S_NO_PERMISSIONS);
	}
	else {
		$req = new Curl($_SERVER['REQUEST_URI']);
		$req->setArgument('sid', null);

		$table = new CTable(null, 'warningTable');
		$table->setAlign('center');
		$table->setHeader(new CCol(_('You are not logged in.'), 'left'), 'header');
		$table->addRow(new CCol(array(_('You cannot view this URL as a'), SPACE, bold(ZBX_GUEST_USER), '. ', _('You must login to view this page.'), BR(), _('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')), 'center'));

		$url = urlencode($req->toString());
		$footer = new CCol(
			array(
				new CButton('login', _('Login'), "javascript: document.location = 'index.php?request=$url';"),
				new CButton('back', _('Cancel'), 'javascript: window.history.back();')
			),
			'left');
		$table->setFooter($footer,'footer');
		$table->show();
	}
	require_once('include/page_footer.php');
}

function detect_page_type($default = PAGE_TYPE_HTML) {
	if (isset($_REQUEST['output'])) {
		switch (strtolower($_REQUEST['output'])) {
			case 'text':
				return PAGE_TYPE_TEXT;
				break;
			case 'ajax':
				return PAGE_TYPE_JS;
				break;
			case 'json':
				return PAGE_TYPE_JSON;
				break;
			case 'json-rpc':
				return PAGE_TYPE_JSON_RPC;
				break;
			case 'html':
				return PAGE_TYPE_HTML_BLOCK;
				break;
			case 'img':
				return PAGE_TYPE_IMAGE;
				break;
			case 'css':
				return PAGE_TYPE_CSS;
				break;
		}
	}
	return $default;
}

function show_messages($bool = true, $okmsg = null, $errmsg = null) {
	global $page, $ZBX_MESSAGES;
	if (!defined('PAGE_HEADER_LOADED')) {
		return null;
	}
	if (defined('ZBX_API_REQUEST')) {
		return null;
	}
	if (!isset($page['type'])) {
		$page['type'] = PAGE_TYPE_HTML;
	}

	$message = array();
	$width = 0;
	$height= 0;

	if (!$bool && !is_null($errmsg)) {
		$msg = _('ERROR').': '.$errmsg;
	}
	elseif ($bool && !is_null($okmsg)) {
		$msg = $okmsg;
	}

	if (isset($msg)) {
		switch ($page['type']) {
			case PAGE_TYPE_IMAGE:
				array_push($message, array(
					'text' => $msg,
					'color' => (!$bool) ? array('R' => 255, 'G' => 0, 'B' => 0) : array('R' => 34, 'G' => 51, 'B' => 68),
					'font' => 2
				));
				$width = max($width, ImageFontWidth(2) * zbx_strlen($msg) + 1);
				$height += imagefontheight(2) + 1;
				break;
			case PAGE_TYPE_XML:
				echo htmlspecialchars($msg)."\n";
				break;
			case PAGE_TYPE_HTML:
			default:
				$msg_tab = new CTable($msg, ($bool ? 'msgok' : 'msgerr'));
				$msg_tab->setCellPadding(0);
				$msg_tab->setCellSpacing(0);

				$row = array();

				$msg_col = new CCol(bold($msg), 'msg_main msg');
				$msg_col->setAttribute('id', 'page_msg');
				$row[] = $msg_col;

				if (isset($ZBX_MESSAGES) && !empty($ZBX_MESSAGES)) {
					$msg_details = new CDiv(_('Details'), 'blacklink');
					$msg_details->setAttribute('onclick', "javascript: ShowHide('msg_messages', IE?'block':'table');");
					$msg_details->setAttribute('title', _('Maximize').'/'._('Minimize'));
					array_unshift($row, new CCol($msg_details, 'clr'));
				}
				$msg_tab->addRow($row);
				$msg_tab->show();
				break;
		}
	}

	if (isset($ZBX_MESSAGES) && !empty($ZBX_MESSAGES)) {
		if ($page['type'] == PAGE_TYPE_IMAGE) {
			$msg_font = 2;
			foreach ($ZBX_MESSAGES as $msg) {
				if ($msg['type'] == 'error') {
					array_push($message, array(
						'text' => $msg['message'],
						'color' => array('R' => 255, 'G' => 55, 'B' => 55),
						'font' => $msg_font
					));
				}
				else {
					array_push($message, array(
						'text' => $msg['message'],
						'color' => array('R' => 155, 'G' => 155, 'B' => 55),
						'font' => $msg_font));
				}
				$width = max($width, imagefontwidth($msg_font) * zbx_strlen($msg['message']) + 1);
				$height += imagefontheight($msg_font) + 1;
			}
		}
		elseif ($page['type'] == PAGE_TYPE_XML) {
			foreach ($ZBX_MESSAGES as $msg) {
				echo '['.$msg['type'].'] '.$msg['message']."\n";
			}
		}
		else {
			$lst_error = new CList(null,'messages');
			foreach ($ZBX_MESSAGES as $msg) {
				$lst_error->addItem($msg['message'], $msg['type']);
				$bool = ($bool && ('error' != zbx_strtolower($msg['type'])));
			}
			$msg_show = 6;
			$msg_count = count($ZBX_MESSAGES);
			if ($msg_count > $msg_show) {
				$msg_count = $msg_show;
				$msg_count = ($msg_count * 16);
				$lst_error->setAttribute('style', 'height: '.$msg_count.'px;');
			}
			$tab = new CTable(null, ($bool ? 'msgok' : 'msgerr'));
			$tab->setCellPadding(0);
			$tab->setCellSpacing(0);
			$tab->setAttribute('id', 'msg_messages');
			$tab->setAttribute('style', 'width: 100%;');
			if (isset($msg_tab) && $bool) {
				$tab->setAttribute('style', 'display: none;');
			}
			$tab->addRow(new CCol($lst_error, 'msg'));
			$tab->show();
		}
		$ZBX_MESSAGES = null;
	}

	if ($page['type'] == PAGE_TYPE_IMAGE && count($message) > 0) {
		$width += 2;
		$height += 2;
		$canvas = imagecreate($width, $height);
		imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));

		foreach ($message as $id => $msg) {
			$message[$id]['y'] = 1 + (isset($previd) ? $message[$previd]['y'] + $message[$previd]['h'] : 0);
			$message[$id]['h'] = imagefontheight($msg['font']);
			imagestring(
				$canvas,
				$msg['font'],
				1,
				$message[$id]['y'],
				$msg['text'],
				imagecolorallocate($canvas, $msg['color']['R'], $msg['color']['G'], $msg['color']['B'])
			);
			$previd = $id;
		}
		imageOut($canvas);
		imagedestroy($canvas);
	}
}

function show_message($msg) {
	show_messages(true, $msg, '');
}

function show_error_message($msg) {
	show_messages(false, '', $msg);
}

function info($msgs) {
	global $ZBX_MESSAGES;
	zbx_value2array($msgs);
	if (is_null($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = array();
	}
	foreach ($msgs as $msg) {
		array_push($ZBX_MESSAGES, array('type' => 'info', 'message' => $msg));
	}
}

function error($msgs) {
	global $ZBX_MESSAGES;
	$msgs = zbx_toArray($msgs);

	if (is_null($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = array();
	}

	foreach ($msgs as $msg) {
		if (isset(CWebUser::$data['debug_mode']) && !is_object($msg) && !CWebUser::$data['debug_mode']) {
			$msg = preg_replace('/^\[.+?::.+?\]/', '', $msg);
		}
		array_push($ZBX_MESSAGES, array('type' => 'error', 'message' => $msg));
	}
}

function clear_messages($count = null) {
	global $ZBX_MESSAGES;
	$result = array();
	if (!is_null($count)) {
		while ($count-- > 0) {
			array_unshift($result, array_pop($ZBX_MESSAGES));
		}
	}
	else {
		$result = $ZBX_MESSAGES;
		$ZBX_MESSAGES = null;
	}
	return $result;
}

function fatal_error($msg) {
	require_once('include/page_header.php');
	show_error_message($msg);
	require_once('include/page_footer.php');
}

function get_tree_by_parentid($parentid, &$tree, $parent_field, $level = 0) {
	if (empty($tree)) {
		return $tree;
	}

	$level++;
	if ($level > 32) {
		return array();
	}

	$result = array();
	if (isset($tree[$parentid])) {
		$result[$parentid] = $tree[$parentid];
	}

	$tree_ids = array_keys($tree);

	foreach ($tree_ids as $key => $id) {
		$child = $tree[$id];
		if (bccomp($child[$parent_field],$parentid) == 0) {
			$result[$id] = $child;
			$childs = get_tree_by_parentid($id, $tree, $parent_field, $level); // ATTENTION RECURSION !!!
			$result += $childs;
		}
	}
	return $result;
}

function parse_period($str) {
	$out = null;
	$str = trim($str, ';');
	$periods = explode(';', $str);
	foreach ($periods as $period) {
		if (!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $period, $arr)) {
			return null;
		}

		for ($i = $arr[1]; $i <= $arr[2]; $i++) {
			if (!isset($out[$i])) {
				$out[$i] = array();
			}
			array_push($out[$i],
				array(
					'start_h'	=> $arr[3],
					'start_m'	=> $arr[4],
					'end_h'		=> $arr[5],
					'end_m'		=> $arr[6]
				));
		}
	}
	return $out;
}

function get_status() {
	global $ZBX_SERVER, $ZBX_SERVER_PORT;
	$status = array();

	// server
	$checkport = fsockopen($ZBX_SERVER, $ZBX_SERVER_PORT, $errnum, $errstr, 2);
	if (!$checkport) {
		clear_messages();
		$status['zabbix_server'] = _('No');
	}
	else {
		$status['zabbix_server'] = _('Yes');
	}

	// triggers
	$sql = 'SELECT COUNT(DISTINCT t.triggerid) AS cnt'.
			' FROM triggers t,functions f,items i,hosts h'.
			' WHERE t.triggerid=f.triggerid'.
				' AND f.itemid=i.itemid'.
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND i.hostid=h.hostid'.
				' AND h.status='.HOST_STATUS_MONITORED;

	$row = DBfetch(DBselect($sql));
	$status['triggers_count'] = $row['cnt'];

	$row = DBfetch(DBselect($sql.' AND t.status='.TRIGGER_STATUS_ENABLED));
	$status['triggers_count_enabled'] = $row['cnt'];

	$row = DBfetch(DBselect($sql.' AND t.status='.TRIGGER_STATUS_DISABLED));
	$status['triggers_count_disabled'] = $row['cnt'];

	$row = DBfetch(DBselect($sql.' AND t.status='.TRIGGER_STATUS_ENABLED.' AND t.value='.TRIGGER_VALUE_FALSE));
	$status['triggers_count_off'] = $row['cnt'];

	$row = DBfetch(DBselect($sql.' AND t.status='.TRIGGER_STATUS_ENABLED.' AND t.value='.TRIGGER_VALUE_TRUE));
	$status['triggers_count_on'] = $row['cnt'];

	$row = DBfetch(DBselect($sql.' AND t.status='.TRIGGER_STATUS_ENABLED.' AND t.value='.TRIGGER_VALUE_UNKNOWN));
	$status['triggers_count_unknown'] = $row['cnt'];

	// items
	$sql = 'SELECT COUNT(i.itemid) AS cnt'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND h.status='.HOST_STATUS_MONITORED;

	$row = DBfetch(DBselect($sql));
	$status['items_count'] = $row['cnt'];

	$row = DBfetch(DBselect($sql.' AND i.status='.ITEM_STATUS_ACTIVE));
	$status['items_count_monitored'] = $row['cnt'];

	$row = DBfetch(DBselect($sql.' AND i.status='.ITEM_STATUS_DISABLED));
	$status['items_count_disabled'] = $row['cnt'];

	$row = DBfetch(DBselect($sql.' AND i.status='.ITEM_STATUS_NOTSUPPORTED));
	$status['items_count_not_supported'] = $row['cnt'];

	// hosts
	$sql = 'SELECT COUNT(hostid) AS cnt'.
			' FROM hosts'.
			' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';

	$row = DBfetch(DBselect($sql));
	$status['hosts_count'] = $row['cnt'];

	$row = DBfetch(DBselect('SELECT COUNT(hostid) as cnt FROM hosts WHERE status='.HOST_STATUS_MONITORED));
	$status['hosts_count_monitored'] = $row['cnt'];

	$row = DBfetch(DBselect('SELECT COUNT(hostid) as cnt FROM hosts WHERE status='.HOST_STATUS_NOT_MONITORED));
	$status['hosts_count_not_monitored'] = $row['cnt'];

	$row = DBfetch(DBselect('SELECT COUNT(hostid) as cnt FROM hosts WHERE status='.HOST_STATUS_TEMPLATE));
	$status['hosts_count_template'] = $row['cnt'];

	// users
	$row = DBfetch(DBselect('SELECT COUNT(userid) as usr_cnt FROM users u WHERE '.DBin_node('u.userid')));
	$status['users_count'] = $row['usr_cnt'];

	$status['users_online'] = 0;

	$sql = 'SELECT s.userid,s.status,MAX(s.lastaccess) AS lastaccess'.
			' FROM sessions s'.
			' WHERE '.DBin_node('s.userid').
				' AND s.status='.ZBX_SESSION_ACTIVE.
			' GROUP BY s.userid,s.status';
	$db_sessions = DBselect($sql);
	while ($session = DBfetch($db_sessions)) {
		if (($session['lastaccess'] + ZBX_USER_ONLINE_TIME) >= time()) {
			$status['users_online']++;
		}
	}

	// comments: !!! Don't forget sync code with C !!!
	$sql = 'SELECT sum(1.0/i.delay) AS qps'.
			' FROM items i,hosts h'.
			' WHERE i.status='.ITEM_STATUS_ACTIVE.
				' AND i.hostid=h.hostid'.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND i.delay<>0';
	$row = DBfetch(DBselect($sql));
	$status['qps_total'] = round($row['qps'], 2);
	return $status;
}

function set_image_header($format = null) {
	global $IMAGE_FORMAT_DEFAULT;
	if (is_null($format)) {
		$format = $IMAGE_FORMAT_DEFAULT;
	}

	if (IMAGE_FORMAT_JPEG == $format) {
		header('Content-type:  image/jpeg');
	}
	if (IMAGE_FORMAT_TEXT == $format) {
		header('Content-type:  text/html');
	}
	else {
		header('Content-type:  image/png');
	}

	header('Expires:  Mon, 17 Aug 1998 12:51:50 GMT');
}

function imageOut(&$image, $format = null) {
	global $page, $IMAGE_FORMAT_DEFAULT;

	if (is_null($format)) {
		$format = $IMAGE_FORMAT_DEFAULT;
	}

	ob_start();

	if (IMAGE_FORMAT_JPEG == $format) {
		imagejpeg($image);
	}
	else {
		imagepng($image);
	}

	$imageSource = ob_get_contents();
	ob_end_clean();

	if ($page['type'] != PAGE_TYPE_IMAGE) {
		session_start();
		$imageId = md5(strlen($imageSource));
		$_SESSION['image_id'] = array();
		$_SESSION['image_id'][$imageId] = $imageSource;
		session_write_close();
	}

	switch ($page['type']) {
		case PAGE_TYPE_IMAGE:
			print($imageSource);
			break;
		case PAGE_TYPE_JSON:
			$json = new CJSON();
			print($json->encode(array('result' => $imageId)));
			break;
		case PAGE_TYPE_TEXT:
		default:
			print($imageId);
	}
}

function encode_log($data) {
	if (defined('ZBX_LOG_ENCODING_DEFAULT') && function_exists('mb_convert_encoding')) {
		return mb_convert_encoding($data, _('UTF-8'), ZBX_LOG_ENCODING_DEFAULT);
	}
	else {
		return $data;
	}
}

// function used in defines, so can't move it to func.inc.php
function zbx_stripslashes($value) {
	if (is_array($value)) {
		foreach ($value as $id => $data) {
			$value[$id] = zbx_stripslashes($data);
		}
	}
	elseif (is_string($value)) {
		$value = stripslashes($value);
	}
	return $value;
}
?>