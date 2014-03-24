<?php namespace Bugvote\Core;

use Bugvote\Services\Context;

class UrlBuilder
{
	function seoUrl($string)
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

	function encodeIdeaId($realId)
	{
		return base_convert($realId + 10000, 10, 36);
	}

	function decodeTinyId($tinyId)
	{
		return base_convert($tinyId, 36, 10) - 10000;
	}

	function createIdeaUrl($seoId, $seoTitle)
	{
		return '/i/' . $seoId . '/' . $seoTitle;
	}

	function buildSuggestionUrlFromTinyId(Context $ctx, $tinyId)
	{
		$title = $this->getUrlTitleFromUrlId($ctx, $tinyId);
		return '/i/' . $tinyId . '/' . $title;
	}

	function compressId($rawId)
	{
		return base_convert($rawId + 10000, 10, 36);
	}

	function decompressId($compressedId)
	{
		return base_convert($compressedId, 36, 10) - 10000;
	}

	function createUserUrl($userRawId)
	{
		return '/u/' . $this->compressId($userRawId);
	}

	function viewApp($seoTitle)
	{
		return '/a/' . $seoTitle;
	}

	function appUrl(Context $ctx, $appId)
	{
		$title = $ctx->dal->fetchSingleValue("select seoUrlTitle from projects where projectId = :id", ["id" => $appId]);
		return '/a/' . $title;
	}

	function createAppNewIdeaUrl($seoTitle)
	{
		return '/a/' . $seoTitle . '/submit';
	}

	function viewBugManual($urlId, $urlTitle)
	{
		return '/i/' . $urlId . '/' . $urlTitle;
	}

	/**
	 * @param $ctx
	 * @param $suggestionId int raw suggestion id
	 * @return string url
	 */
	function createSuggestionUrl($ctx, $suggestionId)
	{
		$tinyId = $this->compressId($suggestionId);
		return $this->buildSuggestionUrlFromTinyId($ctx, $tinyId);
	}

	function createUrlEditIdea($ctx, $ideaId)
	{
		return $this->createSuggestionUrl($ctx, $ideaId) . '/edit';
	}

	function getUrlTitleFromUrlId(Context $ctx, $id)
	{
		$uuid = "ideaSeoUrlId:$id urlTitle";

		$value = $ctx->cache->Load($uuid);
		if(!$value) {
			$value = $ctx->dal->fetchSingleValue("select seoUrlTitle from suggestions where seoUrlId = :id", [":id" => $id], "seoUrlId->seoUrlTitle lookup");
			$ctx->cache->Save($uuid, $value, 600); // cache for 10 mins (or whatever the typical user session length is)
		}

		return $value;
	}

	function invalidateUrlTitleFromUrlIdCache(Context $ctx, $id)
	{
		$uuid = "ideaSeoUrlId:$id urlTitle";
		$ctx->cache->invalidate($uuid);
	}
}
