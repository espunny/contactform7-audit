=== ContactForm7 Audit ===
Contributors: tuusuario
Tags: contact form 7, audit, hash chain, blockchain, trazabilidad, GDPR
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistema de trazabilidad completa para formularios Contact Form 7 con hash chaining para auditorías inmutables.

== Description ==

ContactForm7 Audit es un plugin que proporciona trazabilidad completa e inmutable de todos los envíos de formularios Contact Form 7.

= Características principales =

* **Hash Chaining (Blockchain)**: Cada registro está vinculado criptográficamente al anterior usando SHA-256
* **Trazabilidad completa**: Captura todos los datos del formulario, incluyendo:
  - Todos los campos del formulario
  - Texto completo de casillas de aceptación (en texto plano)
  - Estado de las casillas (marcadas/no marcadas)
  - Dirección IP del usuario (sin anonimizar)
  - User Agent completo del navegador
  - Cookies del dominio
  - Resolución de pantalla
* **Verificación de integridad**: Valida que la cadena de hash no haya sido alterada
* **Exportación JSON**: Exporta registros por rango de fechas en formato JSON
* **Panel de administración**: Visualiza estadísticas y registros recientes
* **Hash génesis documentado**: Punto de partida verificable de la cadena

= Casos de uso =

* Cumplimiento de normativas de protección de datos
* Auditorías legales de formularios de contacto
* Evidencia legal de consentimientos otorgados
* Registro inmutable de comunicaciones

= Requisitos =

* WordPress 5.0 o superior
* PHP 7.2 o superior
* Contact Form 7 activo

== Installation ==

1. Sube la carpeta `contactform7-audit` a `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Asegúrate de que Contact Form 7 esté instalado y activo
4. Ve a "Auditoría CF7" en el menú de administración

= Instalación desde ZIP =

1. Descarga el archivo ZIP del plugin
2. Ve a Plugins > Añadir nuevo > Subir plugin
3. Selecciona el archivo ZIP y haz clic en "Instalar ahora"
4. Activa el plugin

== Frequently Asked Questions ==

= ¿Necesito Contact Form 7? =

Sí, este plugin requiere Contact Form 7 para funcionar.

= ¿Los datos se pueden modificar o eliminar? =

Los registros en la base de datos están protegidos por hash chaining. Cualquier modificación o eliminación romperá la cadena, lo cual será detectado por la verificación de integridad.

= ¿Qué es el hash génesis? =

Es el hash inicial de la cadena, compuesto por 64 ceros. Se inserta al activar el plugin y marca el punto de inicio de la cadena de auditoría.

= ¿Puedo exportar los datos? =

Sí, desde el panel de administración puedes exportar registros en formato JSON seleccionando un rango de fechas.

= ¿Cómo verifico la integridad de la cadena? =

En el panel de administración hay un botón "Verificar Integridad de la Cadena" que procesa todos los registros y verifica que no hayan sido alterados.

= ¿Qué pasa al desinstalar el plugin? =

Al desinstalar el plugin, se elimina automáticamente la tabla de auditoría y todos los registros. Asegúrate de exportar los datos antes de desinstalar si necesitas conservarlos.

== Screenshots ==

1. Panel principal con estadísticas de auditoría
2. Verificación de integridad de la cadena
3. Exportación de datos por rango de fechas
4. Tabla de registros recientes
5. Detalles completos de un registro

== Changelog ==

= 1.0.0 - 2025-12-16 =
* Lanzamiento inicial
* Implementación de hash chaining con SHA-256
* Captura completa de datos de formularios CF7
* Panel de administración con estadísticas
* Verificación de integridad por chunks
* Exportación JSON por rango de fechas
* Captura de datos del navegador (cookies, resolución)
* Captura de texto de casillas de aceptación

== Upgrade Notice ==

= 1.0.0 =
Lanzamiento inicial del plugin.

== Privacy Policy ==

Este plugin captura y almacena la siguiente información:

* Todos los datos enviados en formularios Contact Form 7
* Direcciones IP de usuarios (sin anonimizar)
* User Agent completo del navegador
* Cookies del dominio
* Resolución de pantalla del dispositivo

Estos datos se almacenan en una tabla de base de datos separada (`wp_contactform_audit`) y están protegidos por hash chaining para garantizar su inmutabilidad.

**IMPORTANTE**: Este plugin está diseñado para auditorías y no implementa anonimización de IPs. Asegúrate de que tu política de privacidad informe adecuadamente a los usuarios sobre la captura y almacenamiento de estos datos.

== Technical Details ==

= Hash Chaining =

Cada registro incluye:
* `previous_hash`: Hash del registro anterior
* `current_hash`: SHA-256 de todos los datos del registro + previous_hash

El hash génesis es: `0000000000000000000000000000000000000000000000000000000000000000`

= Estructura de la tabla =

```sql
CREATE TABLE wp_contactform_audit (
    id bigint(20) UNSIGNED AUTO_INCREMENT,
    form_id bigint(20) UNSIGNED,
    form_name varchar(255),
    submission_data longtext,  -- JSON
    user_ip varchar(45),
    user_agent text,
    browser_data longtext,     -- JSON
    previous_hash varchar(64),
    current_hash varchar(64),
    created_at datetime,
    PRIMARY KEY (id),
    KEY form_id (form_id),
    KEY created_at (created_at),
    KEY current_hash (current_hash)
);
```

= Verificación de integridad =

La verificación procesa registros en lotes de 100 para optimizar el rendimiento en bases de datos grandes. Cada registro se verifica:
1. Recalculando el hash con los datos almacenados
2. Comparando con el hash almacenado
3. Verificando que el registro siguiente tenga el previous_hash correcto

== Support ==

Para soporte, reportes de errores o solicitudes de características, visita:
https://github.com/tuusuario/contactform7-audit/issues
