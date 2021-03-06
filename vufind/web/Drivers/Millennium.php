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
require_once ROOT_DIR . '/sys/Proxy_Request.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/CirculationStatus.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LoanRule.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LoanRuleDeterminer.php';
require_once ROOT_DIR . '/Drivers/Interface.php';
require_once ROOT_DIR . '/Drivers/Innovative.php';

/**
 * VuFind Connector for Marmot's Innovative catalog (millenium)
 *
 * This class uses screen scraping techniques to gather record holdings written
 * by Adam Bryn of the Tri-College consortium.
 *
 * @author Adam Brin <abrin@brynmawr.com>
 *
 * Extended by Mark Noble and CJ O'Hara based on specific requirements for
 * Marmot Library Network.
 *
 * @author Mark Noble <mnoble@turningleaftech.com>
 * @author CJ O'Hara <cj@marmot.org>
 */
class MillenniumDriver implements DriverInterface
{

	var $statusTranslations = null;
	var $holdableStatiRegex = null;
	var $availableStatiRegex = null;
	/** @var  Solr */
	public $db;

	/**
	 * Load information about circulation statuses from the database
	 * so we can perform translations easily and so we can determine
	 * what is available and what is not available
	 *
	 * @return void
	 */
	private function loadCircStatusInfo(){
		if (is_null($this->holdableStatiRegex)){
			$circStatus = new CirculationStatus();
			$circStatus->find();
			$holdableStati = array();
			$availableStati = array();
			if ($circStatus->N > 0){
				while ($circStatus->fetch()){
					if ($circStatus->holdable == 1){
						$holdableStati[] = $circStatus->millenniumName;
					}
					if ($circStatus->available == 1){
						$availableStati[] = $circStatus->millenniumName;
					}
					if (isset($circStatus->displayName) && is_string($circStatus->displayName) && strlen($circStatus->displayName) > 0){
						$this->statusTranslations[$circStatus->millenniumName] = $circStatus->displayName;
					}
				}
			}
			//Holdable statuses are statuses where the patron could get the item in a reasonable amount of time if they place a hold.
			$this->holdableStatiRegex = implode('|', $holdableStati);
			//Available statuses are statuses where the patron can walk into the library and get it pretty much immediately.
			$this->availableStatiRegex = implode('|', $availableStati);
		}
	}

	/** @var LoanRule[] $loanRules  */
	var $loanRules = null;
	/** @var LoanRuleDeterminer[] $loanRuleDeterminers */
	var $loanRuleDeterminers = null;
	private function loadLoanRules(){
		if (is_null($this->loanRules)){
			/** @var Memcache $memCache */
			global $memCache;
			global $configArray;
			$this->loanRules = $memCache->get('loan_rules');
			if (!$this->loanRules){
				$this->loanRules = array();
				$loanRule = new LoanRule();
				$loanRule->find();
				while ($loanRule->fetch()){
					$this->loanRules[$loanRule->loanRuleId] = clone($loanRule);
				}
			}
			$memCache->set('loan_rules', $this->loanRules, $configArray['Caching']['loan_rules']);

			$this->loanRuleDeterminers = $memCache->get('loan_rule_determiners');
			if (!$this->loanRuleDeterminers){
				$this->loanRuleDeterminers = array();
				$loanRuleDeterminer = new LoanRuleDeterminer();
				//$loanRuleDeterminer->active = 1;
				$loanRuleDeterminer->orderBy('rowNumber DESC');
				$loanRuleDeterminer->find();
				while ($loanRuleDeterminer->fetch()){
					$this->loanRuleDeterminers[$loanRuleDeterminer->rowNumber] = clone($loanRuleDeterminer);
				}
			}
			$memCache->set('loan_rule_determiners', $this->loanRuleDeterminers, $configArray['Caching']['loan_rules']);
		}
	}

