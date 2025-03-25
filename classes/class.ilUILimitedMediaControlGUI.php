<?php

declare(strict_types=1);

use ilGlobalTemplateInterface as Gti;
use ILIAS\HTTP\Services as HttpServices;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Factory as UiFactory;
use ILIAS\UI\Renderer as UiRenderer;

/**
 * @ilCtrl_IsCalledBy ilUILimitedMediaControlGUI: ilUIPluginRouterGUI
 */
class ilUILimitedMediaControlGUI
{
    private ilCtrl $ctrl;
    private ilAccessHandler $access;
    private ilToolbarGUI $toolbar;
    private ilLocatorGUI $locator;
    private ilLanguage $lng;
    private Gti $tpl;
    private HttpServices $http;
    private Refinery $refinery;

    private UiFactory $ui_factory;
    private UiRenderer $ui_renderer;

    private ilObjTest $test;
    private ilTestParticipantData $participants;

    private ilUILimitedMediaControlPlugin $plugin;

    /** @var ilPCLimitedMediaPlayerPlugin  */
    private ilPageComponentPlugin $player_plugin;

    /** @var ILIAS\Plugin\LimitedMediaPlayer\MediumRepo */
    private $medium_repo;

    /** @var ILIAS\Plugin\LimitedMediaPlayer\LimitRepo */
    private $limit_repo;

    // Request variables

    private int $ref_id = 0;
    private int $active_id = 0;
    private ?int $user_id = null;
    private ?int $plays = null;
    private ?string $page_and_file = null;
    private ?int $page_id = null;
    private ?string $file_id = null;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->access = $DIC->access();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->toolbar = $DIC->toolbar();
        $this->locator = $DIC->locator();
        $this->lng = $DIC->lng();
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();

        $this->lng->loadLanguageModule('assessment');

        $this->plugin = $DIC['component.factory']->getPlugin('limpco');
        $this->player_plugin = $this->plugin->getPlayerPlugin();
        $this->medium_repo = $this->player_plugin->factory()->mediumRepo();
        $this->limit_repo = $this->player_plugin->factory()->limitRepo();

