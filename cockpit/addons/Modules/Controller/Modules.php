<?php

declare(strict_types=1);

namespace Modules\Controller;

use App\Controller\App;

/**
 * Réglages › Modules : la liste des addons et leur interrupteur.
 *
 * @package Modules
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */
class Modules extends App
{
    protected function before()
    {
        if (!$this->helper('acl')->isAllowed('modules/manage')) {
            return $this->stop(401);
        }
    }

    public function index()
    {
        $registre = $this->app['modules.registre'];

        return $this->render('modules:views/modules.php', [
            'modules' => $registre->modules(),
            // Sans la ligne de configuration, l'interrupteur n'aurait aucun effet : on le dit plutôt que de laisser croire.
            'branche' => $this->app->retrieve('modules.disabled') !== null,
            'fichier' => (string) ($this->app->retrieve('modules.fichier') ?: $this->app->path('#storage:').'/modules.json'),
        ]);
    }

    /** POST { nom, actif } : rend la liste à jour, ou l'erreur. */
    public function changer()
    {
        $this->helper('session')->close();
        $this->hasValidCsrfToken(true);

        $nom = (string) $this->param('nom', '');
        $actif = (bool) $this->param('actif', false);
        $user = $this->helper('auth')->getUser();

        $erreur = $this->app['modules.registre']->changer($nom, $actif, (string) ($user['name'] ?? $user['user'] ?? ''));

        if ($erreur !== null) {
            return $this->stop(['error' => $erreur], 412);
        }

        $this->app->trigger('modules.change', [$nom, $actif]);

        return ['modules' => $this->app['modules.registre']->modules()];
    }
}
