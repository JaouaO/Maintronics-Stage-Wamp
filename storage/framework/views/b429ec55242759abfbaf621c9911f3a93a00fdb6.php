
<template id="tplHistory">
    <div class="hist-wrap">
        <h2 class="hist-title">Historique du dossier <?php echo e($interv->NumInt); ?></h2>
        <table class="hist-table table">
            <thead>
            <tr>
                <th class="w-150">Date</th>
                <th>Résumé</th>
                <th class="w-200">Rendez-vous / Appel</th>
                <th class="w-40"></th>
            </tr>
            </thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $suivis; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $suivi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $dateTxt = '—';
                    $raw = (string)($suivi->Texte ?? '');
                    $resumeLine = trim(preg_split('/\R/', $raw, 2)[0] ?? '');
                    $resumeClean = rtrim($resumeLine, " \t—–-:;.,");
                    $resumeShort = \Illuminate\Support\Str::limit($resumeClean !== '' ? $resumeClean : '—', 60, '…');
                    $objet = trim((string)($suivi->Titre ?? ''));
                    $auteur = trim((string)($suivi->CodeSalAuteur ?? ''));

                    $evtType = $suivi->evt_type ?? null;
                    $meta = [];
                    if (!empty($suivi->evt_meta)) {
                        try { $meta = (is_array($suivi->evt_meta) ? $suivi->evt_meta : json_decode($suivi->evt_meta, true)) ?: []; }
                        catch (\Throwable $e) { $meta = []; }
                    }

                    $dateIso = $meta['date'] ?? $meta['d'] ?? null;
                    $heure   = $meta['heure'] ?? $meta['h'] ?? null;
                    $tech    = $meta['tech']  ?? $meta['t'] ?? null;
                    $urgent  = (int)(
                        (isset($meta['urg']) && $meta['urg'])
                        || (isset($meta['urgent']) && $meta['urgent'])
                    );

                    $traitementList  = $meta['tl'] ?? [];
                    $affectationList = $meta['al'] ?? [];

                    if ($dateIso && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIso)) {
                        [$yy,$mm,$dd] = explode('-', $dateIso);
                        $dateTxt = "$dd/$mm/$yy";
                    }

                    $evtLabel = null; $evtClass = null;
                    switch ($evtType) {
                        case 'CALL_PLANNED':      $evtLabel='Appel planifié';              $evtClass='badge-call';  break;
                        case 'RDV_TEMP_INSERTED': $evtLabel='RDV temporaire (créé)';       $evtClass='badge-temp';  break;
                        case 'RDV_TEMP_UPDATED':  $evtLabel='RDV temporaire (mis à jour)'; $evtClass='badge-temp';  break;
                        case 'RDV_FIXED':         $evtLabel='RDV validé';                  $evtClass='badge-valid'; break;
                    }
                    if ($evtLabel) {
                        $parts = [];
                        if ($dateTxt && $heure)      $parts[] = $dateTxt.' à '.$heure;
                        elseif ($dateTxt)             $parts[] = $dateTxt;
                        elseif ($heure)               $parts[] = $heure;
                        if ($tech) $parts[] = $tech;
                        if (!empty($parts)) $evtLabel .= ' — ' . implode(' · ', $parts);
                    }
                ?>

                <tr class="row-main" data-row="main">
                    <td class="cell-p6"><?php echo e($dateTxt); ?></td>
                    <td class="cell-p6" title="<?php echo e($resumeClean); ?>">
                        <?php if($auteur !== ''): ?>
                            <strong><?php echo e($auteur); ?></strong> —
                        <?php endif; ?>
                        <?php if($objet  !== ''): ?>
                            <em><?php echo e($objet); ?></em> —
                        <?php endif; ?>
                        <?php echo e($resumeShort); ?>

                    </td>

                    <td class="cell-p6">
                        <?php if($evtLabel): ?>
                            <span class="badge <?php echo e($evtClass); ?>"><?php echo e($evtLabel); ?></span>
                            <?php if($urgent): ?>
                                <span class="badge badge-urgent" aria-label="Dossier urgent">URGENT</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="note">—</span>
                        <?php endif; ?>
                    </td>

                    <td class="cell-p6 cell-center">
                        <button class="hist-toggle" type="button" aria-expanded="false" title="Afficher le détail">+</button>
                    </td>
                </tr>

                <tr class="row-details" data-row="details">
                    <td colspan="3" class="hist-details-cell">
                        <?php if($suivi->CodeSalAuteur): ?>
                            <div><strong>Auteur :</strong> <?php echo e($suivi->CodeSalAuteur); ?></div>
                        <?php endif; ?>
                        <?php if($suivi->Titre): ?>
                            <div><strong>Titre :</strong> <em><?php echo e($suivi->Titre); ?></em></div>
                        <?php endif; ?>

                        <div class="prewrap mt8"><?php echo e((string)($suivi->Texte ?? '')); ?></div>

                        <?php if(!empty($traitementList) || !empty($affectationList)): ?>
                            <hr class="sep">
                            <div class="grid-2">
                                <div>
                                    <div class="section-title">Tâches effectuées</div>
                                    <?php if(!empty($traitementList)): ?>
                                        <div class="chips-wrap">
                                            <?php $__currentLoopData = $traitementList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <span class="chip chip-green"><?php echo e($lbl); ?></span>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="note">—</div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="section-title">Affectation</div>
                                    <?php if(!empty($affectationList)): ?>
                                        <div class="chips-wrap">
                                            <?php $__currentLoopData = $affectationList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <span class="chip chip-amber"><?php echo e($lbl); ?></span>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="note">—</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="3" class="note cell-p8-10">Aucun suivi</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</template>
<?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/interventions/partials/history_template.blade.php ENDPATH**/ ?>