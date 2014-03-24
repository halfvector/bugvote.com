<?php
/**
 * Created by PhpStorm.
 * User: tester1
 * Date: 8/2/13
 * Time: 1:29 AM
 */
namespace Bugvote\Core\Cache;

use Bugvote\Core\AuditFacade;

interface IObjectCache
{
    function __construct($facade);

    function cache($key, $object);

    function invalidate($dependencyKey);

    function getCachedOrEval($key, $method, $params, $forceInvalidation = false);

    function get($key);
}