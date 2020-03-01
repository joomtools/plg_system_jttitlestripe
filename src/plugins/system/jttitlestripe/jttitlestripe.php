<?php
/**
 * @package      Joomla.Plugin
 * @subpackage   System.Jttitlestripe
 *
 * @author       Guido De Gobbis <support@joomtools.de>
 * @copyright    2020 JoomTools.de - All rights reserved.
 * @license      GNU General Public License version 3 or later
 **/

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Profiler\Profiler;

/**
 * @package      Joomla.Plugin
 * @subpackage   System.Jttitlestripe
 *
 * @since        3.0.0
 */
class PlgSystemJttitlestripe extends CMSPlugin
{
	/**
	 * @var     boolean
	 * @since   3.0.0
	 */
	protected $debug = false;

	/**
	 * @var     array
	 * @since   3.0.0
	 */
	protected $stripe = array();

	/**
	 * @var     array
	 * @since   3.0.0
	 */
	protected $breakStripe = array();

	/**
	 * @var     array
	 * @since   3.0.0
	 */
	protected $tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');

	/**
	 * @var     null
	 * @since   3.0.0
	 */
	protected $setCss = null;

	/**
	 * @var     null
	 * @since   3.0.0
	 */
	protected $css = null;

	/**
	 * @var     CMSApplication
	 * @since   3.0.0
	 */
	protected $app = null;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var     boolean
	 * @since   3.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * After Initialise Event.
	 * Defines basic variables on initalise system.
	 *
	 * @return   void
	 *
	 * @since    3.0.0
	 */
	public function onAfterInitialise()
	{
		$debug  = empty($this->params->get('debug', 0));

		if (Factory::getConfig()->get('debug', false))
		{
			$debug = true;
		}

		$this->debug = $debug;

		JLoader::register('JDocumentHTML', JPATH_PLUGINS . '/system/jttitlestripe/assets/HtmlDocument.php');

		$stripe      = explode(',', $this->params->get('stripe', '||'));
		$breakStripe = explode(',', $this->params->get('breakStripe', '|n|'));
		$tags        = explode(',', $this->params->get('tags', ''));

		foreach ($stripe as $value)
		{
			$this->stripe[] = trim($value);
		}

		foreach ($breakStripe as $value)
		{
			$this->breakStripe[] = trim($value);
		}

		foreach ($tags as $value)
		{
			$this->tags[] = strtolower(trim($value));
		}

		$this->setCss = !empty($this->params->get('css', 1));

		if ($this->setCss)
		{
			$this->css = 'plugins/' . $this->_type . '/' . $this->_name . '/assets/stripe.css';
		}
	}

	public function onRenderModule(&$module, $attribs)
	{
		if ($this->app->isClient('administrator'))
		{
			return;
		}

		if ($this->params->get('inModule', 0) == 0 && $module->module != 'mod_breadcrumbs')
		{
			return;
		}

		$moduleTitle = str_replace('&nbsp;', ' ', $module->title);

		if ($this->debug)
		{
			// Set starttime for process total time
			$startTime = microtime(1);

			Profiler::getInstance('JT - Titlestripe (onRenderModule -> ' . $moduleTitle . ')')
				->setStart($startTime);
		}

		$moduleContent = str_replace('&nbsp;', ' ', $module->content);
		$clear         = ($module->module == 'mod_breadcrumbs') ? true : false;

		$findInTitle   = $this->_checkStripe($moduleTitle);
		$findInContent = $this->_checkStripe($moduleContent);

		$module->title   = ($findInTitle) ? $this->setMultilineTitle($moduleTitle) : $moduleTitle;
		$module->content = ($findInContent) ? $this->_renderXML($moduleContent, $clear) : $moduleContent;

		if (($findInTitle || $findInContent) && !$clear)
		{
			if ($this->setCss && !$this->css)
			{
				$this->css = 'plugins/' . $this->_type . '/' . $this->_name . '/assets/stripe.css';
			}
		}

		if ($this->debug)
		{
			$this->app->enqueueMessage(
				Profiler::getInstance('JT - Titlestripe (onRenderModule -> ' . $moduleTitle . ')')
					->mark('Verarbeitungszeit'),
				'info'
			);
		}
	}

