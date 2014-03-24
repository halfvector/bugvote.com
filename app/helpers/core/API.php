<?php namespace Bugvote\Core;

use Bugvote\Commons\Testing\UserSession;
use Bugvote\Commons\UrlHelper;
use Bugvote\Core\DAL;
use Exception;

class API
{
	/** @var DAL */
	protected $dal;
	/** @var ObjectCache */
	protected $cache;
	/** @var IAudit */
	protected $audit;
	/** @var AssetManager */
	protected $assetManager;

	use API\Ideas;

	/**
	 * @param $dal DAL
	 * @param $audit IAudit
	 * @param $assetManager AssetManager
	 */
	public function __construct($dal, $audit, $assetManager)
	{
		$this->dal = $dal;
		$this->audit = $audit;
		$this->assetManager = $assetManager;
		$this->cache = new ObjectCache($audit);
	}

	/////////////////////////////////////////////////
	// new

	function CreateIdea($appId, $userId, $title, $description)
	{
		$date = $this->dal->getCurrentDateTimeIso8601();

		$seoTitle = UrlHelper::seoUrl($title);

		$ideaId = $this->dal->insert('suggestions')->set([
			'appId' => $appId, 'title' => $title, 'suggestion' => $description,
			'postedAt' => $date, 'userId' => $userId, 'seoUrlTitle' => $seoTitle
		]);

		$seoId = UrlHelper::encodeIdeaId($ideaId);

		// update suggestion with an seo-friendly tiny-id
		$this->dal->update("suggestions")->set(['seoUrlId' => $seoId])->where(['suggestionId' => $ideaId]);

		// insert the initial revision
		$revisionId = $this->dal->insert('suggestionRevisions')->set([
			'suggestionId' => $ideaId, 'title' => $title, 'description' => $description,
			'revisionDate' => $date, 'userId' => $userId
		]);

		$log = $this->audit->getLogger();
		$log && $log->write("Created an idea " . UrlHelper::createIdeaUrl($seoId, $seoTitle));
	}


	/////////////////////////////////////////////////
	// old

	public function UpdateBugView($suggestionId, $userId)
	{
		$this->dal->updateSingleObj(
			'insert into suggestionViews set suggestionId = :suggestionId, userId = :userId, viewed = utc_timestamp() on duplicate key update viewed = utc_timestamp()',
			[':suggestionId' => $suggestionId, ':userId' => $userId]
		);
	}

	public function GetNotificationCount($appId)
	{
		return $this->dal->fetchSingleValue("select count(*) from suggestions where appId = :appId and needsAttention = 1", [':appId' => $appId]);
	}

	public function GetNewSuggestions()
	{
	}

	const ISSUE_TYPE_BUG = 1;
	const ISSUE_TYPE_FEATURE = 2;
	const ISSUE_TYPE_DUPLICATE = 3;

	// fresh means the issue hasn't been touched yet, it needs to be responded to
	const ISSUE_STATE_FRESH = 1;
	// in discovery phase, trying to understand the problem
	const ISSUE_STATE_DISCOVERY = 2;
	// issue has been understood and transformed into a bug and is awaiting prioritization
	const ISSUE_STATE_PRIORITIZATION = 3;
	// issue is being actively worked on, someone is trying to fix it
	const ISSUE_STATE_WIP = 4;
	// issue has been resolved. fixed, ignored, whatever it has been dealt with.
	const ISSUE_STATE_RESOLVED = 5;

