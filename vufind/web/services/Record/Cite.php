<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

require_once 'Record.php';

class Record_Cite extends Record_Record {
	function launch(){
		global $interface;

		// Do not cache holdings page
		$interface->caching = 0;

		$interface->setPageTitle('Title: ' . $this->record['title_short']);

		$this->loadCitation();

		if (isset($_GET['lightbox'])) {
			// Use for lightbox
			$interface->assign('lightbox', true);
			$interface->assign('title', translate('Citation'));
			echo $interface->fetch('Record/cite.tpl');
			//$html = file_get_contents('http://www.worldcat.org/oclc/4670293?page=citation');
			//return transform($html, 'services/Record/xsl/worldcat-cite.xsl');
		} else {
			// Display Page
			$interface->setPageTitle('Record Citations');
			$interface->assign('subTemplate', 'cite.tpl');
			$interface->setTemplate('view-alt.tpl');
			$interface->display('layout.tpl', 'RecordCite' . $_GET['id']);
		}
	}

	function loadCitation(){
		global $interface;

		$citationCount = 0;
		$formats = $this->recordDriver->getCitationFormats();
		foreach($formats as $current) {
			$interface->assign(strtolower($current),
			$this->recordDriver->getCitation($current));
			$citationCount++;
		}
		$interface->assign('citationCount', $citationCount);
	}
}