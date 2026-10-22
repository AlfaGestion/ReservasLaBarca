# Portal de jugadores (`/app`)

## Regla permanente de idioma

Toda la interfaz de La Barca utiliza español de Argentina (`es-AR`): portal de jugadores, web pública, administración, JavaScript, validaciones, estados de reservas y pagos, y correos electrónicos. El locale principal está configurado en `Config\App` y los mensajes de validación propios se encuentran en `app/Language/es-AR/Validation.php`.

Los códigos internos, nombres de rutas, variables, estados de APIs y contratos de Mercado Pago se conservan para mantener la compatibilidad. Los errores técnicos se registran en los logs y se reemplazan por mensajes claros para el usuario.

La creación de reservas en `/app/reservas` utiliza un asistente de cinco pasos dentro de la misma página: servicio, fecha, instalación, horario y datos finales. La cotización y la creación continúan usando los endpoints y servicios existentes.

La migración `2026-10-14-090000_PlayerAccountLocality` agrega la localidad del jugador de forma aditiva. Cuando el jugador edita nombre, teléfono o localidad durante una reserva, esos datos se guardan en `player_accounts` únicamente después de crear correctamente la reserva y sus preferencias de pago. El correo y la contraseña no se modifican desde este flujo.

## Instalación

1. Cambiar a la rama `feature/la-barca-app`.
2. Instalar dependencias existentes con `composer install`.
3. Configurar la base de datos y el `baseURL` en el entorno habitual.
4. Ejecutar `php spark migrate` para crear `player_accounts`.
5. Verificar permisos de escritura en `writable/session` y `writable/logs`.

La migración es aditiva. No modifica `users`, `customers`, `bookings`, pagos ni configuraciones existentes.

## Rutas

- `GET /app`: inicio público.
- `GET|POST /app/registro`: alta pública de jugadores.
- `GET /app/verificar-email`: pantalla de espera o confirmación mediante token.
- `POST /app/verificar-email/reenviar`: reenvío limitado de verificación.
- `GET|POST /app/login`: autenticación de jugadores.
- `GET|POST /app/recuperar-clave`: solicitud y cambio mediante token de un solo uso.
- `GET|POST /app/perfil`: perfil privado.
- `GET /app/logout`: cierre de sesión del jugador.
- `GET /app/reservas`: creación y consulta de reservas.
- `GET /app/partidos`, `/app/partidos/crear`, `/app/mis-partidos`: partidos abiertos y solicitudes.

La sesión del portal utiliza `player_logged_in`, `player_id` y `player_session_version`; el login administrativo continúa utilizando `logueado` e `id_user`.

Las cuentas nuevas no pueden iniciar sesión hasta tener `email_verified_at`. La migración marca como verificadas las cuentas existentes para no bloquear usuarios creados antes de esta etapa.

## Correo y variables de entorno

El portal usa `PlayerEmailService`, que inicializa explícitamente SMTP y registra fallos técnicos sin exponer tokens ni contraseñas. Se pueden definir estas variables en `.env` o en el entorno del servidor:

```ini
player.email.enabled = true
player.email.fromEmail = no-reply@dominio.com
player.email.fromName = La Barca Centro Deportivo
player.email.protocol = smtp
player.email.smtpHost = smtp.proveedor.com
player.email.smtpUser = usuario SMTP
player.email.smtpPassword = secreto SMTP
player.email.smtpPort = 587
player.email.smtpCrypto = tls
player.email.smtpTimeout = 8
```

No subir el valor de `smtpPassword` al repositorio. Si el SMTP no está configurado, las cuentas quedan pendientes y el sistema registra el motivo; la respuesta al usuario no revela detalles internos.

## Turnstile y límites

Turnstile queda desactivado por defecto. El registro continúa disponible sin esta protección mientras no se configure explícitamente. Cuando estén disponibles las claves, puede habilitarse desde `.env`.

