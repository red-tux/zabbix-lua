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
require_once('include/triggers.inc.php');
require_once('include/js.inc.php');

$dstfrm	= get_request('dstfrm',	0);	// destination form

$page['title'] = 'S_GRAPH_ITEM';
$page['file'] = 'popup_gitem.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null),

		'parent_discoveryid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			null),
		'normal_only'=>	array(T_ZBX_INT, O_OPT,	 null,	null,			null),
		'graphid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			null),
		'gid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,	BETWEEN(0,65535),	null),
		'graphtype'=>	array(T_ZBX_INT, O_OPT,	 null,	IN('0,1,2,3'),		'isset({save})'),
		'list_name'=>	array(T_ZBX_STR, O_OPT,  P_SYS,	NOT_EMPTY,			'isset({save})&&isset({gid})'),
		'itemid'=>		array(T_ZBX_INT, O_OPT,  null,	DB_ID.'({}!=0)',	'isset({save})'),
		'color'=>		array(T_ZBX_CLR, O_OPT,  null,	null,				'isset({save})'),
		'drawtype'=>	array(T_ZBX_INT, O_OPT,  null,	IN(graph_item_drawtypes()),'isset({save})&&(({graphtype} == 0) || ({graphtype} == 1))'),
		'sortorder'=>	array(T_ZBX_INT, O_OPT,  null,	BETWEEN(0,65535),	'isset({save})&&(({graphtype} == 0) || ({graphtype} == 1))'),
		'yaxisside'=>	array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),			'isset({save})&&(({graphtype} == 0) || ({graphtype} == 1))'),
		'calc_fnc'=>	array(T_ZBX_INT, O_OPT,	 null,	IN('1,2,4,7,9'),	'isset({save})'),
		'type'=>		array(T_ZBX_INT, O_OPT,	 null,	IN('0,1,2'),		'isset({save})'),

		'only_hostid'=>	array(T_ZBX_INT, O_OPT,  null,	DB_ID,			null),
		'monitored_hosts'=>array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
