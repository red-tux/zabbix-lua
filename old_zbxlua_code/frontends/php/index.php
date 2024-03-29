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
define('ZBX_PAGE_NO_AUTHORIZATION', 1);
define('ZBX_NOT_ALLOW_ALL_NODES', 1);
define('ZBX_HIDE_NODE_SELECTION', 1);

require_once('include/config.inc.php');
require_once('include/forms.inc.php');

$page['title']	= 'S_ZABBIX_BIG';
$page['file']	= 'index.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'name' =>			array(T_ZBX_STR, O_NO,	null,	NOT_EMPTY,	'isset({enter})', _('Username')),
	'password' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({enter})'),
	'sessionid' =>		array(T_ZBX_STR, O_OPT,	null,	null,		null),
	'reconnect' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535), null),
	'enter' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'autologin' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'request' =>		array(T_ZBX_STR, O_OPT, null,	null,		null)
);
check_fields($fields);
?>
<?php
$sessionid = get_cookie('zbx_sessionid');

if (isset($_REQUEST['reconnect']) && isset($sessionid)) {
	add_audit(AUDIT_ACTION_LOGOUT, AUDIT_RESOURCE_USER, _('Manual Logout'));

	CWebUser::logout($sessionid);
	clear_messages(1);

	$loginForm = new CView('general.login');
	$loginForm->render();
	exit();
}

$config = select_config();

if ($config['authentication_type'] == ZBX_AUTH_HTTP) {
	if (isset($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_USER'])) {
		$_REQUEST['enter'] = _('Sign in');
		$_REQUEST['name'] = $_SERVER['PHP_AUTH_USER'];
		$_REQUEST['password'] = 'zabbix';
	}
	else {
		access_deny();
	}
}

$request = get_request('request');

if (isset($_REQUEST['enter']) && $_REQUEST['enter'] == _('Sign in')) {
	$name = get_request('name', '');
	$passwd = get_request('password', '');
	$login = CWebUser::login($name, $passwd);

	if ($login) {
		// save remember login preferance
		$user = array('autologin' => get_request('autologin', 0));
		if (CWebUser::$data['autologin'] != $user['autologin']) {
			$result = API::User()->updateProfile($user);
		}

		add_audit_ext(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER, CWebUser::$data['userid'], '', null, null, null);

		$url = zbx_empty($request) ? CWebUser::$data['url'] : $request;
		if (zbx_empty($url) || $url == $page['file']) {
			$url = 'dashboard.php';
		}

		redirect($url);
		exit();
	}
}

if ($sessionid) {
	CWebUser::checkAuthentication($sessionid);
}

if (CWebUser::$data['alias'] == ZBX_GUEST_USER) {
	switch ($config['authentication_type']) {
		case ZBX_AUTH_HTTP:
			break;
		case ZBX_AUTH_LDAP:
		case ZBX_AUTH_INTERNAL:
			if (isset($_REQUEST['enter'])) {
				$_REQUEST['autologin'] = get_request('autologin', 0);
			}

			if ($messages = clear_messages()) {
				$messages = array_pop($messages);
				$_REQUEST['message'] = $messages['message'];
			}

			$loginForm = new CView('general.login');
			$loginForm->render();
	}
}
else {
	redirect(zbx_empty(CWebUser::$data['url']) ? 'dashboard.php' : CWebUser::$data['url']);
}
?>
