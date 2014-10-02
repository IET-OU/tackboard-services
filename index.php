<?php

// XXX We check a valid user, we are at "private alpha" stage
// $active_users = array('ed4565','sab668','mda99','mc23488','gh4389','ajh59');
// if((!isset($_SERVER['PHP_AUTH_USER'])) || (!in_array($_SERVER['PHP_AUTH_USER'], $active_users))){
// 	header("HTTP/1.0 403 Forbidden", true, 403);
// 	header("Content-type: text/plain; charset=utf-8");
// 	echo 'Forbidden: user not authenticated or user not allowed';
// 	exit();
// }
//$_SERVER['PHP_AUTH_USER'] = 'ed4565';
date_default_timezone_set('Europe/London');
define('ACTIONS_HOME', dirname(__FILE__) . '/actions');
include_once dirname(__FILE__) . '/rip/index.php';