	/**
	 * @param $suggestionId int id of user suggestion
	 * @param $issueType int issue type SUGGESTION_BUG/SUGGESTION_FEATURE/SUGGESTION_DUPLICATE
	 * @param $relatedIssueId int optional suggestionId of duplicated issue (required for SUGGESTION_DUPLICATE)
	 */
	public function SetSuggestionType($suggestionId, $issueType, $relatedIssueId = 0)
	{
		// TODO: permissions check

		if( $issueType == self::ISSUE_TYPE_BUG )
		{   // we have a new bug
			$this->dal->update("suggestions")->set(['suggestionTypeId' => self::ISSUE_TYPE_BUG])->where(['suggestionId' => $suggestionId]);
		}

		if( $issueType == self::ISSUE_TYPE_FEATURE )
		{   // we have a new feature
			$this->dal->update("suggestions")->set(['suggestionTypeId' => self::ISSUE_TYPE_FEATURE])->where(['suggestionId' => $suggestionId]);
		}

		if( $issueType == self::ISSUE_TYPE_DUPLICATE )
		{   // we have a duplicate
			$this->dal->update("suggestions")->set(['suggestionTypeId' => self::ISSUE_TYPE_DUPLICATE])->where(['suggestionId' => $suggestionId]);
		}
	}

	public function GetSuggestionType($suggestionId)
	{
	}

	public function GetSuggestionComments($suggestionId)
	{
		return $this->dal->fetchMultipleObjs('
			select
				c.*, date_format(postedAt, "%Y-%m-%dT%TZ") as postedAtIso8601,
			 	u.userId, u.profileMediumAssetId, u.fullName
			from
				suggestionComments c
				natural left join users u
			where
				suggestionId = :suggestionId
			', [':suggestionId' => $suggestionId]
		);
	}

	public function GetSuggestionsByAppId($appId)
	{
		return $this->dal->fetchMultipleObjs('
			select s.*, u.userId, u.profileMediumAssetId, u.fullName
			from
				suggestions s
				natural left join users u
			where
				-- suggestionTypeId = 0 and
				appId = :appId
			', [':appId' => $appId]
		);
	}

	public function GetSuggestionDetailsById($suggestionId)
	{
		return $this->dal->fetchSingleObj('
			select *
			from suggestions natural left join users u
			where suggestionId = :suggestionId
			', [':suggestionId' => $suggestionId]
		);
	}

	public function OpenIssue($userId, $title, $body)
	{
	}

	public function SetIssueType($issueId, $issueType)
	{
	}

	public function GetIssueType($issueId)
	{
	}

	public function GetIssuesByType($appId, $issueType)
	{
		$issues = $this->dal->selectMany("s.*, unix_timestamp(s.postedAt) as postedAtTimestamp, utc_timestamp() - postedAt as age from suggestions s")->where(['appId' => $appId, 'suggestionTypeId' => $issueType]);

		return $issues;
	}

	public function GetAllUsersVotingOnIssue($issueId)
	{
		$users = $this->dal->selectMany("* from suggestionVotes natural left join users u")->where(['suggestionId' => $issueId]);
		return $users;
	}

	/**
	 * @param $issueId int issueId
	 * @return bool|mixed list of users who voted to get the issue fixed
	 */
	public function GetUsersVotingForIssue($issueId)
	{
		$users = $this->dal->selectMany("* from suggestionVotes natural left join users u")->where(['suggestionId' => $issueId, 'vote' => 1]);
		return $users;
	}


	public function GetIssueState($issueId)
	{
	}

	public function SetIssueState($issueId, $issueState)
	{
	}

	public function GetAppsByDeveloperId($developerId)
	{
		return $this->dal->selectMany("* from apps")->where(['developerId' => $developerId]);
	}

	# get apps admined by the user
	public function GetAppsByUser($userId)
	{
		$perf = \PerformanceLog::start(__METHOD__);

		# cache data
		$result = $this->cache->Cache("$userId/apps/list", [$this, 'GetAppsByUserUncached'], [ $userId ], $perf);

		$perf->save();

		return $result;
	}

	/**
	 * @param int $userId
	 * @param \PerformanceLog $perf
	 * @return array|bool
	 */
	public function GetAppsByUserUncached($userId, $perf)
	{
		# grab data
		$apps = $this->dal->selectMany("* from apps natural join appAdmins")->where(['userId' => $userId]);

		# format it
		foreach( $apps as $app )
		{
			$app->imgUrl = ImageProvider::GetResizedAssetWebPath($app->thumbnailMediumAssetId, 200, 150, "auto");
			if( ! $app->imgUrl )
				$app->imgUrl = "/img/placeholders/200x150.gif";
			$app->appUrl = "/developer/app/{$app->appId}";
		}

		$perf->mark("App DataModels prepared");

		return $apps;
	}

