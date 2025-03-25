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

        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();

        if (!$this->plugin_object->checkPlayerActive()) {
            return;
        }

        if ($a_part == 'sub_tabs') {

            if ($this->ctrl->getCmdClass() == strtolower(ilTestParticipantsGUI::class)) {
                $this->ctrl->saveParameterByClass(ilUILimitedMediaControlGUI::class, 'ref_id');

                $this->tabs->addSubTab(
                    'media_limits',
                    $this->plugin_object->txt('media_limits'),
                    $this->ctrl->getLinkTargetByClass([ilUIPluginRouterGUI::class, ilUILimitedMediaControlGUI::class])
                );

                $this->saveTabs(ilTestParticipantsGUI::class);
            }

            if ($this->ctrl->getCmdClass() == strtolower(ilUILimitedMediaControlPlugin::class)) {
                $this->restoreTabs(ilTestParticipantsGUI::class);
                $this->tabs->activateTab('participants');
                $this->tabs->activateSubTab('media_limits');
            }

        }
    }

    protected function saveTabs(string $a_context): void
    {
        $this->setArrayInSession($a_context, 'TabTarget', $this->tabs->target);
        $this->setArrayInSession($a_context, 'TabSubTarget', $this->tabs->sub_target);
    }

    protected function restoreTabs(string $a_context): void
    {
        if (!empty($target = $this->getArrayFromSession($a_context, 'TabTarget'))) {
            $this->tabs->target = $target;
        }
        if (!empty($target = $this->getArrayFromSession($a_context, 'TabSubTarget'))) {
            $this->tabs->sub_target = $target;
        }
    }

    protected function setArrayInSession(string $a_context, string $name, array $array): void
    {
        ilSession::set(__class__ . '.' . $a_context . '.' . $name, serialize($array));
    }

    protected function getArrayFromSession(string $a_context, string $name): ?array
    {
        try {
            return unserialize(ilSession::get(__class__ . '.' . $a_context . '.' . $name));
        } catch (Exception $e) {
            return null;
        }
    }
}
