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
require_once('include/events.inc.php');
require_once('include/actions.inc.php');
require_once('include/discovery.inc.php');
require_once('include/html.inc.php');

if(isset($_REQUEST['csv_export'])){
	$CSV_EXPORT = true;
	$csvRows = array();

	$page['type'] = detect_page_type(PAGE_TYPE_CSV);
	$page['file'] = 'zbx_events_export.csv';

	require_once('include/func.inc.php');
}
else{
	$CSV_EXPORT = false;

	$page['title'] = 'S_LATEST_EVENTS';
	$page['file'] = 'events.php';
	$page['hist_arg'] = array('groupid','hostid');
	$page['scripts'] = array('class.calendar.js','gtlc.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}
}

require_once('include/page_header.php');
?>
<?php
	$allow_discovery = check_right_on_discovery(PERM_READ_ONLY);

	$allowed_sources[] = EVENT_SOURCE_TRIGGERS;
	if($allow_discovery) $allowed_sources[] = EVENT_SOURCE_DISCOVERY;

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'source'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN($allowed_sources),	NULL),
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		'triggerid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),

		'period'=>			array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'dec'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'inc'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'left'=>			array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'right'=>			array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'stime'=>			array(T_ZBX_STR, O_OPT,	 null,	null, null),

		'load'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		'fullscreen'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
// Export
		'csv_export'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
// filter
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),

		'showUnknown'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
		'favid'=>		array(T_ZBX_INT, O_OPT, P_ACT,  null,			null),
	);

	check_fields($fields);

/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.events.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		// saving fixed/dynamic setting to profile
		if('timelinefixedperiod' == $_REQUEST['favobj']){
			if(isset($_REQUEST['favid'])){
				CProfile::update('web.events.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		require_once('include/page_footer.php');
		exit();
	}
//--------

// FILTER
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['triggerid'] = 0;
		$_REQUEST['showUnknown'] = 0;
	}

	$source = get_request('triggerid') > 0 ? EVENT_SOURCE_TRIGGERS : get_request('source', CProfile::get('web.events.source', EVENT_SOURCE_TRIGGERS));

	$_REQUEST['triggerid'] = get_request('triggerid',CProfile::get('web.events.filter.triggerid',0));
	$_REQUEST['showUnknown'] = get_request('showUnknown',CProfile::get('web.events.filter.showUnknown',0));

	// Change triggerId filter if change hostId
	if(($_REQUEST['triggerid'] > 0) && isset($_REQUEST['hostid'])){
		$hostid = get_request('hostid');
		$oldTriggers = API::Trigger()->get(array(
			'output' => array('triggerid', 'description', 'expression'),
			'selectHosts' => array('hostid', 'host'),
			'selectItems' => API_OUTPUT_EXTEND,
			'selectFunctions' => API_OUTPUT_EXTEND,
			'triggerids' => $_REQUEST['triggerid'],
		));

		foreach($oldTriggers as $oldTrigger){
			$_REQUEST['triggerid'] = 0;
			$oldTrigger['hosts'] = zbx_toHash($oldTrigger['hosts'],'hostid');
			$oldTrigger['items'] = zbx_toHash($oldTrigger['items'],'itemid');
			$oldTrigger['functions'] = zbx_toHash($oldTrigger['functions'],'functionid');
			$oldExpression = triggerExpression($oldTrigger,false);

			if(isset($oldTrigger['hosts'][$hostid])) break;

			$newTriggers = API::Trigger()->get(array(
				'output' => array('triggerid', 'description', 'expression'),
				'selectHosts' => array('hostid', 'host'),
				'selectItems' => API_OUTPUT_EXTEND,
				'selectFunctions' => API_OUTPUT_EXTEND,
				'filter' => array('description' => $oldTrigger['description']),
				'hostids' => $hostid,
			));

			foreach($newTriggers as $tnum => $newTrigger){
				if(count($oldTrigger['items']) != count($newTrigger['items'])) continue;
				$newTrigger['items'] = zbx_toHash($newTrigger['items'],'itemid');
				$newTrigger['hosts'] = zbx_toHash($newTrigger['hosts'],'hostid');
				$newTrigger['functions'] = zbx_toHash($newTrigger['functions'],'functionid');

				$found = false;
				foreach($newTrigger['functions'] as $fnum => $function){
					foreach($oldTrigger['functions'] as $ofnum => $oldFunction){;
						// compare functions
						if(($function['function'] != $oldFunction['function']) || ($function['parameter'] != $oldFunction['parameter'])) continue;
						// compare that functions uses same item keys
						if($newTrigger['items'][$function['itemid']]['key_'] != $oldTrigger['items'][$oldFunction['itemid']]['key_']) continue;
						// rewrite itemid so we could compare expressions
						// of two triggers form different hosts
						$newTrigger['functions'][$fnum]['itemid'] = $oldFunction['itemid'];
						$found = true;

						unset($oldTrigger['functions'][$ofnum]);
						break;
					}
					if(!$found) break;
				}
				if(!$found) continue;

				// if we found same trigger we overwriting it's hosts and items for expression compare
				$newTrigger['hosts'] = $oldTrigger['hosts'];
				$newTrigger['items'] = $oldTrigger['items'];

				$newExpression = triggerExpression($newTrigger,false);

				if(strcmp($oldExpression, $newExpression) == 0){
					$_REQUEST['triggerid'] = $newTrigger['triggerid'];
					$_REQUEST['filter_set'] = 1;
					break;
				}
			}
		}
	}
// --------

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.events.filter.triggerid',$_REQUEST['triggerid'], PROFILE_TYPE_ID);
		CProfile::update('web.events.filter.showUnknown',$_REQUEST['showUnknown'], PROFILE_TYPE_INT);
	}
