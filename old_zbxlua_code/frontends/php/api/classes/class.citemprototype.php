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
 * @package API
 */

class CItemprototype extends CItemGeneral{

	protected $tableName = 'items';

	protected $tableAlias = 'i';


/**
 * Get Itemprototype data
 */
	public function get($options = array()) {
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sort_columns = array('itemid', 'name', 'key_', 'delay', 'type', 'status');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM);

		$sql_parts = array(
			'select'	=> array('items' => 'i.itemid'),
			'from'		=> array('items' => 'items i'),
			'where'		=> array('i.flags='.ZBX_FLAG_DISCOVERY_CHILD),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'itemids'					=> null,
			'discoveryids'				=> null,
			'inherited'					=> null,
			'templated'					=> null,
			'monitored'					=> null,
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
			'selectApplications'		=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($def_options, $options);

		if (is_array($options['output'])) {
			unset($sql_parts['select']['items']);

			$dbTable = DB::getSchema('items');
			$sql_parts['select']['itemid'] = 'i.itemid';
			foreach($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sql_parts['select'][$field] = 'i.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
								' SELECT hgg.groupid '.
								' FROM hosts_groups hgg, rights rr, users_groups gg '.
								' WHERE hgg.hostid=hg.hostid '.
									' AND rr.id=hgg.groupid '.
									' AND rr.groupid=gg.usrgrpid '.
									' AND gg.userid='.$userid.
									' AND rr.permission<'.$permission.')';
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);

			if(!is_null($options['hostids'])){
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else{
				$options['hostids'] = $options['templateids'];
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['where']['hostid'] = DBcondition('i.hostid', $options['hostids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['i'] = 'i.hostid';
			}
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);

			$sql_parts['where']['itemid'] = DBcondition('i.itemid', $options['itemids']);
		}

// discoveryids
		if(!is_null($options['discoveryids'])){
			zbx_value2array($options['discoveryids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['discoveryid'] = 'id.parent_itemid';
			}

			$sql_parts['from']['item_discovery'] = 'item_discovery id';
			$sql_parts['where'][] = DBcondition('id.parent_itemid', $options['discoveryids']);
			$sql_parts['where']['idi'] = 'i.itemid=id.itemid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['id'] = 'id.parent_itemid';
			}
		}

// inherited
		if(!is_null($options['inherited'])){
			if($options['inherited'])
				$sql_parts['where'][] = 'i.templateid IS NOT NULL';
			else
				$sql_parts['where'][] = 'i.templateid IS NULL';
		}

// templated
		if(!is_null($options['templated'])){
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if($options['templated'])
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			else
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
		}

// monitored
		if(!is_null($options['monitored'])){
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if($options['monitored']){
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sql_parts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else{
				$sql_parts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}


// search
		if(is_array($options['search'])){
			zbx_db_search('items i', $options, $sql_parts);
		}

// --- FILTER ---
		if(is_array($options['filter'])){
			zbx_db_filter('items i', $options, $sql_parts);

			if(isset($options['filter']['host'])){
				zbx_value2array($options['filter']['host']);

				$sql_parts['from']['hosts'] = 'hosts h';
				$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
				$sql_parts['where']['h'] = DBcondition('h.host', $options['filter']['host']);
			}
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['items'] = 'i.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT i.itemid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		zbx_db_sorting($sql_parts, $options, $sort_columns, 'i');

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//----------

		$itemids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['group'] = array_unique($sql_parts['group']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_group = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('i.itemid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
//SDI($sql);
		$res = DBselect($sql, $sql_limit);
		while($item = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $item;
				else
					$result = $item['rowscount'];
			}
			else{
				$itemids[$item['itemid']] = $item['itemid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$item['itemid']] = array('itemid' => $item['itemid']);
				}
				else{
					if(!isset($result[$item['itemid']]))
						$result[$item['itemid']]= array();

					if(!is_null($options['selectHosts']) && !isset($result[$item['itemid']]['hosts'])){
						$result[$item['itemid']]['hosts'] = array();
					}
					if(!is_null($options['selectApplications']) && !isset($result[$item['itemid']]['applications'])){
						$result[$item['itemid']]['applications'] = array();
					}
					if(!is_null($options['selectTriggers']) && !isset($result[$item['itemid']]['triggers'])){
						$result[$item['itemid']]['triggers'] = array();
					}
					if(!is_null($options['selectGraphs']) && !isset($result[$item['itemid']]['graphs'])){
						$result[$item['itemid']]['graphs'] = array();
					}

// hostids
					if(isset($item['hostid']) && is_null($options['selectHosts'])){
						if(!isset($result[$item['itemid']]['hosts'])) $result[$item['itemid']]['hosts'] = array();
						$result[$item['itemid']]['hosts'][] = array('hostid' => $item['hostid']);
					}

// triggerids
					if(isset($item['triggerid']) && is_null($options['selectTriggers'])){
						if(!isset($result[$item['itemid']]['triggers']))
							$result[$item['itemid']]['triggers'] = array();

						$result[$item['itemid']]['triggers'][] = array('triggerid' => $item['triggerid']);
						unset($item['triggerid']);
					}
// graphids
					if(isset($item['graphid']) && is_null($options['selectGraphs'])){
						if(!isset($result[$item['itemid']]['graphs']))
							$result[$item['itemid']]['graphs'] = array();

						$result[$item['itemid']]['graphs'][] = array('graphid' => $item['graphid']);
						unset($item['graphid']);
					}
// discoveryids
					if(isset($item['discoveryids'])){
						if(!isset($result[$item['itemid']]['discovery']))
							$result[$item['itemid']]['discovery'] = array();

						$result[$item['itemid']]['discovery'][] = array('ruleid' => $item['item_parentid']);
						unset($item['item_parentid']);
					}

					$result[$item['itemid']] += $item;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			return $result;
		}

// Adding Objects
// Adding hosts
		if(!is_null($options['selectHosts'])){
			if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
				$obj_params = array(
					'nodeids' => $nodeids,
					'itemids' => $itemids,
					'templated_hosts' => 1,
					'output' => $options['selectHosts'],
					'nopermissions' => 1,
					'preservekeys' => 1
				);
				$hosts = API::Host()->get($obj_params);

				foreach($hosts as $hostid => $host){
					$hitems = $host['items'];
					unset($host['items']);
					foreach($hitems as $inum => $item){
						$result[$item['itemid']]['hosts'][] = $host;
					}
				}

				$templates = API::Template()->get($obj_params);
				foreach($templates as $templateid => $template){
					$titems = $template['items'];
					unset($template['items']);
					foreach($titems as $inum => $item){
						$result[$item['itemid']]['hosts'][] = $template;
					}
				}
			}
		}

// Adding triggers
		if(!is_null($options['selectTriggers'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'discoveryids' => $itemids,
				'preservekeys' => 1,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CHILD),
			);

			if(in_array($options['selectTriggers'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectTriggers'];
				$triggers = API::Trigger()->get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($triggers, 'name');
				foreach($triggers as $triggerid => $trigger){
					unset($triggers[$triggerid]['items']);
					$count = array();
					foreach($trigger['items'] as $item){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$item['itemid']])) $count[$item['itemid']] = 0;
							$count[$item['itemid']]++;

							if($count[$item['itemid']] > $options['limitSelects']) continue;
						}

						$result[$item['itemid']]['triggers'][] = &$triggers[$triggerid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectTriggers']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$triggers = API::Trigger()->get($obj_params);

				$triggers = zbx_toHash($triggers, 'parent_itemid');
				foreach($result as $itemid => $item){
					if(isset($triggers[$itemid]))
						$result[$itemid]['triggers'] = $triggers[$itemid]['rowscount'];
					else
						$result[$itemid]['triggers'] = 0;
				}
			}
		}

// Adding applications
		if(!is_null($options['selectApplications']) && str_in_array($options['selectApplications'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['selectApplications'],
				'itemids' => $itemids,
				'preservekeys' => 1
			);
			$applications = API::Application()->get($obj_params);
			foreach($applications as $applicationid => $application){
				$aitems = $application['items'];
				unset($application['items']);
				foreach($aitems as $inum => $item){
					$result[$item['itemid']]['applications'][] = $application;
				}
			}
		}

// Adding graphs
		if(!is_null($options['selectGraphs'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'discoveryids' => $itemids,
				'preservekeys' => 1,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CHILD),
			);

			if(in_array($options['selectGraphs'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectGraphs'];
				$graphs = API::Graph()->get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($graphs, 'name');
				foreach($graphs as $graphid => $graph){
					unset($graphs[$graphid]['discoveries']);
					$count = array();
					foreach($graph['discoveries'] as $item){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$item['itemid']])) $count[$item['itemid']] = 0;
							$count[$item['itemid']]++;

							if($count[$item['itemid']] > $options['limitSelects']) continue;
						}

						$result[$item['itemid']]['graphs'][] = &$graphs[$graphid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectGraphs']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$graphs = API::Graph()->get($obj_params);

				$graphs = zbx_toHash($graphs, 'parent_itemid');
				foreach($result as $itemid => $item){
					if(isset($graphs[$itemid]))
						$result[$itemid]['graphs'] = $graphs[$itemid]['rowscount'];
					else
						$result[$itemid]['graphs'] = 0;
				}
			}
		}

COpt::memoryPick();
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	public function exists($object){
		$options = array(
			'filter' => array('key_' => $object['key_']),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);

		if(isset($object['hostid'])) $options['hostids'] = $object['hostid'];
		if(isset($object['host'])) $options['filter']['host'] = $object['host'];

		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

		return !empty($objs);
	}

	protected function checkInput(array &$items, $update=false){
		foreach($items as $inum => $item){
			$items[$inum]['flags'] = ZBX_FLAG_DISCOVERY_CHILD;
		}

		parent::checkInput($items, $update);
	}

/**
 * Add Itemprototype
 *
 * @param array $items
 * @return array|boolean
 */
	public function create($items){
		$items = zbx_toArray($items);

			$this->checkInput($items);

			$this->createReal($items);

			$this->inherit($items);

			return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

	protected function createReal(&$items){
		foreach($items as $key => $item){
			$itemsExists = API::Item()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array(
					'hostid' => $item['hostid'],
					'key_' => $item['key_']
				),
				'nopermissions' => 1
			));
			foreach($itemsExists as $inum => $itemExists){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Host with item ['.$item['key_'].'] already exists');
			}
		}

		$itemids = DB::insert('items', $items);

		$itemApplications = $insert_item_discovery = array();
		foreach($items as $key => $item){
			$items[$key]['itemid'] = $itemids[$key];

			$insert_item_discovery[] = array(
				'itemid' => $items[$key]['itemid'],
				'parent_itemid' => $item['ruleid']
			);

			if(isset($item['applications'])){
				foreach($item['applications'] as $anum => $appid){
					if($appid == 0) continue;

					$itemApplications[] = array(
						'applicationid' => $appid,
						'itemid' => $items[$key]['itemid']
					);
				}
			}
		}

		DB::insert('item_discovery', $insert_item_discovery);

		if(!empty($itemApplications)){
			DB::insert('items_applications', $itemApplications);
		}

// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('key_'),
			'selectHosts' => array('host'),
			'nopermissions' => 1
		));
		foreach($itemHosts as $item){
			$host = reset($item['hosts']);
			info(S_ITEM_PROTOTYPE.' ['.$host['host'].':'.$item['key_'].'] '.S_CREATED_SMALL);
		}
	}

	protected function updateReal($items){
		$items = zbx_toArray($items);

		$data = array();
		foreach($items as $inum => $item){
			$itemsExists = API::Item()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array(
					'hostid' => $item['hostid'],
					'key_' => $item['key_']
				),
				'nopermissions' => 1
			));
			foreach($itemsExists as $inum => $itemExists){
				if(bccomp($itemExists['itemid'],$item['itemid'])!=0){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Host with item [ '.$item['key_'].' ] already exists');
				}
			}

			$data[] = array('values' => $item, 'where'=> array('itemid'=>$item['itemid']));
		}
		$result = DB::update('items', $data);
		if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

		$itemids = array();
		$itemApplications = array();
		foreach($items as $item){
			if(!isset($item['applications'])) continue;

			$itemids[] = $item['itemid'];
			foreach($item['applications'] as $anum => $appid){
				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $item['itemid']
				);
			}
		}

		if(!empty($itemids)){
			DB::delete('items_applications', array('itemid'=>$itemids));
			DB::insert('items_applications', $itemApplications);
		}

// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('key_'),
			'selectHosts' => array('host'),
			'nopermissions' => 1,
		));
		foreach($itemHosts as $item){
			$host = reset($item['hosts']);
			info(S_ITEM_PROTOTYPE." [".$host['host'].':'.$item['key_']."] ".S_UPDATED_SMALL);
		}
	}

/**
 * Update Itemprototype
 *
 * @param array $items
 * @return boolean
 */
	public function update($items) {
		$items = zbx_toArray($items);

			$this->checkInput($items, true);
			$this->updateReal($items);
			$this->inherit($items);

			return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

/**
 * Delete Itemprototypes
 *
 * @param array $ruleids
 * @return
 */
	public function delete($prototypeids, $nopermissions=false) {

			if (empty($prototypeids)) self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));

			$prototypeids = zbx_toHash($prototypeids);

			$options = array(
				'itemids' => $prototypeids,
				'editable' => 1,
				'preservekeys' => 1,
				'output' => API_OUTPUT_EXTEND,
			);
			$del_itemPrototypes = $this->get($options);

// TODO: remove $nopermissions hack
			if (!$nopermissions) {
				foreach ($prototypeids as $prototypeid) {
					if (!isset($del_itemPrototypes[$prototypeid])) {
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
					}
					if ($del_itemPrototypes[$prototypeid]['templateid'] != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete templated items');
					}
				}
			}

// first delete child items
			$parent_itemids = $prototypeids;
			$child_prototypeids = array();
			do{
				$db_items = DBselect('SELECT itemid FROM items WHERE ' . DBcondition('templateid', $parent_itemids));
				$parent_itemids = array();
				while($db_item = DBfetch($db_items)){
					$parent_itemids[$db_item['itemid']] = $db_item['itemid'];
					$child_prototypeids[$db_item['itemid']] = $db_item['itemid'];
				}
			}while(!empty($parent_itemids));

			$options = array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $child_prototypeids,
				'nopermissions' => true,
				'preservekeys' => true,
			);
			$del_itemPrototypes_childs = $this->get($options);

			$del_itemPrototypes = array_merge($del_itemPrototypes, $del_itemPrototypes_childs);
			$prototypeids = array_merge($prototypeids, $child_prototypeids);


// CREATED ITEMS
			$created_items = array();
			$sql = 'SELECT itemid FROM item_discovery WHERE '.DBcondition('parent_itemid', $prototypeids);
			$db_items = DBselect($sql);
			while($item = DBfetch($db_items)){
				$created_items[$item['itemid']] = $item['itemid'];
			}
			if(!empty($created_items)){
				$result = API::Item()->delete($created_items, true);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete item prototype');
			}


// GRAPH PROTOTYPES
			$del_graphPrototypes = API::GraphPrototype()->get(array(
				'itemids' => $prototypeids,
				'output' => API_OUTPUT_SHORTEN,
				'nopermissions' => true,
				'preservekeys' => true,
			));
			if(!empty($del_graphPrototypes)){
				$result = API::GraphPrototype()->delete(zbx_objectValues($del_graphPrototypes, 'graphid'), true);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete graph prototype');
			}


// TRIGGER PROTOTYPES
			$del_triggerPrototypes = API::TriggerPrototype()->get(array(
				'itemids' => $prototypeids,
				'output' => API_OUTPUT_SHORTEN,
				'nopermissions' => true,
				'preservekeys' => true,
			));
			if(!empty($del_triggerPrototypes)){
				$result = API::TriggerPrototype()->delete(zbx_objectValues($del_triggerPrototypes, 'triggerid'), true);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete trigger prototype');
			}


// ITEM PROTOTYPES
			DB::delete('items', array('itemid' => $prototypeids));


// HOUSEKEEPER {{{
			$item_data_tables = array(
				'trends',
				'trends_uint',
				'history_text',
				'history_log',
				'history_uint',
				'history_str',
				'history',
			);

			$insert = array();
			foreach($prototypeids as $id => $prototypeid){
				foreach($item_data_tables as $table){
					$insert[] = array(
						'tablename' => $table,
						'field' => 'itemid',
						'value' => $prototypeid,
					);
				}
			}
			DB::insert('housekeeper', $insert);
// }}} HOUSEKEEPER

// TODO: remove info from API
			foreach($del_itemPrototypes as $item){
				info(_s('Item prototype [%1$s:%2$s] deleted.', $item['name'], $item['key_']));
			}

			return array('prototypeids' => $prototypeids);
	}

	public function syncTemplates($data){
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		if(!API::Host()->isWritable($data['hostids'])){
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}
		if(!API::Template()->isReadable($data['templateids'])){
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}

		$selectFields = array();
		foreach($this->fieldRules as $key => $rules){
			if(!isset($rules['system']) && !isset($rules['host'])){
				$selectFields[] = $key;
			}
		}
		$options = array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'selectApplications' => API_OUTPUT_REFER,
			'output' => $selectFields,
		);
		$items = $this->get($options);

		foreach($items as $inum => $item){
			$items[$inum]['applications'] = zbx_objectValues($item['applications'], 'applicationid');
		}

		$this->inherit($items, $data['hostids']);

		return true;
	}

