# Demo temporal con ngrok

Esta guía permite compartir la aplicación local de forma temporal. No reemplaza una configuración de producción ni habilita cobros reales.

## Preparar el entorno

1. Iniciar MySQL/MariaDB desde Laragon.
2. Copiar `.env.example` como `.env` si todavía no existe.
3. Completar únicamente los valores sensibles en `.env` local. No subir ese archivo al repositorio.
4. Ejecutar las migraciones cuando corresponda:

```powershell
php spark migrate
```

## Iniciar la aplicación

Desde la raíz del proyecto:

```powershell
php spark serve --port 8080
```

Verificar primero `http://localhost:8080/app`.

## Iniciar ngrok

Usar una versión actual de ngrok y configurar el token una sola vez, fuera del repositorio:

```powershell
ngrok config add-authtoken "TU_TOKEN"
ngrok http 8080
```

Copiar la URL `https://...ngrok-free.app` que muestra la terminal. El túnel deja de existir al cerrar ngrok.

## Configurar la URL pública

Durante la demo, editar `.env`:

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://tu-subdominio.ngrok-free.app/'
cookie.secure = true
cookie.httponly = true
cookie.samesite = 'Lax'
```

Reiniciar `php spark serve` después de modificar el `.env`. No dejar el dominio temporal de ngrok escrito en el código.

La primera visita a un dominio gratuito de ngrok puede mostrar un aviso de seguridad del proveedor. Debe aceptarse desde el navegador antes de continuar.

## Verificación rápida

- Abrir `/app` desde una ventana privada o un celular con datos móviles.
- Confirmar que CSS, JavaScript, imágenes y formularios carguen desde HTTPS.
- Probar registro, login y navegación del portal.
- Verificar que los enlaces no redirijan a `localhost`.
- Revisar el panel de inspección en `http://127.0.0.1:4040`.
- No compartir ni pegar públicamente el token de ngrok.

## Correo y pagos

El registro verificado, recuperación de contraseña y notificaciones requieren SMTP configurado mediante variables de entorno. Si el SMTP no está configurado, la operación principal no debe mostrar detalles técnicos.

Mercado Pago conserva su flujo existente. Para una demo, utilizar el modo de prueba configurado por el administrador y no realizar cobros reales sin autorización.

## Detener y volver a local

1. Presionar `Ctrl+C` en la terminal de ngrok.
2. Presionar `Ctrl+C` en la terminal de `php spark serve` cuando corresponda.
3. Restaurar en `.env`:

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
cookie.secure = false
```

No incluir credenciales SMTP, Mercado Pago, base de datos ni ngrok en esta documentación.
