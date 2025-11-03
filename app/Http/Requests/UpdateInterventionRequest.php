<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInterventionRequest extends FormRequest
{
    public function authorize()
    {
        return true; // tu as déjà le middleware de session
    }

    public function rules()
    {
        return [
            'commentaire'   => ['nullable','string','max:250'],
            'contact_reel'  => ['nullable','string','max:250'],

            'rea_sal'       => ['required','string','max:5','exists:t_salarie,CodeSal'],
            'date_rdv'  => ['date_format:Y-m-d','required','after_or_equal:today'],
            'heure_rdv' => ['date_format:H:i','required'],

            'code_sal_auteur' => ['required','string','max:5'],

            // adapte les tailles à ton schéma (5 pour CP FR, 80 pour ville/marque par ex.)
            'code_postal'   => ['nullable','string','max:10','regex:/^[0-9A-Za-z\- ]{4,10}$/'],
            'ville'         => ['nullable','string','max:80'],
            'marque'        => ['nullable','string','max:80'],
            'objet_trait'   => ['nullable','string','max:120'],

            'traitement'    => ['array'],
            'traitement.*'  => ['in:0,1'],
            'affectation'   => ['array'],
            'affectation.*' => ['in:0,1'],

            'action_type'   => ['required','in:,appel,rdv_valide'],

            'urgent' => ['sometimes','boolean'],
            'not_regex:/[\'{}";<>]/', // interdit les ' et ;
        ];
    }

    public function messages()
    {
        return [
            'commentaire.string' => 'Le commentaire doit être une chaîne de caractères.',
            'commentaire.max'    => 'Le commentaire ne peut pas dépasser 250 caractères.',

            'contact_reel.string' => 'Le contact réel doit être une chaîne de caractères.',
            'contact_reel.max'    => 'Le contact réel ne peut pas dépasser 250 caractères.',

            'rea_sal.required' => 'Veuillez sélectionner un technicien.',
            'rea_sal.string'   => 'Le code technicien doit être une chaîne.',
            'rea_sal.max'      => 'Le code technicien ne peut pas dépasser 5 caractères.',
            'rea_sal.exists'   => 'Le technicien sélectionné est introuvable.',

            'date_rdv.date_format'    => 'La date doit être au format AAAA-MM-JJ.',
            'date_rdv.required'  => 'La date est requise.',
            'date_rdv.after_or_equal' => 'La date doit être aujourd’hui ou plus tard.',

            'heure_rdv.date_format'   => 'L’heure doit être au format HH:MM.',
            'heure_rdv.required' => 'L’heure est requise.',

            'code_sal_auteur.required' => 'Votre identifiant est requis.',
            'code_sal_auteur.string'   => 'Votre identifiant doit être une chaîne.',
            'code_sal_auteur.max'      => 'Votre identifiant ne peut pas dépasser 5 caractères.',

            'code_postal.string' => 'Le code postal doit être une chaîne.',
            'code_postal.max'    => 'Le code postal ne peut pas dépasser 10 caractères.',
            'code_postal.regex'  => 'Le code postal contient des caractères non autorisés.',

            'ville.string' => 'La ville doit être une chaîne.',
            'ville.max'    => 'La ville ne peut pas dépasser 80 caractères.',

            'marque.string' => 'La marque doit être une chaîne.',
            'marque.max'    => 'La marque ne peut pas dépasser 80 caractères.',

            'objet_trait.string' => 'L’objet doit être une chaîne.',
            'objet_trait.max'    => 'L’objet ne peut pas dépasser 120 caractères.',

            'traitement.array'    => 'Le bloc “Traitement” est invalide.',
            'traitement.*.in'     => 'Chaque case “Traitement” doit valoir 0 ou 1.',

            'affectation.array'   => 'Le bloc “Affectation” est invalide.',
            'affectation.*.in'    => 'Chaque case “Affectation” doit valoir 0 ou 1.',

            'action_type.in'      => 'Le type d’action est invalide.',
        ];
    }
}

