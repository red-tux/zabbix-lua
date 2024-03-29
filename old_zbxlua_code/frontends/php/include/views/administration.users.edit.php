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
include('include/views/js/administration.users.edit.js.php');
global $ZBX_LOCALES, $USER_DETAILS;

$userWidget = new CWidget();
if ($this->data['is_profile']) {
	$userWidget->addPageHeader(_('USER PROFILE').' : '.$this->data['name'].' '.$this->data['surname']);
}
else {
	$userWidget->addPageHeader(_('CONFIGURATION OF USER'));
}

// create form
$userForm = new CForm();
$userForm->setName('userForm');
$userForm->addVar('config', get_request('config', 0));
$userForm->addVar('form', $this->data['form']);
$userForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
if (isset($_REQUEST['userid'])) {
	$userForm->addVar('userid', $this->data['userid']);
}

/*
 * User tab
 */
$userFormList = new CFormList('userFormList');

if (!$data['is_profile']) {
	$userFormList->addRow(_('Alias'), new CTextBox('alias', $this->data['alias'], ZBX_TEXTBOX_STANDARD_SIZE));
	$userFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE));
	$userFormList->addRow(_('Surname'), new CTextBox('surname', $this->data['surname'], ZBX_TEXTBOX_STANDARD_SIZE));
}

// append password to form list
if ($data['auth_type'] == ZBX_AUTH_INTERNAL) {
	if (empty($this->data['userid']) || isset($this->data['change_password'])) {
		$userFormList->addRow(_('Password'), new CPassBox('password1', $this->data['password1'], ZBX_TEXTBOX_SMALL_SIZE));
		$userFormList->addRow(_('Password (once again)'), new CPassBox('password2', $this->data['password2'], ZBX_TEXTBOX_SMALL_SIZE));
		if (isset($this->data['change_password'])) {
			$userForm->addVar('change_password', $this->data['change_password']);
		}
	}
	else {
		$passwdButton = new CSubmit('change_password', _('Change password'));
		if ($this->data['alias'] == ZBX_GUEST_USER) {
			$passwdButton->setAttribute('disabled','disabled');
		}
		$userFormList->addRow(_('Password'), $passwdButton);
	}
}

// append user groups to form list
if (!$this->data['is_profile']) {
	$userForm->addVar('user_groups', $this->data['user_groups']);
	$lstGroups = new CListBox('user_groups_to_del[]', null, 10);
	$lstGroups->attributes['style'] = 'width: 320px';
	foreach ($this->data['groups'] as $group) {
		$lstGroups->addItem($group['usrgrpid'], $group['name']);
	}

	$userFormList->addRow(_('Groups'),
		array(
			$lstGroups,
			BR(),
			new CButton('add_group', _('Add'), 'return PopUp("popup_usrgrp.php?dstfrm='.$userForm->getName().'&list_name=user_groups_to_del[]&var_name=user_groups",450, 450);'),
			SPACE,
			(count($this->data['user_groups']) > 0) ? new CSubmit('del_user_group', _('Delete selected')) : null
		)
	);
}

// append languages to form list
$LangComboBox = new CComboBox('lang', $this->data['lang']);
$languages_unable_set = 0;
foreach ($ZBX_LOCALES as $loc_id => $loc_name) {
	// checking if this locale exists in the system. The only way of doing it is to try and set one
	// trying to set only the LC_MESSAGES locale to avoid changing LC_NUMERIC
	$locale_exists = setlocale(LC_MESSAGES, zbx_locale_variants($loc_id)) || $loc_id == 'en_GB' ? 'yes' : 'no';
	$selected = ($loc_id == $USER_DETAILS['lang']) ? true : null;
	$LangComboBox->addItem($loc_id, $loc_name, $selected, $locale_exists);

	if ($locale_exists != 'yes') {
		$languages_unable_set++;
	}
}
setlocale(LC_MESSAGES, zbx_locale_variants($USER_DETAILS['lang'])); // restoring original locale
$lang_hint = $languages_unable_set > 0 ? _('You are not able to choose some of the languages, because locales for them are not installed on the web server.') : '';
$userFormList->addRow(_('Language'), array($LangComboBox, new CSpan($lang_hint, 'red wrap')));

