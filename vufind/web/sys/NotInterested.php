<?php
/**
 * Store records tha a user is not interested in seeing
 *
 * @category VuFind-Plus 
 * @author Mark Noble <mark@marmot.org>
 * Date: 5/1/13
 * Time: 9:51 AM
 */

class NotInterested extends DB_DataObject{
	public $id;
	public $userId;
	public $resourceId;
	public $dateMarked;

	public $__table = 'user_not_interested';

	//Additional properties added with joins
	public $source;
	public $record_id;
}