	protected function _checkStripe($string)
	{
		$stripe      = $this->stripe;
		$breakStripe = $this->breakStripe;

		foreach ($stripe as $_stripe)
		{
			if (strpos($string, $_stripe))
			{
				return true;
			}
		}

		foreach ($breakStripe as $_breakStripe)
		{
			if (strpos($string, $_breakStripe))
			{
				return true;
			}
		}

		return false;
	}

	protected function setMultilineTitle($title)
	{
		$stripe      = $this->stripe;
		$breakStripe = $this->breakStripe;
		$subTitle    = false;
		$sub         = '';

		foreach ($breakStripe as $value)
		{
			$title = str_replace($value, '|||&n|', $title);
		}

		foreach ($stripe as $value)
		{
			$title = str_replace($value . ' ', '||&nbsp;', $title);
			$title = str_replace(' ' . $value, '&nbsp;||', $title);
		}

		$mainTitle = $title;

		if (strpos($title, '||'))
		{
			list($mainTitle, $_subTitle) = explode('||', $title, 2);

			$subTitle = ($_subTitle != '' && $_subTitle != '|&n|') ? explode('||', $_subTitle) : false;
			$sub      = ' sub';
		}

		$counter        = 1;
		$break          = '';
		$returnSubTitle = '';

		if ($subTitle)
		{
			foreach ($subTitle as $_title)
			{
				if (strpos($_title, '|&n|') !== false)
				{
					$break  = ' break';
					$_title = substr($_title, 4);
				}
				else
				{
					$returnSubTitle = '';
				}

				$returnSubTitle .= '<span class="subtitle sub-' . ($counter++) . $break . '">' . $_title . '</span>';
			}
		}

		$returnMainTitle = '<span class="maintitle' . $sub . '">' . $mainTitle . '</span>';

		return '<span class="jttitlestripe">' . $returnMainTitle . $returnSubTitle . '</span>';
	}

	/**
	 * @param   string  $article
	 * @param   bool    $clear
	 *
	 * @return   string
	 *
	 * @since   3.0.0
	 */
	protected function _renderXML(&$article, $clear = false)
	{
		$xmlString = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><container>' . $article . '</container>';

		$xml = new DOMDocument;
		libxml_use_internal_errors(true);

		$xml->loadHTML($xmlString);
		libxml_clear_errors();

		$xmlString   = $xml->saveXML($xml->getElementsByTagName('container')->item(0));
		$findStripes = simplexml_load_string($xmlString, 'SimpleXMLElement');

		$this->_findStripes($findStripes, false, $clear);

		$_article = new DOMDocument;
		libxml_use_internal_errors(true);

		$_article->loadXML($findStripes->saveXML());
		libxml_clear_errors();

		$newArticle = $_article->saveHTML();

		preg_match('/<container>(.*?)<\/container>/uxis', $newArticle, $article);

		$article = html_entity_decode($article[1], ENT_QUOTES, 'utf-8');

		return $article;
	}

	protected function _findStripes($xml, $inTag = false, $clear = false)
	{
		if (!$xml instanceof SimpleXMLElement)
		{
			return;
		}

		foreach ($xml as $node)
		{
			$nodeName = strtolower($node->getName());

			$children   = count($node->children());
			$attributes = $node->attributes();

			foreach ($attributes as $attrKey => $attrValue)
			{
				if ($attrKey == 'value' || $nodeName == 'textarea')
				{
					continue;
				}

				if ($this->_checkStripe((string) $attrValue))
				{
					$attributes[$attrKey] = $this->clearMultilineTitle((string) $attrValue);
				}
			}

			if (in_array($nodeName, $this->tags) || $inTag && !$clear)
			{
				if ($this->_checkStripe((string) $node[0]))
				{
					$node[0] = $this->setMultilineTitle(trim((string) $node[0]));
				}

				if ($children >= 1)
				{
					$this->_findStripes($node->children(), true, $clear);
				}
			}
			else
			{
				if ($this->_checkStripe((string) $node[0]))
				{
					$node[0] = $this->clearMultilineTitle(trim((string) $node[0]));
				}
			}

			if ($children >= 1)
			{
				$this->_findStripes($node->children(), $inTag, $clear);
			}
		}
	}

