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
require_once('include/events.inc.php');
require_once('include/actions.inc.php');
require_once('include/js.inc.php');

?>
<?php

	function screen_resources($res=null){
		$resources = array(
			SCREEN_RESOURCE_CLOCK => S_CLOCK,
			SCREEN_RESOURCE_DATA_OVERVIEW => S_DATA_OVERVIEW,
			SCREEN_RESOURCE_GRAPH => S_GRAPH,
			SCREEN_RESOURCE_ACTIONS => S_HISTORY_OF_ACTIONS,
			SCREEN_RESOURCE_EVENTS => S_HISTORY_OF_EVENTS,
			SCREEN_RESOURCE_HOSTS_INFO => S_HOSTS_INFO,
			SCREEN_RESOURCE_MAP => S_MAP,
			SCREEN_RESOURCE_PLAIN_TEXT => S_PLAIN_TEXT,
			SCREEN_RESOURCE_SCREEN => S_SCREEN,
			SCREEN_RESOURCE_SERVER_INFO => S_SERVER_INFO,
			SCREEN_RESOURCE_SIMPLE_GRAPH => S_SIMPLE_GRAPH,
			SCREEN_RESOURCE_HOSTGROUP_TRIGGERS => S_STATUS_OF_HOSTGROUP_TRIGGERS,
			SCREEN_RESOURCE_HOST_TRIGGERS => S_STATUS_OF_HOST_TRIGGERS,
			SCREEN_RESOURCE_SYSTEM_STATUS => _('System status'),
			SCREEN_RESOURCE_TRIGGERS_INFO => S_TRIGGERS_INFO,
			SCREEN_RESOURCE_TRIGGERS_OVERVIEW => S_TRIGGERS_OVERVIEW,
			SCREEN_RESOURCE_URL => S_URL,
		);

		if(is_null($res)){
			natsort($resources);
			return $resources;
		}
		else if(isset($resources[$res]))
			return $resources[$res];
		else
			return S_UNKNOW;
	}

	function get_screen_by_screenid($screenid){
		$result = DBselect("select * from screens where screenid=$screenid");
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		// error("No screen with screenid=[$screenid]");
	return FALSE;
	}

	function check_screen_recursion($mother_screenid, $child_screenid){
		if((bccomp($mother_screenid , $child_screenid)==0))	return TRUE;

		$db_scr_items = DBselect("select resourceid from screens_items where".
			" screenid=$child_screenid and resourcetype=".SCREEN_RESOURCE_SCREEN);
		while($scr_item = DBfetch($db_scr_items)){
			if(check_screen_recursion($mother_screenid,$scr_item["resourceid"]))
				return TRUE;
		}
	return FALSE;
	}

	function get_slideshow($slideshowid, $step, $effectiveperiod=NULL){
		$sql = 'SELECT min(step) as min_step, max(step) as max_step '.
				' FROM slides '.
				' WHERE slideshowid='.$slideshowid;
		$slide_data = DBfetch(DBselect($sql));
		if(!$slide_data || is_null($slide_data['min_step'])){
			return false;
		}

		$step = $step % ($slide_data['max_step']+1);
		if(!isset($step) || $step < $slide_data['min_step'] || $step > $slide_data['max_step']){
			$curr_step = $slide_data['min_step'];
		}
		else{
			$curr_step = $step;
		}

		$sql = 'SELECT sl.* '.
				' FROM slides sl, slideshows ss '.
				' WHERE ss.slideshowid='.$slideshowid.
					' and sl.slideshowid=ss.slideshowid '.
					' and sl.step='.$curr_step;
		$slide_data = DBfetch(DBselect($sql));

	return $slide_data;
	}


	function slideshow_accessible($slideshowid, $perm){
		$result = false;

		$sql = 'SELECT slideshowid '.
				' FROM slideshows '.
				' WHERE slideshowid='.$slideshowid.
					' AND '.DBin_node('slideshowid', get_current_nodeid(null,$perm));
		if(DBselect($sql)){
			$result = true;

			$screenids = array();
			$sql = 'SELECT DISTINCT screenid '.
					' FROM slides '.
					' WHERE slideshowid='.$slideshowid;
			$db_screens = DBselect($sql);
			while($slide_data = DBfetch($db_screens)){
				$screenids[$slide_data['screenid']] = $slide_data['screenid'];
			}

			$options = array(
					'screenids' => $screenids
				);
			if($perm == PERM_READ_WRITE) $options['editable'] = 1;

			$screens = API::Screen()->get($options);
			$screens = zbx_toHash($screens, 'screenid');

			foreach($screenids as $snum => $screenid){
				if(!isset($screens[$screenid])) return false;
			}
		}

	return $result;
	}

	function get_slideshow_by_slideshowid($slideshowid){
		return DBfetch(DBselect('select * from slideshows where slideshowid='.$slideshowid));
	}

	function add_slideshow($name, $delay, $slides){
		if(empty($slides)){
			error(S_SLIDESHOW_MUST_CONTAIN_SLIDES);
			return false;
		}

		$screenids = zbx_objectValues($slides, 'screenid');
		$screens = API::Screen()->get(array(
			'screenids' => $screenids,
			'output' => API_OUTPUT_SHORTEN,
		));
		$screens = ZBX_toHash($screens, 'screenid');
		foreach($screenids as $screenid){
			if(!isset($screens[$screenid])) return false;
		}

		foreach($slides as $slide){
			if(!isset($slide['delay'])) $slide['delay'] = 0;
		}

		$slideshowid = get_dbid('slideshows','slideshowid');
		$result = DBexecute('INSERT INTO slideshows (slideshowid,name,delay) '.
							' VALUES ('.$slideshowid.','.zbx_dbstr($name).','.$delay.')');

		$i = 0;
		foreach($slides as $num => $slide){
			$slideid = get_dbid('slides','slideid');

// TODO: resulve conflict about regression of delay per slide
			$result = DBexecute('INSERT INTO slides (slideid,slideshowid,screenid,step,delay) '.
								' VALUES ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')');
			if(!$result) return false;
		}

	return $slideshowid;
	}

	function update_slideshow($slideshowid, $name, $delay, $slides){
		if(empty($slides)){
			error(S_SLIDESHOW_MUST_CONTAIN_SLIDES);
			return false;
		}

		$screenids = zbx_objectValues($slides, 'screenid');
		$screens = API::Screen()->get(array(
			'screenids' => $screenids,
			'output' => API_OUTPUT_SHORTEN,
		));
		$screens = ZBX_toHash($screens, 'screenid');
		foreach($screenids as $screenid){
			if(!isset($screens[$screenid])) return false;
		}

		foreach($slides as $slide){
			if(!isset($slide['delay'])) $slide['delay'] = 0;
		}

		if(!$result = DBexecute('UPDATE slideshows SET name='.zbx_dbstr($name).',delay='.$delay.' WHERE slideshowid='.$slideshowid))
			return false;

		// fetching all slides that currently are
		$dbSlidesR = DBSelect('SELECT * FROM slides WHERE slideshowid='.$slideshowid.' ORDER BY step');
		$dbSlides = array();
		while($dbSlide = DBFetch($dbSlidesR)){
			$dbSlides[] = $dbSlide;
		}

		// checking, if at least one of them has changes
		$slidesChanged = false;
		if(count($dbSlides) != count($slides)){
			$slidesChanged = true;
		}
		else{
			foreach($dbSlides as $i=>$dbSlide){
				if(bccomp($dbSlides[$i]['screenid'], $slides[$i]['screenid']) != 0 || $dbSlides[$i]['delay'] != $slides[$i]['delay']){
					$slidesChanged = true;
					break;
				}
			}
		}

		// if slides have changed
		if($slidesChanged){
			// wiping all of them out
			DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);
			// and inserting new ones
			$i = 0;
			foreach($slides as $slide){
				$slideid = get_dbid('slides','slideid');
				if(!isset($slide['delay'])) $slide['delay'] = $delay;
				$result = DBexecute('INSERT INTO slides (slideid,slideshowid,screenid,step,delay) '.
					' VALUES ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')');
				if(!$result){
					return false;
				}
			}
		}

	return true;
	}

	function delete_slideshow($slideshowid){

		$result = DBexecute('DELETE FROM slideshows where slideshowid='.$slideshowid);
		$result &= DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);
		$result &= DBexecute("DELETE FROM profiles WHERE idx='web.favorite.screenids' AND source='slideshowid' AND value_id=$slideshowid");

		return $result;
	}


