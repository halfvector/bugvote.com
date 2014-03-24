<?php namespace Bugvote\Core\API;

use Bugvote\Core\DAL;

trait Ideas
{
	function hottestIdeas(DAL $dal, $appId, $userId)
	{
		// join suggestionRevisions r on (r.suggestionId = s.suggestionId) and (r.revisionId = (select max(revisionId) from suggestionRevisions where suggestionId = s.suggestionId))

		// suggestionRevisions is now a historical table
		// the latest data will always be in suggestions

		return $dal->fetchMultipleObjs('
            select
            	s.suggestionId, s.title, s.suggestionTypeId,
            	o.fullName as creatorName, o.profileMediumAssetId as creatorImgId,
            	coalesce(v.votes,0) as votes,
            	coalesce(mv.myvote,0) as myvote,
            	coalesce(c.comments,0) as comments,
            	coalesce(mc.mycomments,0) as mycomments,
            	unix_timestamp(s.postedAt) as postedAt,
            	(unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt)) as createdAgeSec
            from
            	suggestions s
            		left join (select sum(vote) as votes, suggestionId from suggestionVotes group by suggestionId) as v on (v.suggestionId = s.suggestionId)
            		left join (select vote as myvote, userId, suggestionId from suggestionVotes group by suggestionId) as mv on (mv.suggestionId = s.suggestionId and mv.userId = :userId)

            		left join (select count(commentId) as comments, suggestionId from suggestionComments group by suggestionId) as c on (c.suggestionId = s.suggestionId)
            		left join (select count(commentId) as mycomments, suggestionId from suggestionComments group by suggestionId) as mc on (c.suggestionId = s.suggestionId and mv.userId = :userId)

            		left join users o on (o.userId = s.userId)
            where
            	appId = :appId
            group by
            	s.suggestionId
            order by
            	votes desc,
            	createdAgeSec asc
            ', [':appId' => $appId, ':userId' => $userId], "hottest ideas"
		);
	}

	function newestIdeas(DAL $dal, $appId, $userId)
	{
		return $dal->fetchMultipleObjs('
            select
            	s.suggestionId, s.title, s.suggestionTypeId,
            	o.fullName as creatorName, o.profileMediumAssetId as creatorImgId,
            	coalesce(v.votes,0) as votes,
            	coalesce(mv.myvote,0) as myvote,
            	coalesce(c.comments,0) as comments,
            	coalesce(mc.mycomments,0) as mycomments,
            	unix_timestamp(s.postedAt) as postedAt,
            	(unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt)) as createdAgeSec
            from
            	suggestions s
            		left join (select sum(vote) as votes, suggestionId from suggestionVotes group by suggestionId) as v on (v.suggestionId = s.suggestionId)
            		left join (select vote as myvote, userId, suggestionId from suggestionVotes group by suggestionId) as mv on (mv.suggestionId = s.suggestionId and mv.userId = :userId)

            		left join (select count(commentId) as comments, suggestionId from suggestionComments group by suggestionId) as c on (c.suggestionId = s.suggestionId)
            		left join (select count(commentId) as mycomments, suggestionId from suggestionComments group by suggestionId) as mc on (c.suggestionId = s.suggestionId and mv.userId = :userId)

            		left join users o on (o.userId = s.userId)
            where
            	appId = :appId
            group by
            	s.suggestionId
            order by
            	createdAgeSec asc
            ', [':appId' => $appId, ':userId' => $userId], "newest ideas"
		);
	}
}