// --------------

	CProfile::update('web.events.source',$source, PROFILE_TYPE_INT);
?>
<?php

	$events_wdgt = new CWidget();

// PAGE HEADER {{{
	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$events_wdgt->addPageHeader(array(S_HISTORY_OF_EVENTS_BIG.SPACE.S_ON_BIG.SPACE, zbx_date2str(S_EVENTS_DATE_FORMAT,time())), $fs_icon);
// }}}PAGE HEADER

// HEADER {{{
	$r_form = new CForm('get');
	$r_form->addVar('fullscreen',$_REQUEST['fullscreen']);
	$r_form->addVar('stime', get_request('stime'));
	$r_form->addVar('period', get_request('period'));

	if(EVENT_SOURCE_TRIGGERS == $source){

		$options = array(
			'groups' => array(
				'monitored_hosts' => 1,
				'with_items' => 1,
			),
			'hosts' => array(
				'monitored_hosts' => 1,
				'with_items' => 1,
			),
			'triggers' => array(),
			'hostid' => get_request('hostid', null),
			'groupid' => get_request('groupid', null),
			'triggerid' => get_request('triggerid', null)
		);
		$pageFilter = new CPageFilter($options);
		$_REQUEST['groupid'] = $pageFilter->groupid;
		$_REQUEST['hostid'] = $pageFilter->hostid;
		if($pageFilter->triggerid > 0){
			$_REQUEST['triggerid'] = $pageFilter->triggerid;
		}

		$r_form->addItem(array(S_GROUP.SPACE,$pageFilter->getGroupsCB(true)));
		$r_form->addItem(array(SPACE.S_HOST.SPACE,$pageFilter->getHostsCB(true)));
	}

	if($allow_discovery){
		$cmbSource = new CComboBox('source', $source, 'submit()');
		$cmbSource->addItem(EVENT_SOURCE_TRIGGERS, S_TRIGGER);
		$cmbSource->addItem(EVENT_SOURCE_DISCOVERY, S_DISCOVERY);
		$r_form->addItem(array(SPACE.S_SOURCE.SPACE, $cmbSource));
	}

	$events_wdgt->addHeader(_('EVENTS'), $r_form);



	// allow CSV export button
	$frmForm = new CForm();
	$frmForm->cleanItems();
	if(isset($_REQUEST['groupid'])) $frmForm->addVar('groupid', $_REQUEST['groupid'], 'groupid_csv');
	if(isset($_REQUEST['hostid'])) $frmForm->addVar('hostid', $_REQUEST['hostid'], 'hostid_csv');
	if(isset($_REQUEST['source'])) $frmForm->addVar('source', $_REQUEST['source'], 'source_csv');
	if(isset($_REQUEST['start'])) $frmForm->addVar('start', $_REQUEST['start'], 'start_csv');
	if(isset($_REQUEST['stime'])) $frmForm->addVar('stime', $_REQUEST['stime'], 'stime_csv');
	if(isset($_REQUEST['period'])) $frmForm->addVar('period', $_REQUEST['period'], 'period_csv');
	$buttons = new CDiv(array(
		new CSubmit('csv_export', _('Export to CSV')),
	));
	$buttons->useJQueryStyle();
	$frmForm->addItem($buttons);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');
	$events_wdgt->addHeader($numrows, $frmForm);

