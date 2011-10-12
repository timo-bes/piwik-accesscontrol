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
class Piwik_AccessControl extends Piwik_Plugin
{
	
	/** The parsed set of rules for the current report */
	private $rules;
	
	/** The column that holds the label */
	private $labelColumn;
	
	/** Whether to take the label from metadata */
	private $labelFromMetadata;
	
	/** General plugin info */
	public function getInformation()
	{
		return array(
			'description' => Piwik_Translate('AccessControl_PluginDescription'),
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'version' => Piwik_Version::VERSION,
			'translationAvailable' => true
		);
	}

	/** Use some hooks */
	public function getListHooksRegistered()
	{
		return array(
			'AdminMenu.add' => 'addMenu',
			'AssetManager.getJsFiles' => 'getJsFiles',
        	'AssetManager.getCssFiles' => 'getCssFiles',
			'API.Proxy.processReturnValue' => 'processApiReturnValue'
		);
	}
	
	/** Add tab to admin menu */
	public function addMenu()
	{
		Piwik_AddAdminMenu('AccessControl_AccessControl', 
				array('module' => 'AccessControl', 'action' => 'index'),
				Piwik::isUserIsSuperUser(), $order = 4);		
	}
	
	/** Add JavaScript */
    public function getJsFiles($notification) 
	{
		$jsFiles = &$notification->getNotificationObject();
		$jsFiles[] = 'plugins/AccessControl/templates/AccessControl.js';
	}

	/** Add CSS */
    public function getCssFiles($notification) 
	{
		$cssFiles = &$notification->getNotificationObject();
		$cssFiles[] = 'plugins/AccessControl/templates/AccessControl.css';
	}
	
	/**
	 * Enforce access restrictions on API level
	 * Remove restricted rows from the datatables
	 */
	public function processApiReturnValue($notification)
	{
		$info = $notification->getNotificationInfo();
		$returnValue = &$notification->getNotificationObject();
		
		$this->rules = null;
		
		if (!($returnValue instanceof Piwik_DataTable)
				&& !($returnValue instanceof Piwik_DataTable_Array))
		{
			// if notification object is not a datatable, do nothing
			return;
		}
		
		if ($info['module'] == 'UserCountry'
				&& ($info['action'] == 'getCountry' || $info['action'] == 'getContinent'))
		{
			$this->labelColumn = 'code';
			$this->labelFromMetadata = true;
		}
		else
		{
			$this->labelColumn = 'label';
			$this->labelFromMetadata = false;
		}
		
		if ($returnValue instanceof Piwik_DataTable_Array)
		{
			// if it's a datatable array, loop through each
			foreach ($returnValue->getArray() as $table)
			{
				$this->processDataTable($table, $info);
			}
		}
		else
		{
			// handle a regular datatable
			$this->processDataTable($returnValue, $info);
		}
	}
	
	/**
	 * Enforce access restrictions on a datatable object
	 */
	public function processDataTable($dataTable, &$info)
	{
		if (isset($info['parameters']['idSubtable'])
				&& $info['parameters']['idSubtable'] > 0
				&& $info['module'] != 'Actions')
		{
			// nested report usually have two api methods
			// one for the main report and one for the subtables
			// (e.g. search engines and keywords for each search engine)
			// in this case, we use self::subTableIsDenied (see comment there) 
			if ($this->subTableIsDenied($info))
			{
				$dataTable->deleteRowsOffset(0);
				return;
			}
		}
		
		// if we have a root or actions report, load the restrictions
		if ($this->rules == null)
		{
			$apiMethod = $info['module'].'.'.$info['action'];
			
			$userName = Piwik::getCurrentUserLogin();
			$config = Piwik_AccessControl_API::getInstance()
						->getAccessConfiguration($apiMethod, $userName, $info['parameters']['idSite']);
			
			$this->rules = $this->parseAccessConfig($config);
		}
		
		// apply rules
		if (isset($this->rules['labels']))
		{
			$this->enforceLabelRestrictions($dataTable, $this->rules['labels'], $info);
		}
		
		if (isset($this->rules['metrics']))
		{
			$this->enforceMetricRestrictions($dataTable, $this->rules['metrics']);
		}
	}
	
