# Instrucciones del proyecto La Barca

- Toda interfaz visible debe estar en español de Argentina (`es-AR`), incluyendo la web pública, el portal `/app`, el panel administrativo, JavaScript, validaciones, estados de pago y correos.
- Usar fechas, horarios y números con formato argentino y la zona horaria configurada para el complejo.
- Los mensajes técnicos, excepciones, trazas, consultas SQL y respuestas crudas de proveedores nunca deben mostrarse al usuario. Registrarlos internamente y mostrar un mensaje claro en español.
- Las funcionalidades nuevas deben reutilizar la lógica existente sin romper rutas, reservas, pagos ni autenticación administrativa.
- No incluir secretos ni credenciales en el repositorio.
- Toda pantalla nueva del portal debe cargar y respetar `public/assets/player/player-theme.css` y la documentación de `docs/PLAYER_PORTAL_DESIGN_SYSTEM.md`.
- Usar Inter, la escala tipográfica y las variables `--app-*`; no agregar colores o tamaños arbitrarios en componentes individuales.

## Reglas de diseño y compatibilidad visual — Portal de Jugadores

- Toda nueva pantalla del Portal de Jugadores debe funcionar en modo claro y oscuro, respetando `data-theme` y los tokens `--app-*`.
- Todo nuevo componente debe reutilizar los tokens y componentes definidos en `docs/PLAYER_PORTAL_DESIGN_SYSTEM.md`; no incorporar una paleta paralela ni estilos duplicados.
- Toda funcionalidad nueva debe ser responsive desde 320 px hasta escritorio, sin desplazamiento horizontal ni contenido cortado.
- No se permiten colores fijos, fondos, bordes o sombras que rompan alguno de los temas. Las excepciones deben ser fotografías, overlays de contraste y estados documentados.
- Los formularios deben contemplar estados normal, foco, error, carga, deshabilitado y éxito, con mensajes legibles en ambos temas.
- Las pantallas móviles deben respetar la navegación inferior, el área segura del dispositivo y el espacio para controles táctiles.
- Las decoraciones deben ser no interactivas, no tapar contenido y no generar overflow, fragmentos cortados ni barras de desplazamiento.
- Antes de finalizar cualquier tarea visual se deben comprobar ambos temas, al menos un celular, una tablet y un escritorio. Si no es posible abrir el navegador, debe documentarse la limitación.
- No se considera terminado un desarrollo visual que solamente funcione en un tema o resolución.
- La referencia visual y los tokens obligatorios están definidos en `docs/PLAYER_PORTAL_DESIGN_SYSTEM.md`; el procedimiento de validación está en `docs/PLAYER_PORTAL_VISUAL_QA.md`.
