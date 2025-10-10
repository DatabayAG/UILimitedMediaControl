<?php

declare(strict_types=1);

use ILIAS\TestQuestionPool\Questions\PublicInterface as Question;

class ilUILimitedMediaControlTableGUI extends ilTable2GUI
{
    /** @var ilUILimitedMediaControlGUI $parent_obj */
    protected ?object $parent_obj;
    protected string $parent_cmd;
    private ilUILimitedMediaControlPlugin $plugin;
    private Question $question_info;

    public function __construct(?object $a_parent_obj, string $a_parent_cmd)
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->parent_obj = $a_parent_obj;
        $this->parent_cmd = $a_parent_cmd;
        $this->plugin = $DIC['component.factory']->getPlugin('limpco');
        $this->question_info = $DIC->testQuestion();

        $this->setId('ilUILimitedMediaControl');
        $this->setPrefix('ilUILimitedMediaControl');

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setFormName('test_overview');
        $this->setTitle($this->plugin->txt('adapted_media_limits'));
        $this->setStyle('table', 'fullwidth');
        $this->addColumn($this->lng->txt("user"), 'name');
        $this->addColumn($this->plugin->txt("question_medium"), 'medium');
        $this->addColumn($this->plugin->txt('limit'), 'limit');
        $this->addColumn($this->lng->txt('actions'));

        $this->setRowTemplate("tpl.il_ui_limited_media_control_row.html", $this->plugin->getDirectory());
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

        $this->enable('header');
        $this->disable('select_all');

        $this->setEnableNumInfo(false);
        $this->setExternalSegmentation(true);
    }

    /**
     * @var \ILIAS\Plugin\LimitedMediaPlayer\Medium[] $media
     * @var \ILIAS\Plugin\LimitedMediaPlayer\Limit[] $limits
     */
    public function prepareData(ilObjTest $test, ilTestParticipantData $participants, array $media, array $limits)
    {
        $rows = [];
        foreach ($limits as $limit) {
            $row = array();
            $row['limit_obj'] = $limit;

            $active_id = (int) $participants->getActiveIdByUserId($limit->getUserId());
            $row['name'] = $this->parent_obj->formatParticipantName($active_id);

            if ($limit->getMediumKey() === null) {
                $row['medium'] = $this->plugin->txt('all_media');
            } else {
                $medium = $media[$limit->getMediumKey()] ?? null;
                $row['medium'] = $this->parent_obj->formatQuestionMediumTitle(
                    $this->question_info->getGeneralQuestionProperties($limit->getPageId())->getTitle(),
                    $medium ? $medium->getTitle() : ''
                );
            }
            $row['limit'] = $limit->getPlays();
            $rows[] = $row;
        }

        $this->setData($rows);
    }

    protected function fillRow(array $a_set): void
    {
        /** @var \ILIAS\Plugin\LimitedMediaPlayer\Limit $limit */
        $limit = $a_set['limit_obj'];

        // prepare action menu
        $list = new ilAdvancedSelectionListGUI();
        $list->setSelectionHeaderClass('small');
        $list->setItemLinkClass('small');
        $list->setId('actl_' . rand(0, 999999));
        $list->setListTitle($this->lng->txt('actions'));

        $this->ctrl->clearParameters($this->parent_obj);
        $this->ctrl->saveParameter($this->parent_obj, 'ref_id');
        if ($limit->getUserId() !== null) {
            $this->ctrl->setParameter($this->parent_obj, 'user_id', $limit->getUserId());
        }
        if ($limit->getMediumKey() !== null) {
            $this->ctrl->setParameter($this->parent_obj, 'medium_key', $limit->getMediumKey());
        }

        $list->addItem($this->lng->txt('edit'), '', $this->ctrl->getLinkTarget($this->parent_obj, 'editLimit'));
        $list->addItem($this->lng->txt('delete'), '', $this->ctrl->getLinkTarget($this->parent_obj, 'confirmDeleteLimit'));

        $this->tpl->setVariable('NAME', $a_set['name']);
        $this->tpl->setVariable('MEDIUM', $a_set['medium']);
        $this->tpl->setVariable('LIMIT', $a_set['limit']);
        $this->tpl->setVariable('ACTIONS', $list->getHTML());
    }
}