// append themes to form list
$themeComboBox = new CComboBox('theme', $this->data['theme']);
$themeComboBox->addItem(ZBX_DEFAULT_CSS, _('System default'));
$themeComboBox->addItem('css_ob.css', _('Original blue'));
$themeComboBox->addItem('css_bb.css', _('Black & Blue'));
$themeComboBox->addItem('css_od.css', _('Dark orange'));
$userFormList->addRow(_('Theme'), $themeComboBox);

// append auto-login & auto-logout to form list
$autologoutCheckBox = new CCheckBox('autologout_visible', ($this->data['autologout'] == 0) ? 'no' : 'yes');
$autologoutTextBox = new CNumericBox('autologout', ($this->data['autologout'] == 0) ? '90' : $this->data['autologout'], 4);
if (!$this->data['autologout']) {
	$autologoutTextBox->setAttribute('disabled', 'disabled');
}
$userFormList->addRow(_('Auto-login'), new CCheckBox('autologin', $this->data['autologin'], null, 1));
$userFormList->addRow(_('Auto-logout (min 90 seconds)'), array($autologoutCheckBox, $autologoutTextBox));

$userFormList->addRow(_('Refresh (in seconds)'), new CNumericBox('refresh', $this->data['refresh'], 4));
$userFormList->addRow(_('Rows per page'), new CNumericBox('rows_per_page', $this->data['rows_per_page'], 6));
$userFormList->addRow(_('URL (after login)'), new CTextBox('url', $this->data['url'], 50));

/*
 * Media tab
 */
if (uint_in_array($USER_DETAILS['type'], array(USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN))) {
	$userMediaFormList = new CFormList('userMediaFormList');
	$userForm->addVar('user_medias', $this->data['user_medias']);

	$mediaTableInfo = new CTableInfo(_('No media defined.'));
	foreach ($this->data['user_medias'] as $id => $media) {
		if (!isset($media['active']) || !$media['active']) {
			$status = new CLink(_('Enabled'), '#', 'enabled');
			$status->onClick('return create_var("'.$userForm->getName().'","disable_media",'.$id.', true);');
		}
		else {
			$status = new CLink(_('Disabled'), '#', 'disabled');
			$status->onClick('return create_var("'.$userForm->getName().'","enable_media",'.$id.', true);');
		}
		$mediaUrl = '?dstfrm='.$userForm->getName().
						'&media='.$id.
						'&mediatypeid='.$media['mediatypeid'].
						'&sendto='.urlencode($media['sendto']).
						'&period='.$media['period'].
						'&severity='.$media['severity'].
						'&active='.$media['active'];

		$mediaTableInfo->addRow(array(
			new CCheckBox('user_medias_to_del['.$id.']', null, null, $id),
			new CSpan($this->data['media_types'][$media['mediatypeid']], 'nowrap'),
			new CSpan($media['sendto'], 'nowrap'),
			new CSpan($media['period'], 'nowrap'),
			media_severity2str($media['severity']),
			$status,
			new CButton('edit_media', _('Edit'), 'javascript: return PopUp("popup_media.php'.$mediaUrl.'",550,400);', 'link_menu'))
		);
	}

	$userMediaFormList->addRow(_('Media'),
		array($mediaTableInfo,
			new CButton('add_media', _('Add'), 'javascript: return PopUp("popup_media.php?dstfrm='.$userForm->getName().'",550,400);', 'link_menu'),
			SPACE,
			SPACE,
			(count($this->data['user_medias']) > 0) ? new CSubmit('del_user_media', _('Delete selected'), null, 'link_menu') : null
	));
}

/*
 * Profile fields
 */
