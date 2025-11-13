<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotBlankRequest extends FormRequest
{
    /**
     * Utilisé pour le formulaire de login : ne restreint pas plus que ça.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Chaque champ envoyé doit être :
     * - non vide
     * - string ≤ 255
     * - sans certains caractères dangereux.
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->all() as $field => $value) {
            $rules[$field] = [
                'required',
                'string',
                'max:255',
                'not_regex:/[\'{}";<>]/', // interdit les guillemets simples, accolades, ;, <, >
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required'  => 'Le champ est obligatoire.',
            'string'    => 'Le champ doit être une chaîne de caractères.',
            'max'       => 'Le champ ne peut pas dépasser :max caractères.',
            'not_regex' => 'Le champ contient des caractères non autorisés',
        ];
    }
}
