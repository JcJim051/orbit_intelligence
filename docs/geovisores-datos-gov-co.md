# Geovisores desde Datos.gov.co

El módulo **Datos abiertos** permite que un Gestor SIID pegue una portada oficial de Datos.gov.co y cree una fuente, una capa y un geovisor sin usar SQL ni acceder al servidor.

## Flujo

1. Abra **Gestión geográfica → Datos abiertos**.
2. Pegue una URL HTTPS de `datos.gov.co` y pulse **Analizar conjunto**.
3. Revise metadatos, diagnóstico territorial y vista previa.
4. Seleccione indicador, agregación, filtros (máximo 4), atributos públicos (máximo 12) y estilo.
5. Cree un visor nuevo o agregue la capa a un borrador propio.
6. Revise la vista previa y pulse **Enviar a revisión**.
7. Gerencia, apoyo administrativo o administración aprueban el geovisor desde **Visores y capas**.

SIID reconoce geometrías Socrata, pares latitud/longitud y códigos DANE municipales del Meta. Un conjunto sin geografía confiable se rechaza antes de crear registros.

## Sincronización y continuidad

`open-data:refresh --queue` actualiza las fuentes publicadas cada seis horas. Cada combinación consultada de filtros conserva una instantánea. Si Datos.gov.co falla o cambia el esquema, la API pública continúa entregando la última instantánea válida y el panel marca la fuente como `needs_review` o `error`.

Para probar una fuente manualmente:

```bash
sudo -u www-data php artisan open-data:refresh --source=identificador-de-la-fuente
```

El scheduler del servidor debe ejecutar `php artisan schedule:run` cada minuto y debe existir al menos un trabajador de la cola configurada por la aplicación.
