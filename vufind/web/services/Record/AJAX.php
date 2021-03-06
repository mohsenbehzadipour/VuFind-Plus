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

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/sys/Proxy_Request.php';

global $configArray;

class Record_AJAX extends Action {

	function launch() {
		global $timer;
		global $analytics;
		$analytics->disableTracking();
		$method = $_GET['method'];
		$timer->logTime("Starting method $method");
		if (in_array($method, array('RateTitle', 'GetSeriesTitles', 'GetComments', 'SaveComment', 'SaveTag', 'SaveRecord'))){
			header('Content-type: text/plain');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			echo $this->$method();
		}else if (in_array($method, array('GetGoDeeperData', 'getPurchaseOptions', 'getDescription'))){
			header('Content-type: text/html');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			echo $this->$method();
		}else if ($method == 'downloadMarc'){
			echo $this->$method();
		}else{
			header ('Content-type: text/xml');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

			$xmlResponse = '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
			$xmlResponse .= "<AJAXResponse>\n";
			if (is_callable(array($this, $_GET['method']))) {
				$xmlResponse .= $this->$_GET['method']();
			} else {
				$xmlResponse .= '<Error>Invalid Method</Error>';
			}
			$xmlResponse .= '</AJAXResponse>';

			echo $xmlResponse;
		}
	}

	function downloadMarc(){
		$id = $_REQUEST['id'];
		$marcData = MarcLoader::loadMarcRecordByILSId($id);
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header("Content-Disposition: attachment; filename={$id}.mrc");
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');

		header('Content-Length: ' . strlen($marcData->toRaw()));
		ob_clean();
		flush();
		echo($marcData->toRaw());
	}
	function getPurchaseOptions(){
		global $interface;
		if (isset($_REQUEST['id'])){
			$id = $_REQUEST['id'];
			$interface->assign('id', $id);
			$marcRecord = MarcLoader::loadMarcRecordByILSId($id);
			if ($marcRecord){
				$linkFields = $marcRecord->getFields('856') ;
				$purchaseLinks = array();
				if ($linkFields){
					$field856Index = 0;
					/** @var File_MARC_Data_Field[] $linkFields */
					foreach ($linkFields as $marcField){
						$field856Index++;
						//Get the link
						if ($marcField->getSubfield('u')){
							$link = $marcField->getSubfield('u')->getData();
							if ($marcField->getSubfield('3')){
								$linkText = $marcField->getSubfield('3')->getData();
							}elseif ($marcField->getSubfield('y')){
								$linkText = $marcField->getSubfield('y')->getData();
							}elseif ($marcField->getSubfield('z')){
								$linkText = $marcField->getSubfield('z')->getData();
							}else{
								$linkText = $link;
							}
							//Process some links differently so we can either hide them
							//or show them in different areas of the catalog.
							if (preg_match('/purchase|buy/i', $linkText) ||
							preg_match('/barnesandnoble|tatteredcover|amazon\.com/i', $link)){
								if (preg_match('/barnesandnoble/i', $link)){
									$purchaseLinks[] = array(
		        		  	  'link' => $link,
	                    'linkText' => 'Buy from Barnes & Noble',
		        		  		'storeName' => 'Barnes & Noble',
											'image' => '/images/barnes_and_noble.png',
											'field856Index' => $field856Index,
									);
								}else if (preg_match('/tatteredcover/i', $link)){
									$purchaseLinks[] = array(
	                    'link' => $link,
	                    'linkText' => 'Buy from Tattered Cover',
		        		  		'storeName' => 'Tattered Cover',
											'image' => '/images/tattered_cover.png',
											'field856Index' => $field856Index,
									);
								}else if (preg_match('/amazon\.com/i', $link)){
									$purchaseLinks[] = array(
	                    'link' => $link,
	                    'linkText' => 'Buy from Amazon',
	                  	'storeName' => 'Amazon',
											'image' => '/images/amazon.png',
											'field856Index' => $field856Index,
									);
								}else if (preg_match('/smashwords\.com/i', $link)){
									$purchaseLinks[] = array(
	                    'link' => $link,
	                    'linkText' => 'Buy from Smashwords',
	                  	'storeName' => 'Smashwords',
											'image' => '/images/smashwords.png',
											'field856Index' => $field856Index,
									);
								}else{
									$purchaseLinks[] = array(
	                    'link' => $link,
	                    'linkText' => $linkText,
	                  	'storeName' => 'Smashwords',
											'image' => '',
											'field856Index' => $field856Index,
									);
								}
							}
						}
					}
				} //End checking for purchase information in the marc record


				if (count($purchaseLinks) > 0){
					$interface->assign('purchaseLinks', $purchaseLinks);
				}else{
					$resource = new Resource();
					$resource->record_id = $id;
					$resource->source = 'VuFind';
					if ($resource->find(true)){

						$title = $resource->title;
						$author = $resource->author;
						require_once ROOT_DIR . '/services/Record/Purchase.php';
						$purchaseLinks = Record_Purchase::getStoresForTitle($title, $author);

						if (count($purchaseLinks) > 0){
							$interface->assign('purchaseLinks', $purchaseLinks);
						}else{
							$interface->assign('errors', array("Sorry we couldn't find any stores that offer this title."));
						}
					}else{
						$interface->assign('errors', array("Sorry we couldn't find a resource for that id."));
					}
				}
			}else{
				$errors = array("Could not load marc information for that id.");
				$interface->assign('errors', $errors);
			}
		}else{
			$errors = array("You must provide the id of the title to be purchased. ");
			$interface->assign('errors', $errors);
		}

		echo $interface->fetch('Record/ajax-purchase-options.tpl');
	}

