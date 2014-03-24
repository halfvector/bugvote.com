<?php namespace Bugvote\Core\Renderer;

use Bugvote\Core\Logging\AppPerformanceLog;
use Bugvote\Core\Logging\ILogger;
use Bugvote\Services\Context;
use DOMDocument;
use DOMNode;
use DOMXPath;
use ErrorException;
use Exception;
use InvalidArgumentException;

define('BUGVOTE_XAML_DEBUGGING', true);
define('BUGVOTE_XAML_DUMP_GENERATED_CODE', false);
define('BUGVOTE_XAML_FORCE_REBUILD', false);

class XamlParameter
{
	function __construct($name, $value, $isBinding)
	{
		$this->name = $name;
		$this->value = $value;
		$this->isBinding = $isBinding;
	}

	public $name, $value, $isBinding;
}

class LeXamlRenderer
{
	protected $namespaces;
	protected $folder;
	/** @var DOMDocument $document */
	protected $document;
	protected $xmlPreload;
	protected $mainFilename;

	public $dependencies = [];

	public static $Beautify = false;

	/** @var  Context */
	protected $ctx;
	/** @var  AppPerformanceLog */
	protected $perf;
	/** @var  ILogger */
	protected $log;

	function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
		$this->perf = $ctx->perf;
		$this->log = $ctx->log;
	}

	/**
	 * the node may contain Lexy attributes or be a Lexy node
	 * @param DOMNode $node
	 * @return DOMNode
	 */
	protected function ProcessNodeWithLexy($node)
	{
		$p = $this->perf->start("Node processing: " . $node->getNodePath());

		if( $node->nodeType == XML_ATTRIBUTE_NODE )
		{	// its a lexy-attribute, do something with it
			$attribute = $node;

			switch( $attribute->name )
			{
				case "context":
					$node = $this->ApplyContextAttribute($attribute);
					break;
			}
		}
		else if( $node->nodeType == XML_ELEMENT_NODE )
		{	// its a lexy-node

			$p1 = $p->fork("Compiling Special Node '{$node->nodeName}'");

			switch( $node->localName )
			{
				case "manyItems":
					$this->CompileItemsController($node);
					break;
				case "itemTemplate":
					$this->CompileItemTemplateInstance($node);
					break;
				case "template":
					$this->CompileItemTemplate($node);
					break;
				case "if":
					$this->CompileIfStatement($node);
					break;
				case "csrf":
					$this->CompileCSRF($node);
					break;
				case "header":
					$this->CompileHeader($node);
					break;
				case "css":
					$this->CompileCssLink($node);
					break;
				case "contentPresenter":
					$this->ProcessContentPresenter($node);
					break;
			}

			$p1->stop();
		}

		$p->stop();

		return $node;
	}

	/**
	 * an entry-point used to render COMPLETE pages. don't use this to render a partial
	 * @param string $templatePath absolute path
	 * @param $data
	 * @throws InvalidArgumentException
	 */
	public function Execute($templatePath, $data)
	{
		// execute() is only called on real files, so realpath() is allowed here
		$templatePath = realpath($templatePath);
		if( ! $templatePath ) {
			throw new InvalidArgumentException("templatePath must be a valid absolute path; templatePath=[$templatePath]");
		}

		//$localPath = TemplateSettings::GetRelativePath($templatePath);
		$filename = basename($templatePath);

		$p = $this->perf->start("Executing Template: $filename");

		$this->log->write("Executing template: $filename");

		//if( \Debugging::rebuildTemplates() )
		if( BUGVOTE_XAML_FORCE_REBUILD )
		{	// always rebuild templates
			$cacheValid = false;
		} else
		{	// caching is enabled, so only invalidate if we have to.
			$cacheValid = $this->IsTemplateCacheValid($templatePath);
		}

		if( ! $cacheValid )
		{
			$p1 = $p->fork("Template Cache rebuilding");
			if( ! $this->CompileTemplateFromFile($templatePath, false) )
			{	// template compilation failed
				$this->log->write("ERROR: Template could not be compiled: [$templatePath]");
			}
			$p1->stop();
		}

		// don't apply template when logging (sometimes easier to diagnose this way)
		//if( !\Debugging::isLogWanted() )
		$this->ApplyTemplateByName($templatePath, $data);

		$p->stop();
	}

	/**
	 * @param $file
	 * @return bool True on template is valid, False on template is invalid
	 */
	public function IsTemplateCacheValid($file)
	{
		// TODO: logging here slows down template-cache-validation to 3x longer than instantiation (0.3ms)

		// can use APC to store a "recently-validated" token that only allows revalidation every 10-seconds or something
		// a bit of sugar for high-load scenarios (to shave off.. just 200us)

		$compiledFile = TemplateCacheMaster::GetCompiledTemplatePath($file);
		$dependenciesFile = TemplateCacheMaster::GetDependenciesPath($file);

		if( !file_exists($compiledFile) || ! file_exists($dependenciesFile) )
			return false;

		/** @var \TemplateDependencies $dependenciesDescriptor  */
		$dependenciesDescriptor = json_decode(CAL::Load($dependenciesFile));

		$compiledTemplateTime = filemtime($compiledFile);

		$p = $this->perf->start("Template Cache validation");

		//$this->log->write("Template Compiled on '" . date("F d Y H:i:s", $compiledTemplateTime) . "'");
		foreach($dependenciesDescriptor->dependencies as $dependency)
		{
			// make sure dependency exists
			if( ! file_exists($dependency) )
			{	// well that's a show stopper. maybe file was renamed?
				$this->log->write("ERROR: Template ($file) is missing a dependency: $dependency");
				$p->stop();
				return false;
			}

			// get time of last modification
			$lastmodified = filemtime($dependency);
			$dependencyAge = $compiledTemplateTime - $lastmodified;

			//$this->log->write("  Dependecy $dependency last modified '" . date("F d Y H:i:s", $lastmodified) . "' Age diff: $dependencyAge seconds");

			// allow a minor offset for hysteresis
			if( $dependencyAge < 0 )
			{	// a dependency is newer than the compiled template!
				$this->log->write("    -> Dependency has been modified since last template compilation! Invalidating template..");
				$p->stop();
				return false;
			}
		}

		//$this->log->write("  Template Cache still valid.");
		$p->stop();

		return true;
	}

	// the path must be absolute
	// but it may point to a virtual file, so realpath() won't work here
	public function ApplyTemplateByName($absPath, $data = false, $beautify = true)
	{
		$p = $this->perf->start("Applying Template: " . TemplateSettings::GetRelativePath($absPath));

		// ensure it's compiled

		$compiledCache = TemplateCacheMaster::GetCompiledTemplatePath($absPath);

		//$this->log->write("Cache Path: $compiledCache");

		$html = "";

		//if( ! \Debugging::isLogWanted() )
		if(!BUGVOTE_XAML_DUMP_GENERATED_CODE)
		{
			try
			{
				// buffer result, dump if bad?
				ob_start();

				// the $data property is used by the precompiled header included here

				// letting APC's op-code cache take-over is 2x faster
				require $compiledCache;

				// than manually pulling cached-html and executing it without op-code cache
				//$templateData = CAL::Load($compiledCache);
				//include("data://text/plain," . $templateData);

				//ob_end_flush();
				//ob_end_flush();
				$html = ob_get_clean();
			}
			catch(Exception $e)
			{
				$partial = ob_get_clean();

				$msg = $e->getMessage();

				var_dump($msg);

				if(preg_match("/Undefined property: (.*)/", $msg, $propertyMatch) && strstr($e->getTraceAsString(),$compiledCache))
				{	// an undefined property. is it in a template?
					$relativeTemplatePath = TemplateSettings::GetRelativePath($absPath);

					$undefinedProperty = $propertyMatch[1];

					echo <<<EOT
	<div>
		Undefined Property: $undefinedProperty in template $relativeTemplatePath
	</div>
EOT;
				} else
				{
					echo "<pre>";
					//Log::NiceEcho( "[<a href='#' title='$escaped'>Template Error</a>]\n");
					echo "Error Importing Template: $absPath\n";
					echo $e->getFile() . ":" . $e->getLine() . " " . $e->getMessage() . " (code:".$e->getCode().")\n";
					echo "\n-------------------------------------------------------------------------------------------------\n\n";
					echo $e->getTraceAsString();
					echo "\n-------------------------------------------------------------------------------------------------\n\n";
					echo "Partially generated HTML:\n";
					echo "\n-------------------------------------------------------------------------------------------------\n\n";
					echo htmlspecialchars($partial);
					echo  "</pre>";
				}

				// should template cache be invalidated on error?
			}
		}
		else
		{
			$this->log->write("Template cache: " . TemplateSettings::GetRelativePath($absPath));// && $log->raw(htmlspecialchars(CAL::Load($compiledCache))) && $perf->mark("Logged HTML");
			$this->log->write("Template execution: " . TemplateSettings::GetRelativePath($compiledCache));

			ob_start();
			include $compiledCache;
			$html = ob_get_clean();

			//$log->neat("Generated HTML:") && $log->raw(htmlspecialchars($html)) && $perf->mark("Logged HTML");
		}

		if( self::$Beautify )
		{
			$html = self::BeautifyHTML($html, $p);
		}

		//if( ! \Debugging::isLogWanted() )
		//if(BUGVOTE_XAML_DUMP_GENERATED_CODE)
			echo $html;

		$p->stop();
	}

	/**
	 * Always compiles a template, regardless if existing cache is still valid.
	 * To use cache, should run cache-validity-check before calling this method.
	 * @param $file
	 * @param bool $partial indicates a partial template and not an entire page
	 * @return CompiledTemplate
	 */
	protected function CompileTemplateFromFile($file, $partial = false)
	{
		$p = $this->perf->start("Template compilation: $file");

		$this->mainFilename = $file;
		$this->folder = dirname($file);
		$this->dependencies []= $file;

		if( ! file_exists($file) )
		{	// can't compile a file that doesn't exist
			$this->log->write("Cannot compile template because file not found: [$file]");
			$p->stop();
			return false;
		}

		$templateHTML = CAL::Load($file);

		//var_dump("file: $file");
		//var_dump($templateHTML);

		// compile template-html down to php+html
		$html = $this->CompileTemplate($templateHTML, $file, $partial);

		$compiledTemplate = new CompiledTemplate($this->ctx, $file, $html, $this->dependencies);
		$compiledTemplate->SaveInCache();

		$p->stop();

		return $compiledTemplate;
	}

	/**
	 * @param DOMAttr $attribute
	 * @return DOMNode
	 */
	protected function ApplyContextAttribute($attribute)
	{
		$perf = PerformanceLog::start("Compiling Special Attribute '{$attribute->nodeName}'");

		$node = $attribute->ownerElement;

		// grab the new DataContext path
		$newContextRaw = $node->attributes->getNamedItem("context")->nodeValue;

		// extract the value of the path
		preg_match('/{binding:(\w+)}/', $newContextRaw, $matches);

		$newContext = $matches[1];

		$pushContext = <<<EOT

\Bugvote\Core\Renderer\DataContext::push(\$data);
\$data = \Bugvote\Core\Renderer\DataContext::resolvePath(\$data, "$newContext");

EOT;
		$popContext = <<<EOT

\$data = \Bugvote\Core\Renderer\DataContext::pop();

EOT;

		$dataContextPush = $this->document->createCDATASection("<?php\n" . $pushContext . '?>');
		$dataContextPop = $this->document->createCDATASection("<?php\n" . $popContext . '?>');

		$node->removeAttributeNode($node->attributes->getNamedItem("context"));

		assert($node != NULL);
		assert($node->parentNode != NULL);

		$perf->mark("Block creation");

		// surround node with php nodes

		$this->QueueUpNodeTransaction([ "action" => "insertBefore", "node" => $dataContextPush, "before" => $node, "parent" => $node->parentNode ]);

		// try to find the next-sibling to this node so we can perform an "insertAfter" using the insertBefore
		// otherwise there is no next-node, so just do an appendChild on the parent node
		if( $node->nextSibling != NULL )
			$this->QueueUpNodeTransaction([ "action" => "insertBefore", "node" => $dataContextPop, "before" => $node->nextSibling, "parent" => $node->parentNode ]);
		else
			$this->QueueUpNodeTransaction([ "action" => "add", "node" => $dataContextPop, "parent" => $node->parentNode ]);

		$perf->save();

		return $node;
	}

	/**
	 * only used on partials
	 * @param \DOMNode node
	 * @return string html
	 */
	protected function PostProcessTemplate($node)
	{
		$p = $this->perf->start("Node -> DOMDoc -> HTML ($node->nodeName)");
		$p1 = $p->fork("DOMDocument creation");
		$this->log->write("Post-Porcessing Template: $node->nodeName");

		$doc = new DOMDocument();
		$doc->formatOutput = false;
		$doc->preserveWhiteSpace = true;
		//$doc->recover = true;

		$p1->next("XML Node import");

		// xml based fragment import: 200us (total)
		// node based import: 70us (total)

		$node = $doc->importNode($node, true);
		$doc->appendChild($node);

		$p1->next("Post-Process");

		$html = $this->FinishProcessingTemplate($doc, true);

		$p1->stop();
		$p->stop();

		return $html;
	}

	protected function PostProcessTemplateFragment(DOMNode $node)
	{
		$p = $this->perf->start("Post-Processing Template ($node->nodeName)");
		$p1 = $p->fork("DOMDocument Fragment creation");
		$this->log->write("Post-Porcessing Template Fragment: $node->nodeName");

		$doc = new DOMDocument();
		$doc->formatOutput = false;
		$doc->preserveWhiteSpace = true;
		//$doc->recover = true;

		$p1->next("XML Node import");

		$fragment = $doc->createDocumentFragment();

		//$node->appendChild($attr);

		//$xml = $node->ownerDocument->saveXML($node);

		//$xml = preg_replace('/\s?xmlns:lexy.*lexy\"/', '', $xml);

		//$fragment->appendXML($xml);

		foreach($node->childNodes as $child)
		{
			$child = $doc->importNode($child, true);
			$fragment->appendChild($child);
		}

		$doc->appendChild($fragment);

		$p1->next("Post-Process");

		$html = $this->FinishProcessingTemplate($doc, true);

		$p1->stop();
		$p->stop();

		return $html;
	}

	/**
	 * @param \DOMDocument $doc
	 * @param bool $partial is true when the document is a child-template that shouldn't contain the entire html-body
	 * @return mixed
	 */
	protected function FinishProcessingTemplate($doc, $partial = false)
	{
		$p = $this->perf->start("Block preparation");

		// all of the block replacement is pretty cheap (40us)

		$doc->substituteEntities = false;

		// this is the right way to grab code without unwanted escaping/encoding
		// strips doctype, and comments, and closes all tags even if they are empty
		// eg: src="{binding:img}" is fine this way
		//$html = $doc->C14N(true);

		// returns an unnecessary XML tag, which we can strip easily
		// retains doctype, extra namespace data, and comments
		// closes all empty tags (link, meta)
		// but unfortunately:
		//     closes empty <i> tags that bootstrap uses
		//     closes <script> tags which is invalid and screws up browsers
		//$html = $doc->saveXML();
		//$html = preg_replace('/\<\?xml version="1.0"\?\>\s+/', '', $html, 1);

		$p->next("XHTML export");

		// escapes too many tokens, but i can fix that..
		$html = $doc->saveHTML();

		// escapes {binding} within quotes (breaking things like src="{binding:img}")
		// retains doctype, comments, extra namespace info
		// but is non-xml: leaves tags open (which tidy can repair just fine, turning it into XHTML easily)
		//$html = $doc->saveHTML();

		// lazy-eval for the win! 0.20ms vs 0.03ms
		// the empty function call turns into a nop and prevents the trailing htmlspecialchars() from ever evaluating
		//$log->neat("DOMDocument HTML:") && $log->raw(htmlspecialchars($html)) && $perf->mark("Logged HTML");

		if( self::$Beautify )
		{
			$html = self::BeautifyHTML($html, $p, $partial);
		}


		$p->next("HTML Fixup");

		$this->log->write("Expanding bindings..");

		// correct bindings
		// together these two are 5x faster than the single php untabbing below..
		$html = preg_replace_callback('/{binding:(.*(?:&gt\;).*)}/',
			function($m) {
				return html_entity_decode($m[0]);
			}, $html
		);

		$html = preg_replace_callback('/\"%7B(binding.*)%7D\"/',
			function($m) {
				return urldecode($m[0]);
			}, $html
		);

		$html = preg_replace_callback('/%7B%7B(.*)%7D%7D/',
			function($m) {
				return urldecode($m[0]);
			}, $html
		);

		// wipe out the spaces before php tags so php code is neatly laid against the start of each line
		// this is expensive..
		// 0.5ms just for this. two above run in 0.1ms
		//$html = preg_replace("/[ |\t]+\<php\>/", '<php>', $html);

		$p->next("Binding expansion");

		//$log->neat("Compiled Tidy HTML:") && $log->raw(htmlspecialchars($html)) && $perf->mark("Logged HTML");

		// expand bindings
		// very cheap (20us)
		$html = preg_replace('/{binding:this}/', '<?= \$data ?>', $html);
		/*$html = preg_replace('/{binding:([^}]+)}/', '<?= \$data->$1 ?>', $html);*/

		// mustache-style bindings
		/*
		$html = preg_replace('/{{(\w[^}]+)}}/', '<?= \$data->$1 ?>', $html);
		$html = preg_replace('/{{{(\w[^}]+)}}}/', '<?= \$data->$1 ?>', $html);
		*/

		// matches <img src="{{#userStatus.urlUserProfileImage}}34x34:cover{{/userStatus.urlUserProfileImage}}" />
		// doesn't have to do the temp-local-var trick below for __invoke to work!
		$html = preg_replace_callback('/{{#(\w[^}]+)}}(.+){{\/.*}}?/',
			function($m) {
				$method = str_replace(".", "->", $m[1]);
				$args = $m[2];

				return "<?= call_user_func(\$data->$method, '$args') ?>";
			}, $html
		);

		// match <img src="{binding:userStatus.urlUserProfileImage('34x34:cover')}" />
		// uses a temp local variable to call __invoke
		$html = preg_replace_callback('/{binding:([^}]+)}/',
			function($m) {
				$nicePath = str_replace(".", "->", $m[1]);

				if(strstr($nicePath, "("))
				{   // a method call, do a local instance copy trick to ensure __invoke gets called in objects
					$parts = explode("(", $nicePath);
					$caller = "\$__p = \$data->$parts[0]; echo \$__p($parts[1]";


					return "<?php { $caller; } ?>";
				}

				return '<?= $data->' . $nicePath . '; ?>';
			}, $html
		);

		// expands raw (unescaped) {{{obj.variable}}}
		$html = preg_replace_callback('/{{{?(\w[^}]+)}}}?/',
			function($m) {
				return '<?= $data->' . str_replace(".", "->", $m[1]) . ' ?>';
			}, $html
		);

		// cheap conditionals
		$html = preg_replace('/{if:([^?]+)\?(.+):(.+)}/', '<?= (\$data->$1) ? $2 : $3 ?>', $html);

		// remove lexy namespace declarations
		$html = preg_replace('/\s?xmlns:lexy.*lexy\"/', '', $html);

		//$log->neat("Final Expanded HTML:") && $log->raw(htmlspecialchars($html)) && $perf->mark("Logged HTML");

		//$this->log->writeObject("Finished HTML", "<pre>" . htmlspecialchars($html) . "</pre>");

		//$perf->bytes = strlen($html);
		$p->stop();

		return $html;
	}

	protected static function BeautifyHTML($html, $p, $partial = true)
	{
		if(true)
		{
			$p->next("HTML Beautification");
			//$tidy = new tidy();
			//$tidy->parseString($html, array('new-blocklevel-tags' => '?php,?', 'indent' => TRUE, 'force-output' => true, 'show-body-only' => TRUE, 'input-xml' => TRUE, 'output-xml' => TRUE, 'wrap' => 200), 'UTF8');
			//$html = "" . $tidy;

			// expensive and optional (80us)
			//$html = "" . tidy_parse_string($html, array('add-xml-space' => TRUE, 'preserve-entities' => TRUE, 'indent' => TRUE, 'force-output' => true, 'show-body-only' => TRUE, 'input-xml' => TRUE, 'output-xhtml' => TRUE, 'wrap' => 500, 'wrap-php' => 0), 'UTF8');
			$tidy_config = array(
				//'new-pre-tags' => 'php',
				//'new-empty-tags' => 'img',
				//'new-blocklevel-tags' => 'meta,script',
				//'add-xml-space' => TRUE,
				'preserve-entities' => true,
				'indent' => true,
				//'force-output' => true,
				//'show-body-only' => $partial, // good for partials (but only if they are inside the body tag..)
				//'input-xml' => true,
				'output-html' => true,
				//'output-xml' => true,
				'wrap' => 200,
				'hide-endtags' => true,
				'quote-ampersand' => false,
				//'new-blocklevel-tags' => 'i',
				//'wrap-php' => 0,
				'drop-empty-paras' => false,
				//'new-inline-tags' => 'i',
				'quote-marks' => false,
				//'markup' => false,
				'tab-size' => 4,
				'input-encoding' => 'utf8',
				'output-encoding' => 'utf8',
				'fix-uri' => false,
				'fix-backslash' => false,
			);

			$tidy = tidy_parse_string($html, $tidy_config, 'UTF8');
			//tidy_clean_repair($tidy);
			$html = tidy_get_output($tidy);
			//$html = preg_replace('/([ ]+)<\/(\w+)>(?!\s)/', "$1</$2>\n$1", $html);
		}

		if(false)
		{
			$p->next("HTML Beautification");
			// this thing adds xml tag and closes script tags improperly
			// but maintains important whitespace better than tidy
			$html = self::xmlpp($html, false);
			$html = str_replace('<?xml version="1.0"?>', '', $html);
			$html = preg_replace('/(<script.*)\/>/', '$1></script>', $html);
		}

		return $html;
	}

	protected static function xmlpp($xml, $html_output=false) {
		$xml_obj = new SimpleXMLElement($xml);
		$level = 4;
		$indent = 0; // current indentation level
		$pretty = array();

		// get an array containing each XML element
		$xml = explode("\n", preg_replace('/>\s*</', ">\n<", $xml_obj->asXML()));

		// shift off opening XML tag if present
		if (count($xml) && preg_match('/^<\?\s*xml/', $xml[0])) {
			$pretty[] = array_shift($xml);
		}

		foreach ($xml as $el) {
			if (preg_match('/^<([\w])+[^>\/]*>$/U', $el)) {
				// opening tag, increase indent
				$pretty[] = str_repeat(' ', $indent) . $el;
				$indent += $level;
			} else {
				if (preg_match('/^<\/.+>$/', $el)) {
					$indent -= $level;  // closing tag, decrease indent
				}
				if ($indent < 0) {
					$indent += $level;
				}
				$pretty[] = str_repeat(' ', $indent) . $el;
			}
		}
		$xml = implode("\n", $pretty);
		return ($html_output) ? htmlentities($xml) : $xml;
	}

	static $HtmlCommonEntities = array(
		'&nbsp;'     => '&#160;',  # no-break space = non-breaking space, U+00A0 ISOnum
		'&iexcl;'    => '&#161;',  # inverted exclamation mark, U+00A1 ISOnum
		'&cent;'     => '&#162;',  # cent sign, U+00A2 ISOnum
		'&pound;'    => '&#163;',  # pound sign, U+00A3 ISOnum
		'&curren;'   => '&#164;',  # currency sign, U+00A4 ISOnum
		'&yen;'      => '&#165;',  # yen sign = yuan sign, U+00A5 ISOnum
		'&brvbar;'   => '&#166;',  # broken bar = broken vertical bar, U+00A6 ISOnum
		'&sect;'     => '&#167;',  # section sign, U+00A7 ISOnum
		'&uml;'      => '&#168;',  # diaeresis = spacing diaeresis, U+00A8 ISOdia
		'&copy;'     => '&#169;',  # copyright sign, U+00A9 ISOnum
		'&ordf;'     => '&#170;',  # feminine ordinal indicator, U+00AA ISOnum
		'&laquo;'    => '&#171;',  # left-pointing double angle quotation mark = left pointing guillemet, U+00AB ISOnum
		'&not;'      => '&#172;',  # not sign, U+00AC ISOnum
		'&shy;'      => '&#173;',  # soft hyphen = discretionary hyphen, U+00AD ISOnum
		'&reg;'      => '&#174;',  # registered sign = registered trade mark sign, U+00AE ISOnum
		'&macr;'     => '&#175;',  # macron = spacing macron = overline = APL overbar, U+00AF ISOdia
		'&deg;'      => '&#176;',  # degree sign, U+00B0 ISOnum
		'&plusmn;'   => '&#177;',  # plus-minus sign = plus-or-minus sign, U+00B1 ISOnum
		'&sup2;'     => '&#178;',  # superscript two = superscript digit two = squared, U+00B2 ISOnum
		'&sup3;'     => '&#179;',  # superscript three = superscript digit three = cubed, U+00B3 ISOnum
		'&acute;'    => '&#180;',  # acute accent = spacing acute, U+00B4 ISOdia
		'&micro;'    => '&#181;',  # micro sign, U+00B5 ISOnum
		'&para;'     => '&#182;',  # pilcrow sign = paragraph sign, U+00B6 ISOnum
		'&middot;'   => '&#183;',  # middle dot = Georgian comma = Greek middle dot, U+00B7 ISOnum
		'&cedil;'    => '&#184;',  # cedilla = spacing cedilla, U+00B8 ISOdia
		'&sup1;'     => '&#185;',  # superscript one = superscript digit one, U+00B9 ISOnum
		'&ordm;'     => '&#186;',  # masculine ordinal indicator, U+00BA ISOnum
		'&raquo;'    => '&#187;',  # right-pointing double angle quotation mark = right pointing guillemet, U+00BB ISOnum
		'&frac14;'   => '&#188;',  # vulgar fraction one quarter = fraction one quarter, U+00BC ISOnum
		'&frac12;'   => '&#189;',  # vulgar fraction one half = fraction one half, U+00BD ISOnum
		'&frac34;'   => '&#190;',  # vulgar fraction three quarters = fraction three quarters, U+00BE ISOnum
		'&iquest;'   => '&#191;',  # inverted question mark = turned question mark, U+00BF ISOnum
		'&Agrave;'   => '&#192;',  # latin capital letter A with grave = latin capital letter A grave, U+00C0 ISOlat1
		'&Aacute;'   => '&#193;',  # latin capital letter A with acute, U+00C1 ISOlat1
		'&Acirc;'    => '&#194;',  # latin capital letter A with circumflex, U+00C2 ISOlat1
		'&Atilde;'   => '&#195;',  # latin capital letter A with tilde, U+00C3 ISOlat1
		'&spades;'   => '&#9824;', # black spade suit, U+2660 ISOpub
		'&clubs;'    => '&#9827;', # black club suit = shamrock, U+2663 ISOpub
		'&hearts;'   => '&#9829;', # black heart suit = valentine, U+2665 ISOpub
		'&diams;'    => '&#9830;', # black diamond suit, U+2666 ISOpub
		'&quot;'     => '&#34;',   # quotation mark = APL quote, U+0022 ISOnum
		'&amp;'      => '&#38;',   # ampersand, U+0026 ISOnum
		'&lt;'       => '&#60;',   # less-than sign, U+003C ISOnum
		'&gt;'       => '&#62;',   # greater-than sign, U+003E ISOnum
	);

	public static function EscapeCommonHtmlEntities($str)
	{
		return preg_replace_callback("/&(\w)+;/", function($m) { return LeXamlRenderer::$HtmlCommonEntities[$m[0]]; }, $str);
	}

	/**
	 * compilation has three main stages:
	 * 1 - processing nodes while ascending the DOM tree, creating action queues
	 * 2 - finalizing nodes through action queues
	 * 3 - expanding php and template blocks and bindings
	 *
	 * compiles the template (and all child templates)
	 *
	 * @param string $templateHTML
	 * @param string $filePath
	 * @param bool $partial indicates a partial template which must be beautified as such
	 * @return CompiledTemplate
	 */
	protected function CompileTemplate($templateHTML, $filePath, $partial = false)
	{
		//Log::NiceEcho( "compiling template for: $filePath\n");

		//gc_collect_cycles();
		//gc_disable();

		// no real diff in spawning times
		// simplexml_load_string takes 0.15ms
		// DOMDocument->loadXML takes 0.15ms

		// builtin scanner is ~5x faster than xpath.
		// getElementsByTagNameNS takes 0.01ms
		// xpath->query("//lexy:*") takes 0.05ms
		// xpath->query("//lexy:* | //*[@lexy:context and not(php)] takes 0.13ms
		// wish there was a getElementsByAttributeNameNS

		$parent = $this->perf->start("Template compilation");
		$p = $parent->fork("XHTML import");

		$this->log->write("Compiling template: $filePath");

		///////
		// spawning a document fragment is expensive. 60us for simple template to 230us for a real template

		//libxml_use_internal_errors(true);

		$this->mainFilename = $filePath;
		$doc = new DOMDocument();

		//$doc = new DOMDocument('1.0', 'iso-8859-1');
		//$doc->formatOutput = false;
		//$doc->preserveWhiteSpace = true;
		//$doc->recover = true;
		//$doc->strictErrorChecking = false;

		//$doctype = $doc->implementation->createDocumentType("html","-//W3C//DTD XHTML 1.1//EN","http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd");
		//$doc->appendChild($doctype);

		// convert html entities into standard xml compatible form
		// eg: &copy; -> &#169; &quot; -> &#34;

		//$templateHTML = str_replace("&copy;", "&#169;", $templateHTML);
		//$templateHTML = str_replace("&copy;", "&#169;", $templateHTML);

		//$templateHTML = html_entity_decode($templateHTML, ENT_QUOTES, 'UTF-8');
		//$templateHTML = htmlentities($templateHTML, ENT_QUOTES, 'UTF-8');
		//$templateHTML = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $templateHTML);

		// could also declare a DTD with a defined list of entities to make loadXML() happy
		// but its only 10% faster than doing this.. specially since the entities aren't very common
		$templateHTML = self::EscapeCommonHtmlEntities($templateHTML);

		// decodes html named entities like &copy; and saves the whole thing as utf-8
		// apparently that's the preferred way to do it with html5?
		//$templateHTML = html_entity_decode($templateHTML);
		//$templateHTML = utf8_encode($templateHTML);

		//$doc->standalone = true;
		//$doc->xmlStandalone = true;

		// loadXML is 50% faster than document-fragment for large templates
		// but document-fragment is more forgiving of multiple-root-nodes and such (which shouldn't be allowed anyway..)
		if(!@$doc->loadXML($templateHTML, LIBXML_NONET | LIBXML_COMPACT))
		{   // direct load failed, probably an xml-fragment
			$this->log->write("Hit XML-fragment, loading it indirectly..");
			$fragment = $doc->createDocumentFragment();

			$fragment->appendXML($templateHTML);

			//foreach($fragment->childNodes as $node)
			//	var_dump($node);

			$doc->appendChild($fragment);
		}

		// loadHTML fails to load custom namespaces
		//$doc->loadHTML($templateHTML);

		// the template isn't guaranteed to be valid XML, specifically it may have multiple root-nodes.
		// document-fragment helps get around that common case
//		$fragment = $doc->createDocumentFragment();
//		$fragment->appendXML($templateHTML);
//		$doc->appendChild($fragment);

		$this->document = $doc;

		$p->next("DOMXPath evaluation");

		//$log->neat("Original HTML:") && $log->raw(htmlspecialchars($templateHTML)) && $perf->mark("Logged HTML");

		///////
		// finding nodes using getElementsByTagNameNS is extremely cheap (10us even for a full templates)
		// unforunately it doesn't capture attributes, forcing us to use the 5-100x slower XPath search

		// 80us
		// document may not have a namespace registered from the xml fragment imported into it (an optimization)
		// so we need to manually provide it here
		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace("lexy", "http://hotcashew.com/lexy");
		//$xpath->query("//@lexy:context", $doc->documentElement);
		$unsortedLexyNodes = $xpath->query("//lexy:* | //@lexy:context", $doc->documentElement);

		// 10us
		//$lexyNodes = $doc->getElementsByTagNameNS("http://hotcashew.com/lexy", "*");

		$p->next("Node sort");

		///////
		// building a properly ordered list of lexy nodes isnt cheap, (50us)
		// grab any node or attribute from the Lexy namespace
		//$unsortedLexyNodes = $xpath->query("//lexy:* | //@lexy:context", $doc->documentElement);

		$sortedLexyNodes = [];

		/*
		if( count($unsortedLexyNodes) > 0 )
		{
			foreach( $unsortedLexyNodes as $node )
				$sortedLexyNodes []= $node;

			$sortedLexyNodes = array_reverse($sortedLexyNodes);
			// the nodes need to be reordered using dependency checking
			// eg: an itemTemplateInstance referencing an itemTemplate will affect the sorting
			$reprioritize = array_filter($sortedLexyNodes, function($item) { return $item->nodeName == "lexy:template"; });
			$sortedLexyNodes = array_filter($sortedLexyNodes, function($item) { return $item->nodeName != "lexy:template"; });
			$sortedLexyNodes = array_merge($reprioritize, $sortedLexyNodes);
		}
		*/

		foreach( $unsortedLexyNodes as $node )
			$sortedLexyNodes []= $node;

		// need to depth-first processing of nodes
		// process the insides of a template before the template itself.
		usort($sortedLexyNodes, function($a,$b) {
			return -strcmp($a->getNodePath(), $b->getNodePath());
		});

//		$p->next("Node dump (log)");

//		// not necessary?
//		$reprioritize = array_filter($sortedLexyNodes, function($item) { return $item->nodeName == "lexy:template"; });
//		$sortedLexyNodes = array_filter($sortedLexyNodes, function($item) { return $item->nodeName != "lexy:template"; });
//		$sortedLexyNodes = array_merge($reprioritize, $sortedLexyNodes);

//		$this->log->write("sorted by depth:");
//		foreach($sortedLexyNodes as $node)
//			$this->log->write("node: " . $node->getNodePath());

		$p->next("Child node processing");

		///////
		// node processing is highly dynamic, but can be really cheap for flat templates (20us)
		foreach( $sortedLexyNodes as $node )
		{
			$this->ProcessNodeWithLexy($node);
			$this->ApplyNodeReplacement();
		}

		$p->next("Post-process");

		$html = $this->FinishProcessingTemplate($doc, $partial);

		//$this->log->writeObject("Finished HTML", "<pre>" . htmlspecialchars($templateHTML) . "</pre>");

		//$perf->bytes = strlen($templateHTML);
		$p->stop();
		$parent->stop();

		// return compiled template
		return $html;
	}

	protected $nodeTransactions = [];

	/**
	 * cheap op, only expensive if source-tracking is enabled
	 */
	protected function QueueUpNodeTransaction($transaction)
	{
		// add source data for debugging
		$source = "N/A";// Log::GetSource(1);
		$transaction["source"] = $source;

		$this->nodeTransactions []= $transaction;
	}

	protected function ApplyNodeReplacement()
	{
		$p = $this->perf->start("Node replacement");

		foreach( $this->nodeTransactions as $nodeTransaction )
		{
			//Log::NiceEcho("action '{$nodeTransaction["action"]}' requested at: {$nodeTransaction["source"]}");

			switch($nodeTransaction["action"])
			{
				case "add":
				{
					$nodeTransaction["parent"]->appendChild($nodeTransaction["node"]);
					$this->log->write("Added '{$nodeTransaction["node"]->nodeName}' to '{$nodeTransaction["parent"]->nodeName}'");
				} break;

				case "remove":
				{
					$nodeTransaction["parent"]->removeChild($nodeTransaction["node"]);
					$this->log->write("Removed '{$nodeTransaction["node"]->nodeName}' from '{$nodeTransaction["parent"]->nodeName}'");
				} break;

				case "insertBefore":
				{
					if( $nodeTransaction["node"] == null )
					{
						$this->log->write( "WARNING: node is null");
						continue;
					}

					if( $nodeTransaction["parent"] == NULL )
					{
						$this->log->write( "WARNING: node parent is null");
						continue;
					}

					$nodeTransaction["parent"]->insertBefore($nodeTransaction["node"], $nodeTransaction["before"]);
					$this->log->write("Inserted '{$nodeTransaction["node"]->nodeName}' into '{$nodeTransaction["parent"]->nodeName}'");
				} break;

				case "replace":
				{
					$nodeTransaction["parent"]->replaceChild($nodeTransaction["node"], $nodeTransaction["old"]);
					$this->log->write("Replaced '{$nodeTransaction["old"]->nodeName}' with '{$nodeTransaction["node"]->nodeName}'");
				} break;

				default:
					$this->log->write("******* Error: Unsupported Action: '{$nodeTransaction["action"]}' *******");
			}
		}

		// clear queue
		$this->nodeTransactions = [];

		$p->stop();
	}

	/**
	 * an INLINE template has already been compiled, so we don't need to process it yet again
	 * @param \DomElement $node
	 */
	protected function CompileItemsController($node)
	{
		// this DOM manipulation is essentially free
		// determine data-context either form attribute or from a child node
		$dataBindingPath = $node->attributes->getNamedItem("itemArray")->textContent;

		// determine template either from attribute or child node
		// template can be either a file or a templateName
		$itemTemplate = $node->attributes->getNamedItem("itemTemplate");

		if( $itemTemplate )
		{
			$fileName = $this->folder . "/" . $itemTemplate->textContent;

			$p = $this->perf->start("External template compilation");

			$this->log->write("External template compilation: $fileName");

			//$log->neat("Compiling external template: " . TemplateSettings::GetRelativePath($fileName));

			$xamlProcessor = new LeXamlRenderer($this->ctx);
			$compiledTemplate = $xamlProcessor->CompileTemplateIfNecessary($fileName, true, $this->dependencies);

			//$this->dependencies = array_merge($this->dependencies, $dependencies);

			$p->stop();

			$compiledCache = $compiledTemplate->cachedPath;

		} else
		{	// try a child-node inline template
			// by the time we get to the manyItems node, its inline itemTemplate will have already been processed
			// we merely need to finalize it

			// TODO: starting and forking right away seems like a common pattern, should simplify it.
			$parent = $this->perf->start("Inline Template compilation");
			$p = $parent->fork("Child search");

			$items = $node->getElementsByTagName("manyItems.itemTemplate");

			// sanity check
			assert(count($items) > 0);
			assert($items->item(0)->firstChild != NULL);

			$itemTemplate = $items->item(0);

			$templateNode = $itemTemplate;

			// i don't know why this grabs the first xml, node grab the whole damn thing!
			/*
			foreach($itemTemplate->childNodes as $child)
			{
				$this->log->write("  itemTemplate child=$child->nodeName nodeType=$child->nodeType");
				$this->log->write(htmlspecialchars($child->nodeValue));
				if( $child->nodeType == XML_ELEMENT_NODE )
					$templateNode = $child;
			}
			*/

			$p->next("Post-process");

			/*
			foreach( $items as $child )
			{
				$log->neat("adding child node to fragment: $child->nodeName");
				$documentFragment->appendChild($child);

				foreach($child->childNodes as $grandChild)
				{
					$log->neat("grandchild node: $grandChild->nodeName");
					if( $grandChild->nodeType == XML_ELEMENT_NODE )
						$templateNode = $grandChild;
				}

				//$documentFragment->appendXML($this->document->saveXML($child));
				//if( $child->nodeType == XML_ELEMENT_NODE )
				//	$templateNode = $child;
			}
			*/

			$idealTemplateNodes = 0;
			$idealTemplateNode = null;

			foreach($itemTemplate->childNodes as $child) {
				//$this->log->write("  itemTemplate child=$child->nodeName nodeType=$child->nodeType");
				//$this->log->write(htmlspecialchars($child->nodeValue));

				if( $child->nodeType != XML_TEXT_NODE ) {
					$idealTemplateNodes ++;
					$idealTemplateNode = $child;
				}
			}

			// the HTML is already compiled, and all dependencies will be in the parent template

			$this->log->write("idealTemplateNodes = $idealTemplateNodes");

			if($idealTemplateNodes > 1) {

				$compiledHTML = $this->PostProcessTemplateFragment($templateNode);

			} else {

				foreach($itemTemplate->childNodes as $child) {
					$this->log->write("  itemTemplate child=$child->nodeName nodeType=$child->nodeType");
					$this->log->write(htmlspecialchars($child->nodeValue));
					if( $child->nodeType == XML_ELEMENT_NODE )
						$templateNode = $child;
				}

				$compiledHTML = $this->PostProcessTemplate($idealTemplateNode);
			}

			$p->next("Namespace stripping");

			// strip any namespaces off the child-template
			$compiledHTML = preg_replace('/\s?xmlns=.*xhtml\"/', '', $compiledHTML);

			$p->next("Compile and Save");


			// dependency tracking an inline anonymous template doesn't make sense
			// because the parent template will be invalided when the template file is modified
			// causing this cache to rebuild itself

			// we create a virtual-file-path for an anonymous inline template
			// there is no real point in putting this into a file, since we can't benefit from sharing it
			// but this way is easier and more consistent

			$fileName = $this->GetAnonymousVirtualTemplatePath();
			$compiledTemplate = new CompiledTemplate($this->ctx, $fileName, $compiledHTML, []);
			$compiledTemplate->SaveInCache();
			$compiledCache = $compiledTemplate->cachedPath;

			$p->stop();
			$parent->stop();
		}

		///////
		// the rest takes 20us
		// but can take 60us if source-tracking is enabled in ::QueueUpNodeTransaction()

		// compile into a php forloop
		$forloop = <<<EOT

\Bugvote\Core\Renderer\DataContext::push(\$data);

\$itemsArray = \Bugvote\Core\Renderer\DataContext::resolvePath(\$data, "$dataBindingPath");
// empty arrays are okay
if( \$itemsArray === null )
{	// View<->VM error here
	PrettyPrint::WriteLine("Error: missing data: $dataBindingPath");
}
else
{
	//\$this->log->write("items: " . count(\$itemsArray));
	//\$this->log->write("fileName: $fileName");
	foreach(\$itemsArray as \$item)
		// cached as: $compiledCache
		\$this->ApplyTemplateByName('$fileName', \$item);
}

\Bugvote\Core\Renderer\DataContext::pop();

EOT;

		$newNode = $this->document->createCDATASection("<?php\n" . $forloop . '?>');
		$this->QueueUpNodeTransaction([ "action" => "replace", "node" => $newNode, "old" => $node, "parent" => $node->parentNode ]);
	}

	/**
	 * replace a placeholder with a compiled template
	 * @param DOMNode $node
	 * @throws InvalidArgumentException
	 */
	protected function CompileItemTemplateInstance($node)
	{
		$p = $this->perf->start("Path build");

		$templatePath = false;
		$templatePathIsStatic = false;

		// we could have a locally defined named-template, or a file-template
		// first check for a named-template
		$templateName = $this->extractParameter($node, "templateName");
		if($templateName) {
			// if we have a non-binding template-name, we can resolve it right now!
			if(!$templateName->isBinding) {
				$templatePath = $this->GetVirtualTemplatePath($templateName->value);
				$templatePathIsStatic = true;

				$this->log->write("\$templatePath = $templatePath (static name)");
			} else {
				$templatePath = $templateName->value;
				$this->log->write("\$templatePath = $templatePath (dynamic name)");
			}

		} else
		{
			$templateFile = $this->extractParameter($node, "templateFile");
			if($templateFile) {
				if(!$templateFile->isBinding) {

					$relativePath = $templateFile->value;

					// append file ending if it doesn't have it
					if(!strstr($relativePath, ".xaml"))
						$relativePath .= ".xaml";

					$rootDir = dirname($this->mainFilename);
					$templatePath = realpath($rootDir . '/' . $relativePath);
					$templatePathIsStatic = true;

					if(!$templatePath) {
						// failed to resolve path at compile time, template probably does not exist
						$this->log->write("ERROR: Static template file not found: $relativePath in rootDir: $rootDir");
					}

					$this->log->write("\$templatePath = $templatePath (static path)");
				} else {
					$templatePath = $templateFile->value;
					$this->log->write("\$templatePath = $templatePath (dynamic path)");
				}
			}
		}

		$p->stop();

		if($templatePathIsStatic) {

			if(!$templatePath) {
				// early bail in case of template missing
				return;
			}

			$this->log->write("Compiling static itemTemplate: $templatePath");

			// make sure the instanced template is compiled
			// then add its dependencies to ours
			self::CompileTemplateIfNecessary($templatePath, true, $this->dependencies);
			$compiledCache = TemplateCacheMaster::GetCompiledTemplatePath($templatePath);

			$virtualPath = $this->CompileChildContent($node);

			$p = $this->perf->start("Creating PHP block (static path)");
			//$p->next("Creating PHP block (static path)");

			$instantiate = <<<EOT

\$oldChild = isset(\$data->childTemplate) ? \$data->childTemplate : false;
\$data->childTemplate = "$virtualPath";
// apply template cached at [$compiledCache] to each item
\$this->ApplyTemplateByName('$templatePath', \$data);
\$data->childTemplate = \$oldChild;

EOT;
		} else {

			$p = $this->perf->start("Creating PHP block (dynamic path)");

			$instantiate = <<<EOT

\$oldChild = isset(\$data->childTemplate) ? \$data->childTemplate : false;
\$data->childTemplate = "N/A";
// dynamicly bound templates cache-status is unknown until runtime
if(empty($templatePath)) {
	// put out a warning? or just a notice? this is non-fatal in Mustache
	//var_dump("Dynamic Binding is NULL!");
	//var_dump(\$data);
	\$this->log->write("Dynamic-binding \"\\$templatePath\" is null");
} else
{
	// expand dynamical path
	\$relativePath = $templatePath;
	if(!strstr(\$relativePath, ".xaml"))
		\$relativePath .= ".xaml";

	\$rootDir = dirname("$this->mainFilename");

	\$templatePath = realpath(\$rootDir . '/' . \$relativePath);

	//var_dump("rootDir: \$rootDir (dynamic)");
	//var_dump("applying template: $templatePath (dynamic)");
	//var_dump("applying template: \$relativePath (relative)");
	//var_dump("applying template: \$templatePath (generated)");
	\$this->Execute(\$templatePath, \$data);
}
\$data->childTemplate = \$oldChild;

EOT;
		}


		/*
		if( $templateSource )
		{	// from a virtual path
			$templatePath = $this->GetVirtualTemplatePath($templateSource->textContent);
		} else
		{	// a real path
			$templateSource = $node->attributes->getNamedItem("templateFile");
			if( ! $templateSource )
			{
				throw new InvalidArgumentException("lexy:itemTemplate requires templateName or templateFile");
			}

			// this is a rael file, so realpath() is allowed
			$templateName = $templateSource->textContent;

			// template name could be a binding! yey dynamic partials!
			if(strstr($templateName, "{binding:"))
			{
				$this->log->write("NOTE: templateSource is a dynamic binding: $templateName");
				$templatePathIsDynamicBinding = true;
				$templatePath = $templateName;
			} else
			{
				$this->log->write("\$templateName = $templateName");
				$this->log->write("\$this->mainFilename = " . dirname($this->mainFilename));

				if(!strstr($templateName, ".xaml"))
					$templateName .= ".xaml";

				$templatePath = realpath(dirname($this->mainFilename) . '/' . $templateName);

				$this->log->write("\$templatePath = $templatePath");
			}
		}

		if(!$templatePathIsDynamicBinding && $templatePath == '') {
			$this->log->write("WARNING: itemTemplate does not have a path specified!");
			$p->stop();
			return;
		}

		*/

		/*
		if(!$templatePathIsDynamicBinding)
		{
			$p->next("Template compile");

			$this->log->write("Compiling itemTemplate instance: $templatePath");

			// make sure the instanced template is compiled
			// then add its dependencies to ours
			self::CompileTemplateIfNecessary($templatePath, true, $this->dependencies);
			$compiledCache = TemplateCacheMaster::GetCompiledTemplatePath($templatePath);

			$this->log->write("Loading template: $templatePath");

			$p->next("Compile children");

			$virtualPath = $this->CompileChildContent($node);

			// don't actually import the template.. add php code to apply it at runtime..
			// compile into a php forloop
			$instantiate = <<<EOT

\$oldChild = isset(\$data->childTemplate) ? \$data->childTemplate : false;
\$data->childTemplate = "$virtualPath";
// apply template cached at [$compiledCache] to each item
\$this->ApplyTemplateByName('$templatePath', \$data);
\$data->childTemplate = \$oldChild;

EOT;

		} else
		{
//			$templatePath = preg_replace_callback('/{binding:(.*(?:&gt\;).*)}/',
//				function($m) {
//					return html_entity_decode($m[0]);
//				}, $templatePath
//			);
//
//			$templatePath = preg_replace_callback('/\"%7B(binding.*)%7D\"/',
//				function($m) {
//					return urldecode($m[0]);
//				}, $templatePath
//			);

			// generate dynamic binding php
			$binding = preg_replace('/{binding:([^}]+)}/', '\$data->$1', $templatePath);
			$virtualPath = "N/A";
			$compiledCache = "N/A (dynamic)";

			// don't actually import the template.. add php code to apply it at runtime..
			// compile into a php forloop
			$instantiate = <<<EOT

\$oldChild = isset(\$data->childTemplate) ? \$data->childTemplate : false;
\$data->childTemplate = "N/A";
// dynamicly bound templates cache-status is unknown until runtime
if(empty($binding)) {
	var_dump("Dynamic Binding is NULL!");
	var_dump(\$data);
} else
{
	var_dump("applying template: $binding");
	\$this->Execute($binding, \$data);
}
\$data->childTemplate = \$oldChild;

EOT;
		}
		*/

		//$this->log->writeObject("PHP Block:", $instantiate);

		$newNode = $this->document->createCDATASection("<?php\n" . $instantiate . '?>');
		$this->QueueUpNodeTransaction([ "action" => "replace", "node" => $newNode, "old" => $node, "parent" => $node->parentNode ]);

		$p->stop();
	}


	protected function extractParameter($node, $parameterName)
	{
		$param = $node->attributes->getNamedItem($parameterName);
		if(!$param)
			return false;

		$value = $param->textContent;

		//preg_match('/{binding:(.+)}/', $value, $matches);
		$binding = preg_replace('/{binding:([^}]+)}/', '\$data->$1', $value);

		// we may have a runtime binding property
		//if(count($matches) == 2)
		if($binding != $value)
		{   // we have a binding, normalize it
			$binding = str_replace('.', '->', $binding);
			return new XamlParameter($parameterName, $binding, true);
		}

		// otherwise, its a plain old hardcoded value
		return new XamlParameter($parameterName, $value, false);
	}

	/**
	 *
	 * @param \DOMNode $node
	 */
	protected function CompileItemTemplate($node)
	{
		$parent = $this->perf->start("Compiling Special Node '{$node->nodeName}'");
		$p = $parent->fork("XML export");

		//$templateName = $node->attributes->getNamedItem("name")->textContent;
		$templateName = $this->extractParameter($node, "name");
		$compiledItemTemplate = "";

		// slightly (unnoticibly really) faster than the other methods
		foreach($node->childNodes as $child)
			$compiledItemTemplate .= $this->document->saveXML($child);

		$p->next("Path building");

		// save compiled template to a virtual-file
		// relative path
		$virtualPath = $this->GetVirtualTemplatePath($templateName);
		$compiledCache = TemplateCacheMaster::GetCompiledTemplatePath($virtualPath);

		$p->next("Template saving");

		CAL::Save($compiledCache, $compiledItemTemplate);

		$this->QueueUpNodeTransaction([ "action" => "remove", "node" => $node, "parent" => $node->parentNode ]);

		$p->stop();
		$parent->stop();
	}

	/**
	 * @param DOMNode $node
	 */
	protected function CompileCSRF($node)
	{
		//$perf = PerformanceLog::start("Compiling Special Node '{$node->nodeName}'");
		$p = $this->perf->start("PHP Block creation for special node: '{$node->nodeName}'");

		// build a unique identifier for this form
		$filePath = $this->GetAnonymousVirtualTemplatePath();
		$domPath = $node->getNodePath();
		$formPath = $filePath . $domPath;

		$csrf_generator = <<<EOT
echo "<input type=\"hidden\" name=\"csrf\" value=\"" . \Security::GenerateTimedCsrf('$formPath') . "\" />\\n";
EOT;

		$newNode = $this->document->createCDATASection("<?php\n" . $csrf_generator . '?>');

		$this->QueueUpNodeTransaction([ "action" => "replace", "node" => $newNode, "old" => $node, "parent" => $node->parentNode ]);

		$p->stop();
	}

	/**
	 * @param string $templatePath absolute path to the template
	 * @param bool $partial indicates it's not a complete web page
	 * @param mixed $parentDependencies array of parent template dependencies onto which to append ours
	 * @return bool if recompiled, false otherwise
	 */
	protected function CompileTemplateIfNecessary($templatePath, $partial = false, &$parentDependencies = false)
	{
		$this->log->write("Trying: $templatePath");

		$processor = new LeXamlRenderer($this->ctx);

		$cacheValid = false;
		$compiledTemplate = false;

		if( ! BUGVOTE_XAML_FORCE_REBUILD )
			// caching is allowed, so check if we have a cached copy
			$cacheValid = $processor->IsTemplateCacheValid($templatePath);

		if( !$cacheValid )
			// not using a cached copy, compile template from scratch
			$compiledTemplate = $processor->CompileTemplateFromFile($templatePath, $partial);

		if( $parentDependencies !== false )
		{	// caller wants to add dependencies

			if( $compiledTemplate ) {
				$dependencies = $compiledTemplate->dependencies;
			} else {
				$dependencyPath = TemplateCacheMaster::GetDependenciesPath($templatePath);
				$dependencies = json_decode(CAL::Load($dependencyPath))->dependencies;
			}

			$parentDependencies = array_merge($parentDependencies, $dependencies);
		}

		return $compiledTemplate !== false;
	}

	// defunc
	// an absolute template path
	protected function RequireTemplate($templatePath)
	{
		$compiledTemplate = self::CompileTemplateIfNecessary($templatePath);

		if( ! $compiledTemplate )
		{	// try to load from cache
			$html = CAL::Load($templatePath);
		} else
		{
			$html = $compiledTemplate->html;
		}

		return $html;
	}

	protected function ProcessContentPresenter($node)
	{
		$perf = PerformanceLog::start("Compiling Special Node '{$node->nodeName}'");
		$log = AwesomeLog::start("Compiling Special Node '{$node->nodeName}'");
		$perf->mark("Spawned log");

		$include = <<<EOT

if( isset(\$data->childTemplate) && \$data->childTemplate != false )
{
	\$templatePath = \$data->childTemplate;
	\$data->childTemplate = false;
	\$this->ApplyTemplateByName(\$templatePath, \$data);
}

EOT;

		// wow, storing php code inside cdata sections works amazingly with DOMDocument
		// when doing saveHTML() it strips the CDATA tags off, and just outputs straight php
		// AND its well indented (though that's bitter-sweet because you don't always want the emitted whitespace)
		// BUT this means i don't have to do a second (ugly) pass to capture and reconstruct php-blocks

		$cdata = $this->document->createCDATASection("<?php\n" . $include . '?>');

		$this->QueueUpNodeTransaction([ "action" => "replace", "node" => $cdata, "old" => $node, "parent" => $node->parentNode ]);
		$perf->mark("PHP Block Created");

		$log->neat("PHP Block:") && $log->raw(htmlspecialchars($cdata->nodeValue)) && $perf->mark("Logged HTML");

		$perf->save();
		$log->save();
	}

	/**
	 * @param DOMNode $node
	 * @param \PerformanceLog $perf
	 * @return bool|string
	 */
	protected function CompileChildContent($node)
	{
		/*
		// take the contents out and process them as their own virtual template
		// the contents of the node should have been processed by the time we reach the node itself
		// so we can just fork them off into a virtual template, and apply that at runtime from within the node's own template
		// in a special ContentPresenter-like position
		$compiledItemTemplate = "";
		foreach($node->childNodes as $child)
			$compiledItemTemplate .= $this->document->saveXML($child);
		*/

		$p = $this->perf->start("HTML generation");

		// can't just do saveXML() on the nodes naively, have to.. nurse it a bit.
		$html = "";
		if( $node->childNodes->length > 0 )
		{
			$p->next("DOMDocument creation");
			$doc = new DOMDocument();
			$doc->formatOutput = false;
			$doc->preserveWhiteSpace = true;
			//$doc->recover = true;

			// xml based fragment import: 200us (total)
			// node based import: 70us (total)

			$p->next("XML Node import");

			foreach($node->childNodes as $child)
			{
				$node = $doc->importNode($child, true);
				$doc->appendChild($node);
			}

			//$node = $doc->importNode($node, true);
			//$doc->appendChild($node);
			$p->next("XML export");

			$html = $this->FinishProcessingTemplate($doc, true);
		}

		// if theres no child content, return false
		if( $html == "")
		{
			$p->stop();
			return false;
		}

		$p->next("Template save");

		// save compiled template to a virtual-file
		// relative path
		$virtualPath = $this->GetAnonymousVirtualTemplatePath();
		$compiledCache = TemplateCacheMaster::GetCompiledTemplatePath($virtualPath);

		CAL::Save($compiledCache, $html);

		$p->stop();

		return $virtualPath;
	}

	protected function CompileHeader($node)
	{
		$perf = PerformanceLog::start("Compiling Special Node '{$node->nodeName}'");
		$log = AwesomeLog::start("Compiling Special Node '{$node->nodeName}'");

		$templatePath = $node->attributes->getNamedItem("template")->textContent;

		// process properties
		$items = $node->getElementsByTagName("css");

		$cssLinks = [];

		if(count($items) > 0)
			foreach( $items as $child )
				if( $child->nodeType == XML_ELEMENT_NODE )
				{	// get relative path
					$href = $child->attributes->getNamedItem("href")->textContent;
					$parentTemplateDir = dirname($this->mainFilename);
					$absPath = $parentTemplateDir . "/$href";
					$relPath = TemplateSettings::GetRelativeCSSPath($absPath);
					$cssLinks []= ["href" => $relPath ];
				}

		/*
		// take the contents out and process them as their own virtual template
		// the contents of the node should have been processed by the time we reach the node itself
		// so we can just fork them off into a virtual template, and apply that at runtime from within the node's own template
		// in a special ContentPresenter-like position
		$compiledItemTemplate = "";
		foreach($node->childNodes as $child)
			$compiledItemTemplate .= $this->document->saveXML($child);

		$perf->mark("XML exporting");

		// save compiled template to a virtual-file
		// relative path
		$virtualPath = $this->GetAnonymousVirtualTemplatePath();
		$compiledCache = TemplateCacheMaster::GetCompiledTemplatePath($virtualPath);

		CAL::Save($compiledCache, $compiledItemTemplate);

		$perf->mark("Template saving");
		*/

		$virtualPath = $this->CompileChildContent($node, $perf);

		$templateData = base64_encode(json_encode($cssLinks));

		// header specifies a filepath, so realpath() is allowed here
		$absPath = realpath($this->folder . "/" . $templatePath);
		if( ! $absPath ) {
			throw new InvalidArgumentException("node specified invalid path; absPath=[".$this->folder . "/" . $templatePath."]");
		}

		$log->neat("path for template [$templatePath] = [$absPath]");

		self::CompileTemplateIfNecessary($absPath, true, $this->dependencies);
		//$compiledCache = $compiledTemplate->html;

		$perf->mark("Template Precompiled");

		// compile into a php forloop
		$include = <<<EOT

\$data->cssHardcoded = json_decode(base64_decode("$templateData"));
\$oldChild = isset(\$data->childTemplate) ? \$data->childTemplate : false;
\$data->childTemplate = "$virtualPath";
\$this->ApplyTemplateByName('$absPath', \$data, false); // don't beautify
\$data->childTemplate = \$oldChild;

EOT;

		$newNode = $this->document->createCDATASection("<?php\n" . $include . '?>');
		//$newNodeTemplate = $this->document->createElement('php.template', $include);

		// we want to make sure everything inside the node doesn't just disappear..
		// and so we forward it into the template using its XAML's ContentPresenter-like property

		//$newNodeIncludes = $this->document->createElement('php.child');

		// move everything from inside this node into the created template
		//foreach( $node->childNodes as $child )
		//	$this->QueueUpNodeTransaction([ "action" => "add", "node" => $child, "parent" => $newNodeIncludes ]);

		// then queue replacement of lexy node with the newely generated node
		//$this->QueueUpNodeTransaction([ "action" => "insertBefore", "node" => $newNodeIncludes, "before" => $node, "parent" => $node->parentNode ]);
		$this->QueueUpNodeTransaction([ "action" => "replace", "node" => $newNode, "old" => $node, "parent" => $node->parentNode ]);

		$perf->mark("PHP Block Created");
		$perf->save();
		$log->save();
	}

	protected function CompileCssLink($node)
	{
		$perf = PerformanceLog::start("Compiling Special Node '{$node->nodeName}'");
		$log = AwesomeLog::start("Compiling Special Node '{$node->nodeName}'");

		$hrefPath = $node->attributes->getNamedItem("href")->textContent;
		$parentTemplateDir = dirname($this->mainFilename);
		$absPath = $parentTemplateDir . "/$hrefPath";
		$relPath = TemplateSettings::GetRelativeCSSPath($absPath);

		$log->neat("Creating css link node for href: $hrefPath");

		// <link href="/css/Profile/Main.css" type="text/css" rel="stylesheet" />
		$newNode = $this->document->createElement("link");
		$newNode->setAttribute("href", $relPath);
		$newNode->setAttribute("type", "text/css");
		$newNode->setAttribute("rel", "stylesheet");

		$this->QueueUpNodeTransaction([ "action" => "replace", "node" => $newNode, "old" => $node, "parent" => $node->parentNode ]);

		$perf->mark("PHP Block Created");
		$perf->save();
		$log->save();
	}

	protected function CompileIfStatement($node)
	{
		$conditional = $this->extractParameter($node, "truedat");
		$comment = $this->extractParameter($node, "comment");

		$comment = $comment ? $comment->value : str_replace('$', '\$', htmlentities($conditional->value));

		$this->log->write("Conditional: $conditional->value");
		$this->log->write("Comment: $comment");

		// normalize conditional
		$conditional->value = str_replace('.', '->', $conditional->value);

		/*
		if( $node->attributes->getNamedItem("comment") != null )
			$comment = $node->attributes->getNamedItem("comment")->textContent;
		else
			// FIXME: shouldn't do this in production, gives away internal details
			$comment = str_replace('$', '\$', htmlentities($conditional));
		*/

		// conditional should be a variable in the current data context
		// there shouldn't be any functions being called here
		// if something needs to be computed, it should be computed before-hand
		// if functions can be called and variables modified.. then running templates can cause side-effects
		// and i don't know if thats a good thing, that sort of logic shouldn't be spread out through templates..

		$ifStatement = '$data->' . $conditional->value;

		$parts = explode('->', $ifStatement);
		$prop = array_pop($parts);
		$obj = join('->', $parts);

		// compile into a php forloop
		$if_start = <<<EOT

if( method_exists($obj, "$prop") ) {
	//\$this->log->write("found callable parameter: $conditional->value");
	\$ifValue = \$data->$conditional->value();
}
else if( property_exists($obj, "$prop") && !method_exists($obj, "$prop") ) {
	//\$this->log->write("found property parameter: $conditional->value");
	\$ifValue = $obj->$prop;
}
else {
	\$ifValue = false;
}


if( \$ifValue )
{
	echo "<!-- if: $comment -->\\n";

EOT;

		$if_end = <<<EOT

	echo "<!-- fi: $comment -->\\n";
} // end of if ( $ifStatement )


EOT;

		$ifStart = $this->document->createCDATASection("<?php\n" . $if_start . '?>');
		$ifEnd = $this->document->createCDATASection("<?php\n" . $if_end . '?>');

		$this->QueueUpNodeTransaction([ "action" => "insertBefore", "node" => $ifStart, "before" => $node, "parent" => $node->parentNode ]);

		foreach( $node->childNodes as $child )
			$this->QueueUpNodeTransaction([ "action" => "insertBefore", "node" => $child, "before" => $node, "parent" => $node->parentNode ]);

		$this->QueueUpNodeTransaction([ "action" => "insertBefore", "node" => $ifEnd, "before" => $node, "parent" => $node->parentNode ]);
		$this->QueueUpNodeTransaction([ "action" => "remove", "node" => $node, "parent" => $node->parentNode ]);
	}

	// create a fake file-path of where the virtual template WOULD be if it was in a file
	// this is used for easy and consistent caching
	protected function GetVirtualTemplatePath($templateName)
	{
		$mainFileRelativePath = $this->mainFilename;
		$virtualPath = $mainFileRelativePath . "/VirtualTemplates/" . $templateName . ".lexy";
		return $virtualPath;
	}

	protected $anonymousTemplateCount = 0;

	protected function GetAnonymousVirtualTemplatePath()
	{
		$this->anonymousTemplateCount ++;
		return $this->GetVirtualTemplatePath("AnonymousTemplate" . $this->anonymousTemplateCount);
	}
}



