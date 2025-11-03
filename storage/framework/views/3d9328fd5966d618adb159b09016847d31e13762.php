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
            value="<?php echo e(old('commentaire')); ?>"
            class="<?php echo e($errors->has('commentaire') ? 'is-invalid' : ''); ?>"
            aria-invalid="<?php echo e($errors->has('commentaire') ? 'true' : 'false'); ?>"
        >
    </div>
</div>
<?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/interventions/partials/commentaire.blade.php ENDPATH**/ ?>