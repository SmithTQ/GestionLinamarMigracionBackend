<?php
namespace App\Http\Requests\Api\V1\Forms;
use Illuminate\Foundation\Http\FormRequest;
class PublicFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['submission_key'=>['required','string','max:100','alpha_dash'],'product_sku'=>['required','string','max:60'],'sender_name'=>['required','string','max:250'],'sender_phone'=>['required','string','max:30'],'recipient_name'=>['required','string','max:250'],'recipient_phone'=>['required','string','max:30'],'district_code'=>['required','string','max:20'],'address'=>['required','string','max:255'],'latitude'=>['required','numeric','between:-90,90'],'longitude'=>['required','numeric','between:-180,180'],'location_accuracy'=>['nullable','numeric','min:0','max:100000'],'dedication'=>['nullable','string','max:5000'],'delivery_date'=>['nullable','date'],'delivery_time'=>['nullable','date_format:H:i']]; }
}
