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
 * File containing CTemplateScreen class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Screens
 */
class CTemplateScreen extends CScreen{

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
			'select'	=> array('screens' => 's.screenid, s.templateid'),
			'from'		=> array('screens' => 'screens s'),
			'where'		=> array('template' => 's.templateid IS NOT NULL'),
			'order'		=> array(),
			'group'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
			'nodeids'					=> null,
			'screenids'					=> null,
			'screenitemids'				=> null,
			'templateids'	 			=> null,
			'hostids'					=> null,
			'editable'					=> null,
			'noInheritance'				=> null,
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
			foreach($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sql_parts['select'][$field] = 's.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		if(!is_null($options['editable']) || (is_null($options['hostids']) && is_null($options['templateids']))){
			$options['noInheritance'] = 1;
		}

// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){}
		else{
// TODO: think how we could combine templateids && hostids options
			if(!is_null($options['templateids'])){
				unset($options['hostids']);

				$options['templateids'] = API::Template()->get(array(
					'templateids' => $options['templateids'],
					'editable' => $options['editable'],
					'preservekeys' => true
				));
				$options['templateids'] = array_keys($options['templateids']);
			}
			else if(!is_null($options['hostids'])){
				$options['templateids'] = API::Host()->get(array(
					'hostids' => $options['hostids'],
					'editable' => $options['editable'],
					'preservekeys' => true
				));
				$options['templateids'] = array_keys($options['templateids']);
			}
			else{
// TODO: get screen
				$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

				$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
				$sql_parts['from']['rights'] = 'rights r';
				$sql_parts['from']['users_groups'] = 'users_groups ug';
				$sql_parts['where'][] = 'hg.hostid=s.templateid';
				$sql_parts['where'][] = 'r.id=hg.groupid ';
				$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
				$sql_parts['where'][] = 'ug.userid='.self::$userData['userid'];
				$sql_parts['where'][] = 'r.permission>='.$permission;
				$sql_parts['where'][] = 'NOT EXISTS( '.
									' SELECT hgg.groupid '.
									' FROM hosts_groups hgg, rights rr, users_groups gg '.
									' WHERE hgg.hostid=hg.hostid '.
										' AND rr.id=hgg.groupid '.
										' AND rr.groupid=gg.usrgrpid '.
										' AND gg.userid='.self::$userData['userid'].
										' AND rr.permission<'.$permission.')';
			}
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

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);

			if(isset($options['hostids']) && !is_null($options['hostids'])){
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

			$templatesChain = array();
// collecting template chain
			$linkedTemplateIds = $options['hostids'];
			$childTemplateIds = $options['hostids'];

			while(is_null($options['noInheritance']) && !empty($childTemplateIds)){
				$sql = 'SELECT ht.* '.
					' FROM hosts_templates ht '.
					' WHERE '.DBcondition('hostid', $childTemplateIds);
				$db_templates = DBselect($sql);

				$childTemplateIds = array();
				while($link = DBfetch($db_templates)){
					$childTemplateIds[$link['templateid']] = $link['templateid'];
					$linkedTemplateIds[$link['templateid']] = $link['templateid'];

					createParentToChildRelation($templatesChain, $link, 'templateid', 'hostid');
				}
			}
//----
			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['templateid'] = 's.templateid';
			}

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['templateid'] = 's.templateid';
			}

			$sql_parts['where']['templateid'] = DBcondition('s.templateid', $linkedTemplateIds);
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
//SDI($sql);
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
					$result[$screen['screenid']] = array(
						'screenid' => $screen['screenid'],
						'templateid' => $screen['templateid']
					);
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

// hashing
		$options['hostids'] = zbx_toHash($options['hostids']);

// Adding ScreenItems
		if(!is_null($options['selectScreenItems']) && str_in_array($options['selectScreenItems'], $subselects_allowed_outputs)){
			$screens_items = array();
			$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
			while($sitem = DBfetch($db_sitems)){
// Sorting
				$screens_items[$sitem['screenitemid']] = $sitem;
				switch($sitem['resourcetype']){
					case SCREEN_RESOURCE_GRAPH:
						$graphids[$sitem['resourceid']] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
					case SCREEN_RESOURCE_PLAIN_TEXT:
						$itemids[$sitem['resourceid']] = $sitem['resourceid'];
					break;
				}
			}

			foreach($screens_items as $snum => $sitem){
				if(!isset($result[$sitem['screenid']]['screenitems'])){
					$result[$sitem['screenid']]['screenitems'] = array();
				}

				$result[$sitem['screenid']]['screenitems'][] = $sitem;
			}
		}

