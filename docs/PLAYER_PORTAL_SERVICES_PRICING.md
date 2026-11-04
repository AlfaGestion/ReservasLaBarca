# Servicios, canchas y precios del Portal de Jugadores

## Fuentes reutilizadas

El portal no crea un catálogo paralelo de canchas ni una fuente alternativa de precios.

- `services` mantiene los datos generales del servicio, horarios, duración, estado y disponibilidad.
- `fields` mantiene los recursos originales asociados al servicio. `service_id` y `service_type` determinan la relación.
- `fields.value` es el precio base de la cancha o recurso.
- `fields.ilumination_value` es el precio nocturno.
- `time.nocturnal_time` es el horario nocturno global utilizado por el administrador anterior.
- `FieldsModel`, `TimeModel` y `CustomerOfferService` conservan la lógica existente de disponibilidad y cotización.

La edición individual desde **Servicios / Precios** actualiza las mismas filas que utiliza el administrador legacy. No se crean canchas duplicadas ni se eliminan datos históricos.

## Servicios / Precios

La pestaña **General** administra solamente la información del servicio: nombre, tipo deportivo, color, ícono, estado, horarios, duración y disponibilidad en el portal.

La pestaña **Precios y recursos** muestra únicamente los recursos relacionados con el servicio seleccionado. Cada fila permite editar:

- nombre;
- medidas;
- tipo de piso;
- detalle o descripción;
- techada o descubierta;
- estado activo/inactivo;
- precio base;
- precio nocturno.

Para servicios no deportivos, los campos deportivos se mantienen disponibles solamente cuando el recurso original los utiliza; no se obliga a crear medidas o tipos de piso inexistentes.

La grilla anterior de precios por hora ya no forma parte de la interfaz. La tabla `service_price_windows` y sus datos se conservan de forma no destructiva por compatibilidad, pero no sobrescriben el precio de las canchas en la cotización vigente.

## Horario nocturno

El horario se configura una sola vez en **Configuración del portal**, mediante `time.nocturnal_time`. Por ejemplo, `19:00` hace que los turnos nocturnos definidos por la lógica legacy utilicen `fields.ilumination_value`.

No existe un horario nocturno independiente por cancha. Al cambiar el valor, las nuevas cotizaciones utilizan la nueva configuración; las reservas ya creadas conservan el importe guardado.

La regla de turnos que atraviesan el límite nocturno se mantiene igual que en el sistema anterior: `CustomerOfferService` considera nocturno un bloque cuando sus límites cumplen la condición legacy. No se introduce un prorrateo nuevo ni se recalculan reservas históricas.

## Reservas, descuentos y seña

La cotización continúa usando la cancha seleccionada, la fecha, el horario, la duración y el precio efectivo base/nocturno. Luego se aplican las reglas existentes de descuentos y porcentaje de seña.

El importe mostrado en el resumen y el importe usado para la preferencia de Mercado Pago provienen del mismo cálculo vigente. Esta modificación no cambia el circuito de Mercado Pago ni crea cobros adicionales.

## Compatibilidad

Los cambios del portal afectan las nuevas reservas del portal cuando corresponde. No deshabilitan recursos históricos ni modifican el catálogo o la disponibilidad del administrador legacy fuera de las estructuras compartidas que ya utilizaba.
