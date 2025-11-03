<script>
    window.APP = {
        serverNow: "<?php echo e($serverNow); ?>",
        sessionId: "<?php echo e(session('id')); ?>",
        apiPlanningRoute: "<?php echo e(route('api.planning.tech', ['codeTech' => '__X__'])); ?>",
        TECHS: <?php echo json_encode($techniciens->pluck('CodeSal')->values(), 15, 512) ?>,
        NAMES: <?php echo json_encode($techniciens->mapWithKeys(fn($t)=>[$t->CodeSal=>$t->NomSal]), 15, 512) ?>,
        techs: <?php echo json_encode($techniciens->pluck('CodeSal')->values(), 15, 512) ?>,
        names: <?php echo json_encode($techniciens->mapWithKeys(fn($t)=>[$t->CodeSal=>$t->NomSal]), 15, 512) ?>,
    };
    window.APP_SESSION_ID = "<?php echo e(session('id')); ?>";
</script>
<?php
    $v = filemtime(public_path('js/interventions_edit/main.js'));
?>
<script type="module" src="<?php echo e(asset('js/interventions_edit/main.js')); ?>?v=<?php echo e($v); ?>"></script>
<?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/interventions/partials/scripts.blade.php ENDPATH**/ ?>