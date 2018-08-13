<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use MatthiasMullie\Minify;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Parses and outputs template files
 *
 * The class supports loading template files, adding variables to them and then
 * printing them to the screen. It functions as abstract parent class for the
 * two core classes "BackendTemplate" and "FrontendTemplate".
 *
 * Usage:
 *
 *     $template = new BackendTemplate();
 *     $template->name = 'Leo Feyer';
 *     $template->output();
 *
 * @property string $style
 * @property array  $cssID
 * @property string $class
 * @property string $inColumn
 * @property string $headline
 * @property array  $hl
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
abstract class Template extends \Controller
{
	use \TemplateInheritance;

	/**
	 * Output buffer
	 * @var string
	 */
	protected $strBuffer;

	/**
	 * Content type
	 * @var string
	 */
	protected $strContentType;

	/**
	 * Template data
	 * @var array
	 */
	protected $arrData = array();

	/**
	 * Valid JavaScipt types
	 * @var array
	 * @see http://www.w3.org/TR/html5/scripting-1.html#scriptingLanguages
	 */
	protected static $validJavaScriptTypes = array
	(
		'application/ecmascript',
		'application/javascript',
		'application/x-ecmascript',
		'application/x-javascript',
		'text/ecmascript',
		'text/javascript',
		'text/javascript1.0',
		'text/javascript1.1',
		'text/javascript1.2',
		'text/javascript1.3',
		'text/javascript1.4',
		'text/javascript1.5',
		'text/jscript',
		'text/livescript',
		'text/x-ecmascript',
		'text/x-javascript',
	);

	/**
	 * Create a new template object
	 *
	 * @param string $strTemplate    The template name
	 * @param string $strContentType The content type (defaults to "text/html")
	 */
	public function __construct($strTemplate='', $strContentType='text/html')
	{
		parent::__construct();

		$this->strTemplate = $strTemplate;
		$this->strContentType = $strContentType;
	}

	/**
	 * Set an object property
	 *
	 * @param string $strKey   The property name
	 * @param mixed  $varValue The property value
	 */
	public function __set($strKey, $varValue)
	{
		$this->arrData[$strKey] = $varValue;
	}

	/**
	 * Return an object property
	 *
	 * @param string $strKey The property name
	 *
	 * @return mixed The property value
	 */
	public function __get($strKey)
	{
		if (isset($this->arrData[$strKey]))
		{
			if (\is_object($this->arrData[$strKey]) && \is_callable($this->arrData[$strKey]))
			{
				return $this->arrData[$strKey]();
			}

			return $this->arrData[$strKey];
		}

		return parent::__get($strKey);
	}

	/**
	 * Execute a callable and return the result
	 *
	 * @param string $strKey    The name of the key
	 * @param array  $arrParams The parameters array
	 *
	 * @return mixed The callable return value
	 *
	 * @throws \InvalidArgumentException If the callable does not exist
	 */
	public function __call($strKey, $arrParams)
	{
		if (!isset($this->arrData[$strKey]) || !\is_callable($this->arrData[$strKey]))
		{
			throw new \InvalidArgumentException("$strKey is not set or not a callable");
		}

		return \call_user_func_array($this->arrData[$strKey], $arrParams);
	}

	/**
	 * Check whether a property is set
	 *
	 * @param string $strKey The property name
	 *
	 * @return boolean True if the property is set
	 */
	public function __isset($strKey)
	{
		return isset($this->arrData[$strKey]);
	}

	/**
	 * Set the template data from an array
	 *
	 * @param array $arrData The data array
	 */
	public function setData($arrData)
	{
		$this->arrData = $arrData;
	}

	/**
	 * Return the template data as array
	 *
	 * @return array The data array
	 */
	public function getData()
	{
		return $this->arrData;
	}

	/**
	 * Set the template name
	 *
	 * @param string $strTemplate The template name
	 */
	public function setName($strTemplate)
	{
		$this->strTemplate = $strTemplate;
	}

	/**
	 * Return the template name
	 *
	 * @return string The template name
	 */
	public function getName()
	{
		return $this->strTemplate;
	}

	/**
	 * Set the output format
	 *
	 * @param string $strFormat The output format
	 */
	public function setFormat($strFormat)
	{
		$this->strFormat = $strFormat;
	}

	/**
	 * Return the output format
	 *
	 * @return string The output format
	 */
	public function getFormat()
	{
		return $this->strFormat;
	}