	public function GetAppDetails($appId)
	{
		$app = $this->dal->fetchSingleObj("select * from projects where projectId = :id", [':id' => $appId], "project from projectId");

		if($app->thumbnailAssetId)
			$app->imgUrl = $this->assetManager->GetWebPathForAsset($app->thumbnailAssetId);
		else
			$app->imgUrl = '/img/placeholders/200x150.gif';

		$app->appUrl = UrlHelper::createAppUrl($app->seoUrlTitle);

		return $app;
	}

	/**
	 * @param $name
	 * @return bool|array
	 */
	public function GetAppsByAppName($name)
	{
		$apps = $this->dal->fetchMultipleObjs('
			select * from apps
				natural join appAdmins
				natural join users
				natural left join developers
				where appName like :appName
			', [':appName' => $name]
		);

		return $apps;
	}

	public function AddSuggestionComment($bugId, $userId, $message)
	{
		return $this->dal->insertSingleObj('
			insert into suggestionComments
			set suggestionId = :bugId, userId = :userId, comment = :message,
				postedAt = utc_timestamp(), editedAt = null
			', [':bugId' => $bugId, ':userId' => $userId, ':message' => $message]
		);
	}

	/////////////////////////////////////////////////
	// Users

	public function GetUsersList()
	{
		return $this->dal->fetchMultipleObjs("select * from users");
	}

	public function GetUserDetails($userId)
	{
		return $this->dal->fetchSingleObj("select * from users where userId = :userId", [':userId' => $userId]);
	}

	public function CreateUser($fullName, $profileMediumAssetId)
	{
		return $this->dal->insert('users')->set(['fullName' => $fullName, 'profileMediumAssetId' => $profileMediumAssetId]);
	}

	public function UpdateUser($userId, $fullName, $profileMediumAssetId)
	{
		$this->dal->update('users')->set(['fullName' => $fullName, 'profileMediumAssetId' => $profileMediumAssetId])->where(['userId' => $userId]);
	}

	public function CanUserModifySuggestion($userId, $suggestionId)
	{
		return true;
	}

