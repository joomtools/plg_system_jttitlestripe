<?php
/**
 * @package      Joomla.Plugin
 * @subpackage   System.Jttitlestripe
 *
 * @author       Guido De Gobbis <support@joomtools.de>
 * @copyright    2020 JoomTools.de - All rights reserved.
 * @license      GNU General Public License version 3 or later
 **/

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Script file of JT - Titlestripe
 *
 * @since  3.0.0
 */
class PlgSystemJttitlestripeInstallerScript
{
	/**
	 * Minimum Joomla version to install
	 *
	 * @var   string
	 *
	 * @since   3.0.0
	 */
	public $minimumJoomla = '3.9';
	/**
	 * Minimum PHP version to install
	 *
	 * @var   string
	 *
	 * @since   3.0.0
	 */
	public $minimumPhp = '7.0';

	/**
	 * Function to act prior to installation process begins
	 *
	 * @param   string      $action     Which action is happening (install|uninstall|discover_install|update)
	 * @param   JInstaller  $installer  The class calling this method
	 *
	 * @return   boolean  True on success
	 * @throws   Exception
	 *
	 * @since   3.0.0
	 */
	public function preflight($action, $installer)
	{
		$app            = Factory::getApplication();
		$errorMinPhp    = false;
		$errorMinJoomla = false;

		Factory::getLanguage()->load('plg_system_jttitlestripe', dirname(__FILE__));

		if (version_compare(PHP_VERSION, $this->minimumPhp, 'lt'))
		{
			$app->enqueueMessage(Text::sprintf('PLG_JT_TITLESTRIPE_MINPHPVERSION', $this->minimumPhp), 'error');

			$errorMinPhp = true;
		}

		if (version_compare(JVERSION, $this->minimumJoomla, 'lt'))
		{
			$app->enqueueMessage(Text::sprintf('PLG_JT_TITLESTRIPE_MINJVERSION', $this->minimumJoomla), 'error');

			$errorMinJoomla = true;
		}

		if ($errorMinPhp || $errorMinJoomla)
		{
			return false;
		}

		if ($action === 'update')
		{
			return $this->removeOldUpdateserver();
		}
	}

	/**
	 * Remove the old Updateserver
	 *
	 * @return   boolean  True on success
	 * @throws   Exception
	 *
	 * @since   3.0.0
	 */
	protected function removeOldUpdateserver()
	{
		$app = Factory::getApplication();
		$db  = JFactory::getDbo();

		try
		{
			// Get the update site ID of the JED Update server
			$id = (int) $db->setQuery(
				$db->getQuery(true)
					->select('update_site_id')
					->from($db->quoteName('#__update_sites'))
					->where($db->quoteName('location') . ' = ' . $db->quote('PLG_JT_TITLESTRIPE_XML_NAME'))
			)->loadResult();

			// Skip delete when id doesn’t exists
			if (!$id)
			{
				return true;
			}

			// Delete from update sites
			$db->setQuery(
				$db->getQuery(true)
					->delete($db->quoteName('#__update_sites'))
					->where($db->quoteName('update_site_id') . ' = ' . (int) $id)
			)->execute();

			// Delete from update sites extensions
			$db->setQuery(
				$db->getQuery(true)
					->delete($db->quoteName('#__update_sites_extensions'))
					->where($db->quoteName('update_site_id') . ' = ' . $id)
			)->execute();
		}
		catch (Exception $e)
		{
			$app->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />');

			return false;
		}
	}
}
