# Guía Técnica para Auditores

**ContactForm7 Audit - Plugin de Trazabilidad**

---

## 📋 Índice

1. [Introducción](#introducción)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Algoritmo de Hash Chaining](#algoritmo-de-hash-chaining)
4. [Estructura de Datos](#estructura-de-datos)
5. [Proceso de Verificación](#proceso-de-verificación)
6. [Guía de Auditoría Paso a Paso](#guía-de-auditoría-paso-a-paso)
7. [Limitaciones y Consideraciones](#limitaciones-y-consideraciones)
8. [Preguntas Frecuentes](#preguntas-frecuentes)

---

## Introducción

Este documento proporciona información técnica detallada sobre el funcionamiento interno del plugin **ContactForm7 Audit**, diseñado específicamente para auditores, inspectores y profesionales técnicos que necesiten verificar la integridad y autenticidad de los registros de formularios.

### Objetivo del Plugin

Proporcionar un sistema de **trazabilidad inmutable** mediante hash chaining (similar a blockchain) para registros de formularios Contact Form 7 en WordPress.

### Transparencia Total

Todo el código fuente está disponible públicamente. Este documento complementa el código con explicaciones detalladas del funcionamiento interno.

---

## Arquitectura del Sistema

### Componentes Principales

```
contactform7-audit/
├── includes/
│   ├── class-hash-chain.php      → Lógica de hash chaining
│   ├── class-form-handler.php    → Captura de envíos de formularios
│   ├── class-activator.php       → Creación de tabla en activación
│   └── class-deactivator.php     → Limpieza en desactivación
├── admin/
│   └── class-admin.php           → Panel de administración y verificación
├── public/
│   └── js/
│       └── browser-capture.js    → Captura de datos del navegador
└── uninstall.php                 → Eliminación completa de datos
```

### Flujo de Datos

```
Usuario envía formulario
        ↓
[Contact Form 7 intercepta el envío]
        ↓
[class-form-handler.php captura datos]
        ↓
[browser-capture.js inyecta datos del navegador]
        ↓
[class-hash-chain.php calcula hash]
        ↓
[Registro guardado en BD con hash encadenado]
        ↓
[Verificador puede validar integridad en cualquier momento]
```

---

## Algoritmo de Hash Chaining

### Concepto

Cada registro está vinculado criptográficamente al registro anterior mediante SHA-256, creando una cadena donde:

- Cualquier modificación en un registro rompe toda la cadena subsecuente
- La integridad es verificable mediante recalculación de hashes
- El sistema es autocontenido y no requiere terceros

### Hash Génesis

**Valor fijo hardcodeado:**

```
0000000000000000000000000000000000000000000000000000000000000000
```

**Ubicación en código:** `includes/class-hash-chain.php`, línea 22

```php
const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';
```

Este hash sirve como `previous_hash` del primer registro de la base de datos.

### Función de Cálculo de Hash

**Algoritmo:** SHA-256

**Datos incluidos (en orden exacto):**

1. `form_id` - ID numérico del formulario Contact Form 7
2. `form_name` - Nombre textual del formulario
3. `submission_data` - JSON completo con todos los campos enviados
4. `user_ip` - Dirección IP del usuario
5. `user_agent` - User Agent completo del navegador
6. `browser_data` - JSON con cookies y datos del navegador
7. `created_at` - Timestamp en formato MySQL (YYYY-MM-DD HH:MM:SS)
8. `previous_hash` - Hash SHA-256 del registro anterior

**String concatenado:**

```
form_id|form_name|submission_data|user_ip|user_agent|browser_data|created_at|previous_hash
```

**Código de referencia:**

```php
// includes/class-hash-chain.php - líneas 56-71
public function calculate_hash($data, $previous_hash) {
    $hash_string =
        $data['form_id'] . '|' .
        $data['form_name'] . '|' .
        $data['submission_data'] . '|' .
        $data['user_ip'] . '|' .
        $data['user_agent'] . '|' .
        $data['browser_data'] . '|' .
        $data['created_at'] . '|' .
        $previous_hash;

    return hash('sha256', $hash_string);
}
```

### Ejemplo Práctico

**Datos del registro:**

```
form_id: 123
form_name: "Formulario de contacto"
submission_data: {"nombre":"Juan","email":"juan@example.com"}
user_ip: "192.168.1.100"
user_agent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"
browser_data: {"cookies":"session=abc123","screen_resolution":"1920x1080"}
created_at: "2025-12-16 10:30:45"
previous_hash: "0000000000000000000000000000000000000000000000000000000000000000"
```

**String concatenado:**

```
123|Formulario de contacto|{"nombre":"Juan","email":"juan@example.com"}|192.168.1.100|Mozilla/5.0 (Windows NT 10.0; Win64; x64)|{"cookies":"session=abc123","screen_resolution":"1920x1080"}|2025-12-16 10:30:45|0000000000000000000000000000000000000000000000000000000000000000
```

**Hash resultante (ejemplo):**

```
a7b9c3d2e1f4567890abcdef1234567890abcdef1234567890abcdef12345678
```

Este hash se guarda como `current_hash` del registro actual, y será el `previous_hash` del siguiente registro.

---

## Estructura de Datos

### Esquema de Base de Datos

**Tabla:** `wp_contactform_audit` (prefijo `wp_` puede variar)

```sql
CREATE TABLE wp_contactform_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id BIGINT NOT NULL,
    form_name VARCHAR(255) NOT NULL,
    submission_data LONGTEXT NOT NULL,
    user_ip VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    browser_data TEXT,
    previous_hash VARCHAR(64) NOT NULL,
    current_hash VARCHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_created_at (created_at),
    INDEX idx_form_id (form_id),
    INDEX idx_current_hash (current_hash)
);
```

### Descripción de Campos

| Campo             | Tipo         | Descripción                              |
| ----------------- | ------------ | ---------------------------------------- |
| `id`              | BIGINT       | ID autoincremental del registro          |
| `form_id`         | BIGINT       | ID del formulario Contact Form 7         |
| `form_name`       | VARCHAR(255) | Nombre descriptivo del formulario        |
| `submission_data` | LONGTEXT     | JSON con todos los campos del formulario |
| `user_ip`         | VARCHAR(45)  | Dirección IP del usuario (IPv4 o IPv6)   |
| `user_agent`      | TEXT         | User Agent completo del navegador        |
| `browser_data`    | TEXT         | JSON con cookies y datos del navegador   |
| `previous_hash`   | VARCHAR(64)  | Hash SHA-256 del registro anterior       |
| `current_hash`    | VARCHAR(64)  | Hash SHA-256 de este registro            |
| `created_at`      | DATETIME     | Timestamp del envío del formulario       |

### Formato de `submission_data`

JSON que contiene todos los campos del formulario, incluyendo:

```json
{
  "nombre": "Juan Pérez",
  "email": "juan@example.com",
  "telefono": "612345678",
  "mensaje": "Consulta sobre producto X",
  "aceptacion-rgpd": {
    "text": "Acepto la política de privacidad y protección de datos",
    "value": "1"
  },
  "newsletter": {
    "text": "Deseo recibir información comercial",
    "value": ""
  }
}
```

**Campos de aceptación:**

- `text`: Texto completo de la casilla de aceptación (legible)
- `value`: "1" si está marcada, "" si no está marcada

### Formato de `browser_data`

JSON capturado por JavaScript en el navegador del cliente:

```json
{
  "cookies": "session_id=abc123; user_pref=xyz789; _ga=GA1.2.123456789",
  "screen_resolution": "1920x1080",
  "timestamp": "2025-12-16T10:30:45.123Z"
}
```

---

## Proceso de Verificación

### Verificación Automática (Panel de Administración)

El plugin incluye un verificador automático accesible desde el panel de administración.

**Ubicación:** WordPress Admin → CF7 Audit → "Verificar Integridad de la Cadena"

**Proceso:**

1. Recupera todos los registros ordenados por ID ascendente
2. Para cada registro:
   - Extrae todos los campos necesarios
   - Recalcula el hash usando la función `calculate_hash()`
   - Compara el hash calculado con el `current_hash` almacenado
   - Verifica que el `previous_hash` coincida con el `current_hash` del registro anterior
3. Genera un reporte con:
   - Total de registros verificados
   - Número de registros íntegros
   - Número de registros con discrepancias (si existen)
   - Detalles de cada discrepancia encontrada

**Código de referencia:** `admin/class-admin.php` - método `verify_chain_integrity()`

### Verificación Manual con SQL

#### 1. Verificar Continuidad de la Cadena

Comprueba que cada `previous_hash` coincida con el `current_hash` del registro anterior:

```sql
SELECT
    a.id,
    a.created_at,
    a.previous_hash AS hash_declarado,
    LAG(a.current_hash) OVER (ORDER BY a.id) AS hash_real_anterior,
    CASE
        WHEN a.previous_hash = LAG(a.current_hash) OVER (ORDER BY a.id) THEN 'OK'
        WHEN a.id = (SELECT MIN(id) FROM wp_contactform_audit)
             AND a.previous_hash = '0000000000000000000000000000000000000000000000000000000000000000'
             THEN 'GENESIS (OK)'
        ELSE 'ERROR - CADENA ROTA'
    END AS estado
FROM wp_contactform_audit a
ORDER BY a.id;
```

#### 2. Detectar Gaps en IDs

Verifica que no falten registros (IDs consecutivos):

```sql
SELECT
    id,
    LAG(id) OVER (ORDER BY id) AS id_anterior,
    id - LAG(id) OVER (ORDER BY id) AS diferencia
FROM wp_contactform_audit
HAVING diferencia > 1;
```

Si hay resultados, indica que se eliminaron registros intermedios.

#### 3. Verificar Hash Génesis

Comprueba que el primer registro use correctamente el hash génesis:

```sql
SELECT
    id,
    previous_hash,
    CASE
        WHEN previous_hash = '0000000000000000000000000000000000000000000000000000000000000000'
        THEN 'CORRECTO'
        ELSE 'INCORRECTO'
    END AS validacion
FROM wp_contactform_audit
WHERE id = (SELECT MIN(id) FROM wp_contactform_audit);
```

### Verificación Manual con PHP

Script para recalcular el hash de un registro específico:

```php
<?php
// Conectar a la base de datos de WordPress
require_once('wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'contactform_audit';

// ID del registro a verificar
$record_id = 1; // Cambiar al ID deseado

// Obtener el registro
$record = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table WHERE id = %d",
    $record_id
));

if (!$record) {
    die("Registro no encontrado");
}

// Reconstruir el string para hashear
$hash_string =
    $record->form_id . '|' .
    $record->form_name . '|' .
    $record->submission_data . '|' .
    $record->user_ip . '|' .
    $record->user_agent . '|' .
    $record->browser_data . '|' .
    $record->created_at . '|' .
    $record->previous_hash;

// Calcular hash
$calculated_hash = hash('sha256', $hash_string);

// Comparar
echo "Registro ID: " . $record->id . "\n";
echo "Fecha: " . $record->created_at . "\n";
echo "Hash almacenado:  " . $record->current_hash . "\n";
echo "Hash calculado:   " . $calculated_hash . "\n";
echo "Coincide: " . ($record->current_hash === $calculated_hash ? 'SÍ ✓' : 'NO ✗') . "\n";

// Verificar encadenamiento
$previous_record = $wpdb->get_row($wpdb->prepare(
    "SELECT current_hash FROM $table WHERE id = %d",
    $record_id - 1
));

if ($previous_record) {
    echo "\nVerificación de encadenamiento:\n";
    echo "Previous hash declarado: " . $record->previous_hash . "\n";
    echo "Current hash anterior:   " . $previous_record->current_hash . "\n";
    echo "Encadenado: " . ($record->previous_hash === $previous_record->current_hash ? 'SÍ ✓' : 'NO ✗') . "\n";
} elseif ($record_id === (int)$wpdb->get_var("SELECT MIN(id) FROM $table")) {
    echo "\nPrimer registro - verificando hash génesis:\n";
    echo "Previous hash: " . $record->previous_hash . "\n";
    echo "Es génesis: " . ($record->previous_hash === '0000000000000000000000000000000000000000000000000000000000000000' ? 'SÍ ✓' : 'NO ✗') . "\n";
}
?>
```

---

## Guía de Auditoría Paso a Paso

### Fase 1: Preparación

1. **Obtener acceso al servidor**

   - Acceso a la base de datos MySQL
   - Acceso al panel de administración de WordPress
   - Acceso a los archivos del plugin (opcional, para verificar código)

2. **Identificar la instalación**

   - Versión de WordPress
   - Versión del plugin ContactForm7 Audit
   - Versión de Contact Form 7
   - Prefijo de tablas de WordPress (típicamente `wp_`)

3. **Documentar el entorno**
   - Servidor web (Apache, Nginx, etc.)
   - Versión de PHP
   - Versión de MySQL/MariaDB
   - Sistema operativo del servidor

### Fase 2: Extracción de Datos

4. **Exportar registros desde el panel**

   - Acceder a WordPress Admin → CF7 Audit
   - Seleccionar rango de fechas relevante
   - Exportar en formato JSON
   - Guardar archivo con nombre descriptivo: `audit_export_YYYY-MM-DD.json`

5. **Backup directo de base de datos** (recomendado)

   ```sql
   -- Exportar tabla completa
   SELECT * FROM wp_contactform_audit
   INTO OUTFILE '/tmp/contactform_audit_backup.csv'
   FIELDS TERMINATED BY ','
   ENCLOSED BY '"'
   LINES TERMINATED BY '\n';
   ```

   O usar `mysqldump`:

   ```bash
   mysqldump -u usuario -p database_name wp_contactform_audit > audit_backup.sql
   ```

### Fase 3: Verificación de Integridad

6. **Verificación automática**

   - Acceder a WordPress Admin → CF7 Audit
   - Hacer clic en "Verificar Integridad de la Cadena"
   - Capturar screenshot del resultado
   - Documentar:
     - Total de registros verificados
     - Registros con errores (si existen)
     - Timestamp de la verificación

7. **Verificación manual del primer registro**

   ```sql
   SELECT * FROM wp_contactform_audit ORDER BY id ASC LIMIT 1;
   ```

   Verificar:

   - El `previous_hash` debe ser el hash génesis (64 ceros)
   - El `current_hash` debe tener 64 caracteres hexadecimales

8. **Verificación de continuidad**

   - Ejecutar la consulta SQL de verificación de cadena (ver sección anterior)
   - Documentar cualquier gap en IDs o discrepancia en hashes

9. **Recalcular hashes manualment**e (muestra aleatoria)
   - Seleccionar 10-20 registros al azar
   - Recalcular sus hashes usando el script PHP
   - Verificar que coincidan con los almacenados

### Fase 4: Análisis de Contenido

10. **Revisar datos capturados**

    - Verificar que `submission_data` contenga todos los campos esperados
    - Verificar que las casillas de aceptación tengan:
      - Texto completo (`text`)
      - Estado (`value`: "1" o "")
    - Verificar que `browser_data` contenga cookies y resolución

11. **Análisis de timestamps**

    - Comparar timestamps de base de datos con logs del servidor web (si disponibles)
    - Buscar patrones anómalos (múltiples envíos en milisegundos, etc.)

12. **Análisis de IPs y User Agents**
    - Detectar posibles suplantaciones
    - Verificar coherencia geográfica (opcional: usar servicios de geolocalización)
    - Identificar patrones de bots o automatización

### Fase 5: Verificación de Seguridad

13. **Revisar logs de acceso a la base de datos** (si disponibles)

    - Buscar accesos directos a la tabla `wp_contactform_audit`
    - Identificar cualquier UPDATE o DELETE manual

14. **Verificar permisos de archivos**

    ```bash
    ls -la wp-content/plugins/contactform7-audit/
    ```

    Los archivos PHP no deben ser escribibles por el usuario web

15. **Revisar código del plugin** (opcional pero recomendado)
    - Comparar con el código fuente oficial en GitHub
    - Buscar modificaciones no autorizadas
    - Calcular hash MD5/SHA256 de archivos clave:
      ```bash
      sha256sum includes/class-hash-chain.php
      ```

### Fase 6: Documentación

16. **Generar informe de auditoría** que incluya:

    - Metodología utilizada
    - Fechas de la auditoría
    - Versiones de software
    - Resultados de verificación de integridad
    - Muestras de registros verificados
    - Discrepancias encontradas (si existen)
    - Screenshots de evidencias
    - Hashes de archivos del plugin
    - Hash génesis documentado
    - Conclusiones técnicas

17. **Preservar evidencias**
    - Guardar exportaciones JSON
    - Guardar backups de base de datos
    - Guardar screenshots
    - Guardar logs relevantes
    - Calcular hash SHA-256 de todos los archivos de evidencia

---

## Limitaciones y Consideraciones

### Fortalezas del Sistema

✅ **Inmutabilidad criptográfica**: Cualquier modificación rompe la cadena de hashes

✅ **Algoritmo robusto**: SHA-256 es un estándar de la industria

✅ **Trazabilidad completa**: Captura contexto completo del envío

✅ **Verificación automatizada**: Validación rápida de integridad

✅ **Transparencia**: Código abierto y auditable

### Limitaciones Técnicas

⚠️ **Almacenamiento local**: Los datos están en la misma base de datos de WordPress

- Vulnerabilidad: Acceso directo a BD puede modificar registros y recalcular hashes
- Mitigación: Revisar logs de acceso a BD

⚠️ **Sin cifrado en reposo**: Los datos están en texto plano en la base de datos

- Vulnerabilidad: Acceso a BD expone todos los datos sensibles
- Mitigación: Cifrado a nivel de sistema de archivos o BD

⚠️ **JavaScript del cliente**: Los datos del navegador se capturan en el cliente

- Vulnerabilidad: Puede ser bloqueado, deshabilitado o manipulado
- Mitigación: Verificar presencia de `browser_data` en registros

⚠️ **Datos manipulables**: IP, User Agent, cookies pueden ser falsificados

- Vulnerabilidad: Un usuario técnico puede suplantar esta información
- Mitigación: Correlación con logs del servidor web

⚠️ **Sin timestamping externo**: No usa servicios de timestamping de terceros

- Vulnerabilidad: El timestamp es generado por el servidor, podría manipularse
- Mitigación: Comparar con logs externos (servidor web, firewall, etc.)

⚠️ **Sin firma digital externa**: No hay firma criptográfica de terceros

- Vulnerabilidad: El sistema es autocontenido
- Mitigación: Exportar y firmar digitalmente las exportaciones JSON periódicamente

### Vectores de Ataque Potenciales

**1. Acceso directo a base de datos:**

- Atacante con acceso a BD puede modificar registros y recalcular hashes
- **Detección**: Revisar logs de acceso a BD, comparar con backups antiguos

**2. Eliminación de registros intermedios:**

- Eliminar registros crea gaps en IDs
- **Detección**: Query SQL para detectar IDs no consecutivos

**3. Modificación de código del plugin:**

- Cambiar el algoritmo de hash o la función de verificación
- **Detección**: Comparar hashes SHA-256 de archivos PHP con originales

**4. Manipulación de timestamp del servidor:**

- Cambiar reloj del servidor para falsificar fechas
- **Detección**: Comparar con logs externos, verificar coherencia temporal

**5. Inyección de registros falsos:**

- Insertar registros recalculando toda la cadena subsecuente
- **Detección**: Muy difícil sin logs externos; verificar timestamps con logs de servidor web

### Recomendaciones de Seguridad Adicionales

Para aumentar la robustez del sistema:

1. **Backups automáticos periódicos** de la tabla `wp_contactform_audit`
2. **Logs de auditoría** de todos los accesos a la base de datos
3. **Firma digital externa** de exportaciones JSON periódicas
4. **Timestamping de terceros** mediante servicios como RFC 3161
5. **Cifrado de base de datos** a nivel de MySQL/MariaDB
6. **Monitorización de integridad de archivos** (AIDE, Tripwire, etc.)
7. **Restricción de acceso** a la base de datos mediante firewall
8. **2FA** en accesos a WordPress Admin y phpMyAdmin

---

## Preguntas Frecuentes

### ¿Es posible modificar registros sin que se detecte?

**Respuesta técnica:**

Sí, si el atacante tiene:

- Acceso directo a la base de datos
- Conocimiento del algoritmo de hash
- Capacidad de recalcular todos los hashes subsecuentes

**Sin embargo**, esto deja rastros:

- Logs de acceso a la base de datos (si están habilitados)
- Discrepancias con backups anteriores
- Cambios en timestamps de modificación de registros en BD (MySQL metadata)

### ¿Qué validez legal tienen estos registros?

**No podemos dar asesoramiento legal**, pero consideraciones técnicas:

- Los registros pueden ser **evidencia digital** en procedimientos
- La **cadena de custodia** debe mantenerse (documentar quién accede, cuándo, cómo)
- La **integridad verificable** es un punto fuerte
- La **ausencia de timestamping externo** puede ser un punto débil

**Recomendación**: Consultar con abogado especializado en derecho digital y evidencia electrónica.

### ¿Cómo puedo estar seguro de que el código no ha sido modificado?

Verificar hashes SHA-256 de archivos clave:

```bash
# En el servidor
cd wp-content/plugins/contactform7-audit/
sha256sum includes/class-hash-chain.php
sha256sum includes/class-form-handler.php
sha256sum admin/class-admin.php
```

Comparar con hashes oficiales del repositorio GitHub.

### ¿Qué pasa si se desinstala el plugin?

Depende de cómo se desinstale:

- **Desactivar**: Los datos permanecen intactos
- **Eliminar (con uninstall.php)**: Se eliminan TODOS los datos de la tabla

Siempre exportar datos antes de desinstalar.

### ¿Puede un usuario normal saltarse la captura de datos?

**Datos del servidor** (IP, timestamp): NO, se capturan server-side

**Datos del navegador** (cookies, resolución): SÍ, si:

- Tiene JavaScript deshabilitado
- Usa extensiones de bloqueo
- Manipula el formulario HTML

**Mitigación**: Verificar presencia de `browser_data` en cada registro. Su ausencia puede indicar bloqueo deliberado.

### ¿Cómo verifico la autenticidad de un consentimiento específico?

1. Localizar el registro en la base de datos o exportación JSON
2. Verificar el campo de aceptación:
   ```json
   "aceptacion-rgpd": {
       "text": "Acepto la política de privacidad...",
       "value": "1"
   }
   ```
3. `value: "1"` indica casilla marcada
4. `text` contiene el texto exacto que vio el usuario
5. Verificar integridad del hash de ese registro
6. Verificar continuidad de la cadena hasta ese punto

---

## Contacto Técnico

Para consultas técnicas sobre este sistema de auditoría:

**Autor**: Rubén García  
**LinkedIn**: [linkedin.com/in/ruben-garcia-4383853a](https://www.linkedin.com/in/ruben-garcia-4383853a)  
**GitHub**: [github.com/espunny/Plugin-Trazabilidad-formularios](https://github.com/espunny/Plugin-Trazabilidad-formularios)

---

## Historial de Versiones del Plugin

| Versión | Fecha      | Cambios Relevantes                       |
| ------- | ---------- | ---------------------------------------- |
| 1.0.0   | 2025-01-XX | Versión inicial con hash chaining básico |
| 1.0.1   | 2025-XX-XX | Mejoras en captura de browser data       |
| 1.0.2   | 2025-XX-XX | Versión actual con verificación mejorada |

**Nota**: Verificar siempre la versión del plugin instalada en el sistema auditado.

---

**Este documento fue creado el 16 de diciembre de 2025**

**Última actualización: 16 de diciembre de 2025**
