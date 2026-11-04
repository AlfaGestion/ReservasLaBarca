# Pagos de participantes en partidos abiertos

## Implementación actual

Se agregaron tres modalidades: el organizador paga la seña; cada jugador paga su parte mediante una operación independiente de Mercado Pago; o todos pagan en la cancha con registro administrativo. La seña se acredita al organizador y nunca se modifica la reserva original. Los pagos independientes se confirman por consulta verificada del proveedor, no por la redirección.

Los cupos con pago online quedan reservados hasta su vencimiento configurable y pasan a confirmados sólo con aprobación. No hay reembolsos automáticos: cualquier devolución requiere gestión administrativa y auditoría.

El flujo actual de Mercado Pago representa el pago de la reserva original: puede ser una seña porcentual o el total, según la configuración existente. No existe en el modelo actual una entidad que relacione un pago independiente con un participante, ni reglas para devoluciones, conciliación, aprobación o vencimiento de ese pago.

Por ese motivo, esta ampliación implementa y publica únicamente la modalidad gratuita. La configuración administrativa conserva las opciones `allow_paid_join` y `organizer_choose_payment` para preparar la decisión, pero la aplicación no ofrece ni confirma una modalidad con pago y rechaza cualquier valor distinto de `free`.

Para habilitar pagos de participantes se debe definir antes un circuito independiente: importe por participante, preferencia o identificador de pago, confirmación exclusiva por webhook/API, estado de la solicitud, vencimiento, reembolso y relación con la reserva original. No se debe reutilizar la preferencia de la reserva, porque eso podría cobrar dos veces la misma seña o total.
# ConfiguraciÃ³n por servicio

La tabla `player_service_match_settings` reutiliza los servicios existentes y no crea un catÃ¡logo paralelo. La configuraciÃ³n global del portal sigue siendo el lÃ­mite superior: si el administrador desactiva `search_players_in_reservation`, no se muestra ni se publica la bÃºsqueda aunque un servicio individual la tenga marcada.

Cada servicio deportivo puede habilitarse para el portal y definir sus lÃ­mites de participantes, titulares, suplentes, inclusiÃ³n del organizador, ingreso automÃ¡tico, aprobaciÃ³n, salida y modificaciÃ³n de cupos. Los valores se validan al guardar y nuevamente al solicitar la publicaciÃ³n; no alcanza con modificar el formulario del navegador.

La configuraciÃ³n por servicio controla la posibilidad de publicar el partido, pero la reserva se crea siempre mediante el flujo existente. La publicaciÃ³n queda pendiente hasta que la reserva cumpla sus condiciones de confirmaciÃ³n y no se crean partidos para reservas anuladas o no confirmadas.
