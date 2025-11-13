<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestNumIntRequest extends FormRequest
{
    /**
     * Réservé aux utilisateurs connectés avec agences en session.
     */
    public function authorize(): bool
    {
        return is_array(session('agences_autorisees')) && !empty(session('codeSal'));
    }

    /**
     * Validation des paramètres d’API pour la suggestion de NumInt.
     */
    public function rules(): array
    {
        return [
            'agence' => ['required','string','max:8', function ($attr, $val, $fail) {
                $allowed = (array) session('agences_autorisees', []);
                if (!in_array(strtoupper($val), $allowed, true)) {
                    $fail('Agence non autorisée.');
                }
            }],
            'date'   => ['nullable','date_format:Y-m-d','after_or_equal:today'],
        ];
    }

    /**
     * Uppercase / trim de l’agence passée en query string.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'agence' => strtoupper(trim((string) $this->query('agence'))) ?: null,
        ]);
    }
}