// }}} HEADER


// FILTER {{{
	$filterForm = null;

	if(EVENT_SOURCE_TRIGGERS == $source){
		$filterForm = new CFormTable(null, null, 'get');//,'events.php?filter_set=1','POST',null,'sform');
		$filterForm->setAttribute('name', 'zbx_filter');
		$filterForm->setAttribute('id', 'zbx_filter');

		$filterForm->addVar('triggerid', get_request('triggerid'));
		$filterForm->addVar('stime', get_request('stime'));
		$filterForm->addVar('period', get_request('period'));

		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
			// trigger description
			$trigger = expand_trigger_description($_REQUEST['triggerid']);
			// prepending host name to trigger description
			$triggerHostDB = get_hosts_by_triggerid($_REQUEST['triggerid']);
			$triggerHost = DBfetch($triggerHostDB);
			$trigger = $triggerHost['host'].':'.$trigger;
		}
		else{
			$trigger = '';
		}

		$row = new CRow(array(
			new CCol(S_TRIGGER,'form_row_l'),
			new CCol(array(
				new CTextBox('trigger',$trigger,96,'yes'),
				new CButton("btn1",S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->GetName()."&dstfld1=triggerid&dstfld2=trigger"."&srctbl=triggers&srcfld1=triggerid&srcfld2=description&real_hosts=1');",'T')
			),'form_row_r')
		));
		$filterForm->addRow($row);

		$filterForm->addVar('showUnknown',$_REQUEST['showUnknown']);
		$unkcbx = new CCheckBox('hide_unk',
			$_REQUEST['showUnknown'],
			'javascript: create_var("'.$filterForm->GetName().'", "showUnknown", (this.checked?1:0), 0); ',
			'1');

		$filterForm->addRow(S_SHOW_UNKNOWN_EVENTS,$unkcbx);

		$reset = new CButton('filter_rst',S_RESET,'javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

		$filterForm->addItemToBottomRow(new CSubmit('filter_set',S_FILTER));
		$filterForm->addItemToBottomRow($reset);
	}

	$events_wdgt->addFlicker($filterForm, CProfile::get('web.events.filter.state',0));


	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id', 'scrollbar_cntr');
	$events_wdgt->addFlicker($scroll_div, CProfile::get('web.events.filter.state',0));
// }}} FILTER


	$table = new CTableInfo(S_NO_EVENTS_FOUND);

// CHECK IF EVENTS EXISTS {{{
	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'eventid',
		'sortorder' => ZBX_SORT_UP,
		'nopermissions' => 1,
		'limit' => 1
	);

	if($source == EVENT_SOURCE_DISCOVERY){
		$options['source'] = EVENT_SOURCE_DISCOVERY;
	}
	else{
		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid'] > 0)){
			$options['triggerids'] = $_REQUEST['triggerid'];
		}
		$options['object'] = EVENT_OBJECT_TRIGGER;
		$options['filter'] = array('value_changed' => TRIGGER_VALUE_CHANGED_YES);
		$options['nodeids'] = get_current_nodeid();
		$options['filters'] = array(
			'value_changed' => TRIGGER_VALUE_CHANGED_YES,
			'object' => EVENT_OBJECT_TRIGGER,
		);
	}

	$firstEvent = API::Event()->get($options);
