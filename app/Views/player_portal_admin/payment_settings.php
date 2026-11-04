<div class="container-fluid py-4 portal-admin">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><span class="text-uppercase small text-warning fw-bold">Administración</span><h1 class="mb-1">Pagos de partidos abiertos</h1><p class="text-muted mb-0">Modalidades y vencimiento de aportes independientes de la reserva.</p></div><a class="btn btn-outline-secondary" href="<?= base_url('admin/portal') ?>">Volver</a></div>
    <?php if (session('msg')): ?><div class="alert alert-<?= esc(session('msg.type')) ?>"><?= esc(session('msg.body')) ?></div><?php endif; ?><form method="post" action="<?= base_url('admin/portal/configuracion/pagos/guardar') ?>" class="card"><div class="card-body">
        <?= csrf_field() ?><h2 class="h5">Modalidades habilitadas</h2>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="payment_mode_organizer_deposit" value="1" <?= !empty($settings['payment_mode_organizer_deposit']) ? 'checked' : '' ?>> <span class="form-check-label">El organizador paga la seña</span></label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="payment_mode_split" value="1" <?= !empty($settings['payment_mode_split']) ? 'checked' : '' ?>> <span class="form-check-label">Cada jugador paga su parte mediante Mercado Pago</span></label>
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="payment_mode_venue" value="1" <?= !empty($settings['payment_mode_venue']) ? 'checked' : '' ?>> <span class="form-check-label">Todos pagan en la cancha (registro administrativo)</span></label>
        <label class="form-label">Horas para completar un pago online pendiente<input class="form-control" type="number" min="1" name="participant_payment_expiry_hours" value="<?= (int) ($settings['participant_payment_expiry_hours'] ?? 24) ?>"></label>
        <p class="small text-muted mt-3">La seña y los descuentos de la reserva se mantienen sin cambios. En el reparto, la seña ya abonada se acredita al organizador; los aportes se registran aparte y nunca modifican el pago original.</p>
        <button class="btn btn-primary">Guardar configuración</button>
    </div></form>
</div>
