<?php
/**
 * Table Definition for EContentItem
 */
require_once 'DB/DataObject.php';
require_once 'DB/DataObject/Cast.php';
require_once ROOT_DIR . '/sys/SolrDataObject.php';

class EContentItem extends DB_DataObject {
	public $__table = 'econtent_item';    // table name
	public $id;                      //int(25)
	public $link;
	public $filename;                    //varchar(255)
	public $folder;
	public $acsId;          //varchar(128)
	public $recordId;       //The id of the record to attach the item to
	public $item_type;           //pdf, epub, etc
	public $libraryId;
	public $notes; //Notes to the user for display (i.e. version with images, version without images).
	public $addedBy;
	public $date_added;
	public $reviewedBy; //Id of a cataloging use who reviewed the item for consistency
	public $reviewStatus; //0 = unreviewed, 1=approved, 2=rejected
	public $reviewNotes;
	public $size;
	public $externalFormat;
	public $externalFormatId;
	public $externalFormatNumeric;
	public $identifier;
	public $sampleName_1;
	public $sampleUrl_1;
	public $sampleName_2;
	public $sampleUrl_2;

	private $_record = null;

	/* Static get */
	function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('econtent_item',$k,$v); }

	function keys() {
		return array('id', 'filename', 'folder');
	}

	static function getObjectStructure(){
		global $configArray;

		//Load Libraries for lookup values
		$library = new Library();
		$library->orderBy('displayName');
		$library->find();
		$libraryList = array();
		$libraryList[-1] = "All Libraries";
		while ($library->fetch()){
			$libraryList[$library->libraryId] = $library->displayName;
		}

		$structure = array(
		'id' => array(
      'property'=>'id',
      'type'=>'hidden',
      'label'=>'Id',
      'primaryKey'=>true,
      'description'=>'The unique id of the e-pub file.',
		),
		'item_type' => array(
		  'property' => 'item_type',
		  'type' => 'enum',
		  'label' => 'Type',
		  'values' => EContentItem::getValidItemTypes(),
		  'description' => 'The type of file being added',
		  'required'=> true,
		  'storeDb' => true,
		  'storeSolr' => false,
		),
		'libraryId' => array(
		  'property' => 'libraryId',
		  'type' => 'enum',
		  'label' => 'For use by',
		  'values' => $libraryList,
		  'description' => 'The library system that has access to the link',
		  'required'=> true,
		  'storeDb' => true,
		  'storeSolr' => false,
		),

		'link' => array(
			'property'=>'link',
			'type'=>'text',
			'label'=>'External Link',
			'size' => 100,
			'maxLength'=>255,
			'description'=>'A link to an external website or document.',
			'required'=> false,
		  'storeDb' => true,
		  'storeSolr' => false,
		),

		'filename' => array(
			'property'=>'filename',
			'type'=>'file',
			'label'=>'Source File',
			'path'=>$configArray['EContent']['library'],
			'description'=>'The source file for display or download within VuFind.',
			'serverValidation' => 'validateEpub',
			'required'=> false,
		  'storeDb' => true,
		  'storeSolr' => false,
		),

		'folder' => array(
			'property'=>'folder',
			'type'=>'folder',
			'size' => 100,
			'maxLength'=>100,
			'label'=>'Folder of MP3 Files (must exist already)',
			'path'=>$configArray['EContent']['library'],
			'description'=>'The directory containing the MP3 files.  Must already exist on the econtent server.',
			'serverValidation' => 'validateEpub',
			'required'=> false,
		  'storeDb' => true,
		  'storeSolr' => false,
		),

		'acsId' => array(
      'property'=>'acsId',
      'type'=>'hidden',
      'label'=>'ACS ID',
      'description'=>'The ID of the title within the Adobe Content Server.',
      'storeDb' => true,
		  'storeSolr' => false,
		),
		'recordId' => array(
      'property'=>'recordId',
      'type'=>'hidden',
      'label'=>'Record ID',
      'description'=>'The ID of the record this item is attached to.',
      'storeDb' => true,
		  'storeSolr' => false,
		),

		'notes' => array(
			'property' => 'notes',
			'type' => 'text',
			'label' => 'Notes',
			'description' => 'Notes to the patron to be displayed in the catalog.',
			'storeDb' => true,
		  'storeSolr' => false,
		),

		'reviewStatus' => array(
			'property' => 'reviewStatus',
			'type' => 'enum',
			'values' => array('Not Reviewed' => 'Not Reviewed', 'Approved' => 'Approved', 'Rejected' => 'Rejected'),
			'label' => 'Review Status',
			'description' => 'The status of the review of the item.',
			'storeDb' => true,
			'storeSolr' => false,
			'default' => 'Not Reviewed'
		),

		'reviewNotes' => array(
			'property' => 'reviewNotes',
			'type' => 'textarea',
			'label' => 'Review Notes',
			'description' => 'Notes relating to the reivew.',
			'storeDb' => true,
			'storeSolr' => false,
		),

		'size' => array(
			'property' => 'size',
			'type' => 'label',
			'label' => 'Size',
			'description' => 'The size of the item in bytes or 0 if not known.',
			'storeDb' => false,
			'storeSolr' => false,
		),

		'externalFormat' => array(
			'property' => 'externalFormat',
			'type' => 'label',
			'label' => 'External Format',
			'description' => 'The textual format for external items for use with OverDrive.',
			'storeDb' => false,
			'storeSolr' => false,
		),

		'externalFormatId' => array(
			'property' => 'externalFormatId',
			'type' => 'hidden',
			'label' => 'External Format Id',
			'description' => 'The internal format for external items for use with OverDrive.',
			'storeDb' => false,
			'storeSolr' => false,
		),

		'externalFormatNumeric' => array(
			'property' => 'externalFormatNumeric',
			'type' => 'hidden',
			'label' => 'External Format Id',
			'description' => 'The numeric format for external items for use with OverDrive.',
			'storeDb' => false,
			'storeSolr' => false,
		),

		'identifier' => array(
			'property' => 'identifier',
			'type' => 'label',
			'label' => 'Identifier (ISBN/ASIN)',
			'description' => 'The Identifier (ISBN/ASIN) for the item if we can split from record.',
			'storeDb' => false,
			'storeSolr' => false,
		),

		'sample' => array(
			'property' => 'sample',
			'type' => 'label',
			'label' => 'Sample',
			'description' => 'A url to get a sample of the title.',
			'storeDb' => false,
			'storeSolr' => false,
		),
		);

		foreach ($structure as $fieldName => $field){
			$field['propertyOld'] = $field['property'] . 'Old';
			$structure[$fieldName] = $field;
		}
		return $structure;
	}

	function validateAcsId(){
		//Setup validation return array
		$validationResults = array(
      'validatedOk' => true,
      'errors' => array(),
		);
		//Make sure the id is in the form urn:uuid:03fbf5cb-9ebb-4fa6-b2e9-7a4926884c1c
		if (strlen($this->acsId) > 0 && !preg_match('/^urn:uuid:[\\da-fA-F]{8}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{12}$/', $this->acsId)){
			$validationResults['errors'][] = "The ACS ID is incorrect, it must start with urn:uuid: and then have a valid uuid after from the Adobe Content Server.";
		}

		if ($this->getAccessType() == 'acs' && strlen($this->acsId) == 0){
			$validationResults['errors'][] = "You must provide an ACS ID for titles with DRM.";
		}else if ($this->getAccessType() != 'acs' && strlen($this->acsId) > 0){
			$validationResults['errors'][] = "If an ACS ID is selected, you must select that the title has DRM.";
		}

		//Make sure there aren't errors
		if (count($validationResults['errors']) > 0){
			$validationResults['validatedOk'] = false;
		}
		return $validationResults;
	}

	static function getValidItemTypes(){
		return array(
			'epub' => 'E-Pub',
			'kindle' => 'Kindle',
			'mp3' => 'MP3 Audio',
			'pdf' => 'PDF',
			'plucker' => 'Plucker',
			'externalMP3' => 'External MP3',
			'interactiveBook' => 'Interactive Book',
			'externalLink' => 'External Link',
			'overdrive' => 'OverDrive',
			'external_web' => 'External Web Site',
			'external_ebook' => 'External eBook',
			'external_eaudio' => 'External eAudio',
			'external_emusic' => 'External eMusic',
			'external_evideo' => 'External eVideo',
			'text' => 'Text',
			'gifs' => 'GIFs',
			'itunes' => 'iTunes Audio'
		);
	}

	static function getExternalItemTypes(){
		return array(
			'' => 'N/A',
			'externalMP3' => 'External MP3',
			'interactiveBook' => 'Interactive Book',
			'externalLink' => 'External Link',
			'overdrive' => 'OverDrive',
			'external_web' => 'External Web Site',
			'external_ebook' => 'External eBook',
			'external_eaudio' => 'External eAudio',
			'external_emusic' => 'External eMusic',
			'external_evideo' => 'External eVideo',
		);
	}
	function isExternalItem(){
		return array_key_exists($this->item_type, EContentItem::getExternalItemTypes());
	}
	function validateCover(){
		//Setup validation return array
		$validationResults = array(
      'validatedOk' => true,
      'errors' => array(),
		);

		if ($_FILES['cover']["error"] != 0 && $_FILES['cover']["error"] != 4){
			$validationResults['errors'][] = DataObjectUtil::getFileUploadMessage($_FILES['cover']["error"], 'cover' );
		}

		//Make sure there aren't errors
		if (count($validationResults['errors']) > 0){
			$validationResults['validatedOk'] = false;
		}
		return $validationResults;
	}

	function validateEpub(){
		//Setup validation return array
		$validationResults = array(
      'validatedOk' => true,
      'errors' => array(),
		);

		//Check to see if we have an existing file
		if (isset($_REQUEST['filename_existing']) && $_FILES['filename']['error'] != 4){
			if ($_FILES['filename']["error"] != 0){
				$validationResults['errors'][] = DataObjectUtil::getFileUploadMessage($_FILES['filename']["error"], 'filename' );
			}

			//Make sure that the epub is unique, the title for the object should already be filled out.
			$query = "SELECT * FROM epub_files WHERE filename='" . mysql_escape_string($this->filename) . "' and id != '{$this->id}'";
			$result = mysql_query($query);
			if (mysql_numrows($result) > 0){
				//The title is not unique
				$validationResults['errors'][] = "This file has already been uploaded.  Please select another name.";
			}

			if ($this->item_type == 'epub'){
				if ($_FILES['filename']['type'] != 'application/epub+zip' && $_FILES['filename']['type'] != 'application/octet-stream'){
					$validationResults['errors'][] = "It appears that the file uploaded is not an EPUB file.  Please upload a valid EPUB without DRM.  Detected {$_FILES['filename']['type']}.";
				}
			}else if ($this->item_type == 'pdf'){
				if ($_FILES['filename']['type'] != 'application/pdf'){
					$validationResults['errors'][] = "It appears that the file uploaded is not a PDF file.  Please upload a valid PDF without DRM.  Detected {$_FILES['filename']['type']}.";
				}
			}
		}else{
			//Using the existing file.
		}

		//Make sure there aren't errors
		if (count($validationResults['errors']) > 0){
			$validationResults['validatedOk'] = false;
		}
		return $validationResults;
	}

	function insert(){
		if ($this->reviewStatus == 0){
			$this->reviewStatus = 'Not Reviewed';
		}
		//If the file should be protected with the ACS server, submit the file
		//to the ACS server for protection.
		require_once ROOT_DIR . '/sys/AdobeContentServer.php';
		global $configArray;
		global $user;
		$fileUploaded = false;
		$this->date_added = time();
		$this->addedBy = $user->id;
		$this->date_updated = time();

		//Save the item to the database
		$ret =  parent::insert();

		if ($ret){
			//Package the file as needed
			if ($this->getAccessType() == 'acs' && ($this->item_type == 'epub' || $this->item_type == 'pdf')){
				$uploadResults = AdobeContentServer::packageFile($configArray['EContent']['library'] . '/' . $this->filename, $this->recordId, $this->id, false, $this->getAvailableCopies());
				if ($uploadResults['success']){
					$this->acsId = $uploadResults['acsId'];
					$fileUploaded  = true;
				}else{
					return 0;
				}
			}
		}

		//Make sure to also update the record this is attached to so the full text can be generated
		//Do not update parent for now, it will be indexed the next time the index runs.
		/*if ($this->item_type == 'epub' || $this->item_type == 'pdf'){
			$record = new EContentRecord();
			$record->id = $this->recordId;
			$record->find(true);
			$record->update();
		}*/
		return $ret;

	}

	function update(){
		if ($this->reviewStatus == 0){
			$this->reviewStatus = 'Not Reviewed';
		}
		if ($this->getAccessType() == 'acs' && ($this->item_type == 'epub' || $this->item_type == 'pdf')){
			require_once ROOT_DIR . '/sys/AdobeContentServer.php';
			global $configArray;
			$uploadResults = AdobeContentServer::packageFile($configArray['EContent']['library'] . '/' . $this->filename, $this->recordId, $this->id, $this->acsId, $this->getAvailableCopies());
			if ($uploadResults['success']){
				$oldAcs = $this->acsId;
				$this->acsId = $uploadResults['acsId'];
				$fileUploaded  = true;
				$deleteResults = AdobeContentServer::deleteResource($oldAcs);

			}else{
				$fileUploaded = false;
			}
		}
		$ret = parent::update();
		if ($ret > 0){
			//Make sure to also update the record this is attached to so the full text can be generated
			if ($this->item_type == 'epub' || $this->item_type == 'pdf'){
				$record = $this->getRecord();
				$record->update();
			}
		}
		return $ret;
	}

	function getFullText(){
		global $configArray;
		//Check to see if the text has already been extracted
		$fullText = "";
		$fullTextPath = $configArray['EContent']['fullTextPath'];
		$textFile = "{$fullTextPath}/{$this->recordId}.txt";
		if (file_exists($textFile)){
			return file_get_contents($textFile);
		}else{
			if ($this->item_type == 'text'){
				return file_get_contents($textFile);
			}elseif ($this->item_type == 'epub'){
				require_once(ROOT_DIR . '/sys/eReader/ebook.php');
				$epubFile = $configArray['EContent']['library'] . '/'. $this->filename;
				$ebook = new ebook($epubFile);
				if (!$ebook->readErrorOccurred()){
					$fhnd = fopen($textFile, 'w');
					for ($i = 0; $i < $ebook->getManifestSize(); $i++){
						$manifestId = $ebook->getManifestItem($i, 'id');
						$manifestHref= $ebook->getManifestItem($i, 'href');
						$manifestType= $ebook->getManifestItem($i, 'type');

						if (!in_array($manifestType, array('image/jpeg', 'image/gif', 'image/tif', 'text/css'))){
							try{
								$componentText = $ebook->getContentById($manifestId);
								fwrite($fhnd, strip_tags($componentText));
							}catch(Exeption $e){
								//Ignore it
								//'Unable to load content for component ' . $component;
							}
						}
					}
					fclose($fhnd);
					return file_get_contents($textFile);
				}else{
					return "";
				}
			}elseif ($this->item_type == 'pdf'){
				/* This takes too long for large files */
				/*$pdfboxJar = $configArray['EContent']['pdfbox'];
				$pdfFile = $configArray['EContent']['library'] . '/'. $this->filename;
				$textFile = $configArray['EContent']['fullTextPath'] . '/'. $this->filename;
				shell_exec('java -jar $pdfboxJar ExtractText $pdfFile $textFile');
				return file_get_contents($textFile);*/
				return "";
			}else{
				//Full text not available
				return "";
			}
		}
	}

	function getRecord(){
		if ($this->_record == null){
			require_once(ROOT_DIR . '/sys/eContent/EContentRecord.php');
			$record = new EContentRecord();
			$record->id = $this->recordId;
			if ($record->find(true)){
				$this->_record = clone ($record);
			}
		}
		return $this->_record;
	}

	function getSource(){
		$record = $this->getRecord();
		return $record->source;
	}
	function getAccessType(){
		$record = $this->getRecord();
		return $record->accessType;
	}

	function getAvailableCopies(){
		$record = $this->getRecord();
		return $record->availableCopies;
	}

	function getSize(){
		global $configArray;
		if ($this->filename && strlen($this->filename) > 0){
			if (file_exists($configArray['EContent']['library'] . '/'. $this->filename)){
				return filesize($configArray['EContent']['library'] . '/'. $this->filename);
			}else{
				return 0;
			}
		}else if ($this->folder && strlen($this->folder) > 0){
			//Get the size of all files in the folder
			$mainFolder = $configArray['EContent']['library'] . '/'. $this->folder . '/';
			if (file_exists($configArray['EContent']['library'] . '/'. $this->folder . '/')){
				$size = 0;
				$dh = opendir($mainFolder);
				while (($file = readdir($dh)) !== false) {
					$size += filesize($mainFolder . $file);
				}
				closedir($dh);
				return $size;
			}else{
				return 0;
			}
		}else{
			return 'Unknown';
		}
	}

	function getFormatNotes(){
		$notes = '';
		if ($this->item_type == 'mp3'){

		}else if ($this->item_type == 'epub'){

		}else if ($this->item_type == 'kindle'){

		}else if ($this->item_type == 'plucker'){

		}else if ($this->item_type == 'pdf'){

		}else if ($this->item_type == 'externalMP3'){

		}else if ($this->item_type == 'external_ebook'){
			$source = $this->getSource();
			if ($source == 'SpringerLink'){
				//$notes = "May be read online or downloaded as a PDF";
			}elseif (preg_match('/ebsco/i', $source)){
				$notes = "May be read online. Portions can be printed as a PDF.";
			}

		}else if ($this->item_type == 'externalLink'){

		}else if ($this->item_type == 'overdrive'){
			if ($this->externalFormatId == 'audiobook-mp3'){
				$notes = "Works on MP3 Players, PCs, and Macs. Some mobile devices may require an application to be installed.";
			}else if ($this->externalFormatId == 'audiobook-wma'){
				$notes = "Works on Windows PCs and some devices that can be connected to a Windows PC.";
			}else if ($this->externalFormatId == 'video-wmv'){
				$notes = "Works on Windows PCs and some devices that can be connected to a Windows PC.";
			}else if ($this->externalFormatId == 'music-wma'){
				$notes = "Works on Windows PCs and some devices that can be connected to a Windows PC.";
			}else if ($this->externalFormatId == 'ebook-kindle'){
				$notes = "Works on Kindles and devices with a Kindle app installed.";
			}else if ($this->externalFormatId == 'ebook-epub-adobe'){
				$notes = "Works on all eReaders (except Kindles), desktop computers and mobile devices with with reading apps installed.";
			}else if ($this->externalFormatId == 'ebook-pdf-adobe'){

			}else if ($this->externalFormatId == 'ebook-epub-open'){
				$notes = "Works on all eReaders (except Kindles), desktop computers and mobile devices with with reading apps installed.";
			}else if ($this->externalFormatId == 'ebook-pdf-open'){

			}else{

			}
		}else if ($this->item_type == 'external_eaudio'){

		}else if ($this->item_type == 'external_emusic'){

		}else if ($this->item_type == 'text'){

		}else if ($this->item_type == 'itunes'){

		}else if ($this->item_type == 'gifs'){

		}else{

		}
		return $notes;
	}

	function getDisplayFormat(){
		if ($this->externalFormat){
			return $this->externalFormat;
		}else{
			return translate($this->item_type);
		}
	}

	function getHelpText(){
		$helpText = '';
		if ($this->item_type == 'mp3'){
			$helpText = "How to use a MP3";
		}else if ($this->item_type == 'epub'){
			$helpText = "How to use an EPUB eBook";
		}else if ($this->item_type == 'kindle'){
			$helpText = "How to use a Kindle eBook";
		}else if ($this->item_type == 'plucker'){

		}else if ($this->item_type == 'pdf'){
			$helpText = "How to use a PDF eBook";
		}else if ($this->item_type == 'externalMP3'){

		}else if ($this->item_type == 'external_ebook'){
			$source = $this->getSource();
			if ($source == 'SpringerLink'){
				//$helpText = "How to use SpringerLink eBooks";
			}elseif (preg_match('/ebsco/i', $source)){
				$helpText = "How to use an EBSCO eBook";
			}
		}else if ($this->item_type == 'externalLink'){

		}else if ($this->item_type == 'overdrive'){
			if ($this->externalFormatId == 'audiobook-mp3'){
				$helpText = "How to use a MP3 Audiobook";
			}else if ($this->externalFormatId == 'audiobook-wma'){
				$helpText = "How to use a WMA Audiobook";
			}else if ($this->externalFormatId == 'video-wmv'){
				$helpText = "How to use a WMV Video";
			}else if ($this->externalFormatId == 'music-wma'){
				$helpText = "How to use WMA Music";
			}else if ($this->externalFormatId == 'ebook-kindle'){
				$helpText = "How to use a Kindle eBook";
			}else if ($this->externalFormatId == 'ebook-epub-adobe'){
				$helpText = "How to use an EPUB eBook";
			}else if ($this->externalFormatId == 'ebook-pdf-adobe'){
				$helpText = "How to use a PDF eBook";
			}else if ($this->externalFormatId == 'ebook-epub-open'){
				$helpText = "How to use an EPUB eBook";
			}else if ($this->externalFormatId == 'ebook-pdf-open'){
				$helpText = "How to use a PDF eBook";
			}else{

			}
		}else if ($this->item_type == 'external_eaudio'){

		}else if ($this->item_type == 'external_emusic'){

		}else if ($this->item_type == 'text'){

		}else if ($this->item_type == 'itunes'){

		}else if ($this->item_type == 'gifs'){

		}else{

		}
		return $helpText;
	}
	function getUsageNotes(){
		$notes = '';
		if ($this->libraryId != -1){
			$library = new Library();
			$library->libraryId = $this->libraryId;
			if ($library->find(true)){
				$notes = "Available to <b>{$library->displayName} patrons</b> only.";
			}else{
				$notes = "Could not load library information.";
			}
		}
		return $notes;
	}

}