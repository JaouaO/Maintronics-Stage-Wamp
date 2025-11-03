<div class="box">
    <div class="head">
        <strong>Affectation du dossier</strong>
        <span id="srvDateTimeText" class="note">—</span>
    </div>

    <div class="body">
        <div class="affectationSticky">
            
            <div class="grid2">
                <label for="selAny">Affecter à</label>
                <div class="hstack-12">
                    <select
                        name="rea_sal"
                        id="selAny"
                        required
                        class="<?php echo e($errors->has('rea_sal') ? 'is-invalid' : ''); ?>"
                        aria-invalid="<?php echo e($errors->has('rea_sal') ? 'true' : 'false'); ?>">
                        <option value="">— Sélectionner —</option>
                        <?php if(($techniciens ?? collect())->count()): ?>
                            <optgroup label="Techniciens">
                                <?php $__currentLoopData = $techniciens; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($t->CodeSal); ?>" <?php echo e(old('rea_sal') == $t->CodeSal ? 'selected' : ''); ?>>
                                        <?php echo e($t->NomSal); ?> (<?php echo e($t->CodeSal); ?>)
                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if(($salaries ?? collect())->count()): ?>
                            <optgroup label="Salariés">
                                <?php $__currentLoopData = $salaries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($s->CodeSal); ?>" <?php echo e(old('rea_sal') == $s->CodeSal ? 'selected' : ''); ?>>
                                        <?php echo e($s->NomSal); ?> (<?php echo e($s->CodeSal); ?>)
                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>

                    
                    <label class="urgent-toggle" for="urgent">
                        <input type="hidden" name="urgent" value="0">
                        <input type="checkbox" id="urgent" name="urgent" value="1" <?php echo e(old('urgent') == '1' ? 'checked' : ''); ?>>
                        <span>Urgent</span>
                    </label>
                </div>
            </div>

            
            <div class="gridRow gridRow--dt">
                <label for="dtPrev">Date</label>
                <input type="date" id="dtPrev" name="date_rdv" required
                       value="<?php echo e(old('date_rdv')); ?>"
                       class="<?php echo e($errors->has('date_rdv') ? 'is-invalid' : ''); ?>"
                       aria-invalid="<?php echo e($errors->has('date_rdv') ? 'true' : 'false'); ?>">
                <label for="tmPrev">Heure</label>
                <input type="time" id="tmPrev" name="heure_rdv" required
                       value="<?php echo e(old('heure_rdv')); ?>"
                       class="<?php echo e($errors->has('heure_rdv') ? 'is-invalid' : ''); ?>"
                       aria-invalid="<?php echo e($errors->has('heure_rdv') ? 'true' : 'false'); ?>">
            </div>

            
            <div class="table mt8 <?php echo e($errors->has('affectation') || $errors->has('affectation.*') ? 'is-invalid-block' : ''); ?>">
                <table>
                    <thead>
                    <tr>
                        <th>Étapes de planification</th>
                        <th class="w-66">Statut</th>
                        <th>Étapes de planification</th>
                        <th class="w-66">Statut</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $pairs = array_chunk(($affectationItems ?? []), 2); ?>
                    <?php $__empty_1 = true; $__currentLoopData = $pairs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pair): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($pair[0]['label'] ?? ''); ?></td>
                            <td class="status">
                                <?php if(isset($pair[0])): ?>
                                    <input type="hidden" name="affectation[<?php echo e($pair[0]['code']); ?>]" value="0">
                                    <input type="checkbox" name="affectation[<?php echo e($pair[0]['code']); ?>]" value="1"
                                        <?php echo e(old("affectation.{$pair[0]['code']}") === '1' ? 'checked' : ''); ?>>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($pair[1]['label'] ?? ''); ?></td>
                            <td class="status">
                                <?php if(isset($pair[1])): ?>
                                    <input type="hidden" name="affectation[<?php echo e($pair[1]['code']); ?>]" value="0">
                                    <input type="checkbox" name="affectation[<?php echo e($pair[1]['code']); ?>]" value="1"
                                        <?php echo e(old("affectation.{$pair[1]['code']}") === '1' ? 'checked' : ''); ?>>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="4" class="note">Aucun item d’affectation</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            
            <div class="flex-end-bar">
                <button id="btnPlanifierAppel" class="btn btn-plan-call btn-sm" type="button">Planifier un nouvel appel</button>
                <button id="btnPlanifierRdv" class="btn btn-plan-rdv btn-sm" type="button">Planifier un rendez-vous</button>
                <button id="btnValider" class="btn btn-validate" type="button">Valider le prochain rendez-vous</button>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/interventions/partials/affectation.blade.php ENDPATH**/ ?>