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
 * File containing CScreen class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Screens
 */
class CScreen extends CZBXAPI {

	protected $tableName = 'screens';

	protected $tableAlias = 's';

/**
 * Get Screen data
 *
 * @param array $options
 * @param array $options['nodeids'] Node IDs
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in host names
 * @param int $options['limit'] limit selection
 * @param string $options['order'] deprecated parameter (for now)
 * @return array|boolean Host data as array or false if error
 */
	public function get($options = array()) {
		$result = array();
		$user_type = self::$userData['type'];

		// allowed columns for sorting
		$sort_columns = array('screenid', 'name');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sql_parts = array(
			'select'	=> array('screens' => 's.screenid'),
			'from'		=> array('screens' => 'screens s'),
			'where'		=> array('template' => 's.templateid IS NULL'),
			'order'		=> array(),
			'group'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
			'nodeids'					=> null,
			'screenids'					=> null,
			'screenitemids'				=> null,
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
			'selectScreenItems'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($def_options, $options);

		if (is_array($options['output'])) {
			unset($sql_parts['select']['screens']);

			$dbTable = DB::getSchema('screens');
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sql_parts['select'][$field] = 's.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// screenids
		if(!is_null($options['screenids'])){
			zbx_value2array($options['screenids']);
			$sql_parts['where'][] = DBcondition('s.screenid', $options['screenids']);
		}

// screenitemids
		if(!is_null($options['screenitemids'])){
			zbx_value2array($options['screenitemids']);
			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['screenitemid'] = 'si.screenitemid';
			}
			$sql_parts['from']['screens_items'] = 'screens_items si';
			$sql_parts['where']['ssi'] = 'si.screenid=s.screenid';
			$sql_parts['where'][] = DBcondition('si.screenitemid', $options['screenitemids']);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('screens s', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('screens s', $options, $sql_parts);
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['screens'] = 's.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT s.screenid) as rowscount');

// groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		zbx_db_sorting($sql_parts, $options, $sort_columns, 's');

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------

		$screenids = array();

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
		if(!empty($sql_parts['group']))		$sql_group.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('s.screenid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($screen = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $screen;
				else
					$result = $screen['rowscount'];
			}
			else{
				$screenids[$screen['screenid']] = $screen['screenid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$screen['screenid']] = array('screenid' => $screen['screenid']);
				}
				else{
					if(!isset($result[$screen['screenid']])) $result[$screen['screenid']]= array();

					if(!is_null($options['selectScreenItems']) && !isset($result[$screen['screenid']]['screenitems'])){
						$result[$screen['screenid']]['screenitems'] = array();
					}

					if(isset($screen['screenitemid']) && is_null($options['selectScreenItems'])){
						if(!isset($result[$screen['screenid']]['screenitems']))
							$result[$screen['screenid']]['screenitems'] = array();

						$result[$screen['screenid']]['screenitems'][] = array('screenitemid' => $screen['screenitemid']);
						unset($screen['screenitemid']);
					}

					$result[$screen['screenid']] += $screen;
				}
			}
		}

// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){}
		else if(!empty($result)){
			$groups_to_check = array();
			$hosts_to_check = array();
			$graphs_to_check = array();
			$items_to_check = array();
			$maps_to_check = array();
			$screens_to_check = array();
			$screens_items = array();

			$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
			while($sitem = DBfetch($db_sitems)){
				if($sitem['resourceid'] == 0) continue;

				$screens_items[$sitem['screenitemid']] = $sitem;

				switch($sitem['resourcetype']){
					case SCREEN_RESOURCE_HOSTS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
						$groups_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_HOST_TRIGGERS:
						$hosts_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_GRAPH:
						$graphs_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
					case SCREEN_RESOURCE_PLAIN_TEXT:
						$items_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_MAP:
						$maps_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_SCREEN:
						$screens_to_check[] = $sitem['resourceid'];
					break;
				}
			}

			$groups_to_check = array_unique($groups_to_check);
			$hosts_to_check = array_unique($hosts_to_check);
			$graphs_to_check = array_unique($graphs_to_check);
			$items_to_check = array_unique($items_to_check);
			$maps_to_check = array_unique($maps_to_check);
			$screens_to_check = array_unique($screens_to_check);
/*
sdii($graphs_to_check);
sdii($items_to_check);
sdii($maps_to_check);
sdii($screens_to_check);
//*/
// group
			$group_options = array(
								'nodeids' => $nodeids,
								'groupids' => $groups_to_check,
								'editable' => $options['editable']);
			$allowed_groups = API::HostGroup()->get($group_options);
			$allowed_groups = zbx_objectValues($allowed_groups, 'groupid');

// host
			$host_options = array(
								'nodeids' => $nodeids,
								'hostids' => $hosts_to_check,
								'editable' => $options['editable']);
			$allowed_hosts = API::Host()->get($host_options);
			$allowed_hosts = zbx_objectValues($allowed_hosts, 'hostid');

// graph
			$graph_options = array(
								'nodeids' => $nodeids,
								'graphids' => $graphs_to_check,
								'editable' => $options['editable']);
			$allowed_graphs = API::Graph()->get($graph_options);
			$allowed_graphs = zbx_objectValues($allowed_graphs, 'graphid');

// item
			$item_options = array(
				'nodeids' => $nodeids,
				'itemids' => $items_to_check,
				'webitems' => 1,
				'editable' => $options['editable']
			);
			$allowed_items = API::Item()->get($item_options);
			$allowed_items = zbx_objectValues($allowed_items, 'itemid');
// map
			$map_options = array(
				'nodeids' => $nodeids,
				'sysmapids' => $maps_to_check,
				'editable' => $options['editable']
			);
			$allowed_maps = API::Map()->get($map_options);
			$allowed_maps = zbx_objectValues($allowed_maps, 'sysmapid');
// screen
			$screens_options = array(
								'nodeids' => $nodeids,
								'screenids' => $screens_to_check,
								'editable' => $options['editable']);
			$allowed_screens = API::Screen()->get($screens_options);
			$allowed_screens = zbx_objectValues($allowed_screens, 'screenid');


			$restr_groups = array_diff($groups_to_check, $allowed_groups);
			$restr_hosts = array_diff($hosts_to_check, $allowed_hosts);
			$restr_graphs = array_diff($graphs_to_check, $allowed_graphs);
			$restr_items = array_diff($items_to_check, $allowed_items);
			$restr_maps = array_diff($maps_to_check, $allowed_maps);
			$restr_screens = array_diff($screens_to_check, $allowed_screens);


/*
SDI('---------------------------------------');
SDII($restr_graphs);
SDII($restr_items);
SDII($restr_maps);
SDII($restr_screens);
SDI('/////////////////////////////////');
//*/
// group
			foreach($restr_groups as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if((bccomp($screen_item['resourceid'],$resourceid) == 0) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO,SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW,SCREEN_RESOURCE_HOSTGROUP_TRIGGERS))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// host
			foreach($restr_hosts as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if((bccomp($screen_item['resourceid'],$resourceid) == 0) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_HOST_TRIGGERS))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// graph
			foreach($restr_graphs as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if((bccomp($screen_item['resourceid'],$resourceid) == 0) && ($screen_item['resourcetype'] == SCREEN_RESOURCE_GRAPH)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// item
			foreach($restr_items as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if((bccomp($screen_item['resourceid'],$resourceid) == 0) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// map
			foreach($restr_maps as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if((bccomp($screen_item['resourceid'],$resourceid) == 0) && ($screen_item['resourcetype'] == SCREEN_RESOURCE_MAP)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// screen
			foreach($restr_screens as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if((bccomp($screen_item['resourceid'],$resourceid) == 0) && ($screen_item['resourcetype'] == SCREEN_RESOURCE_SCREEN)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
		}

		if(!is_null($options['countOutput'])){
			return $result;
		}


// Adding ScreenItems
		if(!is_null($options['selectScreenItems']) && str_in_array($options['selectScreenItems'], $subselects_allowed_outputs)){
			if(!isset($screens_items)){
				$screens_items = array();
				$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
				while($sitem = DBfetch($db_sitems)){
					$screens_items[$sitem['screenitemid']] = $sitem;
				}
			}

			foreach($screens_items as $snum => $sitem){
				if(!isset($result[$sitem['screenid']]['screenitems'])){
					$result[$sitem['screenid']]['screenitems'] = array();
				}

				$result[$sitem['screenid']]['screenitems'][] = $sitem;
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	public function exists($data){
		$keyFields = array(array('screenid', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $data),
			'preservekeys' => 1,
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);

		if(isset($data['node']))
			$options['nodeids'] = getNodeIdByNodeName($data['node']);
		else if(isset($data['nodeids']))
			$options['nodeids'] = $data['nodeids'];

		$screens = $this->get($options);

		return !empty($screens);
	}

	protected function checkItems($screenitems){
		$hostgroups = array();
		$hosts = array();
		$graphs = array();
		$items = array();
		$maps = array();
		$screens = array();

		$resources = array(SCREEN_RESOURCE_GRAPH, SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT,
					SCREEN_RESOURCE_MAP,SCREEN_RESOURCE_SCREEN, SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
					SCREEN_RESOURCE_DATA_OVERVIEW);

		foreach($screenitems as $item){
			if((isset($item['resourcetype']) && !isset($item['resourceid'])) ||
				(!isset($item['resourcetype']) && isset($item['resourceid'])))
			{
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			if(isset($item['resourceid']) && ($item['resourceid'] == 0)){
				if(uint_in_array($item['resourcetype'], $resources))
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect resource provided for screen item'));
				else
					continue;
			}

			switch($item['resourcetype']){
				case SCREEN_RESOURCE_HOSTS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
				case SCREEN_RESOURCE_DATA_OVERVIEW:
				case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
					$hostgroups[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_HOST_TRIGGERS:
					$hosts[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_GRAPH:
					$graphs[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_SIMPLE_GRAPH:
				case SCREEN_RESOURCE_PLAIN_TEXT:
					$items[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_MAP:
					$maps[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_SCREEN:
					$screens[] = $item['resourceid'];
				break;
			}
		}

		if(!empty($hostgroups)){
			$result = API::HostGroup()->get(array(
				'groupids' => $hostgroups,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($hostgroups as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect host group ID "%s" provided for screen element.', $id));
			}
		}
		if(!empty($hosts)){
			$result = API::Host()->get(array(
				'hostids' => $hosts,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($hosts as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect host ID "%s" provided for screen element.', $id));
			}
		}
		if(!empty($graphs)){
			$result = API::Graph()->get(array(
				'graphids' => $graphs,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($graphs as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect graph ID "%s" provided for screen element.', $id));
			}
		}
		if(!empty($items)){
			$result = API::Item()->get(array(
				'itemids' => $items,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
				'webitems' => 1,
			));
			foreach($items as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect item ID "%s" provided for screen element.', $id));
			}
		}
		if(!empty($maps)){
			$result = API::Map()->get(array(
				'sysmapids' => $maps,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($maps as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect map ID "%s" provided for screen element.', $id));
			}
		}
		if(!empty($screens)){
			$result = $this->get(array(
				'screenids' => $screens,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($screens as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect screen ID "%s" provided for screen element.', $id));
			}
		}
	}

/**
 * Create Screen
 *
 * @param _array $screens
 * @param string $screens['name']
 * @param array $screens['hsize']
 * @param int $screens['vsize']
 * @return array
 */
	public function create($screens){
		$screens = zbx_toArray($screens);
		$insert_screen_items = array();

			$newScreenNames = zbx_objectValues($screens, 'name');
// Exists
			$options = array(
				'filter' => array('name' => $newScreenNames),
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => 1
			);
			$db_screens = $this->get($options);
			foreach($db_screens as $dbsnum => $db_screen){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_SCREEN.' [ '.$db_screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
			}
//---

			foreach($screens as $snum => $screen){

				$screen_db_fields = array('name' => null);
				if(!check_db_fields($screen_db_fields, $screen)){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for screen [ %s ]', $screen['name']));
				}

				if($this->exists($screen)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}
			}
			$screenids = DB::insert('screens', $screens);

			foreach($screens as $snum => $screen){
				if(isset($screen['screenitems'])){
					foreach($screen['screenitems'] as $screenitem){
						$screenitem['screenid'] = $screenids[$snum];
						$insert_screen_items[] = $screenitem;
					}
				}
			}

			// save screen items
			API::ScreenItem()->create($insert_screen_items);

			return array('screenids' => $screenids);
	}

/**
 * Update Screen
 *
 * @param _array $screens multidimensional array with Hosts data
 * @param string $screens['screenid']
 * @param int $screens['name']
 * @param int $screens['hsize']
 * @param int $screens['vsize']
 * @return boolean
 */
	public function update($screens){
		$screens = zbx_toArray($screens);
		$update = array();

		$options = array(
			'screenids' => zbx_objectValues($screens, 'screenid'),
			'editable' => 1,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => 1,
		);
		$upd_screens = $this->get($options);
		foreach($screens as $gnum => $screen){
				if(!isset($screen['screenid'], $upd_screens[$screen['screenid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
		}

		foreach($screens as $snum => $screen){
			if(isset($screen['name'])){
				$options = array(
					'filter' => array(
						'name' => $screen['name'],
					),
					'preservekeys' => 1,
					'nopermissions' => 1,
					'output' => API_OUTPUT_SHORTEN,
				);
				$exist_screen = $this->get($options);
				$exist_screen = reset($exist_screen);

				if($exist_screen && (bccomp($exist_screen['screenid'],$screen['screenid']) != 0))
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
			}

			$screenid = $screen['screenid'];
			unset($screen['screenid']);
			if(!empty($screen)){
				$update[] = array(
					'values' => $screen,
					'where' => array('screenid' => $screenid),
				);
			}

			// udpate screen items
			if (isset($screen['screenitems'])) {
				$this->replaceItems($screenid, $screen['screenitems']);
			}
		}
		DB::update('screens', $update);

		return array('screenids' => zbx_objectValues($screens, 'screenid'));
	}

/**
 * Delete Screen
 *
 * @param array $screenids
 * @return boolean
 */
	public function delete($screenids){
		$screenids = zbx_toArray($screenids);

		$options = array(
				'screenids' => $screenids,
				'editable' => 1,
				'preservekeys' => 1,
		);
		$del_screens = $this->get($options);

		foreach($screenids as $screenid){
			if(!isset($del_screens[$screenid]))
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}

		DB::delete('screens_items', array('screenid'=>$screenids));
		DB::delete('screens_items', array('resourceid'=>$screenids, 'resourcetype'=>SCREEN_RESOURCE_SCREEN));
		DB::delete('slides', array('screenid'=>$screenids));
		DB::delete('screens', array('screenid'=>$screenids));

		return array('screenids' => $screenids);
	}


	/**
	 * Replaces all of the screen items of the given screen with the new ones.
	 *
	 * @param int $screenid        The ID of the target screen
	 * @param array $screenItems   An array of screen items
	 */
	protected function replaceItems($screenid, $screenItems){
		// fetch the current screen items
		$dbScreenItems = API::ScreenItem()->get(array(
			'screenids' => $screenid,
			'preservekeys' => true
		));

		// update the new ones
		foreach ($screenItems as &$screenItem) {
			$screenItem['screenid'] = $screenid;
		}
		$result = API::ScreenItem()->updateByPosition($screenItems);

		// deleted the old items
		$deleteItemIds = array_diff(array_keys($dbScreenItems), $result['screenitemids']);
		API::ScreenItem()->delete($deleteItemIds);
	}
}
?>
