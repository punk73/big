<?php

namespace App\Api\V1\Requests;

use Config;
use Dingo\Api\Http\FormRequest;

class ScheduleDetailProcessRequest extends FormRequest
{
    public function rules()
    {
        return [
        	'release_date' => 'required|date',
            'effective_date' => 'required|date',
            'end_effective_date' => 'required|date',
            
        ];
    }

    public function authorize()
    {
        return true;
    }
}
