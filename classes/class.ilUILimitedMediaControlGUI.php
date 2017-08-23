<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once ('Modules/Test/classes/class.ilObjTest.php');

/**
 * GUI for Limited Media Control
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_IsCalledBy ilUILimitedMediaControlGUI: ilUIPluginRouterGUI
 */
class ilUILimitedMediaControlGUI
{
	/** @var ilCtrl $ctrl */
	protected $ctrl;

	/** @var ilTemplate $tpl */
	protected $tpl;

	/** @var ilUILimitedMediaControlPlugin $plugin */
	protected $plugin;

	/** @var ilObjTest $testObj */
	protected $testObj;


	/**
	 * ilUILimitedMediaControlGUI constructor.
	 */
	public function __construct()
	{
		global $ilCtrl, $tpl, $lng;

		$this->ctrl = $ilCtrl;
		$this->tpl = $tpl;

		$lng->loadLanguageModule('assessment');

		$this->plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'UIComponent', 'uihk', 'UILimitedMediaControl');
		$this->testObj = new ilObjTest($_GET['ref_id']);
	}

	/**
	* Handles all commands, default is "show"
	*/
	public function executeCommand()
	{
		/** @var ilAccessHandler $ilAccess */
		/** @var ilErrorHandling $ilErr */
		global $ilAccess, $ilErr, $lng;

		if (!$ilAccess->checkAccess('write','',$this->testObj->getRefId()))
		{
            ilUtil::sendFailure($lng->txt("permission_denied"), true);
            ilUtil::redirect("goto.php?target=tst_".$this->testObj->getRefId());
		}
		elseif (!$this->plugin->checkPlayerActive())
        {
            ilUtil::sendFailure($lng->txt("player_plugin_not_active"), true);
            ilUtil::redirect("goto.php?target=tst_".$this->testObj->getRefId());
        }

		$this->ctrl->saveParameter($this, 'ref_id');
		$cmd = $this->ctrl->getCmd('showAdaptations');

		switch ($cmd)
		{
			case "showAdaptations":
            case "selectParticipant":
            case "selectMedium":
            case "editLimit":
				if ($this->prepareOutput())
				{
					$this->$cmd();
				}
                break;
			case "saveLimit":
				$this->$cmd();
				break;

			default:
                ilUtil::sendFailure($lng->txt("permission_denied"), true);
                ilUtil::redirect("goto.php?target=tst_".$this->testObj->getRefId());
				break;
		}
	}

	/**
	 * Get the plugin object
	 * @return ilUILimitedMediaControlPlugin|null
	 */
	public function getPlugin()
	{
		return $this->plugin;
	}

	/**
	 * Get the test object id (needed for table filter)
	 * @return int
	 */
	public function getId()
	{
		return $this->testObj->getId();
	}

	/**
	 * Prepare the test header, tabs etc.
	 */
	protected function prepareOutput()
	{
		/** @var ilLocatorGUI $ilLocator */
		/** @var ilLanguage $lng */
		global $ilLocator, $lng;

		$this->ctrl->setParameterByClass('ilObjTestGUI', 'ref_id',  $this->testObj->getRefId());
		$ilLocator->addRepositoryItems($this->testObj->getRefId());
		$ilLocator->addItem($this->testObj->getTitle(),$this->ctrl->getLinkTargetByClass('ilObjTestGUI'));

		$this->tpl->getStandardTemplate();
		$this->tpl->setLocator();
		$this->tpl->setTitle($this->testObj->getPresentationTitle());
		$this->tpl->setDescription($this->testObj->getLongDescription());
		$this->tpl->setTitleIcon(ilObject::_getIcon('', 'big', 'tst'), $lng->txt('obj_tst'));
		$this->tpl->addCss($this->plugin->getStyleSheetLocation('exte_stat.css'));

		if ($this->testObj->isDynamicTest())
		{
			ilUtil::sendFailure($this->plugin->txt('not_for_dynamic_test'));
			$this->tpl->show();
			return false;
		}

		return true;
	}

	/**
	 * Show the limit adaptations
	 */
	protected function showAdaptations()
	{
		$this->setToolbar();

		/** @var   $tableGUI */
		$this->plugin->includeClass('class.ilUILimitedMediaControlTableGUI.php');
		$tableGUI = new ilUILimitedMediaControlTableGUI($this, 'showAdaptations');

		$this->tpl->setContent($tableGUI->getHTML());
		$this->tpl->show();
	}


	/**
	 * Set the Toolbar
	 */
	protected function setToolbar()
	{
		/** @var ilToolbarGUI $ilToolbar */
		global $ilToolbar, $lng;

		require_once 'Services/UIComponent/Button/classes/class.ilLinkButton.php';
		$button = ilLinkButton::getInstance();
		$button->setUrl($this->ctrl->getLinkTarget($this, 'selectParticipant'));
		$button->setCaption('new_adaptation');
		$button->getOmitPreventDoubleSubmission();
		$ilToolbar->addButtonInstance($button);
    }
}
?>