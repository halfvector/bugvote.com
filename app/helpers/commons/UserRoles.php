<?php

namespace Bugvote\Commons;

// permissions mask (and-test)
// 0000 0000 = none (anonymous)
// 0000 0001 = user
// 1000 0010 = moderator
// 1000 0100 = developer
// 1000 1000 = admin

// or

// privilege lists (in-set)
// 0x00     = none (anonymous)
// 0x01     = user
// 0x11     = privileged user (owns bug-report or comment)
// 0x10     = moderator
// 0x12     = developer
// 0x14     = admin


abstract class UserRoles
{
    const Anonymous = 0x00;
	const Regular = 0x01;
	const Moderator = 0x10;
	const Developer = 0x12;
	const Admin = 0x14;

	static function HasRole($mask, $role)
	{
		return ($mask & $role) == $role;
	}
}
