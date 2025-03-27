<?php

declare(strict_types=1);

use ilGlobalTemplateInterface as Gti;
use ILIAS\HTTP\Services as HttpServices;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Factory as UiFactory;
use ILIAS\UI\Renderer as UiRenderer;
use ILIAS\TestQuestionPool\QuestionInfoService;

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
    private ?string $medium_key = null;
    private ?int $page_id = null;
    private ?string $file_id = null;
    private QuestionInfoService $question_info;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->access = $DIC->access();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->toolbar = $DIC->toolbar();
        $this->locator = $DIC['ilLocator'];
        $this->lng = $DIC->language();
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
        $this->question_info = new QuestionInfoService($DIC->database(), $DIC['component.factory'], $DIC->language());
    }

    public function executeCommand()
    {
        if (!$this->plugin->checkPlayerActive()) {
            $this->handleFailure($this->lng->txt("player_plugin_not_active"));
        }

        // initialize all possible request variables
        $this->ref_id = $this->requestInteger('ref_id') ?? 0;
        $this->active_id = $this->requestInteger('active_id') ?? 0;
        $this->user_id = $this->requestInteger('user_id');
        $this->plays = $this->requestInteger('plays');
        $this->medium_key = $this->requestString('medium_key');

        // page and file are selected together
        if ($this->medium_key !== null) {
            $parts = explode('_', $this->medium_key);
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

    public function formatParticipantName(?int $active_id): string
    {
        if (empty($active_id)) {
            return $this->plugin->txt('all_participants');
        }
        $name = $this->participants->getFormatedFullnameByActiveId($active_id);
        $data = $this->participants->getUserDataByActiveId($active_id);
        $name .= ' (' . ($data['login'] ?? 'anonymous') . ')';

        return $name;
    }

    public function formatQuestionMediumTitle(string $question_title, string $medium_title): string
    {
        if (empty($question_title) && empty($medium_title)) {
            return $this->plugin->txt('all_media');
        }
        return ilStr::shortenText($question_title, 0, 40)
            . " / "
            . ilStr::shortenText($medium_title, 0, 40);
    }

    private function prepareOutput(): void
    {
        $this->locator->addRepositoryItems($this->test->getRefId());
        $this->locator->addItem($this->test->getTitle(), $this->ctrl->getLinkTargetByClass('ilObjTestGUI'));

        $this->tpl->setLocator();
        $this->tpl->setTitle($this->test->getPresentationTitle());
        $this->tpl->setDescription($this->test->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon($this->test->getId(), 'big', 'tst'), $this->lng->txt('obj_tst'));
    }

    private function showAdaptations()
    {
        $button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this, 'selectParticipant'));
        $button->setCaption($this->plugin->txt('new_adaptation'), false);
        $this->toolbar->addButtonInstance($button);

        $media = $this->medium_repo->findMedia($this->getQuestionIds());
        $limits = $this->limit_repo->all($this->test->getId());

        $table_gui = new ilUILimitedMediaControlTableGUI($this, 'showAdaptations');
        $table_gui->prepareData($this->test, $this->participants, $media, $limits);

        $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_INFO, $this->plugin->txt('remark_media')
            . '<br />' . $this->plugin->txt('remark_user'));
        $this->tpl->setContent($table_gui->getHTML());
        $this->tpl->printToStdout();
    }

    private function selectParticipant()
    {
        $options = ['0' => $this->plugin->txt('all_participants')];
        foreach ($this->participants->getActiveIds() as $active_id) {
            $options[$active_id] = $this->formatParticipantName($active_id);
        }

        $factory = $this->ui_factory->input()->field();
        $fields = [
            'active_id' => $factory->select($this->plugin->txt('participant'), $options)
        ];
        $sections = [
            'general' => $factory->section($fields, $this->plugin->txt('select_participant'))
        ];
        $form = $this->ui_factory->input()->container()->form()->standard('#', $sections)
             ->withSubmitLabel($this->lng->txt('continue'));

        if ($this->http->request()->getMethod() === 'POST') {
            $form = $form->withRequest($this->http->request());
            $data = $form->getData();
            $this->ctrl->setParameter($this, 'active_id', $data['general']['active_id'] ?? 0);
            $this->ctrl->redirect($this, 'selectMedium');
        }

        $this->tpl->setContent($this->ui_renderer->render($form));
        $this->tpl->printToStdout();
    }

    private function selectMedium()
    {
        if ($this->active_id !== 0) {
            $this->user_id = (int) $this->participants->getUserIdByActiveId($this->active_id);
            $this->ctrl->setParameter($this, 'user_id', $this->user_id);
        } elseif ($this->test->isRandomTest()) {
            // in a random text, only 'all media' can be selected for 'all participants'
            $this->ctrl->redirect($this, 'editLimit');
        }

        $media = $this->medium_repo->findMedia($this->getQuestionIds());
        $options = ['' => $this->plugin->txt('all_media')];

        /** @var \ILIAS\Plugin\LimitedMediaPlayer\Medium $medium */
        foreach ($media as $medium) {
            $options[$medium->getKey()] = $this->formatQuestionMediumTitle(
                $this->question_info->getQuestionTitle($medium->getPageId()),
                $medium->getTitle()
            );
        }

        $factory = $this->ui_factory->input()->field();
        $fields = [
            'medium_key' => $factory->select($this->plugin->txt('question_medium'), $options)
        ];
        $sections = [
            'general' => $factory->section(
                $fields,
                $this->plugin->txt('select_medium'),
                $this->formatParticipantName($this->active_id)
            )
        ];
        $form = $this->ui_factory->input()->container()->form()->standard('#', $sections)
                                 ->withSubmitLabel($this->lng->txt('continue'));

        if ($this->http->request()->getMethod() === 'POST') {
            $form = $form->withRequest($this->http->request());
            $data = $form->getData();
            $this->ctrl->setParameter($this, 'medium_key', $data['general']['medium_key'] ?? '');
            $this->ctrl->redirect($this, 'editLimit');
        }

        $this->tpl->setContent($this->ui_renderer->render($form));
        $this->tpl->printToStdout();
    }

    private function editLimit()
    {
        if ($this->user_id !== null) {
            $this->ctrl->saveParameter($this, 'user_id');
        }
        $this->ctrl->saveParameter($this, 'medium_key');

        $medium = $this->medium_repo->getMedium($this->page_id, $this->file_id);
        if ($medium !== null) {
            $medium_title = $this->formatQuestionMediumTitle(
                $this->question_info->getQuestionTitle((int) $this->page_id),
                $medium->getTitle()
            );
            $default_plays = $medium->getLimitPlays();
        } else {
            $medium_title = $this->plugin->txt('all_media');
            $default_plays = null;
        }

        /** @var \ILIAS\Plugin\LimitedMediaPlayer\Limit $limit */
        $limit = $this->limit_repo->get($this->test->getId(), $this->page_id, $this->file_id, $this->user_id);

        $factory = $this->ui_factory->input()->field();
        $fields = [
            'plays' => $factory->numeric($this->plugin->txt('limit_custom'))->withValue($limit->getPlays())
        ];
        $sections = [
            'general' => $factory->section(
                $fields,
                $this->plugin->txt('adapt_limit'),
                implode('<br />', [
                    $this->formatParticipantName((int) $this->participants->getActiveIdByUserId($this->user_id)),
                    $this->plugin->txt('question_medium') . ': ' . $medium_title,
                    $this->plugin->txt('limit_standard') . ': ' . $default_plays ?? $this->plugin->txt('unlimited'),
                ]),
            ),
        ];

        $form = $this->ui_factory->input()->container()->form()->standard('#', $sections)
                                 ->withSubmitLabel($this->lng->txt('continue'));

        if ($this->http->request()->getMethod() === 'POST') {
            $form = $form->withRequest($this->http->request());
            $data = $form->getData();
            if (!empty($data['general']['plays'])) {
                $this->ctrl->setParameter($this, 'plays', $data['general']['plays']);
                $this->ctrl->redirect($this, 'saveLimit');
            }
        }

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
        $this->ctrl->saveParameter($this, 'medium_key');

        $user_name = $this->formatParticipantName((int) $this->participants->getActiveIdByUserId($this->user_id));

        $medium = $this->medium_repo->getMedium($this->page_id, $this->file_id);
        if ($medium !== null) {
            $medium_title = $this->formatQuestionMediumTitle(
                $this->question_info->getQuestionTitle((int) $this->page_id),
                $medium->getTitle()
            );
        } else {
            $medium_title = $this->plugin->txt('all_media');
        }

        $gui = new ilConfirmationGUI();
        $gui->setFormAction($this->ctrl->getFormAction($this, 'showAdaptations'));
        $gui->setHeaderText($this->plugin->txt('confirm_delete_limit'));
        $gui->addItem('', '', $this->plugin->txt('participant') . ': ' . $user_name);
        $gui->addItem('', '', $this->plugin->txt('question_medium') . ': ' . $medium_title);
        $gui->setConfirm($this->lng->txt('delete'), 'deleteLimit');
        $gui->setCancel($this->lng->txt('cancel'), 'showAdaptations');

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
        if ($this->http->wrapper()->query()->has($key)) {
            return $this->http->wrapper()->query()->retrieve($key, $this->refinery->kindlyTo()->int());
        }
        return null;
    }

    private function requestString(string $key): ?string
    {
        if ($this->http->wrapper()->query()->has($key)) {
            return $this->http->wrapper()->query()->retrieve($key, $this->refinery->kindlyTo()->string());
        }
        return null;
    }

    /**
     * @return int[]
     */
    private function getQuestionIds(): array
    {
        $question_ids = [];
        if ($this->test->isFixedTest()) {
            $question_ids = $this->test->getQuestions();
        } elseif ($this->test->isRandomTest()) {
            foreach ($this->test->getQuestionsOfTest($this->active_id) as $data) {
                $question_ids[] = $data['question_fi'] ?? 0;
            }
        }
        return $question_ids;
    }
}
