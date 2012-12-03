<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'HTTP/Request.php';;

class ilRoundcubeHttpClient
{
	/**
	 * @var
	 */
	private $rcHost;

	/**
	 * @var string
	 */
	private $rcPath;

	/**
	 * @var string
	 */
	private $rcSessionID;

	/**
	 * @var string
	 */
	private $rcSessionAuth;

	/**
	 * Save the current status of the Roundcube session. 0 = unkown, 1 = logged in, -1 = not logged in.
	 * @var int
	 */
	private $rcLoginStatus;

	/**
	 * Roundcube 0.5.1 adds a request token for 'security'. This variable
	 * saves the last token and sends it with login and logout requests.
	 * @var string
	 */
	private $lastToken;

	/**
	 * @var bool
	 */
	private $debug = false;

	/**
	 * @param $url
	 * @param $debug
	 */
	public function __construct($url, $debug)
	{
		$parts = parse_url($url);

		$this->rcHost        = $parts['host'];
		$this->rcPath        = $parts['path'];
		$this->debug         = $debug;
		$this->rcSessionID   = '';
		$this->rcSessionAuth = '';
		$this->rcLoginStatus = 0;
	}

	/**
	 * Login to Roundcube using the IMAP username/password
	 * Note: If the function detects that we're already logged in,
	 *       it performs a re-login, i.e. a logout/login-combination to ensure
	 *       that the specified user is logged in.
	 *       If you don't want this, use the isLoggedIn()-function and redirect
	 *       the RC without calling login().
	 * @param string IMAP username
	 * @param string IMAP password (plain text)
	 * @return boolean Returns TRUE if the login was successful, FALSE otherwise
	 * @throws RoundcubeLoginException
	 */
	public function login($username, $password)
	{
		$this->updateLoginStatus();

		// If already logged in, perform a re-login (logout first)
		if($this->isLoggedIn())
			$this->logout();

		$data = array(
			'_task'      => 'login',
			'_action'    => 'login',
			'_timezone'  => '1',
			'_dstactive' => '1',
			'_url'       => '',
			'_user'      => urlencode($username),
			'_pass'      => urlencode($password)
		);
		if($this->lastToken)
		{
			$data['_token'] = $this->lastToken;
		}

		$response = $this->sendRequest($this->rcPath, $data);

		$header           = $response->getResponseHeader();
		$response_cookies = $response->getResponseCookies();

		$del_cookie = null;
		foreach((array)$response_cookies as $cookie)
		{
			if(strpos($cookie['name'], 'sessauth') !== false && '-del-' == $cookie['value'])
			{
				$del_cookie = $cookie;
			}
		}

		//  Login successful! A redirection to ./?_task=... is a success!
		if(isset($header['location']) && preg_match('/^.*_task=/mi', $header['location']))
		{
			$this->debug("LOGIN SUCCESSFUL", "RC sent a redirection to ./?_task=..., that means we did it!");
			$this->rcLoginStatus = 1;
		}
		// Login failure detected! If the login failed, RC sends the cookie "sessauth=-del-"
		else if($del_cookie)
		{
			setcookie($del_cookie['name'], $del_cookie['value'], strtotime($del_cookie['expires']), $del_cookie['path'], $del_cookie['domain'], $del_cookie['secure']);
			$this->debug("LOGIN FAILED", "RC sent 'sessauth=-del-'; User/Pass combination wrong.");
			$this->rcLoginStatus = -1;
		}
		// Unkown, neither failure nor success.
		// This maybe the case if no session ID was sent
		else
		{
			$this->debug("LOGIN STATUS UNKNOWN", "Neither failure nor success. This maybe the case if no session ID was sent");
			throw new RoundcubeLoginException("Unable to determine login-status due to technical problems.");
		}

		return $this->isLoggedIn();
	}

	/**
	 * Returns whether there is an active Roundcube session.
	 * @return bool Return TRUE if a user is logged in, FALSE otherwise
	 * @throws RoundcubeLoginException
	 */
	public function isLoggedIn()
	{
		$this->updateLoginStatus();

		if(!$this->rcLoginStatus)
			throw new RoundcubeLoginException("Unable to determine login-status due to technical problems.");

		return ($this->rcLoginStatus > 0) ? true : false;
	}

	/**
	 * Logout from Roundcube
	 * @return bool Returns TRUE if the login was successful, FALSE otherwise
	 */
	public function logout()
	{
		$data = array(
			'_task'   => 'logout',
			'_action' => 'logout'
		);
		if($this->lastToken)
		{
			$data['_token'] = $this->lastToken;
		}

		$this->sendRequest($this->rcPath, $data);

		return !$this->isLoggedIn();
	}

	/**
	 * Simply redirect to the Roundcube application.
	 */
	public function redirect()
	{
		header("Location: {$this->rcPath}");
		exit;
	}