class DataContext
{
	protected static $stack = [];

	public static function push($context)
	{
		array_push(self::$stack, $context);
	}

	public static function pop()
	{
		return array_pop(self::$stack);
	}

	public static function current()
	{
		return end(self::$stack);
	}

	public static function resolvePath($context, $path)
	{
		if( $context == null ) {
			throw new ErrorException("\$context must not be null");
		}

		$__return_value = null;

		// normalize path
		$path = str_replace(".", "->", $path);

		if( is_array($context) )
		{	// array
			//var_dump("Resolving path '$path' in array");
			extract($context);
			eval("\$__return_value = \$" . $path . ";");
		}
		else if( is_object($context) )
		{	// object
			//var_dump("Resolving path '$path' in object");
			eval("\$__return_value = \$context->" . $path . ";");
		}
		else
		{
			//$log->enabled && $log->neat("WARNING: Unsupported context type") && $log->raw(print_r($context,true));
			throw new InvalidArgumentException("Unsupported context type: " . print_r($context,true));
		}

		return $__return_value;
	}
}

/**
 * Wrapper around a compiled template instance. Used by LeXamlRenderer
 */
class CompiledTemplate
{
	public $dependencies = [];
	public $html;
	public $template;
	public $cachedPath = false;
	public $dependencyPath = false;

