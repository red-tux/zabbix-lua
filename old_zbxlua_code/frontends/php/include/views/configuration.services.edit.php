<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
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
include('include/views/js/configuration.services.edit.js.php');
global $ZBX_MESSAGES;

$servicesWidget = new CWidget();
$servicesWidget->addPageHeader(_('CONFIGURATION OF IT SERVICES'));

// create form
$servicesForm = new CForm();
$servicesForm->setName('servicesForm');
$servicesForm->addVar('form', $this->data['form']);
$servicesForm->addVar('parentid', $this->data['parentid']);
$servicesForm->addVar('parentname', $this->data['parentname']);
$servicesForm->addVar('triggerid', $this->data['triggerid']);
if (isset($this->data['service'])) {
	$servicesForm->addVar('serviceid', $this->data['service']['serviceid']);
}

// create form list
$servicesFormList = new CFormList('servicesFormList');
$servicesFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 128));

// append parent link to form list
$servicesFormList->addRow(_('Parent service'), array(
	new CTextBox('parent_name', $this->data['parentname'], ZBX_TEXTBOX_STANDARD_SIZE, 'yes', 128),
	new CButton('select_parent', _('Change'), "javascript: openWinCentered('services.php?pservices=1".url_param('serviceid')."', 'ZBX_Services_List', 740, 420, 'scrollbars=1, toolbar=0, menubar=0, resizable=1, dialog=0');", 'formlist')
));

// append algorithm to form list
$algorithmComboBox = new CComboBox('algorithm', $this->data['algorithm']);
foreach (array(SERVICE_ALGORITHM_MAX, SERVICE_ALGORITHM_MIN, SERVICE_ALGORITHM_NONE) as $value) {
	$algorithmComboBox->addItem($value, algorithm2str($value));
}
$servicesFormList->addRow(_('Status calculation algorithm'), $algorithmComboBox);

// append SLA to form list
$showslaCheckbox = new CCheckBox('showsla', $this->data['showsla'], ($this->data['showsla'] == 0) ? 'no' : 'yes', 1);
$goodslaTextBox = new CTextBox('goodsla', $this->data['goodsla'], 6, 'no', 8);
if (!$this->data['showsla']) {
	$goodslaTextBox->setAttribute('disabled', 'disabled');
}
$servicesFormList->addRow(_('Calculate SLA, acceptable SLA (in %)'), array($showslaCheckbox, $goodslaTextBox));