	public function isUserStaff(){
		global $configArray;
		global $user;
		if (count($user->getRoles()) > 0){
			return true;
		}else if (isset($configArray['Staff P-Types'])){
			$staffPTypes = $configArray['Staff P-Types'];
			$pType = $this->getPType();
			if (array_key_exists($pType, $staffPTypes)){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	public function getMillenniumScope(){
		if (isset($_REQUEST['useUnscopedHoldingsSummary'])){
			return $this->getDefaultScope();
		}
		$searchLibrary = Library::getSearchLibrary();
		$searchLocation = Location::getSearchLocation();

		$branchScope = '';
		//Load the holding label for the branch where the user is physically.
		if (!is_null($searchLocation)){
			if ($searchLocation->useScope && $searchLocation->restrictSearchByLocation){
				$branchScope = $searchLocation->scope;
			}
		}
		if (strlen($branchScope)){
			return $branchScope;
		}else if (isset($searchLibrary) && $searchLibrary->useScope && $searchLibrary->restrictSearchByLibrary) {
			return $searchLibrary->scope;
		}else{
      return $this->getDefaultScope();
		}
	}

	public function getDefaultScope(){
		global $configArray;
		return isset($configArray['OPAC']['defaultScope']) ? $configArray['OPAC']['defaultScope'] : '93';
	}

	public function getMillenniumRecordInfo($id){
		global $configArray;

		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumCache.php';
		/** @var Memcache $memCache */
		global $memCache;
		$scope = $this->getMillenniumScope();
		//Clear millennium cache once per minute
		$lastCacheClear = $memCache->get('millennium_cache_interval');
		//echo ("lastCacheClear = $lastCacheClear, cache_interval = {$configArray['Caching']['millennium_cache_interval']}");
		if ($lastCacheClear == false || isset($_REQUEST['reload'])){
			//Get rid of anything in the cache older than 5 minutes
			$millenniumCache = new MillenniumCache();
			//First clean out any records that are more than 5 minutes old
			if (isset($_REQUEST['reload'])){
				$cacheExpirationTime = time() ;
			}else{
				$cacheExpirationTime = time() - 5 * 60;
			}
			//$logger->log("Clearing millennium cache before $cacheExpirationTime", PEAR_LOG_INFO);
			//Update memcache before clearing the database so we don't have tons of threads trying to clear the cache
			$memCache->set('millennium_cache_interval', $cacheExpirationTime, 0, $configArray['Caching']['millennium_cache_interval']);
			$millenniumCache->whereAdd("cacheDate < $cacheExpirationTime");
			$millenniumCache->delete(true);
		}
		//Now see if the record already exists in our cache.
		$millenniumCache = new MillenniumCache();
		$millenniumCache->recordId = $id;
		$millenniumCache->scope = $scope;
		$millenniumCache->find();
		if ($millenniumCache->N > 0){
			//Found a cache entry
			$millenniumCache->fetch();
			//We already deleted old cache entries so we don't need to check to see if the entry is stale.
			//Just return the entry
			return $millenniumCache;
		}
		//Load the pages for holdings, order information, and items
		$millenniumCache = new MillenniumCache();
		$millenniumCache->recordId = $id;
		$millenniumCache->scope = $scope;
		global $configArray;
		global $timer;
		if (substr($configArray['Catalog']['url'], -1) == '/') {
			$host = substr($configArray['Catalog']['url'], 0, -1);
		} else {
			$host = $configArray['Catalog']['url'];
		}

		// Strip ID
		$id_ = substr(str_replace('.b', '', $id), 0, -1);

		$req =  $host . "/search~S{$scope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/holdings~" . $id_;
		$millenniumCache->holdingsInfo = file_get_contents($req);
		//$logger->log("Loaded holdings from url $req", PEAR_LOG_DEBUG);
		$timer->logTime('got holdings from millennium');

		$req =  $host . "/search~S{$scope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/frameset~" . $id_;
		$millenniumCache->framesetInfo = file_get_contents($req);
		$timer->logTime('got frameset info from millennium');

		$millenniumCache->cacheDate = time();
		//Temporarily ignore errors
		disableErrorHandler();
		$millenniumCache->insert();
		enableErrorHandler();

		return $millenniumCache;

	}

	var $statuses = array();
	public function getStatus($id){
		global $timer;

		if (isset($this->statuses[$id])){
			return $this->statuses[$id];
		}
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumStatusLoader.php';
		$millenniumStatusLoader = new MillenniumStatusLoader($this);
		//Load circulation status information so we can use it later on to
		//determine what is holdable and what is not.
		self::loadCircStatusInfo();
		self::loadLoanRules();
		$timer->logTime('loadCircStatusInfo, loadLoanRules');

		$this->statuses[$id] = $millenniumStatusLoader->getStatus($id);

		return $this->statuses[$id];
	}

	public function getStatuses($ids) {
		$items = array();
		$count = 0;
		foreach ($ids as $id) {
			$items[$count] = $this->getStatus($id);
			$count++;
		}
		return $items;
	}

	/**
	 * Returns a summary of the holdings information for a single id. Used to display
	 * within the search results and at the top of a full record display to ensure
	 * the holding information makes sense to all users.
	 *
	 * @param string $id the id of the bid to load holdings for
	 * @param boolean $forSearch whether or not the summary will be shown in search results
	 * @return array an associative array with a summary of the holdings.
	 */
	public function getStatusSummary($id, $forSearch = false){
		//Load circulation status information so we can use it later on to
		//determine what is holdable and what is not.
		self::loadCircStatusInfo();
		self::loadLoanRules();

		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumStatusLoader.php';
		$millenniumStatusLoader = new MillenniumStatusLoader($this);
		return $millenniumStatusLoader->getStatusSummary($id, $forSearch);
	}

	/**
	 * Returns summary information for an array of ids.  This allows the search results
	 * to query all holdings at one time.
	 *
	 * @param array $ids an array ids to load summary information for.
	 * @param boolean $forSearch whether or not the summary will be shown in search results
	 * @return array an associative array containing a second array with summary information.
	 */
	public function getStatusSummaries($ids, $forSearch = false){
		$items = array();
		$count = 0;
		foreach ($ids as $id) {
			$items[$count] = $this->getStatusSummary($id, $forSearch);
			$count++;
		}
		return $items;
	}

	public function getHolding($id)
	{
		return $this->getStatus($id);
	}

	public function getPurchaseHistory($id)
	{
		return array();
	}

	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against the catalog.
	 * Interface defined in CatalogConnection.php
	 *
	 * @param   string  $username   The patron username
	 * @param   string  $password   The patron password
	 * @return  mixed               A string of the user's ID number
	 *                              If an error occures, return a PEAR_Error
	 * @access  public
	 */
	public function patronLogin($username, $password)
	{
		global $timer;

		//Strip any non digit characters from the password
		$password = preg_replace('/[a-or-zA-OR-Z\W]/', '', $password);
		if (strlen($password) == 5){
			$password = '41000000' . $password;
		}elseif (strlen($password) == 6){
			$password = '4100000' . $password;
		}

		//Load the raw information about the patron
		$patronDump = $this->_getPatronDump($password);

		//Create a variety of possible name combinations for testing purposes.
		$userValid = false;
		if (isset($patronDump['PATRN_NAME'])){
			$Fullname = str_replace(","," ",$patronDump['PATRN_NAME']);
			$Fullname = str_replace(";"," ",$Fullname);
			$Fullname = str_replace(";","'",$Fullname);
			$allNameComponents = preg_split('^[\s-]^', strtolower($Fullname));
			$nameParts = explode(' ',$Fullname);
			$lastname = strtolower($nameParts[0]);
			$middlename = isset($nameParts[2]) ? strtolower($nameParts[2]) : '';
			$firstname = isset($nameParts[1]) ? strtolower($nameParts[1]) : $middlename;

			//Get the first name that the user supplies.
			//This expects the user to enter one or two names and only
			//Validates the first name that was entered.
			$enteredNames=preg_split('^[\s-]^', strtolower($username));
			foreach ($enteredNames as $name){
				if (in_array($name, $allNameComponents, false)){
					$userValid = true;
					break;
				}
			}
		}
		if ($userValid){
			$user = array(
                'id'        => $password,
                'username'  => $patronDump['RECORD_#'],
                'firstname' => isset($firstname) ? $firstname : '',
                'lastname'  => isset($lastname) ? $lastname : '',
                'fullname'  => isset($Fullname) ? $Fullname : '',     //Added to array for possible display later.
                'cat_username' => $username, //Should this be $Fullname or $patronDump['PATRN_NAME']
                'cat_password' => $password,

                'email' => isset($patronDump['EMAIL_ADDR']) ? $patronDump['EMAIL_ADDR'] : '',
                'major' => null,
                'college' => null,
								'patronType' => $patronDump['P_TYPE'],
								'web_note' => isset($patronDump['WEB_NOTE']) ? $patronDump['WEB_NOTE'] : '');
			$timer->logTime("patron logged in successfully");
			return $user;

		} else {
			$timer->logTime("patron login failed");
			return null;
		}

	}

	private $patronProfiles = array();
	/**
	 * Get Patron Profile
	 *
	 * This is responsible for retrieving the profile for a specific patron.
	 * Interface defined in CatalogConnection.php
	 *
	 * @param   array   $patron     The patron array
	 * @return  array               Array of the patron's profile data
	 *                              If an error occures, return a PEAR_Error
	 * @access  public
	 */
	public function getMyProfile($patron)
	{
		global $timer;
		global $configArray;

		if (is_object($patron)){
			$patron = get_object_vars($patron);
			$id2 = $this->_getBarcode();
		}else{
			$id2= $patron['id'];
		}

		if (array_key_exists($patron['id'], $this->patronProfiles)){
			$timer->logTime('Retrieved Cached Profile for Patron');
			return $this->patronProfiles[$patron['id']];
		}

		//Load the raw information about the patron
		$patronDump = $this->_getPatronDump($id2);

		if (isset($patronDump['ADDRESS'])){
			$Fulladdress = $patronDump['ADDRESS'];
			$addressParts =explode('$',$Fulladdress);
			$Address1 = $addressParts[0];
			$City = isset($addressParts[1]) ? $addressParts[1] : '';
			$State = isset($addressParts[2]) ? $addressParts[2] : '';
			$Zip = isset($addressParts[3]) ? $addressParts[3] : '';

			if (preg_match('/(.*?),\\s+(.*)\\s+(\\d*(?:-\\d*)?)/', $City, $matches)) {
				$City = $matches[1];
				$State = $matches[2];
				$Zip = $matches[3];
			}
		}else{
			$Address1 = "";
			$City = "";
			$State = "";
			$Zip = "";
		}

		$fullName = $patronDump['PATRN_NAME'];

		$nameParts = explode(', ',$fullName);
		$lastName = $nameParts[0];
		$secondName = isset($nameParts[1]) ? $nameParts[1] : '';
		if (strpos($secondName, ' ')){
			$nameParts2 = explode(' ', $secondName);
			$firstName = $nameParts2[0];
		}else{
			$firstName = $secondName;
		}

		//Get additional information about the patron's home branch for display.
		$homeBranchCode = $patronDump['HOME_LIBR'];
		//Translate home branch to plain text
		global $user;

		$location = new Location();
		$location->whereAdd("code = '$homeBranchCode'");
		$location->find(1);

		if ($user) {
			if ($user->homeLocationId == 0) {
				$user->homeLocationId = $location->locationId;
				if ($location->nearbyLocation1 > 0){
					$user->myLocation1Id = $location->nearbyLocation1;
				}else{
					$user->myLocation1Id = $location->locationId;
				}
				if ($location->nearbyLocation2 > 0){
					$user->myLocation2Id = $location->nearbyLocation2;
				}else{
					$user->myLocation2Id = $location->locationId;
				}
				if ($user instanceof User) {
					//Update the database
					$user->update();
					//Update the serialized instance stored in the session
					$_SESSION['userinfo'] = serialize($user);
				}
			}

			//Get display name for preferred location 1
			$myLocation1 = new Location();
			$myLocation1->whereAdd("locationId = '$user->myLocation1Id'");
			$myLocation1->find(1);

			//Get display name for preferred location 1
			$myLocation2 = new Location();
			$myLocation2->whereAdd("locationId = '$user->myLocation2Id'");
			$myLocation2->find(1);
		}

		//see if expiration date is close
		list ($monthExp, $dayExp, $yearExp) = explode("-",$patronDump['EXP_DATE']);
		$timeExpire = strtotime($monthExp . "/" . $dayExp . "/" . $yearExp);
		$timeNow = time();
		$timeToExpire = $timeExpire - $timeNow;
		if ($timeToExpire <= 30 * 24 * 60 * 60){
			$expireClose = 1;
		}else{
			$expireClose = 0;
		}

		$finesVal = floatval(preg_replace('/[^\\d.]/', '', $patronDump['MONEY_OWED']));

		$numHoldsAvailable = 0;
		$numHoldsRequested = 0;
		$availableStatusRegex = isset($configArray['Catalog']['patronApiAvailableHoldsRegex']) ? $configArray['Catalog']['patronApiAvailableHoldsRegex'] : "/ST=(105|98),/";
		if (isset($patronDump['HOLD']) && count($patronDump['HOLD']) > 0){
			foreach ($patronDump['HOLD'] as $hold){
				if (preg_match("$availableStatusRegex", $hold)){
					$numHoldsAvailable++;
				}else{
					$numHoldsRequested++;
				}
			}
		}
		$profile = array('lastname' => $lastName,
				'firstname' => $firstName,
				'fullname' => $fullName,
				'address1' => $Address1,
				'address2' => $City . ', ' . $State,
				'city' => $City,
				'state' => $State,
				'zip'=> $Zip,
				'email' => isset($patronDump['EMAIL_ADDR']) ? $patronDump['EMAIL_ADDR'] : '',
				'overdriveEmail' => ($user) ? $user->overdriveEmail : (isset($patronDump['EMAIL_ADDR']) ? $patronDump['EMAIL_ADDR'] : ''),
				'promptForOverdriveEmail' => $user ? $user->promptForOverdriveEmail : 1,
				'phone' => isset($patronDump['TELEPHONE']) ? $patronDump['TELEPHONE'] : '',
				'fines' => $patronDump['MONEY_OWED'],
				'finesval' =>$finesVal,
				'expires' =>$patronDump['EXP_DATE'],
				'expireclose' =>$expireClose,
				'homeLocationCode' => trim($homeBranchCode),
				'homeLocationId' => $location->locationId,
				'homeLocation' => $location->displayName,
				'myLocation1Id' => ($user) ? $user->myLocation1Id : -1,
				'myLocation1' => isset($myLocation1) ? $myLocation1->displayName : '',
				'myLocation2Id' => ($user) ? $user->myLocation2Id : -1,
				'myLocation2' => isset($myLocation2) ? $myLocation2->displayName : '',
				'numCheckedOut' => $patronDump['CUR_CHKOUT'],
				'numHolds' => isset($patronDump['HOLD']) ? count($patronDump['HOLD']) : 0,
				'numHoldsAvailable' => $numHoldsAvailable,
				'numHoldsRequested' => $numHoldsRequested,
				'bypassAutoLogout' => ($user) ? $user->bypassAutoLogout : 0,
				'ptype' => $patronDump['P_TYPE'],
				'notices' => $patronDump['NOTICE_PREF'],
				'web_note' => isset($patronDump['WEB_NOTE']) ? $patronDump['WEB_NOTE'] : '',
		);

		//Get eContent info as well
		require_once(ROOT_DIR . '/Drivers/EContentDriver.php');
		$eContentDriver = new EContentDriver();
		$eContentAccountSummary = $eContentDriver->getAccountSummary();
		$profile = array_merge($profile, $eContentAccountSummary);

		//Get a count of the materials requests for the user
		if ($user){
			$materialsRequest = new MaterialsRequest();
			$materialsRequest->createdBy = $user->id;
			$homeLibrary = Library::getPatronHomeLibrary();
			$statusQuery = new MaterialsRequestStatus();
			$statusQuery->isOpen = 1;
			$statusQuery->libraryId = $homeLibrary->libraryId;
			$materialsRequest->joinAdd($statusQuery);
			$materialsRequest->find();
			$profile['numMaterialsRequests'] = $materialsRequest->N;
		}

		$timer->logTime("Got Patron Profile");
		$this->patronProfiles[$patron['id']] = $profile;
		return $profile;
	}

	/**
	 * Get a dump of information from Millennium that can be used in other
	 * routines.
	 *
	 * @param string  $barcode the patron's barcode
	 * @param boolean $forceReload whether or not cached data can be used.
	 * @return array
	 */
	public function _getPatronDump($barcode, $forceReload = false)
	{
		global $configArray;
		/** @var Memcache $memCache */
		global $memCache;
		global $timer;
		$patronDump = $memCache->get("patron_dump_$barcode");
		if (!$patronDump || $forceReload){
			$host=$configArray['OPAC']['patron_host'];
			//Special processing to allow MCVSD Students to login
			//with their student id.
			if (strlen($barcode)== 5){
				$barcode = "41000000" . $barcode;
			}elseif (strlen($barcode)== 6){
				$barcode = "4100000" . $barcode;
			}

			// Load Record Page.  This page has a dump of all patron information
			//as a simple name value pair list within the body of the webpage.
			//Sample format of a row is as follows:
			//P TYPE[p47]=100<BR>
			$req =  $host . "/PATRONAPI/" . $barcode ."/dump" ;
			$req = new Proxy_Request($req);
			//$result = file_get_contents($req);
			if (PEAR_Singleton::isError($req->sendRequest())) {
				return null;
			}
			$result = $req->getResponseBody();

			//Strip the actual contents out of the body of the page.
			$r = substr($result, stripos($result, 'BODY'));
			$r = substr($r,strpos($r,">")+1);
			$r = substr($r,0,stripos($r,"</BODY"));

			//Remove the bracketed information from each row
			$r = preg_replace("/\[.+?]=/","=",$r);

			//Split the rows on each BR tag.
			//This could also be done with a regex similar to the following:
			//(.*)<BR\s*>
			//And then get all matches of group 1.
			//Or a regex similar to
			//(.*?)\[.*?\]=(.*?)<BR\s*>
			//Group1 would be the keys and group 2 the values.
			$rows = preg_replace("/<BR.*?>/","*",$r);
			$rows = explode("*",$rows);
			//Add the key and value from each row into an associative array.
			$patronDump = array();
			foreach ($rows as $row) {
				if (strlen(trim($row)) > 0){
					$ret = explode("=",$row, 2);
					//$patronDump[str_replace(" ", "_", trim($ret[0]))] = str_replace("$", " ",$ret[1]);
					$patronDumpKey = str_replace(" ", "_", trim($ret[0]));
					//Holds can be an array, treat them differently.
					if ($patronDumpKey == 'HOLD'){
						$patronDump[$patronDumpKey][] = isset($ret[1]) ? $ret[1] : '';
					}else{
						$patronDump[$patronDumpKey] = isset($ret[1]) ? $ret[1] : '';
					}
				}
			}
			$timer->logTime("Got patron information from Patron API");

			if (isset($configArray['ERRNUM'])){
				return null;
			}else{

				$memCache->set("patron_dump_$barcode", $patronDump, 0, $configArray['Caching']['patron_dump']);
				//Need to wait a little bit since getting the patron api locks the record in the DB
				usleep(250);
			}
		}
		return $patronDump;
	}

	private $curl_connection;
	public function getMyTransactions( $page = 1, $recordsPerPage = -1, $sortOption = 'dueDate') {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumCheckouts.php';
		$millenniumCheckouts = new MillenniumCheckouts($this);
		return $millenniumCheckouts->getMyTransactions($page, $recordsPerPage, $sortOption);
	}

	/**
	 * Uses CURL to fetch a page from millennium and return the raw results
	 * for further processing.
	 *
	 * Performs minimal processing on it's own to remove HTML comments.
	 *
	 * @param array     $patronInfo information about a patron fetched from millenium
	 * @param string    $page       The page to load within millenium
	 *
	 * @return string the result of the page load.
	 */
	public function _fetchPatronInfoPage($patronInfo, $page){
		$cookieJar = tempnam ("/tmp", "CURLCOOKIE");
		$deleteCookie = true;
		global $logger;
		//$logger->log('PatronInfo cookie ' . $cookie, PEAR_LOG_INFO);
		global $configArray;
		$scope = $this->getDefaultScope();
		$curl_url = $configArray['Catalog']['url'] . "/patroninfo~S{$scope}/" . $patronInfo['RECORD_#'] ."/$page";
		$logger->log('Loading page ' . $curl_url, PEAR_LOG_INFO);
		//echo "$curl_url";
		$this->curl_connection = curl_init($curl_url);

		curl_setopt($this->curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($this->curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
		curl_setopt($this->curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl_connection, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->curl_connection, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($this->curl_connection, CURLOPT_UNRESTRICTED_AUTH, true);
		curl_setopt($this->curl_connection, CURLOPT_COOKIEJAR, $cookieJar );
		curl_setopt($this->curl_connection, CURLOPT_COOKIESESSION, is_null($cookieJar) ? true : false);

		$post_data = $this->_getLoginFormValues();
		$post_items = array();
		foreach ($post_data as $key => $value) {
			$post_items[] = $key . '=' . urlencode($value);
		}
		$post_string = implode ('&', $post_items);
		curl_setopt($this->curl_connection, CURLOPT_POSTFIELDS, $post_string);
		$sResult = curl_exec($this->curl_connection);

		if (true){
			curl_close($this->curl_connection);
		}

		//For debugging purposes
		//echo "<h1>CURL Results</h1>For URL: $curl_url<br /> $sresult";
		if ($deleteCookie){
			unlink($cookieJar);
		}

		//Strip HTML comments
		$sResult = preg_replace("/<!--([^(-->)]*)-->/"," ",$sResult);
		return $sResult;
	}

	public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut") {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumReadingHistory.php';
		$millenniumReadingHistory = new MillenniumReadingHistory($this);
		return $millenniumReadingHistory->getReadingHistory($patron, $page, $recordsPerPage, $sortOption);
	}

	/**
	 * Do an update or edit of reading history information.  Current actions are:
	 * deleteMarked
	 * deleteAll
	 * exportList
	 * optOut
	 *
	 * @param   string  $action         The action to perform
	 * @param   array   $selectedTitles The titles to do the action on if applicable
	 */
	function doReadingHistoryAction($action, $selectedTitles){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumReadingHistory.php';
		$millenniumReadingHistory = new MillenniumReadingHistory($this);
		$millenniumReadingHistory->doReadingHistoryAction($action, $selectedTitles);
	}

	public function getMyHolds($patron, $page = 1, $recordsPerPage = -1, $sortOption = 'title'){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->getMyHolds($patron, $page, $recordsPerPage, $sortOption);
	}

	public function parseHoldsPage($sresult){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->parseHoldsPage($sresult);
	}

	/**
	 * Place Hold
	 *
	 * This is responsible for both placing holds as well as placing recalls.
	 *
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $patronId   The id of the patron
	 * @param   string  $comment    Any comment regarding the hold or recall
	 * @param   string  $type       Whether to place a hold or recall
	 * @return  mixed               True if successful, false if unsuccessful
	 *                              If an error occures, return a PEAR_Error
	 * @access  public
	 */
	public function placeHold($recordId, $patronId, $comment, $type){
		$result = $this->placeItemHold($recordId, null, $patronId, $comment, $type);
		return $result;
	}

	/**
	 * Place Item Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $itemId     The id of the item to hold
	 * @param   string  $patronId   The id of the patron
	 * @param   string  $comment    Any comment regarding the hold or recall
	 * @param   string  $type       Whether to place a hold or recall
	 * @param   string  $type       The date when the hold should be cancelled if any
	 * @return  mixed               True if successful, false if unsuccessful
	 *                              If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	public function placeItemHold($recordId, $itemId, $patronId, $comment, $type){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->placeItemHold($recordId, $itemId, $patronId, $comment, $type);
	}


	public function updateHold($requestId, $patronId, $type, $title){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->updateHold($requestId, $patronId, $type, $title);
	}

	public function updateHoldDetailed($patronId, $type, $title, $xNum, $cancelId, $locationId, $freezeValue='off'){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		$millenniumHolds->updateHoldDetailed($patronId, $type, $title, $xNum, $cancelId, $locationId, $freezeValue);
	}

	public function renewAll(){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumCheckouts.php';
		$millenniumCheckouts = new MillenniumCheckouts($this);
		return $millenniumCheckouts->renewAll();
	}

	public function renewItem($itemId, $itemIndex){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumCheckouts.php';
		$millenniumCheckouts = new MillenniumCheckouts($this);
		return $millenniumCheckouts->renewItem($itemId, $itemIndex);
	}

	public function updatePatronInfo($canUpdateContactInfo){
		global $user;
		global $configArray;
		global $analytics;

		//Setup the call to Millennium
		$patronDump = $this->_getPatronDump($this->_getBarcode());

		$this->_updateVuFindPatronInfo();

		if ($canUpdateContactInfo){
			$scope = $this->getMillenniumScope();
			//Update profile information
			$extraPostInfo = array();
			if (isset($_REQUEST['address1'])){
				$extraPostInfo['addr1a'] = $_REQUEST['address1'];
				$extraPostInfo['addr1b'] = $_REQUEST['city'] . ', ' . $_REQUEST['state'] . ' ' . $_REQUEST['zip'];
				$extraPostInfo['addr1c'] = '';
				$extraPostInfo['addr1d'] = '';
			}
			$extraPostInfo['tele1'] = $_REQUEST['phone'];
			$extraPostInfo['email'] = $_REQUEST['email'];

			if (isset($_REQUEST['notices'])){
				$extraPostInfo['notices'] = $_REQUEST['notices'];
			}

			//Login to the patron's account
			$cookieJar = tempnam ("/tmp", "CURLCOOKIE");

			$curl_url = $configArray['Catalog']['url'] . "/patroninfo";

			$curl_connection = curl_init($curl_url);
			curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
			curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($curl_connection, CURLOPT_UNRESTRICTED_AUTH, true);
			curl_setopt($curl_connection, CURLOPT_COOKIEJAR, $cookieJar );
			curl_setopt($curl_connection, CURLOPT_COOKIESESSION, false);
			curl_setopt($curl_connection, CURLOPT_POST, true);
			$post_data = $this->_getLoginFormValues();
			$post_items = array();
			foreach ($post_data as $key => $value) {
				$post_items[] = $key . '=' . urlencode($value);
			}
			$post_string = implode ('&', $post_items);
			curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);
			curl_exec($curl_connection);

			//Issue a post request to update the patron information
			$post_items = array();
			foreach ($extraPostInfo as $key => $value) {
				$post_items[] = $key . '=' . urlencode($value);
			}
			$patronUpdateParams = implode ('&', $post_items);
			curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $patronUpdateParams);
			$curl_url = $configArray['Catalog']['url'] . "/patroninfo~S{$scope}/" . $patronDump['RECORD_#'] ."/modpinfo";
			curl_setopt($curl_connection, CURLOPT_URL, $curl_url);
			$sresult = curl_exec($curl_connection);

			curl_close($curl_connection);
			unlink($cookieJar);

			//Make sure to clear any cached data
			/** @var Memcache $memCache */
			global $memCache;
			$memCache->delete("patron_dump_{$this->_getBarcode()}");
			usleep(250);
		}

		//Should get Patron Information Updated on success
		if (isset($sresult) && preg_match('/Patron information updated/', $sresult)){
			if ($canUpdateContactInfo){
				$user->phone = $_REQUEST['phone'];
				$user->email = $_REQUEST['email'];
				$user->update();
			}
			//Update the serialized instance stored in the session
			$_SESSION['userinfo'] = serialize($user);
			if ($analytics){
				$analytics->addEvent('ILS Integration', 'Profile updated successfully');
			}
			return true;
		}else{
			if ($analytics){
				$analytics->addEvent('ILS Integration', 'Profile update failed');
			}
			return false;
		}

	}

	protected function _updateVuFindPatronInfo(){
		global $user;

		//Validate that the input data is correct
		if (isset($_POST['myLocation1']) && preg_match('/^\d{1,3}$/', $_POST['myLocation1']) == 0){
			PEAR_Singleton::raiseError('The 1st location had an incorrect format.');
		}
		if (isset($_POST['myLocation2']) && preg_match('/^\d{1,3}$/', $_POST['myLocation2']) == 0){
			PEAR_Singleton::raiseError('The 2nd location had an incorrect format.');
		}
		if (isset($_REQUEST['bypassAutoLogout'])){
			if ($_REQUEST['bypassAutoLogout'] == 'yes'){
				$user->bypassAutoLogout = 1;
			}else{
				$user->bypassAutoLogout = 0;
			}
		}
		if (isset($_REQUEST['promptForOverdriveEmail'])){
			if ($_REQUEST['promptForOverdriveEmail'] == 'yes'){
				$user->promptForOverdriveEmail = 1;
			}else{
				$user->promptForOverdriveEmail = 0;
			}
		}
		if (isset($_REQUEST['overdriveEmail'])){
			$user->overdriveEmail = strip_tags($_REQUEST['overdriveEmail']);
		}
		//Make sure the selected location codes are in the database.
		if (isset($_POST['myLocation1'])){
			$location = new Location();
			$location->whereAdd("locationId = '{$_POST['myLocation1']}'");
			$location->find();
			if ($location->N != 1) {
				PEAR_Singleton::raiseError('The 1st location could not be found in the database.');
			}
			$user->myLocation1Id = $_POST['myLocation1'];
		}
		if (isset($_POST['myLocation2'])){
			$location = new Location();
			$location->whereAdd();
			$location->whereAdd("locationId = '{$_POST['myLocation2']}'");
			$location->find();
			if ($location->N != 1) {
				PEAR_Singleton::raiseError('The 2nd location could not be found in the database.');
			}
			$user->myLocation2Id = $_POST['myLocation2'];
		}
		$user->update();
		//Update the serialized instance stored in the session
		$_SESSION['userinfo'] = serialize($user);
	}

	var $pType;
	/**
	 * returns the patron type identifier if a patron is logged in or if the patron
	 * is not logged in, it will return the default PType for the library domain.
	 * If a domain is not in use it will return -1.
	 *
	 * @return int
	 */
	public function getPType(){
		if ($this->pType == null){
			/** @var $user User */
			global $user;
			/** @var $locationSingleton Location */
			global $locationSingleton;
			$searchLocation = $locationSingleton->getSearchLocation();
			$searchLibrary = Library::getSearchLibrary();
			if (isset($user) && $user != false){
				$patronDump = $this->_getPatronDump($this->_getBarcode());
				$this->pType = $patronDump['P_TYPE'];
			}else if (isset($searchLocation) && $searchLocation->defaultPType > 0){
				$this->pType = $searchLocation->defaultPType;
			}else if (isset($searchLibrary) && $searchLibrary->defaultPType > 0){
				$this->pType = $searchLibrary->defaultPType;
			}else{
				$this->pType = -1;
			}
		}
		return $this->pType;
	}

	public function _getBarcode(){
		global $user;
		if (strlen($user->cat_password) == 5){
			$user->cat_password = '41000000' . $user->cat_password;
		}elseif (strlen($user->cat_password) == 6){
			$user->cat_password = '4100000' . $user->cat_password;
		}
		return $user->cat_password;
	}

	/**
	 * Checks millennium to determine if there are issue summaries available.
	 * If there are issue summaries available, it will return them in an array.
	 * With holdings below them.
	 *
	 * If there are no issue summaries, null will be returned from the summary.
	 *
	 * @param MillenniumCache $millenniumInfo - Information from Millennium to load issue information from.
	 *
	 * @return mixed - array or null
	 */
	public function getIssueSummaries($millenniumInfo){
		//Issue summaries are loaded from the main record page.

		if (preg_match('/class\\s*=\\s*\\"bibHoldings\\"/s', $millenniumInfo->framesetInfo)){
			//There are issue summaries available
			//Extract the table with the holdings
			$issueSummaries = array();
			$matches = array();
			if (preg_match('/<table\\s.*?class=\\"bibHoldings\\">(.*?)<\/table>/s', $millenniumInfo->framesetInfo, $matches)) {
				$issueSummaryTable = trim($matches[1]);
				//Each holdingSummary begins with a holdingsDivider statement
				$summaryMatches = explode('<tr><td colspan="2"><hr  class="holdingsDivider" /></td></tr>', $issueSummaryTable);
				if (count($summaryMatches) > 1){
					//Process each match independently
					foreach ($summaryMatches as $summaryData){
						$summaryData = trim($summaryData);
						if (strlen($summaryData) > 0){
							//Get each line within the summary
							$issueSummary = array();
							$issueSummary['type'] = 'issueSummary';
							$summaryLines = array();
							preg_match_all('/<tr\\s*>(.*?)<\/tr>/s', $summaryData, $summaryLines, PREG_SET_ORDER);
							for ($matchi = 0; $matchi < count($summaryLines); $matchi++) {
								$summaryLine = trim(str_replace('&nbsp;', ' ', $summaryLines[$matchi][1]));
								$summaryCols = array();
								if (preg_match('/<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>/s', $summaryLine, $summaryCols)) {
									$label = trim($summaryCols[1]);
									$value = trim(strip_tags($summaryCols[2]));
									//Check to see if this has a link to a check-in grid.
									if (preg_match('/.*?<a href="(.*?)">.*/s', $label, $linkData)) {
										//Parse the check-in id
										$checkInLink = $linkData[1];
										if (preg_match('/\/search~S\\d+\\?\/.*?\/.*?\/.*?\/(.*?)&.*/', $checkInLink, $checkInGridInfo)) {
											$issueSummary['checkInGridId'] = $checkInGridInfo[1];
										}
										$issueSummary['checkInGridLink'] = 'http://www.millenium.marmot.org' . $checkInLink;
									}
									//Convert to camel case
									$label = (preg_replace('/[^\\w]/', '', strip_tags($label)));
									$label = strtolower(substr($label, 0, 1)) . substr($label, 1);
									if ($label == 'location'){
										//Try to trim the courier code if any
										if (preg_match('/(.*?)\\sC\\d{3}\\w{0,2}$/', $value, $locationParts)){
											$value = $locationParts[1];
										}
									}
									$issueSummary[$label] = $value;
								}
							}
							$issueSummaries[$issueSummary['location'] . count($issueSummaries)] = $issueSummary;
						}
					}
				}
			}
			return $issueSummaries;
		}else{
			return null;
		}
	}

	/**
	 * @param File_MARC_Record $marcRecord
	 * @return bool
	 */
	function isRecordHoldable($marcRecord){
		$pType = $this->getPType();
		/** @var File_MARC_Data_Field[] $items */
		$items = $marcRecord->getFields('989');
		$holdable = false;
		$itemNumber = 0;
		foreach ($items as $item){
			$itemNumber++;
			$subfield_j = $item->getSubfield('j');
			if (is_object($subfield_j) && !$subfield_j->isEmpty()){
				$iType = $subfield_j->getData();
			}else{
				$iType = '0';
			}
			$subfield_d = $item->getSubfield('d');
			if (is_object($subfield_d) && !$subfield_d->isEmpty()){
				$locationCode = $subfield_d->getData();
			}else{
				$subfield_p = $item->getSubfield('p');
				if (is_object($subfield_p) && !$subfield_p->isEmpty()){
					$locationCode = $subfield_p->getData();
				}else{
					$locationCode = '?????';
				}
			}
			//$logger->log("$itemNumber) iType = $iType, locationCode = $locationCode", PEAR_LOG_DEBUG);

			//Check the determiner table to see if this matches
			$holdable = $this->isItemHoldableToPatron($locationCode, $iType, $pType);

			if ($holdable){
				break;
			}
		}
		return $holdable;
	}

	function isItemHoldableToPatron($locationCode, $iType, $pType){
		$holdable = false;
		//global $logger;
		//$logger->log("Checking loan rule for $locationCode, $iType, $pType", PEAR_LOG_DEBUG);
		foreach ($this->loanRuleDeterminers as $loanRuleDeterminer){
			//Check the location to be sure the determiner applies to this item
			if ($loanRuleDeterminer->matchesLocation($locationCode) ){
				//$logger->log("{$loanRuleDeterminer->rowNumber}) Location correct $locationCode, {$loanRuleDeterminer->location} ({$loanRuleDeterminer->trimmedLocation()})", PEAR_LOG_DEBUG);
				//Check that the iType is correct
				if ($loanRuleDeterminer->itemType == '999' || in_array($iType, $loanRuleDeterminer->iTypeArray())){
					//$logger->log("{$loanRuleDeterminer->rowNumber}) iType correct $iType, {$loanRuleDeterminer->itemType}", PEAR_LOG_DEBUG);
					if ($loanRuleDeterminer->patronType == '999' || in_array($pType, $loanRuleDeterminer->pTypeArray())){
						//$logger->log("{$loanRuleDeterminer->rowNumber}) pType correct $pType, {$loanRuleDeterminer->patronType}", PEAR_LOG_DEBUG);
						$loanRule = $this->loanRules[$loanRuleDeterminer->loanRuleId];
						//$logger->log("Determiner {$loanRuleDeterminer->rowNumber} indicates Loan Rule {$loanRule->loanRuleId} applies, holdable {$loanRule->holdable}", PEAR_LOG_DEBUG);
						$holdable = ($loanRule->holdable == 1);
						break;
					}else{
						//$logger->log("PType incorrect", PEAR_LOG_DEBUG);
					}
				}else{
					//$logger->log("IType incorrect", PEAR_LOG_DEBUG);
				}
			}else{
				//$logger->log("Location incorrect {$loanRuleDeterminer->location} != {$location}", PEAR_LOG_DEBUG);
			}
		}
		return $holdable;
	}

	function getCheckInGrid($id, $checkInGridId){
		//Issue summaries are loaded from the main record page.
		global $configArray;

		// Strip ID
		$id_ = substr(str_replace('.b', '', $id), 0, -1);

		// Load Record Page
		if (substr($configArray['Catalog']['url'], -1) == '/') {
			$host = substr($configArray['Catalog']['url'], 0, -1);
		} else {
			$host = $configArray['Catalog']['url'];
		}

		$branchScope = $this->getMillenniumScope();
		$req =  $host . "/search~S{$branchScope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/$checkInGridId&FF=1,0,";
		$result = file_get_contents($req);

		//Extract the actual table
		$checkInData = array();
		if (preg_match('/<table  class="checkinCardTable">(.*?)<\/table>/s', $result, $matches)) {
			$checkInTable = trim($matches[1]);

			//Extract each item from the grid.
			preg_match_all('/.*?<td valign="top" class="(.*?)">(.*?)<\/td>/s', $checkInTable, $checkInCellMatch, PREG_SET_ORDER);
			for ($matchi = 0; $matchi < count($checkInCellMatch); $matchi++) {
				$checkInCell = array();
				$checkInCell['class'] = $checkInCellMatch[$matchi][1];
				$cellData = trim($checkInCellMatch[$matchi][2]);
				//Load issue date, status, date received, issue number, copies received
				if (preg_match('/(.*?)<br\\s*\/?>.*?<span class="(?:.*?)">(.*?)<\/span>.*?on (\\d{1,2}-\\d{1,2}-\\d{1,2})<br\\s*\/?>(.*?)(?:<!-- copies --> \\((\\d+) copy\\))?<br\\s*\/?>/s', $cellData, $matches)) {
					$checkInCell['issueDate'] = trim($matches[1]);
					$checkInCell['status'] = trim($matches[2]);
					$checkInCell['statusDate'] = trim($matches[3]);
					$checkInCell['issueNumber'] = trim($matches[4]);
					if (isset($matches[5])){
						$checkInCell['copies'] = trim($matches[5]);
					}
				}
				$checkInData[] = $checkInCell;
			}
		}
		return $checkInData;
	}

	function _getItemDetails($id, $holdings){
		global $logger;
		global $configArray;
		$scope = $this->getDefaultScope();

		$shortId = substr(str_replace('.b', 'b', $id), 0, -1);

		//Login to the site using vufind login.
		$cookie = tempnam ("/tmp", "CURLCOOKIE");
		$curl_url = $configArray['Catalog']['url'] . "/patroninfo";
		$logger->log('Loading page ' . $curl_url, PEAR_LOG_INFO);
		//echo "$curl_url";
		$curl_connection = curl_init($curl_url);
		curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
		curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl_connection, CURLOPT_UNRESTRICTED_AUTH, true);
		$post_data['name'] = $configArray['Catalog']['ils_admin_user'];
		$post_data['code'] = $configArray['Catalog']['ils_admin_pwd'];
		$post_items = array();
		foreach ($post_data as $key => $value) {
			$post_items[] = $key . '=' . urlencode($value);
		}
		$post_string = implode ('&', $post_items);
		curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);
		curl_exec($curl_connection);

		foreach ($holdings as $itemNumber => $holding){
			//Get the staff page for the record
			//$curl_url = "https://www.millennium.marmot.org/search~S93?/Ypig&searchscope=93&SORT=D/Ypig&searchscope=93&SORT=D&SUBKEY=pig/1,383,383,B/staffi1~$shortId&FF=Ypig&2,2,";
			$curl_url = $configArray['Catalog']['url'] . "/search~S{$scope}?/Ypig&searchscope={$scope}&SORT=D/Ypig&searchscope={$scope}&SORT=D&SUBKEY=pig/1,383,383,B/staffi$itemNumber~$shortId&FF=Ypig&2,2,";
			$logger->log('Loading page ' . $curl_url, PEAR_LOG_INFO);
			//echo "$curl_url";
			curl_setopt($curl_connection, CURLOPT_URL, $curl_url);
			curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
			curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($curl_connection, CURLOPT_UNRESTRICTED_AUTH, true);
			curl_setopt($curl_connection, CURLOPT_COOKIEJAR, $cookie );
			curl_setopt($curl_connection, CURLOPT_COOKIESESSION, false);
			$sResult = curl_exec($curl_connection);

			//Extract Item information
			if (preg_match('/<!-- Fixfields -->.*?<table.*?>(.*?)<\/table>.*?<!-- Varfields -->.*?<table.*?>(.*?)<\/table>.*?<!-- Lnkfields -->.*?<table.*?>(.*?)<\/table>/s', $sResult, $matches)) {
				$fixFieldString = $matches[1];
				$varFieldString = $matches[2];
			}

			//Extract the fixFields into an array of name value pairs
			$fixFields = array();
			if (isset($fixFieldString)){
				preg_match_all('/<td><font size="-1"><em>(.*?)<\/em><\/font>&nbsp;<strong>(.*?)<\/strong><\/td>/s', $fixFieldString, $fieldData, PREG_PATTERN_ORDER);
				for ($i = 0; $i < count($fieldData[0]); $i++) {
					$fixFields[$fieldData[1][$i]] = $fieldData[2][$i];
				}
			}

			//Extract the fixFields into an array of name value pairs
			$varFields = array();
			if (isset($varFieldString)){
				preg_match_all('/<td.*?><font size="-1"><em>(.*?)<\/em><\/font><\/td><td width="80%">(.*?)<\/td>/s', $varFieldString, $fieldData, PREG_PATTERN_ORDER);
				for ($i = 0; $i < count($fieldData[0]); $i++) {
					$varFields[$fieldData[1][$i]] = $fieldData[2][$i];
				}
			}

			//Add on the item information
			$holdings[$itemNumber] = array_merge($fixFields, $varFields, $holding);
		}
		curl_close($curl_connection);
	}

	function selfRegister(){
		global $logger;
		global $configArray;

		$firstName = $_REQUEST['firstName'];
		$lastName = $_REQUEST['lastName'];
		$address = $_REQUEST['address'];
		$city = $_REQUEST['city'];
		$state = $_REQUEST['state'];
		$zip = $_REQUEST['zip'];
		$email = $_REQUEST['email'];

		$cookie = tempnam ("/tmp", "CURLCOOKIE");
		$curl_url = $configArray['Catalog']['url'] . "/selfreg~S" . $this->getMillenniumScope();
		$logger->log('Loading page ' . $curl_url, PEAR_LOG_INFO);
		//echo "$curl_url";
		$curl_connection = curl_init($curl_url);
		curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
		curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl_connection, CURLOPT_UNRESTRICTED_AUTH, true);

		$post_data['nfirst'] = $firstName;
		$post_data['nlast'] = $lastName;
		$post_data['stre_aaddress'] = $address;
		$post_data['city_aaddress'] = $city;
		$post_data['stat_aaddress'] = $state;
		$post_data['post_aaddress'] = $zip;
		$post_data['zemailaddr'] = $email;
		$post_items = array();
		foreach ($post_data as $key => $value) {
			$post_items[] = $key . '=' . urlencode($value);
		}
		$post_string = implode ('&', $post_items);
		curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);
		$sresult = curl_exec($curl_connection);

		curl_close($curl_connection);
		unlink($cookie);

		//Parse the library card number from the response
		if (preg_match('/Your barcode is:.*?(\\d+)<\/(b|strong)>/s', $sresult, $matches)) {
			$barcode = $matches[0];
			return array('success' => true, 'barcode' => $barcode);
		} else {
			return array('success' => false, 'barcode' => '');
		}

	}

	public function _getLoginFormValues(){
		$loginData = array();
		global $user;
		$loginData['name'] = $user->cat_username;
		$loginData['code'] = $user->cat_password;

		return $loginData;
	}

	function doInventory($login, $password1, $initials, $password2, $barcodes, $updateIncorrectStatuses){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumInventory.php';
		$millenniumInventory = new MillenniumInventory($this);
		return $millenniumInventory->doInventory($login, $password1, $initials, $password2, $barcodes, $updateIncorrectStatuses);
	}
}