<?php

namespace App\Api\V1\Helper;
use App\Ticket;
use App\Master;
use App\ColumnSetting;
use App\Board;
use App\Mastermodel;
use Dingo\Api\Exception\StoreResourceFailedException;

class Dummy {
	protected $dummy_id;
	protected $dummyColumn;
	protected $model;
	protected $boards;
	protected $prefixCodeLength;
	protected $prefixCode;
	protected $dummyType; //ticket or master or board or model
	protected $guidname; //guid_master or guid_ticket;

	public function __construct($dummy_id){
		$this->dummy_id = $dummy_id;

		if(strlen($this->dummy_id) < 24 ){
			$this->prefixCodeLength = env('previx_code', 5); //get env previx_code or 5 as default
			$this->prefixCode = substr($this->dummy_id, 0, $this->prefixCodeLength );
			$this->initModel();
			// it could be model too;
			// what should we do here ??
		}else{
			// defaultnya itu ini; board id langsung;
			$this->setDummyType('board');
		}
	}

	public function __toString(){
		return json_encode([
			'dummy_id' => $this->dummy_id,
			'model' => $this->model,
			'boards' => $this->boards,
			'prefixCode' => $this->prefixCode,
			'prefixCodeLength' => $this->prefixCodeLength,
		]);
	}

	public function setDummyType($dummyType){
		$this->dummyType = $dummyType;
	}

	public function getDummyType(){
		return $this->dummyType;
	}

	public function getUniqueColumn(){
		$uniqueColumn = ($this->getDummyType() == 'master') ? 'serial_no':'guid_master' ;
		return $uniqueColumn;
	}

	public function setGuidName($name){
		$this->guidname = $name;
	}

	public function getGuidName(){
		return $this->guidname;
	}

	private function initDummyColumn(){
		$this->dummyColumn = ( $this->getDummyType() == 'master' ) ? 'ticket_no_master' : 'ticket_no';
	}

	private function initModel(){
		$setting = ColumnSetting::where('code_prefix', $this->prefixCode )->first();
		
		if(is_null($setting)){
			// check if it modelname;
			$mastermodel = Mastermodel::where('name', 'like', $this->dummy_id )->first();
			if(!$mastermodel){
				throw new StoreResourceFailedException("ini tidak termasuk dummy master ataupun dummy tickets", [
					'dummy' => json_decode($this)
				]);
			}

			$this->setDummyType('model');
			
		}
		
		$this->setDummyType(str_singular($setting->table_name));
		$this->setGuidName('guid_'. str_singular($setting->table_name));
		$this->initDummyColumn();
		$className = 'App\\' . studly_case(str_singular($setting->table_name));
		if(class_exists($className)) {
			$this->model = new $className; //instantiate new model (Master or Ticket)
		}
	}

	public function toArray(){
		return json_decode( $this->__toString() );
	}

	public function getBoards(){
		if(strlen( $this->dummy_id) >= 24 ){
			return $this->dummy_id;
		}

		$guidName = $this->getGuidName();
		$guids = $this->model
			->select([
				$guidName
			])
			->where( $this->dummyColumn, $this->dummy_id)
			->where($this->getUniqueColumn(), null )
			->groupBy($guidName)->get();

		$arrayOfGuid = [];
		foreach ($guids as $key => $guid ) {
			$arrayOfGuid[] = $guid[$guidName];
		}

		$boards = Board::whereIn($guidName, $arrayOfGuid )
			->select('board_id')
			->groupBy('board_id')
			->get();

		$arrayBoards = [];
		foreach ($boards as $key => $board) {
			$arrayBoards[] = $board['board_id'];
		}
		return $arrayBoards;
	}
}