	/** @var \Bugvote\Core\Logging\AppPerformanceLog */
	protected $perf;
	/** @var \Bugvote\Core\Logging\ILogger */
	protected $log;

	function __construct(Context $ctx, $template, $html, $dependencies)
	{
		$this->template = $template;
		$this->html = $html;
		$this->dependencies = $dependencies;
		$this->perf = $ctx->perf;
		$this->log = $ctx->log;
	}

	public function SaveInCache()
	{
		$p = $this->perf->start("Compiled Template save");

		$p1 = $p->fork("Path prep");

		$dependencyDefinition = new TemplateDependencies($this->template, $this->dependencies);
		$this->cachedPath = TemplateCacheMaster::GetCompiledTemplatePath($this->template);
		$this->dependencyPath = TemplateCacheMaster::GetDependenciesPath($this->template);

		$p1->next("JSON encode");

		// having a comment at the top of file puts IE9 into quirksmode
		// so none of this commentary
		//$htmlData = "<!-- $localPath -->\n" . $this->html . "\n";

		$htmlData = $this->html . "\n";
		$dependencyData = json_encode($dependencyDefinition);

		$this->log->write("Saved to cache: {$this->cachedPath}");

		$p1->next("HTML write");
		CAL::Save($this->cachedPath, $htmlData);

		$p1->next("Dependencies write");
		CAL::Save($this->dependencyPath, $dependencyData);

		// TODO: add bandwidth measurement to performance timer
		//$perf->bytes = strlen($htmlData) + strlen($dependencyData);

		$p1->stop();
		$p->stop();
	}
}

