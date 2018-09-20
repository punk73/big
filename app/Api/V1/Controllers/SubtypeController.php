<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller; //parent contoller
use App\Subtype;
use Illuminate\Http\Request;
use App\Api\V1\Requests\SubtypeRequest;
use Dingo\Api\Exception\StoreResourceFailedException;

class SubtypeController extends Controller
{   
    protected $result = [
        'success' => true,
        'data'    => null,
    ];

    protected $allowedParameter = [
        'name',
        'model_id'
    ];
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request )
    {
        $this->result['data'] = $this->getSubtype()->get();
        return $this->result;

    }

    private function getSubtype(){
        return Subtype::select([
            'models.name as modelname',
            'subtypes.*'
        ])->leftJoin('models', 'models.id', '=', 'subtypes.model_id');    
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SubtypeRequest $request)
    {
        $subtype = new Subtype;
        $subtype->model_id = $request->model_id;
        $subtype->name = $request->name;
        $subtype->save();
        
        $this->result['data'] = $subtype;

        return $this->result;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Subtype  $subtype
     * @return \Illuminate\Http\Response
     */
    public function show(Subtype $subtype)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Subtype  $subtype
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id )
    {
        $subtype = Subtype::find($id);
        // kalau subtype ada baru edit
        if(!$subtype){
            throw new StoreResourceFailedException("Subtype dengan id {$id} tidak ditemukan", [
                'id' => $id
            ]);
        }
        
        $subtype->name = (isset($request->name))?$request->name : $subtype->name ;
        $subtype->save();
        $this->result['data'] = $subtype;

        return $this->result;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Subtype  $subtype
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $subtype = Subtype::find($id);
        // kalau subtype ada baru edit
        if(!$subtype){
            throw new StoreResourceFailedException("Subtype dengan id {$id} tidak ditemukan", [
                'id' => $id
            ]);
        }

        $subtype->delete();

        return $this->result;
    }
}
