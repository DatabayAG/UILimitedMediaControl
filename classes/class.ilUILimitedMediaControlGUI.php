<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once('./Modules/Test/classes/class.ilObjTest.php');
require_once('./Modules/Test/classes/class.ilTestParticipantData.php');
require_once('./Modules/TestQuestionPool/classes/class.assQuestion.php');
require_once('./Services/MediaObjects/classes/class.ilObjMediaObject.php');

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

	/** @var  ilLanguage $lng */
	protected $lng;

	/** @var ilUILimitedMediaControlPlugin $plugin */
	protected $plugin;

	/** @var ilObjTest $testObj */
	protected $testObj;

	/** @var  ilTestParticipantData $pdataObj */
    protected $pdataObj;

	/**
	 * ilUILimitedMediaControlGUI constructor.
	 */
	public function __construct()
	{
		global $ilDB, $ilCtrl, $tpl, $lng;

		$this->ctrl = $ilCtrl;
		$this->tpl = $tpl;
		$this->lng = $lng;

		$lng->loadLanguageModule('assessment');

		$this->plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'UIComponent', 'uihk', 'UILimitedMediaControl');

		$this->testObj = new ilObjTest($_GET['ref_id']);
        $this->pdataObj = new ilTestParticipantData($ilDB, $this->lng);
        $this->pdataObj->load($this->testObj->getTestId());
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
            case "confirmDeleteLimit":
				if ($this->prepareOutput())
				{
					$this->$cmd();
				}
                break;
			case "saveLimit":
            case "deleteLimit":
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
     * Format the name to be displayed for a participant
     * @param int $a_active_id
     * @param bool $a_add_login
     * @return string
     */
    public function formatParticipantName($a_active_id)
    {
        if (empty($a_active_id))
        {
            return $this->plugin->txt('all_participants');
        }
        $name = $this->pdataObj->getFormatedFullnameByActiveId($a_active_id);
        $data = $this->pdataObj->getUserDataByActiveId($a_active_id);
        $name .= ' ('.$data['login'].')';

        return $name;
    }

    /**
     * Format the titles of the question and the medium to fint into a select box
     * @param string $a_question_title
     * @param string $a_medium_title
     * @return string
     */
    public function formatQuestionMediumTitle($a_question_title, $a_medium_title)
    {
        return ilUtil::shortenText($a_question_title, 40, true)
            . " / "
            . ilUtil::shortenText($a_medium_title, 40, true);
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
		$tableGUI->prepareData($this->testObj, $this->pdataObj);

		ilUtil::sendInfo($this->plugin->txt('remark_media').'<br />'. $this->plugin->txt('remark_user'));
		$this->tpl->setContent($tableGUI->getHTML());
		$this->tpl->show();
	}

    /**
     * Select the participant to adapt
     */
	protected function selectParticipant()
    {
        global $ilDB;

        $options = array('0' => $this->plugin->txt('all_participants'));
        foreach ($this->pdataObj->getActiveIds() as $active_id)
        {
            $options[$active_id] = $this->formatParticipantName($active_id);
        }

        require_once('Services/Form/classes/class.ilPropertyFormGUI.php');
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, 'showAdaptations'));
        $form->setTitle($this->plugin->txt('select_participant'));

        $sel = new ilSelectInputGUI($this->plugin->txt('participant'), 'active_id');
        $sel->setOptions($options);
        $form->addItem($sel);

        $form->addCommandButton('selectMedium', $this->lng->txt('continue'));
        $form->addCommandButton('showAdaptations', $this->lng->txt('cancel'));

        $this->tpl->setContent($form->getHTML());
        $this->tpl->show();
    }

    /**
     * Select the Medium
     */
    protected function selectMedium()
    {
        global $ilDB;

        $active_id = (int) $_REQUEST['active_id'];

        // in a random text, only 'all media' can be selected for 'all participants'
        if ($active_id == 0 && $this->testObj->isRandomTest())
        {
            $this->ctrl->setParameter($this, 'user_id', 0);
            $this->ctrl->setParameter($this, 'page_mob_id', '');
            $this->ctrl->redirect($this, 'editLimit');
        }

        $question_ids = array();
        if ($this->testObj->isFixedTest())
        {
            $question_ids = $this->testObj->getQuestions();
        }
        elseif ($this->testObj->isRandomTest())
        {
            foreach($this->testObj->getQuestionsOfTest($active_id) as $qdata)
            {
                $question_ids[] = [$qdata['question_fi']];
            }
        }

        $found = $this->plugin->findLimitedMedia($question_ids);
        $options = array('' => $this->plugin->txt('all_media'));
        foreach ($found as $data)
        {
            $options[$data['page_id'].'_'.$data['mob_id']] = $this->formatQuestionMediumTitle(
                assQuestion::_getTitle($data['page_id']), $data['title']);
        }

        require_once('Services/Form/classes/class.ilPropertyFormGUI.php');
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, 'showAdaptations'));
        $form->setTitle($this->plugin->txt('select_medium'));

        $nam = new ilNonEditableValueGUI($this->plugin->txt('participant'));
        $nam->setValue($this->formatParticipantName($active_id));
        $form->addItem($nam);

        $usr = new ilHiddenInputGUI('user_id');
        $usr->setValue($active_id == 0 ? 0 : $this->pdataObj->getUserIdByActiveId($active_id));
        $form->addItem($usr);

        $sel = new ilSelectInputGUI($this->plugin->txt('question_medium'), 'page_mob_id');
        $sel->setOptions($options);
        $form->addItem($sel);

        $form->addCommandButton('editLimit', $this->lng->txt('continue'));
        $form->addCommandButton('showAdaptations', $this->lng->txt('cancel'));

        $this->tpl->setContent($form->getHTML());
        $this->tpl->show();
    }


    /**
     * Edit the limit for the medium
     */
    protected function editLimit()
    {
        $user_id = (int) $_REQUEST['user_id'];
        $parts = explode('_', (string) $_REQUEST['page_mob_id']);
        $page_id = (int) $parts[0];
        $mob_id = (int) $parts[1];
        $active_id = (int) $this->pdataObj->getActiveIdByUserId($user_id);

        if ($page_id != 0 && $mob_id != 0)
        {
            $found = (array) $this->plugin->findLimitedMedia((array) $page_id, $mob_id);
            $data = $found[0];
            $defined_limit = $data['limit'];
            $title = $this->formatQuestionMediumTitle(assQuestion::_getTitle($page_id), $data['title']);
        }
        else
        {
            $defined_limit = null;
            $title = $this->plugin->txt('all_media');
        }

        foreach ($this->plugin->getTestLimits($this->testObj->getId()) as $limitObj)
        {
            if ($limitObj->getPageId() == $page_id
                && $limitObj->getMobId() == $mob_id
                && $limitObj->getUserId() == $user_id)
            {
                $custom_limit = $limitObj->getLimit();
                break;
            }
        }

        require_once('Services/Form/classes/class.ilPropertyFormGUI.php');
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, 'showAdaptations'));
        $form->setTitle($this->plugin->txt('adapt_limit'));

        $nam = new ilNonEditableValueGUI($this->plugin->txt('participant'));
        $nam->setValue($this->formatParticipantName($active_id));
        $form->addItem($nam);

        $usr = new ilHiddenInputGUI('user_id');
        $usr->setValue($user_id);
        $form->addItem($usr);

        $tit = new ilNonEditableValueGUI($this->plugin->txt('question_medium'));
        $tit->setValue($title);
        $form->addItem($tit);

        $pgm = new ilHiddenInputGUI('page_mob_id');
        $pgm->setValue($page_id.'_'.$mob_id);
        $form->addItem($pgm);

        if (isset($defined_limit))
        {
            $def = new ilNonEditableValueGUI($this->plugin->txt('limit_standard'));
            $def->setValue($defined_limit);
            $form->addItem($def);
        }

        $lim = new ilNumberInputGUI($this->plugin->txt('limit_custom'), 'limit');
        $lim->setDecimals(0);
        $lim->setSize(2);
        $lim->setValue($custom_limit);
        $form->addItem($lim);

        $form->addCommandButton('saveLimit', $this->lng->txt('save'));
        $form->addCommandButton('showAdaptations', $this->lng->txt('cancel'));

        $this->tpl->setContent($form->getHTML());
        $this->tpl->show();
    }

    /**
     * Save an edited Limit
     */
    protected function saveLimit()
    {
        $user_id = (int) $_REQUEST['user_id'];
        $parts = explode('_', (string) $_REQUEST['page_mob_id']);
        $page_id = (int) $parts[0];
        $mob_id = (int) $parts[1];
        $limit = (int) $_REQUEST['limit'];

        $this->plugin->saveLimit($this->testObj->getId(), $page_id, $mob_id, $user_id, $limit);
        ilUtil::sendSuccess($this->plugin->txt('limit_saved'), true);
        $this->ctrl->redirect($this, 'showAdaptations');
    }

    /**
     * Confirm the deletion of a limit
     */
    protected function confirmDeleteLimit()
    {
        $user_id = (int) $_REQUEST['user_id'];
        $active_id = (int) $this->pdataObj->getActiveIdByUserId($user_id);
        $parts = explode('_', (string) $_REQUEST['page_mob_id']);
        $page_id = (int) $parts[0];
        $mob_id = (int) $parts[1];

        $uname = $this->formatParticipantName($active_id);
        $mtitle = $this->formatQuestionMediumTitle(
            assQuestion::_getTitle($page_id),
            ilObjMediaObject::_lookupTitle($mob_id)
        );

        require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
        $gui = new ilConfirmationGUI;
        $gui->setFormAction($this->ctrl->getFormAction($this, "showAdaptations"));
        $gui->addHiddenItem('user_id', $user_id);
        $gui->addHiddenItem('page_mob_id', $page_id . '_' . $mob_id);
        $gui->setHeaderText($this->plugin->txt('confirm_delete_limit'));
        $gui->addItem('','', $this->plugin->txt('participant').': '. $uname);
        $gui->addItem('','', $this->plugin->txt('question_medium').': '. $mtitle);
        $gui->addButton($this->lng->txt('delete'), 'deleteLimit');
        $gui->addButton($this->lng->txt('cancel'), 'showAdaptations');

        $this->tpl->setContent($gui->getHTML());
        $this->tpl->show();
    }


    /**
     * Delete a Limit
     */
    protected function deleteLimit()
    {
        $user_id = (int) $_REQUEST['user_id'];
        $parts = explode('_', (string) $_REQUEST['page_mob_id']);
        $page_id = (int) $parts[0];
        $mob_id = (int) $parts[1];

        $this->plugin->deleteLimit($this->testObj->getId(), $page_id, $mob_id, $user_id);
        ilUtil::sendSuccess($this->plugin->txt('limit_deleted'), true);
        $this->ctrl->redirect($this, 'showAdaptations');
    }


    /**
	 * Set the Toolbar
	 */
	protected function setToolbar()
	{
		/** @var ilToolbarGUI $ilToolbar */
		global $ilToolbar;

		require_once 'Services/UIComponent/Button/classes/class.ilLinkButton.php';
		$button = ilLinkButton::getInstance();
		$button->setUrl($this->ctrl->getLinkTarget($this, 'selectParticipant'));
		$button->setCaption($this->plugin->txt('new_adaptation'), false);
		$button->getOmitPreventDoubleSubmission();
		$ilToolbar->addButtonInstance($button);
    }
}
?>