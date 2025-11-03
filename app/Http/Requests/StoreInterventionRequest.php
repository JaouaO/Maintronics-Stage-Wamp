<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Session déjà sécurisée par votre middleware
        return is_array(session('agences_autorisees')) && !empty(session('codeSal'));
    }

    public function rules(): array
    {
        return [
            'Agence'        => ['required','string','max:8', function($attr,$val,$fail){
                $allowed = (array) session('agences_autorisees', []);
                if (!in_array($val, $allowed, true)) $fail('Agence non autorisée.');
            }],
            'NumInt'        => ['required','string','max:20','regex:/^[A-Z0-9_-]+-[0-9]+$/'],
            'Marque'        => ['nullable','string','max:80','not_regex:/[<>]/'],
            'VilleLivCli'   => ['nullable','string','max:80','not_regex:/[<>]/'],
            'CPLivCli'      => ['nullable','string','max:10','regex:/^[0-9A-Za-z\- ]{4,10}$/'],

            // RDV optionnel : si l’un est présent, l’autre est requis
            'DateIntPrevu'  => ['nullable','date_format:Y-m-d','after_or_equal:today'],
            'HeureIntPrevu' => ['nullable','date_format:H:i'],

            'Commentaire'   => ['nullable','string','max:250','not_regex:/[<>]/'],
            'Urgent'        => ['required','in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'Agence.required' => 'Veuillez sélectionner une agence.',
            'Agence.*'        => 'Agence non autorisée.',
            'NumInt.regex'    => 'Format NumInt invalide (AGXX-12345).',
            'CPLivCli.regex'  => 'Le code postal contient des caractères non autorisés.',
            'DateIntPrevu.after_or_equal' => 'La date prévue doit être aujourd’hui ou plus tard.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'Agence'      => $this->input('Agence') ? strtoupper(trim($this->input('Agence'))) : null,
            'NumInt'      => $this->input('NumInt') ? strtoupper(trim($this->input('NumInt'))) : null,
            'Marque'      => $this->input('Marque') ? trim(preg_replace('/[\x00-\x1F\x7F]/u','',$this->input('Marque'))) : null,
            'VilleLivCli' => $this->input('VilleLivCli') ? trim(preg_replace('/[\x00-\x1F\x7F]/u','',$this->input('VilleLivCli'))) : null,
            'CPLivCli'    => $this->input('CPLivCli') ? trim($this->input('CPLivCli')) : null,
            'Commentaire' => $this->input('Commentaire') ? trim(preg_replace('/[\x00-\x1F\x7F]/u','',$this->input('Commentaire'))) : null,
            'Urgent'      => $this->has('Urgent') ? (string)$this->input('Urgent') : '0',
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $d = $this->input('DateIntPrevu');
            $h = $this->input('HeureIntPrevu');
            if (($d && !$h) || (!$d && $h)) {
                $v->errors()->add('DateIntPrevu', 'Saisissez la date et l’heure ensemble, ou laissez les deux vides.');
                $v->errors()->add('HeureIntPrevu', 'Saisissez la date et l’heure ensemble, ou laissez les deux vides.');
            }

            // Verrouille NumInt sur le préfixe Agence
            $ag = $this->input('Agence');
            $num= $this->input('NumInt');
            if ($ag && $num && strpos($num, $ag.'-') !== 0) {
                $v->errors()->add('NumInt', 'Le numéro doit commencer par l’agence sélectionnée.');
            }
        });
    }
}
