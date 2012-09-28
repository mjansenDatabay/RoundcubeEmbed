<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php';

class ilRoundcubeEmbedPlugin extends ilUserInterfaceHookPlugin
{
	/**
	 * @var int
	 */
	protected static $iv_source = MCRYPT_DEV_RANDOM;

	/**
	 * @var ilSetting
	 */
	protected static $settings;

	/**
	 * Get Plugin Name. Must be same as in class name il<Name>Plugin
	 * and must correspond to plugins subdirectory name.
	 * Must be overwritten in plugin class of plugin
	 * (and should be made final)
	 * @return string
	 */
	public function getPluginName()
	{
		return 'RoundcubeEmbed';
	}

	/**
	 * @param string $crypt_data
	 * @return string
	 */
	public static function decrypt($crypt_data)
	{
		$sym_key = self::getSymKey();

		$cipher   = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
		$iv       = mcrypt_create_iv(mcrypt_enc_get_iv_size($cipher), self::$iv_source);
		$key_size = mcrypt_enc_get_key_size($cipher);
		$sym_key  = substr($sym_key, 0, $key_size);
		mcrypt_generic_init($cipher, $sym_key, $iv);
		$plain_data = trim(mdecrypt_generic($cipher, self::urlbase64_decode($crypt_data)));
		mcrypt_generic_deinit($cipher);
		mcrypt_module_close($cipher);
		return $plain_data;
	}

	/**
	 * @param string $plain_data
	 * @return string
	 */
	public static function encrypt($plain_data)
	{
		$sym_key = self::getSymKey();

		$cipher   = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
		$iv       = mcrypt_create_iv(mcrypt_enc_get_iv_size($cipher), self::$iv_source);
		$key_size = mcrypt_enc_get_key_size($cipher);
		$sym_key  = substr($sym_key, 0, $key_size);
		mcrypt_generic_init($cipher, $sym_key, $iv);
		$crypt_data = self::urlbase64_encode(mcrypt_generic($cipher, $plain_data));
		mcrypt_generic_deinit($cipher);
		mcrypt_module_close($cipher);
		return $crypt_data;
	}

	protected static function getSymKey()
	{
		return md5(implode('|', array(CLIENT_ID, $_SERVER['HOST'])));
	}

	/**
	 * @param string $data
	 * @return string
	 */
	protected static function urlbase64_decode($data)
	{
		return base64_decode(str_replace(array('_', '-', '.'), array('/', '+', '='), $data), true);
	}

	/**
	 * @param string $data
	 * @return string
	 */
	protected static function urlbase64_encode($data)
	{
		return str_replace(array('/', '+', '='), array('_', '-', '.'), base64_encode($data));
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	public static function isRoundcubeConnectable($url)
	{
		require_once 'HTTP/Request.php';
		require_once 'classes/class.ilProxySettings.php';

		if(ilProxySettings::_getInstance()->isActive())
		{
			$options = array(
				'proxy_host' => ilProxySettings::_getInstance()->getHost(),
				'proxy_port' => ilProxySettings::_getInstance()->getPort()
			);
		}
		else
		{
			$options = array();
		}

		$req = new HTTP_Request($url, $options);
		$req->sendRequest();

		switch($req->getResponseCode())
		{
			// EVERYTHING OK
			case '200':
				// In the moment 301 will be handled as ok
			case '301':
			case '302':
				return true;

			default:
				return false;
		}
	}

	/**
 	 * @return ilSetting
	 */
	public static function getSettings()
	{
		if(null === self::$settings)
		{
			self::$settings = new ilSetting('roundcubeembed');
		}
		
		return self::$settings;
	}
}
