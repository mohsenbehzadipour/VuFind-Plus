<?php
require_once ROOT_DIR . '/Drivers/marmot_inc/UserRating.php';
require_once ROOT_DIR . '/services/MyResearch/lib/Resource.php';
require_once ROOT_DIR . '/sys/Novelist.php';
require_once ROOT_DIR . '/sys/eContent/EContentRating.php';

class Suggestions{
	/*
	 * Get suggestions for titles that a user might like based on their rating history
	 * and related titles from Novelist.
	 */
	static function getSuggestions($userId = -1){
		global $configArray;
		if ($userId == -1){
			global $user;
			$userId = $user->id;
		}

		//Load all titles the user is not interested in
		$notInterestedTitles = array();
		$notInterested = new NotInterested();
		$resource = new Resource();
		$notInterested->joinAdd($resource);
		$notInterested->userId = $userId;
		$notInterested->find();
		while ($notInterested->fetch()){
			if ($notInterested->source == 'VuFind'){
				$fullId = $notInterested->record_id;
			}else{
				$fullId = 'econtentRecord' . $notInterested->record_id;
			}
			$notInterestedTitles[$fullId] = $fullId;
		}

		//Load all titles the user has rated (print)
		$allRatedTitles = array();
		$allLikedRatedTitles = array();
		$ratings = new UserRating();
		$ratings->userid = $userId;
		$resource = new Resource();
		$notInterested->joinAdd($resource);
		$ratings->joinAdd($resource);
		$ratings->find();
		while ($ratings->fetch()){
			$allRatedTitles[$ratings->record_id] = $ratings->record_id;
			if ($ratings->rating >= 4){
				$allLikedRatedTitles[] = $ratings->record_id;
			}
		}

		//Load all titles the user has rated (eContent)
		$econtentRatings = new EContentRating();
		$econtentRatings->userId = $userId;
		$econtentRatings->find();
		while ($econtentRatings->fetch()){
			$allRatedTitles['econtentRecord' . $econtentRatings->recordId] = 'econtentRecord' . $econtentRatings->recordId;
			if ($econtentRatings->rating >= 4){
				$allLikedRatedTitles[] = 'econtentRecord' . $econtentRatings->recordId;
			}
		}

		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url = $configArray['Index']['url'];
		$db = new $class($url);
		if ($configArray['System']['debugSolr']) {
			$db->debug = true;
		}

		//Get a list of all titles the user has rated (3 star and above)
		$ratings = new UserRating();
		$ratings->whereAdd("userId = $userId", 'AND');
		$ratings->whereAdd('rating >= 3', 'AND');
		$ratings->orderBy('rating DESC, dateRated DESC, id DESC');
		//Use the 20 highest ratings to make real-time recommendations faster
		$ratings->limit(0, 5);

		$ratings->find();
		$suggestions = array();
		//echo("User has rated {$ratings->N} titles<br/>");
		if ($ratings->N > 0){
			while($ratings->fetch()){
				$resourceId = $ratings->resourceid;
				//Load the resource
				$resource = new Resource();
				$resource->id = $resourceId;
				$resource->find();
				if ($resource->N != 1){
					//echo("Did not find resource for $resourceId<br/>");
				}else{
					$resource->fetch();
					//echo("Found resource for $resourceId - {$resource->title}<br/>");
					$ratedTitles[$resource->record_id] = clone $ratings;
					$numRecommendations = 0;
					if ($resource->isbn){
						//If there is an isbn for the title, we can load similar titles based on Novelist.
						$isbn = $resource->isbn;
						$numRecommendations = Suggestions::getNovelistRecommendations($ratings, $isbn, $resource, $allRatedTitles, $suggestions, $notInterestedTitles);
						//echo("&nbsp;- Found $numRecommendations for $isbn from Novelist<br/>");

					}
					if ($numRecommendations == 0){
						Suggestions::getSimilarlyRatedTitles($db, $ratings, $userId, $allRatedTitles, $suggestions, $notInterestedTitles);
						//echo("&nbsp;- Found $numRecommendations based on ratings from other users<br/>");
					}
				}
			}
		}

		//Also get eContent the user has rated highly
		$econtentRatings = new EContentRating();
		$econtentRatings->userId = $userId;
		$econtentRatings->whereAdd('rating >= 3');
		$econtentRatings->orderBy('rating DESC, dateRated DESC');
		$econtentRatings->limit(0, 5);
		$econtentRatings->find();
		//echo("User has rated {$econtentRatings->N} econtent titles<br/>");
		if ($econtentRatings->N > 0){
			while($econtentRatings->fetch()){
				//echo("Processing eContent Rating {$econtentRatings->recordId}<br/>");
				//Load the resource
				$resource = new Resource();
				$resource->record_id = $econtentRatings->recordId;
				$resource->source = 'eContent';
				$resource->find();
				if ($resource->N != 1){
					//echo("Did not find resource for $resourceId<br/>");
				}else{
					$resource->fetch();
					//echo("Found resource for $resourceId - {$resource->title}<br/>");
					$ratedTitles[$resource->record_id] = clone $econtentRatings;
					$numRecommendations = 0;
					if ($resource->isbn){
						//If there is an isbn for the title, we can load similar titles based on Novelist.
						$isbn = $resource->isbn;
						$numRecommendations = Suggestions::getNovelistRecommendations($ratings, $isbn, $resource, $allRatedTitles, $suggestions, $notInterestedTitles);
						//echo("&nbsp;- Found $numRecommendations for $isbn from Novelist<br/>");
					}
					if ($numRecommendations == 0){
						Suggestions::getSimilarlyRatedTitles($db, $ratings, $userId, $allRatedTitles, $suggestions, $notInterestedTitles);
						//echo("&nbsp;- Found $numRecommendations based on ratings from other users<br/>");
					}
				}
			}
		}

		$groupedTitles = array();
		foreach ($suggestions as $suggestion){
			$groupingTerm = $suggestion['titleInfo']['grouping_term'];
			$groupedTitles[] = $groupingTerm;
		}

		//If the user has not rated anything, return nothing.
		if (count($allLikedRatedTitles) == 0){
			return array();
		}
		//Get recommendations based on everything I've rated using more like this functionality
		$class = $configArray['Index']['engine'];
		$url = $configArray['Index']['url'];
		/** @var Solr $db */
		$db = new $class($url);
		//$db->debug = true;
		$moreLikeTheseSuggestions = $db->getMoreLikeThese($allLikedRatedTitles);
		//print_r($moreLikeTheseSuggestions);
		if (count($suggestions) < 30){
			foreach ($moreLikeTheseSuggestions['response']['docs'] as $suggestion){
				$groupingTerm = $suggestion['grouping_term'];
				if (array_key_exists($groupingTerm, $groupedTitles)){
					//echo ($suggestion['grouping_term'] . " is already in the suggestions");
					continue;
				}
				$groupedTitles[$groupingTerm] = $groupingTerm;
				//print_r($suggestion);
				if (!array_key_exists($suggestion['id'], $allRatedTitles) && !array_key_exists($suggestion['id'], $notInterestedTitles)){
					$suggestions[$suggestion['id']] = array(
						'rating' => $suggestion['rating'] - 2.5,
						'titleInfo' => $suggestion,
						'basedOn' => 'MetaData for all titles rated',
					);
				}
				if (count($suggestions) == 30){
					break;
				}
			}
		}
		//print_r($groupedTitles);

		//sort suggestions based on score from ascending to descending
		uasort($suggestions, 'Suggestions::compareSuggestions');
		//Only return up to 50 suggestions to make the page size reasonable
		$suggestions = array_slice($suggestions, 0, 30, true);
		//Return suggestions for use in the user interface.
		return $suggestions;
	}


