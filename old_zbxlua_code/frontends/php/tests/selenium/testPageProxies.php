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
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testPageProxies extends CWebTest
{
	// Returns all proxies
	public static function allProxies()
	{
		return DBdata("select * from hosts where status in (".HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.") order by hostid");
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageProxies_SimpleTest($proxy)
	{
		$this->login('proxies.php');
		$this->assertTitle('Proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
		// Header
		$this->ok(array('Name','Mode','Last seen (age)','Host count','Item count','Required performance (vps)','Hosts'));
		// Data
		$this->ok(array($proxy['host']));
		$this->dropdown_select('go','Activate selected');
		$this->dropdown_select('go','Disable selected');
		$this->dropdown_select('go','Delete selected');
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageProxies_SimpleUpdate($proxy)
	{
		$proxyid=$proxy['hostid'];
		$name=$proxy['host'];

		$sql1="select * from hosts where host='$name' order by hostid";
		$oldHashProxy=DBhash($sql1);
		$sql2="select proxy_hostid from hosts order by hostid";
		$oldHashHosts=DBhash($sql2);

		$this->login('proxies.php');
		$this->assertTitle('Proxies');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Proxies');
		$this->ok('Proxy updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF PROXIES');

		$this->assertEquals($oldHashProxy,DBhash($sql1),"Chuck Norris: no-change proxy update should not update data in table 'hosts'");
		$this->assertEquals($oldHashHosts,DBhash($sql2),"Chuck Norris: no-change proxy update should not update 'hosts.proxy_hostid'");
	}

	public function testPageProxies_MassActivateAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageProxies_MassActivate($proxy)
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageProxies_MassDisableAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageProxies_MassDisable($proxy)
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageProxies_MassDeleteAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageProxies_MassDelete($proxy)
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageProxies_Sorting()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
