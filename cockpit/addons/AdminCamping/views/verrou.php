<?php
$moi = $this->helper('auth')->getUser();
$cestMoi = $moi && ($meta['user']['_id'] ?? null) === $moi['_id'];
$peutDebloquer = $cestMoi || $this->helper('acl')->isAllowed('app/resources/unlock');
$depuis = max(1, (int) round((time() - (int) ($meta['time'] ?? time())) / 60));
$libere = max(1, 5 - $depuis);
?>
<kiss-container class="kiss-margin-small camping-page" size="small">

    <section class="verrou">
        <span class="verrou__icone"><icon><?= $cestMoi ? 'tab' : 'lock' ?></icon></span>
        <?php if ($cestMoi) : ?>
            <h1>Cette fiche est déjà ouverte</h1>
            <p>Vous l’avez ouverte dans un autre onglet ou sur un autre appareil il y a <?= $depuis ?> min. Pour éviter que deux versions s’écrasent, une seule fenêtre peut la modifier à la fois.</p>
        <?php else : ?>
            <h1>Quelqu’un modifie cette fiche</h1>
            <p><b><?= $this->escape($meta['user']['name'] ?? '') ?></b> (<?= $this->escape($meta['user']['email'] ?? '') ?>) y travaille en ce moment. Elle se libère d’elle-même dans <?= $libere ?> min au plus après sa dernière action.</p>
        <?php endif ?>

        <div class="verrou__actions">
            <a class="kiss-button" href="javascript:history.back()"><icon>arrow_back</icon>Revenir</a>
            <?php if ($peutDebloquer) : ?>
                <button type="button" class="kiss-button kiss-button-primary" id="verrou-reprendre"><icon>edit</icon><?= $cestMoi ? 'Reprendre ici' : 'Débloquer quand même' ?></button>
            <?php endif ?>
        </div>
        <?php if ($cestMoi) : ?>
            <p class="verrou__note">Pensez à enregistrer d’abord dans l’autre fenêtre si vous y avez fait des changements.</p>
        <?php endif ?>
    </section>

    <?php if ($peutDebloquer) : ?>
    <script type="module">
        document.getElementById('verrou-reprendre').addEventListener('click', () => {
            App.request('/admincamping/reprendre/<?= $this->escape($resourceId) ?>')
                .then(() => location.reload())
                .catch(() => App.ui.notify('La fiche n’a pas pu être reprise.', 'error'));
        });
    </script>
    <?php endif ?>

</kiss-container>
