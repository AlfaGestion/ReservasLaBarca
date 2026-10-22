# Instrucciones del proyecto La Barca

- Toda interfaz visible debe estar en español de Argentina (`es-AR`), incluyendo la web pública, el portal `/app`, el panel administrativo, JavaScript, validaciones, estados de pago y correos.
- Usar fechas, horarios y números con formato argentino y la zona horaria configurada para el complejo.
- Los mensajes técnicos, excepciones, trazas, consultas SQL y respuestas crudas de proveedores nunca deben mostrarse al usuario. Registrarlos internamente y mostrar un mensaje claro en español.
- Las funcionalidades nuevas deben reutilizar la lógica existente sin romper rutas, reservas, pagos ni autenticación administrativa.
- No incluir secretos ni credenciales en el repositorio.
- Toda pantalla nueva del portal debe cargar y respetar `public/assets/player/player-theme.css` y la documentación de `docs/PLAYER_PORTAL_DESIGN_SYSTEM.md`.
- Usar Inter, la escala tipográfica y las variables `--app-*`; no agregar colores o tamaños arbitrarios en componentes individuales.
