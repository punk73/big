<?php

namespace App\Api\V1\Helper;
use App\Ticket;
use App\Master;
use App\ColumnSetting;
use Dingo\Api\Exception\StoreResourceFailedException;

class Dummy {
	protected $dummy_id;
	protected $model;
	protected $boards;
	protected $prefixCodeLength;
	protected $prefixCode;

	public function __construct($dummy_id){

		$this->dummy_id = $dummy_id;
		$this->prefixCodeLength = env('previx_code', 5); //get env previx_code or 5 as default
		$this->prefixCode = substr($this->dummy_id, 0, $this->prefixCodeLength );

		$this->initModel();
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

	private function initModel(){
		$setting = ColumnSetting::where('code_prefix', $this->prefixCode )->first();
		
		if(is_null($setting)){
			throw new StoreResourceFailedException("ini tidak termasuk dummy master ataupun dummy tickets", [
				'dummy' => json_decode($this)
			]);
		}
		
		$className = 'App\\' . studly_case(str_singular($setting->table_name));
		if(class_exists($className)) {
			$this->model = new $className; //instantiate new model (Master or Ticket)
		}
	}	

	public function toArray(){
		return json_decode( $this->__toString() );
	}
}