	/**
	 * Print all template variables to the screen using print_r
	 *
	 * @deprecated Deprecated since Contao 4.3, to be removed in Contao 5.
	 *             Use Template::dumpTemplateVars() instead.
	 */
	public function showTemplateVars()
	{
		@trigger_error('Using Template::showTemplateVars() has been deprecated and will no longer work in Contao 5.0. Use Template::dumpTemplateVars() instead.', E_USER_DEPRECATED);

		$this->dumpTemplateVars();
	}

	/**
	 * Print all template variables to the screen using the Symfony VarDumper component
	 */
	public function dumpTemplateVars()
	{
		VarDumper::dump($this->arrData);
	}

	/**
	 * Parse the template file and return it as string
	 *
	 * @return string The template markup
	 */
	public function parse()
	{
		if ($this->strTemplate == '')
		{
			return '';
		}

		// HOOK: add custom parse filters
		if (isset($GLOBALS['TL_HOOKS']['parseTemplate']) && \is_array($GLOBALS['TL_HOOKS']['parseTemplate']))
		{
			foreach ($GLOBALS['TL_HOOKS']['parseTemplate'] as $callback)
			{
				$this->import($callback[0]);
				$this->{$callback[0]}->{$callback[1]}($this);
			}
		}

		return $this->inherit();
	}

	/**
	 * Parse the template file and print it to the screen
	 *
	 * @deprecated Deprecated since Contao 4.0, to be removed in Contao 5.0.
	 *             Use Template::getResponse() instead.
	 */
	public function output()
	{
		@trigger_error('Using Template::output() has been deprecated and will no longer work in Contao 5.0. Use Template::getResponse() instead.', E_USER_DEPRECATED);

		$this->compile();

		header('Content-Type: ' . $this->strContentType . '; charset=' . \Config::get('characterSet'));

		echo $this->strBuffer;

		// Flush the output buffers (see #6962)
		$this->flushAllData();
	}

	/**
	 * Return a response object
	 *
	 * @return Response The response object
	 */
	public function getResponse()
	{
		$this->compile();

		$response = new Response($this->strBuffer);
		$response->headers->set('Content-Type', $this->strContentType . '; charset=' . Config::get('characterSet'));

		return $response;
	}

	/**
	 * Return a route relative to the base URL
	 *
	 * @param string $strName   The route name
	 * @param array  $arrParams The route parameters
	 *
	 * @return string The route
	 */
	public function route($strName, $arrParams=array())
	{
		$strUrl = \System::getContainer()->get('router')->generate($strName, $arrParams);
		$strUrl = substr($strUrl, \strlen(\Environment::get('path')) + 1);

		return ampersand($strUrl);
	}

	/**
	 * Compile the template
	 *
	 * @internal Do not call this method in your code. It will be made private in Contao 5.0.
	 */
	protected function compile()
	{
		if (!$this->strBuffer)
		{
			$this->strBuffer = $this->parse();
		}

		// Minify the markup
		$this->strBuffer = $this->minifyHtml($this->strBuffer);
	}

