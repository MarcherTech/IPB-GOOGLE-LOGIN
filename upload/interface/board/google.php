<?php

define('IPB_THIS_SCRIPT', 'api');
define('IPB_LOAD_SQL', 'queries');
define('IPS_PUBLIC_SCRIPT', 'index.php');

require_once( '../../initdata.php' ); /* noLibHook */

//-----------------------------------------
// Main code
//-----------------------------------------

require_once( IPS_ROOT_PATH . 'sources/base/ipsRegistry.php' ); /* noLibHook */
require_once( IPS_ROOT_PATH . 'sources/base/ipsController.php' ); /* noLibHook */

$_GET['app'] = 'core';
$_REQUEST['app'] = 'core';
$_GET['module'] = 'global';
$_GET['section'] = 'login';
$_GET['google'] = $_GET['code'];
$_REQUEST['google'] = $_REQUEST['code'];
$_GET['state'] = $_GET['state'];
$_REQUEST['state'] = $_REQUEST['state'];
/* IPB fiddles with CODE to make it DO */
if (!$_GET['code'] AND $_GET['do']) {
	$_GET['google'] = $_GET['do'];
}

if (!$_REQUEST['code'] AND $_REQUEST['do']) {
	$_REQUEST['google'] = $_REQUEST['do'];
}

if ($_GET['state'] || $_REQUEST['state']) {
	$state = $_GET['state'] ? $_GET['state'] : $_REQUEST['state'];

	$data = json_decode(base64_decode($state), 1);

	if (isset($data['referer']) && strpos($data['referer'], 'app=core&amp;module=global') !== FALSE) {
		unset($data['referer']);
	}

	$_GET = array_merge($_GET, $data);
	$_REQUEST = array_merge($_REQUEST, $data);

}
//now reset.
$_REQUEST['do'] = 'process';
$_GET['do'] = 'process';
$_GET['use_google'] = 1;

ipsController::run();

exit();
