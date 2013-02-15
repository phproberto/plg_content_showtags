<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Showtags
 *
 * @author      Roberto Segura <roberto@phproberto.com>
 * @copyright   (c) 2012 Roberto Segura. All Rights Reserved.
 * @license     GNU/GPL 2, http://www.gnu.org/licenses/gpl-2.0.htm
 */

defined('_JEXEC') or die;

JLoader::import('joomla.plugin.plugin');

/**
 * Main plugin class
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.Showtags
 * @since       2.5
 *
 */
class PlgContentShowtags extends JPlugin
{

	const PLUGIN_NAME = 'showtags';

	private $_params = null;

	// Paths
	private $_pathPlugin             = null;

	private $_pathBoilerplates       = null;

	private $_pathCurrentBoilerplate = null;

	// URLs
	private $_urlPlugin    = null;

	private $_urlPluginJs  = null;

	private $_urlPluginCss = null;

	// Array of tags
	private $_tags = array();

	/**
	 * Valid context where the plugin can be triggered
	 *
	 * @var  array
	 */
	private $_validContexts = array(
		'com_content.article',
		'com_content.category',
		'com_content.featured'
	);

	/**
	* Constructor
	*
	* @param   mixed  &$subject  Subject
	*/
	function __construct( &$subject )
	{
		parent::__construct($subject);

		// Load plugin parameters
		$this->_plugin = JPluginHelper::getPlugin('content', 'showtags');
		$this->_params = new JRegistry($this->_plugin->params);

		// Init folder structure
		$this->_initFolders();

		// Load plugin language
		$this->loadLanguage('plg_' . $this->_plugin->type . '_' . $this->_plugin->name, JPATH_ADMINISTRATOR);
	}

	/**
     * Parse the tags before displaying content
     *
     * @param   string   $context     application context
     * @param   object   &$article    the article object
     * @param   object   &$params     parameters
     * @param   integer  $limitstart  limit
     *
     * @return void
     */
	public function onContentBeforeDisplay($context, &$article, &$params, $limitstart = 0 )
	{
		// Required objects
		$jinput   = JFactory::getApplication()->input;

		$view   = $jinput->get('view', null);

		$this->_article = $article;

		// Validate view
		if (!$this->_validateContext($context) || !$this->_validateView()
			|| !isset($article->metakey) || empty($article->metakey))
		{
			return;
		}

		$this->_tags = explode(',', $article->metakey);
		$parsedTags  = $this->_parseTags();
		$position    = $this->_params->get('position', 'before');
		$field       = ($view == 'category' || $view == 'featured') ? 'introtext' : 'text';

		switch ($position)
		{
			case 'before':
				$article->{$field} = $parsedTags . $article->{$field};
				break;

			case 'after':
				$article->{$field} .= $parsedTags;
				break;

			// Create a new article property called showtags with the parsed tags
			case 'property':
				$article->showtags = $parsedTags;
				break;

			default:
				$article->{$field} = $parsedTags . $article->{$field} . $parsedTags;
			break;
		}

		// Load the overridable CSS
		JHtml::stylesheet('plg_content_showtags/showtags.css', false, true, false);
	}

	/**
	 * Initialise folder structure
	 *
	 * @return void
	 */
	private function _initFolders()
	{
		// Paths
		$this->_pathPlugin = JPATH_PLUGINS . DIRECTORY_SEPARATOR . "content" . DIRECTORY_SEPARATOR . self::PLUGIN_NAME;

		// URLs
		$this->_urlPlugin    = JURI::root() . "plugins/content/showtags";
		$this->_urlPluginJs  = $this->_urlPlugin . "/js";
		$this->_urlPluginCss = $this->_urlPlugin . "/css";
	}

	/**
	 * Check if the plugin has to be triggered in the current context
	 *
	 * @param   string  $context  Plugin context
	 *
	 * @return  boolean
	 */
	private function _validateContext($context)
	{
		return in_array($context, $this->_validContexts);
	}

	/**
	 * validate view url
	 *
	 * @return boolean
	 *
	 * @author Roberto Segura
	 * @version 14/08/2012
	 *
	 */
	private function _validateView()
	{
		$jinput   = JFactory::getApplication()->input;

		// Get url parameters
		$option = $jinput->get('option', null);
		$view   = $jinput->get('view', null);
		$id     = $jinput->get('id', null);

		if ($option == 'com_content')
		{
			// Get active categories
			$activeCategories = $this->_params->get('active_categories', '');

			// Force activeCategories format
			if (!is_array($activeCategories))
			{
				$activeCategories = array($activeCategories);
			}

			// Article view enabled?
			if ($view == 'article' && $id && $this->_params->get('enable_article', 1))
			{
				// Category filter
				if ($activeCategories && $this->_article
					&& ( in_array('-1', $activeCategories) || in_array($this->_article->catid, $activeCategories) ))
				{
					return true;
				}
			}

			// Category view enabled?
			if ($view == 'category' && $id && $this->_params->get('enable_category', 1))
			{
				// Category filter
				if ($activeCategories
					&& ( in_array('-1', $activeCategories) || in_array($this->_id, $activeCategories) ))
				{
					return true;
				}

			}

			// Featured view enabled ?
			if ($view == 'featured' && $this->_params->get('enable_featured', 1)
				&& ( in_array('-1', $activeCategories) || in_array($this->_article->catid, $activeCategories) ))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse the tags into HTML
	 *
	 * @return   string  The HTML code with the parsed tags
	 */
	private function _parseTags()
	{
		// Default value
		$html = '';

		// Parse parameters
		$parentContainer   = $this->_params->get('container', 'div');
		$customCss         = $this->_params->get('css_class', null);
		$parseMode         = $this->_params->get('format', 'ulli');
		$wordlistSeparator = $this->_params->get('wordlist_separator', ',');
		$menulink          = $this->_params->get('menulink', null);
		$taxonomyActive    = $this->_params->get('taxonomy_enabled', 0);

		// Render the overridable template
		ob_start();
		require self::getLayoutPath($this->_plugin->type, $this->_plugin->name, $layout = 'default_' . $parseMode);
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Function to get the path to a layout checking overrides.
	 * It's exactly as it's used in the Joomla! Platform 12.2 to easily replace it when available
	 *
	 * @param   string  $type    Plugin type (system, content, etc.)
	 * @param   string  $name    Name of the plugin
	 * @param   string  $layout  The layout name
	 *
	 * @return string  Path where we have to use to call the layout
	 */
	public static function getLayoutPath($type, $name, $layout = 'default')
	{
		$template = JFactory::getApplication()->getTemplate();
		$defaultLayout = $layout;

		if (strpos($layout, ':') !== false)
		{
			// Get the template and file name from the string
			$temp = explode(':', $layout);
			$template = ($temp[0] == '_') ? $template : $temp[0];
			$layout = $temp[1];
			$defaultLayout = ($temp[1]) ? $temp[1] : 'default';
		}

		// Build the template and base path for the layout
		$tPath = JPATH_THEMES . '/' . $template . '/html/plg_' . $type . '_' . $name . '/' . $layout . '.php';
		$bPath = JPATH_BASE . '/plugins/' . $type . '/' . $name . '/tmpl/' . $defaultLayout . '.php';
		$dPath = JPATH_BASE . '/plugins/' . $type . '/' . $name . '/tmpl/' . 'default.php';

		// If the template has a layout override use it
		if (file_exists($tPath))
		{
			return $tPath;
		}
		elseif (file_exists($bPath))
		{
			return $bPath;
		}
		else
		{
			return $dPath;
		}
	}
}
