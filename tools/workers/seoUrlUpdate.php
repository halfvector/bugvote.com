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

$items = $dataAccessLayer->fetchMultipleObjs("select * from projects");

foreach($items as $item)
{
	$title = UrlHelper::seoUrl($item->projectTitle);
	$id = UrlHelper::encodeIdeaId($item->projectId);

	$dataAccessLayer->update("projects")->set(["seoUrlId" => $id, "seoUrlTitle" => $title])->where(["projectId" => $item->projectId]);
}
