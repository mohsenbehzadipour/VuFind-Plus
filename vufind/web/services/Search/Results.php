<?php
/**
 *
 * Copyright (C) Andrew Nagy 2009
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
require_once ROOT_DIR . '/services/MyResearch/lib/User.php';
require_once ROOT_DIR . '/services/MyResearch/lib/Search.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/Prospector.php';

require_once ROOT_DIR . '/sys/SolrStats.php';
require_once ROOT_DIR . '/sys/Pager.php';

class Search_Results extends Action {

	function launch() {
		global $interface;
		global $configArray;
		global $timer;
		global $analytics;

		/** @var string|LibrarySearchSource|LocationSearchSource $searchSource */
		$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';
		if (preg_match('/library\d+/', $searchSource)){
			$trimmedId = str_replace('library', '', $searchSource);
			$searchSourceObj = new LibrarySearchSource();
			$searchSourceObj->id = $trimmedId;
			if ($searchSourceObj->find(true)){
				$searchSource = $searchSourceObj;
			}
		}

		if (isset($_REQUEST['replacementTerm'])){
			$replacementTerm = $_REQUEST['replacementTerm'];
			$interface->assign('replacementTerm', $replacementTerm);
			$oldTerm = $_REQUEST['lookfor'];
			$interface->assign('oldTerm', $oldTerm);
			$_REQUEST['lookfor'] = $replacementTerm;
			$_GET['lookfor'] = $replacementTerm;
			$oldSearchUrl = $_SERVER['REQUEST_URI'];
			$oldSearchUrl = str_replace('replacementTerm=' . urlencode($replacementTerm), 'disallowReplacements', $oldSearchUrl);
			$interface->assign('oldSearchUrl', $oldSearchUrl);
		}

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Solr.php';
		$timer->logTime('Include search engine');

		//Check to see if the year has been set and if so, convert to a filter and resend.
		$dateFilters = array('publishDate');
		foreach ($dateFilters as $dateFilter){
			if (isset($_REQUEST[$dateFilter . 'yearfrom']) || isset($_REQUEST[$dateFilter . 'yearto'])){
				$queryParams = $_GET;
				$yearFrom = preg_match('/^\d{2,4}$/', $_REQUEST[$dateFilter . 'yearfrom']) ? $_REQUEST[$dateFilter . 'yearfrom'] : '*';
				$yearTo = preg_match('/^\d{2,4}$/', $_REQUEST[$dateFilter . 'yearto']) ? $_REQUEST[$dateFilter . 'yearto'] : '*';
				if (strlen($yearFrom) == 2){
					$yearFrom = '19' . $yearFrom;
				}else if (strlen($yearFrom) == 3){
					$yearFrom = '0' . $yearFrom;
				}
				if (strlen($yearTo) == 2){
					$yearTo = '19' . $yearTo;
				}else if (strlen($yearFrom) == 3){
					$yearTo = '0' . $yearTo;
				}
				if ($yearTo != '*' && $yearFrom != '*' && $yearTo < $yearFrom){
					$tmpYear = $yearTo;
					$yearTo = $yearFrom;
					$yearFrom = $tmpYear;
				}
				unset($queryParams['module']);
				unset($queryParams['action']);
				unset($queryParams[$dateFilter . 'yearfrom']);
				unset($queryParams[$dateFilter . 'yearto']);
				if (!isset($queryParams['sort'])){
					$queryParams['sort'] = 'year';
				}
				$queryParamStrings = array();
				foreach($queryParams as $paramName => $queryValue){
					if (is_array($queryValue)){
						foreach ($queryValue as $arrayValue){
							if (strlen($arrayValue) > 0){
								$queryParamStrings[] = $paramName . '[]=' . $arrayValue;
							}
						}
					}else{
						if (strlen($queryValue)){
							$queryParamStrings[] = $paramName . '=' . $queryValue;
						}
					}
				}
				if ($yearFrom != '*' || $yearTo != '*'){
					$queryParamStrings[] = "&filter[]=$dateFilter:[$yearFrom+TO+$yearTo]";
				}
				$queryParamString = join('&', $queryParamStrings);
				header("Location: {$configArray['Site']['path']}/Search/Results?$queryParamString");
				exit;
			}
		}

		$rangeFilters = array('lexile_score', 'accelerated_reader_reading_level', 'accelerated_reader_point_value');
		foreach ($rangeFilters as $filter){
			if ((isset($_REQUEST[$filter . 'from']) && strlen($_REQUEST[$filter . 'from']) > 0) || (isset($_REQUEST[$filter . 'to']) && strlen($_REQUEST[$filter . 'to']) > 0)){
				$queryParams = $_GET;
				$from = (isset($_REQUEST[$filter . 'from']) && preg_match('/^\d*(\.\d*)?$/', $_REQUEST[$filter . 'from'])) ? $_REQUEST[$filter . 'from'] : '*';
				$to = (isset($_REQUEST[$filter . 'to']) && preg_match('/^\d*(\.\d*)?$/', $_REQUEST[$filter . 'to'])) ? $_REQUEST[$filter . 'to'] : '*';

				if ($to != '*' && $from != '*' && $to < $from){
					$tmpFilter = $to;
					$to = $from;
					$from = $tmpFilter;
				}
				unset($queryParams['module']);
				unset($queryParams['action']);
				unset($queryParams[$filter . 'from']);
				unset($queryParams[$filter . 'to']);
				$queryParamStrings = array();
				foreach($queryParams as $paramName => $queryValue){
					if (is_array($queryValue)){
						foreach ($queryValue as $arrayValue){
							if (strlen($arrayValue) > 0){
								$queryParamStrings[] = $paramName . '[]=' . $arrayValue;
							}
						}
					}else{
						if (strlen($queryValue)){
							$queryParamStrings[] = $paramName . '=' . $queryValue;
						}
					}
				}
				if ($from != '*' || $to != '*'){
					$queryParamStrings[] = "&filter[]=$filter:[$from+TO+$to]";
				}
				$queryParamString = join('&', $queryParamStrings);
				header("Location: {$configArray['Site']['path']}/Search/Results?$queryParamString");
				exit;
			}
		}

		// Initialise from the current search globals
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init($searchSource);
		$timer->logTime("Init Search Object");

		// Build RSS Feed for Results (if requested)
		if ($searchObject->getView() == 'rss') {
			// Throw the XML to screen
			echo $searchObject->buildRSS();
			// And we're done
			exit();
		}else if ($searchObject->getView() == 'excel'){
			// Throw the Excel spreadsheet to screen for download
			echo $searchObject->buildExcel();
			// And we're done
			exit();
		}

		// TODO : Investigate this... do we still need
		// If user wants to print record show directly print-dialog box
		if (isset($_GET['print'])) {
			$interface->assign('print', true);
		}

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList',   $searchObject->getSortList());
		$interface->assign('rssLink',    $searchObject->getRSSUrl());
		$interface->assign('excelLink',  $searchObject->getExcelUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result->getMessage());
		}
		$timer->logTime('Process Search');

		// Some more variables
		//   Those we can construct AFTER the search is executed, but we need
		//   no matter whether there were any results
		$interface->assign('qtime',               round($searchObject->getQuerySpeed(), 2));
		$interface->assign('spellingSuggestions', $searchObject->getSpellingSuggestions());
		$interface->assign('lookfor',             $searchObject->displayQuery());
		$interface->assign('searchType',          $searchObject->getSearchType());
		// Will assign null for an advanced search
		$interface->assign('searchIndex',         $searchObject->getSearchIndex());

		// We'll need recommendations no matter how many results we found:
		$interface->assign('topRecommendations',
		$searchObject->getRecommendationsTemplates('top'));
		$interface->assign('sideRecommendations',
		$searchObject->getRecommendationsTemplates('side'));

		// 'Finish' the search... complete timers and log search history.
		$searchObject->close();
		$interface->assign('time', round($searchObject->getTotalSpeed(), 2));
		// Show the save/unsave code on screen
		// The ID won't exist until after the search has been put in the search history
		//    so this needs to occur after the close() on the searchObject
		$interface->assign('showSaved',   true);
		$interface->assign('savedSearch', $searchObject->isSavedSearch());
		$interface->assign('searchId',    $searchObject->getSearchId());
		$currentPage = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$interface->assign('page', $currentPage);

		//Enable and disable functionality based on library settings
		//This must be done before we process each result
		global $library;
		/** @var Location $locationSingleton */
		global $locationSingleton;
		$location = $locationSingleton->getActiveLocation();
		$showHoldButton = 1;
		$showHoldButtonInSearchResults = 1;
		$interface->assign('showNotInterested', false);
		if (isset($library) && $location != null){
			$interface->assign('showFavorites', $library->showFavorites);
			$interface->assign('showComments', $library->showComments);
			$showHoldButton = (($location->showHoldButton == 1) && ($library->showHoldButton == 1)) ? 1 : 0;
			$showHoldButtonInSearchResults = (($location->showHoldButton == 1) && ($library->showHoldButtonInSearchResults == 1)) ? 1 : 0;
		}else if ($location != null){
			$interface->assign('showFavorites', 1);
			$showHoldButton = $location->showHoldButton;
		}else if (isset($library)){
			$interface->assign('showFavorites', $library->showFavorites);
			$showHoldButton = $library->showHoldButton;
			$showHoldButtonInSearchResults = $library->showHoldButtonInSearchResults;
			$interface->assign('showComments', $library->showComments);
		}else{
			$interface->assign('showFavorites', 1);
			$interface->assign('showComments', 1);
		}
		if ($showHoldButton == 0){
			$showHoldButtonInSearchResults = 0;
		}
		$interface->assign('showHoldButton', $showHoldButtonInSearchResults);
		$interface->assign('page_body_style', 'sidebar_left');
		$interface->assign('overDriveVersion', isset($configArray['OverDrive']['interfaceVersion']) ? $configArray['OverDrive']['interfaceVersion'] : 1);

		//Check to see if we should show unscoped results
		$enableUnscopedSearch = false;
		$searchLibrary = Library::getSearchLibrary();
		if ($searchLibrary != null && $searchLibrary->showMarmotResultsAtEndOfSearch){
			if (is_object($searchSource)){
				$enableUnscopedSearch = $searchSource->catalogScoping != 'unscoped';
				$unscopedSearch = clone($searchObject);
			}else{
				$searchSources = new SearchSources();
				$searchOptions = $searchSources->getSearchSources();
				if (isset($searchOptions['marmot'])){
					$unscopedSearch = clone($searchObject);
					$enableUnscopedSearch = true;
				}
			}
		}

		$enableProspectorIntegration = isset($configArray['Content']['Prospector']) ? $configArray['Content']['Prospector'] : false;
		$showRatings = 1;
		$showProspectorResultsAtEndOfSearch = true;
		if (isset($library)){
			$enableProspectorIntegration = ($library->enablePospectorIntegration == 1);
			$showRatings = $library->showRatings;
			$showProspectorResultsAtEndOfSearch = ($library->showProspectorResultsAtEndOfSearch == 1);
		}
		$interface->assign('showRatings', $showRatings);

		$numProspectorTitlesToLoad = 0;
		$numUnscopedTitlesToLoad = 0;

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();

		// Save the URL of this search to the session so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();

		if (is_object($searchSource)){
			$translatedSearch = $searchSource->label;
		}else{
			$allSearchSources = SearchSources::getSearchSources();
			if (!isset($allSearchSources[$searchSource]) && $searchSource == 'marmot'){
				$searchSource = 'local';
			}
			$translatedSearch = $allSearchSources[$searchSource]['name'];
		}
		$analytics->addSearch($translatedSearch, $searchObject->displayQuery(), $searchObject->isAdvanced(), $searchObject->getFullSearchType(), $searchObject->hasAppliedFacets(), $searchObject->getResultTotal());
		if ($searchObject->getResultTotal() < 1) {
			//We didn't find anything.  Look for search Suggestions
			//Don't try to find suggestions if facets were applied
			$autoSwitchSearch = false;
			$disallowReplacements = isset($_REQUEST['disallowReplacements']) || isset($_REQUEST['replacementTerm']);
			if (!$disallowReplacements && (!isset($facetSet) || count($facetSet) == 0)){
				require_once ROOT_DIR . '/services/Search/lib/SearchSuggestions.php';
				$searchSuggestions = new SearchSuggestions();
				$commonSearches = $searchSuggestions->getCommonSearchesMySql($searchObject->displayQuery(), $searchObject->getSearchIndex());
				//If the first search in the list is used 10 times more than the next, just show results for that
				$numSuggestions = count($commonSearches);
				if ($numSuggestions == 1){
					$autoSwitchSearch = true;
				}elseif ($numSuggestions >= 2){
					$firstTimesSearched = $commonSearches[0]['numSearches'];
					$secondTimesSearched = $commonSearches[1]['numSearches'];
					if ($firstTimesSearched / $secondTimesSearched > 10){
						$autoSwitchSearch = true;
					}
				}

				$interface->assign('autoSwitchSearch', $autoSwitchSearch);
				if ($autoSwitchSearch){
					//Get search results for the new search
					$interface->assign('oldTerm', $searchObject->displayQuery());
					$interface->assign('newTerm', $commonSearches[0]['phrase']);
					$thisUrl = $_SERVER['REQUEST_URI'];
					$thisUrl = $thisUrl . "&replacementTerm=" . urlencode($commonSearches[0]['phrase']);
					header("Location: " . $thisUrl);
					exit();
				}

				$interface->assign('searchSuggestions', $commonSearches);
			}

			//Var for the IDCLREADER TEMPLATE
			$interface->assign('ButtonBack',true);
			$interface->assign('ButtonHome',true);
			$interface->assign('MobileTitle','No Results Found');

			// No record found
			$interface->setTemplate('list-none.tpl');
			$interface->assign('recordCount', 0);

			// Was the empty result set due to an error?
			$error = $searchObject->getIndexError();
			if ($error !== false) {
				// If it's a parse error or the user specified an invalid field, we
				// should display an appropriate message:
				if (stristr($error, 'org.apache.lucene.queryParser.ParseException') ||
				preg_match('/^undefined field/', $error)) {
					$interface->assign('parseError', true);

					// Unexpected error -- let's treat this as a fatal condition.
				} else {
					PEAR_Singleton::raiseError(new PEAR_Error('Unable to process query<br />' .
                        'Solr Returned: ' . $error));
				}
			}

			$numProspectorTitlesToLoad = 10;
			$numUnscopedTitlesToLoad = 10;
			$timer->logTime('no hits processing');

		} else if ($searchObject->getResultTotal() == 1 && (strpos($searchObject->displayQuery(), 'id') === 0 || $searchObject->getSearchType() == 'id')){
			//Redirect to the home page for the record
			$recordSet = $searchObject->getResultRecordSet();
			$record = reset($recordSet);
			$_SESSION['searchId'] = $searchObject->getSearchId();
			if ($record['recordtype'] == 'list'){
				$listId = substr($record['id'], 4);
				header("Location: " . $configArray['Site']['path'] . "/MyResearch/MyList/{$listId}");
				exit();
			}elseif ($record['recordtype'] == 'econtentRecord'){
				$shortId = str_replace('econtentRecord', '', $record['id']);
				header("Location: " . $configArray['Site']['path'] . "/EcontentRecord/$shortId/Home");
				exit();
			}else{
				header("Location: " . $configArray['Site']['path'] . "/Record/{$record['id']}/Home");
				exit();
			}

		} else {
			$timer->logTime('save search');

			// Assign interface variables
			$summary = $searchObject->getResultSummary();
			$interface->assign('recordCount', $summary['resultTotal']);
			$interface->assign('recordStart', $summary['startRecord']);
			$interface->assign('recordEnd',   $summary['endRecord']);

			$facetSet = $searchObject->getFacetList();
			$interface->assign('facetSet',       $facetSet);

			//Check to see if a format category is already set
			$categorySelected = false;
			if (isset($facetSet['top'])){
				foreach ($facetSet['top'] as $cluster){
					if ($cluster['label'] == 'Category'){
						foreach ($cluster['list'] as $thisFacet){
							if ($thisFacet['isApplied']){
								$categorySelected = true;
							}
						}
					}
				}
			}
			$interface->assign('categorySelected', $categorySelected);
			$timer->logTime('load selected category');

			// Big one - our results
			$recordSet = $searchObject->getResultRecordHTML();
			$interface->assign('recordSet', $recordSet);
			$timer->logTime('load result records');

			// Setup Display
			$interface->assign('sitepath', $configArray['Site']['path']);
			$interface->assign('subpage', 'Search/list-list.tpl');
			$interface->setTemplate('list.tpl');

			//Var for the IDCLREADER TEMPLATE
			$interface->assign('ButtonBack',true);
			$interface->assign('ButtonHome',true);
			$interface->assign('MobileTitle','Search Results');

			// Process Paging
			$link = $searchObject->renderLinkPageTemplate();
			$options = array('totalItems' => $summary['resultTotal'],
                             'fileName'   => $link,
                             'perPage'    => $summary['perPage']);
			$pager = new VuFindPager($options);
			$interface->assign('pageLinks', $pager->getLinks());
			if ($pager->isLastPage()){
				$numProspectorTitlesToLoad = 5;
				$numUnscopedTitlesToLoad = 5;
			}
			$timer->logTime('finish hits processing');
		}

		if ($numProspectorTitlesToLoad > 0 && $enableProspectorIntegration && $showProspectorResultsAtEndOfSearch){
			$interface->assign('prospectorNumTitlesToLoad', $numProspectorTitlesToLoad);
			$interface->assign('prospectorSavedSearchId', $searchObject->getSearchId());
		}else{
			$interface->assign('prospectorNumTitlesToLoad', 0);
		}

		if ($enableUnscopedSearch && isset($unscopedSearch)){
			$unscopedSearch->setLimit($numUnscopedTitlesToLoad * 4);
			$unscopedSearch->disableScoping();
			$unscopedSearch->processSearch(false, false);
			$numUnscopedResults = $unscopedSearch->getResultTotal();
			$interface->assign('numUnscopedResults', $numUnscopedResults);
			$unscopedSearchUrl = $unscopedSearch->renderSearchUrl();
			if (preg_match('/searchSource=(.*?)(?:&|$)/', $unscopedSearchUrl)){
				$unscopedSearchUrl = preg_replace('/(.*searchSource=)(.*?)(&|$)(.*)/', '$1marmot$3$4', $unscopedSearchUrl);
				$unscopedSearchUrl = preg_replace('/&/', '&amp;', $unscopedSearchUrl);
			}else{
				$unscopedSearchUrl .= "&amp;searchSource=marmot";
			}
			$unscopedSearchUrl .= "&amp;shard=";
			$interface->assign('unscopedSearchUrl', $unscopedSearchUrl);
			if ($numUnscopedTitlesToLoad > 0){
				$unscopedResults = $unscopedSearch->getSupplementalResultRecordHTML($searchObject->getResultRecordSet(), $numUnscopedTitlesToLoad, $searchObject->getResultTotal());
				$interface->assign('unscopedResults', $unscopedResults);
			}
		}

		//Determine whether or not materials request functionality should be enabled
		$interface->assign('enableMaterialsRequest', MaterialsRequest::enableMaterialsRequest());

		if ($configArray['Statistics']['enabled'] && isset( $_GET['lookfor'])) {
			require_once(ROOT_DIR . '/Drivers/marmot_inc/SearchStat.php');
			$searchStat = new SearchStat();
			$searchStat->saveSearch( strip_tags($_GET['lookfor']),  strip_tags(isset($_GET['type']) ? $_GET['type'] : (isset($_GET['basicType']) ? $_GET['basicType'] : 'Keyword')), $searchObject->getResultTotal());
		}

		// Done, display the page
		$interface->display('layout.tpl');
	} // End launch()
}