	protected function inherit($items, $hostids=null){
		if(empty($items)) return true;

		$chdHosts = API::Host()->get(array(
			'templateids' => zbx_objectValues($items, 'hostid'),
			'hostids' => $hostids,
			'output' => array('hostid', 'host', 'status'),
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		));
		if(empty($chdHosts)) return true;

		$ruleIds = array();
		$sql = 'SELECT i.itemid as ruleid, id.itemid, i.hostid '.
			' FROM items i, item_discovery id '.
			' WHERE i.templateid=id.parent_itemid '.
				' AND '.DBcondition('id.itemid', zbx_objectValues($items, 'itemid'));
		$db_result = DBselect($sql);
		while($rule = DBfetch($db_result)){
			if(!isset($ruleIds[$rule['itemid']])) $ruleIds[$rule['itemid']] = array();
			$ruleIds[$rule['itemid']][$rule['hostid']] = $rule['ruleid'];
		}

		$insertItems = array();
		$updateItems = array();
		$inheritedItems = array();
		foreach($chdHosts as $hostid => $host){
			$templateids = zbx_toHash($host['templates'], 'templateid');

// skip items not from parent templates of current host
			$parentItems = array();
			foreach($items as $itemid => $item){
				if(isset($templateids[$item['hostid']]))
					$parentItems[$itemid] = $item;
			}
//----

// check existing items to decide insert or update
			$exItems = $this->get(array(
				'output' => array('itemid', 'key_', 'type', 'flags', 'templateid'),
				'hostids' => $hostid,
				'filter' => array('flags' => null),
				'preservekeys' => true,
				'nopermissions' => true
			));
			$exItemsKeys = zbx_toHash($exItems, 'key_');
			$exItemsTpl = zbx_toHash($exItems, 'templateid');

			foreach($parentItems as $item){
				$exItem = null;

// update by tempalteid
				if(isset($exItemsTpl[$item['itemid']])){
					$exItem = $exItemsTpl[$item['itemid']];
				}

// update by key
				if(isset($item['key_']) && isset($exItemsKeys[$item['key_']])){
					$exItem = $exItemsKeys[$item['key_']];

					if($exItem['flags'] != ZBX_FLAG_DISCOVERY_CHILD){
						$this->errorInheritFlags($exItem['flags'], $exItem['key_'], $host['host']);
					}
					else if(($exItem['templateid'] > 0) && (bccomp($exItem['templateid'], $item['itemid']) != 0)){
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item "%1$s:%2$s" already exists, inherited from another template.', $host['host'], $exItem['key_']));
					}
				}

				if ($host['status'] == HOST_STATUS_TEMPLATE || !isset($item['type'])) {
					unset($item['interfaceid']);
				}
				elseif ((isset($item['type']) && isset($exItem) && $item['type'] != $exItem['type']) || !isset($exItem)) {

					// find a matching interface
					$interface = self::findInterfaceForItem($item, $host['interfaces']);
					if ($interface) {
						$item['interfaceid'] = $interface['interfaceid'];
					}
					// no matching interface found, throw an error
					elseif($interface !== false) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot find host interface on host "%1$s" for item key "%2$s".', $host['host'], $item['key_']));
					}
				}
// coping item
				$newItem = $item;
				$newItem['hostid'] = $host['hostid'];
				$newItem['templateid'] = $item['itemid'];

// setting item application
				if(isset($item['applications'])){
					$newItem['applications'] = get_same_applications_for_host($item['applications'], $host['hostid']);
				}
//--

				if($exItem){
					$newItem['itemid'] = $exItem['itemid'];
					$inheritedItems[] = $newItem;
					unset($newItem['ruleid']);
					$updateItems[] = $newItem;
				}
				else{
					$newItem['ruleid'] = $ruleIds[$item['itemid']][$host['hostid']];
					$newItem['flags'] = ZBX_FLAG_DISCOVERY_CHILD;
					$insertItems[] = $newItem;
				}
			}
		}

		$this->createReal($insertItems);
		$this->updateReal($updateItems);

		$this->inherit($inheritedItems);
	}
}
?>
