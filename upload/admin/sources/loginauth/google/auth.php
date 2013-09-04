<?php

if (!defined('IN_IPB')) {
	print "<h1>Incorrect access</h1>You cannot access this file directly. If you have recently upgraded, make sure you upgraded 'admin.php'.";
	exit();
}

/**
 * @class login_google
 * @brief IPB Google OAuth Login
 * @author Marcher
 * @last updated May 8, 3:30:00 PM 2012
 * @revision 4
 */
class login_google extends login_core implements interface_login {

	/**
	 *       Properties passed from database entry for this method
	 *       @access protected
	 *       @param  array
	 */
	protected $method_config = array();

	/**
	 *       Properties passed from conf.php for this method
	 *       @access protected
	 *       @param  array
	 */
	protected $external_conf = array();

    /**
     * @var googleOauth
     */
    public $googleLib;

	/**
	 *       Constructor
	 *       @access public
	 *       @param  object  ipsRegistry object
	 *       @param  array   DB entry array
	 *       @param  array   conf.php array
	 */
	public function __construct(ipsRegistry $registry, $method, $conf = array()) {
		$this->method_config = $method;
		$this->external_conf = $conf;

		parent::__construct($registry);
	}

	/**
	 *       Authenticate the member against your own system
	 *       @access  public
	 *       @param   string  Username
	 *       @param   string  Email Address
	 *       @param   string  Plain text password entered from log in form
	 *       Troll... none of these are used.... Gooogle handles ALL here.
	 */
	public function authenticate($username, $email_address, $password) {

		if ($this->request['use_google']) {

			$googleOauth = IPSLib::loadLibrary(IPS_KERNEL_PATH . '/google/oauth.php', 'googleOauth');
			$this->googleLib = new $googleOauth();
			$board_url = ipsRegistry::$settings['logins_over_https'] ? ipsRegistry::$settings['board_url_https'] : ipsRegistry::$settings['board_url'];

			if (ipsRegistry::$settings['logins_over_https'] and !$_SERVER['HTTPS']) {
				$this->registry->output->silentRedirect(ipsRegistry::$settings['base_url_https'] . "app=core&amp;module=global&amp;section=login&amp;do=process&amp;use_google=1&amp;auth_key=" . ipsRegistry::instance()->member()->form_hash);
			}

			$extra['auth_key'] = ipsRegistry::instance()->member()->form_hash;
			if ($this->request['rememberMe']) {
				$extra['rememberMe'] = 1;
			}

			if ($this->request['anonymous']) {
				$extra['anonymous'] = 1;
			}
			if ($this->request['referer']) {
				$extra['referer'] = $this->request['referer'];
			}
			if (!intval(ipsRegistry::$settings['google_login_force'])) {
				$type = 'online';
				$prompt = 'auto';
			} else {
				$type = 'offline';
				$prompt = 'force';
			}

			$extra = base64_encode(json_encode($extra));

			$google_url = $this->googleLib->buildUrl($board_url . '/interface/board/google.php', TRUE, array('https://www.googleapis.com/auth/userinfo.email', 'https://www.googleapis.com/auth/userinfo.profile'), 'https://accounts.google.com/o/oauth2/auth?', $extra, $type, $prompt);

            if($this->request['code']) {
                $this->googleLib->validateToken($board_url . '/interface/board/google.php');
                if ($this->googleLib->google['access_token']) {
                    $this->googleLib->getData('https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $this->googleLib->google['access_token']);
                }
            }

			if (!$this->googleLib->google['userinfo'] AND $this->request['use_google']) {
				$this->registry->output->silentRedirect($google_url);
			}

			if ($this->googleLib->google['userinfo']['id']) {

                if ($this->request['auth_key'] !== ipsRegistry::instance()->member()->form_hash) {
					$this->return_code = 'WRONG_AUTH';
				}
				$referrerval = $board_url;
# IPB handily fubs this... blegh... here we go
				if (strpos($this->request['state'], ';') !== FALSE) {
					$states = explode(';', $this->request['state']);
					foreach ($states as $s) {
						if (strpos($s, '~~') !== FALSE) {
							$data[] = explode('~~', $s);
						}
					}
				} elseif (strpos($this->request['state'], '~~') !== FALSE) {
					$data[] = explode('~~', $this->request['state']);
				}
				if (count($data)) {
					foreach ($data as $val) {
						if ($val[0] == 'referer') {
							$referrerval = urldecode($val[1]);
						}
					}
				}
				$this->request['referer'] = $referrerval;
				/** Test locally */
				$localMember = $this->DB->buildAndFetch(array('select' => 'member_id, google_access_token', 'from' => 'members', 'where' => "google_uid='{$this->googleLib->google['userinfo']['id']}'"));


				if ($localMember['member_id']) {
					if ($this->googleLib->google['access_token'] !== $localMember['google_access_token']) {
						$this->googleLib->save($localMember);
					}
					$this->member_data = $localMember;
					$this->return_code = 'SUCCESS';
					return true;
				} else {
					/** Test locally again */
					$exists = IPSMember::load($this->googleLib->google['userinfo']['email']);
					if ($exists['email']) {
						$this->member_data = $exists;
						$this->googleLib->save($exists);
						$this->return_code = 'SUCCESS';
						return true;
					} else {
						$email = $this->googleLib->google['userinfo']['email'];
					}
					$name = '';
					if (ipsRegistry::$settings['google_force_name'] === '1') {
						$name = $this->googleLib->google['userinfo']['given_name'];
					}
					if (ipsRegistry::$settings['google_force_name'] === '2') {
						$name = $this->googleLib->google['userinfo']['family_name'];
					}
					if (ipsRegistry::$settings['google_force_name'] === '3') {
						$name = $this->googleLib->google['userinfo']['name'];
					}
					if (!ipsRegistry::$settings['auth_allow_dnames'] && !$name) {
						$name = $this->googleLib->google['userinfo']['name'];
					}

					$this->member_data = IPSMember::create(array('core' => array(
									'email' => $email,
									'name' => $name,
									'members_l_username' => IPSText::mbstrtolower($name),
									'members_display_name' => $name,
									'members_l_display_name' => IPSText::mbstrtolower($name),
									'joined' => time(),
									'members_created_remote' => '1',
									'member_group_id' => ipsRegistry::$settings['google_mgid'],
									'google_access_token' => $this->googleLib->google['access_token'],
									'google_access_expires' => $this->googleLib->google['expires_in'],
									'google_refresh_token' => $this->googleLib->google['refresh_token'],
									'google_uid' => $this->googleLib->google['userinfo']['id'],
								)
									), TRUE, TRUE);
					$this->return_code = 'SUCCESS';
					return true;
				}
			}
		}
		return false;
	}

}
