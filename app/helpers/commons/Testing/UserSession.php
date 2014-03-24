<?php

namespace Bugvote\Commons\Testing;

use Bugvote\Commons\IUserSessionProvider;

class UserSession implements IUserSessionProvider
{
    function getUserId()
    {
        return 15;
    }

    function getRole()
    {

    }
}