	function IsLoggedIn()
	{
		require_once ROOT_DIR . '/services/MyResearch/lib/User.php';

		return "<result>" .
		(UserAccount::isLoggedIn() ? "True" : "False") . "</result>";
	}

	// Saves a Record to User's Account
	function SaveRecord()
	{
		require_once ROOT_DIR . '/services/Record/Save.php';
		require_once ROOT_DIR . '/services/MyResearch/lib/User_list.php';

		$result = array();
		if (UserAccount::isLoggedIn()) {
			$saveService = new Record_Save();
			$result = $saveService->saveRecord();
			if (!PEAR_Singleton::isError($result)) {
				$result['result'] = "Done";
			} else {
				$result['result'] = "Error";
			}
		} else {
			$result['result'] = "Unauthorized";
		}
		return json_encode($result);
	}

	function GetSaveStatus()
	{
		require_once ROOT_DIR . '/services/MyResearch/lib/User.php';
		require_once ROOT_DIR . '/services/MyResearch/lib/Resource.php';

		// check if user is logged in
		if ((!$user = UserAccount::isLoggedIn())) {
			return "<result>Unauthorized</result>";
		}

		// Check if resource is saved to favorites
		$resource = new Resource();
		$resource->record_id = $_GET['id'];
		$resource->source = 'VuFind';
		if ($resource->find(true)) {
			if ($user->hasResource($resource)) {
				return '<result>Saved</result>';
			} else {
				return '<result>Not Saved</result>';
			}
		} else {
			return '<result>Not Saved</result>';
		}
	}

	// Email Record
	function SendEmail()
	{
		require_once ROOT_DIR . '/services/Record/Email.php';

		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		$emailService = new Record_Email();
		$result = $emailService->sendEmail($_GET['to'], $_GET['from'], $_GET['message']);

		if (PEAR_Singleton::isError($result)) {
			return '<result>Error</result><details>' .
			htmlspecialchars($result->getMessage()) . '</details>';
		} else {
			if ($result === true){
				return '<result>Done</result>';
			}else{
				return '<result><![CDATA[' . $result . ']]></result>';
			}
		}
	}

	// SMS Record
	function SendSMS()
	{
		require_once ROOT_DIR . '/services/Record/SMS.php';
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		$sms = new SMS();
		$result = $sms->sendSMS();

		if (PEAR_Singleton::isError($result)) {
			return '<result>Error</result>';
		} else {
			if ($result === true){
				return '<result>Done</result>';
			}else{
				return '<result><![CDATA[' . $result . ']]></result>';
			}
		}
	}

