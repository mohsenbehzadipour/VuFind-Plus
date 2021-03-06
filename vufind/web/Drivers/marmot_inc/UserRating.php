<?php
/**
 * Table Definition for User Ratings
 */
require_once 'DB/DataObject.php';
require_once 'DB/DataObject/Cast.php';

class UserRating extends DB_DataObject 
{
  public $__table = 'user_rating';    // table name
  public $id;                       //int(11)
  public $userid;                   //int(11)
  public $resourceid;               //int(11)
  public $rating;                   //int(5)
	public $dateAdded;

	//Variables created with joins
	public $record_id;


  /* Static get */
  function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('UserRating',$k,$v); }

  function keys() {
      return array('id', 'userid', 'resourceid');
  }
}