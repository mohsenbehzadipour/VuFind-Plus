<?php

require_once 'bootstrap.php';
require_once ROOT_DIR . '/sys/Utils/SwitchDatabase.php';
global $timer;
global $logger;

$library = new Library();
$library->find();

ob_start();
echo("<br>Starting to optimize tables<br/>\r\n");
$logger->log('Starting to optimize tables', PEAR_LOG_INFO);
ob_flush();

foreach ($configArray['Database'] as $key => $value){
	if (preg_match('/table_(.*)/', $key, $matches)){
		if ($value =='vufind'){
			SwitchDatabase::switchToVuFind();
		}else{
			SwitchDatabase::switchToEcontent();
		}
		$tableName = $matches[1];

		//Some tables take too long to optimize, ignore them.
		if (in_array($tableName, array('analytics_session'))){
			optimizeTable($tableName);
		}
	}
}

//Optimize tables that are not part of the browse definition
SwitchDatabase::switchToVuFind();
set_time_limit(300);
optimizeTable('title_browse');
optimizeTable('title_browse_metadata');
optimizeTable('title_browse_scoped_results_global');
optimizeTable('author_browse');
optimizeTable('author_browse_metadata');
optimizeTable('author_browse_scoped_results_global');
optimizeTable('subject_browse');
optimizeTable('subject_browse_metadata');
optimizeTable('subject_browse_scoped_results_global');
optimizeTable('callnumber_browse');
optimizeTable('callnumber_browse_metadata');
optimizeTable('callnumber_browse_scoped_results_global');

while ($library->fetch()){
	optimizeTable("title_browse_scoped_results_library_{$library->subdomain}");
	optimizeTable("author_browse_scoped_results_library_{$library->subdomain}");
	optimizeTable("subject_browse_scoped_results_library_{$library->subdomain}");
	optimizeTable("callnumber_browse_scoped_results_library_{$library->subdomain}");
}
$logger->log('Finished optimizing tables', PEAR_LOG_INFO);

function optimizeTable($tableName){
	global $logger;
	set_time_limit(1000);
	echo("Optimizing $tableName<br/>\r\n");
	mysql_query("OPTIMIZE TABLE $tableName;");
	$logger->log('Optimized table: ' . $tableName, PEAR_LOG_INFO);
}