/**
 * A list of dependencies that if modified must invalidate the parent template
 */
class TemplateDependencies
{
	function __construct($template, $dependencies)
	{
		$this->template = $template;
		$this->dependencies = $dependencies;
		$this->lastModified = time();
	}

	public $lastModified;
	public $template;
	public $dependencies;
}

class TemplateSettings
{
	// these functions may get virtual-file paths, so realpath() isn't allowed here

	// path relative to the root-path of the entire project
	public static function GetRelativePath($absolutePath)
	{
		if(strstr($absolutePath, BUGVOTE_ROOT) === false)
		{        // bad path
			throw new InvalidArgumentException("supplied argument must be an absolute path rooted in web-app root folder. absolutePath=[$absolutePath]");
		}

		$localPath = str_replace('\\','/', $absolutePath);
		$localPath = str_replace(BUGVOTE_ROOT, "", $localPath);
		$localPath = trim($localPath, '/');
		return $localPath;
	}

	public static function GetRelativeCSSPath($absolutePath)
	{        // relative to the web-server
		$localPath = str_replace('\\','/', $absolutePath);
		$localPath = str_replace(BUGVOTE_APP_VIEWS, "", $localPath);
		$localPath = trim($localPath, '/');
		$localPath = '/css/' . $localPath;
		return $localPath;
	}
}

