<?php

namespace Bugvote\Commons;

interface IUserSessionProvider
{
    function getUserId();
    function getRole();
}