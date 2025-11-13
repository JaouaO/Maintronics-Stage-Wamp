<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterventionRequest extends FormRequest
{
    /**
     * Création d’interventions uniquement pour un salarié connecté.
     */
    public function authorize(): bool
    {
        return is_array(session('agences_autorisees')) && !empty(session('codeSal'));
    }

    /**
     * Règles pour le formulaire “Nouvelle intervention”.
     */
    public function rules(): array
    {
        return [
            // Agence obligatoire + dans la liste autorisée
            'Agence'        => ['required','string','max:8', function ($attr, $val, $fail) {
                $allowed = (array) session('agences_autorisees', []);
                if (!in_array($val, $allowed, true)) {
                    $fail('Agence non autorisée.');
                }
            }],

            // NumInt déjà généré côté front (cohérent avec l’agence)
            'NumInt'        => ['required','string','max:20','regex:/^[A-Z0-9_-]+-[0-9]+$/'],

            'Marque'        => ['nullable','string','max:80','not_regex:/[<>]/'],
            'VilleLivCli'   => ['nullable','string','max:80','not_regex:/[<>]/'],
            'CPLivCli'      => ['nullable','string','max:10','regex:/^[0-9A-Za-z\- ]{4,10}$/'],

            // RDV optionnel : si présent, date >= aujourd’hui
            'DateIntPrevu'  => ['nullable','date_format:Y-m-d','after_or_equal:today'],
            'HeureIntPrevu' => ['nullable','date_format:H:i'],

            'Commentaire'   => ['nullable','string','max:250','not_regex:/[<>]/'],
            'Urgent'        => ['required','in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'Agence.required'             => 'Veuillez sélectionner une agence.',
            'Agence.*'                    => 'Agence non autorisée.',
            'NumInt.regex'                => 'Format NumInt invalide (AGXX-12345).',
            'CPLivCli.regex'              => 'Le code postal contient des caractères non autorisés.',
            'DateIntPrevu.after_or_equal' => 'La date prévue doit être aujourd’hui ou plus tard.',
        ];
    }

    /**
     * Normalisation de base (uppercase agence/NumInt + nettoyage).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'Agence'      => $this->input('Agence')
                ? strtoupper(trim($this->input('Agence')))
                : null,
            'NumInt'      => $this->input('NumInt')
                ? strtoupper(trim($this->input('NumInt')))
                : null,
            'Marque'      => $this->input('Marque')
                ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $this->input('Marque')))
                : null,
            'VilleLivCli' => $this->input('VilleLivCli')
                ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $this->input('VilleLivCli')))
                : null,
            'CPLivCli'    => $this->input('CPLivCli')
                ? trim($this->input('CPLivCli'))
                : null,
            'Commentaire' => $this->input('Commentaire')
                ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $this->input('Commentaire')))
                : null,
            'Urgent'      => $this->has('Urgent') ? (string) $this->input('Urgent') : '0',
        ]);
    }

    /**
     * Vérifications complémentaires :
     * - Date/heure couplées
     * - NumInt doit commencer par l’agence choisie.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $d = $this->input('DateIntPrevu');
            $h = $this->input('HeureIntPrevu');

            if (($d && !$h) || (!$d && $h)) {
                $msg = 'Saisissez la date et l’heure ensemble, ou laissez les deux vides.';
                $v->errors()->add('DateIntPrevu', $msg);
                $v->errors()->add('HeureIntPrevu', $msg);
            }

            // NumInt doit respecter le préfixe d’agence
            $ag  = $this->input('Agence');
            $num = $this->input('NumInt');

            if ($ag && $num && strpos($num, $ag . '-') !== 0) {
                $v->errors()->add('NumInt', 'Le numéro doit commencer par l’agence sélectionnée.');
            }
        });
    }
}