```ini
turnstile.siteKey = clave pública
turnstile.secretKey = clave privada
turnstile.required = true

## Etapa 2: reservas en `/app`

El portal de jugadores incorpora las siguientes rutas protegidas:

- `GET /app/reservas`: flujo de reserva y listado de reservas del jugador.
- `GET /app/mis-reservas`: acceso equivalente al listado.
- `GET /app/reservas/disponibilidad`: horarios calculados por el servidor.
- `POST /app/reservas/cotizar`: cotización server-side con ofertas existentes.
- `POST /app/reservas/crear`: reutiliza `MercadoPago::setPreference` y el checkout actual.
- `GET /app/reservas/detalle/{id}`: detalle autorizado de una reserva propia.

La disponibilidad utiliza `AvailabilityService`, `BookingSlotsModel`, cierres, horarios y configuraciones de `services`/`fields`. La cotización utiliza `CustomerOfferService`; el navegador no define el precio final.

Las reservas creadas con una sesión de jugador se vinculan mediante la tabla aditiva `player_booking_links`. Esta relación no modifica reservas históricas ni exige que las reservas tradicionales tengan jugador asociado. La asociación se realiza en el servidor durante la creación de la reserva; nunca se acepta un ID de jugador enviado por el navegador.

La migración `2026-10-13-120000_ServiceSportClassification` agrega `services.is_sport`. La acción futura de buscar jugadores se muestra únicamente cuando esa configuración explícita vale `1`; no se decide por el texto o nombre del servicio. La migración marca fútbol y pádel existentes como deportivos para la transición inicial.

El flujo de pago usa el endpoint y `MercadoPagoLibrary` existentes. No se agregó un webhook nuevo ni una lógica paralela de confirmación. Las pruebas de esta etapa no realizan cobros reales.

Si una instalación no tiene precio configurado, la cotización devuelve cero y la creación se rechaza, evitando generar reservas inválidas. Antes de habilitar reservas reales, cada servicio online debe tener precio y la configuración de Mercado Pago debe estar completa.
```

El servidor valida el token contra Cloudflare. Además, registro, reenvío y recuperación tienen límites temporales mediante cache. La respuesta de recuperación es siempre genérica.

## Etapa 3: partidos abiertos

El portal permite publicar partidos a partir de reservas deportivas reales del jugador y buscar participantes desde:

- `GET /app/partidos`: partidos futuros abiertos, con filtros por deporte y fecha.
- `GET /app/partidos/crear`: publicación de un partido desde una reserva deportiva propia.
- `GET /app/partidos/detalle/{id}`: detalle, incorporación y gestión de solicitudes.
- `GET /app/mis-partidos`: partidos organizados, participaciones y solicitudes pendientes.

Las tablas aditivas de la migración `2026-10-21-090000_PlayerMatches` son `player_matches`, `player_match_participants`, `player_match_requests` y `player_match_settings`. La reserva original continúa siendo la fuente de fecha, horario, cancha y estado; no se duplica ni se crea una reserva nueva.

La capacidad y los jugadores confirmados se controlan en el servidor. Las incorporaciones automáticas y las aceptaciones de solicitudes usan una actualización condicionada dentro de una transacción para evitar superar la capacidad ante operaciones simultáneas. Las solicitudes pendientes no ocupan lugares hasta ser aceptadas.

Solo se pueden publicar reservas propias, futuras, vigentes y clasificadas como deportivas mediante `services.is_sport`. Las anulaciones dejan de aparecer como partidos disponibles. Las modalidades de ingreso se habilitan desde `player_match_settings`; no se incluyen rankings, torneos ni valoraciones en esta etapa.

Las pruebas de incorporación, solicitudes simultáneas y cancelación de reservas requieren dos cuentas de prueba y deben ejecutarse sin cobros reales. La notificación visible se realiza dentro del portal; el envío de correos para estos eventos queda preparado para integrarse con `PlayerEmailService` sin bloquear operaciones exitosas.

## Verificación recomendada

Con PHP, Composer y la base configurados, ejecutar `composer test`, `php spark migrate` y probar manualmente `/`, `/auth/login`, `/app`, registro pendiente, verificación, reenvío, login, logout, recuperación y edición de perfil. También revisar `writable/logs` para diagnosticar SMTP sin mostrar esos detalles al usuario.
