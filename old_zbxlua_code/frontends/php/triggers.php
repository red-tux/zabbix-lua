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
require_once('include/triggers.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_TRIGGERS';
$page['file'] = 'triggers.php';
$page['hist_arg'] = array('hostid','groupid');

require_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID, null),
		'hostid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID, null),

		'triggerid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'(isset({form})&&({form}=="update"))'),

		'copy_type'	=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1'),'isset({copy})'),
		'copy_mode'	=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0'),NULL),

		'type'=>			array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	'isset({save})'),
		'description'=>		array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})', _('Name')),
		'expression'=>		array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		'priority'=>		array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1,2,3,4,5'),'isset({save})'),
		'comments'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
		'url'=>				array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
		'status'=>			array(T_ZBX_STR, O_OPT,  NULL,	NULL, NULL),

        'input_method'=>	array(T_ZBX_INT, O_OPT,  NULL,  NOT_EMPTY,'isset({toggle_input_method})'),
        'expr_temp'=>		array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'(isset({add_expression})||isset({and_expression})||isset({or_expression})||isset({replace_expression}))'),
        'expr_target_single'=>		array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'(isset({and_expression})||isset({or_expression})||isset({replace_expression}))'),

		'dependencies'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),
		'new_dependence'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID.'{}>0','isset({add_dependence})'),
		'rem_dependence'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),

		'g_triggerid'=>		array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),
		'copy_targetid'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		'filter_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),

		'showdisabled'=>	array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'),	NULL),

// mass update
		'massupdate'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'visible'=>			array(T_ZBX_STR, O_OPT,	null, 	null,	null),

// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),

// form
        'toggle_input_method'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'add_expression'=> 		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'and_expression'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'or_expression'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'replace_expression'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'remove_expression'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'test_expression'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),

		'add_dependence'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'del_dependence'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_enable'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_disable'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_delete'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'copy'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'mass_save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

// other
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	$_REQUEST['showdisabled'] = get_request('showdisabled', CProfile::get('web.triggers.showdisabled', 1));

	check_fields($fields);
	validate_sort_and_sortorder('description',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');

// PERMISSIONS
	if (get_request('triggerid', false)) {
		$options = array(
			'triggerids' => $_REQUEST['triggerid'],
			'editable' => true,
			'preservekeys' => true,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
		);
		$triggers = API::Trigger()->get($options);
		if (empty($triggers)) {
			access_deny();
		}
	}
	elseif (get_request('hostid', 0) > 0) {
		$options = array(
			'hostids' => $_REQUEST['hostid'],
			'output' => API_OUTPUT_EXTEND,
			'templated_hosts' => 1,
			'editable' => 1
		);
		$hosts = API::Host()->get($options);
		if (empty($hosts)) {
			access_deny();
		}
	}


	$showdisabled = get_request('showdisabled', 1);
	CProfile::update('web.triggers.showdisabled',$showdisabled,PROFILE_TYPE_INT);

// EXPRESSION ACTIONS
	if(isset($_REQUEST['add_expression'])){
		$_REQUEST['expression'] = $_REQUEST['expr_temp'];
		$_REQUEST['expr_temp'] = '';
	}
	else if(isset($_REQUEST['and_expression'])){
		$_REQUEST['expr_action'] = '&';
	}
	else if(isset($_REQUEST['or_expression'])){
		$_REQUEST['expr_action'] = '|';
	}
	else if(isset($_REQUEST['replace_expression'])){
		$_REQUEST['expr_action'] = 'r';
	}
	else if(isset($_REQUEST['remove_expression']) && zbx_strlen($_REQUEST['remove_expression'])){
		$_REQUEST['expr_action'] = 'R';
		$_REQUEST['expr_target_single'] = $_REQUEST['remove_expression'];
	}
/* FORM ACTIONS */
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['triggerid'])){
		unset($_REQUEST['triggerid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		$trigger = array(
			'expression' => $_REQUEST['expression'],
			'description' => $_REQUEST['description'],
			'type' => $_REQUEST['type'],
			'priority' => $_REQUEST['priority'],
			'status' => isset($_REQUEST['status'])?TRIGGER_STATUS_DISABLED:TRIGGER_STATUS_ENABLED,
			'comments' => $_REQUEST['comments'],
			'url' => $_REQUEST['url'],
			'dependencies' => get_request('dependencies',array()),
		);

		if(isset($_REQUEST['triggerid'])){
			$trigger['triggerid'] = $_REQUEST['triggerid'];
			$result = API::Trigger()->update($trigger);

			show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
		}
		else{
			$result = API::Trigger()->create($trigger);

			show_messages($result, _('Trigger added'), _('Cannot add trigger'));
		}
		if($result)
			unset($_REQUEST['form']);
	}
	else if(isset($_REQUEST['delete']) && isset($_REQUEST['triggerid'])){
		DBstart();
		$result = API::Trigger()->delete($_REQUEST['triggerid']);
		$result = DBend($result);
		show_messages($result, S_TRIGGER_DELETED, S_CANNOT_DELETE_TRIGGER);

		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['triggerid']);
		}
	}
