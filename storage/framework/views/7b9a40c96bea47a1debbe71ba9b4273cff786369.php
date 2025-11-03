<div class="box agendaBox" id="agendaBox">
    <div class="head">
        <strong>Agenda technicien</strong>
        <span class="note">vue mensuelle (Tous par défaut)</span>
    </div>
    <div class="body">
        
        <div class="grid2">
            <label>Technicien</label>
            <select id="selModeTech">
                <option value="_ALL" selected>Tous les techniciens</option>
                <?php $__currentLoopData = $techniciens; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $technicien): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($technicien->CodeSal); ?>">
                        <?php echo e($technicien->NomSal); ?> (<?php echo e($technicien->CodeSal); ?>)
                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div id="calWrap">
            <div id="calHead">
                <button id="calPrev" class="btn" type="button">◀</button>
                <div id="calHeadMid">
                    <div id="calTitle"></div>
                    <button id="calToggle" class="btn" type="button" aria-expanded="true">▾ Mois</button>
                </div>
                <button id="calNext" class="btn" type="button">▶</button>
            </div>

            
            <div id="calGrid" class="cal-grid"></div>

            
            <div id="calList" class="cal-list is-hidden">
                <div id="calListHead">
                    <button id="dayPrev" class="btn" type="button" title="Jour précédent" aria-label="Jour précédent">◀</button>
                    <div id="calListTitle"></div>
                    <button id="dayNext" class="btn" type="button" title="Jour suivant" aria-label="Jour suivant">▶</button>
                </div>
                <div id="calListBody" class="table">
                    <table>
                        <thead>
                        <tr>
                            <th class="w-80">Heure</th>
                            <th class="w-80">Tech</th>
                            <th class="w-200">Contact</th>
                            <th>Label</th>
                            <th class="col-icon"></th>
                        </tr>
                        </thead>
                        <tbody id="calListRows"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- /calWrap -->
    </div>
</div>
<?php /**PATH C:\Users\chaou\Desktop\Stage\test programme pour wamp\Projet-Stage-Maintronic\src\resources\views/interventions/partials/agenda.blade.php ENDPATH**/ ?>