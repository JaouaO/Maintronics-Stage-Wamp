<div class="box">
    <div class="head">
        <strong>Traitement du dossier — <?php echo e($interv->NumInt); ?></strong>
        <span class="note"><?php echo e($data->NomSal ?? ($data->CodeSal ?? '—')); ?></span>
    </div>

    <div class="body">
        
        <div class="grid2">
            <label>Objet</label>
            <div class="ro"><?php echo e($objetTrait ?: '—'); ?></div>
        </div>

        
        <div class="grid2">
            <label for="contactReel">Contact réel</label>
            <input type="text" id="contactReel" name="contact_reel"
                   maxlength="255"
                   value="<?php echo e(old('contact_reel', $contactReel)); ?>"
                   class="<?php echo e($errors->has('contact_reel') ? 'is-invalid' : ''); ?>"
                   aria-invalid="<?php echo e($errors->has('contact_reel') ? 'true' : 'false'); ?>">
        </div>

        
        <button id="openHistory"
                class="btn btn-history btn-block"
                type="button"
                data-num-int="<?php echo e($interv->NumInt); ?>">
            Ouvrir l’historique
        </button>

        
        <div class="table mt6 <?php echo e($errors->has('traitement') || $errors->has('traitement.*') ? 'is-invalid-block' : ''); ?>">
            <table>
                <tbody>
                <?php $traits = $traitementItems ?? []; ?>
                <?php $__empty_1 = true; $__currentLoopData = $traits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trait): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($trait['label']); ?></td>
                        <td class="status">
                            <input type="hidden" name="traitement[<?php echo e($trait['code']); ?>]" value="0">
                            <input type="checkbox"
                                   name="traitement[<?php echo e($trait['code']); ?>]"
                                   value="1"
                                <?php echo e(old("traitement.{$trait['code']}") === '1' ? 'checked' : ''); ?>>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="2" class="note">Aucun item de traitement</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/interventions/partials/traitement.blade.php ENDPATH**/ ?>