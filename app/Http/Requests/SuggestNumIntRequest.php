<?php
// app/Http/Requests/SuggestNumIntRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestNumIntRequest extends FormRequest
{
    public function authorize(): bool
    {
        return is_array(session('agences_autorisees')) && !empty(session('codeSal'));
    }
    public function rules(): array
    {
        return [
            'agence' => ['required','string','max:8', function($a,$v,$f){
                $allowed = (array) session('agences_autorisees', []);
                if (!in_array(strtoupper($v), $allowed, true)) $f('Agence non autorisée.');
            }],
            'date'   => ['nullable','date_format:Y-m-d','after_or_equal:today'],
        ];
    }
    protected function prepareForValidation(): void
    {
        $this->merge(['agence' => strtoupper(trim((string)$this->query('agence'))) ?: null]);
    }
}
