<?php
$player = $player ?? null;
$message = session('player_msg');
helper('url');
$playerNotifications = [];
$playerUnreadNotifications = 0;
$playerLastNotificationId = 0;
if ($player) {
    $notificationService = new \App\Libraries\PlayerNotificationService();
    $playerNotifications = $notificationService->forPlayer((int) $player['id']);
    $playerUnreadNotifications = $notificationService->unreadCount((int) $player['id']);
    foreach ($playerNotifications as $playerNotification) {
        $playerLastNotificationId = max($playerLastNotificationId, (int) $playerNotification['id']);
    }
}
?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal de jugadores de La Barca Centro Deportivo">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function () {
            const saved = localStorage.getItem('la-barca-theme');
            const theme = saved === 'light' || saved === 'dark' ? saved : (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            document.documentElement.dataset.theme = theme;
        }());
    </script>
    <title><?= esc($title ?? 'La Barca') ?> · La Barca</title>
    <link rel="icon" href="<?= base_url(PUBLIC_FOLDER . 'assets/images/favicon.ico') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/player/player.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/player/player-mobile.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/player/player-bookings.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/player/player-theme.css') ?>">
    <script src="<?= base_url('assets/player/player-theme.js') ?>" defer></script>
    <script src="<?= base_url('assets/player/player-carousel.js') ?>" defer></script>
    <script src="<?= base_url('assets/player/player-notifications.js') ?>" defer></script>
</head>
<body class="player-app<?= !empty($minimalLayout) ? ' player-minimal' : '' ?>" data-player-notifications="<?= $player ? 'true' : 'false' ?>" data-notification-last-id="<?= $playerLastNotificationId ?>">
<header class="player-header">
    <a class="brand" href="<?= base_url('app') ?>"><img src="<?= base_url(PUBLIC_FOLDER . 'assets/images/logo.png') ?>" alt="La Barca Centro Deportivo"></a>
    <nav class="desktop-nav" aria-label="Navegación principal">
        <a href="<?= base_url('app') ?>">Inicio</a>
        <a href="<?= base_url('app/partidos') ?>">Partidos</a>
        <?php if ($player): ?><a href="<?= base_url('app/perfil') ?>">Mi perfil</a><?php endif; ?>
    </nav>
    <div class="header-actions">
        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
            <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
            <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
        </button>
        <?php if ($player): ?>
            <div class="player-notification-menu" data-notification-menu>
                <button class="notification-toggle" type="button" data-notification-toggle aria-expanded="false" aria-controls="player-notifications" aria-label="Notificaciones">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    <?php if ($playerUnreadNotifications > 0): ?><span class="notification-badge"><?= $playerUnreadNotifications > 99 ? '99+' : $playerUnreadNotifications ?></span><?php endif; ?>
                </button>
                <div class="notification-dropdown" id="player-notifications" data-notification-dropdown hidden>
                    <div class="notification-dropdown-head"><strong>Notificaciones</strong><span><?= $playerUnreadNotifications ?> nuevas</span></div>
                    <?php if ($playerNotifications === []): ?>
                        <p class="notification-empty">No tenés notificaciones nuevas.</p>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach ($playerNotifications as $notification): ?>
                                <?php $notificationUrl = base_url('app/notificaciones/leer/' . (int) $notification['id']); ?>
                                <a class="notification-item<?= (int) $notification['is_read'] === 0 ? ' is-unread' : '' ?>" href="<?= $notificationUrl ?>">
                                    <span class="notification-item-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
                                    <span class="notification-item-content"><strong><?= esc($notification['title']) ?></strong><span><?= esc($notification['message']) ?></span><small><?= esc(date('d/m H:i', strtotime($notification['created_at']))) ?></small></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="notification-sound-setting"><button type="button" data-notification-sound-toggle><i class="fa-solid fa-volume-high" aria-hidden="true"></i> Activar sonidos</button></div>
                </div>
            </div>
            <a class="avatar-mini" href="<?= base_url('app/perfil') ?>"><?= esc(strtoupper(substr($player['first_name'], 0, 1) . substr($player['last_name'], 0, 1))) ?></a>
        <?php else: ?>
            <a class="header-login" href="<?= base_url('app/login') ?>">Ingresar</a>
            <a class="button button-small" href="<?= base_url('app/registro') ?>">Registrarme</a>
        <?php endif; ?>
    </div>
</header>
<main class="player-main">
    <?php if ($message && (empty($minimalLayout) || ($contentView ?? '') !== 'player/login')): ?><div class="flash flash-<?= esc($message['type']) ?>" role="status"><?= esc($message['body']) ?></div><?php endif; ?>
    <?= view($contentView, get_defined_vars()) ?>
</main>
<?php if ($player): ?><div class="player-notification-toast" data-notification-toast role="status" aria-live="polite" hidden></div><?php endif; ?>
<nav class="mobile-nav" aria-label="Navegación móvil">
    <a href="<?= base_url('app') ?>"><span>⌂</span>Inicio</a>
    <a href="<?= base_url('app/partidos') ?>"><span>◉</span>Partidos</a>
    <a href="<?= base_url('app/reservas') ?>"><span>▣</span>Reservas</a>
    <a href="<?= base_url($player ? 'app/perfil' : 'app/login') ?>"><span>●</span><?= $player ? 'Perfil' : 'Ingresar' ?></a>
</nav>
</body>
</html>
