<?php namespace Bugvote\Commons;

use Bugvote\Services\Context;

class UrlParser
{
    /** @var \Bugvote\Context */ protected $ctx;
	/** @var \Bugvote\EasyAccessFacade */ protected $helper;

    function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    function getIdeaUrl()
    {
        $id = $this->ctx->parameters->id->ideaId;
        $name = $this->ctx->parameters->strings->seoUrl;

	    assert($id > 0, "ideaId within valid range");

	    // don't trust supplied idea title, regenerate it every time
	    $title = $this->ctx->dal->fetchSingleValue("select seoUrlTitle from suggestions where id = :id", ["id" => $id]);

    }
}

class UrlHelper
{
	static function seoUrl($string)
	{
		//Unwanted:  {UPPERCASE} ; / ? : @ & = + $ , . ! ~ * ' ( )
		$string = strtolower($string);
		//Strip any unwanted characters
		$string = preg_replace('/[^a-z0-9_\s-]/', '', $string);
		//Clean multiple dashes or whitespaces
		$string = preg_replace('/[\s-]+/', ' ', $string);
		//Convert whitespaces and underscore to dash
		$string = preg_replace('/[\s_]/', '_', $string);

		// trim
		$string = substr($string, 0, 64);

		return $string;
	}

	static function encodeIdeaId($realId)
	{
		return base_convert($realId + 10000, 10, 36);
	}

	static function decodeTinyId($tinyId)
	{
		return base_convert($tinyId, 36, 10) - 10000;
	}

	static function createIdeaUrl($seoId, $seoTitle)
	{
		return '/i/' . $seoId . '/' . $seoTitle;
	}

	static function buildSuggestionUrlFromTinyId(Context $ctx, $tinyId)
	{
		$title = self::getUrlTitleFromUrlId($ctx, $tinyId);
		return '/i/' . $tinyId . '/' . $title;
	}

	static function compressId($rawId)
	{
		return base_convert($rawId + 10000, 10, 36);
	}

	static function decompressId($compressedId)
	{
		return base_convert($compressedId, 36, 10) - 10000;
	}

	static function createUserUrl($userRawId)
	{
		return '/u/' . self::compressId($userRawId);
	}

	static function createAppUrl($seoTitle)
	{
		return '/a/' . $seoTitle;
	}

    static function appUrl(Context $ctx, $appId)
    {
        $title = $ctx->dal->fetchSingleValue("select seoUrlTitle from projects where projectId = :id", ["id" => $appId]);
        return '/a/' . $title;
    }

    static function createAppNewIdeaUrl($seoTitle)
    {
        return '/a/' . $seoTitle . '/submit';
    }

	static function createSuggestionUrlManually($urlId, $urlTitle)
	{
		return '/i/' . $urlId . '/' . $urlTitle;
	}

	/**
	 * @param $ctx
	 * @param $suggestionId int raw suggestion id
	 * @return string url
	 */
	static function createSuggestionUrl($ctx, $suggestionId)
	{
		$tinyId = self::compressId($suggestionId);
		return self::buildSuggestionUrlFromTinyId($ctx, $tinyId);
	}

	static function createUrlEditIdea($ctx, $ideaId)
	{
		return self::createSuggestionUrl($ctx, $ideaId) . '/edit';
	}

	static function getUrlTitleFromUrlId(Context $ctx, $id)
	{
		$uuid = "ideaSeoUrlId:$id urlTitle";

		$value = $ctx->cache->Load($uuid);
		if(!$value) {
			$value = $ctx->dal->fetchSingleValue("select seoUrlTitle from suggestions where seoUrlId = :id", [":id" => $id], "seoUrlId->seoUrlTitle lookup");
			$ctx->cache->Save($uuid, $value, 600); // cache for 10 mins (or whatever the typical user session length is)
		}

		return $value;
	}

	static function invalidateUrlTitleFromUrlIdCache(Context $ctx, $id)
	{
		$uuid = "ideaSeoUrlId:$id urlTitle";
		$ctx->cache->invalidate($uuid);
	}
}