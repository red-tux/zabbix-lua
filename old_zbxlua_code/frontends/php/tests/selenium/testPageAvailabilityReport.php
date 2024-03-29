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

class testPageAvailabilityReport extends CWebTest
{
	public function testPageAvailabilityReport_ByHost_SimpleTest()
	{
		$this->login('report2.php?config=0');
		$this->assertTitle('Availability report');
		$this->ok('AVAILABILITY REPORT');
		$this->ok('Mode');
		$this->ok('Filter');
		$this->ok(array('Host','Name','Problems','Ok','Unknown','Graph'));
	}

// Check that no real host or template names displayed
	public function testPageAvailabilityReport_ByHost_NoHostNames()
	{
		$this->login('report2.php?config=0');
		$this->assertTitle('Availability report');
		$this->checkNoRealHostnames();
	}

	public function testPageAvailabilityReport_ByTriggerTemplate_SimpleTest()
	{
		$this->login('report2.php?config=1');
		$this->assertTitle('Availability report');
		$this->ok('AVAILABILITY REPORT');
		$this->ok('Mode');
		$this->ok('Filter');
		$this->ok(array('Host','Name','Problems','Ok','Unknown','Graph'));
	}

// Check that no real host or template names displayed
	public function testPageAvailabilityReport_ByTriggerTemplate_NoHostNames()
	{
		$this->login('report2.php?config=1');
		$this->checkNoRealHostnames();
	}
}
?>
