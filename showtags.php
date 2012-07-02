<?php

/**
 * Content Showtags
 * Author: Roberto Segura - roberto@phproberto.com - www.phproberto.com
 * Copyright (c) 2012 Roberto Segura. All Rights Reserved.
 * License: GNU/GPL 2, http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Showtags for Joomla 2.5 by Roberto Segura
 *
 */

//no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/**
 * Showtags content plugin
 * @author Roberto Segura - phproberto.com
 *
 */
class plgContentShowtags extends JPlugin {
    
    const PLUGIN_NAME = 'showtags';
    
    private $_params = null;
    
    // paths
    private $_pathPlugin = null;
    private $_pathBoilerplates = null;
    private $_pathCurrentBoilerplate = null;
    
    // urls
    private $_urlPlugin = null;
    private $_urlPluginJs = null;
    private $_urlPluginCss = null;
    
    // url parameters
    private $_option = null;
    private $_view = null;
    private $_id = null;
    
    // array of tags
    private $_tags = array();
    
    function __construct( &$subject ){
        
        parent::__construct( $subject );
    
        // Load plugin parameters
        $this->_plugin = JPluginHelper::getPlugin( 'content', 'showtags' );
        $this->_params = new JRegistry( $this->_plugin->params );
        
        // init folder structure
        $this->_initFolders();
        
        // load plugin language
        $this->loadLanguage ('plg_'.$this->_plugin->type.'_'.$this->_plugin->name, JPATH_ADMINISTRATOR);
    }
    
    
    /**
     * Parse the tags before displaying content
     * @param string $context
     * @param object $article
     * @param object $params
     * @param integer $limitstart
     */
    public function onContentBeforeDisplay($context, &$article, &$params, $limitstart = 0 ) {
        
        // required objects
        $app = JFactory::getApplication();
        $document = JFactory::getDocument();
        $jinput = $app->input;
        
        // get url parameters
        $this->_option = $jinput->get('option',null);
        $this->_view = $jinput->get('view',null);
        $this->_id = $jinput->get('id',null);

        $this->_article = $article;

        // validate view
        if ($context != 'com_content.article' || !$this->_validateView() || !isset($article->metakey) || empty($article->metakey)) {
            return;
        }
      
        $this->_tags = explode(',',$article->metakey);
        $parsedTags = $this->_parseTags();
        
        $position = $this->_params->get('position', 'before');
        $field = ($this->_view == 'category') ? 'introtext' : 'text';

        switch ($position) {
            case 'before':
                $article->{$field} = $parsedTags . $article->{$field};
                break;
            case 'after':
                $article->{$field} .= $parsedTags;
                break;
            // create a new article property called showtags with the parsed tags
            case 'property':
                $article->showtags = $parsedTags;
                break;
            default:
                $article->{$field} = $parsedTags . $article->{$field} . $parsedTags;
            break;
        }
        $document->addStyleSheet($this->_urlPluginCss.'/showtags.css');
    }
    
    private function _initFolders() {
        
        // paths
        $this->_pathPlugin = JPATH_PLUGINS . DIRECTORY_SEPARATOR . "content" . DIRECTORY_SEPARATOR . self::PLUGIN_NAME;
        
        // urls
        $this->_urlPlugin = JURI::root()."plugins/content/showtags";
        $this->_urlPluginJs = $this->_urlPlugin . "/js";
        $this->_urlPluginCss = $this->_urlPlugin . "/css";
    }
	
	private function _validateView() {
	    
	    if ($this->_option == 'com_content') {

            // get active categories
            $activeCategories = $this->_params->get('active_categories','');
            if (!is_array($activeCategories)) {
                $activeCategories = array($activeCategories);
            }

            // article view enabled?
            if ($this->_view == 'article' && $this->_id && $this->_params->get('enable_article',0)) {

                // category filter
                if ($activeCategories && $this->_article
                    && ( in_array('-1', $activeCategories) || in_array($this->_article->catid, $activeCategories) )) {
                    return true;
                }
            }

            // category view enabled?
            if ($this->_view == 'category' && $this->_id && $this->_params->get('enable_category',0)) {

                // category filter
                if ($activeCategories
                    && ( in_array('-1', $activeCategories) || in_array($this->_id, $activeCategories) )) {
                    return true;
                }

            }
	    }
	    return false;
	}
	
	private function _parseTags() {

        // default value
	    $html = '';

        // parse parameters
	    $parentContainer = $this->_params->get('container','div');
	    $customCss = $this->_params->get('css_class',null);
	    $parseMode = $this->_params->get('format','ulli');
        $wordlistSeparator = $this->_params->get('wordlist_separator',',');
        $menulink= $this->_params->get('menulink',null);
        $taxonomyActive = $this->_params->get('taxonomy_enabled',0);
	    
	    if ($this->_tags) {
	        $html .= "\n<".$parentContainer." class=\"content-showtags ".$customCss."\">";
	        if ($parseMode == 'ulli') {
	            $html .= "\n\t<ul>";
	        }
	        $html .= '<span>'.JText::_('PLG_CONTENT_SHOWTAGS_TITLE').' </span>';
	        $i = 0;
	        foreach ($this->_tags as $tag) {
                
                // clear tag empty spaces
	            $tag = trim($tag);
	            
                // generate the url
                if ($taxonomyActive) {
                    $url = 'index.php?option=com_taxonomy&tag=' . $tag;
                } else {
	                $url = 'index.php?option=com_search&searchword='. $tag . '&ordering=&searchphrase=all';
                }
                
                // force Itemid?
                if ($menulink) {
                    $url .= "&Itemid=".$menulink;
                }
                
                // build the route
                $url = JRoute::_(JFilterOutput::ampReplace($url));
                
	            $tag = '<a href="'.$url.'" >'.$tag.'</a>';
	            if ($parseMode == 'ulli') {
	                $html .= "\n\t\t<li>".$tag."</li>";
	            } else {
	                if ($i) {
	                    $html .= $wordlistSeparator . ' ';
	                }
	                $html .= $tag;
	            }
	            $i++;
	        }
	        if ($parseMode) {
	            $html .= "\n\t</ul>";
	        }
	        $html .= "\n</".$parentContainer.">\n";
	    }
	    return $html;
	}
	
}
?>