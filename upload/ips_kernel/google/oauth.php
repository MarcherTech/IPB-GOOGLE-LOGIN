<?php

/**
 * @class googleOauth
 * @brief IPB-Style API Of Google OAuth Web Application Flow
 * @author Marcher
 * @last updated May 8, 3:30:00 PM 2012
 * @revision 4
 */
class googleOauth {

	/**
	 * incoming data
	 */
	public $google = array();
	private $fileManagement = null;

	public function __construct() {
		
	}

	/**
	 * @access method to nicely access fileManagement throughout
	 * @return void
	 */
	public function fileManagement() {
		if ($this->fileManagement == null || !is_object($this->fileManagement)) {
			$classToLoad = IPSLib::loadLibrary(IPS_KERNEL_PATH . '/classFileManagement.php', 'classFileManagement');
			$this->fileManagement = new $classToLoad();
			$this->fileManagement->timeout = 1;
		}
		return $this->fileManagement;
	}

	/**
	 * Get the URL to  Google Authorization for a service/services.
	 *
	 * @param mixed returnTo URI to tell google where to return, MUST BE THE FULL URI WITH THE PROTOCOL
	 * @param bool useAmp Use &amp; in the URL, true; or just &, false. 
	 * @param mixed scope Service url or array of Service urls to request access for.
	 * @param string Base Google Url with ?
	 * @param string any state string you need to send. delmited so: key1:value1;key2:value2;key3:value3
	 * @param string Type of access, offline or offline.
	 * @param string Type of prompt, force or auto.
	 * @return string The string to go in the URL
	 */
	public function buildUrl($returnTo = FALSE, $useAmp = TRUE, $scope = array('https://www.googleapis.com/auth/userinfo.email', 'https://www.googleapis.com/auth/userinfo.profile'), $base = 'https://accounts.google.com/o/oauth2/auth?', $state = FALSE, $type = 'online', $prompt = 'auto') {
		$state = $state ? $state : 'auth_key~~' . ipsRegistry::instance()->member()->form_hash;
		$returnTo = (!$returnTo) ? ipsRegistry::$settings['this_url'] : $returnTo;
		$scopes = is_array($scope) ? $scope : array($scope);
		$params = array(
			'scope' => is_array($scope) ? implode(' ', $scope) : $scope,
			'response_type' => 'code',
			'redirect_uri' => $returnTo,
			'client_id' => ipsRegistry::$settings['google_client_id'],
			'state' => $state,
			'access_type' => $type,
			'approval_prompt' => $prompt,
		);
		foreach ($scopes as $data) {
			$this->google['scopeData'][] = 'google_' . str_replace(array('https://www.googleapis.com/auth/', '.'), '', $data);
		}

		$sep = ($useAmp) ? '&amp;' : '&';
		return $base . urldecode(http_build_query($params, '', $sep));
	}

	/**
	 * Validate a Token
	 * @param mixed returnTo URI to tell google where to return, MUST BE THE FULL URI WITH THE PROTOCOL
	 * @param string Base Google Url with ?
	 * @return string Returns the Google token info if successful or FALSE on failure
	 */
	public function validateToken($returnTo = FALSE, $base = 'https://accounts.google.com/o/oauth2/token?') {
		/** Start off with some basic checks */
		if (ipsRegistry::$request['error']) {
			return FALSE;
		}
		$returnTo = (!$returnTo) ? ipsRegistry::$settings['this_url'] : $returnTo;
		/** IPB fiddles with CODE to make it DO... so we use google
		 * Start off with some basic params
		 */
		$params = array(
			'code' => ipsRegistry::$request['google'],
			'client_id' => ipsRegistry::$settings['google_client_id'],
			'client_secret' => ipsRegistry::$settings['google_client_secret'],
			'redirect_uri' => $returnTo,
			'grant_type' => 'authorization_code',
		);

		$google = self::fileManagement()->postFileContents($base, $params);
		if (!strstr($google, 'access_token')) {
			return FALSE;
		}
		$gData = json_decode($google, TRUE);

		if (is_array($gData)) {
			foreach ($gData as $g => $d) {
				/** store it in our "nice" array. */
				$this->google[$g] = $d;
			}
		}
		return $this->google['access_token'] ? $this->google : FALSE;
	}

	/**
	 * Programmatically Invalidates a members Token
	 * @param mixed member_id to lazyLoad, or memberData, MUST Have a google_refresh_token
	 * 
	 * @return void
	 */
	public function removeToken($member) {
		if (!is_array($member)) {
			$member = IPSMember::load($member);
		}

		self::fileManagement()->getFileContents('https://accounts.google.com/o/oauth2/revoke?token=' . $member['google_refresh_token']);

		return TRUE;
	}

