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
 * @package AccessControl
 */
class Piwik_AccessControl_Controller extends Piwik_Controller_Admin
{
	
	/** Metadata for all reports */
	private $reportMeta = array();
	
	/** Array of available reports, ordered by category */
	private $availableReports = array();
	
	/** Index of admin area */
	function index()
	{
		Piwik::checkUserIsSuperUser();
		
		$this->loadMetaData();
		
		$view = Piwik_View::factory('AccessControl');
		$this->setBasicVariablesView($view);
		$view->menu = Piwik_GetAdminMenu();
		
		$view->availableReports = $this->availableReports;
		$view->users = Piwik_UsersManager_API::getInstance()->getUsers();
		
		$sitesApi = Piwik_SitesManager_API::getInstance();
		$sitesConfig = array();
		foreach ($sitesApi->getSitesIdWithAdminAccess() as $idSite)
		{
			$config = $sitesApi->getSiteFromId($idSite);
			$sitesConfig[] = array('idsite' => $config['idsite'], 'name' => $config['name']);
		}
		$view->availableSites = $sitesConfig;
		
		echo $view->render();
	}
	
	/** Load metadata for available reports */
	private function loadMetaData()
	{
		$this->reportMeta = Piwik_API_API::getInstance()->getReportMetadata($this->idSite);
		
		foreach ($this->reportMeta as $meta)
		{
			if (($meta['module'] == 'Actions' &&
				 		($meta['action'] == 'getEntryPageUrls' || $meta['action'] == 'getExitPageUrls'))
					|| isset($meta['parameters']))
			{
				// ignore entry and exit pages reports (they are covered by the rules for the regular pages report)
				// ignore reports with parameters (they are covered by the main report)
				continue;
			}
			
			$this->availableReports[$meta['category']][] = array(
				'name' => $meta['name'],
				'method' => $meta['module'].'.'.$meta['action']
			);
		}
	}
	
}
