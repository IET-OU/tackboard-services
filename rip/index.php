<?php
defined('ACTIONS_HOME') or define('ACTIONS_HOME', dirname(__FILE__) . '/lib');
if(1){
	error_reporting(E_ALL);
	ini_set('display_errors',1);
}
function error_not_found($m) {
	header("HTTP/1.0 404 Not Found", true, 404);
	header("Content-type: text/plain; charset=utf-8"); 
	echo 'Not Found: ' .  $m; 
	exit(); 
}
function error_malformed($msg){
        header("HTTP/1.0 400 Bad request", true, 400);
        header("Content-type: text/plain; charset=utf-8");
        $echo = 'Bad request: ' . $msg;
	error_log($echo);
	echo $echo;
        exit();
}
function error_method_not_allowed($method){
	header("HTTP/1.0 405 Method Not Allowed", true, 405);
        header("Content-type: text/plain; charset=utf-8");
        $echo = 'Method not allowed: ' . $method;
	echo $echo;
        exit(); 
}
function error_internal_server_error($msg){
        header("HTTP/1.0 500 Internal Server Error", true, 500);
        header("Content-type: text/plain; charset=utf-8");
	echo 'Server error: ' . $msg;
//	echo $msg;
        exit();
}
function error_forbidden($message){
	header("HTTP/1.0 403 Forbidden", true, 403);
        header("Content-type: text/plain; charset=utf-8");
        echo 'Forbidden: ' .  $message;
        exit();
}


/* REST Interface */
interface Rest {
	function doGET();
	function doPOST();
	function doPUT();
	function doPATCH();
	function doDELETE();
	function doHEAD();
	function doOPTIONS();
}
interface Auth {
	function isPermitted($method, $userid);
	function getUserId();
}
interface Executor {
	function runHttpMethod($method);
}
abstract class AbstractRest implements Rest, Auth, Executor{

	function isPermitted($method, $userid){
		return true;
	}
	function getUserId(){
		return '';
	}
	// Security Helpers
	function allowFromIp($ip){
		if( $_SERVER['REMOTE_ADDR'] === $ip ){
			return true;
		}
		error_forbidden($ip);
	}

	// Input Validation Helpers
	function assertNotEmpty($paramName){
		$value = false;
		if(isset($this->parameters[$paramName])){
			$value = $this->parameters[$paramName];
		}

		if($value!==false && $value!=''){
			return $value;
		}
		error_malformed("'$paramName' is missing or empty");
	}
	
	function getParam($name, $default = null){
		if(isset($this->parameters[$name])){
			return $this->parameters[$name];
		}else{
			return $default;
		}
	}
	
	function getInt($name, $default = -1){
		$v = getParam($name);
		if($v === null){
			return $default;
		}else{
			return intval($v);
		}
	}
	
	function runHttpMethod($method){
		if($this->isPermitted($method, $this->getUserId())){
			$method = 'do'.strtoupper($method);
			return $this->$method();
		}else{
			error_forbidden("HTTP Method not allowed");
		}
	}
	// Default behavior
	function doHEAD(){  error_method_not_allowed('HEAD'); }
	function doGET(){ error_method_not_allowed('GET'); }
	function doPOST(){ error_method_not_allowed('POST'); }
	function doPUT(){ error_method_not_allowed('PUT'); }
	function doPATCH(){ error_method_not_allowed('PATCH'); }
	function doDELETE(){ error_method_not_allowed('DELETE'); }	
	function doOPTIONS(){ error_method_not_allowed('OPTIONS'); }	
}
/* default Action */
class Action extends AbstractRest{}

/* Please put all the rest implementations in ./lib/ActionXxxxxx.php */
function __autoload($class_name) {
    $f = ACTIONS_HOME .'/'. $class_name . '.php';
    if(file_exists($f)){
	require_once $f;
    }else{
	throw new ClassNotFoundException($class_name);
    }
}
class ClassNotFoundException extends Exception{}

// This function is for routing the HTTP request
// Requests are understood as follow:
//   /<action>[/<param>/<value>]*[?[<param>=<value>]*]
// This is routed to Rest implementation class named
//   Action<Action>
// 
function route(){
	$method = $_SERVER['REQUEST_METHOD'];
	$request = explode("/", substr(@$_SERVER['PATH_INFO'], 1));
	$pathParameters = array();
	$pathName = array_shift($request);
	for($x = 0; $x<count($request); $x+=2){	
		$v = ( isset( $request[$x+1] ) ) ? $request[$x+1] : NULL;
		$pathParameters[$request[$x]] = $v;
	}
	$params = array_merge($_GET, $_POST, $pathParameters);
	$actionName =  ucfirst($pathName);
	$action = 'Action' . $actionName;	
	try{
		$exists = class_exists($action);
	}catch(ClassNotFoundException $e){
		error_not_found($actionName);
	}
	if($exists && in_array( 'Rest', class_implements($action) ) ){
		$a = new $action;
		$m = 'do' . $method;
		if(method_exists($a, $m)){ 
			$a->parameters = $params;
			$a->headers = getallheaders();
			$a->runHttpMethod($method);
			// after executed, we must be sure nothing else happen. 
			exit(); 
		}else{
			 error_method_not_allowed($method);
		} 
	}else{
		error_not_found("Not found: " . $request[0]);
	}
}


function start(){
	try{
		route();
	}catch(Exception $e){
		error_internal_server_error($e->getMessage());
	}
}

start();