// DEPENDENCE ACTIONS
	else if(isset($_REQUEST['add_dependence']) && isset($_REQUEST['new_dependence'])){
		if(!isset($_REQUEST['dependencies']))
			$_REQUEST['dependencies'] = array();

			foreach($_REQUEST['new_dependence'] as $triggerid) {
			if(!uint_in_array($triggerid, $_REQUEST['dependencies']))
				array_push($_REQUEST['dependencies'], $triggerid);
		}
	}
	else if(isset($_REQUEST['del_dependence'])&&isset($_REQUEST['rem_dependence'])){
		if(isset($_REQUEST['dependencies'])){
			foreach($_REQUEST['dependencies'] as $key => $val){
				if(!uint_in_array($val, $_REQUEST['rem_dependence']))	continue;
				unset($_REQUEST['dependencies'][$key]);
			}
		}
	}
// ------- GO ---------
	else if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['mass_save']) && isset($_REQUEST['g_triggerid'])){
		$result = false;

		$visible = get_request('visible',array());
		$_REQUEST['dependencies'] = get_request('dependencies',array());

		$options = array(
			'triggerids' => $_REQUEST['g_triggerid'],
			'selectDependencies' => 1,
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1
		);
		$triggers = API::Trigger()->get($options);

		DBstart();
		foreach($triggers as $tnum => $db_trig){
			foreach($db_trig as $key => $value){
				if(isset($visible[$key])){
					$db_trig[$key] = $_REQUEST[$key];
				}
			}

			$result = API::Trigger()->update(array(
				'triggerid' =>$db_trig['triggerid'],
				'priority' => $db_trig['priority'],
				'dependencies' => $db_trig['dependencies'],
			));

			if(!$result) break;
		}
		$result = DBend($result);

		show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
		if($result){
			unset($_REQUEST['massupdate']);
			unset($_REQUEST['form']);
		}

		$go_result = $result;
	}
	elseif (str_in_array($_REQUEST['go'], array('activate', 'disable')) && isset($_REQUEST['g_triggerid'])) {
		$go_result = true;

		if ($_REQUEST['go'] == 'activate') {
			$status = TRIGGER_STATUS_ENABLED;
			$statusOld = array('status' => TRIGGER_STATUS_DISABLED);
			$statusNew = array('status' => TRIGGER_STATUS_ENABLED);
		}
		else {
			$status = TRIGGER_STATUS_DISABLED;
			$statusOld = array('status' => TRIGGER_STATUS_ENABLED);
			$statusNew = array('status' => TRIGGER_STATUS_DISABLED);
		}

		DBstart();

		// get requested triggers with permission check
		$options = array(
			'triggerids' => $_REQUEST['g_triggerid'],
			'editable' => true,
			'output' => array('triggerid', 'status'),
			'preservekeys' => true
		);
		$triggers = API::Trigger()->get($options);

		// triggerids to gather child triggers
		$childTriggerIds = array_keys($triggers);

		// triggerids which status must be changed
		$triggerIdsToUpdate = array();
		foreach ($triggers as $triggerid => $trigger){
			if ($trigger['status'] != $status) {
				$triggerIdsToUpdate[] = $triggerid;
			}
		}


		do {
			// gather all triggerids which status should be changed including child triggers
			$options = array(
				'filter' => array('templateid' => $childTriggerIds),
				'output' => array('triggerid', 'status'),
				'preservekeys' => true,
				'nopermissions' => true
			);
			$triggers = API::Trigger()->get($options);

			$childTriggerIds = array_keys($triggers);

			foreach ($triggers as $triggerid => $trigger) {
				if ($trigger['status'] != $status) {
					$triggerIdsToUpdate[] = $triggerid;
				}
			}

		} while (!empty($childTriggerIds));

		DB::update('triggers', array(
			'values' => array('status' => $status),
			'where' => array('triggerid' => $triggerIdsToUpdate)
		));

		// if disable trigger, unknown event must be created
		if ($status == TRIGGER_STATUS_DISABLED) {
			$valueTriggerIds = array();
			$result = DBselect(
				'SELECT t.triggerid'.
						' FROM triggers t,functions f,items i,hosts h'.
						' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND '.DBcondition('t.triggerid', $triggerIdsToUpdate).
						' AND t.value_flags='.TRIGGER_VALUE_FLAG_NORMAL.
						' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'
			);
			while ($row = DBfetch($result)) {
				$valueTriggerIds[] = $row['triggerid'];
			}

			if (!empty($valueTriggerIds)) {
				DB::update('triggers', array(
					'values' => array(
						'value_flags' => TRIGGER_VALUE_FLAG_UNKNOWN,
						'error' => 'Trigger status became "Disabled"'
					),
					'where' => array('triggerid' => $valueTriggerIds)
				));

				addEvent($valueTriggerIds, TRIGGER_VALUE_UNKNOWN);
			}
		}

		// get updated triggers with additional data
		$options = array(
			'triggerids' => $triggerIdsToUpdate,
			'output' => array('triggerid', 'description'),
			'preservekeys' => true,
			'selectHosts' => API_OUTPUT_EXTEND,
			'nopermissions' => true
		);
		$triggers = API::Trigger()->get($options);
		foreach ($triggers as $triggerid => $trigger) {
			$servStatus = (isset($_REQUEST['activate'])) ? get_service_status_of_trigger($triggerid) : 0;

			// updating status to all services by the dependency
			update_services($trigger['triggerid'], $servStatus);

			$host = reset($trigger['hosts']);
			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER, $triggerid,
				$host['host'].':'.$trigger['description'], 'triggers', $statusOld, $statusNew);
		}

		$go_result = DBend($go_result);
		show_messages($go_result, _('Status updated'), _('Cannot update status'));
	}
	elseif ($_REQUEST['go'] == 'copy_to' && isset($_REQUEST['copy']) && isset($_REQUEST['g_triggerid'])) {
		if (isset($_REQUEST['copy_targetid']) && $_REQUEST['copy_targetid'] > 0 && isset($_REQUEST['copy_type'])) {
			if ($_REQUEST['copy_type'] == 0) {	// hosts
				$hosts_ids = $_REQUEST['copy_targetid'];
			}
			else {	// groups
				$hosts_ids = array();
				$group_ids = $_REQUEST['copy_targetid'];

				$db_hosts = DBselect(
					'SELECT DISTINCT h.hostid'.
						' FROM hosts h,hosts_groups hg'.
						' WHERE h.hostid=hg.hostid'.
							' AND '.DBcondition('hg.groupid', $group_ids)
				);
				while ($db_host = DBfetch($db_hosts)) {
					$hosts_ids[] = $db_host['hostid'];
				}
			}

			DBstart();
			$go_result = copyTriggersToHosts(get_request('hostid', 0), $_REQUEST['g_triggerid'], $hosts_ids);
			$go_result = DBend($go_result);

			show_messages($go_result, _('Trigger added'), _('Cannot add trigger'));
			$_REQUEST['go'] = 'none2';
		}
		else {
			show_error_message(_('No target selected'));
		}
	}
	elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['g_triggerid'])) {
		$go_result = API::Trigger()->delete($_REQUEST['g_triggerid']);
		show_messages($go_result, S_TRIGGERS_DELETED, S_CANNOT_DELETE_TRIGGERS);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
		$_REQUEST['go'] = 'none';
	}


	$options = array(
		'groups' => array('not_proxy_hosts' => 1, 'editable' => 1),
		'hosts' => array('templated_hosts' => 1, 'editable' => 1),
		'triggers' => array('editable' => 1),
		'groupid' => get_request('groupid', null),
		'hostid' => get_request('hostid', null),
		'triggerid' => get_request('triggerid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;

	if($pageFilter->triggerid > 0){
		$_REQUEST['triggerid'] = $pageFilter->triggerid;
	}


	$triggers_wdgt = new CWidget();

	$form = new CForm('get');

// Config
	if(!isset($_REQUEST['form'])){
		$form->addItem(new CSubmit('form', S_CREATE_TRIGGER));
	}

	$triggers_wdgt->addPageHeader(S_CONFIGURATION_OF_TRIGGERS_BIG, $form);


	if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['g_triggerid'])){
		$triggers_wdgt->addItem(insert_mass_update_trigger_form());
	}
	else if(isset($_REQUEST['form'])){
		$triggers_wdgt->addItem(insert_trigger_form());
	}
	else if(($_REQUEST['go'] == 'copy_to') && isset($_REQUEST['g_triggerid'])){
		$triggers_wdgt->addItem(insert_copy_elements_to_forms('g_triggerid'));
	}
	else{
/* TABLE */

// Triggers Header
		$r_form = new CForm('get');

		$r_form->addItem(array(S_GROUP.SPACE,$pageFilter->getGroupsCB()));
		$r_form->addItem(array(SPACE.S_HOST.SPACE,$pageFilter->getHostsCB()));

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$tr_link = new CLink($showdisabled?S_HIDE_DISABLED_TRIGGERS : S_SHOW_DISABLED_TRIGGERS,'triggers.php?showdisabled='.($showdisabled?0:1));

		$triggers_wdgt->addHeader(S_TRIGGERS_BIG, $r_form);
		$triggers_wdgt->addHeader($numrows, array('[ ',$tr_link,' ]'));
// ----------------

		$form = new CForm();
		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);

