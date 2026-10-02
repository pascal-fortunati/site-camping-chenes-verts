<?php

/**
 * A photo for every account. Cockpit only draws initials on a colour picked
 * from the first letter: nobody can change it but by changing their name.
 *
 * « Mon avatar », in the account menu, opens a dialog: send a photo, or pick
 * one among the assets. The server always rebuilds it — a centred square,
 * 256 px, WebP — so what is stored is a plain image whatever was sent. It is
 * kept with the site's media, under avatars/, and its path in the account
 * (« avatar »). Everyone changes their own avatar only: no permission needed.
 *
 * Wherever Cockpit draws the initials of an account that has a photo, the
 * photo is shown instead (assets/avatar.js). Passerelle reads the same field
 * to show it on the public site.
 *
 * An addon rather than a patch, so updating Cockpit never undoes it.
 */

const AVATAR_SIZE = 256;
const AVATAR_MAX_BYTES = 8 * 1024 * 1024;

/** Public address of an avatar path stored on an account, or null. */
$avatarUrl = function (?string $path): ?string {
    if (!$path || !str_starts_with($path, '/avatars/')) {
        return null;
    }

    return rtrim((string) $this->fileStorage->getURL('uploads://'), '/').$path;
};

/** The request is a signed-in admin request with a valid CSRF token (sent by App.request). */
$avatarAllowed = function (): ?array {
    $user = $this->helper('auth')->getUser();
    $token = $this->request->server['HTTP_X_CSRF_TOKEN'] ?? null;

    return $user && $this->helper('csrf')->isValid('app.csrf', $token) ? $user : null;
};

$avatarRemoveFile = function (?string $path): void {
    $uploads = rtrim((string) $this->path('#uploads:'), '/\\');
    if ($path && preg_match('#^/avatars/[0-9a-f]{24}-[0-9a-f]{8}\.webp$#', $path) && is_file($uploads.$path)) {
        unlink($uploads.$path);
    }
};

$this->bind('/avatar/liste', function () use ($avatarUrl) {

    $this->response->mime = 'json';

    if (!$this->helper('auth')->getUser()) {
        return ['comptes' => []];
    }

    $comptes = [];
    foreach ($this->dataStorage->find('system/users', ['fields' => ['name' => 1, 'user' => 1, 'avatar' => 1]])->toArray() as $u) {
        if ($url = $avatarUrl($u['avatar'] ?? null)) {
            $comptes[] = ['nom' => (string) ($u['name'] ?? $u['user'] ?? ''), 'url' => $url];
        }
    }

    return ['comptes' => $comptes];
});

$this->bind('/avatar/enregistrer', function () use ($avatarAllowed, $avatarRemoveFile, $avatarUrl) {

    $this->response->mime = 'json';

    if (!($user = $avatarAllowed())) {
        $this->response->status = 403;
        return ['erreur' => 'Action non autorisée'];
    }

    // The image: sent from the computer (data URL), or one of the assets.
    $bytes = null;
    $image = (string) $this->param('image', '');
    $asset = (string) $this->param('asset', '');

    if ($image !== '' && preg_match('#^data:image/(png|jpeg|webp|gif);base64,#', $image)) {
        $bytes = base64_decode(substr($image, strpos($image, ',') + 1), true);
    } elseif ($asset !== '' && ($doc = $this->dataStorage->findOne('assets', ['_id' => $asset])) && ($doc['type'] ?? '') === 'image') {
        $file = rtrim((string) $this->path('#uploads:'), '/\\').'/'.ltrim((string) $doc['path'], '/');
        $bytes = is_file($file) ? file_get_contents($file) : null;
    }

    if (!$bytes || strlen($bytes) > AVATAR_MAX_BYTES || !($source = @imagecreatefromstring($bytes))) {
        $this->response->status = 400;
        return ['erreur' => 'Cette image ne peut pas être lue. Essayez une photo JPEG, PNG ou WebP de moins de 8 Mo.'];
    }

    // Rebuilt from scratch: a centred square, resized, re-encoded.
    $w = imagesx($source);
    $h = imagesy($source);
    $side = min($w, $h);
    $avatar = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
    imagefill($avatar, 0, 0, imagecolorallocate($avatar, 255, 255, 255));
    imagecopyresampled($avatar, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), AVATAR_SIZE, AVATAR_SIZE, $side, $side);

    $folder = rtrim((string) $this->path('#uploads:'), '/\\').'/avatars';
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }
    $path = '/avatars/'.$user['_id'].'-'.bin2hex(random_bytes(4)).'.webp';
    imagewebp($avatar, $folder.substr($path, strlen('/avatars')), 85);

    $account = $this->dataStorage->findOne('system/users', ['_id' => $user['_id']]);
    $avatarRemoveFile($account['avatar'] ?? null);
    $account['avatar'] = $path;
    $this->dataStorage->save('system/users', $account);

    return ['url' => $avatarUrl($path)];
});

$this->bind('/avatar/retirer', function () use ($avatarAllowed, $avatarRemoveFile) {

    $this->response->mime = 'json';

    if (!($user = $avatarAllowed())) {
        $this->response->status = 403;
        return ['erreur' => 'Action non autorisée'];
    }

    $account = $this->dataStorage->findOne('system/users', ['_id' => $user['_id']]);
    $avatarRemoveFile($account['avatar'] ?? null);
    unset($account['avatar']);
    $this->dataStorage->remove('system/users', ['_id' => $account['_id']]);
    $this->dataStorage->insert('system/users', $account);

    return ['ok' => true];
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if (!$this->helper('auth')->getUser()) {
        return;
    }

    if ($context === 'app:header') {
        $assets[] = 'avatar:assets/avatar.css';
    }

    if ($context === 'app:footer') {
        $assets[] = ['src' => 'avatar:assets/avatar.js', 'type' => 'module', 'position' => 'footer'];
    }
});