// Creating linkage of template -> real objects
		if(!is_null($options['selectScreenItems']) && !is_null($options['hostids'])){
// prepare Graphs
			if(!empty($graphids)){
				$tplGraphs = API::Graph()->get(array(
					'output' => array('graphid', 'name'),
					'graphids' => $graphids,
					'nopermissions' => 1,
					'preservekeys' => 1
				));

				$dbGraphs = API::Graph()->get(array(
					'output' => array('graphid', 'name'),
					'hostids' => $options['hostids'],
					'filter' => array('name' => zbx_objectValues($tplGraphs, 'name')),
					'nopermissions' => 1,
					'preservekeys' => 1
				));
				$realGraphs = array();
				foreach($dbGraphs as $graphid => $graph){
					$host = reset($graph['hosts']);
					unset($graph['hosts']);

					if(!isset($realGraphs[$host['hostid']])) $realGraphs[$host['hostid']] = array();
					$realGraphs[$host['hostid']][$graph['name']] = $graph;
				}
			}

// prepare Items
			if(!empty($itemids)){
				$tplItems = API::Item()->get(array(
					'output' => array('itemid', 'key_'),
					'itemids' => $itemids,
					'nopermissions' => 1,
					'preservekeys' => 1
				));

				$dbItems = API::Item()->get(array(
					'output' => array('itemid', 'key_'),
					'hostids' => $options['hostids'],
					'filter' => array('key_' => zbx_objectValues($tplItems, 'key_')),
					'nopermissions' => 1,
					'preservekeys' => 1
				));

				$realItems = array();
				foreach($dbItems as $itemid => $item){
					unset($item['hosts']);

					if(!isset($realItems[$item['hostid']])) $realItems[$item['hostid']] = array();
					$realItems[$item['hostid']][$item['key_']] = $item;
				}
			}
		}


// creating copies of templated screens (inheritance)

// screenNum is needed due to we can't refer to screenid/hostid/templateid as they will repeat
		$screenNum = 0;
		$vrtResult = array();

		foreach($result as $screenid => $screen){
			if(is_null($options['hostids']) || isset($options['hostids'][$screen['templateid']])){
				$screenNum++;
				$vrtResult[$screenNum] = $screen;
				$vrtResult[$screenNum]['hostid'] = $screen['templateid'];
			}

			if(!isset($templatesChain[$screen['templateid']])) continue;

			foreach($templatesChain[$screen['templateid']] as $hnum => $hostid){
				if(!isset($options['hostids'][$hostid])) continue;

				$screenNum++;
				$vrtResult[$screenNum] = $screen;
				$vrtResult[$screenNum]['hostid'] = $hostid;

				if(!isset($vrtResult[$screenNum]['screenitems'])) continue;

				foreach($vrtResult[$screenNum]['screenitems'] as $snum => &$screenitem){
					switch($screenitem['resourcetype']){
						case SCREEN_RESOURCE_GRAPH:
							$graphName = $tplGraphs[$screenitem['resourceid']]['name'];
							$screenitem['resourceid'] = $realGraphs[$hostid][$graphName]['graphid'];
						break;
						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$itemKey = $tplItems[$screenitem['resourceid']]['key_'];
							$screenitem['resourceid'] = $realItems[$hostid][$itemKey]['itemid'];
						break;
					}
				}
				unset($screenitem);
			}
		}
		$result = array_values($vrtResult);
//-----

		if(!is_null($options['countOutput'])){
			return $result;
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}
		else if(!is_null($options['noInheritance'])){
			$result = zbx_toHash($result, 'screenid');
		}

	return $result;
	}

	public function exists($data){
		$keyFields = array(array('screenid', 'name'), 'templateid');

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

			$screenNames = zbx_objectValues($screens, 'name');
			$templateids = zbx_objectValues($screens, 'templateid');
// Exists
			$options = array(
				'filter' => array(
					'name' => $screenNames,
					'templateid' => $templateids
				),
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => 1
			);
			$db_screens = $this->get($options);
//---

			foreach($screens as $snum => $screen){
				$screen_db_fields = array('name' => null, 'templateid' => null);
				if(!check_db_fields($screen_db_fields, $screen)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen [ '.$screen['name'].' ]');
				}

				foreach($db_screens as $dbsnum => $db_screen){
					if(($db_screen['name'] == $screen['name']) && (bccomp($db_screen['templateid'],$screen['templateid']) == 0)){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_SCREEN.' [ '.$db_screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
					}
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
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
			);
			$updScreens = $this->get($options);
			foreach($screens as $gnum => $screen){
				if(!isset($screen['screenid'], $updScreens[$screen['screenid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			foreach($screens as $snum => $screen){
				$screen_db_fields = array('screenid' => null);
				if(!check_db_fields($screen_db_fields, $screen)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen [ '.$screen['name'].' ]');
				}

				$dbScreen = $updScreens[$screen['screenid']];
				if(isset($screen['templateid']) && (bccomp($screen['templateid'],$dbScreen['templateid']) != 0)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot change template for Screen [ '.$screen['name'].' ]');
				}

				if(isset($screen['name'])){
					$options = array(
						'filter' => array(
							'name' => $screen['name'],
							'templateid' => $dbScreen['templateid']
						),
						'preservekeys' => 1,
						'nopermissions' => 1,
						'output' => API_OUTPUT_SHORTEN
					);
					$exist_screens = $this->get($options);
					$exist_screen = reset($exist_screens);

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

				if(isset($screen['screenitems'])){
					$this->replaceItems($screenid, $screen['screenitems']);
				}
			}
			DB::update('screens', $update);

			return  array('screenids' => zbx_objectValues($screens, 'screenid'));
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
					'preservekeys' => 1
			);
			$del_screens = $this->get($options);
			foreach($screenids as $screenid){
				if(!isset($del_screens[$screenid])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			DB::delete('screens_items', array('screenid'=>$screenids));
			DB::delete('screens_items', array('resourceid'=>$screenids, 'resourcetype'=>SCREEN_RESOURCE_SCREEN));
			DB::delete('slides', array('screenid'=>$screenids));
			DB::delete('screens', array('screenid'=>$screenids));

			return array('screenids' => $screenids);
	}
}
?>
