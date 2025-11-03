<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use IntlDateFormatter;

class AuthService
{
    public function login(string $codeSal): array
    {
        $user = DB::table('t_salarie')
            ->select('codeSal', 'CodeAgSal')
            ->where('codeSal', $codeSal)
            ->first();

        if ($user) {
            return [
                'success' => true,
                'user' => [
                    'codeSal' => $user->codeSal,
                    'CodeAgSal' => $user->CodeAgSal,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'Code salarié inconnu',
        ];
    }
    public function logAccess($id, $ip, $codeSal, $codeAgSal)
    {
        // Agence à journaliser (toujours une STRING)
        $agToLog = (string) $codeAgSal;

        // Si profils "globaux", on tente une agence par défaut concrète
        if (in_array($codeAgSal, ['PLUS','ADMI','DOAG'], true)) {

            // 1) Préférence t_resp.Defaut='O' pour ce salarié
            $pref = DB::table('t_resp')
                ->where('CodeSal', $codeSal)
                ->where('Defaut', 'O')
                ->value('CodeAgSal'); // <-- une seule valeur (string)

            if (is_string($pref) && $pref !== '') {
                $agToLog = $pref;
            } else {
                // 2) Fallbacks par rôle
                if ($codeAgSal === 'PLUS') {
                    $fallback = DB::table('t_resp')
                        ->where('CodeSal', $codeSal)
                        ->orderBy('CodeAgSal')
                        ->value('CodeAgSal');
                    $agToLog = $fallback ?: 'PLUS';
                } elseif ($codeAgSal === 'DOAG') {
                    // DOAG : agences M* ou C* (ordre alpha)
                    $fallback = DB::table('agence')
                        ->where(function ($q) {
                            $q->where('Code_ag', 'like', 'M%')
                                ->orWhere('Code_ag', 'like', 'C%');
                        })
                        ->orderBy('Code_ag')
                        ->value('Code_ag');
                    $agToLog = $fallback ?: 'DOAG';
                } else { // ADMI
                    $fallback = DB::table('agence')
                        ->orderBy('Code_ag')
                        ->value('Code_ag');
                    $agToLog = $fallback ?: 'ADMI';
                }
            }
        }

        // Sécurité finale : cast en string court (au cas où)
        $agToLog = (string) $agToLog;

        DB::table('t_log_util')->insert([
            'id'         => $id,
            'IP'         => $ip,
            'Util'       => $codeSal,
            'Agence'     => $agToLog, // toujours une chaîne
            'DateAcces'  => Carbon::now('Europe/Paris')->format('Y-m-d'),
            'HeureAcces' => Carbon::now('Europe/Paris')->format('H:i'),
            'Demat'      => null,
        ]);
    }

    public function generateId(): string
    {
        return Str::random(40);
    }

    public function verifHoraires(string $codeSal)
    {
        $fmt = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Europe/Paris',
            IntlDateFormatter::GREGORIAN,
            'EEEE'
        );

        $jour = $fmt->format(Carbon::now('Europe/Paris'));
        $jour = ucfirst(substr($jour, 0, 2));

        $horaires = DB::table('t_horaire')->where('Code_Sal', $codeSal)->first();

        if (!$horaires) {
            return [
                'success' => false,
                'message' => 'Plage horaire non autorisée',
            ];
        }

        $now = Carbon::now()->format('H:i:s');
        $startAM = $horaires->{$jour . '1'} ?? null;
        $endAM = $horaires->{$jour . '2'} ?? null;
        $startPM = $horaires->{$jour . '3'} ?? null;
        $endPM = $horaires->{$jour . '4'} ?? null;

        if (($startAM && $endAM && $now > $startAM && $now < $endAM) ||
            ($startPM && $endPM && $now > $startPM && $now < $endPM)) {
            return [
                'success' => true
            ];
        }

        // Vérification des horaires exceptionnels
        $horairesException = DB::table('t_horaireexcept')
            ->where('Code_Sal', $codeSal)
            ->where('date1', date("Y-m-d"))
            ->first();

        if (!$horairesException) {
            return [
                'success' => false,
                'message' => 'Plage horaire non autorisée',
            ];
        }

        return ['success'=>($now > $horairesException->HoraireJour1 && $now < $horairesException->HoraireJour2) ||
            ($now > $horairesException->HoraireJour3 && $now < $horairesException->HoraireJour4),
            'message' => 'Plage horaire non autorisée',
        ];
    }
}
