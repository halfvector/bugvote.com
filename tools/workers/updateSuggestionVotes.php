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

$items = $dataAccessLayer->updateSingleObj("update suggestions s set numOfVotes = (select sum(v.vote) from suggestionVotes v where (s.suggestionId = v.suggestionId))");

/*
$items = $dataAccessLayer->fetchMultipleObjs("select s.*, count(v.vote) as votes from suggestions s left join suggestionVotes v on (s.suggestionId = v.suggestionId and s.userId = v.userId) group by s.suggestionId");

foreach($items as $item)
{
	// ensure that each suggestion has at least one vote -- that of the original poster

	if($item->title != "")
	{
		//if(!$item->votes)
		{
			echo "$item->suggestionId $item->title has $item->votes self-votes\n";
			//$dataAccessLayer->insert("suggestionVotes")->set(["suggestionId" => $item->suggestionId, "userId" => $item->userId, "vote" => 1]);
		}

	}

	//$dataAccessLayer->update("projects")->set(["seoUrlId" => $id, "seoUrlTitle" => $title])->where(["projectId" => $item->projectId]);
}
*/