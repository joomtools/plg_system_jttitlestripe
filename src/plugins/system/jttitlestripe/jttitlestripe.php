<?php
/**
 * @package          Joomla.Plugin
 * @subpackage       System.Jttitlestripe
 *
 * @author           Guido De Gobbis <support@joomtools.de>
 * @copyright    (c) 2017 JoomTools.de - All rights reserved.
 * @license          GNU General Public License version 3 or later
 **/

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @package      Joomla.Plugin
 * @subpackage   System.Jttitlestripe
 *
 * @since   3.8
 */
class PlgSystemJttitlestripe extends JPlugin
{
	/**
	 * A Registry object holding the parameters for the plugin
	 *
	 * @var    Registry
	 * @since  1.5
	 */
	public $params = null;

	/**
	 * @var array
	 * @since version
	 */
	protected $stripe = array();

	/**
	 * @var array
	 * @since version
	 */
	protected $breakStripe = array();

	/**
	 * @var array
	 * @since version
	 */
	protected $tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');

	/**
	 * @var null
	 * @since version
	 */
	protected $setCss = null;

	/**
	 * @var null
	 * @since version
	 */
	protected $css = null;

	/**
	 * @var null
	 * @since version
	 */
	protected $app = null;

	/**
	 * The name of the plugin
	 *
	 * @var    string
	 * @since  1.5
	 */
	protected $_name = null;

	/**
	 * The plugin type
	 *
	 * @var    string
	 * @since  1.5
	 */
	protected $_type = null;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	public function onAfterInitialise()
	{
		if ($this->app->isAdmin())
		{
			return;
		}

		JLoader::register('JDocumentHTML', JPATH_PLUGINS . '/system/jttitlestripe/assets/HtmlDocument.php');

		$stripe      = explode(',', $this->params->get('stripe'));
		$breakStripe = explode(',', $this->params->get('breakStripe'));
		$tags        = explode(',', $this->params->get('tags'));

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

		$this->setCss = $this->params->get('css');

		if ($this->setCss)
		{
			$this->css = 'plugins/' . $this->_type . '/' . $this->_name . '/assets/stripe.css';
		}
	}

	public function onRenderModule(&$module, $attribs)
	{
		if ($this->app->isAdmin())
		{
			return;
		}

		if ($this->params->get('inModule', 0) == 0 && $module->module != 'mod_breadcrumbs')
		{
			return;
		}

		$moduleTitle   = str_replace('&nbsp;', ' ', $module->title);
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
		$sub         = ' sub';

		foreach ($breakStripe as $value)
		{
			$title = str_replace($value, '|||&n|', $title);
		}

		foreach ($stripe as $value)
		{
			$title = str_replace($value . ' ', '||&nbsp;', $title);
			$title = str_replace(' ' . $value, '&nbsp;||', $title);
		}

		if (strpos($title, '||'))
		{
			list($mainTitle, $_subTitle) = explode('||', $title, 2);
			$subTitle = ($_subTitle != '' && $_subTitle != '|&n|') ? explode('||', $_subTitle) : false;
		}
		else
		{
			$mainTitle = $title;
			$subTitle  = false;
			$sub       = '';
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
		$return          = '<span class="jttitlestripe">' . $returnMainTitle . $returnSubTitle . '</span>';

		return $return;
	}

	/**
	 * @param      $article
	 * @param bool $clear
	 *
	 * @return string
	 *
	 * @since version
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
		else
		{
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
	}

	/**
	 * @param $title
	 *
	 * @return string
	 *
	 * @since version
	 */
	protected function clearMultilineTitle($title)
	{
		$stripe      = $this->stripe;
		$breakStripe = $this->breakStripe;

		foreach ($breakStripe as $value)
		{
			$title = str_replace($value, ' ', $title);
		}

		foreach ($stripe as $value)
		{
			$title = str_replace($value . ' ', '', $title);
			$title = str_replace(' ' . $value, ' ', $title);
		}

		if (strpos($title, '||'))
		{
			list($mainTitle, $_subTitle) = explode('||', $title, 2);
			$subTitle = ($_subTitle != '' && $_subTitle != '\n') ? explode('||', $_subTitle) : false;
		}
		else
		{
			$mainTitle = $title;
			$subTitle  = false;
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
		}

		$document->setTitle($title);

		if ($this->app->isAdmin())
		{
			return;
		}

		$template        = $document->getTemplateBuffer();
		$component       = $document->getBuffer('component');
		$findInComponent = $this->_checkStripe($component);

		if ($findInComponent)
		{
			$component = $this->_renderXML($component);
			$document->setBuffer($component, 'component');
		}

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

		if ($findInComponent || $findInTemplate || ($this->setCss && $this->css))
		{
			$base = str_replace('/administrator', '', JURI::base(true));
			$document->addStylesheet($base . '/' . $this->css);
		}
	}
}