	function SaveComment()
	{
		require_once ROOT_DIR . '/services/MyResearch/lib/Resource.php';

		$user = UserAccount::isLoggedIn();
		if ($user === false) {
			return "<result>Unauthorized</result>";
		}

		$resource = new Resource();
		$resource->record_id = $_GET['id'];
		$resource->source = 'VuFind';
		if (!$resource->find(true)) {
			$resource->insert();
		}
		$resource->addComment($_REQUEST['comment'], $user);

		return json_encode(array('result' => 'Done'));
	}

	function DeleteComment()
	{
		require_once ROOT_DIR . '/services/MyResearch/lib/Comments.php';
		global $user;

		// Process Delete Comment
		if (is_object($user)) {
			$comment = new Comments();
			$comment->id = $_GET['commentId'];
			if ($comment->find(true)) {
				if ($user->id == $comment->user_id || $user->hasRole('opacAdmin')) {
					$comment->delete();
				}
			}
		}
		return '<result>true</result>';
	}

	function GetComments()
	{
		global $interface;

		require_once ROOT_DIR . '/services/MyResearch/lib/Resource.php';
		require_once ROOT_DIR . '/services/MyResearch/lib/Comments.php';

		$interface->assign('id', $_GET['id']);

		$resource = new Resource();
		$resource->record_id = $_GET['id'];
		$resource->source = 'VuFind';
		$commentList = array('user'=>array(), 'staff'=> array());
		if ($resource->find(true)) {
			$commentList = $resource->getComments();
		}

		$interface->assign('commentList', $commentList['user']);
		$userComments = $interface->fetch('Record/view-comments-list.tpl');
		$interface->assign('staffCommentList', $commentList['staff']);
		$staffComments = $interface->fetch('Record/view-staff-reviews-list.tpl');

		return json_encode(array(
			'staffComments' => $staffComments,
			'userComments' => $userComments,
		));
	}

	function RateTitle(){
		require_once ROOT_DIR . '/services/MyResearch/lib/Resource.php';
		require_once(ROOT_DIR . '/Drivers/marmot_inc/UserRating.php');
		global $user;
		global $analytics;
		if (!isset($user) || $user == false){
			header('HTTP/1.0 500 Internal server error');
			return 'Please login to rate this title.';
		}
		$rating = $_REQUEST['rating'];
		//Save the rating
		$resource = new Resource();
		$resource->record_id = $_GET['id'];
		$resource->source = 'VuFind';
		if (!$resource->find(true)) {
			$resource->insert();
		}
		$resource->addRating($rating, $user);
		$analytics->addEvent('User Enrichment', 'Rate Title', $resource->title);

		/** @var Memcache $memCache */
		global $memCache;
		$memCache->delete('rating_' . $_GET['id']);

		return $rating;
	}

	function GetGoDeeperData(){
		require_once(ROOT_DIR . '/Drivers/marmot_inc/GoDeeperData.php');
		$dataType = $_REQUEST['dataType'];
		$upc = $_REQUEST['upc'];
		$isbn = $_REQUEST['isbn'];

		$formattedData = GoDeeperData::getHtmlData($dataType, 'Record', $isbn, $upc);
		return $formattedData;

	}

