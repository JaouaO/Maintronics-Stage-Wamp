<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware gère déjà l'accès
    }

    public function rules(): array
    {
        return [
            'id'   => ['required','string'],             // pour satisfaire ton middleware POST
            'note' => ['nullable','string','max:5000'],  // ≤ 5000 chars
        ];
    }

    public function messages(): array
    {
        return [
            'id.required'   => 'Session manquante.',
            'note.string'   => 'La note doit être du texte.',
            'note.max'      => 'La note interne dépasse 5000 caractères.',
        ];
    }

    /**
     * Uniformise la réponse JSON pour les appels AJAX (422)
     */
    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'ok'  => false,
                    'msg' => collect($validator->errors()->all())->implode(' '),
                    'err' => $validator->errors(),
                ], 422)
            );
        }
        parent::failedValidation($validator);
    }
}
