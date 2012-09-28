<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/UIComponent/classes/class.ilUIHookPluginGUI.php';
require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/RoundcubeEmbed/interfaces/interface.RoundcubeConstants.php';

class ilRoundcubeEmbedUIHookGUI extends ilUIHookPluginGUI implements RoundcubeConstants
{
	public function getHTML($a_comp, $a_part, $a_par = array())
	{
		if(!ilRoundcubeEmbedPlugin::getSettings()->get('is_enabled', 0))
		{
			return parent::getHTML($a_comp, $a_part, $a_par);
		}

		if('Services/Utilities' == $a_comp && 'redirect' == $a_part)
		{
			if(isset($_POST['username']) && isset($_POST['password']))
			{
				$_SESSION['roundcubeembed_username'] = $_POST['username'];
				$_SESSION['roundcubeembed_password'] = ilRoundcubeEmbedPlugin::encrypt($_POST['password']);
			}

			return parent::getHTML($a_comp, $a_part, $a_par);
		}

		if(!isset($_GET['rce_active']) || 'Services/PersonalDesktop' != $a_comp)
		{
			return parent::getHTML($a_comp, $a_part, $a_par);
		}

		if('center_column' == $a_part)
		{
			/**
			 * @var $ilTabs ilTabsGUI
			 * @var $tpl    ilTemplate
			 * @var $lng    ilLanguage
			 * @var $ilHelp ilHelpGUI
			 * @var $ilCtrl ilCtrl
			 * @var $ilUser ilObjUser
			 */
			global $ilTabs, $ilHelp, $tpl, $lng, $ilCtrl, $ilUser;

			$this->getPluginObject()->loadLanguageModule();

			if(version_compare(ILIAS_VERSION_NUMERIC, '4.3.0', '>='))
			{
				$ilHelp->setScreenIdComponent('mail');
			}

			$tpl->setTitle($lng->txt('mail') . ': ' . $this->getPluginObject()->txt('roundcube_inbox'));
			$tpl->setTitleIcon($this->getPluginObject()->getDirectory() . '/templates/images/roundcubeembed.png');

			if(version_compare(ILIAS_VERSION_NUMERIC, '4.3.0', '>='))
			{
				$ilTabs->setBackTarget($lng->txt('back'), $ilCtrl->getLinkTargetByClass('ilMailGUI'));
			}
			else
			{
				$ilTabs->setBackTarget($lng->txt('back'), 'ilias.php?baseClass=ilMailGUI');
			}

			require_once $this->getPluginObject()->getDirectory() . '/classes/class.ilRoundcubeHttpClient.php';
			$client = new ilRoundcubeHttpClient(ilRoundcubeEmbedPlugin::getSettings()->get('url'), ilRoundcubeEmbedPlugin::getSettings()->get('debug_mode'));

			try
			{
				if(!$client->isLoggedIn())
				{
					if(self::AUTH_MODE_ILIAS_CREDENTIALS == ilRoundcubeEmbedPlugin::getSettings()->get('auth_settings'))
					{
						$client->login($_SESSION['roundcubeembed_username'], ilRoundcubeEmbedPlugin::decrypt($_SESSION['roundcubeembed_password']));
					}
					else if(self::AUTH_MODE_UDF == ilRoundcubeEmbedPlugin::getSettings()->get('auth_settings'))
					{
						$data = $ilUser->getUserDefinedData();
						$client->login($data['f_' . ilRoundcubeEmbedPlugin::getSettings()->get('auth_udf_id')], ilRoundcubeEmbedPlugin::decrypt($_SESSION['roundcubeembed_password']));
					}
				}

				if($client->isLoggedIn())
				{
					$content_tpl = new ilTemplate($this->getPluginObject()->getDirectory() . '/templates/tpl.roundcube_embed.html', false, false);
					$content_tpl->setVariable('URL', ilRoundcubeEmbedPlugin::getSettings()->get('url'));
					return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => $content_tpl->get());
				}
				else
				{
					ilUtil::sendFailure($this->getPluginObject()->txt('login_not_successfull'), true);
					ilUtil::redirect(ILIAS_HTTP_PATH . '/ilias.php?baseClass=ilPersonalDesktopGUI');
				}
			}
			catch(Exception $e)
			{
				ilUtil::sendFailure($e->getMessage(), true);
				ilUtil::redirect(ILIAS_HTTP_PATH . '/ilias.php?baseClass=ilPersonalDesktopGUI');
			}
		}
		else if(in_array($a_part, array('left_column', 'right_column')))
		{
			return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => '');
		}

		return parent::getHTML($a_comp, $a_part, $a_par);
	}

	public function modifyGUI($a_comp, $a_part, $a_par = array())
	{
		if('tabs' == $a_part && 'ilmailgui' == strtolower($_GET['baseClass']))
		{
			/**
			 * @var $ilTabs ilTabsGUI
			 * @var $ilCtrl ilCtrl;
			 */
			global $ilTabs, $ilCtrl;

			$this->getPluginObject()->loadLanguageModule();

			if(ilRoundcubeEmbedPlugin::getSettings()->get('is_enabled', 0))
			{
				if(version_compare(ILIAS_VERSION_NUMERIC, '4.3.0', '>='))
				{
					$ilCtrl->setParameterByClass('ilPersonalDesktopGUI', 'rce_active', 1);
					$ilTabs->addTab('roundcube_inbox', $this->getPluginObject()->txt('roundcube_inbox'), $ilCtrl->getLinkTargetByClass('ilPersonalDesktopGUI'));
					$ilCtrl->setParameterByClass('ilPersonalDesktopGUI', 'rce_active', '');
				}
				else
				{
					$ilTabs->addTab('roundcube_inbox', $this->getPluginObject()->txt('roundcube_inbox'), 'ilias.php?baseClass=ilPersonalDesktopGUI&amp;rce_active=1');
				}
			}
		}
	}
}
