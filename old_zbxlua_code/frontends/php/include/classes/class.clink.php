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
class CLink extends CTag {
	protected $sid = null;

	public function __construct($item = null, $url = null, $class = null, $action = null, $nosid = null) {
		parent::__construct('a', 'yes');

		$this->tag_start = '';
		$this->tag_end = '';
		$this->tag_body_start = '';
		$this->tag_body_end = '';
		$this->nosid = $nosid;

		if (!is_null($class)) {
			$this->setAttribute('class', $class);
		}
		if (!is_null($item)) {
			$this->addItem($item);
		}
		if (!is_null($url)) {
			$this->setUrl($url);
		}
		if (!is_null($action)) {
			$this->setAttribute('onclick', $action);
		}
	}

	public function setUrl($value) {
		if (is_null($this->nosid)) {
			if (is_null($this->sid)) {
				$this->sid = isset($_COOKIE['zbx_sessionid']) ? substr($_COOKIE['zbx_sessionid'], 16, 16) : null;
			}
			if (!is_null($this->sid)) {
				if (zbx_strstr($value, '&') !== false || zbx_strstr($value, '?') !== false) {
					$value .= '&sid='.$this->sid;
				}
				else {
					$value .= '?sid='.$this->sid;
				}
			}
			$url = $value;
		}
		else {
			$url = $value;
		}
		$this->setAttribute('href', $url);
	}

	public function getUrl() {
		if (isset($this->attributes['href'])) {
			return $this->attributes['href'];
		}
		else {
			return null;
		}
	}

	public function setTarget($value = null) {
		if (is_null($value)) {
			unset($this->attributes['target']);
		}
		else {
			$this->attributes['target'] = $value;
		}
	}
}
?>