// append trigger to form list
$servicesFormList->addRow(_('Trigger'), array(
	new CTextBox('trigger', $this->data['trigger'], ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
	new CButton('btn1', _('Select'), "return PopUp('popup.php?"."dstfrm=".$servicesForm->getName()."&dstfld1=triggerid&dstfld2=trigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description&real_hosts=1');", 'formlist')
));
$servicesFormList->addRow(_('Sort order (0->999)'), new CTextBox('sortorder', $this->data['sortorder'], 3, 'no', 3));

/*
 * Dependencies tab
 */
$servicesChildTable = new CTable(_('No dependencies defined.'), 'formElementTable');
$servicesChildTable->setAttribute('style', 'min-width:500px;');
$servicesChildTable->setAttribute('id', 'service_childs');
$servicesChildTable->setHeader(array(_('Services'), _('Soft'), _('Trigger'), _('Action')));
foreach ($this->data['childs'] as $child) {
	$row = new CRow(array(
		array(
			new CLink($child['name'], 'services.php?form=1&serviceid='.$child['serviceid']),
			new CVar('childs['.$child['serviceid'].'][name]', $child['name']),
			new CVar('childs['.$child['serviceid'].'][serviceid]', $child['serviceid']),
			new CVar('childs['.$child['serviceid'].'][triggerid]', isset($child['triggerid']) ? $child['triggerid'] : '')
		),
		new CCheckBox(
			'childs['.$child['serviceid'].'][soft]',
			isset($child['soft']) && !empty($child['soft']) ? 'checked' : 'no',
			null,
			1
		),
		!empty($child['trigger']) ? $child['trigger'] : '-',
		new CButton('remove', _('Remove'), 'javascript: removeDependentChild(\''.$child['serviceid'].'\');', 'link_menu')
	));
	$row->setAttribute('id', 'childs_'.$child['serviceid']);
	$servicesChildTable->addRow($row);
}
$servicesDependenciesFormList = new CFormList('servicesDependensiesFormList');
$servicesDependenciesFormList->addRow(
	_('Depends on'),
	new CDiv(array(
		$servicesChildTable,
		new CButton('add_child_service', _('Add'), "javascript: openWinCentered('services.php?cservices=1".url_param('serviceid')."', 'ZBX_Services_List', 640, 520, 'scrollbars=1, toolbar=0, menubar=0, resizable=0');", 'link_menu'),
	),
	'objectgroup inlineblock border_dotted ui-corner-all')
);

/*
 * Service times tab
 */
$servicesTimeFormList = new CFormList('servicesTimeFormList');
$servicesTimeTable = new CTable(_('No times defined. Work 24x7.'), 'formElementTable');
$servicesTimeTable->setAttribute('style', 'min-width:500px;');
$servicesTimeTable->setHeader(array(_('Type'), _('Interval'), _('Note'), _('Action')));

$i = 0;
foreach ($this->data['service_times'] as $serviceTime) {
	switch ($serviceTime['type']) {
		case SERVICE_TIME_TYPE_UPTIME:
			$type = new CSpan(_('Uptime'), 'enabled');
			$from = dowHrMinToStr($serviceTime['from']);
			$to = dowHrMinToStr($serviceTime['to'], true);
			break;
		case SERVICE_TIME_TYPE_DOWNTIME:
			$type = new CSpan(_('Downtime'), 'disabled');
			$from = dowHrMinToStr($serviceTime['from']);
			$to = dowHrMinToStr($serviceTime['to'], true);
			break;
		case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
			$type = new CSpan(_('One-time downtime'), 'disabled');
			$from = zbx_date2str(_('d M Y H:i'), $serviceTime['from']);
			$to = zbx_date2str(_('d M Y H:i'), $serviceTime['to']);
			break;
	}
	$row = new CRow(array(
		array(
			$type,
			new CVar('service_times['.$i.'][type]', $serviceTime['type']),
			new CVar('service_times['.$i.'][from]', $serviceTime['from']),
			new CVar('service_times['.$i.'][to]', $serviceTime['to']),
			new CVar('service_times['.$i.'][note]', $serviceTime['note'])
		),
		$from.' - '.$to,
		htmlspecialchars($serviceTime['note']),
		new CButton('remove', _('Remove'), 'javascript: removeTime(\''.$i.'\');', 'link_menu')
	));
	$row->setAttribute('id', 'service_times_'.$i);
	$servicesTimeTable->addRow($row);
	$i++;
}
$servicesTimeFormList->addRow(
	_('Service times'),
	new CDiv($servicesTimeTable, 'objectgroup inlineblock border_dotted ui-corner-all')
);

// create service time table
$serviceTimeTable = new CTable(null, 'formElementTable');
if ($this->data['new_service_time']['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
	$downtimeSince = date('YmdHis');
	$downtimeTill = date('YmdHis', time() + 86400);

	$servicesForm->addVar('new_service_time[from]', $downtimeSince);
	$servicesForm->addVar('new_service_time[to]', $downtimeTill);

	$downtimeSince = zbxDateToTime($downtimeSince);
	$downtimeTill = zbxDateToTime($downtimeTill);

	// create calendar table
	$timeCalendarTable = new CTable();

	$calendarIcon = new CImg('images/general/bar/cal.gif', 'calendar', 16, 12, 'pointer');
	$calendarIcon->addAction('onclick', "javascript: var pos = getPosition(this); pos.top -= 203; pos.left += 16; CLNDR['downtime_since'].clndr.clndrshow(pos.top, pos.left); CLNDR['downtime_till'].clndr.clndrhide();");

	// downtime since
	$noteTextBox = new CTextBox('new_service_time[note]', '', ZBX_TEXTBOX_STANDARD_SIZE);
	$noteTextBox->setAttribute('placeholder', _('short description'));
	$downtimeSinceDay = new CNumericBox('downtime_since_day', $downtimeSince > 0 ? date('d', $downtimeSince) : '', 2);
	$downtimeSinceDay->setAttribute('placeholder', _('dd'));
	$downtimeSinceMonth = new CNumericBox('downtime_since_month', $downtimeSince > 0 ? date('m', $downtimeSince) : '', 2);
	$downtimeSinceMonth->setAttribute('placeholder', _('mm'));
	$downtimeSinceYear = new CNumericBox('downtime_since_year', $downtimeSince > 0 ? date('Y', $downtimeSince) : '', 4);
	$downtimeSinceYear->setAttribute('placeholder', _('yyyy'));
	$downtimeSinceHour = new CNumericBox('downtime_since_hour', $downtimeSince > 0 ? date('H', $downtimeSince) : '', 2);
	$downtimeSinceHour->setAttribute('placeholder', _('hh'));
	$downtimeSinceMinute = new CNumericBox('downtime_since_minute', $downtimeSince > 0 ? date('i', $downtimeSince) : '', 2);
	$downtimeSinceMinute->setAttribute('placeholder', _('mm'));

	$timeCalendarTable->addRow(array(_('Note'), $noteTextBox));
	$timeCalendarTable->addRow(array(_('From'), new CCol(array($downtimeSinceDay, '/', $downtimeSinceMonth, '/', $downtimeSinceYear, SPACE, $downtimeSinceHour, ':', $downtimeSinceMinute, $calendarIcon))));
	zbx_add_post_js('create_calendar(null, ["downtime_since_day", "downtime_since_month", "downtime_since_year", "downtime_since_hour", "downtime_since_minute"], "downtime_since", "new_service_time_from");');

	// downtime till
	$calendarIcon->addAction('onclick', "javascript: var pos = getPosition(this); pos.top -= 203; pos.left += 16; CLNDR['downtime_till'].clndr.clndrshow(pos.top, pos.left); CLNDR['downtime_since'].clndr.clndrhide();");
	$downtimeTillDay = new CNumericBox('downtime_till_day', $downtimeTill > 0 ? date('d', $downtimeTill) : '', 2);
	$downtimeTillDay->setAttribute('placeholder', _('dd'));
	$downtimeTillMonth = new CNumericBox('downtime_till_month', $downtimeTill > 0 ? date('m', $downtimeTill) : '', 2);
	$downtimeTillMonth->setAttribute('placeholder', _('mm'));
	$downtimeTillYear = new CNumericBox('downtime_till_year', $downtimeTill > 0 ? date('Y', $downtimeTill) : '', 4);
	$downtimeTillYear->setAttribute('placeholder', _('yyyy'));
	$downtimeTillHour = new CNumericBox('downtime_till_hour', $downtimeTill > 0 ? date('H', $downtimeTill) : '', 2);
	$downtimeTillHour->setAttribute('placeholder', _('hh'));
	$downtimeTillMinute = new CNumericBox('downtime_till_minute', $downtimeTill > 0 ? date('i', $downtimeTill) : '', 2);
	$downtimeTillMinute->setAttribute('placeholder', _('mm'));

	$timeCalendarTable->addRow(array(_('Till'), new CCol(array($downtimeTillDay, '/', $downtimeTillMonth, '/', $downtimeTillYear, SPACE, $downtimeTillHour, ':', $downtimeTillMinute, $calendarIcon))));
	zbx_add_post_js('create_calendar(null, ["downtime_till_day", "downtime_till_month", "downtime_till_year", "downtime_till_hour", "downtime_till_minute"], "downtime_till", "new_service_time_to");');

	$serviceTimeTable->addRow($timeCalendarTable);
}
else {
	$weekFromComboBox = new CComboBox('new_service_time[from_week]', !empty($ZBX_MESSAGES) ? $_REQUEST['new_service_time']['from_week'] : 'Sunday');
	$weekToComboBox = new CComboBox('new_service_time[to_week]', !empty($ZBX_MESSAGES) ? $_REQUEST['new_service_time']['to_week'] : 'Sunday');
	for ($dow = 0; $dow < 7; $dow++) {
		$weekFromComboBox->addItem($dow, getDayOfWeekCaption($dow));
		$weekToComboBox->addItem($dow, getDayOfWeekCaption($dow));
	}
	$timeFromHourTextBox = new CTextBox('new_service_time[from_hour]', !empty($ZBX_MESSAGES) ? $_REQUEST['new_service_time']['from_hour'] : '', 2, 'no', 2);
	$timeFromHourTextBox->setAttribute('placeholder', _('hh'));
	$timeFromMinuteTextBox = new CTextBox('new_service_time[from_minute]', !empty($ZBX_MESSAGES) ? $_REQUEST['new_service_time']['from_minute'] : '', 2, 'no', 2);
	$timeFromMinuteTextBox->setAttribute('placeholder', _('mm'));
	$timeToHourTextBox = new CTextBox('new_service_time[to_hour]', !empty($ZBX_MESSAGES) ? $_REQUEST['new_service_time']['to_hour'] : '', 2, 'no', 2);
	$timeToHourTextBox->setAttribute('placeholder', _('hh'));
	$timeToMinuteTextBox = new CTextBox('new_service_time[to_minute]', !empty($ZBX_MESSAGES) ? $_REQUEST['new_service_time']['to_minute'] : '', 2, 'no', 2);
	$timeToMinuteTextBox->setAttribute('placeholder', _('mm'));

	$serviceTimeTable->addRow(array(_('From'), $weekFromComboBox, new CCol(array(_('Time'), SPACE, $timeFromHourTextBox, ' : ', $timeFromMinuteTextBox))));
	$serviceTimeTable->addRow(array(_('Till'), $weekToComboBox, new CCol(array(_('Time'), SPACE, $timeToHourTextBox, ' : ', $timeToMinuteTextBox))));
	$servicesForm->addVar('new_service_time[note]', '');
}

$timeTypeComboBox = new CComboBox('new_service_time[type]', $this->data['new_service_time']['type'], 'javascript: document.forms[0].action += \'?form=1\'; submit();');
$timeTypeComboBox->addItem(SERVICE_TIME_TYPE_UPTIME, _('Uptime'));
$timeTypeComboBox->addItem(SERVICE_TIME_TYPE_DOWNTIME, _('Downtime'));
$timeTypeComboBox->addItem(SERVICE_TIME_TYPE_ONETIME_DOWNTIME, _('One-time downtime'));
$servicesTimeFormList->addRow(
	_('New service time'),
	new CDiv(array(
		$timeTypeComboBox,
		BR(),
		$serviceTimeTable,
		new CButton('add_service_time', _('Add'), null, 'link_menu')
	),
	'objectgroup inlineblock border_dotted ui-corner-all')
);

/*
 * Append tabs to form
 */
$servicesTab = new CTabView(array('remember' => true));
if (!$this->data['form_refresh']) {
	$servicesTab->setSelected(0);
}
$servicesTab->addTab('servicesTab', _('Service'), $servicesFormList);
$servicesTab->addTab('servicesDependenciesTab', _('Dependencies'), $servicesDependenciesFormList);
$servicesTab->addTab('servicesTimeTab', _('Time'), $servicesTimeFormList);
$servicesForm->addItem($servicesTab);

// append buttons to form
if (!empty($this->data['service']['serviceid'])) {
	$servicesForm->addItem(makeFormFooter(
		array(new CSubmit('save_service', _('Save'), 'javascript: document.forms[0].action += \'?saction=1\';')),
		array(
			new CButtonDelete('Delete selected service?', url_param('form').url_param('serviceid').'&saction=1'),
			new CButtonCancel()
		)
	));
}
else {
	$servicesForm->addItem(makeFormFooter(
		array(new CSubmit('save_service', _('Save'), 'javascript: document.forms[0].action += \'?saction=1\';')),
		array(new CButtonCancel())
	));
}

// append form to widget
$servicesWidget->addItem($servicesForm);
return $servicesWidget;
?>
