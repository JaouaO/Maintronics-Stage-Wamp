<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Normalizer;

class ShowInterventionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return is_array(session('agences_autorisees')) && !empty(session('codeSal'));
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|in:10,25,50,100',
            'q'        => ['nullable','string','min:1','max:120','regex:/^[^\x00-\x1F\x7F<>]{1,}$/u'],
            'scope'    => 'nullable|string|in:urgent,me,both',
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.in' => 'Le nombre de lignes par page est invalide.',
            'q.min'       => 'Votre recherche doit contenir au moins 1 caractère.',
            'q.max'       => 'Votre recherche est trop longue (120 max).',
            'q.regex'     => 'Certains caractères ne sont pas autorisés.',
            'scope.in'    => 'Le filtre demandé est invalide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $q = $this->query('q');
        if (is_string($q)) {
            if (class_exists(Normalizer::class)) {
                $q = Normalizer::normalize($q, Normalizer::FORM_C);
            }
            $q = preg_replace('/[\x00-\x1F\x7F]/u', '', $q);
            $q = str_replace(['<','>'], '', $q);
            $q = preg_replace('/\s+/u', ' ', trim($q));
            $q = mb_substr($q, 0, 120);
        }

        $this->merge([
            'q'        => $q ?: null,
            'per_page' => (int) $this->query('per_page', 10),
            'scope'    => $this->query('scope') ?: null,
        ]);
    }
}
