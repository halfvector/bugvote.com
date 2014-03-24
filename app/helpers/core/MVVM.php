<?php

namespace Bugvote\Core;

class MVVM
{
	/**
	 * @param \_Request $request
	 * @param \_Response $response
	 */
	public static function View($request, $response)
	{
		dump($request->controller);
		dump($request->view);
		dump($request->section);
	}
}
