<?php
/*****************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Claus Due <claus@wildside.dk>, Wildside A/S
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 *****************************************************************/

/**
 * Page Configuration Provider
 *
 * @author Claus Due <claus@wildside.dk>, Wildside A/S
 * @package Fluidpages
 * @subpackage Provider
 */
class Tx_Fluidpages_Provider_PageProvider extends Tx_Flux_Provider_AbstractProvider implements Tx_Flux_Provider_ProviderInterface {

	/**
	 * @var string
	 */
	protected $tableName = 'pages';

	/**
	 * @var string
	 */
	protected $parentFieldName = 'pid';

	/**
	 * @var string
	 */
	protected $fieldName = 'tx_fed_page_flexform';

	/**
	 * @var string
	 */
	protected $subFieldName = 'tx_fed_page_flexform_sub';

	/**
	 * @var string
	 */
	protected $currentFieldName = NULL;

	/**
	 * @var string
	 */
	protected $extensionKey = 'fluidpages';

	/**
	 * @var string
	 */
	protected $configurationSectionName = 'Configuration';

	/**
	 * @var t3lib_flexformtools
	 */
	protected $flexformTool;

	/**
	 * @var Tx_Fluidpages_Service_PageService
	 */
	protected $pageService;

	/**
	 * @var Tx_Fluidpages_Service_ConfigurationService
	 */
	protected $configurationService;

	/**
	 * @var integer
	 */
	protected $priority = 100;

	/**
	 * @var string
	 */
	protected $mainAction = 'tx_fed_page_controller_action';

	/**
	 * @var string
	 */
	protected $subAction = 'tx_fed_page_controller_action_sub';

	/**
	 * CONSTRUCTOR
	 */
	public function __construct() {
		$this->flexformTool = t3lib_div::makeInstance('t3lib_flexformtools');
	}

	/**
	 * @param Tx_Fluidpages_Service_PageService $pageService
	 * @return void
	 */
	public function injectPageService(Tx_Fluidpages_Service_PageService $pageService) {
		$this->pageService = $pageService;
	}

	/**
	 * @param Tx_Fluidpages_Service_ConfigurationService $configurationService
	 * @return void
	 */
	public function injectConfigurationService(Tx_Fluidpages_Service_ConfigurationService $configurationService) {
		$this->configurationService = $configurationService;
	}

	/**
	 * @param array $row
	 * @return string
	 */
	public function getExtensionKey(array $row) {
		$controllerExtensionKey = $this->getControllerExtensionKeyFromRecord($row);
		if (FALSE === empty($controllerExtensionKey)) {
			return $controllerExtensionKey;
		}
		return parent::getExtensionKey($row);
	}

	/**
	 * @param array $row
	 * @return array
	 */
	public function getTemplatePaths(array $row) {
		$extensionName = $this->getExtensionKey($row);
		$paths = $this->configurationService->getPageConfiguration($extensionName);
		if (TRUE === is_array($paths) && FALSE === empty($paths)) {
			$paths = Tx_Flux_Utility_Path::translatePath($paths);
			return $paths;
		}

		return parent::getTemplatePaths($row);
	}

	/**
	 * @param array $row
	 * @return string
	 */
	public function getTemplatePathAndFilename(array $row) {
		$action = $this->getControllerActionReferenceFromRecord($row);
		$paths = $this->getTemplatePaths($row);
		if (FALSE === empty($action)) {
			list ($extensionName, $action) = explode('->', $action);
			if (is_array($paths)) {
				$templateRootPath = $paths['templateRootPath'];
				if ('/' === substr($templateRootPath, -1)) {
					$templateRootPath = substr($templateRootPath, 0, -1);
				}
				$templatePathAndFilename = $templateRootPath . '/Page/' . $action . '.html';
				if (TRUE === isset($paths['overlays']) && TRUE === is_array($paths['overlays'])) {
					foreach ($paths['overlays'] as $possibleOverlayPaths) {
						if (TRUE === isset($possibleOverlayPaths['templateRootPath'])) {
							$overlayTemplateRootPath = $possibleOverlayPaths['templateRootPath'];
							$overlayTemplateRootPath = rtrim($overlayTemplateRootPath, '/');
							$possibleOverlayFile = t3lib_div::getFileAbsFileName($overlayTemplateRootPath . '/Page/' . $action . '.html');
							if (TRUE === file_exists($possibleOverlayFile)) {
								$templatePathAndFilename = $possibleOverlayFile;
								break;
							}
						}
					}
				}
			}
		}
		$templatePathAndFilename = t3lib_div::getFileAbsFileName($templatePathAndFilename);
		return $templatePathAndFilename;
	}

