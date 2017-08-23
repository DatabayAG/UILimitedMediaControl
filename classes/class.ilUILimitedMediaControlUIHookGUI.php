<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


include_once("./Services/UIComponent/classes/class.ilUIHookPluginGUI.php");

/**
 * User interface hook class
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilUILimitedMediaControlUIHookGUI extends ilUIHookPluginGUI
{
	/**
	 * Modify GUI objects, before they generate ouput
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 */
	function modifyGUI($a_comp, $a_part, $a_par = array())
	{
		/** @var ilCtrl $ilCtrl */
		/** @var ilTabsGUI $ilTabs */
		global $ilCtrl, $ilTabs;

		// add sub tab only if player is active
		if ($this->plugin_object->checkPlayerActive())
        {
            return;
        }

		switch ($a_part)
		{
			// case 'tabs':
			case 'sub_tabs':

				if ($ilCtrl->getCmdClass() == 'ilobjtestgui'
					and in_array($ilCtrl->getCmd(), array('participants')))
				{
					$ilCtrl->saveParameterByClass('ilUILimitedMediaControlGUI','ref_id');

					// we need to use the deprecated method because evaluation sub tabs work with automatic activation
					// with addSubTab the new sub tabs would always be activated
					$ilTabs->addSubTabTarget(
						$this->plugin_object->txt('media_limits'), // text is also the subtab id
						$ilCtrl->getLinkTargetByClass(array('ilUIPluginRouterGUI','ilUILimitedMediaControlGUI'), 'showAdaptations'),
						array('showAdaptations','selectParticipant', 'selectMedium', 'editLimit'), // commands to be recognized for activation
						'ilUILimitedMediaControlGUI', 	// cmdClass to be recognized activation
						'', 								// frame
						false, 							// manual activation
						true								// text is direct, not a language var
					);

					// save the tabs for reuse on the plugin pages
					// (these do not have the test gui as parent)
					// not nice, but effective
					$_SESSION['UILimitedMediaControl']['TabTarget'] = $ilTabs->target;
					$_SESSION['UILimitedMediaControl']['TabSubTarget'] = $ilTabs->sub_target;
				}

				if ($ilCtrl->getCmdClass()  == 'iluilimitedmediacontrolgui')
				{
					// reuse the tabs that were saved from the test gui
					if (isset($_SESSION['UILimitedMediaControl']['TabTarget']))
					{
						$ilTabs->target = $_SESSION['UILimitedMediaControl']['TabTarget'];
					}
					if (isset($_SESSION['UILimitedMediaControl']['TabSubTarget']))
					{
						$ilTabs->sub_target = $_SESSION['UILimitedMediaControl']['TabSubTarget'];
					}

					// this works because the tabs are rendered after the sub tabs
					$ilTabs->activateTab('participants');
				}

				break;

			default:
				break;
		}
	}

}
?>