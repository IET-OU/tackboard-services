<?php
class AbstractTackboardAction extends AbstractRest {
	
	protected function sendNoCache(){
		header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
		header('Pragma: no-cache'); // HTTP 1.0.
		header('Expires: 0');
	}
	
	function getUserId(){
		if(!isset($_SERVER['PHP_AUTH_USER'])){
			return 'anonym';
		}
		return $_SERVER['PHP_AUTH_USER'];
	}
	
	function isPermitted($method, $userid){
		// only registered users can do other then GET
		if($method == 'GET'){
			return true;
		}else{
			//return false;
			$q = 'SELECT * FROM OUCU WHERE OUCU = ?';
			$mysqli = $this->getConnection();
			$stmt = $mysqli->prepare($q);
			if($stmt == null){
				throw new Exception($mysqli == null ? 'Undefined connection' : $mysqli->error);
			}
			$stmt->bind_param('s', $this->getUserId());
			if (!$stmt->execute()) {
				$stmt->close();
				error_internal_server_error( "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
			}
			$exists = false;
			//$stmt->bind_result($exists);
			
			if($stmt->fetch()){
				$exists = true;
			}
			$stmt->close();
			echo $exists;
			return $exists;
		}
	}
	
	protected function canEdit($signature){
		$q = 'SELECT SIGNATURE FROM BOARD WHERE CREATEDBY = ? AND SIGNATURE = ? LIMIT 1';
		$mysqli = $this->getConnection();
		$stmt = $mysqli->prepare($q);
		if($stmt == null){
			throw new Exception($mysqli == null ? 'Undefined connection' : $mysqli->error);
		}
		$i = $this->getUserId();
		$stmt->bind_param('ss', $i, $signature);
		if (!$stmt->execute()) {
			$stmt->close();
			error_internal_server_error( "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
		}
		$exists = false;
		$stmt->bind_result($exists);
		$boards = array();
		if(!$stmt->fetch()){
			$exists = false;
		}
		$stmt->close();
		
		return $exists == $signature;
	}
	
	protected $_config = null;
	protected $_connection = null;
	protected function getConfig(){
		if($this->_config == null){
			$this->_config = parse_ini_file(dirname(__FILE__) . "/config.ini");
		}
		return $this->_config;
	}
	
	protected function closeConnection(){
		try{
			$threadId = $this->_connection->thread_id;
			$this->_connection->close();
			$this->_connection->kill($threadId);
			$this->_connection = null;
		}catch(Exception $e){
			throw $e;
		}
	}
	
	protected function createConnection(){
		if($this->_connection !== null){
			$this->closeConnection();
		}else{
			$config = $this->getConfig();
			$this->_connection = new mysqli($config['mysql.host'], $config['mysql.user'], $config['mysql.password'], $config['mysql.database']);
			if (mysqli_connect_errno()) {
				error_internal_server_error("Connect failed: " . mysqli_connect_error());
			}
		}
	}
	protected function getConnection(){
		if($this->_connection == null){
			$this->createConnection();
		}
		return $this->_connection;
	}

	protected function createBoard($signature, $label, $createdby){
		$q = 'INSERT INTO BOARD (SIGNATURE, LABEL, CREATEDBY, CREATED) VALUES (?, ?, ?,  NOW())';
		$mysqli = $this->getConnection();
		$stmt = $mysqli->prepare($q);
		if($stmt == null){
			throw new Exception($mysqli == null ? 'Undefined connection' : $mysqli->error);
		}
		$stmt->bind_param('sss', $signature, $label, $this->getUserId() );
		$errors = array();
		if(!$stmt->execute()){
			$errors['createBoard'] = "Error: " .  $stmt->errno . ") " . $stmt->error;
		}
		$stmt->close();
		return $errors;
	}
	

	protected function getIdFromSignature($signature){
		$q = 'SELECT ID FROM BOARD WHERE SIGNATURE = ? LIMIT 1';
		$mysqli = $this->getConnection();
		$stmt = $mysqli->prepare($q);
		if($stmt == null){
			throw new Exception($mysqli == null ? 'Undefined connection' : $mysqli->error);
		}
		$stmt->bind_param('s', $signature);
		if (!$stmt->execute()) {
			$stmt->close();
			error_internal_server_error( "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
		}

		$stmt->bind_result($id);
                $stmt->fetch();
                $stmt->close();
		return $id;
	}
	
	protected function delete($signature){

		if(!$this->canEdit($signature)){
			return array('delete' => "Execute failed: User not allowed");
		}
		$mysqli = $this->getConnection();
		$mysqli->autocommit(FALSE);
		$errors = array();
		$id = $this->getIdFromSignature($signature);
		
		$q = 'DELETE FROM BOARD WHERE ID = ?';
		$delete = $mysqli->prepare($q);
		$delete->bind_param( 'i', $id);
		
		if (!$delete->execute()) {
			$errors['delete'] =   "Execute failed: (" . $delete->errno . ") " . $delete->error ;
		}
		
		if(count($errors) > 0 ){
			$mysqli->rollback();
		}else{
			$mysqli->commit();
		}
		// close everything
		if(isset($delete)) 	$delete->close();
		
		$mysqli->autocommit(TRUE);
		
		return $errors;
	}
	
	protected function update($signature, $object){
		$mysqli = $this->getConnection();
		
		if(!$this->canEdit($signature)){
			error_forbidden('User cannot modify this item');	
		}
		
		// start update
		$mysqli->autocommit(FALSE);
		$errors = array();
		$id = $this->getIdFromSignature($signature);
	
		if(isset($object->label)){
			if($object->label == ''){
				$object->label = 'Unnamed board';
			}
			$q = 'UPDATE BOARD SET LABEL = ?, UPDATEDBY = ? WHERE ID = ?';
			$updatel = $mysqli->prepare($q);
			$updatel->bind_param( 'ssi', $object->label, $this->getUserId(), $id );
			if(!$updatel->execute()){
				$errors['updatel'] =  "Execute failed: (" . $updatel->errno . ") " . $updatel->error;
			}
		}else{
			$q = 'UPDATE BOARD SET UPDATEDBY = ? WHERE ID = ?';
			$updatecb = $mysqli->prepare($q);
			$updatecb->bind_param( 'si', $this->getUserId(), $id );
			if(!$updatecb->execute()){
				$errors['updatecb'] =  "Execute failed: (" . $updatecb->errno . ") " . $updatecb->error;
			}
		}
		
		if(isset($object->tacked) && is_array($object->tacked)){
			$tacked = $object->tacked;
			if(count($errors) == 0){
				$q = 'DELETE FROM TACKED WHERE BOARD = ?';
				$delete = $mysqli->prepare($q);
				$delete->bind_param( 'i', $id);
				$delete->execute();
				if (!$delete->execute()) {
					$errors['delete'] =   "Execute failed: (" . $delete->errno . ") " . $delete->error ;
				}
			}
			
			if(count($errors) == 0){
				$q = 'INSERT IGNORE INTO IRI (SIGNATURE, MD5) VALUES ( ?, ? )';
				$insert1 = $mysqli->prepare($q);
				foreach($tacked as $iri){
					$insert1->bind_param( 'ss', $iri, md5($iri) );
					if(!$insert1->execute()){
						$errors['insert1'] =  "Execute failed: (" . $update->errno . ") " . $update->error;
						break;
					}
				}
			}
			
			if(count($errors) == 0){
				$q = 'INSERT INTO TACKED (BOARD, IRI) VALUES ( ?, ( SELECT ID FROM IRI WHERE MD5 = ? ) )';
				$insert2 = $mysqli->prepare($q);
				foreach($tacked as $iri){
					$insert2->bind_param( 'is', $id, md5($iri) );
					if(!$insert2->execute()){
						$errors['insert2'] =  "Execute failed: (" . $insert2->errno . ") " . $insert2->error;
						break;
					}
				}
			}
		}
		// if errors
		if(count($errors) > 0 ){
			$mysqli->rollback();
		}else{
			$mysqli->commit();
		}
		// close everything
		if(isset($updatel)) $updatel->close();
		if(isset($updatecb)) $updatecb->close();
		if(isset($delete)) 	$delete->close();
		if(isset($insert1)) $insert1->close();
		if(isset($insert2)) $insert2->close();
		
		$mysqli->autocommit(TRUE);
		
		return $errors;
	}
	
	protected function listBoards(){
		$q = 'SELECT LABEL, SIGNATURE, CREATED FROM BOARD WHERE CREATEDBY = ? ORDER BY CREATED DESC';
		$mysqli = $this->getConnection();
		$stmt = $mysqli->prepare($q);
		$i = $this->getUserId();
		$stmt->bind_param('s', $i);
		if (!$stmt->execute()) {
			$stmt->close();
			error_internal_server_error( "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
		}
		
		$stmt->bind_result($label, $signature, $created);
		$boards = array();
		while($stmt->fetch()){
			$o = new stdClass;
			$o->label = $label;
			$o->signature = $signature;
			$o->created = $created;
			array_push($boards, $o);
		}
		$stmt->close();
		$object = new stdClass;
		$object->boards = $boards;
		return $boards;
	}
	
	protected function loadBoard($signature){

		$q = 'SELECT LABEL, ID, CREATEDBY FROM BOARD WHERE SIGNATURE = ? LIMIT 1';
		$mysqli = $this->getConnection();
		$stmt = $mysqli->prepare($q);
		$stmt->bind_param('s', $signature);
		if (!$stmt->execute()) {
			$stmt->close();
    		error_internal_server_error( "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
		}
		#$res = $stmt->get_result();
		$stmt->bind_result($label, $id, $createdBy);
		$exists = $stmt->fetch();
		$stmt->close();
		if(!$exists){
			error_not_found('Not found');
		}
		$object = new stdClass;
		$object->_id = $id;
		$object->id = $signature;
		$object->label = $label;
		$object->createdBy = $createdBy;
		
		// LOAD TACKED ITEMS
		$q = 'SELECT IRI.SIGNATURE FROM TACKED, IRI WHERE TACKED.BOARD = ? AND IRI.ID = TACKED.IRI';
		$stmt = $mysqli->prepare($q);
		$stmt->bind_param('i', $object->_id);
		if (!$stmt->execute()) {
			$stmt->close();
			error_internal_server_error( "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
		}
		$stmt->bind_result($iri);
		$tacked = array();
		while($stmt->fetch()){
			array_push($tacked, $iri);
		}
		$stmt->close();
		
		$object->tacked = $tacked;
		return $object;
	}
	
	public function doOPTIONS(){
		// do nothing but support
// 		header('Access-Control-Allow-Origin', '*', true);
// 		header('Access-Control-Allow-Methods', 'GET,PUT,POST,DELETE,PATCH,OPTIONS', true);
// 		header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept', true);
		return '';
	}
}