/* actions */
		'add'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, null,	null,	null)
	);

	check_fields($fields);

	insert_js_function('add_graph_item');
	insert_js_function('update_graph_item');

	$_REQUEST['drawtype'] = get_request('drawtype',0);
	$_REQUEST['yaxisside'] = get_request('yaxisside',GRAPH_YAXIS_SIDE_DEFAULT);
	$_REQUEST['sortorder'] = get_request('sortorder',0);
	$graphid = get_request('graphid',false);


	if(isset($_REQUEST['type']) && ($_REQUEST['type'] == GRAPH_ITEM_SUM) && ($graphid !== false)){
		$sql = 'SELECT COUNT(itemid) as items'.
				' FROM graphs_items '.
				' WHERE type='.GRAPH_ITEM_SUM.
					' AND graphid='.$graphid.
					' AND itemid<>'.$_REQUEST['itemid'];
		$res = DBselect($sql);
		while($rows = DBfetch($res)){
			if(isset($rows['items']) && ($rows['items'] > 0)){
				show_messages(false, null, S_ANOTHER_ITEM_SUM);
				if(isset($_REQUEST['save'])) unset($_REQUEST['save']);
				$_REQUEST['type'] = GRAPH_ITEM_SIMPLE;
			}
		}
	}

	if(isset($_REQUEST['save']) && !isset($_REQUEST['gid'])){
		$script = "add_graph_item(".
			zbx_jsvalue($_REQUEST['dstfrm']).",'".
			$_REQUEST['itemid']."','".
			$_REQUEST['color']."',".
			$_REQUEST['drawtype'].",".
			$_REQUEST['sortorder'].",".
			$_REQUEST['yaxisside'].",".
			$_REQUEST['calc_fnc'].",".
			$_REQUEST['type'].");\n";
		insert_js($script);
	}

	if(isset($_REQUEST['save']) && isset($_REQUEST['gid'])){
		$script = "update_graph_item(".
			zbx_jsvalue($_REQUEST['dstfrm']).",".
			zbx_jsvalue($_REQUEST['list_name']).",'".
			$_REQUEST['gid']."','".
			$_REQUEST['itemid']."','".
			$_REQUEST['color']."',".
			$_REQUEST['drawtype'].",".
			$_REQUEST['sortorder'].",".
			$_REQUEST['yaxisside'].",".
			$_REQUEST['calc_fnc'].",".
			$_REQUEST['type'].");\n";
		insert_js($script);
	}
	else{
		echo SBR;

		$graphid	= get_request('graphid', 		null);
		$graphtype	= get_request('graphtype',	 	GRAPH_TYPE_NORMAL);
		$gid		= get_request('gid',	 		null);
		$list_name	= get_request('list_name',	 	null);
		$itemid		= get_request('itemid', 		0);
		$color		= get_request('color', 			'009900');
		$drawtype	= get_request('drawtype',		0);
		$sortorder	= get_request('sortorder',		0);
		$yaxisside	= get_request('yaxisside',		GRAPH_YAXIS_SIDE_DEFAULT);
		$calc_fnc	= get_request('calc_fnc',		2);
		$type		= get_request('type',			0);
		$only_hostid	= get_request('only_hostid',	null);
		$monitored_hosts = get_request('monitored_hosts', null);

        $caption = ($itemid) ? S_UPD_ITEM_FOR_THE_GRAPH : S_NEW_ITEM_FOR_THE_GRAPH;
		$frmGItem = new CFormTable($caption);

		$frmGItem->setName('graph_item');
		$frmGItem->setHelp('web.graph.item.php');

		$frmGItem->addVar('dstfrm',$_REQUEST['dstfrm']);

		$description = '';
		if($itemid > 0){
			$description = get_item_by_itemid($itemid);
			$description = itemName($description);
		}

		$frmGItem->addVar('graphid',$graphid);
		$frmGItem->addVar('gid',$gid);
		$frmGItem->addVar('list_name',$list_name);
		$frmGItem->addVar('itemid',$itemid);
		$frmGItem->addVar('graphtype',$graphtype);
		$frmGItem->addVar('only_hostid',$only_hostid);

		$txtCondVal = new CTextBox('name',$description,50,'yes');

		$host_condition = '';
		if(isset($only_hostid)){// graph for template must use only one host
			$host_condition = "&only_hostid=".$only_hostid;
		}
		else if(isset($monitored_hosts)){
			$host_condition = "&real_hosts=1";
		}

		$parent_discoveryid = get_request('parent_discoveryid', false);
		$normal_only = get_request('normal_only') ? '&normal_only=1' : '';
		if($parent_discoveryid){
			$btnSelect = new CSubmit('btn1',S_SELECT,
				"return PopUp('popup.php?writeonly=1&dstfrm=".$frmGItem->GetName().
						"&dstfld1=itemid&dstfld2=name&".
						"srctbl=prototypes&srcfld1=itemid&srcfld2=name&parent_discoveryid=".$parent_discoveryid.
						"', 800, 600);",
				'T'
			);
		}
		else{
			$btnSelect = new CSubmit('btn1',S_SELECT,
				"return PopUp('popup.php?writeonly=1&dstfrm=".$frmGItem->GetName().
						"&dstfld1=itemid&dstfld2=name".$normal_only.
						"&srctbl=items&srcfld1=itemid&srcfld2=name".$host_condition."', 800, 600);",
				'T'
			);
		}

		$frmGItem->addRow(S_PARAMETER ,array($txtCondVal,$btnSelect));

		if ($graphtype == GRAPH_TYPE_PIE || $graphtype == GRAPH_TYPE_EXPLODED) {
			$cmbType = new CComboBox('type', $type, 'submit()');
			$cmbType->addItem(GRAPH_ITEM_SIMPLE, S_SIMPLE);
			$cmbType->addItem(GRAPH_ITEM_SUM, S_GRAPH_SUM);
			$frmGItem->addRow(S_TYPE, $cmbType);
		}
		else {
			$frmGItem->addVar('type', GRAPH_ITEM_SIMPLE);
		}

		$cmbFnc = new CComboBox('calc_fnc', $calc_fnc, 'submit();');

		if ($graphtype == GRAPH_TYPE_PIE || $graphtype == GRAPH_TYPE_EXPLODED) {

			$cmbFnc->addItem(CALC_FNC_MIN, S_MIN_SMALL);
			$cmbFnc->addItem(CALC_FNC_AVG, S_AVG_SMALL);
			$cmbFnc->addItem(CALC_FNC_MAX, S_MAX_SMALL);
			$cmbFnc->addItem(CALC_FNC_LST, S_LST_SMALL);
			$frmGItem->addRow(S_FUNCTION, $cmbFnc);
		}
		else {
			if ($graphtype == GRAPH_TYPE_NORMAL) {
				$cmbFnc->addItem(CALC_FNC_ALL, S_ALL_SMALL);
			}

			$cmbFnc->addItem(CALC_FNC_MIN, S_MIN_SMALL);
			$cmbFnc->addItem(CALC_FNC_AVG, S_AVG_SMALL);
			$cmbFnc->addItem(CALC_FNC_MAX, S_MAX_SMALL);
			$frmGItem->addRow(S_FUNCTION, $cmbFnc);

			if ($graphtype == GRAPH_TYPE_NORMAL) {
				$cmbType = new CComboBox('drawtype', $drawtype);
				$drawtypes = graph_item_drawtypes();

				foreach ($drawtypes as $i) {
					$cmbType->addItem($i, graph_item_drawtype2str($i));
				}

				$frmGItem->addRow(S_DRAW_STYLE, $cmbType);
			}
			else {
				$frmGItem->addVar('drawtype', 1);
			}
		}

		$frmGItem->addRow(S_COLOR, new CColor('color', $color));

		if(($graphtype == GRAPH_TYPE_NORMAL) || ($graphtype == GRAPH_TYPE_STACKED)){
			$cmbYax = new CComboBox('yaxisside',$yaxisside);
			$cmbYax->addItem(GRAPH_YAXIS_SIDE_LEFT,	S_LEFT);
			$cmbYax->addItem(GRAPH_YAXIS_SIDE_RIGHT, S_RIGHT);
			$frmGItem->addRow(S_YAXIS_SIDE, $cmbYax);
		}

		if($type != GRAPH_ITEM_SUM){
			$frmGItem->addRow(S_SORT_ORDER_0_100, new CTextBox('sortorder',$sortorder,3));
		}

		$frmGItem->addItemToBottomRow(new CSubmit('save', isset($gid) ? S_SAVE : S_ADD));

		$frmGItem->addItemToBottomRow(new CButtonCancel(null,'close_window();'));
		$frmGItem->show();
	}
?>
<?php

require_once('include/page_footer.php');

?>
