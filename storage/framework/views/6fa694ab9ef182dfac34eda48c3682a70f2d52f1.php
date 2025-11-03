<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Mon Projet'); ?></title>

    
    <?php echo $__env->yieldPushContent('head'); ?>
</head>
<body>
<main>
    <?php echo $__env->yieldContent('content'); ?>
</main>


<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/layouts/base.blade.php ENDPATH**/ ?>