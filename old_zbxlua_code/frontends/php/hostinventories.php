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
require_once('include/hosts.inc.php');
require_once('include/forms.inc.php');

$page['title'] = _('Host inventories');
$page['file'] = 'hostinventories.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
	'groupid' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
	'hostid' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT,	P_ACT,	null,	null),
	'filter_field'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
	'filter_field_value'=>	array(T_ZBX_STR, O_OPT,  null,	null,	null),
	'filter_exact'=>        array(T_ZBX_INT, O_OPT,  null,	'IN(0,1)',	null),
	//ajax
	'favobj'=>			array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
	'favref'=>			array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	'state'=>			array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})')
);

check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

if(isset($_REQUEST['favobj'])){
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.hostinventories.filter.state', $_REQUEST['state'], PROFILE_TYPE_INT);
	}
}

if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
	require_once('include/page_footer.php');
	exit();
}
?>
<?php

$options = array(
	'groups' => array(
		'real_hosts' => 1,
	),
	'groupid' => get_request('groupid', null),
);
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;

$_REQUEST['hostid'] = get_request('hostid', 0);
// permission check, imo should be removed in future.
if($_REQUEST['hostid'] > 0){
	$res = API::Host()->get(array(
		'real_hosts' => 1,
		'hostids' => $_REQUEST['hostid']
	));
	if(empty($res)) access_deny();
}

$hostinvent_wdgt = new CWidget();
$hostinvent_wdgt->addPageHeader(_('HOST INVENTORIES'));

