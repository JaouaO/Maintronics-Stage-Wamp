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
    };
</script>

@php
    $v = filemtime(public_path('js/interventions_edit/main.js'));
@endphp
<script type="module" src="{{ asset('js/interventions_edit/main.js') }}?v={{ $v }}"></script>
