
<?php $__env->startSection('title', 'Intervention'); ?>

<?php $__env->startSection('content'); ?>
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/intervention_edit.css')); ?>">

    <?php echo $__env->make('interventions.partials.errors', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <form id="interventionForm" method="POST"
          onsubmit="return confirm('Confirmer la validation?');"
          action="<?php echo e(route('interventions.update', $interv->NumInt)); ?>">
        <?php echo csrf_field(); ?>
        
        <input type="hidden" name="code_sal_auteur" value="<?php echo e($data->CodeSal ?? 'Utilisateur'); ?>">
        <input type="hidden" name="marque" value="<?php echo e($interv->Marque ?? ''); ?>">
        <input type="hidden" name="objet_trait" value="<?php echo e($objetTrait ?? ''); ?>">
        <input type="hidden" name="code_postal" value="<?php echo e($interv->CPLivCli ?? ''); ?>">
        <input type="hidden" name="ville" value="<?php echo e($interv->VilleLivCli ?? ''); ?>">
        <input type="hidden" name="action_type" id="actionType" value="">

        <div class="app">
            
            <section class="col center">
                <?php echo $__env->make('interventions.partials.traitement', [
                    'interv'           => $interv,
                    'data'             => $data,
                    'objetTrait'       => $objetTrait,
                    'contactReel'      => $contactReel,
                    'traitementItems'  => $traitementItems ?? [],
                    'suivis'           => $suivis,
                ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                <?php echo $__env->make('interventions.partials.history_template', [
                    'interv' => $interv,
                    'suivis' => $suivis,
                ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                <?php echo $__env->make('interventions.partials.commentaire', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </section>

            
            <section class="col right">
                <?php echo $__env->make('interventions.partials.affectation', [
                    'techniciens'       => $techniciens,
                    'salaries'          => $salaries ?? collect(),
                    'affectationItems'  => $affectationItems ?? [],
                    'serverNow'         => $serverNow,
                ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                <?php echo $__env->make('interventions.partials.agenda', [
                    'techniciens' => $techniciens,
                ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </section>
        </div>
    </form>

    <?php echo $__env->make('interventions.partials.modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <?php echo $__env->make('interventions.partials.scripts', [
        'techniciens' => $techniciens,
        'serverNow'   => $serverNow,
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.base', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/interventions/edit.blade.php ENDPATH**/ ?>