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