        $this->participants = new ilTestParticipantData($DIC->database(), $DIC->language());
    }

    public function executeCommand()
    {
        if (!$this->plugin->checkPlayerActive()) {
            $this->handleFailure($this->lng->txt("player_plugin_not_active"));
        }

        // initialize common request variables
        $this->ref_id = $this->requestInteger('ref_id') ?? 0;
        $this->active_id = $this->requestInteger('active_id') ?? 0;
        $this->user_id = $this->requestInteger('user_id');
        $this->plays = $this->requestInteger('plays');

        // page and medium file are selected together
        $this->page_and_file = $this->requestString('page_and_file');
        if ($this->page_and_file !== null) {
            $parts = explode('_', $this->page_and_file);
            if (count($parts) == 2) {
                $this->page_id = (int) $parts[0];
                $this->file_id = (string) $parts[1];
            }
        }

        if (!$this->access->checkAccess('write', '', $this->ref_id, 'tst')) {
            $this->handleFailure($this->lng->txt("permission_denied"));
        }

        $this->test = new ilObjTest($this->ref_id);
        $this->participants->load($this->test->getTestId());

        $this->ctrl->saveParameter($this, 'ref_id');
        $cmd = $this->ctrl->getCmd('showAdaptations');

        switch ($cmd) {
            case 'showAdaptations':
            case 'selectParticipant':
            case 'selectMedium':
            case 'editLimit':
            case 'confirmDeleteLimit':
                $this->prepareOutput();
                $this->$cmd();
                break;

            case 'saveLimit':
            case 'deleteLimit':
                $this->$cmd();
                break;

            default:
                $this->handleFailure($this->lng->txt("permission_denied"));
                break;
        }
    }

    private function handleFailure(string $message): void
    {
        $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_FAILURE, $message, true);
        $this->ctrl->redirectToURL(ilLink::_getLink($this->ref_id));
    }

    /**
     * Format the name to be displayed for a participant
     */
    public function formatParticipantName(?int $active_id): string
    {
        if (empty($active_id)) {
            return $this->plugin->txt('all_participants');
        }
        $name = $this->participants->getFormatedFullnameByActiveId($active_id);
        $data = $this->participants->getUserDataByActiveId($active_id);
        $name .= ' (' . $data['login'] ?? 'anonymous' . ')';

        return $name;
    }

    /**
     * Format the titles of the question and the medium to fit into a select box
     */
    public function formatQuestionMediumTitle(string $question_title, string $medium_title): string
    {
        if (empty($question_title) && empty($medium_title)) {
            return $this->plugin->txt('all_media');
        }
        return ilStr::shortenText($question_title, 0, 40)
            . " / "
            . ilStr::shortenText($medium_title, 0, 40);
    }

    /**
     * Prepare the test header, tabs etc.
     */
    private function prepareOutput(): void
    {
        $this->locator->addRepositoryItems($this->test->getRefId());
        $this->locator->addItem($this->test->getTitle(), $this->ctrl->getLinkTargetByClass('ilObjTestGUI'));

        $this->tpl->setLocator();
        $this->tpl->setTitle($this->test->getPresentationTitle());
        $this->tpl->setDescription($this->test->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon($this->test->getId(), 'big', 'tst'), $this->lng->txt('obj_tst'));
    }

    /**
     * Show the adaptations of playing limits
     */
    private function showAdaptations()
    {
        $button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this, 'selectParticipant'));
        $button->setCaption($this->plugin->txt('new_adaptation'), false);
        $this->toolbar->addButtonInstance($button);

        $table_gui = new ilUILimitedMediaControlTableGUI($this, 'showAdaptations');
        $table_gui->prepareData($this->test, $this->participants);

        $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_INFO, $this->plugin->txt('remark_media')
            . '<br />' . $this->plugin->txt('remark_user'));
        $this->tpl->setContent($table_gui->getHTML());
        $this->tpl->printToStdout();
    }

    /**
     * Select the participant for which the adaptations should be limited
     */
    private function selectParticipant()
    {
        $options = ['0' => $this->plugin->txt('all_participants')];
        foreach ($this->participants->getActiveIds() as $active_id) {
            $options[$active_id] = $this->formatParticipantName($active_id);
        }

        $factory = $this->ui_factory->input()->field();
        $fields = [
            'title' => $factory->section([], $this->plugin->txt('select_participant')),
            'active_id' => $factory->select($this->plugin->txt('participant'), $options)
        ];

        $form = $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, 'selectMedium'),
            $fields
        )
            ->withSubmitCaption($this->lng->txt('continue'));

        $this->tpl->setContent($this->ui_renderer->render($form));
        $this->tpl->printToStdout();
    }

    /**
     * Select the Medium
     */
    private function selectMedium()
    {
        if ($this->active_id !== 0) {
            $this->user_id = $this->participants->getUserIdByActiveId($this->active_id);
            $this->ctrl->setParameter($this, 'user_id', $this->user_id);
        } elseif ($this->test->isRandomTest()) {
            // in a random text, only 'all media' can be selected for 'all participants'
            $this->ctrl->redirect($this, 'editLimit');
        }

        $question_ids = [];
        if ($this->test->isFixedTest()) {
            $question_ids = $this->test->getQuestions();
        } elseif ($this->test->isRandomTest()) {
            foreach ($this->test->getQuestionsOfTest($this->active_id) as $data) {
                $question_ids[] = $data['question_fi'] ?? 0;
            }
        }
        $found = $this->medium_repo->findLimitedMedia($question_ids);
        $options = ['' => $this->plugin->txt('all_media')];

        /** @var \ILIAS\Plugin\LimitedMediaPlayer\Medium $medium */
        foreach ($found as $medium) {
            $options[$medium->getPageId() . '_' . $medium->getFileId()] = $this->formatQuestionMediumTitle(
                assQuestion::_getTitle($medium->getPageId()),
                $medium->getTitle()
            );
        }

        $factory = $this->ui_factory->input()->field();
        $fields = [
            'title' => $factory->section(
                [],
                $this->plugin->txt('select_medium'),
                $this->formatParticipantName($this->active_id)
            ),
            'page_and_file' => $factory->select($this->plugin->txt('question_medium'), $options)
        ];

        $form = $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, 'editLimit'),
            $fields
        )
                ->withSubmitCaption($this->lng->txt('continue'));

        $this->tpl->setContent($this->ui_renderer->render($form));
        $this->tpl->printToStdout();
    }


    private function editLimit()
    {
        if ($this->user_id !== null) {
            $this->ctrl->saveParameter($this, 'user_id');
        }
        $this->ctrl->saveParameter($this, 'page_and_file');

        $medium_title = $this->plugin->txt('all_media');
        $default_plays = null;
        if ($this->page_id !== null && $this->file_id !== null) {
            $found = $this->medium_repo->findLimitedMedia((array) $this->page_id, $this->file_id);
            if (!empty($found)) {
                /** @var \ILIAS\Plugin\LimitedMediaPlayer\Medium $medium */
                $medium = $found[0];
                $default_plays = $medium->getLimitPlays();
                $medium_title = $this->formatQuestionMediumTitle(assQuestion::_getTitle($this->page_id), $medium->getTitle());
            }
        }

        /** @var \ILIAS\Plugin\LimitedMediaPlayer\Limit $limit */
        $limit = $this->limit_repo->get($this->test->getId(), $this->page_id, $this->file_id, $this->user_id);

        $factory = $this->ui_factory->input()->field();
        $fields = [
            'title' => $factory->section(
                [],
                $this->plugin->txt('adapt_limit'),
                implode('<br />', [
                    $this->formatParticipantName($this->active_id),
                    $this->plugin->txt('question_medium') . ': ' . $medium_title,
                    $this->plugin->txt('limit_standard') . ': ' . $default_plays ?? $this->plugin->txt('unlimited'),
                ]),
            ),
            'plays' => $factory->numeric($this->plugin->txt('limit_custom'))->withValue($limit->getPlays())
        ];

        $form = $this->ui_factory->input()->container()->form()->standard($this->ctrl->getFormAction($this, 'editLimit'), $fields)
            ->withSubmitCaption($this->lng->txt('continue'));

        $this->tpl->setContent($this->ui_renderer->render($form));
        $this->tpl->printToStdout();
    }

    private function saveLimit()
    {
        $limit = $this->limit_repo->get($this->test->getId(), $this->page_id, $this->file_id, $this->user_id);
        $limit->setPlays($this->plays);
        $this->limit_repo->save($limit);

        $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_SUCCESS, $this->plugin->txt('limit_saved'), true);
        $this->ctrl->redirect($this, 'showAdaptations');
    }

    private function confirmDeleteLimit()
    {
        if ($this->user_id !== null) {
            $this->ctrl->saveParameter($this, 'user_id');
        }
        $this->ctrl->saveParameter($this, 'page_and_file');

        $active_id = $this->participants->getActiveIdByUserId($this->user_id ?? 0);
        $user_name = $this->formatParticipantName($this->active_id);

        $medium_title = $this->plugin->txt('all_media');
        if ($this->page_id !== null && $this->file_id !== null) {
            $found = $this->medium_repo->findLimitedMedia((array) $this->page_id, $this->file_id);
            if (!empty($found)) {
                /** @var \ILIAS\Plugin\LimitedMediaPlayer\Medium $medium */
                $medium = $found[0];
                $medium_title = $this->formatQuestionMediumTitle(assQuestion::_getTitle((int) $this->page_id), $medium->getTitle());
            }
        }

        $gui = new ilConfirmationGUI();
        $gui->setFormAction($this->ctrl->getFormAction($this, "showAdaptations"));
        $gui->setHeaderText($this->plugin->txt('confirm_delete_limit'));
        $gui->addItem('', '', $this->plugin->txt('participant') . ': ' . $user_name);
        $gui->addItem('', '', $this->plugin->txt('question_medium') . ': ' . $medium_title);
        $gui->addButton($this->lng->txt('delete'), 'deleteLimit');
        $gui->addButton($this->lng->txt('cancel'), 'showAdaptations');

        $this->tpl->setContent($gui->getHTML());
        $this->tpl->printToStdout();
    }

    private function deleteLimit()
    {
        $limit = $this->limit_repo->get($this->test->getId(), $this->page_id, $this->file_id, $this->user_id);
        $this->limit_repo->delete($limit);

        $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_SUCCESS, $this->plugin->txt('limit_deleted'), true);
        $this->ctrl->redirect($this, 'showAdaptations');
    }

    private function requestInteger(string $key): ?int
    {
        if ($this->http->wrapper()->post()->has($key)) {
            return $this->http->wrapper()->post()->retrieve($key, $this->refinery->kindlyTo()->int());
        }
        if ($this->http->wrapper()->query()->has($key)) {
            return $this->http->wrapper()->post()->retrieve($key, $this->refinery->kindlyTo()->int());
        }
        return null;
    }

    private function requestString(string $key): ?string
    {
        if ($this->http->wrapper()->post()->has($key)) {
            return $this->http->wrapper()->post()->retrieve($key, $this->refinery->kindlyTo()->string());
        }
        if ($this->http->wrapper()->query()->has($key)) {
            return $this->http->wrapper()->post()->retrieve($key, $this->refinery->kindlyTo()->string());
        }
        return null;
    }
}
