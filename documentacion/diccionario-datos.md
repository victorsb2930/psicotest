# Diccionario de Datos — Psicoguia

Este documento describe el modelo lógico y físico de datos del aplicativo, sus entidades, relaciones, claves y restricciones. Está generado a partir de las migraciones y modelos Eloquent del proyecto a fecha de hoy.

- Motor de BD (dev): PostgreSQL (Docker). Compatible con MySQL si se ajustan índices parciales y collations.
- Notación de tipos: se indican tipos genéricos (según migraciones). En PostgreSQL, `timestamp` = `timestamp without time zone`.
- Convenciones: claves primarias `id` autoincrementales (bigint o int según tabla); claves foráneas a `users.id` salvo que se indique.

## Modelo lógico (alto nivel)

Entidades principales y propósito:
- Usuario (users): identidad de pacientes y profesionales; presencia, estado, datos de perfil y seguridad.
- Rol/Permiso (Spatie): autorización basada en roles y permisos.
- Solicitud profesional (professional_applications): flujo de alta/validación del profesional (documentos y revisión).
- Citas (appointments): agenda entre profesional y paciente, con estados de aceptación.
- Mensajes (messages): mensajería 1:1 entre usuarios; lectura/no leídos.
- Amistades (friend_requests): modelo dirigido de solicitud/aceptación de amistad.
- Sesiones de usuario (user_logins): auditoría de sesiones con control de sesión abierta por navegador.
- Dispositivos (user_devices): gestión de dispositivos recordados con token hash.
- Reapertura de sesión (device_reopen_attempts / device_reopen_blocks): intentos y bloqueos de re-apertura 2FA por dispositivo.
- Fotos de usuario (user_photos): foto de perfil y galería.
- Infraestructura (jobs, cache, sessions, password_reset_tokens, failed_jobs, job_batches): soporte framework.

Relaciones clave (cardinalidades):
- User 1—N Appointment (como profesional) y 1—N Appointment (como paciente).
- User 1—N Message (enviados) y 1—N Message (recibidos).
- User N—N User vía FriendRequest (simétrica por pares; cuando status=accepted implica amistad).
- User 1—N UserDevice, 1—N UserLogin, 1—N DeviceReopenAttempt, 1—N DeviceReopenBlock.
- User 1—N UserPhoto; máximo una con is_profile=true como foto de perfil.
- User 1—N ProfessionalApplication (en la práctica 1—1 activa); y 1—N como reviewer via reviewed_by.
- Autorización: User N—N Role; Role N—N Permission; User N—N Permission (vía pivotes Spatie).

### Diagrama ER (Mermaid)

```mermaid
erDiagram
  USERS ||--o{ USER_PHOTOS : has
  USERS ||--o{ APPOINTMENTS : professional
  USERS ||--o{ APPOINTMENTS : patient
  USERS ||--o{ MESSAGES : sent
  USERS ||--o{ MESSAGES : received
  USERS ||--o{ FRIEND_REQUESTS : from
  USERS ||--o{ FRIEND_REQUESTS : to
  USERS ||--o{ USER_DEVICES : has
  USERS ||--o{ USER_LOGINS : has
  USERS ||--o{ DEVICE_REOPEN_ATTEMPTS : has
  USERS ||--o{ DEVICE_REOPEN_BLOCKS : has
  USERS ||--o{ PROFESSIONAL_APPLICATIONS : author
  USERS ||--o{ PROFESSIONAL_APPLICATIONS : reviewer

  ROLES ||--o{ MODEL_HAS_ROLES : via
  PERMISSIONS ||--o{ MODEL_HAS_PERMISSIONS : via
  ROLES ||--o{ ROLE_HAS_PERMISSIONS : grants

  USERS {
    bigint id PK
    varchar name
    varchar email UNIQUE
    varchar phone NULL
    varchar timezone NULL
    varchar password
    varchar specialty NULL
    json appointment_types NULL
    varchar location NULL
    numeric rating(3,1) NULL
    varchar status DEFAULT "online"
    timestamp email_verified_at NULL
    timestamp last_seen_at NULL
    rememberToken
    timestamps
    softDeletes
  }

  APPOINTMENTS {
    bigint id PK
    bigint professional_id FK -> users.id
    bigint patient_id FK -> users.id
    varchar title NULL
    timestamp start NULL
    timestamp end NULL
    boolean all_day DEFAULT false
    enum status ['pending','accepted','rejected','cancelled'] DEFAULT 'pending'
    text notes NULL
    timestamps
    softDeletes
  }

  MESSAGES {
    bigint id PK
    bigint from_id FK -> users.id
    bigint to_id FK -> users.id
    text body
    timestamp read_at NULL
    timestamps
  }

  FRIEND_REQUESTS {
    bigint id PK
    bigint from_id FK -> users.id
    bigint to_id FK -> users.id
    enum status ['pending','accepted','rejected'] DEFAULT 'pending'
    timestamp accepted_at NULL
    timestamp rejected_at NULL
    timestamps
    UNIQUE (from_id, to_id)
  }

  PROFESSIONAL_APPLICATIONS {
    bigint id PK
    bigint user_id FK -> users.id
    varchar titulo_path NULL
    varchar cedula_path NULL
    enum status ['pending','approved','rejected'] DEFAULT 'pending'
    text notes NULL
    bigint reviewed_by NULL FK -> users.id
    timestamp reviewed_at NULL
    timestamps
  }

  USER_DEVICES {
    bigint id PK
    bigint user_id FK -> users.id
    varchar token_hash(64)
    varchar name NULL
    varchar ip_address NULL
    text user_agent NULL
    timestamp last_seen_at NULL
    timestamp revoked_at NULL
    timestamps
  }

  USER_LOGINS {
    bigint id PK
    bigint user_id
    varchar session_id NULL
    varchar ip_address NULL
    text user_agent NULL
    varchar browser_token_hash(64) NULL
    timestamp started_at NULL
    timestamp ended_at NULL
    int duration_seconds NULL
    timestamps
  }

  DEVICE_REOPEN_ATTEMPTS {
    bigint id PK
    bigint user_id
    varchar token_hash(64) NULL
    varchar ip_address(45) NULL
    text user_agent NULL
    boolean success NULL
    varchar action DEFAULT 'confirm'
    timestamps
  }

  DEVICE_REOPEN_BLOCKS {
    bigint id PK
    bigint user_id
    varchar token_hash(64) NULL
    timestamp blocked_until NULL
    boolean permanent DEFAULT false
    bigint admin_unlocked_by NULL
    timestamp admin_unlocked_at NULL
    timestamps
  }

  USER_PHOTOS {
    bigint id PK
    bigint user_id FK -> users.id
    varchar path NULL
    text caption NULL
    boolean is_profile DEFAULT false
    timestamps
  }
```

