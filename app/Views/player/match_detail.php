<?php
$title = 'Detalle del partido';
helper('url');
$isOrganizer = (int) $match['organizer_player_id'] === (int) $player['id'];
$profileUrl = static function (int $playerId) use ($match): string {
    return base_url('app/jugadores/' . $playerId . '?partido=' . (int) $match['id']);
};
?>
<section class="match-detail-page">
    <a class="back-link" href="<?= base_url('app/partidos') ?>">← Volver a partidos</a>
    <article class="match-detail-card profile-card">
        <span class="booking-service"><?= esc($match['service_name']) ?></span>
        <h1><?= esc($match['field_name']) ?></h1>
        <p class="match-date"><strong><?= esc(date('d/m/Y', strtotime($match['date']))) ?></strong> · <?= esc(substr($match['time_from'], 0, 5)) ?> a <?= esc(substr($match['time_until'], 0, 5)) ?></p>
        <p class="match-organizer"><i class="fa-solid fa-user" aria-hidden="true"></i> Organiza <?= esc(trim($match['organizer_first_name'] . ' ' . $match['organizer_last_name'])) ?></p>
        <div class="match-capacity detail-capacity"><strong><?= (int) $match['confirmed_players'] ?>/<?= (int) $match['max_players'] ?></strong><span>jugadores confirmados · <?= max(0, (int) $match['max_players'] - (int) $match['confirmed_players']) ?> lugares libres</span></div>
        <?php if (! empty($match['description'])): ?><p class="match-description"><?= esc($match['description']) ?></p><?php endif; ?>
        <p class="match-join-note"><?= $match['join_mode'] === 'auto' ? 'Podés incorporarte directamente si hay lugar.' : 'Tu solicitud será revisada por el organizador.' ?></p>
        <?php if (! $isOrganizer && $match['status'] === 'open'): ?>
            <?php if (! empty($request) && $request['status'] === 'pending'): ?><form method="post" action="<?= base_url('app/partidos/' . $match['id'] . '/solicitud/cancelar') ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><button class="button button-ghost button-full" type="submit">Cancelar solicitud</button></form>
            <?php elseif (! empty($request) && $request['status'] === 'accepted'): ?><div class="match-success">Ya estás incorporado a este partido.</div>
            <?php else: ?><form method="post" action="<?= base_url('app/partidos/' . $match['id'] . '/unirse') ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><button class="button button-primary button-full" type="submit"><?= $match['join_mode'] === 'auto' ? 'Sumarme al partido' : 'Solicitar unirme' ?> <span>→</span></button></form><?php endif; ?>
        <?php endif; ?>

        <section class="match-people-section">
            <div class="match-section-heading"><div><span class="eyebrow orange">EQUIPO</span><h2>Jugadores confirmados</h2></div><span class="people-count"><?= count($participants) ?>/<?= (int) $match['max_players'] ?></span></div>
            <div class="participant-list">
                <?php foreach ($participants as $participant): ?>
                    <?php $participantName = trim($participant['first_name'] . ' ' . $participant['last_name']); ?>
                    <div class="participant-row"><span class="avatar-mini"><?= esc(strtoupper(substr($participant['first_name'], 0, 1) . substr($participant['last_name'], 0, 1))) ?></span><span class="participant-info"><strong><?= esc($participantName) ?><?= $participant['role'] === 'organizer' ? ' · Organizador' : '' ?></strong><small>Jugador confirmado</small></span><a class="button button-ghost button-small participant-profile-link" href="<?= $profileUrl((int) $participant['player_id']) ?>">Ver perfil</a></div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($isOrganizer): ?>
            <section class="match-requests"><div class="match-section-heading"><div><span class="eyebrow orange">PARA REVISAR</span><h2>Solicitudes pendientes</h2></div><span class="people-count"><?= count($pendingRequests) ?></span></div>
                <?php if ($pendingRequests === []): ?><p class="booking-muted">No hay solicitudes pendientes.</p><?php else: ?>
                    <?php foreach ($pendingRequests as $pending): ?><div class="match-request-row"><span class="avatar-mini"><?= esc(strtoupper(substr($pending['first_name'], 0, 1) . substr($pending['last_name'], 0, 1))) ?></span><strong><?= esc(trim($pending['first_name'] . ' ' . $pending['last_name'])) ?></strong><a class="button button-ghost button-small" href="<?= $profileUrl((int) $pending['player_id']) ?>">Ver perfil</a><div class="match-request-actions"><form method="post" action="<?= base_url('app/partidos/solicitudes/' . $pending['id'] . '/accept') ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><button class="button button-primary button-small" type="submit">Aceptar</button></form><form method="post" action="<?= base_url('app/partidos/solicitudes/' . $pending['id'] . '/reject') ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><button class="button button-ghost button-small" type="submit">Rechazar</button></form></div></div><?php endforeach; ?>
                <?php endif; ?>
            </section>
            <div class="match-organizer-actions"><form method="post" action="<?= base_url('app/partidos/' . $match['id'] . ($match['status'] === 'open' ? '/close' : '/reopen')) ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><button class="button button-ghost" type="submit"><?= $match['status'] === 'open' ? 'Cerrar búsqueda' : 'Reabrir búsqueda' ?></button></form></div>
        <?php elseif (! empty($request) && $request['status'] === 'accepted'): ?><form method="post" action="<?= base_url('app/partidos/' . $match['id'] . '/retirarse') ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><button class="button button-ghost" type="submit">Retirarme del partido</button></form><?php endif; ?>
    </article>
</section>
