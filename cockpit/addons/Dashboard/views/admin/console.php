<?php

/**
 * Console : les commandes de Cockpit (Tower), dans le navigateur.
 *
 * @var bool $isAvailable le serveur permet d'exécuter les commandes (Cockpit)
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="dashboard-tableau">
                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Console</h1>
                    <p class="tdb-accueil__phrase">Les commandes de Cockpit, comme en ligne de commande sur le serveur. Réservé à qui sait ce qu’il fait.</p>
                </section>

                <?php if (!$isAvailable) : ?>
                    <div class="mt-vide">
                        <icon>terminal</icon>
                        <p>Ce serveur ne permet pas d’exécuter des commandes depuis le navigateur (proc_open ou PHP en ligne de commande indisponible).</p>
                    </div>
                <?php else : ?>
                    <div class="console__grille">
                        <section class="tdb-carte console__terminal">
                            <h2><icon>terminal</icon>Terminal</h2>
                            <system-terminal height="460"></system-terminal>
                        </section>
                        <section class="tdb-carte">
                            <h2><icon>help</icon>Pour commencer</h2>
                            <ul class="console__aide">
                                <li><code>list</code> affiche toutes les commandes.</li>
                                <li><code>help</code> suivi d’une commande en explique les options.</li>
                                <li>Une commande s’exécute sur le serveur, avec les droits de l’administration.</li>
                                <li>Préférez les scripts du site (<code>bin/</code>) pour installer ou mettre à jour.</li>
                            </ul>
                        </section>
                    </div>
                <?php endif ?>
            </div>
        </template>

        <script type="module">
            export default {
                components: {
                    systemTerminal: 'system:assets/vue-components/system-terminal.js'
                }
            };
        </script>
    </vue-view>
</kiss-container>