	/**
	 * Return the debug bar string
	 *
	 * @deprecated Deprecated since Contao 4.0, to be removed in Contao 5.0.
	 */
	protected function getDebugBar()
	{
		@trigger_error('Using Template::getDebugBar() has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);
	}

	/**
	 * Minify the HTML markup preserving pre, script, style and textarea tags
	 *
	 * @param string $strHtml The HTML markup
	 *
	 * @return string The minified HTML markup
	 */
	public function minifyHtml($strHtml)
	{
		// The feature has been disabled
		if (!\Config::get('minifyMarkup') || \Config::get('debugMode'))
		{
			return $strHtml;
		}

		// Split the markup based on the tags that shall be preserved
		$arrChunks = preg_split('@(</?pre[^>]*>)|(</?script[^>]*>)|(</?style[^>]*>)|( ?</?textarea[^>]*>)@i', $strHtml, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

		$strHtml = '';
		$blnPreserveNext = false;
		$blnOptimizeNext = false;
		$strType = null;

		// Check for valid JavaScript types (see #7927)
		$isJavaScript = function ($strChunk)
		{
			$typeMatch = array();

			if (preg_match('/\stype\s*=\s*(?:(?J)(["\'])\s*(?<type>.*?)\s*\1|(?<type>[^\s>]+))/i', $strChunk, $typeMatch) && !\in_array(strtolower($typeMatch['type']), static::$validJavaScriptTypes))
			{
				return false;
			}

			if (preg_match('/\slanguage\s*=\s*(?:(?J)(["\'])\s*(?<type>.*?)\s*\1|(?<type>[^\s>]+))/i', $strChunk, $typeMatch) && !\in_array('text/' . strtolower($typeMatch['type']), static::$validJavaScriptTypes))
			{
				return false;
			}

			return true;
		};

		// Recombine the markup
		foreach ($arrChunks as $strChunk)
		{
			if (strncasecmp($strChunk, '<pre', 4) === 0 || strncasecmp(ltrim($strChunk), '<textarea', 9) === 0)
			{
				$blnPreserveNext = true;
			}
			elseif (strncasecmp($strChunk, '<script', 7) === 0)
			{
				if ($isJavaScript($strChunk))
				{
					$blnOptimizeNext = true;
					$strType = 'js';
				}
				else
				{
					$blnPreserveNext = true;
				}
			}
			elseif (strncasecmp($strChunk, '<style', 6) === 0)
			{
				$blnOptimizeNext = true;
				$strType = 'css';
			}
			elseif ($blnPreserveNext)
			{
				$blnPreserveNext = false;
			}
			elseif ($blnOptimizeNext)
			{
				$blnOptimizeNext = false;

				// Minify inline scripts
				if ($strType == 'js')
				{
					$objMinify = new Minify\JS();
					$objMinify->add($strChunk);
					$strChunk = $objMinify->minify();
				}
				elseif ($strType == 'css')
				{
					$objMinify = new Minify\CSS();
					$objMinify->add($strChunk);
					$strChunk = $objMinify->minify();
				}
			}
			else
			{
				// Remove line indentations and trailing spaces
				$strChunk = str_replace("\r", '', $strChunk);
				$strChunk = preg_replace(array('/^[\t ]+/m', '/[\t ]+$/m', '/\n\n+/'), array('', '', "\n"), $strChunk);
			}

			$strHtml .= $strChunk;
		}

		return trim($strHtml);
	}

	/**
	 * Generate the markup for a style sheet tag
	 *
	 * @param string $href  The script path
	 * @param string $media The media type string
	 *
	 * @return string The markup string
	 */
	public static function generateStyleTag($href, $media=null)
	{
		return '<link rel="stylesheet" href="' . $href . '"' . (($media && $media != 'all') ? ' media="' . $media . '"' : '') . '>';
	}

	/**
	 * Generate the markup for inline CSS code
	 *
	 * @param string $script The CSS code
	 *
	 * @return string The markup string
	 */
	public static function generateInlineStyle($script)
	{
		return '<style>' . $script . '</style>';
	}

	/**
	 * Generate the markup for a JavaScript tag
	 *
	 * @param string  $src   The script path
	 * @param boolean $async True to add the async attribute
	 *
	 * @return string The markup string
	 */
	public static function generateScriptTag($src, $async=false)
	{
		return '<script src="' . $src . '"' . ($async ? ' async' : '') . '></script>';
	}

	/**
	 * Generate the markup for an inline JavaScript
	 *
	 * @param string $script The JavaScript code
	 *
	 * @return string The markup string
	 */
	public static function generateInlineScript($script)
	{
		return '<script>' . $script . '</script>';
	}

	/**
	 * Generate the markup for an RSS feed tag
	 *
	 * @param string $href   The script path
	 * @param string $format The feed format
	 * @param string $title  The feed title
	 *
	 * @return string The markup string
	 */
	public static function generateFeedTag($href, $format, $title)
	{
		return '<link type="application/' . $format . '+xml" rel="alternate" href="' . $href . '" title="' . \StringUtil::specialchars($title) . '">';
	}

	/**
	 * Flush the output buffers
	 *
	 * @deprecated Deprecated since Contao 4.0, to be removed in Contao 5.0.
	 */
	public function flushAllData()
	{
		@trigger_error('Using Template::flushAllData() has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

		if (\function_exists('fastcgi_finish_request'))
		{
			fastcgi_finish_request();
		}
		elseif (PHP_SAPI !== 'cli')
		{
			$status = ob_get_status(true);
			$level = \count($status);

			while ($level-- > 0 && (!empty($status[$level]['del']) || (isset($status[$level]['flags']) && ($status[$level]['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE) && ($status[$level]['flags'] & PHP_OUTPUT_HANDLER_FLUSHABLE))))
			{
				ob_end_flush();
			}

			flush();
		}
	}
}
