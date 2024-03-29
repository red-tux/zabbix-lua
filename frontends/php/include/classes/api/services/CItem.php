<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with items.
 */
class CItem extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status'];

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_EXISTS_TEMPLATE => _('Item "%1$s" already exists on "%2$s", inherited from another template.'),
			self::ERROR_EXISTS => _('Item "%1$s" already exists on "%2$s".'),
			self::ERROR_INVALID_KEY => _('Invalid key "%1$s" for item "%2$s" on "%3$s": %4$s.')
		]);
	}

	/**
	 * Get items data.
	 *
	 * @param array  $options
	 * @param array  $options['itemids']
	 * @param array  $options['hostids']
	 * @param array  $options['groupids']
	 * @param array  $options['triggerids']
	 * @param array  $options['applicationids']
	 * @param bool   $options['status']
	 * @param bool   $options['templated_items']
	 * @param bool   $options['editable']
	 * @param bool   $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> ['items' => 'items i'],
			'where'		=> ['webtype' => 'i.type<>'.ITEM_TYPE_HTTPTEST, 'flags' => 'i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'proxyids'					=> null,
			'itemids'					=> null,
			'interfaceids'				=> null,
			'graphids'					=> null,
			'triggerids'				=> null,
			'applicationids'			=> null,
			'webitems'					=> null,
			'inherited'					=> null,
			'templated'					=> null,
			'monitored'					=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			'group'						=> null,
			'host'						=> null,
			'application'				=> null,
			'with_triggers'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectHosts'				=> null,
			'selectInterfaces'			=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectApplications'		=> null,
			'selectDiscoveryRule'		=> null,
			'selectItemDiscovery'		=> null,
			'selectPreprocessing'		=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE i.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
					')';
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where']['itemid'] = dbConditionInt('i.itemid', $options['itemids']);
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

			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			$sqlParts['where']['interfaceid'] = dbConditionInt('i.interfaceid', $options['interfaceids']);

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.interfaceid';
			}
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);

			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where'][] = dbConditionInt('h.proxy_hostid', $options['proxyids']);
			$sqlParts['where'][] = 'h.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['h'] = 'h.proxy_hostid';
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['if'] = 'i.itemid=f.itemid';
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['from']['items_applications'] = 'items_applications ia';
			$sqlParts['where'][] = dbConditionInt('ia.applicationid', $options['applicationids']);
			$sqlParts['where']['ia'] = 'ia.itemid=i.itemid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
		}

		// webitems
		if (!is_null($options['webitems'])) {
			unset($sqlParts['where']['webtype']);
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'i.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'i.templateid IS NULL';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('items i', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			if (array_key_exists('delay', $options['filter']) && $options['filter']['delay'] !== null) {
				$sqlParts['where'][] = makeUpdateIntervalFilter('i.delay', $options['filter']['delay']);
				unset($options['filter']['delay']);
			}

			if (array_key_exists('history', $options['filter']) && $options['filter']['history'] !== null) {
				$options['filter']['history'] = getTimeUnitFilters($options['filter']['history']);
			}

			if (array_key_exists('trends', $options['filter']) && $options['filter']['trends'] !== null) {
				$options['filter']['trends'] = getTimeUnitFilters($options['filter']['trends']);
			}

			$this->dbFilter('items i', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['h'] = dbConditionString('h.host', $options['filter']['host'], false, true);
			}

			if (array_key_exists('flags', $options['filter']) &&
					(is_null($options['filter']['flags']) || !zbx_empty($options['filter']['flags']))) {
				unset($sqlParts['where']['flags']);
			}
		}

		// group
		if (!is_null($options['group'])) {
			$sqlParts['from']['groups'] = 'groups g';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['ghg'] = 'g.groupid=hg.groupid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where'][] = ' g.name='.zbx_dbstr($options['group']);
		}

		// host
		if (!is_null($options['host'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where'][] = ' h.host='.zbx_dbstr($options['host']);
		}

		// application
		if (!is_null($options['application'])) {
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['from']['items_applications'] = 'items_applications ia';
			$sqlParts['where']['aia'] = 'a.applicationid = ia.applicationid';
			$sqlParts['where']['iai'] = 'ia.itemid=i.itemid';
			$sqlParts['where'][] = ' a.name='.zbx_dbstr($options['application']);
		}

		// with_triggers
		if (!is_null($options['with_triggers'])) {
			if ($options['with_triggers'] == 1) {
				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM functions ff,triggers t'.
					' WHERE i.itemid=ff.itemid'.
						' AND ff.triggerid=t.triggerid'.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
			}
			else {
				$sqlParts['where'][] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM functions ff,triggers t'.
					' WHERE i.itemid=ff.itemid'.
						' AND ff.triggerid=t.triggerid'.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($item = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $item;
				}
				else {
					$result = $item['rowscount'];
				}
			}
			else {
				$result[$item['itemid']] = $item;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		// add other related objects
		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid', 'interfaceid', 'value_type'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Create item.
	 *
	 * @param $items
	 *
	 * @return array
	 */
	public function create($items) {
		$items = zbx_toArray($items);

		parent::checkInput($items);
		self::validateInventoryLinks($items);

		foreach ($items as &$item) {
			$item['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
			unset($item['itemid']);
		}
		unset($item);
		$this->validateDependentItems($items, API::Item());

		$this->createReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	/**
	 * Create host item.
	 *
	 * @param array $items
	 */
	protected function createReal(array &$items) {
		foreach ($items as &$item) {
			if ($item['type'] != ITEM_TYPE_DEPENDENT) {
				$item['master_itemid'] = null;
			}
			elseif ($item['type'] == ITEM_TYPE_LUA) {
				$item['username'] = self::$userData['userid'];
			}
		}
		unset($item);

		$itemids = DB::insert('items', $items);

		$itemApplications = [];
		foreach ($items as $key => $item) {
			$items[$key]['itemid'] = $itemids[$key];

			if (!isset($item['applications'])) {
				continue;
			}

			foreach ($item['applications'] as $appid) {
				if ($appid == 0) {
					continue;
				}

				$itemApplications[] = [
					'applicationid' => $appid,
					'itemid' => $items[$key]['itemid']
				];
			}
		}

		if (!empty($itemApplications)) {
			DB::insert('items_applications', $itemApplications);
		}

		$this->createItemPreprocessing($items);

		$itemHosts = $this->get([
			'output' => ['name'],
			'itemids' => $itemids,
			'selectHosts' => ['name'],
			'nopermissions' => true
		]);
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Created: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Update host items.
	 *
	 * @param array $items
	 */
	protected function updateReal(array $items) {
		$items = zbx_toArray($items);

		$itemids = [];
		$data = [];
		foreach ($items as $item) {
			unset($item['flags']); // flags cannot be changed
			$data[] = ['values' => $item, 'where' => ['itemid' => $item['itemid']]];
			$itemids[] = $item['itemid'];
		}
		DB::update('items', $data);

		$itemApplications = [];
		$applicationids = [];
		foreach ($items as $item) {
			if (!isset($item['applications'])) {
				continue;
			}
			$applicationids[] = $item['itemid'];

			foreach ($item['applications'] as $appid) {
				$itemApplications[] = [
					'applicationid' => $appid,
					'itemid' => $item['itemid']
				];
			}
		}

		if (!empty($applicationids)) {
			DB::delete('items_applications', ['itemid' => $applicationids]);
			DB::insert('items_applications', $itemApplications);
		}

		$this->updateItemPreprocessing($items);

		$itemHosts = $this->get([
			'output' => ['name'],
			'itemids' => $itemids,
			'selectHosts' => ['name'],
			'nopermissions' => true
		]);
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Updated: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Update item.
	 *
	 * @param array $items
	 *
	 * @return boolean
	 */
	public function update($items) {
		$items = zbx_toArray($items);

		parent::checkInput($items, true);
		self::validateInventoryLinks($items, true);
		$this->validateDependentItems($items, API::Item());

		$dbItems = $this->get([
			'output' => ['flags', 'type', 'master_itemid'],
			'itemids' => zbx_objectValues($items, 'itemid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$items = $this->extendFromObjects(zbx_toHash($items, 'itemid'), $dbItems, ['flags', 'type']);

		foreach ($items as &$item) {
			if ($item['type'] != ITEM_TYPE_DEPENDENT && $dbItems[$item['itemid']]['master_itemid']) {
				$item['master_itemid'] = null;
			}
			elseif (!array_key_exists('master_itemid', $item)) {
				$item['master_itemid'] = $dbItems[$item['itemid']]['master_itemid'];
			}
		}
		unset($item);

		$this->updateReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	/**
	 * Delete items.
	 *
	 * @param array $itemIds
	 * @param bool  $nopermissions
	 *
	 * @return array
	 */
	public function delete(array $itemIds, $nopermissions = false) {
		if (!$itemIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$itemIds = array_keys(array_flip($itemIds));

		$delItems = $this->get([
			'output' => ['itemid', 'name', 'templateid', 'flags'],
			'selectHosts' => ['name'],
			'itemids' => $itemIds,
			'editable' => true,
			'preservekeys' => true
		]);

		// TODO: remove $nopermissions hack
		if (!$nopermissions) {
			foreach ($itemIds as $itemId) {
				if (!isset($delItems[$itemId])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
				$delItem = $delItems[$itemId];

				if ($delItem['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated item.'));
				}
			}
		}

		// first delete child items
		$parentItemIds = $itemIds;
		$db_dependent_items = $delItems;
		do {
			$dbItems = DBselect('SELECT i.itemid FROM items i WHERE '.dbConditionInt('i.templateid',
				$parentItemIds
			));
			$parentItemIds = [];
			while ($dbItem = DBfetch($dbItems)) {
				$parentItemIds[] = $dbItem['itemid'];
				$itemIds[$dbItem['itemid']] = $dbItem['itemid'];
				$db_dependent_items[$dbItem['itemid']] = $dbItem['itemid'];
			}
		} while ($parentItemIds);

		$dependent_items = [];

		while ($db_dependent_items) {
			$db_dependent_items = $this->get([
				'output' => ['itemid', 'name'],
				'filter' => ['type' => ITEM_TYPE_DEPENDENT, 'master_itemid' => array_keys($db_dependent_items)],
				'selectHosts' => ['name'],
				'preservekeys' => true
			]);
			$db_dependent_items = array_diff_key($db_dependent_items, $dependent_items);
			$dependent_items += $db_dependent_items;
		};
		$dependent_itemids = array_keys($dependent_items);
		$itemIds += array_combine($dependent_itemids, $dependent_itemids);

		// delete graphs, leave if graph still have item
		$delGraphs = [];
		$dbGraphs = DBselect(
			'SELECT gi.graphid'.
			' FROM graphs_items gi'.
			' WHERE '.dbConditionInt('gi.itemid', $itemIds).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM graphs_items gii'.
					' WHERE gii.graphid=gi.graphid'.
						' AND '.dbConditionInt('gii.itemid', $itemIds, true).
				')'
		);
		while ($dbGraph = DBfetch($dbGraphs)) {
			$delGraphs[$dbGraph['graphid']] = $dbGraph['graphid'];
		}

		if ($delGraphs) {
			API::Graph()->delete($delGraphs, true);
		}

		// check if any graphs are referencing this item
		$this->checkGraphReference($itemIds);

		$triggers = API::Trigger()->get([
			'output' => [],
			'itemids' => $itemIds,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($triggers) {
			API::Trigger()->delete(array_keys($triggers), true);
		}

		$triggerPrototypes = API::TriggerPrototype()->get([
			'output' => [],
			'itemids' => $itemIds,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($triggerPrototypes) {
			API::TriggerPrototype()->delete(array_keys($triggerPrototypes), true);
		}

		DB::delete('screens_items', [
			'resourceid' => $itemIds,
			'resourcetype' => [SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT, SCREEN_RESOURCE_CLOCK]
		]);

		DB::delete('items', ['itemid' => $itemIds]);

		DB::delete('profiles', [
			'idx' => 'web.favorite.graphids',
			'source' => 'itemid',
			'value_id' => $itemIds
		]);

		$table_names = ['trends', 'trends_uint', 'history_text', 'history_log', 'history_uint', 'history_str',
			'history', 'events'
		];

		$insert = [];

		foreach ($itemIds as $itemId) {
			foreach ($table_names as $table_name) {
				$insert[] = [
					'tablename' => $table_name,
					'field' => 'itemid',
					'value' => $itemId
				];
			}
		}

		DB::insertBatch('housekeeper', $insert);

		// TODO: remove info from API
		$delItems += $dependent_items;
		foreach ($delItems as $item) {
			$host = reset($item['hosts']);
			info(_s('Deleted: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
		$itemids = array_map('strval', array_values($itemIds));

		return ['itemids' => $itemids];
	}

	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$selectFields = [];
		foreach ($this->fieldRules as $key => $rules) {
			if (!isset($rules['system']) && !isset($rules['host'])) {
				$selectFields[] = $key;
			}
		}

		$items = $this->get([
			'output' => $selectFields,
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'selectApplications' => ['applicationid'],
			'selectPreprocessing' => ['type', 'params'],
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		foreach ($items as $inum => $item) {
			$items[$inum]['applications'] = zbx_objectValues($item['applications'], 'applicationid');
		}

		$this->inherit($items, $data['hostids']);

		return true;
	}

	/**
	 * Check item specific fields:
	 *		- validate history and trends using simple interval parser and user macro parser;
	 *		- validate item preprocessing.
	 *
	 * @param array  $item    An array of single item data.
	 * @param string $method  A string of "create" or "update" method.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkSpecificFields(array $item, $method) {
		if (array_key_exists('history', $item)
				&& !validateTimeUnit($item['history'], SEC_PER_HOUR, 25 * SEC_PER_YEAR, true, $error,
					['usermacros' => true])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'history', $error)
			);
		}

		if (array_key_exists('trends', $item)
				&& !validateTimeUnit($item['trends'], SEC_PER_DAY, 25 * SEC_PER_YEAR, true, $error,
					['usermacros' => true])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'trends', $error)
			);
		}

		$this->validateItemPreprocessing($item, $method);
	}

	protected function inherit(array $items, array $hostids = null) {
		if (empty($items)) {
			return true;
		}

		// prepare the child items
		$newItems = $this->prepareInheritedItems($items, $hostids);
		if (!$newItems) {
			return true;
		}

		$insertItems = [];
		$updateItems = [];
		foreach ($newItems as $newItem) {
			if (isset($newItem['itemid'])) {
				$updateItems[] = $newItem;
			}
			else {
				$newItem['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
				$insertItems[] = $newItem;
			}
		}

		// save the new items
		if (!zbx_empty($insertItems)) {
			self::validateInventoryLinks($insertItems, false); // false means 'create'
			$this->createReal($insertItems);
		}

		if (!zbx_empty($updateItems)) {
			self::validateInventoryLinks($updateItems, true); // true means 'update'
			$this->updateReal($updateItems);
		}

		// Update master_itemid for inserted or updated inherited dependent items.
		$this->inheritDependentItems(array_merge($updateItems, $insertItems));

		// propagate the inheritance to the children
		return $this->inherit(array_merge($updateItems, $insertItems));
	}

	/**
	 * Check, if items that are about to be inserted or updated violate the rule:
	 * only one item can be linked to a inventory filed.
	 * If everything is ok, function return true or throws Exception otherwise
	 *
	 * @static
	 *
	 * @param array $items
	 * @param bool $update whether this is update operation
	 *
	 * @return bool
	 */
	public static function validateInventoryLinks(array $items, $update = false) {
		// inventory link field is not being updated, or being updated to 0, no need to validate anything then
		foreach ($items as $i => $item) {
			if (!isset($item['inventory_link']) || $item['inventory_link'] == 0) {
				unset($items[$i]);
			}
		}

		if (zbx_empty($items)) {
			return true;
		}

		$possibleHostInventories = getHostInventories();
		if ($update) {
			// for successful validation we need three fields for each item: inventory_link, hostid and key_
			// problem is, that when we are updating an item, we might not have them, because they are not changed
			// so, we need to find out what is missing and use API to get the lacking info
			$itemsWithNoHostId = [];
			$itemsWithNoInventoryLink = [];
			$itemsWithNoKeys = [];
			foreach ($items as $item) {
				if (!isset($item['inventory_link'])) {
					$itemsWithNoInventoryLink[$item['itemid']] = $item['itemid'];
				}
				if (!isset($item['hostid'])) {
					$itemsWithNoHostId[$item['itemid']] = $item['itemid'];
				}
				if (!isset($item['key_'])) {
					$itemsWithNoKeys[$item['itemid']] = $item['itemid'];
				}
			}
			$itemsToFind = array_merge($itemsWithNoHostId, $itemsWithNoInventoryLink, $itemsWithNoKeys);

			// are there any items with lacking info?
			if (!zbx_empty($itemsToFind)) {
				$missingInfo = API::Item()->get([
					'output' => ['hostid', 'inventory_link', 'key_'],
					'filter' => ['itemid' => $itemsToFind],
					'nopermissions' => true
				]);
				$missingInfo = zbx_toHash($missingInfo, 'itemid');

				// appending host ids, inventory_links and keys where they are needed
				foreach ($items as $i => $item) {
					if (isset($missingInfo[$item['itemid']])) {
						if (!isset($items[$i]['hostid'])) {
							$items[$i]['hostid'] = $missingInfo[$item['itemid']]['hostid'];
						}
						if (!isset($items[$i]['inventory_link'])) {
							$items[$i]['inventory_link'] = $missingInfo[$item['itemid']]['inventory_link'];
						}
						if (!isset($items[$i]['key_'])) {
							$items[$i]['key_'] = $missingInfo[$item['itemid']]['key_'];
						}
					}
				}
			}
		}

		$hostids = zbx_objectValues($items, 'hostid');

		// getting all inventory links on every affected host
		$itemsOnHostsInfo = API::Item()->get([
			'output' => ['key_', 'inventory_link', 'hostid'],
			'filter' => ['hostid' => $hostids],
			'nopermissions' => true
		]);

		// now, changing array to: 'hostid' => array('key_'=>'inventory_link')
		$linksOnHostsCurr = [];
		foreach ($itemsOnHostsInfo as $info) {
			// 0 means no link - we are not interested in those ones
			if ($info['inventory_link'] != 0) {
				if (!isset($linksOnHostsCurr[$info['hostid']])) {
					$linksOnHostsCurr[$info['hostid']] = [$info['key_'] => $info['inventory_link']];
				}
				else{
					$linksOnHostsCurr[$info['hostid']][$info['key_']] = $info['inventory_link'];
				}
			}
		}

		$linksOnHostsFuture = [];

		foreach ($items as $item) {
			// checking if inventory_link value is a valid number
			if ($update || $item['value_type'] != ITEM_VALUE_TYPE_LOG) {
				// does inventory field with provided number exists?
				if (!isset($possibleHostInventories[$item['inventory_link']])) {
					$maxVar = max(array_keys($possibleHostInventories));
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s('Item "%1$s" cannot populate a missing host inventory field number "%2$d". Choices are: from 0 (do not populate) to %3$d.', $item['name'], $item['inventory_link'], $maxVar)
					);
				}
			}

			if (!isset($linksOnHostsFuture[$item['hostid']])) {
				$linksOnHostsFuture[$item['hostid']] = [$item['key_'] => $item['inventory_link']];
			}
			else {
				$linksOnHostsFuture[$item['hostid']][$item['key_']] = $item['inventory_link'];
			}
		}

		foreach ($linksOnHostsFuture as $hostId => $linkFuture) {
			if (isset($linksOnHostsCurr[$hostId])) {
				$futureSituation = array_merge($linksOnHostsCurr[$hostId], $linksOnHostsFuture[$hostId]);
			}
			else {
				$futureSituation = $linksOnHostsFuture[$hostId];
			}
			$valuesCount = array_count_values($futureSituation);

			// if we have a duplicate inventory links after merging - we are in trouble
			if (max($valuesCount) > 1) {
				// what inventory field caused this conflict?
				$conflictedLink = array_keys($valuesCount, 2);
				$conflictedLink = reset($conflictedLink);

				// which of updated items populates this link?
				$beingSavedItemName = '';
				foreach ($items as $item) {
					if ($item['inventory_link'] == $conflictedLink) {
						if (isset($item['name'])) {
							$beingSavedItemName = $item['name'];
						}
						else {
							$thisItem = API::Item()->get([
								'output' => ['name'],
								'filter' => ['itemid' => $item['itemid']],
								'nopermissions' => true
							]);
							$beingSavedItemName = $thisItem[0]['name'];
						}
						break;
					}
				}

				// name of the original item that already populates the field
				$originalItem = API::Item()->get([
					'output' => ['name'],
					'filter' => [
						'hostid' => $hostId,
						'inventory_link' => $conflictedLink
					],
					'nopermissions' => true
				]);
				$originalItemName = $originalItem[0]['name'];

				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s(
						'Two items ("%1$s" and "%2$s") cannot populate one host inventory field "%3$s", this would lead to a conflict.',
						$beingSavedItemName,
						$originalItemName,
						$possibleHostInventories[$conflictedLink]['title']
					)
				);
			}
		}

		return true;
	}

	public function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$itemids = array_keys($result);

		// adding applications
		if ($options['selectApplications'] !== null && $options['selectApplications'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'itemid', 'applicationid', 'items_applications');
			$applications = API::Application()->get([
				'output' => $options['selectApplications'],
				'applicationids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $applications, 'applications');
		}

		// adding interfaces
		if ($options['selectInterfaces'] !== null && $options['selectInterfaces'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'itemid', 'interfaceid');
			$interfaces = API::HostInterface()->get([
				'output' => $options['selectInterfaces'],
				'interfaceids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $interfaces, 'interfaces');
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'itemid', 'triggerid', 'functions');
				$triggers = API::Trigger()->get([
					'output' => $options['selectTriggers'],
					'triggerids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($triggers, 'description');
				}
				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::Trigger()->get([
					'countOutput' => true,
					'groupCount' => true,
					'itemids' => $itemids
				]);
				$triggers = zbx_toHash($triggers, 'itemid');

				foreach ($result as $itemid => $item) {
					if (isset($triggers[$itemid])) {
						$result[$itemid]['triggers'] = $triggers[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['triggers'] = 0;
					}
				}
			}
		}

		// adding graphs
		if (!is_null($options['selectGraphs'])) {
			if ($options['selectGraphs'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'itemid', 'graphid', 'graphs_items');
				$graphs = API::Graph()->get([
					'output' => $options['selectGraphs'],
					'graphids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($graphs, 'name');
				}
				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::Graph()->get([
					'countOutput' => true,
					'groupCount' => true,
					'itemids' => $itemids
				]);
				$graphs = zbx_toHash($graphs, 'itemid');

				foreach ($result as $itemid => $item) {
					if (isset($graphs[$itemid])) {
						$result[$itemid]['graphs'] = $graphs[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['graphs'] = 0;
					}
				}
			}
		}

		// adding discoveryrule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT id1.itemid,id2.parent_itemid'.
					' FROM item_discovery id1,item_discovery id2,items i'.
					' WHERE '.dbConditionInt('id1.itemid', $itemids).
					' AND id1.parent_itemid=id2.itemid'.
					' AND i.itemid=id1.itemid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_CREATED
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['itemid'], $rule['parent_itemid']);
			}

			// item prototypes
			// TODO: this should not be in the item API
			$dbRules = DBselect(
				'SELECT id.parent_itemid,id.itemid'.
					' FROM item_discovery id,items i'.
					' WHERE '.dbConditionInt('id.itemid', $itemids).
					' AND i.itemid=id.itemid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['itemid'], $rule['parent_itemid']);
			}

			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding item discovery
		if ($options['selectItemDiscovery'] !== null) {
			$itemDiscoveries = API::getApiService()->select('item_discovery', [
				'output' => $this->outputExtend($options['selectItemDiscovery'], ['itemdiscoveryid', 'itemid']),
				'filter' => ['itemid' => array_keys($result)],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($itemDiscoveries, 'itemid', 'itemdiscoveryid');

			$itemDiscoveries = $this->unsetExtraFields($itemDiscoveries, ['itemid', 'itemdiscoveryid'],
				$options['selectItemDiscovery']
			);
			$result = $relationMap->mapOne($result, $itemDiscoveries, 'itemDiscovery');
		}

		// adding history data
		$requestedOutput = [];
		if ($this->outputIsRequested('lastclock', $options['output'])) {
			$requestedOutput['lastclock'] = true;
		}
		if ($this->outputIsRequested('lastns', $options['output'])) {
			$requestedOutput['lastns'] = true;
		}
		if ($this->outputIsRequested('lastvalue', $options['output'])) {
			$requestedOutput['lastvalue'] = true;
		}
		if ($this->outputIsRequested('prevvalue', $options['output'])) {
			$requestedOutput['prevvalue'] = true;
		}
		if ($requestedOutput) {
			$history = Manager::History()->getLast($result, 2, ZBX_HISTORY_PERIOD);
			foreach ($result as &$item) {
				$lastHistory = isset($history[$item['itemid']][0]) ? $history[$item['itemid']][0] : null;
				$prevHistory = isset($history[$item['itemid']][1]) ? $history[$item['itemid']][1] : null;

				if (isset($requestedOutput['lastclock'])) {
					$item['lastclock'] = $lastHistory ? $lastHistory['clock'] : '0';
				}
				if (isset($requestedOutput['lastns'])) {
					$item['lastns'] = $lastHistory ? $lastHistory['ns'] : '0';
				}
				if (isset($requestedOutput['lastvalue'])) {
					$item['lastvalue'] = $lastHistory ? $lastHistory['value'] : '0';
				}
				if (isset($requestedOutput['prevvalue'])) {
					$item['prevvalue'] = $prevHistory ? $prevHistory['value'] : '0';
				}
			}
			unset($item);
		}

		if ($options['selectPreprocessing'] !== null && $options['selectPreprocessing'] != API_OUTPUT_COUNT) {
			$db_item_preproc = API::getApiService()->select('item_preproc', [
				'output' => $this->outputExtend($options['selectPreprocessing'], ['itemid', 'step']),
				'filter' => ['itemid' => array_keys($result)],
			]);

			CArrayHelper::sort($db_item_preproc, ['step']);

			foreach ($result as &$item) {
				$item['preprocessing'] = [];
			}
			unset($item);

			foreach ($db_item_preproc as $step) {
				$itemid = $step['itemid'];
				unset($step['item_preprocid'], $step['itemid'], $step['step']);

				if (array_key_exists($itemid, $result)) {
					$result[$itemid]['preprocessing'][] = $step;
				}
			}
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput']) {
			if ($options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
			}

			if ($options['selectInterfaces'] !== null) {
				$sqlParts = $this->addQuerySelect('i.interfaceid', $sqlParts);
			}

			if ($this->outputIsRequested('lastclock', $options['output'])
					|| $this->outputIsRequested('lastns', $options['output'])
					|| $this->outputIsRequested('lastvalue', $options['output'])
					|| $this->outputIsRequested('prevvalue', $options['output'])) {

				$sqlParts = $this->addQuerySelect('i.value_type', $sqlParts);
			}
		}

		return $sqlParts;
	}
}
