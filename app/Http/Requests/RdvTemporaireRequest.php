<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RdvTemporaireRequest extends FormRequest
{
    /**
     * Autorisation : contrôlée en amont (middleware + contrôleur).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles pour la création d’un RDV temporaire (modale “RDV temporaire”).
     */
    public function rules(): array
    {
        return [
            // Technicien obligatoire + existant
            'rea_sal'        => ['required','string','max:5','exists:t_salarie,CodeSal'],

            // Date + heure : couple cohérent, date non passée
            'date_rdv'       => ['nullable','date_format:Y-m-d','after_or_equal:today','required_with:heure_rdv'],
            'heure_rdv'      => ['nullable','date_format:H:i','required_with:date_rdv'],

            // Infos complémentaires (protégées contre < >)
            'commentaire'    => ['nullable','string','max:250','not_regex:/[<>]/'],
            'contact_reel'   => ['nullable','string','max:250','not_regex:/[<>]/'],
            'code_postal'    => ['nullable','string','max:10','regex:/^[0-9A-Za-z\- ]{4,10}$/'],
            'ville'          => ['nullable','string','max:80','not_regex:/[<>]/'],
            'marque'         => ['nullable','string','max:80','not_regex:/[<>]/'],

            // Flag envoyé par l’UI quand l’utilisateur confirme la purge du RDV validé
            'purge_validated'=> ['sometimes','boolean'],
        ];
    }

    /**
     * Normalisation / nettoyage des champs avant validation.
     */
    protected function prepareForValidation(): void
    {
        // trim + suppression des caractères de contrôle
        $clean = static function ($v) {
            return is_string($v)
                ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $v))
                : $v;
        };

        $this->merge([
            'rea_sal'        => $clean(strtoupper((string)$this->input('rea_sal'))),
            'date_rdv'       => $clean($this->input('date_rdv')),
            'heure_rdv'      => $clean($this->input('heure_rdv')),
            'commentaire'    => $clean($this->input('commentaire')),
            'contact_reel'   => $clean($this->input('contact_reel')),
            'code_postal'    => $clean($this->input('code_postal')),
            'ville'          => $clean($this->input('ville')),
            'marque'         => $clean($this->input('marque')),
            // Normalisation booléenne du flag
            'purge_validated'=> filter_var($this->input('purge_validated'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * Validation “croisée” : obliger à saisir date ET heure ensemble.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $d = $this->input('date_rdv');
            $h = $this->input('heure_rdv');

            if (($d && !$h) || (!$d && $h)) {
                $msg = 'Saisissez la date ET l’heure ensemble (ou laissez les deux vides).';
                $v->errors()->add('date_rdv',  $msg);
                $v->errors()->add('heure_rdv', $msg);
            }
        });
    }

    public function messages(): array
    {
        return [
            'rea_sal.required'      => 'Veuillez sélectionner un technicien.',
            'rea_sal.exists'        => 'Le technicien sélectionné est introuvable.',
            'date_rdv.after_or_equal' => 'La date doit être aujourd’hui ou plus tard.',
            'heure_rdv.date_format'   => 'L’heure doit être au format HH:MM.',
            'commentaire.max'       => 'Le commentaire ne peut pas dépasser 250 caractères.',
            'ville.max'             => 'La ville ne peut pas dépasser 80 caractères.',
            'marque.max'            => 'La marque ne peut pas dépasser 80 caractères.',
            'code_postal.regex'     => 'Le code postal contient des caractères non autorisés.',
        ];
    }
}
