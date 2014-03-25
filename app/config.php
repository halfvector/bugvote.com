<?php

use Bugvote\Lib\BugvoteAutoload;

ini_set("xdebug.var_display_max_depth", 5);

define('APP_DEPLOYED', 'HOME');

if($_SERVER['HTTP_HOST'] == 'www.dev.bugvote.com')
	define('APP_STATIC_HOSTNAME', 'static.dev.bugvote.com');
else
	define('APP_STATIC_HOSTNAME', 'static.alpha.bugvote.com');

define('APP_STATIC_IMG_CACHE', '//' . APP_STATIC_HOSTNAME . '/cache');

// "system" folder that contains app, tmp, tools, etc
define('BUGVOTE_ROOT', realpath(dirname(__FILE__) . '/../'));
define('BUGVOTE_APP', BUGVOTE_ROOT . '/app');
define('BUGVOTE_APP_VIEWS', BUGVOTE_ROOT . '/app/Views/');

define('BUGVOTE_TMP', BUGVOTE_ROOT . '/tmp/');
define('BUGVOTE_TMP_XAML_CACHE', BUGVOTE_ROOT . '/tmp/xaml-cache');

// app-specific autoloading config
function SetupAutoloading() {
	$absAppRootPath = BUGVOTE_APP;
	set_include_path(get_include_path() . PATH_SEPARATOR . $absAppRootPath);

	$paths = [
		['\Bugvote\Core', $absAppRootPath . '/helpers/core'],
		['\Bugvote\Commons', $absAppRootPath . '/helpers/commons'],
		['\Bugvote\Lib', $absAppRootPath . '/Lib'],

		// the typical separate-folders MVC layout
		['\Bugvote\Controllers', $absAppRootPath . '/Controllers'],
		['\Bugvote\ViewModels', $absAppRootPath . '/ViewModels'],
		['\Bugvote\DataModels', $absAppRootPath . '/DataModels'],
		['\Bugvote\Services', $absAppRootPath . '/Services'],
	];

	// internal-app-codebase autoloader
	require __DIR__ . "/Lib/Autoloader.php";
	$autoloader = new BugvoteAutoload();
	$autoloader->register($paths);

	// external-libs autoloader
	require __DIR__ . "/../vendor/autoload.php";
}

SetupAutoloading();