// host details
if($_REQUEST['hostid'] > 0){
	$hostinvent_wdgt->addItem(insert_host_inventory_form());
}
// list of hosts
else{
	$r_form = new CForm('get');
	$r_form->addItem(array(_('Group'), $pageFilter->getGroupsCB(true)));
	$hostinvent_wdgt->addHeader(_('HOSTS'), $r_form);

	// HOST INVENTORY FILTER {{{
	if(isset($_REQUEST['filter_set'])){
		$_REQUEST['filter_field'] = get_request('filter_field');
		$_REQUEST['filter_field_value'] = get_request('filter_field_value');
		$_REQUEST['filter_exact'] = get_request('filter_exact');
		CProfile::update('web.hostinventories.filter_field', $_REQUEST['filter_field'], PROFILE_TYPE_STR);
		CProfile::update('web.hostinventories.filter_field_value', $_REQUEST['filter_field_value'], PROFILE_TYPE_STR);
		CProfile::update('web.hostinventories.filter_exact', $_REQUEST['filter_exact'], PROFILE_TYPE_INT);
	}
	else{
		$_REQUEST['filter_field'] = CProfile::get('web.hostinventories.filter_field');
		$_REQUEST['filter_field_value'] = CProfile::get('web.hostinventories.filter_field_value');
		$_REQUEST['filter_exact'] = CProfile::get('web.hostinventories.filter_exact');
	}

	$filter_table = new CTable('', 'filter_config');
	// getting inventory fields to make a drop down
	$inventoryFields = getHostInventories(true); // 'true' means list should be ordered by title
	$inventoryFieldsComboBox = new CComboBox('filter_field', $_REQUEST['filter_field']);
	foreach($inventoryFields as $inventoryField){
		$inventoryFieldsComboBox->addItem(
			$inventoryField['db_field'],
			$inventoryField['title']
		);
	}
	$exactComboBox = new CComboBox('filter_exact', $_REQUEST['filter_exact']);
	$exactComboBox->addItem('0', _('like'));
	$exactComboBox->addItem('1', _('exactly'));
	$filter_table->addRow(array(
		array(
			array(bold(_('Field:')), $inventoryFieldsComboBox),
			array(
				$exactComboBox,
				new CTextBox('filter_field_value', $_REQUEST['filter_field_value'], 20)
			),
		),
	));

	$reset = new CSpan(S_RESET,'link_menu');
	$reset->onClick("javascript: clearAllForm('zbx_filter');");

	$filter = new CButton('filter', S_FILTER, "javascript: create_var('zbx_filter', 'filter_set', '1', true);");
	$filter->useJQueryStyle();

	$footer_col = new CCol(array($filter, SPACE, SPACE, SPACE, $reset), 'center');
	$footer_col->setColSpan(4);

	$filter_table->addRow($footer_col);

	$filter_form = new CForm('get');
	$filter_form->setAttribute('name','zbx_filter');
	$filter_form->setAttribute('id','zbx_filter');
	$filter_form->addItem($filter_table);
	$hostinvent_wdgt->addFlicker($filter_form, CProfile::get('web.hostinventories.filter.state', 0));
	// }}} HOST INVENTORY FILTER

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');
	$hostinvent_wdgt->addHeader($numrows);

	$table = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? make_sorting_header(_('Node'), 'hostid') : null,
		make_sorting_header(_('Host'), 'name'),
		_('Group'),
		make_sorting_header(_('Name'), 'pr_name'),
		make_sorting_header(_('Type'), 'pr_type'),
		make_sorting_header(_('OS'), 'pr_os'),
		make_sorting_header(_('Serial number A'), 'pr_serialno_a'),
		make_sorting_header(_('Tag'), 'pr_tag'),
		make_sorting_header(_('MAC address A'), 'pr_macaddress_a'))
	);

	if($pageFilter->groupsSelected){
		// which inventory fields we will need for displaying
		$requiredInventoryFields = array(
			'name',
			'type',
			'os',
			'serialno_a',
			'tag',
			'macaddress_a'
		);

		// checking if correct inventory field is specified for filter
		$possibleInventoryFields = getHostInventories();
		$possibleInventoryFields = zbx_toHash($possibleInventoryFields, 'db_field');
		if(!empty($_REQUEST['filter_field']) && !empty($_REQUEST['filter_field_value']) && !isset($possibleInventoryFields[$_REQUEST['filter_field']])){
			error(_s('Impossible to filter by inventory field "%s", which does not exist.', $_REQUEST['filter_field']));
			$hosts = array();
			$paging = getPagingLine($hosts);
		}
		else{
			// if we are filtering by field, this field is also required
			if(!empty($_REQUEST['filter_field']) && !empty($_REQUEST['filter_field_value'])){
				$requiredInventoryFields[] = $_REQUEST['filter_field'];
			}

			$options = array(
				'output' => array('hostid', 'name'),
				'selectInventory' => $requiredInventoryFields,
				'withInventory' => true,
				'selectGroups' => API_OUTPUT_EXTEND,
				'limit' => ($config['search_limit'] + 1)
			);
			if($pageFilter->groupid > 0)
				$options['groupids'] = $pageFilter->groupid;

			$hosts = API::Host()->get($options);

			// copy some inventory fields to the uppers array level for sorting
			// and filter out hosts if we are using filter
			foreach($hosts as $num => $host){
				$hosts[$num]['pr_name'] = $host['inventory']['name'];
				$hosts[$num]['pr_type'] = $host['inventory']['type'];
				$hosts[$num]['pr_os'] = $host['inventory']['os'];
				$hosts[$num]['pr_serialno_a'] = $host['inventory']['serialno_a'];
				$hosts[$num]['pr_tag'] = $host['inventory']['tag'];
				$hosts[$num]['pr_macaddress_a'] = $host['inventory']['macaddress_a'];
				// if we are filtering by inventory field
				if(!empty($_REQUEST['filter_field']) && !empty($_REQUEST['filter_field_value'])){
					// must we filter exactly or using a substring (both are case insensitive)
					$match = $_REQUEST['filter_exact']
							? zbx_strtolower($hosts[$num]['inventory'][$_REQUEST['filter_field']]) === zbx_strtolower($_REQUEST['filter_field_value'])
							: zbx_strpos(
								zbx_strtolower($hosts[$num]['inventory'][$_REQUEST['filter_field']]),
								zbx_strtolower($_REQUEST['filter_field_value'])
							) !== false;
					if(!$match){
						unset($hosts[$num]);
					}
				}
			}

			order_result($hosts, getPageSortField('name'), getPageSortOrder());
			$paging = getPagingLine($hosts);

			foreach($hosts as $host){
				$host_groups = array();
				foreach($host['groups'] as $group){
					$host_groups[] = $group['name'];
				}
				natsort($host_groups);
				$host_groups = implode(', ', $host_groups);

				$row = array(
					get_node_name_by_elid($host['hostid']),
					new CLink($host['name'],'?hostid='.$host['hostid'].url_param('groupid')),
					$host_groups,
					zbx_str2links($host['inventory']['name']),
					zbx_str2links($host['inventory']['type']),
					zbx_str2links($host['inventory']['os']),
					zbx_str2links($host['inventory']['serialno_a']),
					zbx_str2links($host['inventory']['tag']),
					zbx_str2links($host['inventory']['macaddress_a']),
				);

				$table->addRow($row);
			}
		}
	}

	$table = array($paging, $table, $paging);
	$hostinvent_wdgt->addItem($table);
}

$hostinvent_wdgt->show();

require_once('include/page_footer.php');
?>