	/**
	 * Gets the current login status and the session cookie.
	 * It updates the private variables rcSessionID and rcLoginStatus by
	 * sending a request to the main page and parsing the result for the login form.
	 */
	private function updateLoginStatus($forceUpdate = false)
	{
		if($this->rcSessionID && $this->rcLoginStatus && !$forceUpdate)
			return;

		// Get current session ID cookie
		if($_COOKIE['roundcube_sessid'])
			$this->rcSessionID = $_COOKIE['roundcube_sessid'];

		if($_COOKIE['roundcube_sessauth'])
			$this->rcSessionAuth = $_COOKIE['roundcube_sessauth'];

		// Send request and maybe receive new session ID
		$response = $this->sendRequest($this->rcPath);

		// Request token (since Roundcube 0.5.1)
		if(preg_match('/"request_token":"([^"]+)",/mi', $response->getResponseBody(), $m))
			$this->lastToken = $m[1];

		if(preg_match('/<input.+name="_token".+value="([^"]+)"/mi', $response->getResponseBody(), $m))
			$this->lastToken = $m[1]; // override previous token (if this one exists!)            

		// Login form available?
		if(preg_match('/<input.+name="_pass"/mi', $response->getResponseBody()))
		{
			$this->debug("NOT LOGGED IN", "Detected that we're NOT logged in.");
			$this->rcLoginStatus = -1;
		}

		else if(preg_match('/<div.+id="message"/mi', $response->getResponseBody()))
		{
			$this->debug("LOGGED IN", "Detected that we're logged in.");
			$this->rcLoginStatus = 1;
		}

		else
		{
			$this->debug("UNKNOWN LOGIN STATE", "Unable to determine the login status. Did you change the RC version?");
			throw new RoundcubeLoginException("Unable to determine the login status. Unable to continue due to technical problems.");
		}

		// If no session ID is available now, throw an exception
		if(!$this->rcSessionID)
		{
			$this->debug("NO SESSION ID", "No session ID received. RC version changed?");
			throw new RoundcubeLoginException("No session ID received. Unable to continue due to technical problems.");
		}
	}

	/**
	 * @param      $path
	 * @param bool $postData
	 * @return HTTP_Request
	 * @throws RoundcubeLoginException
	 */
	private function sendRequest($path, $postData = false)
	{
		/**
		 * @var $https ilHTTPS
		 */
		global $https;

		if(version_compare(ILIAS_VERSION_NUMERIC, '4.3.0', '>='))
		{
			require_once 'Services/Http/classes/class.ilProxySettings.php';
		}
		else
		{
			require_once 'classes/class.ilProxySettings.php';
		}

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

		$options['method'] = (!$postData) ? HTTP_REQUEST_METHOD_GET : HTTP_REQUEST_METHOD_POST;

		$req = new HTTP_Request(($https->isDetected() ? 'https://' : 'http://') . $this->rcHost . $path, $options);
		$req->addHeader('User-Agent', $_SERVER['HTTP_USER_AGENT']);

		foreach($_COOKIE as $name => $value)
			$req->addCookie($name, $value);

		// Add roundcube session ID if available
		if(!$_COOKIE['roundcube_sessid'] && $this->rcSessionID)
			$req->addCookie('roundcube_sessid', $this->rcSessionID);

		if(!$_COOKIE['roundcube_sessauth'] && $this->rcSessionAuth)
			$req->addCookie('roundcube_sessauth', $this->rcSessionAuth);

		if(is_array($postData))
		{
			foreach($postData as $name => $value)
			{
				$req->addPostData($name, $value, true);
			}
		}

		$req->sendRequest();
		
		$this->debug('REQUEST', $req->_buildRequest());

		if(404 == $req->getResponseCode())
		{
			throw new RoundcubeLoginException("No Roundcube installation found at '$path'");
		}

		$response_cookies = $req->getResponseCookies();
		foreach((array)$response_cookies as $cookie)
		{
			setcookie($cookie['name'], $cookie['value'], strtotime($cookie['expires']), $cookie['path'], $cookie['domain'], $cookie['secure']);

			if('roundcube_sessid' == $cookie['name'])
			{
				$this->debug("GOT SESSION ID", "New session ID: '" . $cookie['value'] . "'.");
				$this->rcSessionID = $cookie['value'];
			}
			else if('roundcube_sessauth' == $cookie['name'])
			{
				$this->debug("GOT SESSION AUTH", "New session auth: '" . $cookie['value'] . "'.");
				$this->rcSessionAuthi = $cookie['value'];
			}
		}

		// Request token (since Roundcube 0.5.1)
		if(preg_match('/"request_token":"([^"]+)",/mi', $req->getResponseBody(), $m))
			$this->lastToken = $m[1];

		if(preg_match('/<input.+name="_token".+value="([^"]+)"/mi', $req->getResponseBody(), $m))
			$this->lastToken = $m[1]; // override previous token (if this one exists!)

		$this->debug("RESPONSE", $req->getResponseBody());

		return $req;
	}

	/**
	 * @param $action
	 * @param $message
	 */
	private function debug($action, $message)
	{
		/**
		 * @var $ilLog ilLog
		 */
		global $ilLog;

		if($this->debug)
		{
			$ilLog->write('RoundcubeEmbed Plugin: ' . $action . ' | ' . $message);
		}
	}
}

/**
 * This Roundcube login exception will be thrown if the two
 * login attempts fail.
 */
class RoundcubeLoginException extends Exception
{
}