	/**
	 * uploads a file and attaches it to a suggestion
	 * @param $userId int id of user attempting to modify suggestion
	 * @param $suggestionId int id of parent suggestion
	 * @param $file array entry from _FILES containing [name, tmp_name, etc]
	 * @return bool true on success, false otherwise
	 */
	public function CreateSuggestionAttachment($userId, $suggestionId, $file)
	{
		$log = $this->audit->getLogger();

		// TODO: permissions check: userId allowed-to-modify suggestionId
		if( ! $this->CanUserModifySuggestion($userId, $suggestionId) )
		{
			$log && $log->writeObject("Error: user $userId not allowed to modify $suggestionId", $file);
			return false;
		}

		// TODO: try a new fluent-dal syntax:
		// $dal->table("suggestionAttachments")->insert(['attachmentName' => $file["name"], 'extension' => pathinfo($file["name"], PATHINFO_EXTENSION)]);
		// $dal->table("suggestionAttachments")->update(['attachmentName' => $file["name"]])->where(['suggestionId' => 50]);

		//$assetId = $this->dal->insert("suggestionAttachments")->set(['isValid' => false, 'originalFilename' => $file["name"]]);

		// /storage/ideas/[:ideaId]/attachments/[:attachmentId]/filename.ext

		// permutations of 3 character encodings
		// [0-9]^3 = 1k
		// [0-f]^3 = 4k
		// [a-z]^3 = 18k // max limit of ext3 sub-directories is 32k
		// [a-z0-9]^3 = 47k // max limit of ext4 sub-directories is 64k
		// for easy browsing, you don't want more than 4k subdirectories
		// [a-zA-Z0-9]^3 = 240k
		// [a-zA-Z0-9]^2 = 4k // optimal

		// eg: /storage/assets/jZ/5X/3x/screenshot.jpg
		// jZ5X3x = 56 billion files
		// at that point, i'm going to have other problems.

		// eg: /storage/assets/5jx/z0r/screenshot.jpg
		// eg: /storage/assets/001/8qe/screenshot.jpg
		// 5jxz0r = 1.5 billion files. probably will run out of inodes by this time :)
		// partitioning is pretty easy
		// can just grab every 10 first-level folders. they will contain 1.30 million files.
		// so 0-10 go to server A, 11-20 go to server B
		// easy historical partitioning. every first-level folder contains 47k files.
		// 47k files * 150kb avg file size = 7GB! that's a very convenient partitioning size
		// can easily spawn servers with 200GB of space each. enough to hold 28 first-level folders.

		// /storage/assets/40000/35000/screenshot.jpg vs /storage/assets/zx8/y3x/screenshot.jpg

		// eg: /storage/assets/34/e8/screenshot.jpg
		// 34e8 = 1.3 million files. sounds about right. 34^4 = 1,336,336 (interesting number)

		// eg: /storage/assets/340/097/screenshot.jpg
		// 340097 = 1 million files

		// can always branch. once the asset-id is > some number, switch to sub-directory scheme.

		// or do modula on 50k chunks (or some other size)
		// two 50k chunks = 2.5 billion files
		// 123,456 = 2 * 50k + 23,456 = 2/23456
		// 12,345,678 = 246 * 50k + 45,678 = 246/45678

		// chunks by 4k chunks
		// two 4k chunks = 16 million
		// 123,456 = 30 * 4k + 3456 = 30/3456
		// 12,345,678 = 3086 * 4k + 1678 = 3086/1678
		// 1,234,567,890 = 308641 * 4k + 3890 = (77 * 4k + 641) * 4k + 3890 = 77/641/3890

		// 4k * 200kb = 800MB per folder. easy to backup. very rsyncable.
		// if i have 16 million images, that means i have at least 2 million suggestions (8 images per suggestion)
		// and at least 5000 apps (400 suggestions per app), if only 5% are paying customers, that means 250 paying customers
		// 250 paying customers * $25/mo revenue = $6k/mo total revenue
		// with that much income, i can afford to put up a few servers and spend 2 weeks upgrading storage
		// the upgrade should be as simple as moving every first-level folder into a new first-level folder "000", then adding another 3 digits on the ids
		// this bumps us up to 3 * 4k chunks = 64 billion files.

		// so we can think about things in a different way. "what revenue does this technology scale to?"
		// $6k/mo of revenue at 5% conversion means 5000 apps * 400 suggestions/app * 8 attachments/suggestion = 16 million attachments
		// $15k/mo of revenue? 40 million attachments.

		// 36^2 = 1.3k
		// 36^3 = 46k
		// 36^4 = 1.6 mil
		// 36^5 = 60 mil
		// 36^3/36^2 = 1.3k per leaf node. can start with 1.3k parent nodes, and grow to 46k parent nodes. ext4 ok. up to 60 mil in total.
		// by the time this is reached, i'll have at least 22k/mo in revenue.
		// 1.3k leaf node = 390MB of assets (~300kb each)
		// what about caching thumbnails and various best-fit resolutions? up to 1MB per image
		// 1.3k leaf node = 1GB of assets worst case. still easy chunks for backup/rsync.

		// 36^3/36^3 = 2 billion files. 800k/mo revenue. won't be running on one machine :)

		// only issue is that [a-z0-9] is harder/ambigious to sort lexically. what comes first, numbers or letters? everyone has a diff answer.
		// but doing [a-z]^3 = 13k leaf nodes. 190 million files for two levels.

		// at $15k/mo i can hire two more people to help fulltime. so maybe the goal should be 40 million attachments instead of 16 million.
		// projected revenue based technical specs

		// doing a base_convert from base10 to base34, then splitting into 3-character chunks gives us 50k sub-dirs (2 levels is enough)
		// doing a base_convert from base10 to base16, then splitting into 3-character chunks gives u 4k sub-dirs (3 levels is needed)

		// sanity checks
		if(!is_uploaded_file($file["tmp_name"]))
		{
			$log && $log->writeObject("Error: tmp file is not a valid uploaded file", $file);
			return false;
		}

		if(empty($file["name"]))
		{
			$log && $log->writeObject("Error: uploaded file does not have a valid name", $file);
			return false;
		}

		if($file["error"] != 0)
		{
			$log && $log->writeObject("Error: file upload had an error", $file);
			return false;
		}

		// TODO: what happens when execution is aborted after transaction is started?
		// does the database automatically rollback when the connection is severed by the php process?
		// how long does the connection take to time out?
		// what happens until it times out? do further transactions succeed? do we get holes in assetId sequences
		// when a transaction is awaiting timeout and another transaction succeeds with a higher assetId?
		$this->dal->beginTransaction();

		// insert a placeholder
		$assetId = $this->dal->insert("assets")->set(['isValid' => false, 'originalFilename' => $file["name"]]);
		$absoluteDirectory = $this->assetManager->getAbsoluteAssetStoragePath($assetId);

		// path may need to be created
		if(!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true))
		{
			$log && $log->writeObject("Error: failed to create needed folder: $absoluteDirectory for file", $file);
			$this->dal->rollbackTransaction();
			return false;
		}

