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
require_once('include/maps.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_NETWORK_MAPS';
$page['file'] = 'sysmap.php';
$page['hist_arg'] = array('sysmapid');
$page['scripts'] = array('class.cmap.js', 'class.cviewswitcher.js');
$page['type'] = detect_page_type();

require_once('include/page_header.php');
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'sysmapid'=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,NULL),
		'selementid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		NULL),
		'sysmap'=>		array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,	'isset({save})'),

// actions
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

// other
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),

// ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,	NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  null,	NULL),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,	null),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	NOT_EMPTY,		NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("hat"=={favobj})'),

		'selements'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID, NULL),
		'links'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID, NULL),
	);

	check_fields($fields);

?>
<?php
// ACTION /////////////////////////////////////////////////////////////////////////////
	if (isset($_REQUEST['favobj'])) {
		$json = new CJSON();
		if ('sysmap' == $_REQUEST['favobj']) {
			$sysmapid = get_request('sysmapid', 0);

			switch ($_REQUEST['action']) {
				case 'save':
					@ob_start();
					try {
						DBstart();

						$options = array(
							'sysmapids' => $sysmapid,
							'editable' => true,
							'output' => API_OUTPUT_SHORTEN,
						);
						$sysmap = API::Map()->get($options);
						$sysmap = reset($sysmap);
						if ($sysmap === false) throw new Exception(_('Access denied!')."\n\r");

						$sysmapUpdate = $json->decode($_REQUEST['sysmap'], true);
						$sysmapUpdate['sysmapid'] = $sysmapid;

						$result = API::Map()->update($sysmapUpdate);

						if ($result !== false)
							print('if(Confirm("'._('Map is saved! Return?').'")){ location.href = "sysmaps.php"; }');
						else
							throw new Exception(_('Map save operation failed.')."\n\r");

						DBend(true);
					}
					catch (Exception $e) {
						DBend(false);
						$msg = array($e->getMessage());
						foreach (clear_messages() as $errMsg) $msg[] = $errMsg['type'].': '.$errMsg['message'];

						ob_clean();

						print('alert('.zbx_jsvalue(implode("\n\r", $msg)).');');
					}
					@ob_flush();
					exit();
					break;
			}
		}
	}

	if (PAGE_TYPE_HTML != $page['type']) {
		require_once('include/page_footer.php');
		exit();
	}

// include JS + templates
include('include/views/js/configuration.sysmaps.js.php');

?>
<?php

show_table_header(_('CONFIGURATION OF NETWORK MAPS'));

if (isset($_REQUEST['sysmapid'])) {
	$options = array(
		'sysmapids' => $_REQUEST['sysmapid'],
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'selectSelements' => API_OUTPUT_EXTEND,
		'selectLinks' => API_OUTPUT_EXTEND,
		'preservekeys' => true,
	);
	$maps = API::Map()->get($options);

	if (empty($maps)) access_deny();
	else $sysmap = reset($maps);
}

echo SBR;

// ELEMENTS
$el_add = new CIcon(_('Add element'), 'iconplus');
$el_add->setAttribute('id', 'selementAdd');

$el_rmv = new CIcon(_('Remove element'), 'iconminus');
$el_rmv->setAttribute('id', 'selementRemove');
//-----------------

// CONNECTORS
$cn_add = new CIcon(_('Add link'), 'iconplus');
$cn_add->setAttribute('id', 'linkAdd');

$cn_rmv = new CIcon(_('Remove link'), 'iconminus');
$cn_rmv->setAttribute('id', 'linkRemove');
//------------------------


$gridShow = new CSpan(
	$sysmap['grid_show'] == SYSMAP_GRID_SHOW_ON ? _('Shown') : _('Hidden'),
	'whitelink'
);
$gridShow->setAttribute('id', 'gridshow');

$gridAutoAlign = new CSpan(
	$sysmap['grid_align'] == SYSMAP_GRID_ALIGN_ON ? _('On') : _('Off'),
	'whitelink'
);
$gridAutoAlign->setAttribute('id', 'gridautoalign');

$possibleGridSizes = array(
	20 => '20x20',
	40 => '40x40',
	50 => '50x50',
	75 => '75x75',
	100 => '100x100'
);
$gridSize = new CComboBox('gridsize', $sysmap['grid_size']);
$gridSize->addItems($possibleGridSizes);

$gridAlignAll = new CSubmit('gridalignall', _('Align icons'));
$gridAlignAll->setAttribute('id', 'gridalignall');

$gridForm = new CDiv(array($gridSize, $gridAlignAll));
$gridForm->setAttribute('id', 'gridalignblock');

$save_btn = new CSubmit('save', _('Save'));
$save_btn->setAttribute('id', 'sysmap_save');

$menuRow = array(
	_s('Map "%s"', $sysmap['name']),
	SPACE.SPACE,
	_('Icon').' [', $el_add, $el_rmv, ']',
	SPACE.SPACE,
	_('Link').' [',$cn_add,$cn_rmv,']',
	SPACE.SPACE,
	_('Grid').' [', $gridShow, '|', $gridAutoAlign, ']',
	SPACE,
	$gridForm,
	SPACE.'|'.SPACE,
	$save_btn,
);

$elcn_tab = new CTable(null, 'textwhite');
$elcn_tab->addRow($menuRow);

show_table_header($elcn_tab);


$sysmap_img = new CImg('images/general/tree/zero.gif', 'Sysmap');
$sysmap_img->setAttribute('id', 'sysmap_img', $sysmap['width'], $sysmap['height']);

$table = new CTable();
$table->addRow($sysmap_img);
$table->Show();

$container = new CDiv();
$container->setAttribute('id', 'sysmap_cnt');
$container->Show();


insert_show_color_picker_javascript();


add_elementNames($sysmap['selements']);

foreach ($sysmap['links'] as &$link) {
	foreach ($link['linktriggers'] as $lnum => $linktrigger) {
		$hosts = get_hosts_by_triggerid($linktrigger['triggerid']);
		if ($host = DBfetch($hosts)) {
			$description = $host['name'].':'.expand_trigger_description($linktrigger['triggerid']);
		}
		$link['linktriggers'][$lnum]['desc_exp'] = $description;
	}
	order_result($link['linktriggers'], 'desc_exp');
}
unset($link);


$iconList = array();
$result = DBselect('SELECT imageid, name FROM images WHERE imagetype=1 AND '.DBin_node('imageid'));
while($row = DBfetch($result)){
	$iconList[] = array('imageid' => $row['imageid'], 'name' => $row['name']);
}

if ($sysmap['iconmapid']) {
	$iconMaps = API::IconMap()->get(array(
		'iconmapids' => $sysmap['iconmapid'],
		'output' => array('default_iconid'),
		'preservekeys' => true,
	));
	$iconMap = reset($iconMaps);
	$defaultAutoIconId = $iconMap['default_iconid'];
}
else {
	$defaultAutoIconId = null;
}

$iconList = array();
$result = DBselect('SELECT i.imageid, i.name FROM images i WHERE i.imagetype='.IMAGE_TYPE_ICON.' AND '.DBin_node('i.imageid'));
while ($row = DBfetch($result)) {
	$iconList[] = array('imageid' => $row['imageid'], 'name' => $row['name']);
}

zbx_add_post_js('ZABBIX.apps.map.run("sysmap_cnt", '.zbx_jsvalue(array(
			'sysmap' => $sysmap, 'iconList' => $iconList, 'defaultAutoIconId' => $defaultAutoIconId), true).');'
);

require_once('include/page_footer.php');
?>