//Show screen cell containing plain text values
	function get_screen_plaintext($itemid,$elements,$style=0){

		if($itemid == 0){
			$table = new CTableInfo(S_ITEM_DOES_NOT_EXIST);
			$table->setHeader(array(S_TIMESTAMP,S_ITEM));
			return $table;
		}

		$item = get_item_by_itemid($itemid);
		switch($item['value_type']){
			case ITEM_VALUE_TYPE_TEXT:
			case ITEM_VALUE_TYPE_LOG:
				$order_field = 'id';
				break;
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
			default:
				$order_field = 'clock';
		}

		$host = get_host_by_itemid($itemid);

		$table = new CTableInfo();
		$table->setHeader(array(S_TIMESTAMP,$host['name'].': '.itemName($item)));

		$options = array(
			'history' => $item['value_type'],
			'itemids' => $itemid,
			'output' => API_OUTPUT_EXTEND,
			'sortorder' => ZBX_SORT_DOWN,
			'sortfield' => $order_field,
			'limit' => $elements
		);

		$hData = API::History()->get($options);
		foreach($hData as $hnum => $data){
			switch($item['value_type']){
				case ITEM_VALUE_TYPE_TEXT:
/* do not use break */
				case ITEM_VALUE_TYPE_STR:
					if($style) $value = new CJSscript($data['value']);
					else $value = $data['value'];
					break;
				case ITEM_VALUE_TYPE_LOG:
					if($style) $value = new CJSscript($data['value']);
					else $value = $data['value'];
					break;
				default:
					$value = $data['value'];
					break;
			}

			if($item['valuemapid'] > 0)
				$value = replace_value_by_map($value, $item['valuemapid']);

			$table->addRow(
					array(
						zbx_date2str(S_SCREENS_PLAIN_TEXT_DATE_FORMAT,$data['clock']),
						new CCol($value, 'pre')
					)
				);
		}

	return $table;
	}

