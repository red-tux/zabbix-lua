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
/**
 * File containing CHostGroup class for API.
 * @package API
 */
/**
 * Class containing methods for operations with HostGroups
 */
class CHostGroup extends CZBXAPI{

	protected $tableName = 'groups';

	protected $tableAlias = 'g';

/**
 * Get HostGroups
 *
 * @param array $params
 * @return array
 */
	public function get($params) {
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sort_columns = array('groupid', 'name');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sql_parts = array(
			'select'	=> array('groups' => 'g.groupid'),
			'from'		=> array('groups' => 'groups g'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'templateids'				=> null,
			'graphids'					=> null,
			'triggerids'				=> null,
			'maintenanceids'			=> null,
			'monitored_hosts'			=> null,
			'templated_hosts'			=> null,
			'real_hosts'				=> null,
			'not_proxy_hosts'			=> null,
			'with_items'				=> null,
			'with_monitored_items'		=> null,
			'with_historical_items'		=> null,
			'with_triggers'				=> null,
			'with_monitored_triggers'	=> null,
			'with_httptests'			=> null,
			'with_monitored_httptests'	=> null,
			'with_graphs'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($def_options, $params);

		if (is_array($options['output'])) {
			unset($sql_parts['select']['groups']);

			$dbTable = DB::getSchema('groups');
			$sql_parts['select']['groupid'] = 'g.groupid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sql_parts['select'][$field] = 'g.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $user_type || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = 'r.id=g.groupid';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS('.
									' SELECT gg.groupid'.
										' FROM groups gg,rights rr,users_groups ugg'.
										' WHERE rr.id=g.groupid'.
											' AND rr.groupid=ugg.usrgrpid'.
											' AND ugg.userid='.$userid.
											' AND rr.permission<'.$permission.')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			$sql_parts['where']['groupid'] = DBcondition('g.groupid', $options['groupids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['hostid'] = 'hg.hostid';
			}
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.hostid', $options['hostids']);
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}
			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
		}

		// maintenanceids
		if (!is_null($options['maintenanceids'])) {
			zbx_value2array($options['maintenanceids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['maintenanceid'] = 'mg.maintenanceid';
			}
			$sql_parts['from']['maintenances_groups'] = 'maintenances_groups mg';
			$sql_parts['where'][] = DBcondition('mg.maintenanceid', $options['maintenanceids']);
			$sql_parts['where']['hmh'] = 'g.groupid=mg.groupid';
		}

		// monitored_hosts, real_hosts, templated_hosts, not_proxy_hosts
		if (!is_null($options['monitored_hosts'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
		}
		elseif (!is_null($options['real_hosts'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}
		elseif (!is_null($options['templated_hosts'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
		}
		elseif (!is_null($options['not_proxy_hosts'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'NOT h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')';
		}

		// with_items, with_monitored_items, with_historical_items
		if (!is_null($options['with_items'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid)';
		}
		elseif (!is_null($options['with_monitored_items'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
		}
		elseif (!is_null($options['with_historical_items'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
		}

		// with_triggers, with_monitored_triggers
		if (!is_null($options['with_triggers'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT t.triggerid'.
										' FROM items i, functions f, triggers t'.
										' WHERE i.hostid=hg.hostid'.
											' AND f.itemid=i.itemid'.
											' AND t.triggerid=f.triggerid)';
		}
		elseif (!is_null($options['with_monitored_triggers'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT t.triggerid'.
										' FROM items i, functions f, triggers t'.
										' WHERE i.hostid=hg.hostid'.
											' AND i.status='.ITEM_STATUS_ACTIVE.
											' AND i.itemid=f.itemid'.
											' AND f.triggerid=t.triggerid'.
											' AND t.status='.TRIGGER_STATUS_ENABLED.')';
		}

		// with_httptests, with_monitored_httptests
		if (!is_null($options['with_httptests'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT a.applicationid'.
									' FROM applications a, httptest ht'.
									' WHERE a.hostid=hg.hostid'.
										' AND ht.applicationid=a.applicationid)';
		}
		elseif (!is_null($options['with_monitored_httptests'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT a.applicationid'.
									' FROM applications a, httptest ht'.
									' WHERE a.hostid=hg.hostid'.
										' AND ht.applicationid=a.applicationid'.
										' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
		}

		// with_graphs
		if (!is_null($options['with_graphs'])) {
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT DISTINCT i.itemid'.
										' FROM items i, graphs_items gi'.
										' WHERE i.hostid=hg.hostid'.
											' AND i.itemid=gi.itemid)';
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sql_parts['select']['groups'] = 'g.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sql_parts['select'] = array('COUNT(DISTINCT g.groupid) AS rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sql_parts['group'] as $key => $fields) {
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('groups g', $options, $sql_parts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('groups g', $options, $sql_parts);
		}

		// sorting
		zbx_db_sorting($sql_parts, $options, $sort_columns, 'g');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$groupids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if (!empty($sql_parts['select'])) {
			$sql_select .= implode(',', $sql_parts['select']);
		}
		if (!empty($sql_parts['from'])) {
			$sql_from .= implode(',', $sql_parts['from']);
		}
		if (!empty($sql_parts['where'])) {
			$sql_where .= ' AND '.implode(' AND ', $sql_parts['where']);
		}
		if (!empty($sql_parts['order'])) {
			$sql_order .= ' ORDER BY '.implode(',', $sql_parts['order']);
		}
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('g.groupid', $nodeids).
					$sql_where.
					$sql_order;
		$res = DBselect($sql, $sql_limit);
		while ($group = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $group;
				}
				else {
					$result = $group['rowscount'];
				}
			}
			else {
				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$group['groupid']] = array('groupid' => $group['groupid']);
				}
				else {
					$groupids[$group['groupid']] = $group['groupid'];

					if (!isset($result[$group['groupid']])) {
						$result[$group['groupid']] = array();
					}
					if (!is_null($options['selectTemplates']) && !isset($result[$group['groupid']]['templates'])) {
						$result[$group['groupid']]['templates'] = array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$group['groupid']]['hosts'])) {
						$result[$group['groupid']]['hosts'] = array();
					}

					// hostids
					if (isset($group['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$group['groupid']]['hosts'])) {
							$result[$group['groupid']]['hosts'] = array();
						}
						$result[$group['groupid']]['hosts'][] = array('hostid' => $group['hostid']);
						unset($group['hostid']);
					}

					// graphids
					if (isset($group['graphid'])) {
						if (!isset($result[$group['groupid']]['graphs'])) {
							$result[$group['groupid']]['graphs'] = array();
						}
						$result[$group['groupid']]['graphs'][] = array('graphid' => $group['graphid']);
						unset($group['hostid']);
					}

					// maintenanceids
					if (isset($group['maintenanceid'])) {
						if (!isset($result[$group['groupid']]['maintenanceid'])) {
							$result[$group['groupid']]['maintenances'] = array();
						}
						$result[$group['groupid']]['maintenances'][] = array('maintenanceid' => $group['maintenanceid']);
						unset($group['maintenanceid']);
					}

					// triggerids
					if (isset($group['triggerid'])) {
						if (!isset($result[$group['groupid']]['triggers'])) {
							$result[$group['groupid']]['triggers'] = array();
						}
						$result[$group['groupid']]['triggers'][] = array('triggerid' => $group['triggerid']);
						unset($group['triggerid']);
					}
					$result[$group['groupid']] += $group;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// adding hosts
		if (!is_null($options['selectHosts'])) {
			$obj_params = array(
				'nodeids' => $nodeids,
				'groupids' => $groupids,
				'preservekeys' => 1
			);

			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)) {
				$obj_params['output'] = $options['selectHosts'];
				$hosts = API::Host()->get($obj_params);

				if (!is_null($options['limitSelects'])) {
					order_result($hosts, 'host');
				}

				$count = array();
				foreach ($hosts as $hostid => $host) {
					$hgroups = $host['groups'];
					unset($host['groups']);
					foreach ($hgroups as $group) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$group['groupid']])) {
								$count[$group['groupid']] = 0;
							}
							$count[$group['groupid']]++;

							if ($count[$group['groupid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$group['groupid']]['hosts'][] = $hosts[$hostid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectHosts']) {
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$hosts = API::Host()->get($obj_params);
				$hosts = zbx_toHash($hosts, 'groupid');
				foreach ($result as $groupid => $group) {
					if (isset($hosts[$groupid])) {
						$result[$groupid]['hosts'] = $hosts[$groupid]['rowscount'];
					}
					else {
						$result[$groupid]['hosts'] = 0;
					}
				}
			}
		}

		// adding templates
		if (!is_null($options['selectTemplates'])) {
			$obj_params = array(
				'nodeids' => $nodeids,
				'groupids' => $groupids,
				'preservekeys' => 1
			);

			if (is_array($options['selectTemplates']) || str_in_array($options['selectTemplates'], $subselects_allowed_outputs)) {
				$obj_params['output'] = $options['selectTemplates'];
				$templates = API::Template()->get($obj_params);
				if (!is_null($options['limitSelects'])) {
					order_result($templates, 'host');
				}

				$count = array();
				foreach ($templates as $templateid => $template) {
					$hgroups = $template['groups'];
					unset($template['groups']);
					foreach ($hgroups as $num => $group) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$group['groupid']])) {
								$count[$group['groupid']] = 0;
							}
							$count[$group['groupid']]++;

							if ($count[$group['groupid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$group['groupid']]['templates'][] = $templates[$templateid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectTemplates']) {
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$templates = API::Template()->get($obj_params);
				$templates = zbx_toHash($templates, 'groupid');
				foreach ($result as $groupid => $group) {
					if (isset($templates[$groupid])) {
						$result[$groupid]['templates'] = $templates[$groupid]['rowscount'];
					}
					else {
						$result[$groupid]['templates'] = 0;
					}
				}
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Get HostGroup ID by name
	 *
	 * @param array $data
	 * @param array $data['name']
	 * @return string|boolean HostGroup ID or false if error
	 */
	public function getObjects($hostgroupData) {
		$options = array(
			'filter' => $hostgroupData,
			'output' => API_OUTPUT_EXTEND
		);

		if (isset($hostgroupData['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($hostgroupData['node']);
		}
		elseif (isset($hostgroupData['nodeids'])) {
			$options['nodeids'] = $hostgroupData['nodeids'];
		}
		else {
			$options['nodeids'] = get_current_nodeid(false);
		}
		$result = $this->get($options);
		return $result;
	}

	public function exists($object) {
		$keyFields = array('name', 'groupid');

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if (isset($object['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		}
		elseif (isset($object['nodeids'])) {
			$options['nodeids'] = $object['nodeids'];
		}
		$objs = $this->get($options);
		return !empty($objs);
	}

	/**
	 * Add hostGroups
	 *
	 * @param array $groups array with HostGroup names
	 * @param array $groups['name']
	 * @return array
	 */
	public function create($groups) {
		$groups = zbx_toArray($groups);
		$insert = array();

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create host groups.'));
		}

		foreach ($groups as $num => $group) {
			if (!is_array($group) || !isset($group['name']) || empty($group['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter "name".'));
			}
			if ($this->exists(array('name' => $group['name']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group "%s" already exists.', $group['name']));
			}
			$insert[] = $group;
		}
		$groupids = DB::insert('groups', $insert);
		return array('groupids' => $groupids);
	}

	/**
	 * Update HostGroup
	 *
	 * @param array $groups
	 * @param array $groups[0]['name'], ...
	 * @param array $groups[0]['groupid'], ...
	 * @return boolean
	 */
	public function update($groups) {
		$groups = zbx_toArray($groups);
		$groupids = zbx_objectValues($groups, 'groupid');

		if (empty($groups)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// permissions
		$options = array(
			'groupids' => $groupids,
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$upd_groups = $this->get($options);
		foreach ($groups as $gnum => $group) {
			if (!isset($upd_groups[$group['groupid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation'));
			}
		}

		// name duplicate check
		$options = array(
			'filter' => array(
				'name' => zbx_objectValues($groups, 'name')
			),
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'nopermissions' => 1
		);
		$groupsNames = $this->get($options);
		$groupsNames = zbx_toHash($groupsNames, 'name');

		foreach ($groups as $num => $group) {
			if (isset($group['name']) && isset($groupsNames[$group['name']]) && (bccomp($groupsNames[$group['name']]['groupid'], $group['groupid']) != 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group "%s" already exists.', $group['name']));
			}

			// prevents updating several groups with same name
			$groupsNames[$group['name']] = array('groupid' => $group['groupid']);

			$sql = 'UPDATE groups SET name='.zbx_dbstr($group['name']).' WHERE groupid='.$group['groupid'];
			if (!DBexecute($sql)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
			}
		}
		return array('groupids' => $groupids);
	}

	/**
	 * Delete HostGroups
	 *
	 * @param array $groupids
	 * @return boolean
	 */
	public function delete($groupids) {
			if (empty($groupids)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
			}
			$groupids = zbx_toArray($groupids);

			$options = array(
				'groupids' => $groupids,
				'editable' => true,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$del_groups = $this->get($options);
			foreach ($groupids as $groupid) {
				if (!isset($del_groups[$groupid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation'));
				}
			}

			$dlt_groupids = getDeletableHostGroups($groupids);
			if (count($groupids) != count($dlt_groupids)) {
				foreach ($groupids as $num => $groupid) {
					if ($del_groups[$groupid]['internal'] == ZBX_INTERNAL_GROUP) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
								S_GROUP.' ['.$del_groups[$groupid]['name'].'] '.S_INTERNAL_AND_CANNOT_DELETED_SMALL);
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Group "%s" cannot be deleted, because some hosts depend on it.', $del_groups[$groupid]['name']));
					}
			}
		}

		$dbScripts = API::Script()->get(array(
			'groupids' => $groupids,
			'nopermissions' => true
		));

		if (!empty($dbScripts)) {
			foreach ($dbScripts as $snum => $script) {
				if ($script['groupid'] == 0) {
					continue;
				}
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Group "%s" cannot be deleted, because it is used in a global script.', $del_groups[$script['groupid']]['name']));
				}
			}

			// delete screens items
			$resources = array(
				SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
				SCREEN_RESOURCE_HOSTS_INFO,
				SCREEN_RESOURCE_TRIGGERS_INFO,
				SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
				SCREEN_RESOURCE_DATA_OVERVIEW
			);
			DB::delete('screens_items', array(
				'resourceid' => $groupids,
				'resourcetype' => $resources
			));

		// delete sysmap element
		if (!empty($groupids)) {
			DB::delete('sysmaps_elements', array('elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP, 'elementid' => $groupids));
		}

		// disable actions
		// actions from conditions
		$actionids = array();
		$sql = 'SELECT DISTINCT c.actionid'.
				' FROM conditions c'.
				' WHERE c.conditiontype='.CONDITION_TYPE_HOST_GROUP.
					' AND '.DBcondition('c.value', $groupids);
		$db_actions = DBselect($sql);
		while ($db_action = DBfetch($db_actions)) {
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		// actions from operations
		$sql = 'SELECT DISTINCT o.actionid'.
				' FROM operations o, opgroup og'.
				' WHERE o.operationid=og.operationid'.
					' AND '.DBcondition('og.groupid', $groupids);
		$db_actions = DBselect($sql);
		while ($db_action = DBfetch($db_actions)) {
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		if (!empty($actionids)) {
			$update = array();
			$update[] = array(
				'values' => array('status' => ACTION_STATUS_DISABLED),
				'where' => array('actionid' => $actionids)
			);
			DB::update('actions', $update);
		}

		// delete action conditions
		DB::delete('conditions', array(
			'conditiontype' => CONDITION_TYPE_HOST_GROUP,
			'value' => $groupids
		));

		// delete action operation commands
		$operationids = array();
		$sql = 'SELECT DISTINCT og.operationid'.
				' FROM opgroup og'.
				' WHERE '.DBcondition('og.groupid', $groupids);
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}
		DB::delete('opgroup', array(
			'groupid' => $groupids
		));

		// delete empty operations
		$delOperationids = array();
		$sql = 'SELECT DISTINCT o.operationid'.
				' FROM operations o'.
				' WHERE '.DBcondition('o.operationid', $operationids).
					' AND NOT EXISTS (SELECT og.opgroupid FROM opgroup og WHERE og.operationid=o.operationid)';
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', array(
			'operationid' => $delOperationids,
		));

		// host groups
		DB::delete('groups', array(
			'groupid' => $groupids
		));

		// TODO: remove audit
		foreach ($groupids as $groupid) {
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST_GROUP, $groupid, $del_groups[$groupid]['name'], 'groups', null, null);
		}

		return array('groupids' => $groupids);
	}

	/**
	 * Add Hosts to HostGroups. All Hosts are added to all HostGroups.
	 *
	 * @param array $data
	 * @param array $data['groups']
	 * @param array $data['hosts']
	 * @param array $data['templates']
	 * @return boolean
	 */
	public function massAdd($data) {
		$groups = zbx_toArray($data['groups']);
		$groupids = zbx_objectValues($groups, 'groupid');

		$options = array(
			'groupids' => $groupids,
			'editable' => 1,
			'preservekeys' => 1
		);
		$upd_groups = $this->get($options);
		foreach ($groups as $group) {
			if (!isset($upd_groups[$group['groupid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation'));
			}
		}

		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');

		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');
		$objectids = array_merge($hostids, $templateids);

		$linked = array();
		$sql = 'SELECT hg.hostid,hg.groupid'.
				' FROM hosts_groups hg'.
				' WHERE '.DBcondition('hg.hostid', $objectids).
					' AND '.DBcondition('hg.groupid', $groupids);
		$linked_db = DBselect($sql);
		while ($pair = DBfetch($linked_db)) {
			$linked[$pair['groupid']][$pair['hostid']] = 1;
		}

		$insert = array();
		foreach ($groupids as $groupid) {
			foreach ($objectids as $hostid) {
				if (isset($linked[$groupid][$hostid])) {
					continue;
				}
				$insert[] = array('hostid' => $hostid, 'groupid' => $groupid);
			}
		}
		DB::insert('hosts_groups', $insert);
		return array('groupids' => $groupids);
	}

	/**
	 * Remove Hosts from HostGroups
	 *
	 * @param array $data
	 * @param array $data['groupids']
	 * @param array $data['hostids']
	 * @param array $data['templateids']
	 * @return boolean
	 */
	public function massRemove($data) {
		$groupids = zbx_toArray($data['groupids'], 'groupid');

		$options = array(
			'groupids' => $groupids,
			'editable' => 1,
			'preservekeys' => 1,
			'output' => API_OUTPUT_SHORTEN
		);
		$upd_groups = $this->get($options);
		foreach ($groupids as $groupid) {
			if (!isset($upd_groups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation'));
			}
		}
		$hostids = isset($data['hostids']) ? zbx_toArray($data['hostids']) : array();
		$templateids = isset($data['templateids']) ? zbx_toArray($data['templateids']) : array();
		$objectids_to_unlink = array_merge($hostids, $templateids);
		if (!empty($objectids_to_unlink)) {
			$unlinkable = getUnlinkableHosts($groupids, $objectids_to_unlink);
			if (count($objectids_to_unlink) != count($unlinkable)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'One of the objects is left without host group.');
			}

			DB::delete('hosts_groups', array(
				'hostid' => $objectids_to_unlink,
				'groupid' => $groupids
			));
		}
		return array('groupids' => $groupids);
	}

	/**
	 * Update HostGroups with new Hosts (rewrite)
	 *
	 * @param array $data
	 * @param array $data['groups']
	 * @param array $data['hosts']
	 * @param array $data['templates']
	 * @return boolean
	 */
	public function massUpdate($data) {
		$groups = zbx_toArray($data['groups']);
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$groupids = zbx_objectValues($groups, 'groupid');
		$hostids = zbx_objectValues($hosts, 'hostid');
		$templateids = zbx_objectValues($templates, 'templateid');
		$hosts_to_unlink = $hosts_to_link = array();

		$options = array(
			'groupids' => $groupids,
			'preservekeys' => 1
		);
		if (!is_null($hosts)) {
			$groups_hosts = API::Host()->get($options);
			$hosts_to_unlink = array_diff(array_keys($groups_hosts), $hostids);
			$hosts_to_link = array_diff($hostids, array_keys($groups_hosts));
		}

		$templates_to_unlink = $templates_to_link = array();
		if (!is_null($templates)) {
			$groups_templates = API::Template()->get($options);
			$templates_to_unlink = array_diff(array_keys($groups_templates), $templateids);
			$templates_to_link = array_diff($templateids, array_keys($groups_templates));
		}
		$objectids_to_link = array_merge($hosts_to_link, $templates_to_link);
		$objectids_to_unlink = array_merge($hosts_to_unlink, $templates_to_unlink);

		// permission
		$options = array(
			'groupids' => $groupids,
			'editable' => 1,
			'preservekeys' => 1
		);
		$allowed_groups = $this->get($options);
		foreach ($groups as $group) {
			if (!isset($allowed_groups[$group['groupid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation'));
			}
		}

		if (!is_null($hosts)) {
			$hosts_to_check = array_merge($hosts_to_link, $hosts_to_unlink);
			$options = array(
				'hostids' => $hosts_to_check,
				'editable' => 1,
				'preservekeys' => 1
			);
			$allowed_hosts = API::Host()->get($options);
			foreach ($hosts_to_check as $hostid) {
				if (!isset($allowed_hosts[$hostid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation'));
				}
			}
		}

		if (!is_null($templates)) {
			$templates_to_check = array_merge($templates_to_link, $templates_to_unlink);
			$options = array(
				'templateids' => $templates_to_check,
				'editable' => 1,
				'preservekeys' => 1
			);
			$allowed_templates = API::Template()->get($options);
			foreach ($templates_to_check as $templateid) {
				if (!isset($allowed_templates[$templateid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation'));
				}
			}
		}

		$unlinkable = getUnlinkableHosts($groupids, $objectids_to_unlink);
		if (count($objectids_to_unlink) != count($unlinkable)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'One of the objects is left without host group.');
		}

		$sql = 'DELETE FROM hosts_groups WHERE '.DBcondition('groupid', $groupids).' AND '.DBcondition('hostid', $objectids_to_unlink);
		if (!DBexecute($sql)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}

		foreach ($groupids as $groupid) {
			foreach ($objectids_to_link as $objectid) {
				$hostgroupid = get_dbid('hosts_groups', 'hostgroupid');
				$result = DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES ($hostgroupid, $objectid, $groupid)");
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}
			}
		}
		return array('groupids' => $groupids);
	}

	public function isReadable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'groupids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));
		return count($ids) == $count;
	}

	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'groupids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return count($ids) == $count;
	}
}
?>