	function GetEnrichmentInfo(){
		require_once ROOT_DIR . '/services/Record/Enrichment.php';
		global $configArray;
		global $library;
		$isbn = $_REQUEST['isbn'];
		$upc = $_REQUEST['upc'];
		$id = $_REQUEST['id'];
		$enrichmentData = Record_Enrichment::loadEnrichment($isbn);
		global $interface;
		$interface->assign('id', $id);
		$interface->assign('enrichment', $enrichmentData);
		$showSimilarTitles = false;
		if (isset($enrichmentData['novelist']) && isset($enrichmentData['novelist']['similarTitles']) && is_array($enrichmentData['novelist']['similarTitles']) && count($enrichmentData['novelist']['similarTitles']) > 0){
			foreach ($enrichmentData['novelist']['similarTitles'] as $title){
				if ($title['recordId'] != -1){
					$showSimilarTitles = true;
					break;
				}
			}
		}
		if (isset($library) && $library->showSimilarTitles == 0){
			$interface->assign('showSimilarTitles', false);
		}else{
			$interface->assign('showSimilarTitles', $showSimilarTitles);
		}
		if (isset($library) && $library->showSimilarAuthors == 0){
			$interface->assign('showSimilarAuthors', false);
		}else{
			$interface->assign('showSimilarAuthors', true);
		}

		//Process series data
		$titles = array();
		if (!isset($enrichmentData['novelist']['series']) || count($enrichmentData['novelist']['series']) == 0){
			$interface->assign('seriesInfo', json_encode(array('titles'=>$titles, 'currentIndex'=>0)));
		}else{
			foreach ($enrichmentData['novelist']['series'] as $record){
				$isbn = $record['isbn'];
				if (strpos($isbn, ' ') > 0){
					$isbn = substr($isbn, 0, strpos($isbn, ' '));
				}
				$cover = $configArray['Site']['coverUrl'] . "/bookcover.php?size=medium&isn=" . $isbn;
				if (isset($record['id'])){
					$cover .= "&id=" . $record['id'];
				}
				if (isset($record['upc'])){
					$cover .= "&upc=" . $record['upc'];
				}
				if (isset($record['issn'])){
					$cover .= "&issn=" . $record['issn'];
				}
				if (isset($record['format_category'])){
					$cover .= "&category=" . $record['format_category'][0];
				}
				$title = $record['title'];
				if (isset($record['series'])){
					$title .= ' (' . $record['series'] ;
					if (isset($record['volume'])){
						$title .= ' Volume ' . $record['volume'];
					}
					$title .= ')';
				}
				$titles[] = array(
	        	  'id' => isset($record['id']) ? $record['id'] : '',
			    		'image' => $cover,
			    		'title' => $title,
			    		'author' => $record['author']
				);
			}

			foreach ($titles as $key => $rawData){
				if ($rawData['id']){
					if (strpos($rawData['id'], 'econtentRecord') === 0){
						$fullId = $rawData['id'];
						$shortId = str_replace('econtentRecord', '', $rawData['id']);
						$formattedTitle = "<div id=\"scrollerTitleSeries{$key}\" class=\"scrollerTitle\">" .
								'<a href="' . $configArray['Site']['path'] . "/EcontentRecord/" . $shortId . '" id="descriptionTrigger' . $fullId . '">' .
								"<img src=\"{$rawData['image']}\" class=\"scrollerTitleCover\" alt=\"{$rawData['title']} Cover\"/>" .
								"</a></div>" .
								"<div id='descriptionPlaceholder{$fullId}' style='display:none'></div>";
					}else{
						$shortId = str_replace('.', '', $rawData['id']);
						$formattedTitle = "<div id=\"scrollerTitleSeries{$key}\" class=\"scrollerTitle\">" .
							'<a href="' . $configArray['Site']['path'] . "/Record/" . $rawData['id'] . '" id="descriptionTrigger' . $shortId . '">' .
							"<img src=\"{$rawData['image']}\" class=\"scrollerTitleCover\" alt=\"{$rawData['title']} Cover\"/>" .
							"</a></div>" .
							"<div id='descriptionPlaceholder{$shortId}' style='display:none'></div>";
					}
				}else{
					$formattedTitle = "<div id=\"scrollerTitleSeries{$key}\" class=\"scrollerTitle\">" .
						"<img src=\"{$rawData['image']}\" class=\"scrollerTitleCover\" alt=\"{$rawData['title']} Cover\"/>" .
						"</div>";
				}
				$rawData['formattedTitle'] = $formattedTitle;
				$titles[$key] = $rawData;
			}
			$seriesInfo = array('titles' => $titles, 'currentIndex' => $enrichmentData['novelist']['seriesDefaultIndex']);
			$interface->assign('seriesInfo', json_encode($seriesInfo));
		}

		//Load go deeper options
		if (isset($library) && $library->showGoDeeper == 0){
			$interface->assign('showGoDeeper', false);
		}else{
			require_once(ROOT_DIR . '/Drivers/marmot_inc/GoDeeperData.php');
			$goDeeperOptions = GoDeeperData::getGoDeeperOptions($isbn, $upc);
			if (count($goDeeperOptions['options']) == 0){
				$interface->assign('showGoDeeper', false);
			}else{
				$interface->assign('showGoDeeper', true);
			}
		}

		return $interface->fetch('Record/ajax-enrichment.tpl');
	}

