<script>
    window.APP = {
        serverNow: "{{ $serverNow }}",
        sessionId: "{{ session('id') }}",
        apiPlanningRoute: "{{ route('api.planning.tech', ['codeTech' => '__X__']) }}",
        TECHS: @json($techniciens->pluck('CodeSal')->values()),
        NAMES: @json($techniciens->mapWithKeys(fn($t)=>[$t->CodeSal=>$t->NomSal])),
        techs: @json($techniciens->pluck('CodeSal')->values()),
        names: @json($techniciens->mapWithKeys(fn($t)=>[$t->CodeSal=>$t->NomSal])),
    };
    window.APP_SESSION_ID = "{{ session('id') }}";
</script>
@php
    $v = filemtime(public_path('js/interventions_edit/main.js'));
@endphp
<script type="module" src="{{ asset('js/interventions_edit/main.js') }}?v={{ $v }}"></script>
