<?php

namespace Bugvote\Commons;

# a CRUD interface for easy scaffolding
use Bugvote\Services\Context;

interface IScaffoldDataModel
{
    function index(Context $context);
    function show(Context $context);
    function design(Context $context);
    function create(Context $context);
    function edit(Context $context);
    function update(Context $context);
    function confirm(Context $context);
    function destroy(Context $context);
}
