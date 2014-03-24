<?php namespace Bugvote;

use Bugvote\Commons\UrlHelper;
use Bugvote\Core\AssetManager;
use Bugvote\Core\AuditFacade;
use Bugvote\Core\DAL;
use Bugvote\Core\DAL\DatabaseSettings;
use Bugvote\Core\NoAudit;
use Bugvote\Core\Paths;

// root path of the project (contains /app, /vendor, and /tools)
$rootDir = realpath("../../");

include $rootDir . "/app/helpers/base/MyAppAutoloader.php";
initializeAutoloaders($rootDir);

$paths = new Paths($rootDir);

if(php_uname('n') == 'prometheus')
	$databaseSettings = DatabaseSettings::load("home-staging", $paths);
else
	$databaseSettings = DatabaseSettings::load("joyent-staging", $paths);

$auditProvider = new NoAudit();
$auditFacade = new AuditFacade($auditProvider);

// data access layer
$dataAccessLayer = new DAL($databaseSettings, $auditProvider);

// asset manager
$assetManager = new AssetManager($paths, $auditFacade);

/////////////////////////////////////////////////
// perform work

$items = $dataAccessLayer->fetchMultipleObjs("show tables");

foreach($items as $item)
{
	$tableName = $item->{'Tables_in_apptogether'};

	$charset = $dataAccessLayer->fetchSingleValue("
		SELECT CCSA.character_set_name FROM information_schema.`TABLES` T,
       	information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA
		WHERE CCSA.collation_name = T.table_collation
  		AND T.table_schema = 'apptogether'
  		AND T.table_name = :tableName",
		['tableName' => $tableName]
	);

	if($charset != "utf8" && $charset != "")
		echo "$tableName = $charset\n";

	if(0)
	$dataAccessLayer->updateSingleObj(
		"alter table $tableName convert to character set utf8 collate utf8_unicode_ci"
		//[":tableName" => $tableName ]
	);
}