	/**
	 * This method checks whether access to the root label is denied in a
	 * nested report with two methods.
	 * In this case, the parent labels are not archived with the datatable
	 * but we can load the root report and search for the idSubtable.
	 * If it's not there, it has been removed by this plugin (i.e. access denied).
	 */
	private function subTableIsDenied(&$info) {
		$module = $info['module'];
		$subTableAction = $info['action'];
		
		// get the main action of the root report
		$meta = Piwik_API_API::getInstance()->getReportMetadata($info['parameters']['idSite']);
		foreach ($meta as &$report)
		{
			if (isset($report['actionToLoadSubTables'])
					&& $report['actionToLoadSubTables'] == $subTableAction)
			{
				$action = $report['action'];
			}
		}
		
		// load the parent report
		$className = 'Piwik_'.$module.'_API';
		$params = array(
			'format' => 'original',
			'disable_generic_filters' => 1,
			'idSite' => $info['parameters']['idSite'],
			'period' => $info['parameters']['period'],
			'date' => $info['parameters']['date'],
		);
		
		$parentReport = Piwik_API_Proxy::getInstance()->call($className, $action, $params);
		
		// search the idSubtable
		$currentId = $info['parameters']['idSubtable'];
		foreach ($parentReport->getRows() as $row)
		{
			$subId = $row->getIdSubDataTable();
			if ($subId == $currentId)
			{
				// idSubtable found: access allowed
				return false;
			}
		}
		
		// idSubtable not found: has been removed on root level (restriction in effect)
		return true;
	}
	
	/** Parse the access configuration into an array */
	private function parseAccessConfig($config)
	{
		$rules = array();
		
		foreach (explode(';;;', $config) as $rule) 
		{
			$hit = preg_match('/^(allow|deny) (label |metric |)(.*)$/i', $rule, $matches);
			if (!$hit) continue;
			
			if ($matches[2] == '')
			{
				$matches[2] = 'label';
			}
			else
			{
				$matches[2] = trim($matches[2]);
			}
			
			$rules[$matches[2].'s'][] = array(
				'allow' => (strtolower($matches[1]) == 'allow'),
				'value' => trim($matches[3])
			);
		}
		
		return $rules;
	}
	
	/**
	 * Enforce the restrictions on labels
	 * 
	 * @param Piwik_DataTable $dataTable
	 * @param array $rules
	 * @param array $info from notification
	 */
	private function enforceLabelRestrictions($dataTable, &$rules, &$info)
	{
		// actions report have the parent datatable IDs and labels stored in the archive
		$parents = $dataTable->getParents();
		if (count($parents) > 0)
		{
			// if we are not on root level, apply rules to root label
			$rootLabel = $parents[0][1];
			if (!$rootLabel || $this->isDenied($rootLabel, $rules))
			{
				$dataTable->deleteRowsOffset(0);
			}
			return;
		}
		else if (isset($info['parameters']['idSubtable']) && $info['parameters']['idSubtable'] > 0)
		{
			// we have a subtable from a nested actions report but no parents are available.
			// this happens if the parents array was not archived.
			// in this case, we don't do anything with the subtable.
			// this way, everything works as expected but there is the minor security risk of
			// an attacker guessing the subtable IDs. in order to avoid this, archive everything
			// again with the parent IDs.
			return;
		}
		
		// if report is not nested or we are on root level. apply rules to all labels.
		foreach ($dataTable->getRows() as $index => $row)
		{
			// keep summary row
			if ($index == Piwik_DataTable::ID_SUMMARY_ROW) continue;
			
			if ($this->labelFromMetadata)
			{
				$label = $row->getMetadata($this->labelColumn);
			}
			else
			{
				$label = $row->getColumn($this->labelColumn);
			}
			
			if ($label === false || $this->isDenied($label, $rules))
			{
				$dataTable->deleteRow($index);
			}
		}	
	}
	
	/**
	 * Enforce the restrictions on metrics
	 * 
	 * @param Piwik_DataTable $dataTable
	 * @param array $rules
	 */
	private function enforceMetricRestrictions($dataTable, &$rules)
	{
		foreach ($rules as $rule)
		{
			if (!$rule['allow'])
			{
				foreach($dataTable->getRows() as $row)
				{
					$row->setColumn($rule['value'], 0);
				}
			}
		}	
	}
	
	/** Check whether a value is denied by a set of rules */
	private function isDenied($value, &$rules)
	{
		$denied = false;
		$value = trim($value);
		
		foreach ($rules as &$rule)
		{
			if (!isset($rule['regex']))
			{
				$regex = '/^'.preg_quote($rule['value'], '/').'$/i';
				$rule['regex'] = str_replace('\*', '(?:.*?)', $regex);
			}
			
			if (preg_match($rule['regex'], $value, $matches))
			{
				$denied = !$rule['allow'];
			}
		}
		
		return $denied;
	}
	
}
