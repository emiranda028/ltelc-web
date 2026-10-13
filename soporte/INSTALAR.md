# Soporte LTELC: puesta en marcha en Hostinger

El sistema vive en **https://ltelc.tech/soporte/** y se publica solo con cada push a `main`, como el resto de la web.
Las claves y los adjuntos quedan **fuera de `public_html`**: el deploy desde GitHub no los toca y no son accesibles desde internet.

## 1. Casilla de mail
hPanel → **Correos** → Crear cuenta: `soporte@ltelc.tech` (anotar la contraseña).

## 2. Base de datos
hPanel → **Bases de datos → Administración** → Crear base MySQL (por ejemplo `soporte`) con su usuario y contraseña.
Hostinger les antepone el prefijo de la cuenta: `u497151173_soporte`.

## 3. Configuración
hPanel → **Archivos → Administrador de archivos → Acceder a todos los archivos** (carpeta personal, `/home/u497151173/`).
Ahí, al mismo nivel que `domains`, crear `soporte-config.php` copiando el contenido de `soporte/config.sample.php` y completar:
- datos de la base (paso 2),
- contraseña de `soporte@ltelc.tech` en `smtp` e `imap` (paso 1),
- `cron_key` e `install_key`: dos textos largos al azar.

No usar `domains/ltelc.tech/`: Hostinger la administra para el deploy desde GitHub (archivo `DO_NOT_UPLOAD_HERE`).
Los adjuntos se guardan en `/home/u497151173/soporte-uploads/`, que se crea sola.

## 4. Instalar
Entrar a **https://ltelc.tech/soporte/instalar.php**, poner la `install_key` y crear el primer administrador.
Después, en el panel → **Equipo**, sumar a Fabián y Gustavo.

## 5. Lectura automática del mail (cada 5 minutos)
hPanel → **Avanzado → Cron Jobs** → tipo PHP, cada 5 minutos:
```
/home/u497151173/domains/ltelc.tech/public_html/soporte/cron.php
```
El cron lee `soporte@ltelc.tech`, convierte cada mail en reclamo (o en respuesta, si el asunto trae `[LT-00012]`),
lo archiva en la carpeta **Procesados** de la casilla y envía los avisos pendientes al equipo.

## Cómo funciona
- **Entradas**: formulario web, mail a soporte@ltelc.tech, respuestas a los mails del sistema y derivación desde WhatsApp.
- **Clientes**: reciben número de reclamo, siguen todo por mail o desde su enlace; en «Mis reclamos» entran con un enlace al mail, sin contraseña.
- **Equipo**: bandeja con plazos, asignación, notas internas que el cliente no ve, prioridades y estados.
- **Copia**: todos los avisos al equipo llegan también a agencialtelc@gmail.com (`notify_copy` en la configuración).
- **Horario**: lunes a viernes de 9 a 18. Fuera de horario el equipo no recibe avisos, salvo los **urgentes**. Los plazos se cuentan en horas hábiles (urgente 2 h, alta 4 h, normal 9 h, baja 27 h).
- Los reclamos resueltos sin novedades pasan a «Cerrado» a los 7 días.

## WhatsApp Business: mensajes automáticos
WhatsApp Business → Herramientas para la empresa.

**Mensaje de ausencia** (fuera del horario):
> Hola, gracias por escribir a LTELC. Nuestro horario de atención es de lunes a viernes de 9 a 18 h.
> Para que tu consulta o reclamo no se pierda y quede con número de seguimiento, cargalo en **ltelc.tech/soporte** o escribí a **soporte@ltelc.tech**.
> Si es urgente (el sistema no funciona), marcalo como «Urgente» en el formulario: el equipo recibe el aviso en el momento.

**Mensaje de bienvenida** (en horario):
> Hola, gracias por escribir a LTELC. Para consultas y reclamos sobre los sistemas usamos **ltelc.tech/soporte**: te damos un número de seguimiento y lo atiende quien corresponda.
> Si es por un proyecto nuevo, contanos por acá.

**Respuesta rápida** `/soporte`:
> Para que lo veamos en orden, ¿lo podés cargar en ltelc.tech/soporte? Te llega un número de reclamo y te avisamos por mail cada avance. ¡Gracias!
