<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: $
 * 
 * @category Piwik_Plugins
 * @package Piwik_AccessControl
 */

/**
 * @package Piwik_AccessControl
 */
class Piwik_AccessControl_API
{
	
	static private $instance = null;
	
	/** @return Piwik_AccessControl_API */
	static public function getInstance()
	{
		if (self::$instance == null)
		{
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/** Get access configuration for a report and a user */
	public function getAccessConfiguration($apiMethod, $userName, $idSite)
	{
		Piwik::checkUserIsSuperUserOrTheUser($userName);
		$preferenceName = $this->getUserPrefName($apiMethod, $idSite);
		return Piwik_UsersManager_API::getInstance()->getUserPreference($userName, $preferenceName);
	}
	
	/** Set access configuration for a report and a user */
	public function setAccessConfiguration($apiMethod, $userName, $config, $idSite)
	{
		Piwik::checkUserIsSuperUser();
		
		if ($config == 'none')
		{
			$config = '';
		}
		
		$preferenceName = $this->getUserPrefName($apiMethod, $idSite);
		Piwik_UsersManager_API::getInstance()->setUserPreference($userName, $preferenceName, $config);
	}
	
	/** Generate the name of the user preference storing the settings */
	private function getUserPrefName($apiMethod, $idSite)
	{
		return 'access_api_'.$idSite.'_'.$apiMethod;
	}

}