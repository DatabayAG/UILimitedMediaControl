<?php

declare(strict_types=1);

use ILIAS\DI\Container;

class ilUILimitedMediaControlPlugin extends ilUserInterfaceHookPlugin
{
    private Container $dic;
    private ilObjUser $user;
    private ?ilPageComponentPlugin $player_plugin;

    protected function init(): void
    {
        global $DIC;

        $this->dic = $DIC;
        $this->user = $DIC->user();
        $this->player_plugin = $this->dic['component.factory']->getPlugin('limply');
    }

    /**
     * Get the player plugin
     * Use base class as return type because plugin may not exist
     * @return ilPCLimitedMediaPlayerPlugin|null
     */
    public function getPlayerPlugin(): ilPageComponentPlugin
    {
        return $this->player_plugin;
    }

    public function getUserPreference(string $name, string $default = ''): string
    {
        $value = $this->user->getPref($this->getId() . '_' . $name);
        return $value ?? $default;
    }

    public function setUserPreference(string $name, string $value)
    {
        $this->user->writePref($this->getId() . '_' . $name, $value);
    }

    public function checkPlayerActive(): bool
    {
        return $this->getPlayerPlugin() !== null && $this->getPlayerPlugin()->isActive();
    }

    /**
     * @throws ilPluginException
     */
    public function activate(): bool
    {
        if (!$this->checkPlayerActive()) {
            throw new ilPluginException($this->txt("player_plugin_not_active"));
        } else {
            return parent::activate();
        }
    }
}