		$absoluteFilePath = $absoluteDirectory . $file["name"];

		if( ! $this->assetManager->TryUploadAsset($file, $absoluteFilePath) )
		{
			$log && $log->writeObject("Error: asset-copy failed to $absoluteFilePath for file", $file);
			$this->dal->rollbackTransaction();
			return false;
		}

		// path to asset within the storage folder. this can be quickly transformed into a web-path or a system-path
		$relativeFilePath = $this->assetManager->GetRelativeAssetsPath($assetId) . $file["name"];

		// commit asset
		$this->dal->update('assets')->set(['isValid' => true, 'mimeType' => $file["type"]])->where(['assetId' => $assetId]);

		$fileExtension = pathinfo($file["name"], PATHINFO_EXTENSION);

		// add suggestion-attachment entry
		$this->dal->insert("suggestionAttachments")->set(
			['assetId' => $assetId, 'attachmentName' => $file["name"], 'extension' => $fileExtension, 'suggestionId' => $suggestionId]
		);

		$this->dal->commitTransaction();

		$log && $log->write("Asset created=$relativeFilePath of type {$file["type"]} with assetId=$assetId");

		return true;
	}

	// create a pending asset using an uploaded File
	/**
	 * @param $file File
	 * @return int|bool
	 */
	public function CreateAsset($file)
	{
		$log = $this->audit->getLogger();

		if(!$file)
		{
			$log && $log->write("Asset not created, file object was null");
			return false;
		}

		if( $file['error'] )
		{
			$log && $log->writeObject("File upload had error-code: {$file['error']}", $file);
			return false;
		}

		// sanitize filename
		$assetFilename = $this->SanitizeAssetFilename($file["name"]);

		$this->dal->beginTransaction();

		try
		{
			// insert a placeholder
			$assetId = $this->dal->insert("assets")->set(['isValid' => false, 'originalFilename' => $assetFilename]);

			// get path
			$assetDir = $this->assetManager->getAbsoluteAssetStoragePath($assetId);

			// create path
			if(!file_exists($assetDir))
			{
				$log && $log->write("Creating asset (assetId=$assetId) folder: $assetDir");
				mkdir($assetDir, 0775, true);
			}

			// build final path
			$assetFilePath = $assetDir . $assetFilename;

			// go ahead and upload
			if(!$this->assetManager->TryUploadAsset($file, $assetFilePath))
			{   // on failure, rollback the transaction
				$this->dal->rollbackTransaction();
				$log && $log->write("Asset update Failed: assetId=$assetId filePath=$assetFilePath");
				return false;
			}

			// relative path for building public-urls
			$relativePath = $this->assetManager->createFolderPath($assetId) . $assetFilename;

			// enable asset and give it a handy path
			$this->dal->update('assets')->set([
				'isValid' => true,
				'assetPath' => $relativePath
			])->where(['assetId' => $assetId]);

			$this->dal->commitTransaction();
		}
		catch(Exception $err)
		{
			$log && $log->write("Error  creating asset: " . $err->getMessage());
			$this->dal->rollbackTransaction();
			return false;
		}

        $log && $log->write("Asset created=$assetFilePath with assetId=$assetId");

		return $assetId;
	}

	/**
	 * returns a clean filename fit for an asset
	 * @param $rawFilename string
	 * @return string
	 */
	public function SanitizeAssetFilename($rawFilename)
	{
		$filename = iconv('UTF-8', 'ASCII//TRANSLIT', $rawFilename); // convert unicode to ascii
		$filename = trim($filename,"."); // trim leading and trailing periods

		// save the extension (could be a complex one like .tar.gz)

		$filename = preg_replace("/[^.0-9a-zA-Z_-]/", "_", $filename); // replace funny characters with _
		$filename = $filename ?: "default";

		if(strlen($filename) > 32)
			$filename = substr($filename, strlen($filename) - 32, 32);

		return $filename;
	}

    public function UpdateAsset($assetId, $file)
    {
		$log = $this->audit->getLogger();

        if( $file['error'] )
        {
			$log && $log->writeObject("File upload had error-code: {$file['error']}", $file);
			return false;
        }

        $assetDir = $this->assetManager->getAbsoluteAssetStoragePath($assetId);

		// for sanity's sake, ensure path exists
		if(!file_exists($assetDir))
		{
			$log && $log->write("Creating asset (assetId=$assetId) folder: $assetDir");
			mkdir($assetDir, 0755, true);
		}

		// sanitize filename
		$assetFilename = $this->SanitizeAssetFilename($file["name"]);

		// build final path
		$assetFilePath = $assetDir . $assetFilename;

		// attempt upload
        if($this->assetManager->TryUploadAsset($file, $assetFilePath))
		{
        	$this->dal->update('assets')->set([
				'isValid' => true,
				'assetPath' => $this->assetManager->createFolderPath($assetId) . $assetFilename,
				'originalFilename' => $assetFilename
			])->where(['assetId' => $assetId]);

        	$log && $log->write("Asset update successful: assetId=$assetId filePath=$assetFilePath");

			return true;

		} else
		{
			$log && $log->write("Asset update Failed: assetId=$assetId filePath=$assetFilePath");
			return false;
		}
    }

	public function EnableAsset($assetId)
	{
		$this->dal->update("assets")->set(['isValid' => true])->where(['assetId' => $assetId]);
	}

	protected function FormatApps(&$apps)
	{
		foreach( $apps as $app )
		{
			$app->imgUrl = ImageProvider::GetResizedAssetWebPath($app->thumbnailMediumAssetId, 200, 150, "auto");
			if( ! $app->imgUrl )
				$app->imgUrl = "/img/placeholders/200x150.gif";
			$app->appUrl = "/admin/apps/{$app->appId}";
		}
	}

	# borrowed from http://php.net/manual/en/function.time.php
	public function NiceAgeShort($secs)
	{
		$bit = array(
			'y' => $secs / 31556926 % 12,
			'w' => $secs / 604800 % 52,
			'd' => $secs / 86400 % 7,
			'h' => $secs / 3600 % 24,
			'm' => $secs / 60 % 60,
			's' => $secs % 60
		);

		foreach($bit as $k => $v)
			if($v > 0)$ret[] = $v . $k;

		return join(' ', $ret);
	}

	public function SmartShortAge($secs)
	{
		$secs = max(1,ceil($secs));

		$bit = array(
			' year'        => $secs / 31556926 % 12,
			' month'        => $secs / 2592000 % 12,
			' week'        => $secs / 604800 % 52,
			' day'        => $secs / 86400 % 7,
			' hour'        => $secs / 3600 % 24,
			' minute'    => $secs / 60 % 60,
			' second'    => $secs % 60
		);

		$ret = [];
		foreach($bit as $k => $v) {
			if($v > 1) $ret []= $v . $k . 's';
			if($v == 1) $ret []= $v . $k;
		}

		$best = array_shift($ret);
		return $best;
	}

    public function SmartLongAge($secs)
    {
        $secs = max(1,ceil($secs));

        $bit = array(
            ' year'        => $secs / 31556926 % 12,
            ' month'        => $secs / 2592000 % 12,
            ' week'        => $secs / 604800 % 52,
            ' day'        => $secs / 86400 % 7,
            ' hour'        => $secs / 3600 % 24,
            ' minute'    => $secs / 60 % 60,
            ' second'    => $secs % 60
        );

        $ret = [];
        foreach($bit as $k => $v) {
            if($v > 1) $ret []= $v . $k . 's';
            if($v == 1) $ret []= $v . $k;
        }

		while(count($ret) > 2)
			array_pop($ret);

        //$best = array_shift($ret);
        //return $best;
		return join(' and ', $ret);

        // limit resolution to first two parts of precision
        //if(count($ret) > 2)
        //   $ret = array_slice($ret, 0, 2);

        //array_splice($ret, count($ret)-1, 0, 'and');
        //$ret[] = 'ago';
        //return join(' ', $ret);
    }

	function NiceAgeDays($secs)
	{
		try
		{
			$secs = max(1,ceil($secs));

			$bit = array(
				' day'        => $secs / 86400 % 7,
				' hour'        => $secs / 3600 % 24,
				' minute'    => $secs / 60 % 60,
				' second'    => $secs % 60
			);

			$ret = [];
			foreach($bit as $k => $v) {
				if($v > 1) $ret []= $v . $k . 's';
				if($v == 1) $ret []= $v . $k;
			}

			// limit resolution to first two parts of precision
			if(count($ret) > 1)
				$ret = array_slice($ret, 0, 1);

			//array_splice($ret, count($ret)-1, 0, 'and');
			//$ret[] = 'ago';

			return join(', ', $ret) . ' ago';
		}
		catch(Exception $err)
		{
			\Log::WriteObject("Error creating nice ago-string", $err);
			return "$secs seconds (err)";
		}
	}

	public function NiceAgeLong($secs)
	{
        try
        {
            $secs = max(1,ceil($secs));

            $bit = array(
                ' year'        => $secs / 31556926 % 12,
                ' week'        => $secs / 604800 % 52,
                ' day'        => $secs / 86400 % 7,
                ' hour'        => $secs / 3600 % 24,
                ' minute'    => $secs / 60 % 60,
                ' second'    => $secs % 60
            );

            $ret = [];
            foreach($bit as $k => $v) {
                if($v > 1) $ret []= $v . $k . 's';
                if($v == 1) $ret []= $v . $k;
            }

            // limit resolution to first two parts of precision
            if(count($ret) > 2)
                $ret = array_slice($ret, 0, 2);

            //array_splice($ret, count($ret)-1, 0, 'and');
            //$ret[] = 'ago';

            return join(', ', $ret) . ' ago';
        }
        catch(Exception $err)
        {
            \Log::WriteObject("Error creating nice ago-string", $err);
            return "$secs seconds (err)";
        }
	}

	public function NiceDate($timestamp)
	{
		return strftime("%h %e, %Y", $timestamp);
	}

	public function NiceDateTime($timestamp)
	{
		return strftime("%l %p, %h %e, %Y", $timestamp);
	}
}
