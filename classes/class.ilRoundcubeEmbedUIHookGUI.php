<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/UIComponent/classes/class.ilUIHookPluginGUI.php';

class ilRoundcubeEmbedUIHookGUI extends ilUIHookPluginGUI
{
	public function getHTML($a_comp, $a_part, $a_par = array())
	{
		if(!isset($_GET['rce_active']) || 'Services/PersonalDesktop' != $a_comp)
		{
			return parent::getHTML($a_comp, $a_part, $a_par);
		}

		if('center_column' == $a_part)
		{
			/**
 			 * @var $ilTabs ilTabsGUI
			 * @var $tpl ilTemplate
			 * @var $lng ilLanguage
			 * @var $ilHelp ilHelpGUI
			 * @var $ilCtrl ilCtrl
			 */
			global $ilTabs, $ilHelp, $tpl, $lng, $ilCtrl;

			if(version_compare(ILIAS_VERSION_NUMERIC, '4.3.0', '>='))
			{
				$ilHelp->setScreenIdComponent('mail');
			}

			$tpl->setTitle($lng->txt('mail').': '.$this->getPluginObject()->txt('roundcube_inbox'));
			$tpl->setTitleIcon($this->getPluginObject()->getDirectory().'/templates/images/roundcubeembed.png');

			if(version_compare(ILIAS_VERSION_NUMERIC, '4.3.0', '>='))
			{
				$ilTabs->setBackTarget($lng->txt('back'), $ilCtrl->getLinkTargetByClass('ilMailGUI'));
			}
			else
			{
				$ilTabs->setBackTarget($lng->txt('back'), 'ilias.php?baseClass=ilMailGUI');
			}
			
			return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => 'HelloWorld');
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
