<?php

namespace App\Services\DTO;


use Illuminate\Http\Request;

class RdvTemporaireDTO
{
    public string $numInt;
    public string $auteur;           // code_sal_auteur
    public ?string $reaSal = null;
    public ?string $date = null;     // Y-m-d
    public ?string $heure = null;    // H:i
    public string $commentaire = '';
    public string $contactReel = '';
    public ?string $cp = null;
    public ?string $ville = null;
    public ?string $marque = null;

    public function __construct(
        string  $numInt,
        string  $auteur,
        ?string $reaSal,
        ?string $date,
        ?string $heure,
        string  $commentaire,
        string  $contactReel,
        ?string $cp,
        ?string $ville,
        ?string $marque
    ) {
        $this->numInt      = $numInt;
        $this->auteur      = $auteur;
        $this->reaSal      = $reaSal;
        $this->date        = $date;
        $this->heure       = $heure;
        $this->commentaire = $commentaire;
        $this->contactReel = $contactReel;
        $this->cp          = $cp;
        $this->ville       = $ville;
        $this->marque      = $marque;
    }

    public static function fromRequest(Request $request, string $numInt, string $codeSalAuteur): self
    {
        return new self(
            trim($numInt),
            (string)$codeSalAuteur,
            $request->input('rea_sal'),
            $request->input('date_rdv'),
            $request->input('heure_rdv'),
            (string) $request->input('commentaire', ''),
            (string) $request->input('contact_reel', ''),
            $request->input('code_postal'),
            $request->input('ville'),
            $request->input('marque'),
        );
    }

}