Notas:
- Los canales privados de broadcasting no persisten en BD (definidos en `routes/channels.php`).
- La tabla `notifications` no está en las migraciones del repo; si se añade, se integra con `users` (morph) de forma estándar de Laravel.

## Diccionario físico por tabla

A continuación, cada tabla con columnas, tipos, nulos, default, PK, FK e índices relevantes.

### users
- id: bigint, PK
- name: varchar(255), not null
- email: varchar(255), unique, not null (normalizado a minúsculas por código)
- phone: varchar(32), null, INDEX
- timezone: varchar(255), null
- specialty: varchar(255), null
- appointment_types: json, null (array tipado en modelo)
- location: varchar(255), null
- rating: numeric(3,1), null
- email_verified_at: timestamp, null
- password: varchar(255), not null (hash)
- is_active: boolean default true
- deactivated_reason: text, null
- deactivated_at: timestamp, null
- status: varchar(32) default 'online', INDEX
- last_seen_at: timestamp, null, INDEX
- remember_token: varchar(100), null
- deleted_at: timestamp, null (soft delete)
- created_at/updated_at: timestamp

Índices:
- UNIQUE (email)
- INDEX (status), INDEX (last_seen_at), INDEX (phone)

### password_reset_tokens
- email: varchar, PK
- token: varchar, not null
- created_at: timestamp, null

### sessions (Laravel)
- id: varchar, PK
- user_id: bigint, null, INDEX
- ip_address: varchar(45), null
- user_agent: text, null
- payload: longtext
- last_activity: integer, INDEX

### user_photos
- id: bigint, PK
- user_id: bigint, FK -> users.id ON DELETE CASCADE, INDEX
- path: varchar(255), null
- caption: varchar(255), null
- is_profile: boolean default false, INDEX
- created_at/updated_at

### professional_applications
- id: bigint, PK
- user_id: bigint, FK -> users.id ON DELETE CASCADE
- titulo_path: varchar(255), null
- cedula_path: varchar(255), null
- status: enum('pending','approved','rejected') default 'pending'
- notes: text, null
- reviewed_by: bigint, FK -> users.id NULL ON DELETE SET NULL
- reviewed_at: timestamp, null
- created_at/updated_at

Índices: FK implícitas.

### appointments
- id: bigint, PK
- professional_id: bigint, FK -> users.id ON DELETE CASCADE
- patient_id: bigint, FK -> users.id ON DELETE CASCADE
- title: varchar(255), null
- start: timestamp, null
- end: timestamp, null
- all_day: boolean default false
- status: enum('pending','accepted','rejected','cancelled') default 'pending'
- notes: text, null
- created_at/updated_at
- deleted_at: timestamp, null (soft delete)

### messages
- id: bigint, PK
- from_id: bigint, FK -> users.id ON DELETE CASCADE
- to_id: bigint, FK -> users.id ON DELETE CASCADE
- body: text, not null
- read_at: timestamp, null
- created_at/updated_at

