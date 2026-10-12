# Publicación automática: GitHub → Hostinger

Cada vez que se sube un cambio a la rama `main` de este repositorio, Hostinger lo publica en https://ltelc.tech.

## Configuración (una sola vez)

1. **Dominio en el hosting.** hPanel → Sitios web → asegurarse de que `ltelc.tech` esté asociado al plan de hosting
   (si los nameservers siguen en `dns-parking.com`, Hostinger los cambia al asociarlo).
2. **Repositorio público:** no hace falta clave de acceso.
3. **Crear el despliegue.** hPanel → Sitios web → ltelc.tech → Avanzado → **Git**:
   - Repositorio: `https://github.com/emiranda028/ltelc-web.git`
   - Rama: `main`
   - Directorio: vacío (se instala en `public_html`; tiene que estar vacío, borrar el `default.php` si existe)
   - Crear.
4. **Despliegue automático.** En la lista de repositorios de hPanel → **Auto Deployment** → copiar la **Webhook URL**.
   En GitHub: Settings → **Webhooks** → Add webhook → pegar la URL, Content type `application/json`, evento *Just the push event* → Add.
5. **SSL.** hPanel → Seguridad → SSL → activar el certificado gratuito para `ltelc.tech`.

Desde ahí: cambio en `main` → webhook → Hostinger hace `git pull` → sitio actualizado en segundos.
