<?php
    // En HTTP, $errors est injecté par le middleware.
    // En Tinker / rendu manuel, on crée un sac vide pour éviter les erreurs.
    $__bag = $errors ?? new \Illuminate\Support\ViewErrorBag;
?>

<?php if($__bag->any()): ?>
    <div id="formErrors" class="alert alert--error box">
        <div class="body">
            <strong class="alert-title">Le formulaire contient des erreurs :</strong>
            <ul class="alert-list">
                <?php $__currentLoopData = $__bag->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
<?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/interventions/partials/errors.blade.php ENDPATH**/ ?>