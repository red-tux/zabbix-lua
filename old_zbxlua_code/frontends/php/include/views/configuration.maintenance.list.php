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
zbx_add_post_js('chkbxRange.pageGoName = "maintenanceids";');
$maintenanceWidget = new CWidget();

// create new maintenance button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addItem(new CSubmit('form', _('Create maintenance period')));
$maintenanceWidget->addPageHeader(_('CONFIGURATION OF MAINTENANCE PERIODS'), $createForm);

// header
$filterForm = new CForm('get');
$filterForm->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()));
$numRows = new CDiv();
$numRows->setAttribute('name', 'numrows');
$maintenanceWidget->addHeader(_('Maintenance periods'), $filterForm);
$maintenanceWidget->addHeader($numRows);

// create form
$maintenanceForm = new CForm();
$maintenanceForm->setName('maintenanceForm');

// create table
$maintenanceTable = new CTableInfo(_('No maintenance defined'));
$maintenanceTable->setHeader(array(
	new CCheckBox('all_maintenances', null, "checkAll('".$maintenanceForm->getName()."', 'all_maintenances', 'maintenanceids');"),
	make_sorting_header(_('Name'), 'name'),
	make_sorting_header(_('Type'), 'maintenance_type'),
	_('State'),
	_('Description')
));

foreach ($this->data['maintenances'] as $maintenance) {
	$maintenanceid = $maintenance['maintenanceid'];

	switch ($maintenance['status']) {
		case MAINTENANCE_STATUS_EXPIRED:
			$maintenanceStatus = new CSpan(_('Expired'), 'red');
			break;
		case MAINTENANCE_STATUS_APPROACH:
			$maintenanceStatus = new CSpan(_('Approaching'), 'blue');
			break;
		case MAINTENANCE_STATUS_ACTIVE:
			$maintenanceStatus = new CSpan(_('Active'), 'green');
			break;
	}

	$maintenanceTable->addRow(array(
		new CCheckBox('maintenanceids['.$maintenanceid.']', null, null, $maintenanceid),
		new CLink($maintenance['name'], 'maintenance.php?form=update&maintenanceid='.$maintenanceid.'#form'),
		$maintenance['maintenance_type'] ? _('No data collection') : _('With data collection'),
		$maintenanceStatus,
		$maintenance['description']
	));
}

// create go button
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected maintenance periods?'));
$goComboBox->addItem($goOption);
$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');

// append table to form
$maintenanceForm->addItem(array($this->data['paging'], $maintenanceTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$maintenanceWidget->addItem($maintenanceForm);

return $maintenanceWidget;
?>
