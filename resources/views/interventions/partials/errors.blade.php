@php
    // En HTTP, $errors est injecté par le middleware.
    // En Tinker / rendu manuel, on crée un sac vide pour éviter les erreurs.
    $__bag = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

@if ($__bag->any())
    <div id="formErrors" class="alert alert--error box">
        <div class="body">
            <strong class="alert-title">Le formulaire contient des erreurs :</strong>
            <ul class="alert-list">
                @foreach ($__bag->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
