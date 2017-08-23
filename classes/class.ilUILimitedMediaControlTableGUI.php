<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

include_once('./Services/Table/classes/class.ilTable2GUI.php');

class ilUILimitedMediaControlTableGUI extends ilTable2GUI
{
	/**
	 * @var object $parent_obj
	 */
	protected $parent_obj;

	/**
	 * @var string $parent_cmd
	 */
	protected $parent_cmd;

	/**
	 * @var ilUILimitedMediaControlPlugin|null
	 */
	protected $plugin;


	/**
	 * ilExteStatTableGUI constructor.
	 * @param object	$a_parent_obj
	 * @param string 	$a_parent_cmd
	 */
	public function __construct($a_parent_obj, $a_parent_cmd)
	{
		global $lng, $ilCtrl;

		$this->lng = $lng;
		$this->ctrl = $ilCtrl;
		$this->parent_obj = $a_parent_obj;
		$this->parent_cmd = $a_parent_cmd;
		$this->plugin = $a_parent_obj->getPlugin();

        $this->setId('ilUILimitedMediaControl');
        $this->setPrefix('ilUILimitedMediaControl');

        parent::__construct($a_parent_obj, $a_parent_cmd);
	}
}