Índices:
- INDEX (from_id, to_id)

### friend_requests
- id: bigint, PK
- from_id: bigint, FK -> users.id ON DELETE CASCADE
- to_id: bigint, FK -> users.id ON DELETE CASCADE
- status: enum('pending','accepted','rejected') default 'pending'
- accepted_at: timestamp, null
- rejected_at: timestamp, null
- created_at/updated_at

Restricciones/Índices:
- UNIQUE (from_id, to_id)

### user_logins
- id: bigint, PK
- user_id: bigint, INDEX
- session_id: varchar(255), null, INDEX
- ip_address: varchar(255), null
- user_agent: text, null
- browser_token_hash: varchar(64), null, INDEX
- started_at: timestamp, null
- ended_at: timestamp, null
- duration_seconds: integer unsigned, null
- created_at/updated_at

Índices y reglas:
- PostgreSQL: índice único parcial `user_logins_unique_open_session` en (user_id, session_id) WHERE ended_at IS NULL.

### user_devices
- id: bigint, PK
- user_id: bigint, FK -> users.id ON DELETE CASCADE, INDEX
- token_hash: varchar(64), INDEX
- name: varchar(255), null
- ip_address: varchar(255), null
- user_agent: text, null
- last_seen_at: timestamp, null
- revoked_at: timestamp, null
- created_at/updated_at

### device_reopen_attempts
- id: bigint, PK
- user_id: bigint, INDEX
- token_hash: varchar(64), null, INDEX
- ip_address: varchar(45), null
- user_agent: text, null
- success: boolean, null
- action: varchar(255) default 'confirm'
- created_at/updated_at

### device_reopen_blocks
- id: bigint, PK
- user_id: bigint, INDEX
- token_hash: varchar(64), null, INDEX
- blocked_until: timestamp, null, INDEX
- permanent: boolean default false
- admin_unlocked_by: bigint, null (referencia lógica a users.id; sin FK explícita)
- admin_unlocked_at: timestamp, null
- created_at/updated_at

### Spatie Permission (autorización)
Tablas: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

roles
- id: bigint, PK
- team_id: bigint null (no usado si teams=false)
- name: varchar, not null
- guard_name: varchar, not null
- show_in_signup: boolean default false
- signup_label: varchar null
- requires_docs: boolean default false
- icon_class: varchar null
- badge_color: varchar null
- timestamps

Índices:
- UNIQUE (name, guard_name) [o con team_id si teams=true]

permissions
- id: bigint, PK
- name, guard_name, timestamps; UNIQUE (name, guard_name)

model_has_roles
- role_id: bigint FK -> roles.id
- model_type: string
- model_id: bigint
- team_id: bigint null si teams=true
- PK compuesta: (role_id, model_id, model_type[, team_id])

model_has_permissions
- permission_id: bigint FK -> permissions.id
- model_type, model_id, team_id (si aplica)
- PK compuesta: (permission_id, model_id, model_type[, team_id])

role_has_permissions
- permission_id: bigint FK -> permissions.id
- role_id: bigint FK -> roles.id
- PK compuesta: (permission_id, role_id)

### Infraestructura Laravel
- cache, cache_locks: claves/valores y locks.
- jobs, job_batches, failed_jobs: colas de trabajos.
- password_reset_tokens, sessions: seguridad y sesiones.

## Reglas de negocio relevantes
- Mensajes sólo se pueden enviar entre usuarios con amistad aceptada (enfoque aplicado en el controlador).
- Amistad se modela por `friend_requests` con par único (from_id,to_id); la amistad efectiva es status=accepted (se trata como relación no dirigida a efectos de UI y permisos).
- `user_logins`: control de “sesión abierta” por navegador mediante `browser_token_hash` y único parcial en Postgres.
- `user_devices`: token hash por dispositivo; revocación blanda por `revoked_at`.
- `professional_applications`: revisión por un usuario revisor (nullable) con marcas de tiempo.

## Notas de implementación y mantenimiento
- Emails de usuario se normalizan a minúsculas en migración y en el mutator del modelo (`setEmailAttribute`).
- Soft deletes en `users` y `appointments` (cuidado con consultas que deban incluir borrados).
- Si se añade la tabla `notifications` de Laravel, documentarla como: morph a notifiable, `type`, `data (json)`, `read_at`.
- Índices parciales (PostgreSQL) no se trasladan automáticamente a MySQL; si se usa MySQL, considerar un índice único condicional equivalente o manejarlo en aplicación.

## Cómo generar/actualizar este documento
- Fuente de verdad: migraciones en `database/migrations` y modelos en `app/Models`.
- Al crear una nueva migración, añadir la tabla en la sección “Diccionario físico” y actualizar el ER.
- Se puede validar con `php artisan schema:dump` o herramientas como dbdocs.io/dbdiagram.io si se desea exportar.

---
Última actualización: automatizada desde el repo a fecha de commit.