// }}} CHECK IF EVENTS EXISTS

	$_REQUEST['period'] = get_request('period', SEC_PER_WEEK);
	$effectiveperiod = navigation_bar_calc();
	$bstime = $_REQUEST['stime'];
	$from = zbxDateToTime($_REQUEST['stime']);
	$till = $from + $effectiveperiod;

	$csv_disabled = true;

	if(empty($firstEvent)){
		$starttime = null;
		$events = array();
		$paging = getPagingLine($events);
	}
	else{
		$config = select_config();
		$firstEvent = reset($firstEvent);
		$starttime = $firstEvent['clock'];

		if($source == EVENT_SOURCE_DISCOVERY){
			$options = array(
				'source' => EVENT_SOURCE_DISCOVERY,
				'time_from' => $from,
				'time_till' => $till,
				'output' => API_OUTPUT_SHORTEN,
				'sortfield' => 'eventid',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => ($config['search_limit']+1)
			);
			$dsc_events = API::Event()->get($options);

			$paging = getPagingLine($dsc_events);

			$options = array(
				'source' => EVENT_SOURCE_DISCOVERY,
				'eventids' => zbx_objectValues($dsc_events,'eventid'),
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => API_OUTPUT_EXTEND,
				'selectTriggers' => API_OUTPUT_EXTEND,
				'selectItems' => API_OUTPUT_EXTEND,
			);
			$dsc_events = API::Event()->get($options);
			order_result($dsc_events, 'eventid', ZBX_SORT_DOWN);

			// do we need to make CVS export button enabled?
			$csv_disabled = zbx_empty($dsc_events);

			$objectids = array();
			foreach($dsc_events as $enum => $event_data){
				$objectids[$event_data['objectid']] = $event_data['objectid'];
			}

// OBJECT DHOST
			$dhosts = array();
			$sql = 'SELECT s.dserviceid,s.dhostid,s.ip,s.dns '.
					' FROM dservices s '.
					' WHERE '.DBcondition('s.dhostid', $objectids);
			$res = DBselect($sql);
			while($dservices = DBfetch($res)){
				$dhosts[$dservices['dhostid']] = $dservices;
			}

// OBJECT DSERVICE
			$dservices = array();
			$sql = 'SELECT s.dserviceid,s.ip,s.dns,s.type,s.port '.
					' FROM dservices s '.
					' WHERE '.DBcondition('s.dserviceid', $objectids);
			$res = DBselect($sql);
			while($dservice = DBfetch($res)){
				$dservices[$dservice['dserviceid']] = $dservice;
			}

// TABLE
			$table->setHeader(array(
				S_TIME,
				S_IP,
				S_DNS,
				S_DESCRIPTION,
				S_STATUS
			));

			if($CSV_EXPORT){
				$csvRows[] = array(
					S_TIME,
					S_IP,
					S_DNS,
					S_DESCRIPTION,
					S_STATUS
				);
			}

			foreach($dsc_events as $num => $event_data){
				switch($event_data['object']){
					case EVENT_OBJECT_DHOST:
						if(isset($dhosts[$event_data['objectid']])){
							$event_data['object_data'] = $dhosts[$event_data['objectid']];
						}
						else{
							$event_data['object_data']['ip'] = S_UNKNOWN;
							$event_data['object_data']['dns'] = S_UNKNOWN;
						}
						$event_data['description'] = S_HOST;
						break;
					case EVENT_OBJECT_DSERVICE:
						if(isset($dservices[$event_data['objectid']])){
							$event_data['object_data'] = $dservices[$event_data['objectid']];
						}
						else{
							$event_data['object_data']['ip'] = S_UNKNOWN;
							$event_data['object_data']['dns'] = S_UNKNOWN;
							$event_data['object_data']['type'] = S_UNKNOWN;
							$event_data['object_data']['port'] = S_UNKNOWN;
						}

						$event_data['description'] = _('Service').': '.
								discovery_check_type2str($event_data['object_data']['type']).
								discovery_port2str($event_data['object_data']['type'], $event_data['object_data']['port']);

						break;
					default:
						continue;
				}

				if(!isset($event_data['object_data'])) continue;
				$table->addRow(array(
					zbx_date2str(S_EVENTS_DISCOVERY_TIME_FORMAT,$event_data['clock']),
					$event_data['object_data']['ip'],
					zbx_empty($event_data['object_data']['dns']) ? SPACE : $event_data['object_data']['dns'],
					$event_data['description'],
					new CCol(discovery_value($event_data['value']), discovery_value_style($event_data['value']))
				));

				if($CSV_EXPORT){
					$csvRows[] = array(
						zbx_date2str(S_EVENTS_DISCOVERY_TIME_FORMAT,$event_data['clock']),
						$event_data['object_data']['ip'],
						$event_data['object_data']['dns'],
						$event_data['description'],
						discovery_value($event_data['value'])
					);
				}


			}
		}
		else{
			$table->setHeader(array(
				S_TIME,
				is_show_all_nodes()?S_NODE:null,
				($_REQUEST['hostid'] == 0)?S_HOST:null,
				S_DESCRIPTION,
				S_STATUS,
				S_SEVERITY,
				_('Duration'),
				($config['event_ack_enable'])?S_ACK:NULL,
				S_ACTIONS
			));

			if($CSV_EXPORT){
				$csvRows[] = array(
					S_TIME,
					is_show_all_nodes()?S_NODE:null,
					($_REQUEST['hostid'] == 0)?S_HOST:null,
					S_DESCRIPTION,
					S_STATUS,
					S_SEVERITY,
					_('Duration'),
					($config['event_ack_enable'])?S_ACK:NULL,
					S_ACTIONS
				);
			}

			if($pageFilter->hostsSelected){
				$options = array(
					'nodeids' => get_current_nodeid(),
					'filter' => array(
						'value_changed' => TRIGGER_VALUE_CHANGED_YES,
						'object' => EVENT_OBJECT_TRIGGER,
					),
					'time_from' => $from,
					'time_till' => $till,
					'output' => API_OUTPUT_SHORTEN,
					'sortfield' => 'clock',
					'sortorder' => ZBX_SORT_DOWN,
					'limit' => ($config['search_limit']+1)
				);

				if($_REQUEST['showUnknown']) $options['filter']['value_changed'] = null;

				// trigger options
				$trigOpt = array(
					'nodeids' => get_current_nodeid(),
					'output' => API_OUTPUT_SHORTEN
				);

				if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid'] > 0))
					$trigOpt['triggerids'] = $_REQUEST['triggerid'];
				else if($pageFilter->hostid > 0)
					$trigOpt['hostids'] = $pageFilter->hostid;
				else if($pageFilter->groupid > 0)
					$trigOpt['groupids'] = $pageFilter->groupid;

				$triggers = API::Trigger()->get($trigOpt);
				$options['triggerids'] = zbx_objectValues($triggers, 'triggerid');

				// query event with short data
				$events = API::Event()->get($options);

				// get pagging
				$paging = getPagingLine($events);

				// query event with extend data
				$options = array(
					'nodeids' => get_current_nodeid(),
					'eventids' => zbx_objectValues($events,'eventid'),
					'output' => API_OUTPUT_EXTEND,
					'select_acknowledges' => API_OUTPUT_COUNT,
					'sortfield' => 'eventid',
					'sortorder' => ZBX_SORT_DOWN,
					'nopermissions' => 1
				);
				$events = API::Event()->get($options);
				$sortFields = array(
					array('field' => 'clock', 'order' => ZBX_SORT_DOWN),
					array('field' => 'ns', 'order' => ZBX_SORT_DOWN)
				);
				ArraySorter::sort($events, $sortFields);

				$csv_disabled = zbx_empty($events);

				$triggersOptions = array(
					'triggerids' => zbx_objectValues($events, 'objectid'),
					'selectHosts' => API_OUTPUT_EXTEND,
					'selectTriggers' => API_OUTPUT_EXTEND,
					'selectItems' => API_OUTPUT_EXTEND,
					'output' => API_OUTPUT_EXTEND
				);
				$triggers = API::Trigger()->get($triggersOptions);
				$triggers = zbx_toHash($triggers, 'triggerid');

				foreach($events as $enum => $event){
					$trigger = $triggers[$event['objectid']];
					$host = reset($trigger['hosts']);

					$items = array();
					foreach($trigger['items'] as $inum => $item){
						$i = array();
						$i['itemid'] = $item['itemid'];
						$i['value_type'] = $item['value_type']; //ZBX-3059: So it would be possible to show different caption for history for chars and numbers (KB)
						$i['action'] = str_in_array($item['value_type'],array(ITEM_VALUE_TYPE_FLOAT,ITEM_VALUE_TYPE_UINT64))? 'showgraph':'showvalues';
						$i['name'] = itemName($item);
						$items[] = $i;
					}

					// actions
					$actions = get_event_actions_status($event['eventid']);

					$ack = getEventAckState($event, true);

					$description = expand_trigger_description_by_data(zbx_array_merge($trigger, array('clock'=>$event['clock'], 'ns'=>$event['ns'])), ZBX_FLAG_EVENT);

					$tr_desc = new CSpan($description,'pointer');
					$tr_desc->addAction('onclick', "create_mon_trigger_menu(event, ".
											" [{'triggerid': '".$trigger['triggerid']."', 'lastchange': '".$event['clock']."'}],".
											zbx_jsvalue($items, true).");");

					// duration
					if($nextEvent = get_next_event($event, $events, $_REQUEST['showUnknown']))
						$event['duration'] = zbx_date2age($event['clock'], $nextEvent['clock']);
					else
						$event['duration'] = zbx_date2age($event['clock']);

					$statusSpan = new CSpan(trigger_value2str($event['value']));
					// add colors and blinking to span depending on configuration and trigger parameters
					addTriggerValueStyle(
						$statusSpan,
						$event['value'],
						$event['clock'],
						$event['acknowledged']
					);

					$table->addRow(array(
						new CLink(zbx_date2str(S_EVENTS_ACTION_TIME_FORMAT,$event['clock']),
							'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid'],
							'action'
							),
						is_show_all_nodes() ? get_node_name_by_elid($event['objectid']) : null,
						$_REQUEST['hostid'] == 0 ? $host['name'] : null,
						new CSpan($tr_desc, 'link_menu'),
						$statusSpan,
						getSeverityCell($trigger['priority'], null, !$event['value']),
						$event['duration'],
						($config['event_ack_enable'])?$ack:NULL,
						$actions
					));

					if($CSV_EXPORT) {
						$csvRows[] = array(
							zbx_date2str(S_EVENTS_ACTION_TIME_FORMAT,$event['clock']),
							is_show_all_nodes() ? get_node_name_by_elid($event['objectid']) : null,
							$_REQUEST['hostid'] == 0 ? $host['name'] : null,
							$description,
							trigger_value2str($event['value']),
							getSeverityCaption($trigger['priority']),
							$event['duration'],
							($config['event_ack_enable'])? ($event['acknowledges']?S_YES:S_NO) :NULL, // ($config['event_ack_enable'])? $ack :NULL,
							strip_tags( (string)$actions )
						);
					}
				}
			}
			else{
				$events = array();
				$paging = getPagingLine($events);
			}
		}

		if($CSV_EXPORT){
			print(zbx_toCSV($csvRows));
			exit();
		}

		$table = array($paging, $table, $paging);
	}

	$events_wdgt->addItem($table);

// NAV BAR
	$timeline = array(
		'period' => $effectiveperiod,
		'starttime' => date('YmdHis', $starttime),
		'usertime' => date('YmdHis', $till)
	);

	$dom_graph_id = 'scroll_events_id';
	$objData = array(
		'id' => 'timeline_1',
		'loadSBox' => 0,
		'loadImage' => 0,
		'loadScroll' => 1,
		'dynamic' => 0,
		'mainObject' => 1,
		'periodFixed' => CProfile::get('web.events.timelinefixed', 1)
	);

	zbx_add_post_js('jqBlink.init();');
	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');

	$events_wdgt->show();
	if($csv_disabled) zbx_add_post_js('document.getElementById(\'csv_export\').disabled=true;');

?>
<?php

require_once('include/page_footer.php');

?>