if ($this->data['is_profile']) {
	$userMessagingFormList = new CFormList('userMessagingFormList');
	$userMessagingFormList->addRow(_('Frontend messaging'), new CCheckBox('messages[enabled]', $this->data['messages']['enabled'], null, 1));
	$userMessagingFormList->addRow(_('Message timeout (seconds)'), new CNumericBox('messages[timeout]', $this->data['messages']['timeout'], 5), false, 'timeout_row');

	$repeatSound = new CComboBox('messages[sounds.repeat]', $this->data['messages']['sounds.repeat'], 'javascript: if(IE) submit();');
	$repeatSound->setAttribute('id', 'messages[sounds.repeat]');
	$repeatSound->addItem(1, _('Once'));
	$repeatSound->addItem(10, '10 '._('Seconds'));
	$repeatSound->addItem(-1, _('Message timeout'));
	$userMessagingFormList->addRow(_('Play sound'), $repeatSound, false, 'repeat_row');

	$zbxSounds = getSounds();
	$soundList = new CComboBox('messages[sounds.recovery]', $this->data['messages']['sounds.recovery']);
	foreach ($zbxSounds as $filename => $file) {
		$soundList->addItem($file, $filename);
	}

	$resolved = array(
		new CCheckBox('messages[triggers.recovery]', $this->data['messages']['triggers.recovery'], null, 1),
		_('Recovery'),
		$soundList,
		new CButton('start', _('Play'), "javascript: testUserSound('messages[sounds.recovery]');"),
		new CButton('stop', _('Stop'), 'javascript: AudioList.stopAll();')
	);

	$triggersTable = new CTable('', 'invisible');
	$triggersTable->addRow($resolved);

	$msgVisibility = array('1' => array(
			'messages[timeout]',
			'messages[sounds.repeat]',
			'messages[sounds.recovery]',
			'messages[triggers.recovery]',
			'timeout_row',
			'repeat_row',
			'triggers_row')
	);

	// trigger sounds
	$severities = array(
		TRIGGER_SEVERITY_NOT_CLASSIFIED,
		TRIGGER_SEVERITY_INFORMATION,
		TRIGGER_SEVERITY_WARNING,
		TRIGGER_SEVERITY_AVERAGE,
		TRIGGER_SEVERITY_HIGH,
		TRIGGER_SEVERITY_DISASTER
	);
	foreach ($severities as $severity) {
		$soundList = new CComboBox('messages[sounds.'.$severity.']', $this->data['messages']['sounds.'.$severity]);
		foreach ($zbxSounds as $filename => $file) {
			$soundList->addItem($file, $filename);
		}

		$triggersTable->addRow(array(
			new CCheckBox('messages[triggers.severities]['.$severity.']', isset($this->data['messages']['triggers.severities'][$severity]), null, 1),
			getSeverityCaption($severity),
			$soundList,
			new CButton('start', _('Play'), "javascript: testUserSound('messages[sounds.".$severity."]');"),
			new CButton('stop', _('Stop'), 'javascript: AudioList.stopAll();')
		));

		zbx_subarray_push($msgVisibility, 1, 'messages[triggers.severities]['.$severity.']');
		zbx_subarray_push($msgVisibility, 1, 'messages[sounds.'.$severity.']');
	}

	$userMessagingFormList->addRow(_('Trigger severity'), $triggersTable, false, 'triggers_row');

	zbx_add_post_js("jQuery('#messages_enabled').bind('click',function() {
						if (this.checked
								&& !jQuery(\"input[id='messages_triggers.recovery']\").is(':checked')
								&& !jQuery(\"input[id='messages_triggers.severities_0']\").is(':checked')
								&& !jQuery(\"input[id='messages_triggers.severities_1']\").is(':checked')
								&& !jQuery(\"input[id='messages_triggers.severities_2']\").is(':checked')
								&& !jQuery(\"input[id='messages_triggers.severities_3']\").is(':checked')
								&& !jQuery(\"input[id='messages_triggers.severities_4']\").is(':checked')
								&& !jQuery(\"input[id='messages_triggers.severities_5']\").is(':checked')) {
							jQuery(\"input[id='messages_triggers.recovery']\").attr('checked', true);
							jQuery(\"input[id='messages_triggers.severities_0']\").attr('checked', true);
							jQuery(\"input[id='messages_triggers.severities_1']\").attr('checked', true);
							jQuery(\"input[id='messages_triggers.severities_2']\").attr('checked', true);
							jQuery(\"input[id='messages_triggers.severities_3']\").attr('checked', true);
							jQuery(\"input[id='messages_triggers.severities_4']\").attr('checked', true);
							jQuery(\"input[id='messages_triggers.severities_5']\").attr('checked', true);
						}

						// enable/disable childs fields
						if (this.checked) {
							jQuery('#messagingTab input, #messagingTab select').removeAttr('disabled');
						}
						else {
							jQuery('#messagingTab input, #messagingTab select').attr('disabled', 'disabled');
							jQuery('#messages_enabled').removeAttr('disabled');
						}
					});

					// initial state: enable/disable childs fields
					if (jQuery('#messages_enabled').is(':checked')) {
						jQuery('#messagingTab input, #messagingTab select').removeAttr('disabled');
					}
					else {
						jQuery('#messagingTab input, #messagingTab select').attr('disabled', 'disabled');
						jQuery('#messages_enabled').removeAttr('disabled');
					}");
}

// append form lists to tab
$userTab = new CTabView(array('remember' => 1));
$userTab->addTab('userTab', _('User'), $userFormList);
if (!$this->data['form_refresh']) {
	$userTab->setSelected(0);
}
if (isset($userMediaFormList)) {
	$userTab->addTab('mediaTab', _('Media'), $userMediaFormList);
}
if (!$this->data['is_profile']) {
	/*
	 * Permissions tab
	 */
	$permissionsFormList = new CFormList('permissionsFormList');

	$userTypeComboBox = new CComboBox('user_type', $this->data['user_type'], 'submit();');
	$userTypeComboBox->addItem(USER_TYPE_ZABBIX_USER, user_type2str(USER_TYPE_ZABBIX_USER));
	$userTypeComboBox->addItem(USER_TYPE_ZABBIX_ADMIN, user_type2str(USER_TYPE_ZABBIX_ADMIN));
	$userTypeComboBox->addItem(USER_TYPE_SUPER_ADMIN, user_type2str(USER_TYPE_SUPER_ADMIN));
	if (isset($this->data['userid']) && bccomp($USER_DETAILS['userid'], $this->data['userid']) == 0) {
		$userTypeComboBox->setEnabled('disabled');
		$permissionsFormList->addRow(_('User type'), array($userTypeComboBox, SPACE, new CSpan(_('User can\'t change type for himself'))));
		$userForm->addVar('user_type', $this->data['user_type']);
	}
	else {
		$permissionsFormList->addRow(_('User type'), $userTypeComboBox);
	}
	$permissionsFormList = getPermissionsFormList($this->data['user_rights'], $this->data['user_type'], $permissionsFormList);
	$permissionsFormList->addInfo(_('Permissions can be assigned for user groups only.'));
	$userTab->addTab('permissionsTab', _('Permissions'), $permissionsFormList);
}
if (isset($userMessagingFormList)) {
	$userTab->addTab('messagingTab', _('Messaging'), $userMessagingFormList);
}

// append tab to form
$userForm->addItem($userTab);

// append buttons to form
if (empty($this->data['userid'])) {
	$userForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), array(new CButtonCancel(url_param('config')))));
}
else {
	if (!$this->data['is_profile']) {
		$deleteButton = new CButtonDelete(_('Delete selected user?'), url_param('form').url_param('userid').url_param('config'));
		if (bccomp($USER_DETAILS['userid'], $this->data['userid']) == 0) {
			$deleteButton->setAttribute('disabled', 'disabled');
		}
		$userForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), array($deleteButton, new CButtonCancel(url_param('config')))));
	}
	else {
		$userForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), array(new CButtonCancel(url_param('config')))));
	}
}

// append form to widget
$userWidget->addItem($userForm);

return $userWidget;
?>
