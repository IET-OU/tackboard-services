<?php

require_once dirname(__FILE__) . '/AbstractTackboardAction.php';

class ActionUser extends AbstractTackboardAction {

	function doGET(){
		header('Content-type: application/json; encoding=utf-8');
		$this->sendNoCache();
		$user = $this->getUserId();
		$logoff = (isset($_SERVER['HTTP_SAMS_LOGOFF_URL'])) ? $_SERVER['HTTP_SAMS_LOGOFF_URL'] : '';
		if($user!='anonym'){
			$oucu = $user;
			$uri = 'http://data.open.ac.uk/person/' . md5('ext-' . $user);
			
			$cookie = isset($_SERVER['HTTP_COOKIE']) or '';
			$matches = '';
			preg_match('/.*?HS7BDF=([^;]+)/', $cookie, $matches);
			$name = isset($matches[1]) ? $matches[1] : $user;
			$label = $name;
		}else{
			$label = $user;
			$name = $user;
			$uri = '';
		}

		$boards = $this->listBoards();
		$data = array('user' => $user, 'boards' => $boards, 'name' => $name, 'label' => $label, 'uri' => $uri, 'oucu' => $user, 'email' => "$user@openmail.open.ac.uk", 'logoffurl' => $logoff);
		$json = json_encode($data);

		print $json;
	}

}

