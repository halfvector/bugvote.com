<?php

namespace Bugvote\Commons;

// implement this interface to give a data-model easy scaffolding
// so i have to create the database tables, API entries, and data-models
// the scaffolding will create the view and view-models to interact through
interface IScaffold
{
	function getList();
	function getItem($id);
	function updateItem();
	function createItem();

	function getIdColumnName();
	function getCurrentItem();
	function getTableName();
}