	/**
	 * Gets an inheritance tree (ordered parent -> ... -> this record)
	 * of record arrays containing raw values.
	 *
	 * @param array $row
	 * @return array
	 */
	public function getInheritanceTree(array $row) {
		if (TRUE === $this->isUsingSubFieldName()) {
			return array();
		}
		$records = parent::getInheritanceTree($row);
		if (0 === count($records)) {
			return $records;
		}
		$template = $records[0][$this->subAction];
		foreach ($records as $index => $record) {
			if ((FALSE === empty($record[$this->mainAction]) && $template !== $record[$this->mainAction]) || (FALSE === empty($record[$this->subAction]) && $template !== $record[$this->subAction])) {
				return array_slice($records, $index);
			}
		}
		return $records;
	}

	/**
	 * Post-process the TCEforms DataStructure for a record associated
	 * with this ConfigurationProvider
	 *
	 * @param array $row
	 * @param mixed $dataStructure
	 * @param array $conf
	 * @return NULL
	 */
	public function postProcessDataStructure(array &$row, &$dataStructure, array $conf) {
		$action = $this->getControllerActionReferenceFromRecord($row);
		if (TRUE === empty($action)) {
			$this->configurationService->message('No controller action was found for this page.', t3lib_div::SYSLOG_SEVERITY_WARNING);
			return NULL;
		}
		parent::postProcessDataStructure($row, $dataStructure, $conf);
	}

	/**
	 * @param array $row
	 * @return string
	 */
	public function getControllerExtensionKeyFromRecord(array $row) {
		$action = $this->getControllerActionReferenceFromRecord($row);
		if (FALSE !== strpos($action, '->')) {
			$extensionName = array_shift(explode('->', $action));
			$extensionKey = t3lib_div::camelCaseToLowerCaseUnderscored($extensionName);
			return $extensionKey;
		}
		return parent::getControllerExtensionKeyFromRecord($row);
	}

	/**
	 * @param array $row
	 * @return string
	 */
	public function getControllerActionFromRecord(array $row) {
		$action = $this->getControllerActionReferenceFromRecord($row);
		$controllerActionName = array_pop(explode('->', $action));
		$controllerActionName{0} = strtolower($controllerActionName{0});
		return $controllerActionName;
	}

	/**
	 * @param array $row
	 * @return string
	 */
	public function getControllerActionReferenceFromRecord(array $row) {
		$configuration = $this->pageService->getPageTemplateConfiguration($row['uid']);
		if (TRUE === $this->isUsingSubFieldName()) {
			return $configuration[$this->subAction];
		}
		return $configuration[$this->mainAction];
	}

	/**
	 * @param array $row The record row which triggered processing
	 * @return string|NULL
	 */
	public function getFieldName(array $row) {
		if (TRUE === $this->isUsingSubFieldName()) {
			return $this->subFieldName;
		}
		return $this->fieldName;
	}

	/**
	 * @param array $row
	 * @param string $table
	 * @param string $field
	 * @param string $extensionKey
	 * @return boolean
	 */
	public function trigger(array $row, $table, $field, $extensionKey = NULL) {
		$this->currentFieldName = $field;
		return parent::trigger($row, $table, $field, $extensionKey);
	}

	/**
	 * @param array $row
	 * @return Tx_Flux_Form|NULL
	 */
	public function getForm(array $row) {
		if (TRUE === $this->isUsingSubFieldName()) {
			$configuration = $this->pageService->getPageTemplateConfiguration($row['uid']);
			if ($configuration[$this->mainAction] === $configuration[$this->subAction]) {
				$form = Tx_Flux_Form::create();
				$form->createField('UserFunction', '')->setFunction('Tx_Fluidpages_UserFunction_NoSubPageConfiguration->renderField');
				return $form;
			}
		}
		return parent::getForm($row);
	}

	/**
	 * @return boolean
	 */
	public function isUsingSubFieldName() {
		return $this->currentFieldName === $this->subFieldName;
	}

	/**
	 * @param array $tree
	 * @param string $cacheKey Overrides the cache key
	 * @param boolean $mergeToCache Merges the configuration of $tree to the current $cacheKey
	 * @return array
	 */
	protected function getMergedConfiguration(array $tree, $cacheKey = NULL, $mergeToCache = FALSE) {
		$cacheKey = $this->getCacheKeyForMergedConfiguration($tree);
		if (TRUE === $this->hasCacheForMergedConfiguration($cacheKey)) {
			return parent::getMergedConfiguration($tree, $cacheKey);
		}

		if (FALSE === $this->isUsingSubFieldName()) {
			$branch = reset($tree);
			if (FALSE === empty($branch[$this->mainAction]) && FALSE === empty($branch[$this->subAction]) &&
				$branch[$this->mainAction] !== $branch[$this->subAction] &&
				FALSE === empty($branch[$this->subFieldName])) {

				$branch = array_shift($tree);
				$this->currentFieldName = $this->subFieldName;
				parent::getMergedConfiguration(array($branch), $cacheKey);
				$this->currentFieldName = $this->fieldName;
			}
		}
		return parent::getMergedConfiguration($tree, $cacheKey, TRUE);
	}

}
