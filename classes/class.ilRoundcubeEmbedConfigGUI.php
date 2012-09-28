<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Component/classes/class.ilPluginConfigGUI.php';
require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once 'Services/User/classes/class.ilUserDefinedFields.php';
require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/RoundcubeEmbed/interfaces/interface.RoundcubeConstants.php';


class ilRoundcubeEmbedConfigGUI extends ilPluginConfigGUI implements RoundcubeConstants
{
	/**
	 * @var ilPropertyFormGUI
	 */
	protected $form;

	/**
	 * @param string $cmd
	 */
	public function performCommand($cmd)
	{
		switch($cmd)
		{
			default:
				$this->$cmd();
				break;
		}
	}

	/**

	 */
	protected function configure()
	{
		$this->editSettings();
	}

	/**

	 */
	protected function initSettingsForm()
	{
		/**
		 * @var $ilCtrl ilCtrl
		 */
		global $lng, $ilCtrl;

		$this->form = new ilPropertyFormGUI();

		$this->form->setFormAction($ilCtrl->getFormAction($this, 'saveSettings'));
		$this->form->setTitle($this->getPluginObject()->txt('roundcube_settings'));

		$this->form->addCommandButton('saveSettings', $lng->txt('save'));

		$enable = new ilCheckboxInputGUI($this->getPluginObject()->txt('enable'), 'is_enabled');
		$enable->setInfo($this->getPluginObject()->txt('enable_info'));
		$this->form->addItem($enable);

		$url = new ilTextInputGUI($this->getPluginObject()->txt('url'), 'url');
		$url->setRequired(true);
		$url->setInfo($this->getPluginObject()->txt('url_info'));
		$this->form->addItem($url);

		$auth_settings = new ilRadioGroupInputGUI($this->getPluginObject()->txt('auth_settings'), 'auth_settings');
		$auth_settings->setRequired(true);

		$radio_ilias_auth = new ilRadioOption($this->getPluginObject()->txt('auth_settings_ilias'), self::AUTH_MODE_ILIAS_CREDENTIALS);
		$radio_ilias_auth->setInfo($this->getPluginObject()->txt('auth_settings_ilias_info'));
		$auth_settings->addOption($radio_ilias_auth);

		$radio_selected_field_auth = new ilRadioOption($this->getPluginObject()->txt('auth_with_udf'), self::AUTH_MODE_UDF);
		$radio_selected_field_auth->setInfo($this->getPluginObject()->txt('auth_with_udf_info'));

		$udf = new ilSelectInputGUI($this->getPluginObject()->txt('auth_udf_field'), 'auth_udf_id');
		$udf->setRequired(true);

		$options = array();
		foreach(ilUserDefinedFields::_getInstance()->getDefinitions() as $definition)
		{
			$options[$definition['field_id']] = $definition['field_name'];
		}
		if(count($options))
		{
			$udf->setOptions($options);
			$udf->setRequired(true);
			$radio_selected_field_auth->addSubItem($udf);
			$auth_settings->addOption($radio_selected_field_auth);
		}
		else
		{
			$setting = new ilSetting('roundcubeembed');
			$setting->set('auth_settings', self::AUTH_MODE_ILIAS_CREDENTIALS);
		}

		$this->form->addItem($auth_settings);
	}

	/**
	 * @return array
	 */
	protected function getSettingsValues()
	{
		$values = array();

		$setting = new ilSetting('roundcubeembed');

		$values['is_enabled']    = $setting->get('is_enabled', 0);
		$values['url']           = $setting->get('url');
		$values['auth_settings'] = !$setting->get('auth_udf_id') ? self::AUTH_MODE_ILIAS_CREDENTIALS : $setting->get('auth_settings');
		$values['auth_udf_id']   = $setting->get('auth_udf_id');

		return $values;
	}

	/**

	 */
	protected function editSettings()
	{
		global $tpl;

		$status = $this->handlePreconditions();
		$this->initSettingsForm();
		$values = $this->getSettingsValues();
		$this->form->setValuesByArray($values);
		if(!$status || !($connectable = ilRoundcubeEmbedPlugin::isRoundcubeConnectable($values['url'])))
		{
			if(!$connectable)
			{
				ilUtil::sendFailure(sprintf($this->getPluginObject()->txt('url_not_connectable'), $values['url']));
			}
			$setting = new ilSetting('roundcubeembed');
			$setting->set('is_enabled', 0);
			$this->form->getItemByPostVar('is_enabled')->setChecked(false);
		}

		$tpl->setContent($this->form->getHTML());
	}

	/**

	 */
	protected function saveSettings()
	{
		/**
		 * @var $tpl ilTemplate
		 * @var $lng ilLanguage
		 */
		global $tpl, $lng;

		$deactivate = false;
		$setting    = new ilSetting('roundcubeembed');

		$status = $this->handlePreconditions();
		$this->initSettingsForm();
		if($status && $this->form->checkInput())
		{
			$setting->set('is_enabled', $this->form->getInput('is_enabled'));
			$setting->set('url', $this->form->getInput('url'));
			$setting->set('auth_settings', $this->form->getInput('auth_settings'));
			$setting->set('auth_udf_id', $this->form->getInput('auth_udf_id'));

			if(!ilRoundcubeEmbedPlugin::isRoundcubeConnectable($this->form->getInput('url')))
			{
				ilUtil::sendFailure(sprintf($this->getPluginObject()->txt('url_not_connectable'), $setting->get('url')));
				$deactivate = true;
			}
			else
			{
				ilUtil::sendSuccess($lng->txt('saved_successfully'));
			}
		}
		else
		{
			$deactivate = true;
		}

		if($deactivate)
		{
			$setting->set('is_enabled', 0);
			$_POST['is_enabled'] = 0;
		}

		$this->form->setValuesByPost();

		$tpl->setContent($this->form->getHTML());
		return;
	}

	/**

	 */
	protected function handlePreconditions()
	{
		/**
		 * @var $ilCtrl ilCtrl
		 */
		global $ilCtrl;

		$messages = array();

		if(!extension_loaded('mcrypt') || !function_exists('mcrypt_module_open'))
		{
			$messages[] = '<li>' . $this->getPluginObject()->txt('mcrypt_module_missing') . '</li>';
		}

		if(count($messages))
		{
			$setting = new ilSetting('roundcubeembed');
			$setting->set('is_enabled', 0);

			$message = $this->getPluginObject()->txt('precondition_error') . '<br /><ul>' . implode('\n', $messages) . '</ul>';

			ilUtil::sendFailure($message);

			return false;
		}

		return true;
	}
}

?>