	function GetSeriesTitles(){
		//Get other titles within a series for display within the title scroller
		require_once 'Enrichment.php';
		$isbn = $_REQUEST['isbn'];
		$id = $_REQUEST['id'];
		$enrichmentData = Record_Enrichment::loadEnrichment($isbn);
		global $interface;
		$interface->assign('id', $id);
		$interface->assign('enrichment', $enrichmentData);


	}

	function GetHoldingsInfo(){
		require_once 'Holdings.php';
		global $interface;
		global $configArray;
		$interface->assign('showOtherEditionsPopup', 0);
		$id = strip_tags($_REQUEST['id']);
		$interface->assign('id', $id);
		Record_Holdings::loadHoldings($id);
		return $interface->fetch('Record/ajax-holdings.tpl');
	}

	function GetProspectorInfo(){
		require_once ROOT_DIR . '/Drivers/marmot_inc/Prospector.php';
		global $configArray;
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('id', $id);

		global $library;
		if (isset($library)){
			$interface->assign('showProspectorTitlesAsTab', $library->showProspectorTitlesAsTab);
		}else{
			$interface->assign('showProspectorTitlesAsTab', 1);
		}
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();
		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url = $configArray['Index']['url'];
		/** @var SearchObject_Solr $db */
		$db = new $class($url);
		if ($configArray['System']['debugSolr']) {
			$db->debug = true;
		}

		// Retrieve Full record from Solr
		if (!($record = $db->getRecord($id))) {
			PEAR_Singleton::raiseError(new PEAR_Error('Record Does Not Exist'));
		}

		$prospector = new Prospector();
		//Check to see if the record exists within Prospector so we can get the prospector Id
		$prospectorDetails = $prospector->getProspectorDetailsForLocalRecord($record);
		$interface->assign('prospectorDetails', $prospectorDetails);

		$searchTerms = array(
			array(
				'lookfor' => $record['title'],
				'index' => 'Title'
			),
		);
		if (isset($record['author'])){
			$searchTerms[] = array(
				'lookfor' => $record['author'],
				'index' => 'Author'
			);
		}
		$prospectorResults = $prospector->getTopSearchResults($searchTerms, 10, $prospectorDetails);
		$interface->assign('prospectorResults', $prospectorResults);
		return $interface->fetch('Record/ajax-prospector.tpl');
	}

	function GetReviewInfo(){
		require_once 'Reviews.php';
		$isbn = $_REQUEST['isbn'];
		$id = $_REQUEST['id'];
		$enrichmentData = Record_Reviews::loadReviews($id, $isbn);
		global $interface;
		$interface->assign('id', $id);
		$interface->assign('enrichment', $enrichmentData);
		return $interface->fetch('Record/ajax-reviews.tpl');
	}

	function getDescription(){
		/** @var Memcache $memCache */
		global $memCache;
		global $configArray;
		global $interface;
		$id = $_REQUEST['id'];
		//Bypass loading solr, etc if we already have loaded the descriptive info before
		$descriptionArray = $memCache->get("record_description_{$id}");
		if (!$descriptionArray){
			require_once 'Description.php';
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init();

			$description = new Record_Description(true, $id);
			$descriptionArray = $description->loadData();
			$memCache->set("record_description_{$id}", $descriptionArray, 0, $configArray['Caching']['record_description']);
		}
		$interface->assign('description', $descriptionArray['description']);
		$interface->assign('length', isset($descriptionArray['length']) ? $descriptionArray['length'] : '');
		$interface->assign('publisher', isset($descriptionArray['publisher']) ? $descriptionArray['publisher'] : '');

		return $interface->fetch('Record/ajax-description-popup.tpl');
	}
}