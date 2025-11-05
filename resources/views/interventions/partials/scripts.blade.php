<script>
    window.APP = {
        serverNow: "{{ $serverNow }}",
        sessionId: "{{ session('id') }}",
        apiPlanningRoute: "{{ route('api.planning.tech', ['codeTech' => '__X__']) }}",
        numInt: "{{ $interv->NumInt }}", // 👈 important
        // Pour le filtre côté client (sécurité supplémentaire côté UI)
        agendaAllowedCodes: @json(($agendaPeople ?? collect())->pluck('CodeSal')->values()),
        // Pour le sélecteur
        TECHS: @json(($agendaPeople ?? collect())->pluck('CodeSal')->values()),
        NAMES: @json(($agendaPeople ?? collect())->mapWithKeys(fn($p)=>[$p->CodeSal=>$p->NomSal])),
        // 🔵 Exposé depuis t_intervention
        CLIENT: {
            numInt:  @json($interv->NumInt),
            nom:     @json($interv->NomLivCli),
            tel:     @json($interv->TelLivCli),
            email:   @json($interv->EmailLivCli),
            adr:     @json($interv->AdLivCli),
            ville:   @json($interv->VilleLivCli),
            cp:      @json($interv->CPLivCli),
            typeapp: @json($interv->TypeApp),
            marque:  @json($interv->Marque),
        }
    };
</script>

@php
    $v = filemtime(public_path('js/interventions_edit/main.js'));
@endphp
<script type="module" src="{{ asset('js/interventions_edit/main.js') }}?v={{ $v }}"></script>
