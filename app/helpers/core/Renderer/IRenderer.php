<?php namespace Bugvote\Core\Renderer;

interface IRenderer
{
	public function render($templateName, $templateData);
}