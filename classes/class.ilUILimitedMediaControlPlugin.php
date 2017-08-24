<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

include_once("./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");
 
/**
 * Basic plugin file
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 */
class ilUILimitedMediaControlPlugin extends ilUserInterfaceHookPlugin
{
	/**
	 * @var ilUILimitedMediaControlPlugin $config
	 */
	protected $config;



	public function getPluginName()
	{
		return "UILimitedMediaControl";
	}


	/**
	 * Get a user preference
	 * @param string	$name
	 * @param mixed		$default
	 * @return mixed
	 */
	public function getUserPreference($name, $default = false)
	{
		global $ilUser;
		$value = $ilUser->getPref($this->getId().'_'.$name);
		if ($value !== false)
		{
			return $value;
		}
		else
		{
			return $default;
		}
	}


	/**
	 * Set a user preference
	 * @param string	$name
	 * @param mixed		$value
	 */
	public function setUserPreference($name, $value)
	{
		global $ilUser;
		$ilUser->writePref($this->getId().'_'.$name, $value);
	}


    /**
     * Check if the player plugin is active
     * @return bool
     */
	public function checkPlayerActive()
    {
        /** @var ilPluginAdmin $ilPluginAdmin */
        global $ilPluginAdmin;

        return $ilPluginAdmin->isActive('Services', 'COPage', 'pgcp', 'PCLimitedMediaPlayer');
    }


    /**
     * Check if plugin can be activated
     * @return bool
     * @throws ilPluginException
     */
    public function beforeActivation()
    {
        if (!$this->checkPlayerActive())
        {
            ilUtil::sendFailure($this->txt("player_plugin_not_active"), true);
            throw new ilPluginException($this->txt("player_plugin_not_active"));
        }
        else
        {
            return parent::beforeActivation();
        }
    }


    /**
     * Get the limits defined for a test
     * @param   int   $a_obj_id    obj_id of the test object
     * @return  ilLimitedMediaPlayerLimits[]
     */
    public function getTestLimits($a_obj_id)
    {
        require_once("Customizing/global/plugins/Services/COPage/PCLimitedMediaPlayer/classes/class.ilLimitedMediaPlayerLimits.php");
        return ilLimitedMediaPlayerLimits::getTestLimits($a_obj_id);
    }
}

?>