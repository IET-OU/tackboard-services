<?php

require_once dirname(__FILE__) . '/AbstractTackboardAction.php';

class ActionBoard extends AbstractTackboardAction {
	
	
	// Read data about a board
	function doGET(){
		$this->sendNoCache();
		$signature = $this->getParam('id', false);
		
		// if id param does not exists return a list of all boards of the user
		if($signature === false){
			// Read data from the database
			header("Content-type: application/json; charset=utf-8");
			$object = new stdClass;
			$object->boards = $this->listBoards();
			print json_encode($object);
		}else{
			// Read data from the database
			header("Content-type: application/json; charset=utf-8");
			$object = $this->loadBoard($signature);
			
			if($this->canEdit($signature)){
				$object->readonly = false;
			}else{
				$object->readonly = true;
			}
			print json_encode($object);
		}
	}
	
	// Updates a board or create a new board (if id=0)
	function doPOST(){
		$this->sendNoCache();
		$signature = $this->parameters['id'];
		$json = file_get_contents('php://input');
		$data = json_decode($json);
		
		
		// Write data in the database, then read
		$errors = array();
		if($signature == '0'){
			$signature = uniqid();
			$label = $data->label;
			$errors = $this->createBoard($signature, $label, $this->getUserId());
		}else{
			// do update
			$errors = $this->update($signature, $data);
		}
		
		if(count($errors)>0){
			error_internal_server_error(json_encode(array('errors'=>$errors), JSON_FORCE_OBJECT));
		}
		header("Content-type: application/json; charset=utf-8");
		$object = $this->loadBoard($signature);
		print json_encode($object, JSON_FORCE_OBJECT);
	}
	
	function doDELETE(){
		$this->sendNoCache();
		$signature = $this->assertNotEmpty('id');
		$errors = $this->delete($signature);
		if(count($errors) == 0){
			header("Content-type: application/json; charset=utf-8");
			print json_encode(new stdClass);
		}else{
			error_internal_server_error(json_encode(array('errors'=>$errors), JSON_FORCE_OBJECT));
		}
	}
	
	// Updates part of the data of a board
	function doPATCH(){
		$this->sendNoCache();
		$signature = $this->parameters['id'];
		$json = file_get_contents('php://input');
		$data = json_decode($json);
		$errors = $this->update($signature, $data);
		if(count($errors) == 0){
			header("Content-type: application/json; charset=utf-8");
			$object = $this->loadBoard($signature);
			print json_encode($object);
		}else{
			error_internal_server_error(json_encode(array('errors'=>$errors), JSON_FORCE_OBJECT));
		}
	}
}