/**
 * Responsible for actual file paths. No other part of the xaml system will build file paths.
 */
class TemplateCacheMaster
{
	/**
	 * @param string $templatePath representing the full path to an uncompiled .lexy template
	 * @return string representing the full path to the compiled .php file
	 */
	public static function GetCompiledTemplatePath($templatePath)
	{
		// for 1000 short strings, crc32 2x faster than adler32 slightly faster than md5
		// for 10 strings, the performance difference is negligible (total: 20us, diff: ~3us)
		// so using md5() will give great results and great performance for templates

		// compute the local path
		// normalize path
		$relativeTemplatePath = TemplateSettings::GetRelativePath($templatePath);
		$niceName = str_replace('/', '-', $relativeTemplatePath);

		//$pathHash = HashCache::GetHashForPath($relativeTemplatePath);
		//$compiledCache = BUGVOTE_TMP_XAML_CACHE . "/{$niceName}.$pathHash.php";
		$compiledCache = BUGVOTE_TMP_XAML_CACHE . "/{$niceName}.php";

		return $compiledCache;
	}

	public static function GetDependenciesPath($templatePath)
	{
		$relativeTemplatePath = TemplateSettings::GetRelativePath($templatePath);
		$niceName = str_replace('/', '-', $relativeTemplatePath);

		//$pathHash = HashCache::GetHashForPath($relativeTemplatePath);
		//$compiledCache = BUGVOTE_TMP_XAML_CACHE . "/{$niceName}.dependencies.$pathHash.php";
		$compiledCache = BUGVOTE_TMP_XAML_CACHE . "/{$niceName}.dependencies.php";

		return $compiledCache;
	}
}

