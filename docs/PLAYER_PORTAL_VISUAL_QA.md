# Guía de verificación visual — Portal de Jugadores

Esta guía es obligatoria para cualquier cambio visual o funcional que agregue o modifique una pantalla del Portal de Jugadores (`/app`) o su administración específica.

## Reglas generales

- Usar `Inter`, los tokens `--app-*` y los componentes del sistema de diseño.
- No agregar colores, sombras, radios ni tamaños arbitrarios en una vista si existe un token equivalente.
- Mantener todos los textos en español de Argentina.
- No modificar autenticación, CSRF, permisos, reservas, pagos o notificaciones para resolver un problema visual.
- No dejar estilos inline, selectores duplicados o parches contradictorios sin una justificación documentada.

## Modo claro

- Fondo general basado en `--app-bg`.
- Tarjetas y formularios sobre `--app-surface`.
- Texto principal con `--app-text` y secundario con `--app-text-muted`.
- Bordes visibles con `--app-border`.
- Acciones principales en naranja (`--app-primary`); verde reservado para selección y estados positivos.
- Campos, tablas, menús, alertas y modales sin fondos oscuros heredados del tema anterior.

## Modo oscuro

- Fondo general basado en `--app-bg` oscuro.
- Superficies diferenciadas mediante `--app-surface` y `--app-surface-alt`.
- Texto principal y secundario legibles con los tokens del tema oscuro.
- Campos y selectores con `--app-input-bg`, borde visible y foco naranja.
- No deben quedar superficies blancas, textos grises oscuros ni sombras de la versión clara.
- Verde solamente para selección/éxito; naranja para acciones.

## Resoluciones mínimas

Comprobar cada pantalla en ambos temas en estas dimensiones:

- 320 × 640
- 375 × 812
- 390 × 844
- 430 × 932
- 768 × 1024
- 820 × 1180
- 1366 × 768
- 1440 × 900
- 1920 × 1080

En móvil verificar también orientación y el área segura inferior.

## Checklist responsive

- [ ] No existe scroll horizontal.
- [ ] No hay texto cortado, superpuesto o fuera de la tarjeta.
- [ ] Botones y campos se pueden usar con el dedo.
- [ ] La navegación inferior no tapa botones, mensajes ni contenido.
- [ ] Las grillas colapsan sin producir columnas demasiado estrechas.
- [ ] Las tablas tienen desplazamiento interno o una presentación legible.
- [ ] Las imágenes mantienen proporción y no generan overflow.
- [ ] Los modales caben en el viewport y se pueden cerrar.
- [ ] Menús y desplegables no quedan fuera de pantalla.
- [ ] Estados vacíos y mensajes largos no rompen la composición.

## Checklist de accesibilidad y contraste

- [ ] Cada campo tiene etiqueta asociada y texto de ayuda comprensible.
- [ ] El foco de teclado es visible en enlaces, botones, campos, menús y modales.
- [ ] No se informa un estado solamente por color.
- [ ] Los íconos decorativos tienen `aria-hidden="true"`.
- [ ] Los controles tienen nombre accesible en español.
- [ ] Los textos secundarios siguen siendo legibles en ambos temas.
- [ ] Los controles táctiles conservan un área cómoda y no quedan pegados.

## Checklist de formularios

- [ ] Estado normal.
- [ ] Foco.
- [ ] Error de validación.
- [ ] Carga/procesamiento.
- [ ] Deshabilitado.
- [ ] Éxito o confirmación.
- [ ] Mensajes cercanos al campo o acción correspondiente.
- [ ] CSRF y funcionalidad conservados.

## Checklist de navegación y componentes

- [ ] Cabecera y navegación inferior visibles según el viewport.
- [ ] Tema persistente al navegar.
- [ ] Menús desplegables abren, cierran y no bloquean la pantalla.
- [ ] Notificaciones sin leer, toast y mensajes de sesión se distinguen correctamente.
- [ ] Calendarios, selectores de horarios y filtros tienen estados seleccionado, disponible y deshabilitado.
- [ ] Tarjetas de reservas y partidos muestran estados pendientes, confirmados, completos y cancelados.
- [ ] Pagos pendientes y confirmados se distinguen sin depender solamente del color.
- [ ] Modales y confirmaciones tienen foco y cierre funcional.

## Procedimiento de verificación visual

1. Levantar el proyecto localmente con la versión de PHP y CodeIgniter soportada.
2. Abrir cada ruta pública del portal y las rutas administrativas específicas disponibles.
3. Capturar cada pantalla en modo claro y oscuro en las resoluciones mínimas.
4. Probar navegación, formularios, errores, estados de carga, modales, menús y cambios de tema.
5. Comparar las capturas con el sistema de diseño y revisar overflow, contraste y espaciado.
6. Corregir la causa del problema en la capa adecuada; no superponer parches ni elementos para ocultarlo.
7. Repetir las capturas después de corregir.
8. Registrar en el informe qué rutas, temas y resoluciones fueron realmente abiertas.

## Criterios de pantalla terminada

Una pantalla se considera terminada únicamente cuando:

- Funciona en claro y oscuro.
- Es usable desde 320 px hasta escritorio.
- No tiene overflow, superposiciones ni textos ilegibles.
- Sus estados interactivos fueron comprobados.
- Respeta `PLAYER_PORTAL_DESIGN_SYSTEM.md` y `AGENTS.md`.
- La autenticación, seguridad y lógica de negocio no fueron alteradas.
- Se documentan las limitaciones si no fue posible abrirla en un navegador real.

## Resultado de la auditoría actual

En este entorno se realizó una auditoría estática de las vistas del portal, las vistas administrativas específicas y los assets CSS/JavaScript. No se pudo ejecutar CodeIgniter ni abrir el portal en un navegador automatizado porque no hay intérprete PHP ni Playwright configurados en el workspace. Por lo tanto, no se generaron capturas reales ni se afirma verificación visual de las resoluciones indicadas.

La auditoría estática detectó estilos legacy concentrados en `player.css` y `player-bookings.css`, además de algunos estilos inline en vistas de registro, verificación y administración. La deuda queda documentada para migración gradual; no se reescribió masivamente para evitar afectar reservas, pagos o autenticación.