// Header Host
		if($_REQUEST['hostid'] > 0){
			$tbl_header_host = get_header_host_table($_REQUEST['hostid'],'triggers');
			$triggers_wdgt->addItem($tbl_header_host);
		}

		$form->setName('triggers');
		$form->addVar('hostid', $_REQUEST['hostid']);

		$table->setHeader(array(
			new CCheckBox('all_triggers', NULL, "checkAll('".$form->getName()."','all_triggers','g_triggerid');"),
			make_sorting_header(_('Severity'), 'priority'),
			($_REQUEST['hostid'] > 0) ? NULL : _('Host'),
			make_sorting_header(_('Name'), 'description'),
			_('Expression'),
			make_sorting_header(_('Status'), 'status'),
			_('Error')
		));
// get Triggers
		$triggers = array();

		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();

		if($pageFilter->hostsSelected){
			$options = array(
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array(),
				'sortfield' => $sortfield,
				'sortorder' => $sortorder,
				'limit' => ($config['search_limit']+1)
			);

			if($showdisabled == 0) $options['filter']['status'] = TRIGGER_STATUS_ENABLED;

			if($pageFilter->hostid > 0) $options['hostids'] = $pageFilter->hostid;
			else if($pageFilter->groupid > 0) $options['groupids'] = $pageFilter->groupid;


			$triggers = API::Trigger()->get($options);
		}

