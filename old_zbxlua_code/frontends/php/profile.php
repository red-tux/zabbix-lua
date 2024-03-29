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
require_once('include/config.inc.php');
require_once('include/users.inc.php');
require_once('include/forms.inc.php');
require_once('include/media.inc.php');

$page['title'] = _('User profile');
$page['file'] = 'profile.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.cviewswitcher.js');

ob_start();

require_once('include/page_header.php');

if ($USER_DETAILS['alias'] == ZBX_GUEST_USER) {
	access_deny();
}
?>
<?php
//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'password1' =>			array(T_ZBX_STR, O_OPT, null, null, 'isset({save})&&isset({form})&&({form}!="update")&&isset({change_password})'),
	'password2' =>			array(T_ZBX_STR, O_OPT, null, null, 'isset({save})&&isset({form})&&({form}!="update")&&isset({change_password})'),
	'lang' =>				array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({save})'),
	'theme' =>				array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({save})'),
	'autologin' =>			array(T_ZBX_INT, O_OPT, null, IN('1'), null),
	'autologout' =>			array(T_ZBX_INT, O_OPT, null, BETWEEN(90,10000), null),
	'url' =>				array(T_ZBX_STR, O_OPT, null, null, 'isset({save})'),
	'refresh' =>			array(T_ZBX_INT, O_OPT, null, BETWEEN(0, SEC_PER_HOUR), 'isset({save})'),
	'rows_per_page' =>		array(T_ZBX_INT, O_OPT, null, BETWEEN(1,999999), 'isset({save})'),
	'change_password' =>	array(T_ZBX_STR, O_OPT, null, null, null),
	'user_medias' =>		array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, null),
	'user_medias_to_del' =>	array(T_ZBX_STR, O_OPT, null, DB_ID, null),
	'new_media' =>			array(T_ZBX_STR, O_OPT, null, null, null),
	'enable_media' =>		array(T_ZBX_INT, O_OPT, null, null, null),
	'disable_media' =>		array(T_ZBX_INT, O_OPT, null, null, null),
	'messages' =>			array(T_ZBX_STR, O_OPT, null, null, null),
	// actions
	'save'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'cancel'=>				array(T_ZBX_STR, O_OPT, P_SYS, null, null),
	'del_user_media'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	// form
	'form'=>				array(T_ZBX_STR, O_OPT, P_SYS, null, null),
	'form_refresh'=>		array(T_ZBX_STR, O_OPT, null, null, null)
);
check_fields($fields);
?>
<?php
$_REQUEST['autologin'] = get_request('autologin', 0);

// secondary actions
if (isset($_REQUEST['new_media'])) {
	$_REQUEST['user_medias'] = get_request('user_medias', array());
	array_push($_REQUEST['user_medias'], $_REQUEST['new_media']);
}
elseif (isset($_REQUEST['user_medias']) && isset($_REQUEST['enable_media'])) {
	if (isset($_REQUEST['user_medias'][$_REQUEST['enable_media']])) {
		$_REQUEST['user_medias'][$_REQUEST['enable_media']]['active'] = 0;
	}
}
elseif (isset($_REQUEST['user_medias']) && isset($_REQUEST['disable_media'])) {
	if (isset($_REQUEST['user_medias'][$_REQUEST['disable_media']])) {
		$_REQUEST['user_medias'][$_REQUEST['disable_media']]['active'] = 1;
	}
}
elseif (isset($_REQUEST['del_user_media'])) {
	$user_medias_to_del = get_request('user_medias_to_del', array());
	foreach ($user_medias_to_del as $mediaid) {
		if (isset($_REQUEST['user_medias'][$mediaid])) {
			unset($_REQUEST['user_medias'][$mediaid]);
		}
	}
}
// primary actions
elseif (isset($_REQUEST['cancel'])) {
	ob_end_clean();
	redirect(CWebUser::$data['last_page']['url']);
}
elseif (isset($_REQUEST['save'])) {
	$auth_type = get_user_system_auth($USER_DETAILS['userid']);

	if ($auth_type != ZBX_AUTH_INTERNAL) {
		$_REQUEST['password1'] = $_REQUEST['password2'] = null;
	}
	else {
		$_REQUEST['password1'] = get_request('password1', null);
		$_REQUEST['password2'] = get_request('password2', null);
	}

	if ($_REQUEST['password1'] != $_REQUEST['password2']) {
		show_error_message(_('Cannot update user. Both passwords must be equal.'));
	}
	elseif (isset($_REQUEST['password1']) && $USER_DETAILS['alias'] == ZBX_GUEST_USER && !zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('For guest, password must be empty'));
	}
	elseif (isset($_REQUEST['password1']) && $USER_DETAILS['alias'] != ZBX_GUEST_USER && zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('Password should not be empty'));
	}
	else {
		$user = array();
		$user['userid'] = $USER_DETAILS['userid'];
		$user['alias'] = $USER_DETAILS['alias'];
		$user['passwd'] = get_request('password1');
		$user['url'] = get_request('url');
		$user['autologin'] = get_request('autologin', 0);
		$user['autologout'] = get_request('autologout', 0);
		$user['lang'] = get_request('lang');
		$user['theme'] = get_request('theme');
		$user['refresh'] = get_request('refresh');
		$user['rows_per_page'] = get_request('rows_per_page');
		$user['user_groups'] = null;
		$user['user_medias'] = get_request('user_medias', array());

		$messages = get_request('messages', array());
		if (!isset($messages['enabled'])) {
			$messages['enabled'] = 0;
		}
		if (!isset($messages['sounds.recovery'])) {
			$messages['sounds.recovery'] = 0;
		}
		if (!isset($messages['triggers.recovery'])) {
			$messages['triggers.recovery'] = 0;
		}
		if (!isset($messages['triggers.severities'])) {
			$messages['triggers.severities'] = array();
		}

		DBstart();
		updateMessageSettings($messages);

		$result = API::User()->updateProfile($user);
		if ($result && (CwebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
			$data = array(
				'users' => $user,
				'medias' => $user['user_medias']
			);
			$result = API::User()->updateMedia($data);
		}

		$result = DBend($result);
		if (!$result) {
			error(API::User()->resetErrors());
		}

		if ($result) {
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_USER,
				'User alias ['.$USER_DETAILS['alias'].'] Name ['.$USER_DETAILS['name'].']'.
				' Surname ['.$USER_DETAILS['surname'].'] profile id ['.$USER_DETAILS['userid'].']');

			ob_end_clean();
			redirect(CWebUser::$data['last_page']['url']);
		}
		else {
			show_messages($result, _('User updated'), _('Cannot update user'));
		}
	}
}

ob_end_flush();

/*
 * Display
 */
$data = getUserFormData($USER_DETAILS['userid'], true);
$data['userid'] = $USER_DETAILS['userid'];
$data['form'] = get_request('form');
$data['form_refresh'] = get_request('form_refresh', 0);

// render view
$usersView = new CView('administration.users.edit', $data);
$usersView->render();
$usersView->show();

require_once('include/page_footer.php');
?>
