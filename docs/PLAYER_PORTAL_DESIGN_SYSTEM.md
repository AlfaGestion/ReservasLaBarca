# Sistema de diseño del portal de jugadores

El portal `/app` utiliza una identidad visual única basada en la web original de La Barca. El diseño puede modernizarse, pero no debe crear una paleta o tipografía paralela.

## Tipografía

- Familia oficial: `Inter`.
- Respaldos: `ui-sans-serif`, `system-ui`, `-apple-system`, `Segoe UI`, sans-serif.
- La fuente se carga desde Google Fonts y los controles mantienen `16px` como mínimo para evitar zoom automático en celulares.

## Paleta oficial

### Modo claro

| Variable | HEX | Uso |
|---|---|---|
| `--app-primary` | `#f39323` | Botones y acciones principales |
| `--app-primary-hover` | `#dd8218` | Hover y foco visual de acciones |
| `--app-selection` | `#0e963b` | Selecciones activas |
| `--app-selection-bg` | `#eaf7ee` | Fondo de selección |
| `--app-bg` | `#f4f8fd` | Fondo general azul muy suave |
| `--app-surface` | `#ffffff` | Tarjetas y paneles |
| `--app-surface-alt` | `#eaf2fb` | Superficies secundarias azuladas |
| `--app-text` | `#17324d` | Texto principal azul marino |
| `--app-text-muted` | `#595959` | Texto secundario |
| `--app-border` | `#b1d4f0` | Bordes azulados |

### Modo oscuro

| Variable | HEX | Uso |
|---|---|---|
| `--app-bg` | `#13283d` | Fondo azul marino de marca |
| `--app-surface` | `#1b3148` | Tarjetas y paneles |
| `--app-surface-alt` | `#17314b` | Superficies secundarias |
| `--app-input-bg` | `#10253a` | Campos y selectores |
| `--app-border` | `#3a5f80` | Bordes azulados |
| `--app-text` | `#f5f8fb` | Texto principal |
| `--app-text-muted` | `#c3d1de` | Texto secundario |
| `--app-selection` | `#43c878` | Selecciones activas |
| `--app-selection-bg` | `#194b3a` | Fondo de selección |

El naranja se usa para acciones. El verde se usa para estados seleccionados. No intercambiar sus funciones.

## Escala tipográfica

- Texto general: `15px`.
- Campos y selectores: `16px`.
- Botones: `15px`.
- Etiquetas: `14px`.
- Texto secundario: `13px`.
- Mensajes auxiliares: `12–13px`.
- Títulos de tarjeta: `18px`.
- Títulos de sección: `22–24px`.
- Títulos principales: `clamp(28px, 4vw, 32px)`, con una variante visual mayor únicamente para el hero de inicio.

## Componentes

- Botón principal: fondo naranja, texto oscuro en estado normal y blanco en hover.
- Botón secundario: fondo transparente o superficie secundaria, borde visible y texto del tema.
- Selección activa: verde institucional y fondo verde suave.
- Campos: alto mínimo de `48px`, texto de `16px`, borde del tema y foco naranja.
- Tarjetas: superficie del tema, borde discreto, radio de `12px` y sombra moderada.
- Radios permitidos: `8px`, `12px` y `16px`.

## Responsive

El portal debe funcionar desde `320px` hasta escritorio. Los controles táctiles deben conservar alturas cómodas, no generar scroll horizontal y mantener textos legibles en 320, 375, 390, 430, 768 y 1024px.

## Uso correcto e incorrecto

### Componentes de partidos

La sección de partidos utiliza el selector segmentado `.matches-switcher`, filtros agrupados `.modern-filters`, tarjetas `.modern-match-card`, estado vacío `.modern-empty` y barra de capacidad `.capacity-bar`. Estos componentes deben conservar las variables `--app-*`, usar el verde únicamente para selección/estado y mantener acciones principales en naranja.

La imagen principal del inicio está preparada en `.hero-photo` para recibir una fotografía deportiva autorizada en `public/assets/images/player/hero-sport.jpg`. Actualmente no se incorporó una imagen externa ni se presentó la textura disponible como una instalación real.

Correcto: `background: var(--app-surface); color: var(--app-text);`.

Incorrecto: agregar fondos negros, verdes o azules nuevos directamente dentro de una vista, usar Arial/serif, o crear un botón con una combinación de contraste distinta al sistema.

## Estados visuales obligatorios

Todos los componentes interactivos deben usar los tokens del tema para sus estados:

- Normal: `--app-surface`, `--app-text`, `--app-border`.
- Hover y foco: `--app-primary-hover` o `--app-focus`, con un indicador visible.
- Seleccionado o disponible: `--app-selection` y `--app-selection-bg`.
- Deshabilitado: `--app-surface-alt`, `--app-border`, `--app-text-muted`, sin depender solamente de opacidad.
- Éxito: `--app-success-bg` y `--app-success-text`.
- Error: `--app-danger-bg` y `--app-danger-text`.
- Información: `--app-surface-alt`, `--app-text` y `--app-border`.

Los formularios deben conservar una altura mínima de 48 px, texto de 16 px en campos, etiquetas asociadas mediante `for`/`id`, foco visible y mensajes de error cercanos al campo correspondiente.

## Convenciones de responsive

- El contenido principal debe poder contraerse hasta 320 px sin overflow horizontal.
- Los puntos de corte existentes del portal son 760 px para móvil y 900/1120 px para composiciones intermedias; no crear otro punto de corte sin documentar su motivo.
- La navegación inferior debe reservar espacio inferior mediante `padding-bottom` y `env(safe-area-inset-bottom)`.
- Las grillas deben usar `minmax(0, ...)` cuando exista riesgo de desbordamiento por textos largos.
- Las imágenes deben conservar proporción con `object-fit: cover` o `contain`; nunca deformarse por dimensiones rígidas.
- Las decoraciones deben quedar contenidas, tener `pointer-events: none` y no utilizar posiciones que generen scroll horizontal.

## Acciones en pantallas administrativas

Las pantallas de alta y edición del administrador deben mostrar la barra de acciones en la parte superior del formulario, en este orden:

1. **Nuevo**.
2. **Grabar**.
3. **Cancelar / Salir**.

Los botones deben mantener el mismo layout administrativo, ser accesibles en celular y no quedar al final de formularios extensos. Los grupos de datos relacionados deben presentarse dentro de frames o paneles plegables cuando corresponda, para permitir cerrar una sección y continuar editando otra.

## Componentes y deuda visual conocida

El portal carga una base legacy (`player.css`) y capas específicas (`player-theme.css`, `player-bookings.css`, `player-login.css`). Las pantallas nuevas deben agregar reglas en la capa específica correspondiente y reutilizar `--app-*`; no sumar overrides globales ni repetir selectores para resolver el mismo estado.

Durante una limpieza futura, los estilos legacy de reservas y los estilos inline de vistas administrativas/verificación deben migrarse gradualmente a la capa temática sin modificar la lógica de negocio. Hasta completar esa migración, toda corrección debe comprobarse en claro y oscuro porque algunas reglas antiguas contienen colores de tema oscuro.
