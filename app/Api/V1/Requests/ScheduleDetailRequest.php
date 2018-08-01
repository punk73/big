<?php

namespace App\Api\V1\Requests;

use Config;
use Dingo\Api\Http\FormRequest;

class ScheduleDetailRequest extends FormRequest
{
    public function rules()
    {
        return [
        	'file' => 'required|file'
        ];
    }

    public function authorize()
    {
        return true;
    }
}
