<div class="box mserv">
    <div class="head">
        <label for="commentaire"><strong>Commentaire</strong></label>
        <span class="note">infos utiles</span>
    </div>
    <div class="body">
        <input
            type="text"
            id="commentaire"
            name="commentaire"
            maxlength="249"
            value="{{ old('commentaire') }}"
            class="{{ $errors->has('commentaire') ? 'is-invalid' : '' }}"
            aria-invalid="{{ $errors->has('commentaire') ? 'true' : 'false' }}"
        >
    </div>
</div>
