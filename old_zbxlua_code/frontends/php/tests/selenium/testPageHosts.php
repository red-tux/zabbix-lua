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

class testPageHosts extends CWebTest{
	// Returns all hosts
	public static function allHosts(){
		return DBdata('select * from hosts where status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')');
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_SimpleTest($host){
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','Zabbix servers');
		$this->assertTitle('Hosts');
		$this->ok('HOSTS');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Name','Applications','Items','Triggers','Graphs','Discovery','Interface','Templates','Status','Availability'));
		// Data
		$this->ok(array($host['name']));
		$this->dropdown_select('go','Export selected');
		$this->dropdown_select('go','Mass update');
		$this->dropdown_select('go','Activate selected');
		$this->dropdown_select('go','Disable selected');
		$this->dropdown_select('go','Delete selected');
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_FilterHost($host){
		$this->login('hosts.php');
		$this->click('flicker_icon_l');
		$this->input_type('filter_host',$host['name']);
		$this->input_type('filter_ip','');
		$this->input_type('filter_port','');
		$this->click('filter');
		$this->wait();
		$this->ok($host['name']);
	}

	// Filter returns nothing
	public function testPageHosts_FilterNone(){
		$this->login('hosts.php');

		// Reset filter
		$this->click('css=span.link_menu');

		$this->input_type('filter_host','1928379128ksdhksdjfh');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterNone1(){
		$this->login('hosts.php');

		// Reset filter
		$this->click('css=span.link_menu');

		$this->input_type('filter_host','_');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterNone2(){
		$this->login('hosts.php');

		// Reset filter
		$this->click('css=span.link_menu');

		$this->input_type('filter_host','%');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	// Filter reset

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_FilterReset($host){
		$this->login('hosts.php');
		$this->click('css=span.link_menu');
		$this->click('filter');
		$this->wait();
		$this->ok($host['name']);
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_Items($host){
		$hostid=$host['hostid'];

		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid','all');
		$this->assertTitle('Hosts');
		$this->ok('HOSTS');
		$this->ok('Displaying');
		// Go to the list of items
		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();
		// We are in the list of items
		$this->assertTitle('Configuration of items');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Wizard','Name','Triggers','Key','Interval','History','Trends','Type','Status','Applications','Error'));
	}

	public function testPageHosts_MassExportAll(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassExport(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassUpdateAll(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassUpdate(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassActivateAll(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassActivate(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassDisableAll(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassDisable(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassDeleteAll(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassDelete(){
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_Sorting(){
// TODO
		$this->markTestIncomplete();
	}
}
?>
