<?php

namespace App\Services\DTO;


use Illuminate\Http\Request;

class UpdateInterventionDTO
{
    public string $numInt;
    public string $auteur;           // code_sal_auteur
    public ?string $reaSal = null;
    public ?string $date = null;     // Y-m-d
    public ?string $heure = null;    // H:i
    public bool $urgent = false;
    public string $commentaire = '';
    public string $contactReel = '';
    public string $objetTrait = '';
    public array $traitement = [];
    public array $affectation = [];
    public ?string $cp = null;
    public ?string $ville = null;
    public ?string $marque = null;
    public string $actionType = '';  // '', 'call', 'validate_rdv'


    public function __construct(
        string  $numInt,
        string  $auteur,
        ?string $reaSal,
        ?string $date,
        ?string $heure,
        bool    $urgent,
        string  $commentaire,
        string  $contactReel,
        string  $objetTrait,
        array   $traitement,
        array   $affectation,
        ?string $cp,
        ?string $ville,
        ?string $marque,
        string  $actionType
    ) {
        $this->numInt      = $numInt;
        $this->auteur      = $auteur;
        $this->reaSal      = $reaSal;
        $this->date        = $date;
        $this->heure       = $heure;
        $this->urgent      = $urgent;
        $this->commentaire = $commentaire;
        $this->contactReel = $contactReel;
        $this->objetTrait  = $objetTrait;
        $this->traitement  = $traitement;
        $this->affectation = $affectation;
        $this->cp          = $cp;
        $this->ville       = $ville;
        $this->marque      = $marque;
        $this->actionType  = $actionType;
    }

    public static function fromRequest(Request $request, string $numInt): self
    {
        return new self(
            trim($numInt),
            (string) $request->input('code_sal_auteur'),
            $request->input('rea_sal'),
            $request->input('date_rdv'),
            $request->input('heure_rdv'),
            $request->boolean('urgent'),
            (string) $request->input('commentaire', ''),
            (string) $request->input('contact_reel', ''),
            (string) $request->input('objet_trait', ''),
            (array)  $request->input('traitement', []),
            (array)  $request->input('affectation', []),
            $request->input('code_postal'),
            $request->input('ville'),
            $request->input('marque'),
            (string) $request->input('action_type', '')
        );
    }

}