	/**
	 * Load titles that have been rated by other users which are similar to this.
	 *
	 * @param SearchObject_Solr|SearchObject_Base $db
	 * @param UserRating $ratedTitle
	 * @param integer $userId
	 * @param array $ratedTitles
	 * @param array $suggestions
	 * @param integer[] $notInterestedTitles
	 * @return int The number of suggestions for this title
	 */
	static function getSimilarlyRatedTitles($db, $ratedTitle, $userId, $ratedTitles, &$suggestions, $notInterestedTitles){
		$numRecommendations = 0;
		//If there is no ISBN, can we come up with an alternative algorithm?
		//Possibly using common ratings with other patrons?
		//Get a list of other patrons that have rated this title and that like it as much or more than the active user..
		$otherRaters = new UserRating();
		//Query the database to get items that other users who rated this liked.
		$sqlStatement = ("SELECT resourceid, record_id, " .
                    " sum(case rating when 5 then 10 when 4 then 6 end) as rating " . //Scale the ratings similar to the above.
                    " FROM `user_rating` inner join resource on resource.id = user_rating.resourceid WHERE userId in " .
                    " (select userId from user_rating where resourceId = " . $ratedTitle->resourceid . //Get other users that have rated this title.
                    " and rating >= 4 " . //Make sure that other users liked the book.
                    " and userid != " . $userId . ") " . //Make sure that we don't include this user in the results.
                    " and rating >= 4 " . //Only include ratings that are 4 or 5 star so we don't get books the other user didn't like.
                    " and resourceId != " . $ratedTitle->resourceid . //Make sure we don't get back this title as a recommendation.
                    " and deleted = 0 " . //Ignore deleted resources
                    " group by resourceid order by rating desc limit 10"); //Sort so the highest titles are on top and limit to 10 suggestions.
		$otherRaters->query($sqlStatement);
		if ($otherRaters->N > 0){
			//Other users have also rated this title.
			while ($otherRaters->fetch()){
				//Process the title
				disableErrorHandler();

				if (!($ownedRecord = $db->getRecord($otherRaters->record_id))) {
					//Old record which has been removed? Ignore for purposes of suggestions.
					continue;
				}
				enableErrorHandler();
				//get the title from the Solr Index
				if (isset($ownedRecord['isbn'])){
					if (strpos($ownedRecord['isbn'][0], ' ') > 0){
						$isbnInfo = explode(' ', $ownedRecord['isbn'][0]);
						$isbn = $isbnInfo[0];
					}else{
						$isbn = $ownedRecord['isbn'][0];
					}
					$isbn13 = strlen($isbn) == 13 ? $isbn : ISBNConverter::convertISBN10to13($isbn);
					$isbn10 = strlen($isbn) == 10 ? $isbn : ISBNConverter::convertISBN13to10($isbn);
				}else{
					$isbn13 = '';
					$isbn10 = '';
				}
				//See if we can get the series title from the record
				if (isset($ownedRecord['series'])){
					$series = $ownedRecord['series'][0];
				}else{
					$series = '';
				}
				$similarTitle = array(
						'title' => $ownedRecord['title'],
						'title_short' => $ownedRecord['title_short'],
						'author' => isset($ownedRecord['author']) ? $ownedRecord['author'] : '',
						'publicationDate' => $ownedRecord['publishDate'],
						'isbn' => $isbn13,
						'isbn10' => $isbn10,
						'upc' => isset($ownedRecord['upc']) ? $ownedRecord['upc'][0] : '',
						'recordId' => $ownedRecord['id'],
						'id' => $ownedRecord['id'], //This allows the record to be displayed in various locations.
						'libraryOwned' => true,
						'isCurrent' => false,
						'shortId' => substr($ownedRecord['id'], 1),
						'format_category' => isset($ownedRecord['format_category']) ? $ownedRecord['format_category'] : '',
						'format' => $ownedRecord['format'],
						'recordtype' => $ownedRecord['recordtype'],
						'series' => $series,
						'grouping_term' => $ownedRecord['grouping_term'],
				);
				$numRecommendations++;
				Suggestions::addTitleToSuggestions($ratedTitle, $similarTitle['title'], $similarTitle['recordId'], $similarTitle, $ratedTitles, $suggestions, $notInterestedTitles);
			}
		}
		return $numRecommendations;
	}

