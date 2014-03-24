<?php namespace Bugvote\DataModels;

class HottestIdeasDataModel extends ContextDataModel
{
	function getBugs($appId, $userId)
	{
		$bugs = $this->ctx->dal->fetchMultipleObjs('
            SELECT
            	s.suggestionId, s.title, s.suggestionTypeId, s.seoUrlId, s.seoUrlTitle, s.postedAt,
            	o.fullName AS creatorName, oa.assetId AS posterImgId, oa.originalFilename as posterImgFilename,
            	coalesce(v.votes,0) AS votes,
            	coalesce(mv.myvote,0) AS myvote,
            	coalesce(c.comments,0) AS comments,
            	coalesce(mc.mycomments,0) AS mycomments
            FROM
            	suggestions s
            		LEFT JOIN (SELECT sum(vote) AS votes, suggestionId FROM suggestionVotes GROUP BY suggestionId) AS v ON (v.suggestionId = s.suggestionId)
            		LEFT JOIN (SELECT vote AS myvote, userId, suggestionId FROM suggestionVotes where userId = :userId) AS mv ON (mv.suggestionId = s.suggestionId)

            		LEFT JOIN (SELECT count(commentId) AS comments, suggestionId FROM suggestionComments GROUP BY suggestionId) AS c ON (c.suggestionId = s.suggestionId)
            		LEFT JOIN (SELECT count(commentId) AS mycomments, suggestionId FROM suggestionComments GROUP BY suggestionId) AS mc ON (c.suggestionId = s.suggestionId AND mv.userId = :userId)

            		LEFT JOIN users o ON (o.userId = s.userId)
            		left join assets oa on (o.profileMediumAssetId = oa.assetId)
            WHERE
            	appId = :appId
            GROUP BY
            	s.suggestionId
            ORDER BY
            	votes DESC,
            	s.postedAt DESC
            ',
			[':appId' => $appId, ':userId' => $userId],
			"hottest bugs"
		);

		return $bugs;
	}
}