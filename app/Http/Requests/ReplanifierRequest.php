<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ReplanifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // accès déjà filtré par middleware + controller
    }

    public function rules(): array
    {
        return [
            'rdv_at'    => ['required','date_format:Y-m-d H:i:s'],
            'tech_code' => ['nullable','string','min:2','max:10'],
            'comment'   => ['nullable','string','max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rdv_at.required'      => 'La date/heure est requise.',
            'rdv_at.date_format'   => 'Format de date/heure invalide.',
            'tech_code.min'        => 'Le code technicien est trop court.',
            'tech_code.max'        => 'Le code technicien est trop long.',
        ];
    }

    /**
     * Transforme le payload reçu (rdv_at, tech_code, comment)
     * en payload attendu par UpdateInterventionDTO::fromRequest().
     * - Vérifie l’existence du dossier
     * - Vérifie que le technicien est autorisé pour ce NumInt
     * - N’écrase pas le commentaire si vide côté front
     */
    public function toUpdatePayload(): array
    {
        $v = $this->validated();

        $numInt = (string) $this->route('numInt'); // récupère {numInt} de la route
        $rdvAt  = (string) ($v['rdv_at'] ?? '');
        $tech   = strtoupper(trim((string) ($v['tech_code'] ?? '')));
        $nowUser = (string) (session('codeSal') ?: 'system');

        // 1) Le dossier existe ?
        $exists = DB::table('t_intervention')->where('NumInt', $numInt)->exists();
        if (!$exists) {
            throw ValidationException::withMessages([
                'numInt' => 'Intervention introuvable. Rafraîchissez la page et réessayez.'
            ]);
        }

        // 2) Tech autorisé ?
        if ($tech !== '') {
            /** @var \App\Services\AccessInterventionService $access */
            $access = app(\App\Services\AccessInterventionService::class);
            $allowed = $access->listPeopleForNumInt($numInt)
                ->pluck('CodeSal')->map(fn($c)=>strtoupper(trim((string)$c)))->all();

            if (!in_array($tech, $allowed, true)) {
                throw ValidationException::withMessages([
                    'tech_code' => "Technicien non autorisé pour ce dossier. Rafraîchissez la page."
                ]);
            }
        }

        // 3) Split rdv_at -> date_rdv / heure_rdv
        [$d, $t] = explode(' ', $rdvAt); // formats garantis par rules()
        $date_rdv  = $d;                 // YYYY-MM-DD
        $heure_rdv = substr($t, 0, 5);   // HH:ii

        // 4) Commentaire : si vide, on reprend l’existant pour ne pas l’écraser
        $comment = trim((string) ($v['comment'] ?? ''));
        if ($comment === '') {
            $comment = (string) (DB::table('t_actions_etat')
                ->where('NumInt', $numInt)->value('commentaire') ?? '');
        }

        // 5) Construire le payload attendu par le DTO/service
        return [
            'code_sal_auteur' => $nowUser,
            'rea_sal'         => $tech ?: null,
            'date_rdv'        => $date_rdv,
            'heure_rdv'       => $heure_rdv . ':00',
            'urgent'          => false,              // inchangé ici
            'commentaire'     => $comment,
            'contact_reel'    => '',
            'objet_trait'     => '',
            'traitement'      => [],
            'affectation'     => [],
            'code_postal'     => null,
            'ville'           => null,
            'marque'          => null,
            'action_type'     => 'rdv_valide',       // ⚠️ clé manquante avant => causait l’erreur
        ];
    }
}