	static function getNovelistRecommendations($userRating, $isbn, $resource, $allRatedTitles, &$suggestions, $notInterestedTitles){
		//We now have the title, we can get the related titles from Novelist
		$novelist = NovelistFactory::getNovelist();;
		//Use loadEnrichmentInfo even though there is more data than we need since it uses caching.
		$enrichmentInfo = $novelist->loadEnrichment($isbn);
		$numRecommendations = 0;

		if (isset($enrichmentInfo['similarTitleCountOwned']) && $enrichmentInfo['similarTitleCountOwned'] > 0){
			//For each related title
			foreach ($enrichmentInfo['similarTitles'] as $similarTitle){
				if ($similarTitle['libraryOwned']){
					Suggestions::addTitleToSuggestions($userRating, $resource->title, $resource->record_id, $similarTitle, $allRatedTitles, $suggestions, $notInterestedTitles);
					$numRecommendations++;
				}
			}
		}
		return $numRecommendations;
	}

	static function addTitleToSuggestions($userRating, $sourceTitle, $sourceId, $similarTitle, $allRatedTitles, &$suggestions, $notInterestedTitles){
		//Don't suggest titles that have already been rated
		if (array_key_exists($similarTitle['id'], $allRatedTitles)){
			return;
		}
		//Don't suggest titles the user is not interested in.
		if (array_key_exists($similarTitle['id'], $notInterestedTitles)){
			return;
		}

		$rating = 0;
		$suggestedBasedOn = array();
		//Get the existing rating if any
		if (array_key_exists($similarTitle['id'], $suggestions)){
			$rating = $suggestions[$similarTitle['id']]['rating'];
			$suggestedBasedOn = $suggestions[$similarTitle['id']]['basedOn'];
		}
		//Update the suggestion score.
		//Using the scale:
		//  10 pts - 5 star rating
		//  6 pts -  4 star rating
		//  2 pts -  3 star rating
		if ($userRating->rating == 5){
			$rating += 10;
		}elseif ($userRating->rating == 4){
			$rating += 6;
		}else{
			$rating += 2;
		}
		if (count($suggestedBasedOn) < 3){
			$suggestedBasedOn[] = array('title'=>$sourceTitle,'id'=>$sourceId);
		}
		$suggestions[$similarTitle['id']] = array(
            'rating'=>$rating,
            'titleInfo'=>$similarTitle,
            'basedOn'=>$suggestedBasedOn,
		);
	}

	static function compareSuggestions($a, $b){
		if ($a['rating'] == $b['rating']){
			return 0;
		}
		return ($a['rating'] <= $b['rating']) ? 1 : -1;
	}
}