	/**
	 * @param   string  $title
	 *
	 * @return   string
	 *
	 * @since   3.0.0
	 */
	protected function clearMultilineTitle($title)
	{
		$stripe      = $this->stripe;
		$breakStripe = $this->breakStripe;
		$subTitle  = false;

		foreach ($breakStripe as $value)
		{
			$title = str_replace($value, ' ', $title);
		}

		foreach ($stripe as $value)
		{
			$title = str_replace($value . ' ', '', $title);
			$title = str_replace(' ' . $value, ' ', $title);
		}

		$mainTitle = $title;

		if (strpos($title, '||'))
		{
			list($mainTitle, $_subTitle) = explode('||', $title, 2);
			$subTitle = ($_subTitle != '' && $_subTitle != '\n') ? explode('||', $_subTitle) : false;
		}

		$return = '';
		$return .= $mainTitle;

		if ($subTitle)
		{
			foreach ($subTitle as $_title)
			{
				if (strpos($_title, '\n') !== false)
				{
					$return .= ' ';
					$_title = substr($_title, 2);
				}

				$return .= $_title;
			}
		}

		return $return;
	}

	public function onBeforeCompileHead()
	{
		$document = JFactory::getDocument();
		$title    = $document->getTitle();

		if ($this->_checkStripe($title))
		{
			$title = $this->clearMultilineTitle($title);

			$document->setTitle($title);
		}

		if ($this->app->isClient('administrator'))
		{
			return;
		}

		if ($this->debug)
		{
			// Set starttime for process total time
			$startTime = microtime(1);

			Profiler::getInstance('JT - Titlestripe (onBeforeCompileHead -> Content)')->setStart($startTime);
		}

		$component       = $document->getBuffer('component');
		$findInComponent = $this->_checkStripe($component);

		if ($findInComponent)
		{
			$component = $this->_renderXML($component);
			$document->setBuffer($component, 'component');
		}

		if ($this->debug)
		{
			$this->app->enqueueMessage(
				Profiler::getInstance('JT - Titlestripe (onBeforeCompileHead -> Content)')->mark('Verarbeitungszeit'),
				'info'
			);
		}

		if ($this->debug)
		{
			// Set starttime for process total time
			$startTime = microtime(1);

			Profiler::getInstance('JT - Titlestripe (onBeforeCompileHead -> Template)')->setStart($startTime);
		}

		$template       = $document->getTemplateBuffer();
		$findInTemplate = $this->_checkStripe($template);

		if ($findInTemplate)
		{
			preg_match_all('#(<\s*body[^>]*>)(.*?)(<\s*/\s*body>)#siU', $template, $_template, PREG_SET_ORDER);

			$templateBuffer = preg_replace('@<jdoc:include(.*?)/>@uxis', '<include${1}></include>', $_template[0][2]);
			$templateBuffer = $this->_renderXML($templateBuffer);
			$templateBuffer = preg_replace('@<include(.*?)></include>@uxis', '<jdoc:include${1} />', $templateBuffer);
			$templateBuffer = $_template[0][1] . $templateBuffer . $_template[0][3];
			$template       = preg_replace('#(<\s*body[^>]*>)(.*?)(<\s*/\s*body>)#siU', $templateBuffer, $template);

			$document->setTemplateBuffer($template);
		}

		if ($this->debug)
		{
			$this->app->enqueueMessage(
				Profiler::getInstance('JT - Titlestripe (onBeforeCompileHead -> Template)')->mark('Verarbeitungszeit'),
				'info'
			);
		}

		if ($findInComponent || $findInTemplate || ($this->setCss && $this->css))
		{
			HTMLHelper::_('stylesheet', $this->css, array('version' => 'auto'));
		}
	}
}