// sorting && paging
		order_result($triggers, $sortfield, $sortorder);
		$paging = getPagingLine($triggers);
//---

		$options = array(
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_EXTEND,
			'selectItems' => API_OUTPUT_EXTEND,
			'selectFunctions' => API_OUTPUT_EXTEND,
			'selectDependencies' => API_OUTPUT_EXTEND,
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
		);

		$triggers = API::Trigger()->get($options);
		order_result($triggers, $sortfield, $sortorder);

		$realHosts = getParentHostsByTriggers($triggers);

		foreach($triggers as $tnum => $trigger){
			$triggerid = $trigger['triggerid'];

			$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');
			$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
			$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

			$description = array();
			if($trigger['templateid'] > 0){
				if(!isset($realHosts[$triggerid])){
					$description[] = new CSpan('HOST','unknown');
					$description[] = ':';
				}
				else{
					$real_hosts = $realHosts[$triggerid];
					$real_host = reset($real_hosts);
					$description[] = new CLink($real_host['name'], 'triggers.php?&hostid='.$real_host['hostid'], 'unknown');
					$description[] = ':';
				}
			}

			if(!empty($trigger['discoveryRule'])){
				$description[] = new CLink($trigger['discoveryRule']['name'], 'trigger_prototypes.php?parent_discoveryid='.
					$trigger['discoveryRule']['itemid'],'gold');
				$description[] = ':'.$trigger['description'];
			}
			else{
				$description[] = new CLink($trigger['description'], 'triggers.php?form=update&triggerid='.$triggerid);
			}


//add dependencies {
			$deps = $trigger['dependencies'];
			if(count($deps) > 0){
				$description[] = array(BR(), bold(_('Depends on').' : '));
				foreach($deps as $dnum => $dep_trigger) {
					$description[] = BR();

					$hosts = get_hosts_by_triggerid($dep_trigger['triggerid']);
					while($host = DBfetch($hosts)){
						$description[] = $host['name'];
						$description[] = ', ';
					}

					array_pop($description);
					$description[] = ' : ';
					$description[] = $dep_trigger['description'];
				}
			}
// } add dependencies

			if($trigger['value_flags'] == TRIGGER_VALUE_FLAG_NORMAL) $trigger['error'] = '';

			$templated = false;
			foreach($trigger['hosts'] as $hostid => $host){
				$templated |= (HOST_STATUS_TEMPLATE == $host['status']);
			}

			if(!zbx_empty($trigger['error']) && !$templated){
				$error = new CDiv(SPACE,'status_icon iconerror');
				$error->setHint($trigger['error'], '', 'on');
			}
			else{
				$error = new CDiv(SPACE,'status_icon iconok');
			}

			$status_link = 'triggers.php?go='.(($trigger['status'] == TRIGGER_STATUS_DISABLED) ? 'activate' : 'disable').
				'&g_triggerid%5B%5D='.$triggerid;

			if($trigger['status'] == TRIGGER_STATUS_DISABLED){
				$status = new CLink(S_DISABLED, $status_link, 'disabled');
			}
			else if($trigger['status'] == TRIGGER_STATUS_ENABLED){
				$status = new CLink(S_ENABLED, $status_link, 'enabled');
			}

			$hosts = null;
			if($_REQUEST['hostid'] == 0){
				$hosts = array();
				foreach($trigger['hosts'] as $hostid => $host){
					if(!empty($hosts)) $hosts[] = ', ';
					$hosts[] = $host['name'];
				}
			}


			$cb = new CCheckBox('g_triggerid['.$triggerid.']', NULL, NULL, $triggerid);
			$cb->setEnabled(empty($trigger['discoveryRule']));

			$table->addRow(array(
				$cb,
				getSeverityCell($trigger['priority']),
				$hosts,
				$description,
				triggerExpression($trigger,1),
				$status,
				$error
			));

			$triggers[$tnum] = $trigger;
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('activate',S_ACTIVATE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('massupdate',S_MASS_UPDATE);
		//$goOption->setAttribute('confirm',S_MASS_UPDATE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('copy_to',S_COPY_SELECTED_TO);
		//$goOption->setAttribute('confirm',S_COPY_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CSubmit('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "g_triggerid";');

		$footer = get_table_header(array($goBox, $goButton));

		$table = array($paging,$table,$paging,$footer);

		$form->addItem($table);
		$triggers_wdgt->addItem($form);
	}

	$triggers_wdgt->show();


require_once('include/page_footer.php');

?>