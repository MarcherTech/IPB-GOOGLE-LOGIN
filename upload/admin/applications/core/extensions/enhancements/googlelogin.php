<?php
/**
 * @file		googlelogin.php 	Community Enhancements - Google Login
 * $Copyright: (c) 2001 - 2012 Marcher Technologies$
 * $URL: http://community.invisionpower.com/files/file/5467-sign-in-through-google/$
 * $Author: Author Robert $
 * @since		13 Dec 2012
 * @version		v1.0.6
 * $Revision: 1$
 */

/**
 *
 * @class		enhancements_core_googlelogin
 * @brief		Community Enhancements - Google Login
 */
class enhancements_core_googlelogin
{
	/**
	 * Applicable Settings
	 */
	public $settings = array( 'google_client_id', 'google_client_secret', 'google_force_name', 'google_login_force', 'google_mgid' );
	
	/**
	 * Constructor
	 *
	 * @param	ipsRegistry
	 */
	public function __construct( $registry )
	{
		$enabled = FALSE;
		$hookEnabled = FALSE;
		$disabled = '';
		$registry->getClass('class_localization')->loadLanguageFile(array('public_login'), 'core');
		if(ipsRegistry::$settings['google_client_id'] && ipsRegistry::$settings['google_client_id'])
		{
			$hooks = $registry->cache()->getCache('hooks');
			if( is_array( $hooks['templateHooks']['skin_global'] ) )
				{
					foreach( $hooks['templateHooks']['skin_global'] as $c => $hook )
						{
						if(in_array($hook['className'], array('googleLoginDisplayIcon', 'googleLoginDisplayButtonAjaxExtra', 'googlePasswordReminder') ) )
						{
							if(IPSLib::loginMethod_enabled('google'))
							{
					$enabled = TRUE;
							}
					$hookEnabled = TRUE;
					break;
						}
						}
				}
		}
		if(!$enabled)
		{
			if(!IPSLib::loginMethod_enabled('google'))
			{
				$disabled .= "<a href='".$registry->getClass('output')->buildUrl('app=core&amp;module=tools&amp;section=login&amp;do=login_overview', 'admin')."' target='_blank'>".$registry->getClass('class_localization')->words['google_settings_module_disabled']."</a> ";
			}
			if(!$hookEnabled)
			{
		$disabled .= "<a href='".$registry->getClass('output')->buildUrl('app=core&amp;module=applications&amp;section=hooks&amp;do=hooks_overview', 'admin')."' target='_blank'>".$registry->getClass('class_localization')->words['google_settings_hook_disabled']."</a> ";
			}
			
		}
		$this->title = $registry->getClass('class_localization')->words['sign_in_google'];
		$this->description = $registry->getClass('class_localization')->words['enhancements_googlelogin_desc'];
		$this->message = "<a href='https://code.google.com/apis/console#access' target='_blank'>{$registry->getClass('class_localization')->words['enhancements_googlelogin_help']}</a>";
	if($disabled)
	{
		$this->message .= '<br />'.$disabled;
	}
		$this->enabled = $enabled;
	}
}