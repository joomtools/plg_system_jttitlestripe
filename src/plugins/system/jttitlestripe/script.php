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
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
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
			$errorRemoveOldUpdateserver = $this->removeOldUpdateserver($installer->getName());

			$pluginPath = [];
			$pluginPath[] = JPATH_PLUGINS;
			$pluginPath[] = $installer->get('group', '');
			$pluginPath[] = $installer->getElement();
			$pluginPath = implode($pluginPath);

			$deletes = [];

			$deletes['folder'] = array();

			$deletes['file'] = array(
				// Before 3.0.0-rc4
				JPATH_ROOT . '/administrator/language/de-DE/de-DE.plg_system_jttitlestripe.ini',
				JPATH_ROOT . '/administrator/language/de-DE/de-DE.plg_system_jttitlestripe.sys.ini',
				JPATH_ROOT . '/administrator/language/en-GB/en-GB.plg_system_jttitlestripe.ini',
				JPATH_ROOT . '/administrator/language/en-GB/en-GB.plg_system_jttitlestripe.ini',
			);

			$errorDeleteOrphans = false;

			foreach ($deletes as $key => $orphans)
			{
				$errorDeleteOrphans = $this->deleteOrphans($key, $orphans);
			}

			if ($errorRemoveOldUpdateserver || $errorDeleteOrphans)
			{
				return false;
			}
		}
	}

	/**
	 * Remove the old Updateserver
	 *
	 * @param   string  $name  Installer name (pluginname)
	 *
	 * @return   boolean    False on success (false = no errors)
	 * @throws   Exception
	 *
	 * @since   3.0.0
	 */
	protected function removeOldUpdateserver($name)
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
					->where($db->quoteName('name') . ' = ' . $db->quote($name))
			)->loadResult();

			// Skip delete when id doesn’t exists
			if (!$id)
			{
				return false;
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

			return true;
		}

		return false;
	}

	/**
	 * @param   string  $type     Wich type are orphans of (file or folder)
	 * @param   array   $orphans  Array of files or folders to delete
	 *
	 * @return   boolean    False on success (false = no errors)
	 * @throws   Exception
	 *
	 * @since   3.0.0
	 */
	private function deleteOrphans($type, array $orphans)
	{
		$app    = Factory::getApplication();
		$return = false;

		foreach ($orphans as $item)
		{
			if ($type == 'folder')
			{
				if (is_dir($item))
				{
					if (Folder::delete($item) === false)
					{
						$app->enqueueMessage(Text::sprintf('PLG_JT_TITLESTRIPE_NOT_DELETED', $item) . '<br />');

						$return = true;
					}
				}
			}
			if ($type == 'file')
			{
				if (is_file($item))
				{
					if (File::delete($item) === false)
					{
						$app->enqueueMessage(Text::sprintf('PLG_JT_TITLESTRIPE_NOT_DELETED', $item) . '<br />');

						$return = true;
					}
				}
			}
		}

		return $return;
	}
}