class Utils
{
	// stolen from the nets
	public static function str_replace_once($needle , $replace , $haystack, &$lastOffset){
		// Looks for the first occurence of $needle in $haystack
		// and replaces it with $replace.
		$pos = strpos($haystack, $needle, $lastOffset);
		if ($pos === false) {
			// Nothing found
			return $haystack;
		}
		$lastOffset = $pos;
		return substr_replace($haystack, $replace, $pos, strlen($needle));
	}

	public static function str_replace_once_painter($needle , $replace , $haystack) {
		$pos = strpos($haystack, $needle);
		if ($pos === false)
			return $haystack;
		return substr_replace($haystack, $replace, $pos, strlen($needle));
	}
}

class CAL
{
	static $prefix = "__cal_";

	public static function Save($file, $contents)
	{
		//apc_store(self::$prefix . $file, $contents, 3600);
		file_put_contents($file, $contents, LOCK_EX);
	}

	// only used during the Compilation phase
	public static function Load($file)
	{
		$fetched = false;
		$contents = null; //apc_fetch(self::$prefix . $file, $fetched);

		if( ! $fetched )
		{
			//\Log::NiceEcho("cache miss: fetching from disk: $file");
			$contents = file_get_contents($file);
			//apc_store(self::$prefix . $file, $contents, 3600);
		}

		return $contents;
	}
}