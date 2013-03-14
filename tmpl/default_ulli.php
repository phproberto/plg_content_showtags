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
?>
<?php if($this->_tags): ?>
	<<?php echo $parentContainer; ?> class="<?php echo $customCss; ?>">
		<ul>
			<span><?php echo JText::_('PLG_CONTENT_SHOWTAGS_TITLE'); ?></span>
			<?php foreach ($this->_tags as $tag): ?>
				<?php
				// Clear tag empty spaces
				$tag = trim($tag);

				// Generate the url
				if ($taxonomyActive)
				{
					$url = 'index.php?option=com_taxonomy&tag=' . $tag;
				}
				else
				{
					$url = 'index.php?option=com_search&searchword=' . $tag . '&ordering=&searchphrase=all';
				}

				// Force Itemid?
				if ($menulink)
				{
					$url .= "&Itemid=" . $menulink;
				}

				// Build the route
				$url = JRoute::_(JFilterOutput::ampReplace($url));
				?>
				<li>
					<a href="<?php echo $url; ?>" ><?php echo $tag; ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</<?php echo $parentContainer; ?>>
<?php endif; ?>