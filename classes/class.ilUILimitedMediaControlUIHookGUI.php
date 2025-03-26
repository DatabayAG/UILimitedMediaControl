<?php

declare(strict_types=1);

class ilUILimitedMediaControlUIHookGUI extends ilUIHookPluginGUI
{
    protected ilCtrlInterface $ctrl;
    protected ilTabsGUI $tabs;

    public function modifyGUI(
        string $a_comp,
        string $a_part,
        array $a_par = array()
    ): void {

        if (!$this->plugin_object->checkPlayerActive()) {
            return;
        }

        if ($a_part == 'sub_tabs') {

            global $DIC;
            $this->ctrl = $DIC->ctrl();
            $this->tabs = $DIC->tabs();

            if (in_array($this->ctrl->getCmdClass(), [strtolower(ilTestParticipantsGUI::class),
                                                      strtolower(ilTestParticipantsTableGUI::class)])) {
                $this->ctrl->saveParameterByClass(ilUILimitedMediaControlGUI::class, 'ref_id');

                $this->tabs->addSubTab(
                    'media_limits',
                    $this->plugin_object->txt('media_limits'),
                    $this->ctrl->getLinkTargetByClass([ilUIPluginRouterGUI::class, ilUILimitedMediaControlGUI::class])
                );

                $this->setArrayInSession('TabTarget', $this->tabs->target);
                $this->setArrayInSession('TabSubTarget', $this->tabs->sub_target);
            }

            if ($this->ctrl->getCmdClass() == strtolower(ilUILimitedMediaControlGUI::class)) {
                if (!empty($target = $this->getArrayFromSession('TabTarget'))) {
                    $this->tabs->target = $target;
                }
                if (!empty($target = $this->getArrayFromSession('TabSubTarget'))) {
                    $this->tabs->sub_target = $target;
                }
                $this->tabs->activateTab('dashboard_tab');
                $this->tabs->activateSubTab('media_limits');
            }

        }
    }

    protected function setArrayInSession(string $name, array $array): void
    {
        ilSession::set(__class__ . '/' . $name, serialize($array));
    }

    protected function getArrayFromSession(string $name): ?array
    {
        try {
            return unserialize(ilSession::get(__class__ . '/' . $name));
        } catch (Exception $e) {
            return null;
        }
    }
}
