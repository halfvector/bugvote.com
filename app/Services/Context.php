<?php namespace Bugvote\Services;

use AltoRouter;
use Bugvote\Commons\ImageManager;
use Bugvote\Commons\PageViewModel;
use Bugvote\Commons\UrlResolver;
use Bugvote\Core\API;
use Bugvote\Core\AssetManager;
use Bugvote\Core\DAL;
use Bugvote\Core\Logging\AppPerformanceLog;
use Bugvote\Core\Logging\ILogger;
use Bugvote\Core\Logging\LogEntriesCom;
use Bugvote\Core\Paths;
use Bugvote\Core\Permissions;
use Bugvote\Core\Renderer\IRenderer;
use Bugvote\Core\RequestVariables;
use Bugvote\Core\SessionSegment;
use Bugvote\Core\UrlBuilder;
use Redis;

// service-locator
class Context
{
	/** @var AppPerformanceLog */
	public $perf = false;

	/** @var ILogger */
	public $log = false; // do we really need two separate loggers, cant it be one service?

	/** @var DAL */
	public $dal = false;

	/** @var Redis */
	public $redis = false;

	/** @var IRenderer */
	public $renderer = false;

	/** @var UserSession */
	public $user = false;

	/** @var Permissions */
	public $permissions = false;

	/** @var SessionSegment */
	public $session = false;

	/** @var AssetManager */
	public $assetManager = false;

	/** @var Paths */
	public $paths = false;

	/** @var UrlResolver */
	public $resolver = null; // should be merged with Paths

	/** @var AltoRouter */
	public $router;

	/** @var UrlBuilder */
	public $url = null;

	/** @var ImageManager */
	public $images = null;

	/** @var LibratoClient */
	public $metrics = null;

	/** @var RequestVariables */
	public $parameters = false;

	/** @var DataSigning */
	public $csrf = null;

	/** @var string */
	public $scope = "";

	/** @var LogEntriesCom */
	public $logentries = null;


	public $rendered = false;

	public function renderTemplate(PageViewModel $vm)
	{
		$p = $this->perf->start("Template Rendering");

		$this->log->write("Rendering: layout=$vm->layout template=$vm->template");

		$this->renderer && $this->renderer->render($vm->layout, $vm);

		$p->stop();

		$this->rendered = true;
	}

	public function flash($message, $type) {
		$this->session->setFlash($message, $type);
	}

	/**
	 * sets a redirect header, but does not exist control flow. caller is responsible for returning early if necessary.
	 * @param string $url
	 * @param int $code
	 * @return bool always true
	 */
	public function redirect($url, $code = 303) {
		//$this->response->redirect($url, $code);
		$this->log->write("redirecting to: $url code: $code");
		header("Location: $url", true, $code);
		return true;
	}
}