	/**
	 * Get a Members Token, clears the details loaded, so be careful.
	 * @param mixed member_id to be lazy-loaded, or memberData
	 * @return string Returns the relevant Google token info if successful or FALSE on failure
	 */
	public function getToken($member) {
		if (!is_array($member)) {
			$member = IPSMember::load($member);
		}
		//store it in our nice array.
		$this->google['access_token'] = $member['google_access_token'];
		$this->google['expires_in'] = $member['google_access_expires'];
		$this->google['refresh_token'] = $member['google_refresh_token'];
		return !intval($this->google['expires_in']) ? ($this->google['refresh_token'] ? $this->google['refresh_token'] : $this->google['access_token']) : FALSE;
	}

	/**
	 * Get a Members access Token with a refresh_token, clears the details loaded, so be careful.
	 * @param string member google_refresh_token
	 * @return string Returns the relevant Google access token info if successful or FALSE on failure
	 */
	public function refreshToken($token) {

		$token = (!$token) ? $this->google['refresh_token'] : $token;
		$params = array(
			'refresh_token' => $token,
			'client_id' => ipsRegistry::$settings['google_client_id'],
			'client_secret' => ipsRegistry::$settings['google_client_secret'],
			'grant_type' => 'refresh_token',
		);
		$google = self::fileManagement()->postFileContents('https://accounts.google.com/o/oauth2/token?', $params);
		if (!strstr($google, 'access_token')) {
			return FALSE;
		}
		$gData = json_decode($google, TRUE);

		if (is_array($gData)) {
			foreach ($gData as $g => $d) {
				/** store it in our "nice" array. */
				$this->google[$g] = $d;
			}
		}
		return $this->google['access_token'] ? $this->google : FALSE;
	}

	/**
	 * Gets Google API Service Data On Demand, MUST Have Valid token and authorization for the service requested, also MUST have said API Enabled In Google
	 * @param mixed URL of Service, defaults to userinfo, must include access key if provided.
	 * @param string alias to set/retrieve from, useful for organizing multiple API's
	 * 
	 * @return string Returns the relevant API Service Data if successful or FALSE on failure
	 */
	public function getData($url = FALSE, $alias = 'userinfo') {
		if (is_array($this->google[$alias]) && !$url) {
			return $this->google[$alias];
		}
		$url = $url ? $url : 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $this->google['access_token'];
		$google = self::fileManagement()->getFileContents($url);
		$gData = json_decode($google, TRUE);
		if (is_array($gData)) {
			foreach ($gData as $g => $d) {
				//store it in our nice array.
				$this->google[$alias][$g] = $d;
			}
		}
		return is_array($this->google[$alias]) ? $this->google[$alias] : FALSE;
	}

	/**
	 * Gets a Members Google API Service Data On Demand, MUST Have Valid token and authorization for the service requested, also MUST have said API Enabled In Google
	 * @param string alias of Service to "load"
	 * @param mixed FULL URL of Service, defaults to NOT getting the data again, must include access key if provided.
	 * @param string alias to set/retrieve from, useful for organizing multiple API's
	 * @return string Returns the relevant Members API Service Data if successful or FALSE on failure
	 */
	public function load($alias = 'userinfo', $get = FALSE, $member) {
		if ($get) {
			self::getToken($member);
			self::getData($get, $alias);
			return $this->google[$alias];
		}
		if ($this->google[$alias]) {
			return $this->google[$alias];
		}

		return FALSE;
	}

	/**
	 * updates an existing member with incoming data... used when the the user validates, but the access_token does not match the existing members row to update.
	 * @param mixed member_id or memberData
	 * @return true
	 */
	public function save($member) {
		IPSMember::save(is_array($member) ? $member['member_id'] : $member, array('core' =>
			array('google_access_token' => $this->google['access_token'] ? $this->google['access_token'] : '0',
				'google_access_expires' => $this->google['expires_in'] ? $this->google['expires_in'] : '0',
				'google_refresh_token' => trim($this->google['refresh_token']) ? $this->google['refresh_token'] : '0',
				'google_uid' => $this->google['userinfo']['id'] ? $this->google['userinfo']['id'] : '0')));
		return TRUE;
	}

	/**
	 * 
	 * @param string $state request State parameter to be decoded for use
	 * @return mixed, Boolean FALSE if failed, array of data from the state parameter if successful, also seeds ipsRegistry::$request with said data for sanity.
	 */
	public function getState($state = false) {
		$state = $state ? $state : ipsRegistry::$request['state'];
		if (strpos($state, ';') !== FALSE) {
			$states = explode(';', $state);
			foreach ($states as $s) {
				if (strpos($s, ':') !== FALSE) {
					$data[] = explode(':', $s);
				}
			}
		} elseif (strpos($state, ':') !== FALSE) {
			$data[] = explode(':', $s);
		}
		if (is_array($data)) {
			foreach ($data as $val) {
				$this->google['stateData'][$val[0]] = $val[1];
				ipsRegistry::$request[$val[0]] = $val[1];
			}
		}
		return is_array($this->google['stateData']) ? $this->google['stateData'] : FALSE;
	}

}
