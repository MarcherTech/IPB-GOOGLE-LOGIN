<?php
define( 'IPB_THIS_SCRIPT', 'api' );
define( 'IPB_LOAD_SQL'   , 'queries' );
define( 'IPS_PUBLIC_SCRIPT', 'index.php' );

require_once( '../../initdata.php' );/*noLibHook*/

//-----------------------------------------
// Main code
//-----------------------------------------

require_once( IPS_ROOT_PATH . 'sources/base/ipsRegistry.php' );/*noLibHook*/
require_once( IPS_ROOT_PATH . 'sources/base/ipsController.php' );/*noLibHook*/

$_GET['app']        = 'core';
$_REQUEST['app']    = 'core';
$_GET['module']     = 'usercp';
$_REQUEST['module']    = 'usercp';
$_GET['tab']        = 'core';
$_REQUEST['tab']    = 'core';
$_GET['area']       = 'managegoogle';
$_REQUEST['area']   = 'managegoogle';
$_GET['google'] = $_GET['code'];
$_REQUEST['google'] = $_REQUEST['code'];
$_GET['state'] = $_GET['state'];
$_REQUEST['state'] = $_REQUEST['state'];
/* IPB fiddles with CODE to make it DO */
if ( ! $_GET['code'] AND $_GET['do'] )
{
	$_GET['google'] = $_GET['do'];
}

if ( ! $_REQUEST['code'] AND $_REQUEST['do'] )
{
	$_REQUEST['google'] = $_REQUEST['do'];
}
if($_GET['state'] || $_REQUEST['state'])
{
	$state = $_GET['state']?$_GET['state']:$_REQUEST['state'];
	
$data = array();
	if(strpos($state, ';')!==FALSE)
	{
		$states = explode(';', $state);
		foreach($states as $s)
		{
			if(strpos($s, '~~')!==FALSE)
			{
				$data[] = explode('~~', $s);
			}
		}
	}
	elseif(strpos($state, '~~')!==FALSE)
	{
		$data[] = explode('~~', $state);
	}
	if(count($data))
	{
		foreach($data as $val)
		{
		
			$_GET[$val[0]] = $val[1];
			$_REQUEST[$val[0]] = $val[1];
			
		}
	}
}
unset($_REQUEST['code']);
ipsController::run();

exit();