/*
* Function:
*		check_dynamic_items
*
* Description:
*		Check whether there are dynamic items in the screen, if so return TRUE, else FALSE
*
* Author:
*		Aly
*/

	function check_dynamic_items($elid, $config=0){
		if($config == 0){
			$sql = 'SELECT screenitemid '.
			' FROM screens_items '.
			' WHERE screenid='.$elid.
				' AND dynamic='.SCREEN_DYNAMIC_ITEM;
		}
		else{
			$sql = 'SELECT si.screenitemid '.
			' FROM slides s, screens_items si '.
			' WHERE s.slideshowid='.$elid.
				' AND si.screenid=s.screenid'.
				' AND si.dynamic='.SCREEN_DYNAMIC_ITEM;
		}

		if(DBfetch(DBselect($sql,1))) return TRUE;
	return FALSE;
	}

	function templated_screen($screenid){
		$result = false;

		$sql = 'SELECT resourceid, resourcetype '.
				' FROM screens_items'.
				' WHERE screenid='.$_REQUEST['screenid'];
		$db_sitems = DBSelect($sql);
		while($sitem = DBfetch($db_sitems)){
			if(in_array($sitem['resourcetype'], array(SCREEN_RESOURCE_DATA_OVERVIEW, SCREEN_RESOURCE_ACTIONS,
				SCREEN_RESOURCE_EVENTS, SCREEN_RESOURCE_HOSTS_INFO, SCREEN_RESOURCE_MAP, SCREEN_RESOURCE_SCREEN,
				SCREEN_RESOURCE_SERVER_INFO, SCREEN_RESOURCE_HOSTGROUP_TRIGGERS, SCREEN_RESOURCE_HOST_TRIGGERS,
				SCREEN_RESOURCE_SYSTEM_STATUS, SCREEN_RESOURCE_TRIGGERS_INFO, SCREEN_RESOURCE_TRIGGERS_OVERVIEW
			))){
				$result = false;
				break;
			}
			else if($sitem['resourcetype'] == SCREEN_RESOURCE_GRAPH){
				$tpl = API::Template()->get(array(
					'graphids' => $sitem['resourceid'],
					'output' => API_OUTPUT_SHORTEN,
					'editable' => 1,
				));
				$tpl = reset($tpl);
				$result = $tpl ? $tpl['templateid'] : false;
				break;
			}
			else if($sitem['resourcetype'] == SCREEN_RESOURCE_SIMPLE_GRAPH){
				$tpl = API::Template()->get(array(
					'itemids' => $sitem['resourceid'],
					'output' => API_OUTPUT_SHORTEN,
					'editable' => 1,
				));
				$tpl = reset($tpl);
				$result = $tpl ? $tpl['templateid'] : false;
				break;
			}
		}

		return $result;
	}

	function get_screen_item_form($screen){

		$form = new CFormTable(S_SCREEN_CELL_CONFIGURATION, 'screenedit.php?screenid='.$_REQUEST['screenid']);
		$form->setName('screen_item_form');

		if(isset($_REQUEST['screenitemid'])){
			$form->addVar('screenitemid',$_REQUEST['screenitemid']);
			$screenItems = zbx_toHash($screen['screenitems'], 'screenitemid');
		}
		else{
			$form->addVar('x',$_REQUEST['x']);
			$form->addVar('y',$_REQUEST['y']);
		}

		if(isset($_REQUEST['screenitemid']) && !isset($_REQUEST['form_refresh'])){
			$screenItem = $screenItems[$_REQUEST['screenitemid']];

			$resourcetype	= $screenItem['resourcetype'];
			$resourceid	= $screenItem['resourceid'];
			$width		= $screenItem['width'];
			$height		= $screenItem['height'];
			$colspan	= $screenItem['colspan'];
			$rowspan	= $screenItem['rowspan'];
			$elements	= $screenItem['elements'];
			$valign		= $screenItem['valign'];
			$halign		= $screenItem['halign'];
			$style		= $screenItem['style'];
			$url		= $screenItem['url'];
			$dynamic	= $screenItem['dynamic'];
			$sort_triggers  = $screenItem['sort_triggers'];
		}
		else{
			$resourcetype	= get_request('resourcetype',	0);
			$resourceid	= get_request('resourceid',	0);
			$width		= get_request('width',		500);
			$height		= get_request('height',		100);
			$colspan	= get_request('colspan',	0);
			$rowspan	= get_request('rowspan',	0);
			$elements	= get_request('elements',	25);
			$valign		= get_request('valign',		VALIGN_DEFAULT);
			$halign		= get_request('halign',		HALIGN_DEFAULT);
			$style		= get_request('style',		0);
			$url		= get_request('url',		'');
			$dynamic	= get_request('dynamic',	SCREEN_SIMPLE_ITEM);
			$sort_triggers  = get_request('sort_triggers',  SCREEN_SORT_TRIGGERS_DATE_DESC);
		}

		$form->addVar('screenid',$_REQUEST['screenid']);

		$cmbRes = new CCombobox('resourcetype', $resourcetype, 'submit()');

		$sress = screen_resources();
		if($screen['templateid']){
			unset($sress[SCREEN_RESOURCE_DATA_OVERVIEW], $sress[SCREEN_RESOURCE_ACTIONS], $sress[SCREEN_RESOURCE_EVENTS],
				$sress[SCREEN_RESOURCE_HOSTS_INFO], $sress[SCREEN_RESOURCE_MAP], $sress[SCREEN_RESOURCE_SCREEN],
				$sress[SCREEN_RESOURCE_SERVER_INFO], $sress[SCREEN_RESOURCE_HOSTGROUP_TRIGGERS], $sress[SCREEN_RESOURCE_HOST_TRIGGERS],
				$sress[SCREEN_RESOURCE_SYSTEM_STATUS], $sress[SCREEN_RESOURCE_TRIGGERS_INFO], $sress[SCREEN_RESOURCE_TRIGGERS_OVERVIEW]);
		}

		$cmbRes->addItems($sress);
		$form->addRow(S_RESOURCE, $cmbRes);

		if($resourcetype == SCREEN_RESOURCE_GRAPH){
// User-defined graph
			$options = array(
				'graphids' => $resourceid,
				'selectHosts' => array('hostid', 'name', 'status'),
				'output' => API_OUTPUT_EXTEND
			);
			$graphs = API::Graph()->get($options);

			$caption = '';
			$id=0;

			if(!empty($graphs)){
				$id = $resourceid;
				$graph = reset($graphs);

				order_result($graph['hosts'], 'name');
				$graph['host'] = reset($graph['hosts']);

				if($graph['host']['status'] != HOST_STATUS_TEMPLATE)
					$caption = $graph['host']['name'].':'.$graph['name'];
				else
					$caption = $graph['name'];

				$nodeName = get_node_name_by_elid($graph['host']['hostid']);
				if(!zbx_empty($nodeName))
					$caption = '('.$nodeName.') '.$caption;
			}

			$form->addVar('resourceid',$id);

			$textfield = new CTextbox('caption', $caption, 75, 'yes');
			if($screen['templateid']){
				$selectbtn = new CButton('select', S_SELECT,
					"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName().
							"&templated_hosts=1&only_hostid=".$screen['templateid']."&dstfld1=resourceid&".
							"dstfld2=caption&srctbl=graphs&srcfld1=graphid&srcfld2=name&simpleName=1',800,450);");
			}
			else{
				$selectbtn = new CButton('select', S_SELECT,
					"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName().
							"&real_hosts=1&dstfld1=resourceid&dstfld2=caption&srctbl=graphs&srcfld1=graphid".
							"&srcfld2=name',800,450);");
			}


			$form->addRow(S_GRAPH_NAME,array($textfield,SPACE,$selectbtn));

		}
		else if($resourcetype == SCREEN_RESOURCE_SIMPLE_GRAPH){
// Simple graph
			$options = array(
				'itemids' => $resourceid,
				'selectHosts' => array('hostid', 'name', 'status'),
				'output' => API_OUTPUT_EXTEND
			);
			$items = API::Item()->get($options);

			$caption = '';
			$id = 0;

			if(!empty($items)){
				$id = $resourceid;

				$item = reset($items);
				$item['host'] = reset($item['hosts']);

				if($item['host']['status'] != HOST_STATUS_TEMPLATE)
					$caption = $item['host']['name'].':'.itemName($item);
				else
					$caption = itemName($item);

				$nodeName = get_node_name_by_elid($item['itemid']);
				if(!zbx_empty($nodeName))
					$caption = '('.$nodeName.') '.$caption;
			}

			$form->addVar('resourceid',$id);

			$textfield = new Ctextbox('caption', $caption, 75, 'yes');
			if($screen['templateid']){
				$selectbtn = new CButton('select', S_SELECT,
					"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName().
							"&templated_hosts=1&only_hostid=".$screen['templateid']."&dstfld1=resourceid&".
							"dstfld2=caption&srctbl=simple_graph&srcfld1=itemid&srcfld2=name&simpleName=1',800,450);");
			}
			else{
				$selectbtn = new CButton('select', S_SELECT,
					"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName().
							"&real_hosts=1&dstfld1=resourceid&dstfld2=caption&srctbl=simple_graph&srcfld1=itemid".
							"&srcfld2=name',800,450);");
			}

			$form->addRow(S_PARAMETER, array($textfield, SPACE, $selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_MAP){
// Map
			$options = array(
				'sysmapids' => $resourceid,
				'output' => API_OUTPUT_EXTEND
			);
			$maps = API::Map()->get($options);

			$caption = '';
			$id=0;

			if(!empty($maps)){
				$id = $resourceid;
				$map = reset($maps);

				$caption = $map['name'];
				$nodeName = get_node_name_by_elid($map['sysmapid']);
				if(!zbx_empty($nodeName))
					$caption = '('.$nodeName.') '.$caption;
			}

			$form->addVar('resourceid',$id);
			$textfield = new Ctextbox('caption',$caption,60,'yes');

			$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name',400,450);");

			$form->addRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));

		}
		else if($resourcetype == SCREEN_RESOURCE_PLAIN_TEXT){
// Plain text
			$options = array(
				'itemids' => $resourceid,
				'selectHosts' => array('hostid', 'name'),
				'output' => API_OUTPUT_EXTEND
			);
			$items = API::Item()->get($options);

			$caption = '';
			$id=0;

			if(!empty($items)){
				$id = $resourceid;

				$item = reset($items);
				$item['host'] = reset($item['hosts']);

				$caption = $item['host']['name'].':'.itemName($item);

				$nodeName = get_node_name_by_elid($item['itemid']);
				if(!zbx_empty($nodeName))
					$caption = '('.$nodeName.') '.$caption;
			}

			$form->addVar('resourceid',$id);

			$textfield = new CTextbox('caption', $caption, 75, 'yes');

			if($screen['templateid']){
				$selectbtn = new CButton('select', S_SELECT,
					"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName().
							"&templated_hosts=1&only_hostid=".$screen['templateid']."&dstfld1=resourceid&".
							"dstfld2=caption&srctbl=plain_text&srcfld1=itemid&srcfld2=name', 800, 450);");
			}
			else{
				$selectbtn = new CButton('select', S_SELECT,
					"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName().
							"&real_hosts=1&dstfld1=resourceid&dstfld2=caption&srctbl=plain_text&srcfld1=itemid".
							"&srcfld2=name', 800, 450);");
			}

			$form->addRow(S_PARAMETER, array($textfield, SPACE, $selectbtn));
			$form->addRow(S_SHOW_LINES, new CNumericBox('elements', $elements, 2));
			$form->addRow(S_SHOW_TEXT_AS_HTML, new CCheckBox('style', $style, null, 1));
		}
		else if(in_array($resourcetype, array(SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,SCREEN_RESOURCE_HOST_TRIGGERS))){
			// Status of triggers
			$caption = '';
			$id=0;

			if(SCREEN_RESOURCE_HOSTGROUP_TRIGGERS == $resourcetype){
				if($resourceid > 0){
					$options = array(
						'groupids' => $resourceid,
						'output' => API_OUTPUT_EXTEND,
						'editable' => 1
					);

					$groups = API::HostGroup()->get($options);
					foreach($groups as $gnum => $group){
						$caption = get_node_name_by_elid($group['groupid'], true, ':').$group['name'];
						$id = $resourceid;
					}
				}

				$form->addVar('resourceid', $id);

				$textfield = new CTextbox('caption', $caption, 60, 'yes');
				$selectbtn = new CButton('select', S_SELECT, "javascript: return PopUp('popup.php?writeonly=1&dstfrm=" . $form->getName() . "&dstfld1=resourceid&dstfld2=caption&srctbl=host_group&srcfld1=groupid&srcfld2=name',800,450);");

				$form->addRow(S_GROUP, array($textfield, SPACE, $selectbtn));
			}
			else{
				if($resourceid > 0){
					$options = array(
						'hostids' => $resourceid,
						'output' => API_OUTPUT_EXTEND,
						'editable' => 1
					);

					$hosts = API::Host()->get($options);
					foreach($hosts as $hnum => $host){
						$caption = get_node_name_by_elid($host['hostid'], true, ':').$host['name'];
						$id = $resourceid;
					}
				}

				$form->addVar('resourceid',$id);

				$textfield = new CTextbox('caption',$caption,60,'yes');
				$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=hosts&srcfld1=hostid&srcfld2=name',800,450);");

				$form->addRow(S_HOST,array($textfield,SPACE,$selectbtn));
			}

			// text box to choose number of lines shown
			$form->addRow(S_SHOW_LINES, new CNumericBox('elements', $elements, 2));
			// combobox to choose trigger sort order
			$form->addRow(
				_('Sort triggers by'),
				new CComboBox(
					'sort_triggers',
					$sort_triggers,
					null,
					array(
						SCREEN_SORT_TRIGGERS_DATE_DESC => _('Last change (descending)'),
						SCREEN_SORT_TRIGGERS_SEVERITY_DESC => _('Severity (descending)'),
						SCREEN_SORT_TRIGGERS_HOST_NAME_ASC => _('Host (ascending)')
					)
				)
			);
		}
		else if(in_array($resourcetype, array(SCREEN_RESOURCE_EVENTS, SCREEN_RESOURCE_ACTIONS))){
// History of actions
// History of events
			$form->addRow(S_SHOW_LINES, new CNumericBox('elements', $elements, 2));
			$form->addVar('resourceid', 0);
		}
		else if(in_array($resourcetype, array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW, SCREEN_RESOURCE_DATA_OVERVIEW))){
// Overviews
			$caption = '';
			$id = 0;

			if($resourceid > 0){
				$options = array(
					'groupids' => $resourceid,
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1
				);

				$groups = API::HostGroup()->get($options);
				foreach($groups as $gnum => $group){
					$caption = get_node_name_by_elid($group['groupid'], true, ':').$group['name'];
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);

			$textfield = new CTextbox('caption',$caption,75,'yes');
			$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=overview&srcfld1=groupid&srcfld2=name',800,450);");

			$form->addRow(S_GROUP,array($textfield,SPACE,$selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_SCREEN){
// Screens
			$caption = '';
			$id=0;

			if($resourceid > 0){
				$sql = 'SELECT DISTINCT n.name as node_name,s.screenid,s.name '.
						' FROM screens s '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('s.screenid').
						' WHERE s.screenid='.$resourceid;
				$result=DBselect($sql);

				while($row=DBfetch($result)){
					$r = API::Screen()->get(array(
						'screenids' => $row['screenid'],
						'output' => API_OUTPUT_SHORTEN
					));
					if(empty($r)) continue;
					if(check_screen_recursion($_REQUEST['screenid'],$row['screenid'])) continue;

					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].$row['name'];
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);

			$textfield = new Ctextbox('caption',$caption,60,'yes');
			$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=screens2&srcfld1=screenid&srcfld2=name&screenid=".$_REQUEST['screenid']."',800,450);");

			$form->addRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
		}
		elseif ($resourcetype == SCREEN_RESOURCE_HOSTS_INFO || $resourcetype == SCREEN_RESOURCE_TRIGGERS_INFO){
// HOSTS info
			$caption = '';
			$id = 0;

			if (remove_nodes_from_id($resourceid) > 0) {
				$groups = API::HostGroup()->get(array(
					'groupids' => $resourceid,
					'nodeids' => get_current_nodeid(true),
					'output' => array('name'),
					'preservekeys' => true
				));
				if ($group = reset($groups)) {
					$caption = get_node_name_by_elid($resourceid, true, ': ').$group['name'];
					$id = $resourceid;
				}
			}
			elseif (remove_nodes_from_id($resourceid) == 0) {
				if ($nodeName = get_node_name_by_elid($resourceid, true, ': ')) {
					$caption = $nodeName._('- all groups -');
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid', $id);

			$textfield = new CTextbox('caption', $caption, 60, 'yes');
			$selectbtn = new CButton('select', S_SELECT, "javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=host_group_scr&srcfld1=groupid&srcfld2=name',480,450);");

			$form->addRow(S_GROUP,array($textfield,SPACE,$selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_CLOCK){
			$caption = get_request('caption', '');

			if(zbx_empty($caption) && (TIME_TYPE_HOST == $style) && ($resourceid > 0)){
				$options = array(
					'itemids' => $resourceid,
					'selectHosts' => array('name'),
					'output' => API_OUTPUT_EXTEND
				);
				$items = API::Item()->get($options);
				$item = reset($items);
				$host = reset($item['hosts']);

				$caption = $host['name'].':'.$item['name'];
			}

			$form->addVar('resourceid',$resourceid);

			$cmbStyle = new CComboBox('style', $style, 'javascript: submit();');
			$cmbStyle->addItem(TIME_TYPE_LOCAL,	S_LOCAL_TIME);
			$cmbStyle->addItem(TIME_TYPE_SERVER,S_SERVER_TIME);
			$cmbStyle->addItem(TIME_TYPE_HOST,	S_HOST_TIME);

			$form->addRow(S_TIME_TYPE,	$cmbStyle);

			if(TIME_TYPE_HOST == $style){
				$textfield = new CTextbox('caption',$caption,75,'yes');

				if($screen['templateid']){
					$selectbtn = new CButton('select', S_SELECT,
						"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=items&srcfld1=itemid&srcfld2=name&templated_hosts=1&only_hostid=".$screen['templateid']."',800,450);");
				}
				else{
					$selectbtn = new CButton('select', S_SELECT,
						"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=items&srcfld1=itemid&srcfld2=name&real_hosts=1',800,450);");
				}

				$form->addRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
			}
			else{
				$form->addVar('caption',$caption);
			}
		}
		else{
			$form->addVar('resourceid',0);
		}

		if(in_array($resourcetype, array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO))){
			$cmbStyle = new CComboBox('style', $style);
			$cmbStyle->addItem(STYLE_HORISONTAL,	S_HORIZONTAL);
			$cmbStyle->addItem(STYLE_VERTICAL,	S_VERTICAL);
			$form->addRow(S_STYLE,	$cmbStyle);
		}
		else if(in_array($resourcetype, array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW))){
			$cmbStyle = new CComboBox('style', $style);
			$cmbStyle->addItem(STYLE_LEFT,	S_LEFT);
			$cmbStyle->addItem(STYLE_TOP,	S_TOP);
			$form->addRow(S_HOSTS_LOCATION,	$cmbStyle);
		}
		else{
			$form->addVar('style',	0);
		}

		if(in_array($resourcetype, array(SCREEN_RESOURCE_URL))){
			$form->addRow(S_URL, new CTextBox('url', $url, 60));
		}
		else{
			$form->addVar('url', '');
		}

		if(in_array($resourcetype, array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL))){
			$form->addRow(S_WIDTH,	new CNumericBox('width', $width, 5));
			$form->addRow(S_HEIGHT,	new CNumericBox('height', $height, 5));
		}
		else{
			$form->addVar('width', 500);
			$form->addVar('height', 100);
		}

		if(in_array($resourcetype, array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_MAP,
			SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL))){
			$cmbHalign = new CComboBox('halign', $halign);
			$cmbHalign->addItem(HALIGN_CENTER, S_CENTRE);
			$cmbHalign->addItem(HALIGN_LEFT, S_LEFT);
			$cmbHalign->addItem(HALIGN_RIGHT, S_RIGHT);
			$form->addRow(S_HORIZONTAL_ALIGN, $cmbHalign);
		}
		else{
			$form->addVar('halign',	0);
		}

		$cmbValign = new CComboBox('valign',$valign);
		$cmbValign->addItem(VALIGN_MIDDLE,	S_MIDDLE);
		$cmbValign->addItem(VALIGN_TOP,		S_TOP);
		$cmbValign->addItem(VALIGN_BOTTOM,	S_BOTTOM);
		$form->addRow(S_VERTICAL_ALIGN,	$cmbValign);

		$form->addRow(S_COLUMN_SPAN,	new CNumericBox('colspan',$colspan,2));
		$form->addRow(S_ROW_SPAN,	new CNumericBox('rowspan',$rowspan,2));

// dynamic AddOn
		if(($screen['templateid'] == 0) && in_array($resourcetype, array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_PLAIN_TEXT))){
			$form->addRow(S_DYNAMIC_ITEM,	new CCheckBox('dynamic',$dynamic,null,1));
		}

		$form->addItemToBottomRow(new CSubmit('save', S_SAVE));
		if(isset($_REQUEST['screenitemid'])){
			$form->addItemToBottomRow(array(SPACE, new CButtonDelete(null,
				url_param('form').url_param('screenid').url_param('screenitemid'))));
		}
		$form->addItemToBottomRow(array(SPACE, new CButtonCancel(url_param('screenid'))));

		return $form;
	}

	// editmode: 0 - view with actions, 1 - edit mode, 2 - view without any actions
	function get_screen($screen, $editmode, $effectiveperiod = null) {
		if (is_null($effectiveperiod)) {
			$effectiveperiod = ZBX_MIN_PERIOD;
		}

		if (!$screen) {
			return new CTableInfo(_('No screens defined.'));
		}

		$skip_field = array();
		$screenItems = array();

		foreach ($screen['screenitems'] as $screenItem) {
			$screenItems[] = $screenItem;
			for ($i = 0; $i < $screenItem['rowspan'] || $i == 0; $i++) {
				for ($j = 0; $j < $screenItem['colspan'] || $j == 0; $j++) {
					if ($i != 0 || $j != 0) {
						if (!isset($skip_field[$screenItem['y'] + $i])) {
							$skip_field[$screenItem['y'] + $i] = array();
						}
						$skip_field[$screenItem['y'] + $i][$screenItem['x'] + $j] = 1;
					}
				}
			}
		}

		$table = new CTable(
			new CLink(
				S_NO_ROWS_IN_SCREEN.SPACE.$screen['name'],
				'screenconf.php?config=0&form=update&screenid='.$screen['screenid']
			),
			($editmode == 0 || $editmode == 2) ? 'screen_view' : 'screen_edit'
		);
		$table->setAttribute('id', 'iframe');

		if ($editmode == 1) {
			$new_cols = array(new Ccol(new Cimg('images/general/zero.gif', 'zero', 1, 1)));
			for ($c = 0; $c < $screen['hsize'] + 1; $c++) {
				$add_icon = new Cimg('images/general/closed.gif', NULL, NULL, NULL, 'pointer');
            	$add_icon->addAction('onclick', "javascript: location.href = 'screenedit.php?config=1&screenid=".$screen['screenid']."&add_col=$c';");
				array_push($new_cols, new Ccol($add_icon));
			}
			$table->addRow($new_cols);
		}

		$empty_screen_col = array();

		for ($r = 0; $r < $screen['vsize']; $r++) {
			$new_cols = array();
			$empty_screen_row = true;

			if ($editmode == 1) {
				$add_icon = new Cimg('images/general/closed.gif', NULL, NULL, NULL, 'pointer');
            	$add_icon->addAction('onclick', "javascript: location.href = 'screenedit.php?config=1&screenid=".$screen['screenid']."&add_row=$r';");
				array_push($new_cols, new Ccol($add_icon));
			}

			for ($c = 0; $c < $screen['hsize']; $c++) {
				if (isset($skip_field[$r][$c])) {
					continue;
				}
				$item_form = false;

				$screenItem = false;
				foreach ($screenItems as $tmprow) {
					if ($tmprow['x'] == $c && $tmprow['y'] == $r) {
						$screenItem = $tmprow;
						break;
					}
				}

				if ($screenItem) {
					$screenitemid = $screenItem['screenitemid'];
					$resourcetype = $screenItem['resourcetype'];
					$resourceid = $screenItem['resourceid'];
					$width = $screenItem['width'];
					$height = $screenItem['height'];
					$colspan = $screenItem['colspan'];
					$rowspan = $screenItem['rowspan'];
					$elements = $screenItem['elements'];
					$valign = $screenItem['valign'];
					$halign = $screenItem['halign'];
					$style = $screenItem['style'];
					$url = $screenItem['url'];
					$dynamic = $screenItem['dynamic'];
					$sort_triggers = $screenItem['sort_triggers'];
				}
				else {
					$screenitemid = 0;
					$resourcetype = 0;
					$resourceid = 0;
					$width = 0;
					$height = 0;
					$colspan = 0;
					$rowspan = 0;
					$elements = 0;
					$valign = VALIGN_DEFAULT;
					$halign = HALIGN_DEFAULT;
					$style = 0;
					$url = '';
					$dynamic = 0;
					$sort_triggers = SCREEN_SORT_TRIGGERS_DATE_DESC;
				}

				if ($screenitemid > 0) {
					$empty_screen_row = false;
					$empty_screen_col[$c] = 1;
				}

				if ($editmode == 1 && $screenitemid != 0) {
					$action = 'screenedit.php?form=update'.url_param('screenid').'&screenitemid='.$screenitemid.'#form';
				}
				elseif ($editmode == 1 && $screenitemid == 0) {
					$action = 'screenedit.php?form=update'.url_param('screenid').'&x='.$c.'&y='.$r.'#form';
				}
				else {
					$action = NULL;
				}

				if ($editmode == 1 && isset($_REQUEST['form']) && isset($_REQUEST['x']) && $_REQUEST['x'] == $c &&
					isset($_REQUEST['y']) && $_REQUEST['y'] == $r)
				{
					// click on empty field
					$item = get_screen_item_form($screen);
					$item_form = true;
				}
				elseif ($editmode == 1 && isset($_REQUEST['form']) && isset($_REQUEST['screenitemid']) &&
					bccomp($_REQUEST['screenitemid'], $screenitemid) == 0)
				{
					// click on element
					$item = get_screen_item_form($screen);
					$item_form = true;
				}
				elseif ($screenitemid != 0  && $resourcetype == SCREEN_RESOURCE_GRAPH) {
					if ($editmode == 0) {
						$action = 'charts.php?graphid='.$resourceid.url_param('period').url_param('stime');
					}

					// GRAPH & ZOOM features

					$dom_graph_id = 'graph_'.$screenitemid.'_'.$resourceid;
					$containerid = 'graph_cont_'.$screenitemid.'_'.$resourceid;
					$graphDims = getGraphDims($resourceid);

					$graphDims['graphHeight'] = $height;
					$graphDims['width'] = $width;

					$graph = get_graph_by_graphid($resourceid);

					$graphid = $graph['graphid'];
					$legend = $graph['show_legend'];
					$graph3d = $graph['show_3d'];

					//-------------

					// Host feature

					if ($dynamic == SCREEN_DYNAMIC_ITEM && isset($_REQUEST['hostid']) && $_REQUEST['hostid'] > 0) {
						$options = array(
							'hostids' => $_REQUEST['hostid'],
							'output' => array('hostid', 'host')
						);
						$hosts = API::Host()->get($options);
						$host = reset($hosts);

						$options = array(
							'graphids' => $resourceid,
							'output' => API_OUTPUT_EXTEND,
							'selectHosts' => API_OUTPUT_REFER,
							'selectGraphItems' => API_OUTPUT_EXTEND
						);
						$graph = API::Graph()->get($options);
						$graph = reset($graph);

						if (count($graph['hosts']) == 1) {
							// if items from one host we change them, or set calculated if not exist on that host
							if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid']) {
								$new_dinamic = get_same_graphitems_for_host(
									array(array('itemid' => $graph['ymax_itemid'])),
									$_REQUEST['hostid'],
									false	// false = don't rise Error if item doesn't exist
								);
								$new_dinamic = reset($new_dinamic);
								if (isset($new_dinamic['itemid']) && $new_dinamic['itemid'] > 0) {
									$graph['ymax_itemid'] = $new_dinamic['itemid'];
								}
								else {
									$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
								}
							}
							if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid']) {
								$new_dinamic = get_same_graphitems_for_host(
									array(array('itemid' => $graph['ymin_itemid'])),
									$_REQUEST['hostid'],
									false	// false = don't rise Error if item doesn't exist
								);
								$new_dinamic = reset($new_dinamic);
								if (isset($new_dinamic['itemid']) && $new_dinamic['itemid'] > 0) {
									$graph['ymin_itemid'] = $new_dinamic['itemid'];
								}
								else {
									$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
								}
							}
						}

						$url = ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED)
								? 'chart7.php'
								: 'chart3.php';

						$url = new Curl($url);
						foreach ($graph as $name => $value) {
							if ($name == 'width' || $name == 'height') {
								continue;
							}

							$url->setArgument($name, $value);
						}

						$new_items = get_same_graphitems_for_host($graph['gitems'], $_REQUEST['hostid'], false);
						foreach ($new_items as $gitem) {
							unset($gitem['gitemid']);
							unset($gitem['graphid']);

							foreach ($gitem as $name => $value) {
								$url->setArgument('items['.$gitem['itemid'].']['.$name.']', $value);
							}
						}

						$url->setArgument('name', $host['host'].': '.$graph['name']);
						$url = $url->getUrl();
					}

					//-------------

					$objData = array(
						'id' => $resourceid,
						'domid' => $dom_graph_id,
						'containerid' => $containerid,
						'objDims' => $graphDims,
						'loadSBox' => 0,
						'loadImage' => 1,
						'loadScroll' => 0,
						'dynamic' => 0,
						'periodFixed' => CProfile::get('web.screens.timelinefixed', 1)
					);

					$default = false;
					if ($graphDims['graphtype'] == GRAPH_TYPE_PIE || $graphDims['graphtype'] == GRAPH_TYPE_EXPLODED) {
						if ($dynamic == SCREEN_SIMPLE_ITEM || empty($url)) {
							$url='chart6.php?graphid='.$resourceid;
							$default = true;
						}

						$timeline = array();
						$timeline['period'] = $effectiveperiod;
						$timeline['starttime'] = date('YmdHis', get_min_itemclock_by_graphid($resourceid));

						if (isset($_REQUEST['stime'])) {
							$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
						}

						$src = $url.'&width='.$width.'&height='.$height.'&legend='.$legend.'&graph3d='.$graph3d.'&period='.$effectiveperiod.url_param('stime');

						$objData['src'] = $src;
					}
					else {
						if ($dynamic == SCREEN_SIMPLE_ITEM || empty($url)) {
							$url = 'chart2.php?graphid='.$resourceid;
							$default = true;
						}

						$src = $url.'&width='.$width.'&height='.$height.'&period='.$effectiveperiod.url_param('stime');

						$timeline = array();
						if (isset($graphid) && !is_null($graphid) && $editmode != 1) {
							$timeline['period'] = $effectiveperiod;
							$timeline['starttime'] = date('YmdHis', time() - ZBX_MAX_PERIOD);

							if (isset($_REQUEST['stime'])) {
								$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
							}

							$objData['loadSBox'] = 1;
						}

						$objData['src'] = $src;
					}

					if ($editmode || !$default) {
						$item = new CDiv();
					}
					else {
						$item = new CLink(null, $action);
					}

					$item->setAttribute('id', $containerid);

					$item = array($item);
					if ($editmode == 1) {
						$item[] = BR();
						$item[] = new CLink(S_CHANGE, $action);
					}

					if ($editmode == 2) {
						insert_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
					}
					else {
						zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_SIMPLE_GRAPH) {
					$dom_graph_id = 'graph_'.$screenitemid.'_'.$resourceid;
					$containerid = 'graph_cont_'.$screenitemid.'_'.$resourceid;

					$graphDims = getGraphDims();
					$graphDims['graphHeight'] = $height;
					$graphDims['width'] = $width;

					$objData = array(
						'id' => $resourceid,
						'domid' => $dom_graph_id,
						'containerid' => $containerid,
						'objDims' => $graphDims,
						'loadSBox' => 0,
						'loadImage' => 1,
						'loadScroll' => 0,
						'dynamic' => 0,
						'periodFixed' => CProfile::get('web.screens.timelinefixed', 1)
					);

					// Host feature

					if ($dynamic == SCREEN_DYNAMIC_ITEM && isset($_REQUEST['hostid']) && $_REQUEST['hostid'] > 0) {
						if ($newitemid = get_same_item_for_host($resourceid, $_REQUEST['hostid'])) {
							$resourceid = $newitemid;
						}
						else {
							$resourceid = '';
						}
					}

					//-------------

					if ($editmode == 0 && !empty($resourceid)) {
						$action = 'history.php?action=showgraph&itemid='.$resourceid.url_param('period').url_param('stime');
					}

					$timeline = array();
					$timeline['starttime'] = date('YmdHis', time() - ZBX_MAX_PERIOD);

					if (!zbx_empty($resourceid) && $editmode != 1) {
						$timeline['period'] = $effectiveperiod;

						if (isset($_REQUEST['stime'])) {
							$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
						}

						$objData['loadSBox'] = 1;
					}

					$src = zbx_empty($resourceid) ? 'chart3.php?' : 'chart.php?itemid='.$resourceid.'&';
					$src .= $url.'width='.$width.'&height='.$height;

					$objData['src'] = $src;

					if ($editmode) {
						$item = new CDiv();
					}
					else {
						$item = new CLink(null, $action);
					}

					$item->setAttribute('id', $containerid);

					$item = array($item);
					if ($editmode == 1) {
						$item[] = BR();
						$item[] = new CLink(S_CHANGE, $action);
					}

					if ($editmode == 2) {
						insert_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
					}
					else {
						zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_MAP) {
					$image_map = new CImg("map.php?noedit=1&sysmapid=$resourceid"."&width=$width&height=$height&curtime=".time());
					$item = array($image_map);

					if ($editmode == 0) {
						$options = array(
							'sysmapids' => $resourceid,
							'output' => API_OUTPUT_EXTEND,
							'selectSelements' => API_OUTPUT_EXTEND,
							'selectLinks' => API_OUTPUT_EXTEND,
							'nopermissions' => true,
							'preservekeys' => true
						);
						$sysmaps = API::Map()->get($options);
						$sysmap = reset($sysmaps);

						$action_map = getActionMapBySysmap($sysmap);
						$image_map->setMap($action_map->getName());
						$item = array($action_map, $image_map);
					}
					elseif ($editmode == 1) {
						$item[] = BR();
						$item[] = new CLink(S_CHANGE, $action);
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_PLAIN_TEXT) {
					// Host feature
					if ($dynamic == SCREEN_DYNAMIC_ITEM && isset($_REQUEST['hostid']) && $_REQUEST['hostid'] > 0) {
						if ($newitemid = get_same_item_for_host($resourceid, $_REQUEST['hostid'])){
							$resourceid = $newitemid;
						}
						else {
							$resourceid = 0;
						}
					}

					$item = array(get_screen_plaintext($resourceid, $elements,$style));
					if ($editmode == 1)	{
						array_push($item,new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_HOSTGROUP_TRIGGERS) {
					$params = array(
						'groupids' => null,
						'hostids' => null,
						'maintenance' => null,
						'severity' => null,
						'limit' => $elements
					);

					// by default triggers are sorted by date desc, do we need to override this?
					switch ($sort_triggers) {
						case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
							$params['sortfield'] = 'priority';
							$params['sortorder'] = ZBX_SORT_DOWN;
							break;
						case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
							// a little black magic here - there is no such field 'hostname' in 'triggers',
							// but API has a special case for sorting by hostname
							$params['sortfield'] = 'hostname';
							$params['sortorder'] = ZBX_SORT_UP;
							break;
					}

					if ($resourceid > 0) {
						$options = array(
							'groupids' => $resourceid,
							'output' => API_OUTPUT_EXTEND
						);
						$hostgroups = API::HostGroup()->get($options);
						$hostgroup = reset($hostgroups);

						$tr_form = new CSpan(S_GROUP.': '.$hostgroup['name'], 'white');
						$params['groupids'] = $hostgroup['groupid'];
					}
					else {
						$groupid = get_request('tr_groupid', CProfile::get('web.screens.tr_groupid', 0));
						$hostid = get_request('tr_hostid', CProfile::get('web.screens.tr_hostid', 0));

						CProfile::update('web.screens.tr_groupid', $groupid, PROFILE_TYPE_ID);
						CProfile::update('web.screens.tr_hostid', $hostid, PROFILE_TYPE_ID);

						$options = array(
							'monitored_hosts' => 1,
							'output' => API_OUTPUT_EXTEND
						);
						$groups = API::HostGroup()->get($options);
						order_result($groups, 'name');

						$options = array(
							'monitored_hosts' => 1,
							'output' => API_OUTPUT_EXTEND
						);
						if ($groupid > 0) {
							$options['groupids'] = $groupid;
						}

						$hosts = API::Host()->get($options);
						$hosts = zbx_toHash($hosts, 'hostid');
						order_result($hosts, 'host');

						if (!isset($hosts[$hostid])) {
							$hostid = 0;
						}

						$tr_form = new CForm();

						$cmbGroup = new CComboBox('tr_groupid', $groupid, 'submit()');
						$cmbHosts = new CComboBox('tr_hostid', $hostid, 'submit()');
						if ($editmode == 1) {
							$cmbGroup->attr('disabled', 'disabled');
							$cmbHosts->attr('disabled', 'disabled');
						}

						$cmbGroup->addItem(0, S_ALL_SMALL);
						$cmbHosts->addItem(0, S_ALL_SMALL);

						foreach ($groups as $group) {
							$cmbGroup->addItem($group['groupid'], get_node_name_by_elid($group['groupid'], null, ': ').$group['name']);
						}

						foreach ($hosts as $host) {
							$cmbHosts->addItem($host['hostid'], get_node_name_by_elid($host['hostid'], null, ': ').$host['host']);
						}

						$tr_form->addItem(array(S_GROUP.SPACE, $cmbGroup));
						$tr_form->addItem(array(SPACE.S_HOST.SPACE, $cmbHosts));

						if ($groupid > 0) {
							$params['groupids'] = $groupid;
						}
						if ($hostid > 0) {
							$params['hostids'] = $hostid;
						}
					}

					$params['screenid'] = $screen['screenid'];

					$item = new CUIWidget('hat_htstatus', make_latest_issues($params, true));
					$item->setDoubleHeader(array(S_STATUS_OF_TRIGGERS_BIG, SPACE, zbx_date2str(S_SCREENS_TRIGGER_FORM_DATE_FORMAT), SPACE), $tr_form);
					$item = array($item);

					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_HOST_TRIGGERS) {
					$params = array(
						'groupids' => null,
						'hostids' => null,
						'maintenance' => null,
						'severity' => null,
						'limit' => $elements
					);

					// by default triggers are sorted by date desc, do we need to override this?
					switch ($sort_triggers) {
						case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
							$params['sortfield'] = 'priority';
							$params['sortorder'] = ZBX_SORT_DOWN;
							break;
						case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
							// a little black magic here - there is no such field 'hostname' in 'triggers',
							// but API has a special case for sorting by hostname
							$params['sortfield'] = 'hostname';
							$params['sortorder'] = ZBX_SORT_UP;
							break;
					}

					if ($resourceid > 0) {
						$options = array(
							'hostids' => $resourceid,
							'output' => API_OUTPUT_EXTEND
						);
						$hosts = API::Host()->get($options);
						$host = reset($hosts);

						$tr_form = new CSpan(S_HOST.': '.$host['host'], 'white');
						$params['hostids'] = $host['hostid'];
					}
					else {
						$groupid = get_request('tr_groupid', CProfile::get('web.screens.tr_groupid', 0));
						$hostid = get_request('tr_hostid', CProfile::get('web.screens.tr_hostid', 0));

						CProfile::update('web.screens.tr_groupid', $groupid, PROFILE_TYPE_ID);
						CProfile::update('web.screens.tr_hostid', $hostid, PROFILE_TYPE_ID);

						$options = array(
							'monitored_hosts' => 1,
							'output' => API_OUTPUT_EXTEND
						);
						$groups = API::HostGroup()->get($options);
						order_result($groups, 'name');

						$options = array(
							'monitored_hosts' => 1,
							'output' => API_OUTPUT_EXTEND
						);
						if ($groupid > 0) {
							$options['groupids'] = $groupid;
						}

						$hosts = API::Host()->get($options);
						$hosts = zbx_toHash($hosts, 'hostid');
						order_result($hosts, 'host');

						if (!isset($hosts[$hostid])) {
							$hostid = 0;
						}

						$tr_form = new CForm();

						$cmbGroup = new CComboBox('tr_groupid', $groupid, 'submit()');
						$cmbHosts = new CComboBox('tr_hostid', $hostid, 'submit()');
						if ($editmode == 1) {
							$cmbGroup->attr('disabled', 'disabled');
							$cmbHosts->attr('disabled', 'disabled');
						}

						$cmbGroup->addItem(0, S_ALL_SMALL);
						$cmbHosts->addItem(0, S_ALL_SMALL);

						foreach ($groups as $group) {
							$cmbGroup->addItem(
								$group['groupid'],
								get_node_name_by_elid($group['groupid'], null, ': ').$group['name']
							);
						}

						foreach ($hosts as $host) {
							$cmbHosts->addItem(
								$host['hostid'],
								get_node_name_by_elid($host['hostid'], null, ': ').$host['host']
							);
						}

						$tr_form->addItem(array(S_GROUP.SPACE, $cmbGroup));
						$tr_form->addItem(array(SPACE.S_HOST.SPACE, $cmbHosts));

						if ($groupid > 0) {
							$params['groupids'] = $groupid;
						}
						if ($hostid > 0) {
							$params['hostids'] = $hostid;
						}
					}

					$params['screenid'] = $screen['screenid'];

					$item = new CUIWidget('hat_trstatus', make_latest_issues($params, true));
					$item->setDoubleHeader(array(S_STATUS_OF_TRIGGERS_BIG, SPACE, zbx_date2str(S_SCREENS_TRIGGER_FORM_DATE_FORMAT), SPACE), $tr_form);
					$item = array($item);

					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_SYSTEM_STATUS) {
					$params = array(
						'groupids' => null,
						'hostids' => null,
						'maintenance' => null,
						'severity' => null,
						'limit' => null,
						'extAck' => 0,
						'screenid' => $screen['screenid']
					);

					$item = new CUIWidget('hat_syssum', make_system_status($params));
					$item->setHeader(S_STATUS_OF_ZABBIX, SPACE);
					$item->setFooter(_s('Updated: %s', zbx_date2str(_('H:i:s'))));

					$item = array($item);

					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_HOSTS_INFO) {
					$item = array(new CHostsInfo($resourceid, $style));
					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_TRIGGERS_INFO) {
					$item = new CTriggersInfo($resourceid, null, $style);
					$item = array($item);
					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_SERVER_INFO) {
					$item = array(new CServerInfo());
					if ($editmode == 1) {
						array_push($item,new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_CLOCK) {
					$error = null;
					$timeOffset = null;
					$timeZone = null;

					switch ($style) {
						case TIME_TYPE_HOST:
							$options = array(
								'itemids' => $resourceid,
								'selectHosts' => API_OUTPUT_EXTEND,
								'output' => API_OUTPUT_EXTEND
							);

							$items = API::Item()->get($options);
							$item = reset($items);
							$host = reset($item['hosts']);

							$timeType = $host['host'];

							preg_match('/([+-]{1})([\d]{1,2}):([\d]{1,2})/', $item['lastvalue'], $arr);
							if (!empty($arr)) {
								$timeZone = $arr[2] * SEC_PER_HOUR + $arr[3] * SEC_PER_MIN;
								if ($arr[1] == '-') {
									$timeZone = 0 - $timeZone;
								}
							}

							if ($lastvalue = strtotime($item['lastvalue'])) {
								$diff = (time() - $item['lastclock']);
								$timeOffset = $lastvalue + $diff;
							}
							else {
								$error = _('NO DATA');
							}
							break;
						case TIME_TYPE_SERVER:
							$error = null;
							$timeType = S_SERVER_BIG;
							$timeOffset = time();
							$timeZone = date('Z');
							break;
						default:
							$error = null;
							$timeType = S_LOCAL_BIG;
							$timeOffset = null;
							$timeZone = null;
							break;
					}

					$item = new CFlashClock($width, $height, $action);
					$item->setTimeError($error);
					$item->setTimeType($timeType);
					$item->setTimeZone($timeZone);
					$item->setTimeOffset($timeOffset);

					$item = array($item);
					if ($editmode == 1) {
						$item[] = BR();
						$item[] = new CLink(S_CHANGE, $action);
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_SCREEN) {
					$subScreens = API::Screen()->get(array(
						'screenids' => $resourceid,
						'output' => API_OUTPUT_EXTEND,
						'selectScreenItems' => API_OUTPUT_EXTEND
					));
					$subScreen = reset($subScreens);
					$item = array(get_screen($subScreen, 2, $effectiveperiod));
					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_TRIGGERS_OVERVIEW) {
					$hostids = array();
					$res = DBselect('SELECT DISTINCT hg.hostid FROM hosts_groups hg WHERE hg.groupid='.$resourceid);
					while ($tmp_host = DBfetch($res)) {
						$hostids[$tmp_host['hostid']] = $tmp_host['hostid'];
					}

					$item = array(get_triggers_overview($hostids, $style, array('screenid' => $screen['screenid'])));
					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_DATA_OVERVIEW) {
					$hostids = array();
					$res = DBselect('SELECT DISTINCT hg.hostid FROM hosts_groups hg WHERE hg.groupid='.$resourceid);
					while ($tmp_host = DBfetch($res)) {
						$hostids[$tmp_host['hostid']] = $tmp_host['hostid'];
					}

					$item = array(get_items_data_overview($hostids, $style));
					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_URL) {
					$item = array(new CIFrame($url, $width, $height, 'auto'));
					if ($editmode == 1) {
						array_push($item, BR(), new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_ACTIONS) {
					$item = array(get_history_of_actions($elements));
					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_EVENTS) {
					$options = array(
						'monitored' => 1,
						'value' => array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE),
						'limit' => $elements
					);

					$showUnknown = CProfile::get('web.events.filter.showUnknown', 0);
					if($showUnknown) {
						$options['value'] = array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE);
					}

					$item = new CTableInfo(S_NO_EVENTS_FOUND);
					$item->setHeader(array(
						S_TIME,
						is_show_all_nodes() ? S_NODE : null,
						S_HOST,
						S_DESCRIPTION,
						S_VALUE,
						S_SEVERITY
					));

					$events = getLastEvents($options);
					foreach ($events as $event) {
						$trigger = $event['trigger'];
						$host = $event['host'];

						$statusSpan = new CSpan(trigger_value2str($event['value']));
						// add colors and blinking to span depending on configuration and trigger parameters
						addTriggerValueStyle(
							$statusSpan,
							$event['value'],
							$event['clock'],
							$event['acknowledged']
						);

						$item->addRow(array(
							zbx_date2str(S_EVENTS_TRIGGERS_EVENTS_HISTORY_LIST_DATE_FORMAT, $event['clock']),
							get_node_name_by_elid($event['objectid']),
							$host['host'],
							new CLink(
								$trigger['description'],
								'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid']
							),
							$statusSpan,
							getSeverityCell($trigger['priority'])
						));
					}

					zbx_add_post_js('jqBlink.init();');

					$item = array($item);
					if ($editmode == 1) {
						array_push($item, new CLink(S_CHANGE, $action));
					}
				}
				else {
					$item = array(SPACE);
					if ($editmode == 1) {
						array_push($item, BR(), new CLink(S_CHANGE, $action));
					}
				}

				$str_halign = 'def';
				if($halign == HALIGN_CENTER) {
					$str_halign = 'cntr';
				}
				if($halign == HALIGN_LEFT) {
					$str_halign = 'left';
				}
				if($halign == HALIGN_RIGHT) {
					$str_halign = 'right';
				}

				$str_valign = 'def';
				if($valign == VALIGN_MIDDLE) {
					$str_valign = 'mdl';
				}
				if($valign == VALIGN_TOP) {
					$str_valign = 'top';
				}
				if($valign == VALIGN_BOTTOM) {
					$str_valign = 'bttm';
				}

				if ($editmode == 1 && !$item_form) {
					$item = new CDiv($item, 'draggable');
					$item->setAttribute('id', 'position_'.$r.'_'.$c);
					$item->setAttribute('data-xcoord', $c);
					$item->setAttribute('data-ycoord', $r);
				}

				$new_col = new CCol($item, $str_halign.'_'.$str_valign);

				if ($colspan) {
					$new_col->SetColSpan($colspan);
				}
				if ($rowspan) {
					$new_col->SetRowSpan($rowspan);
				}

				array_push($new_cols, $new_col);
			}

			if ($editmode == 1) {
				$rmv_icon = new Cimg('images/general/opened.gif', NULL, NULL, NULL, 'pointer');
				if ($empty_screen_row) {
					$rmv_row_link = "javascript: location.href = 'screenedit.php?config=1&screenid=".$screen['screenid']."&rmv_row=$r';";
				}
				else {
					$rmv_row_link = "javascript: if(Confirm('".S_THIS_SCREEN_ROW_NOT_EMPTY.'. '.S_DELETE_IT_Q."')){".
									" location.href = 'screenedit.php?config=1&screenid=".$screen['screenid']."&rmv_row=$r';}";
				}
				$rmv_icon->addAction('onclick', $rmv_row_link);

				array_push($new_cols, new Ccol($rmv_icon));
			}
			$table->addRow(new CRow($new_cols));
		}

		if ($editmode == 1) {
         $add_icon = new Cimg('images/general/closed.gif', NULL, NULL, NULL, 'pointer');
         $add_icon->addAction('onclick', "javascript: location.href = 'screenedit.php?config=1&screenid=".$screen['screenid']."&add_row=".$screen['vsize']."';");
			$new_cols = array(new Ccol($add_icon));
			for ($c = 0; $c < $screen['hsize']; $c++) {
				$rmv_icon = new Cimg('images/general/opened.gif', NULL, NULL, NULL, 'pointer');
				if (isset($empty_screen_col[$c])) {
					$rmv_col_link = "javascript: if(Confirm('".S_THIS_SCREEN_COLUMN_NOT_EMPTY.'. '.S_DELETE_IT_Q."')){".
						" location.href = 'screenedit.php?config=1&screenid=".$screen['screenid']."&rmv_col=$c';}";
				}
				else {
					$rmv_col_link = "javascript: location.href = 'screenedit.php?config=1&screenid=".$screen['screenid']."&rmv_col=$c';";
				}
				$rmv_icon->addAction('onclick', $rmv_col_link);
				array_push($new_cols, new Ccol($rmv_icon));
			}

			array_push($new_cols, new Ccol(new Cimg('images/general/zero.gif', 'zero', 1, 1)));
			$table->addRow($new_cols);
		}

		return $table;
	}

	function separateScreenElements($screen){
		$elements = array(
			'sysmaps' => array(),
			'screens' => array(),
			'hostgroups' => array(),
			'hosts' => array(),
			'graphs' => array(),
			'items' => array()
		);


		foreach($screen['screenitems'] as $snum => $screenItem){
			if($screenItem['resourceid'] == 0) continue;

			switch($screenItem['resourcetype']){
				case SCREEN_RESOURCE_HOSTS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
				case SCREEN_RESOURCE_DATA_OVERVIEW:
				case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
					$elements['hostgroups'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_HOST_TRIGGERS:
					$elements['hosts'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_GRAPH:
					$elements['graphs'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_SIMPLE_GRAPH:
				case SCREEN_RESOURCE_PLAIN_TEXT:
					$elements['items'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_MAP:
					$elements['sysmaps'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_SCREEN:
					$elements['screens'][] = $screenItem['resourceid'];
				break;
			}
		}

	return $elements;
	}

	function prepareScreenExport(&$exportScreens){
		$screens = array();
		$sysmaps = array();
		$hostgroups = array();
		$hosts = array();
		$graphs = array();
		$items = array();

		foreach($exportScreens as $snum => $screen){
			$screenItems = separateScreenElements($screen);

			$screens = array_merge($screens, zbx_objectValues($screenItems['screens'], 'resourceid'));
			$sysmaps = array_merge($sysmaps, zbx_objectValues($screenItems['sysmaps'], 'resourceid'));
			$hostgroups = array_merge($hostgroups, zbx_objectValues($screenItems['hostgroups'], 'resourceid'));
			$hosts = array_merge($hosts, zbx_objectValues($screenItems['hosts'], 'resourceid'));
			$graphs = array_merge($graphs, zbx_objectValues($screenItems['graphs'], 'resourceid'));
			$items = array_merge($items, zbx_objectValues($screenItems['items'], 'resourceid'));
		}

		$screens = screenIdents($screens);
		$sysmaps = sysmapIdents($sysmaps);
		$hostgroups = hostgroupIdents($hostgroups);
		$hosts = hostIdents($hosts);
		$graphs = graphIdents($graphs);
		$items = itemIdents($items);

		try{
			foreach($exportScreens as $snum => &$screen){
				unset($screen['screenid']);
				unset($screen['hostid']);

				foreach($screen['screenitems'] as $snum => &$screenItem){
					unset($screenItem['screenid']);
					unset($screenItem['screenitemid']);
					unset($screenItem['dynamic']);
					if($screenItem['resourceid'] == 0) continue;

					switch($screenItem['resourcetype']){
						case SCREEN_RESOURCE_HOSTS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$screenItem['resourceid'] = $hostgroups[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$screenItem['resourceid'] = $hosts[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_GRAPH:
							$screenItem['resourceid'] = $graphs[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$screenItem['resourceid'] = $items[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_MAP:
							$screenItem['resourceid'] = $sysmaps[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_SCREEN:
							$screenItem['resourceid'] = $screens[$screenItem['resourceid']];
						break;
					}
				}
				unset($screenItem);
			}
			unset($screen);
		}
		catch(Exception $e){
			throw new exception($e->getMessage());
		}
	}
?>
