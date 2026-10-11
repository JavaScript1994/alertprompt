# CLAUDE.md — Plataforma de Comunicaciones Masivas (WhatsApp / SMS / Email)

Este archivo da contexto a Claude Code sobre el proyecto. Léelo completo antes de
escribir código. Si algo aquí contradice una instrucción puntual mía en el chat,
gana la instrucción del chat, pero avísame de la contradicción.

---

## 1. Qué es este proyecto

SaaS multi-tenant para que empresas peruanas envíen campañas de mensajería a sus
propias bases de contactos **con consentimiento**, por tres canales:

- **WhatsApp** vía Cloud API oficial de Meta (en demo: sandbox de Twilio)
- **SMS** vía Twilio
- **Email** vía SendGrid (migrar a Amazon SES cuando el volumen lo justifique)

Objetivo inmediato: **MVP demostrable en 7 días**. Priorizar el flujo end-to-end
funcionando sobre features secundarias.

### El flujo que vende la demo

```
CSV → contactos → segmento → plantilla con variables → envío por lotes → dashboard en vivo
```

Todo lo que no esté en ese camino es secundario.

---

## 2. Restricciones no negociables

### 2.1 Legal (Perú — Ley N° 32323, mayo 2025)

La ley modificó el Código de Protección y Defensa del Consumidor. Reglas que el
código DEBE reflejar:

- No se puede enviar publicidad sin **consentimiento previo, libre, informado,
  expreso e inequívoco**, obtenido por iniciativa del propio consumidor.
- El consentimiento es **revocable en cualquier momento, sin expresión de causa**,
  con efecto inmediato.
- Aplica igual a llamadas, SMS, WhatsApp y email.
- La infracción es falta **muy grave** ante Indecopi.

Consecuencias de diseño:

1. La tabla `consents` guarda **evidencia**: canal, fecha, origen, IP y el texto
   exacto que el contacto aceptó. No es opcional.
2. Toda campaña de categoría `marketing` filtra por consentimiento vigente.
3. Todo mensaje saliente lleva mecanismo de baja (botón en plantilla WA, palabra
   clave en SMS, header `List-Unsubscribe` en email).
4. **Nunca** implementes una feature que permita importar bases compradas y
   enviarles marketing. Si te lo pido, recuérdame esto.

### 2.2 WhatsApp Cloud API — límites reales

- Sin verificación de negocio: **250 mensajes iniciados / 24h**.
- Verificado y con buena calidad: escalones **1.000 → 10.000 → 100.000 → ilimitado**.
- Para subir de tier: sostener **80%+ del límite diario por 7 días consecutivos**
  manteniendo calidad **GREEN o YELLOW**.
- Throughput por defecto: **~80 msg/s** (no es el cuello de botella real).
- Fuera de la ventana de 24h todo mensaje requiere **plantilla (HSM) aprobada**.
- Meta aplica **portfolio pacing**: envía campañas grandes por lotes y pausa si
  detecta bloqueos/reportes. Nuestro pacing propio debe anticiparse a esto.

**Lo que tumba una cuenta no es el volumen, es la calidad.** El sistema debe
pausar campañas automáticamente ante señales negativas.

### 2.3 Prohibido

- Librerías no oficiales de WhatsApp (Baileys, whatsapp-web.js, venom, etc.).
- Rotación de números/proxies para evadir detección.
- Cualquier lógica cuyo propósito sea eludir rate limits o antispam de proveedores.

### 2.4 Autenticación de usuarios (MFA)

Aplica a los **usuarios del panel** (tabla `users`): el Administrador general y
los usuarios de cada cliente. Nunca a los contactos.

- **TOTP (app de autenticación) es el ÚNICO segundo factor del login rutinario.**
  No existe "elige tu método": no hay toggle ni pantalla de elección.
- El **correo de respaldo** no es un método elegible. Se configura y verifica
  con un código (una vez) y solo se usa desde "¿Perdiste tu dispositivo?",
  igual que los 8 **códigos de recuperación** (un solo uso, `Hash::make`).
- Obligatoriedad por permiso, no por nombre de rol (hay roles personalizados):
  quien tiene `users.manage` (administradores y el Administrador general)
  enrola en el primer login; el resto tiene **14 días** de gracia desde su
  primer login y al día 15 el middleware lo bloquea.
- Con MFA, la contraseña **no abre sesión**: `POST /api/login` devuelve un
  `challenge_token` (Crypt de Laravel, 5 min, ligado a usuario + IP, nonce de
  un solo uso en Redis) y la sesión nace en `/api/mfa/verify`.
- El MFA se exige **siempre en el middleware** (`mfa`, `mfa:enrollment`),
  nunca solo en el frontend. Ventana TOTP ±1 periodo y sin reutilizar un
  periodo ya aceptado.
- **Re-autenticación** (contraseña + TOTP, 15 min, por acción) con
  `reauth:{acción}`: exportar datos de contactos, configuración de
  proveedores, usuarios/roles, desactivar/restablecer MFA, regenerar códigos,
  cambiar de dispositivo o de correo de respaldo. **Revocar un consentimiento
  NUNCA pide re-autenticación**: la baja tiene efecto inmediato (§2.1).
- Todo uso o cambio de un respaldo, y toda desactivación o restablecimiento,
  queda en `auth_events` y se avisa por correo a quienes tienen `users.manage`
  en el tenant y al propio usuario.
- Quien pierde todo (teléfono, códigos y correo): un administrador de su
  cuenta restablece su MFA; para administradores de clientes, el
  Administrador general.
- Nunca en BD, logs ni `auth_events.metadata`: secreto TOTP (cast
  `encrypted`), códigos de respaldo, OTP del correo (HMAC-SHA256 +
  `hash_equals`) ni `challenge_token`. El correo con el OTP **no** va a la cola
  (el payload quedaría en Redis/`failed_jobs`).

---

## 3. Stack

```
Backend    Laravel 11 (PHP 8.3)
Frontend   React 18 + TypeScript + Vite + TailwindCSS
DB         PostgreSQL 16
Cache/Cola Redis 7 + Laravel Horizon
Auth       Laravel Sanctum (SPA cookie-based)
Testing    Pest
Local      Docker Compose
Deploy     Docker → (definir: Render / AWS ECS)
```

### Por qué Laravel y no Node

Horizon, `Bus::batch()`, `RateLimited` middleware en jobs, backoff exponencial y
reintentos vienen de fábrica. Es exactamente el 80% difícil de este proyecto.

---

## 4. Arquitectura

```
app/
├── Console/Commands/
├── Http/
│   ├── Controllers/Api/          # controladores delgados, sin lógica de negocio
│   ├── Middleware/
│   ├── Requests/                 # toda validación vive aquí
│   └── Resources/                # respuestas JSON tipadas
├── Jobs/
│   ├── DispatchCampaign.php      # arma el batch, aplica pacing
│   ├── SendMessageJob.php        # envía UN mensaje, maneja errores del proveedor
│   └── ImportContactsCsv.php
├── Models/
├── Services/
│   ├── Channels/
│   │   ├── ChannelDriver.php          # interface
│   │   ├── ChannelManager.php         # resuelve driver según config/channels.php
│   │   ├── WhatsApp/
│   │   │   ├── WhatsAppTwilioDriver.php   # demo — sandbox de Twilio
│   │   │   └── WhatsAppCloudDriver.php    # producción — Meta Cloud API directa
│   │   ├── Sms/
│   │   │   └── SmsTwilioDriver.php
│   │   └── Email/
│   │       ├── EmailSendGridDriver.php
│   │       └── EmailSesDriver.php         # cuando el volumen lo justifique
│   ├── SuppressionService.php
│   ├── TemplateRenderer.php      # variables {{nombre}}
│   └── CampaignPacer.php         # decide si liberar el siguiente lote
└── Support/
```

### Interfaz de canal

Todos los drivers implementan lo mismo. Agregar un proveedor nuevo (Infobip,
SES) no debe tocar los jobs.

```php
interface ChannelDriver
{
    public function send(OutboundMessage $message): SendResult;
    public function verifyWebhookSignature(Request $request): bool;
    public function parseWebhook(Request $request): array; // MessageStatusUpdate[]
}
```

`SendResult` trae: `providerMessageId`, `status`, `errorCode`, `shouldRetry`,
`shouldSuppress`.

**Regla dura:** ningún Job, Controller o componente React puede referirse a
Twilio, SendGrid o Meta por nombre. Solo `ChannelManager` conoce proveedores.
Si ves un `use Twilio\Rest\Client` fuera de `Services/Channels/`, es un bug.

### Selección de driver

```php
// config/channels.php
return [
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'twilio'),   // twilio | cloud
        'drivers' => [
            'twilio' => WhatsAppTwilioDriver::class,
            'cloud'  => WhatsAppCloudDriver::class,
        ],
    ],
    'sms' => [
        'driver'  => env('SMS_DRIVER', 'twilio'),
        'drivers' => ['twilio' => SmsTwilioDriver::class],
    ],
    'email' => [
        'driver'  => env('EMAIL_DRIVER', 'sendgrid'),
        'drivers' => [
            'sendgrid' => EmailSendGridDriver::class,
            'ses'      => EmailSesDriver::class,
        ],
    ],
];
```

Cambiar de proveedor = cambiar una variable de entorno. Nada más.

---

## 4-bis. Estrategia de proveedores

Decisión tomada y su razón, para que no se re-discuta en cada sesión.

### WhatsApp: Twilio ahora, Meta directo después

| | Demo (ahora) | Producción |
|---|---|---|
| Proveedor | Twilio WhatsApp Sandbox | Meta Cloud API directa |
| Setup | ~20 min, testers se unen con palabra clave | Business Manager + verificación de negocio (días/semanas) + registro de número + aprobación de plantillas |
| Costo | Meta por mensaje **+ markup de Twilio** | Solo Meta por mensaje |
| Telemetría | Filtrada por el BSP | Quality rating, tier y webhooks directos |

Twilio es un BSP: revende la misma Cloud API de Meta con un fee encima. A escala
de campañas masivas ese markup se come el margen. Además, integrando directo
recibimos las señales de calidad y tier sin intermediario — que es justo lo que
alimenta al `CampaignPacer`.

**El sandbox de Twilio existe solo para no bloquear la demo esperando a Meta.**
Arrancar la verificación de negocio en Meta en paralelo desde el día 1.

Facturación de Meta desde julio 2025: **por mensaje entregado**, con tarifa
según el par mercado–categoría (Perú-marketing ≠ Perú-utility). Si no se
entrega, no se cobra — por eso el estado de entrega ahora importa para costos,
no solo para métricas. La categoría de la plantilla es un campo de negocio, no
un detalle: guárdala en `templates.category` y repórtala en el dashboard de costos.

### SMS: Twilio para arrancar, evaluar local

No hay alternativa "directa" — se necesita un agregador. Twilio funciona, pero
para volumen peruano comparar contra **Infobip** o agregadores locales con
acuerdo directo con Claro/Movistar/Entel: el SMS internacional suele salir caro
frente a uno local. Requiere además registrar sender ID alfanumérico o shortcode
con las operadoras (trámite de semanas — iniciarlo temprano).

### Email: SendGrid → SES

SendGrid hasta ~100k emails/mes. Arriba de eso, Amazon SES cuesta una fracción.
Al migrar: IP dedicada con warm-up progresivo, y SPF + DKIM + DMARC verificados
antes del primer envío.

### Cómo evaluar un proveedor nuevo

Antes de proponer un cambio, contrastar: costo por mensaje al volumen objetivo,
soporte de webhooks de estado, cobertura real en Perú, y si expone o no las
señales de calidad. Un proveedor más barato que oculta la telemetría de calidad
no es más barato.

---

## 5. Esquema de base de datos

```
tenants               id, name, plan, settings(jsonb), created_at
users                 id, tenant_id, name (= nombres + apellidos), email, pending_email,
                      password, deactivated_at,
                      first_name, last_name, job_title, birth_date, phone, mobile,
                      photo_path (disco privado; se sirve por la API dentro del tenant)
                      — fecha de nacimiento, teléfonos, cargo y foto: opcionales (Ley 29733),
                      two_factor_secret (encrypted), two_factor_pending_secret (encrypted),
                      two_factor_recovery_codes (encrypted: lista de hashes),
                      two_factor_confirmed_at, two_factor_email_backup,
                      two_factor_email_verified_at, two_factor_grace_ends_at,
                      last_login_at, last_login_ip
                      (roles y permisos: spatie/laravel-permission con teams = tenant)
contacts              id, tenant_id, phone, email, name, attributes(jsonb)
                      UNIQUE(tenant_id, phone), UNIQUE(tenant_id, email)
consents              id, contact_id, channel, granted_at, revoked_at,
                      source, ip, evidence_text
suppressions          id, tenant_id, channel, identifier, reason, created_at
                      UNIQUE(tenant_id, channel, identifier)      ← se consulta por mensaje
templates             id, tenant_id, channel, category, name, body,
                      variables(jsonb), provider_template_id, status
campaigns             id, tenant_id, template_id, channel, name, status,
                      batch_id, scheduled_at, stats(jsonb)
campaign_recipients   id, campaign_id, contact_id, status, provider_message_id,
                      error_code, sent_at, delivered_at
                      UNIQUE(campaign_id, contact_id)             ← idempotencia
                      INDEX(provider_message_id)                  ← lo usa el webhook
message_events        id, campaign_recipient_id, event, payload(jsonb), occurred_at
                      (particionar por mes cuando crezca)
auth_events           id, user_id, tenant_id, event, ip, user_agent, metadata(jsonb), occurred_at
                      INDEX(user_id, occurred_at), INDEX(tenant_id, event, occurred_at)
sensitive_action_confirmations
                      id, user_id, action, confirmed_at, expires_at, ip
email_backup_otps     id, user_id, purpose(setup|login), email, code_hash(HMAC),
                      attempts, expires_at, used_at, requested_at, ip
                      INDEX(user_id, expires_at)
```

### Detalles que importan

- `UNIQUE(campaign_id, contact_id)` permite reanudar una campaña caída sin duplicar.
- `INDEX(provider_message_id)` evita full scan en cada webhook. Sin él la demo
  se cae con 500 mensajes.
- Estados de `campaign_recipients`: `pending → queued → sent → delivered → read`
  con ramas `failed` y `skipped` (con motivo: `no_consent`, `suppressed`,
  `invalid_number`).
- Estados de `campaigns`: `draft → scheduled → running → paused → completed`
  y `cancelled`. `paused` puede ser automático (pacer).

---

## 6. Reglas del motor de envío

### 6.1 Pacing, no sleep fijo

**No** uses `sleep(15)` entre mensajes. El pacing es por lotes con feedback:

1. `DispatchCampaign` divide destinatarios en lotes (default 500).
2. Encola el lote con `Bus::batch()`.
3. Al completar el lote, `CampaignPacer` evalúa métricas del webhook.
4. Si `(failed + blocked + reported) / sent > umbral` (default 3%), **pausa la
   campaña** y notifica. Si no, libera el siguiente lote.

Rate limit real por canal con `Illuminate\Queue\Middleware\RateLimited`:
- WhatsApp: 60 msg/s por número (margen bajo el techo de 80).
- SMS: según plan de Twilio.
- Email: según sending rate de SendGrid/SES.

### 6.2 Chequeo de supresión en el worker

`SuppressionService::isSuppressed()` se consulta **dentro de `SendMessageJob`**,
justo antes de enviar — nunca solo al armar la campaña. Entre encolar y enviar
alguien pudo darse de baja.

### 6.3 Política de reintentos por código de error

Reintentar lo que no se debe consume cuota, genera señales de spam y duplica
mensajes al destinatario. Mapeo obligatorio:

| Código                | Acción                                          |
|-----------------------|-------------------------------------------------|
| HTTP 429              | reintentar con backoff exponencial              |
| 131048 (spam rate)    | loguear, reprogramar tras medianoche UTC        |
| 131049 (frecuencia)   | loguear, **omitir 48h**, no reintentar          |
| 131016 (servicio)     | reintentar una vez tras 30–60s                  |
| 131031 (cuenta)       | **detener toda la campaña**, alertar            |
| 5xx genérico          | hasta 3 reintentos con backoff                  |
| número inválido       | marcar `failed`, suprimir, no reintentar        |

`SendMessageJob`: `$tries = 3`, `backoff = [30, 120, 600]`, `failOnTimeout`.

### 6.4 Webhooks

- Verificar **firma** siempre (`X-Twilio-Signature`, verificación de SendGrid,
  `hub.verify_token` de Meta). Sin firma válida → 403.
- Idempotentes: el mismo evento puede llegar dos veces.
- Responder 200 rápido; procesar en cola si hace falta.
- Un `bounce` duro o un `unsubscribe` escribe en `suppressions` automáticamente.

---

## 7. Convenciones de código

### PHP / Laravel

- `declare(strict_types=1);` en todos los archivos.
- Controladores delgados. Lógica en Services o Actions.
- Validación en Form Requests, nunca inline en el controlador.
- Respuestas vía API Resources, nunca `->toArray()` crudo del modelo.
- Global scope de tenant en todos los modelos con `tenant_id`. **Toda query debe
  estar aislada por tenant** — si escribes una consulta sin scope, justifícalo.
- Enums de PHP 8 para estados, no strings sueltos.
- Migraciones: una por cambio, nunca editar una ya corrida.

### TypeScript / React

- Tipos explícitos en props y respuestas de API. Nada de `any`.
- Componentes funcionales con hooks.
- Estado de servidor con TanStack Query; nada de `useEffect` + `fetch` a mano.
- Tailwind con clases utilitarias; sin CSS suelto salvo necesidad real.
- Formularios: react-hook-form + zod.

### Tests

- Pest. Feature tests para cada endpoint.
- Cobertura obligatoria en: chequeo de consentimiento, chequeo de supresión,
  idempotencia de webhooks, mapeo de códigos de error.
- Los drivers de canal se mockean; nunca golpear APIs reales en tests.

### Git

- Conventional commits: `feat:`, `fix:`, `refactor:`, `test:`, `chore:`.
- Rama por feature: `feat/campaign-pacing`.

---

## 8. Comandos

```bash
# Levantar entorno
docker compose up -d
php artisan migrate:fresh --seed
php artisan horizon
npm run dev

# Calidad — correr antes de cada commit
./vendor/bin/pint            # formato PHP
./vendor/bin/pest            # tests
npm run typecheck
npm run lint

# Utilidades
php artisan queue:failed
php artisan horizon:status
php artisan campaign:dispatch {id}      # comando propio, disparo manual
```

---

## 9. Variables de entorno

```env
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=comms
DB_USERNAME=comms
DB_PASSWORD=secret

REDIS_HOST=localhost
QUEUE_CONNECTION=redis

# Selección de driver por canal
WHATSAPP_DRIVER=twilio        # twilio (demo) | cloud (producción)
SMS_DRIVER=twilio
EMAIL_DRIVER=sendgrid         # sendgrid | ses

# Twilio (SMS + WhatsApp sandbox en demo)
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_SMS_FROM=
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886

# Meta Cloud API (producción)
META_PHONE_NUMBER_ID=
META_WABA_ID=
META_ACCESS_TOKEN=
META_WEBHOOK_VERIFY_TOKEN=

# SendGrid
SENDGRID_API_KEY=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=

# Pacing
CAMPAIGN_BATCH_SIZE=500
CAMPAIGN_FAILURE_THRESHOLD=0.03
```

Nunca comitear `.env`. Mantener `.env.example` sincronizado.

---

## 10. Roadmap del MVP (7 días)

| Día | Entregable |
|-----|-----------|
| 1 | Docker + migraciones completas + seeders (200 contactos falsos) |
| 2 | Auth Sanctum + layout React + global scope de tenant |
| 3 | Contactos: listado paginado + import CSV con `LazyCollection` |
| 4 | Plantillas: CRUD + parser de variables + preview |
| 5 | **Core**: `ChannelDriver` + `ChannelManager` + drivers + `SendMessageJob` + `Bus::batch()` |
| 6 | Webhooks con verificación de firma + supresión automática |
| 7 | Dashboard con polling 3s + pulido |

El día 5 es el único con riesgo técnico real. El resto es CRUD conocido.

### Fuera de alcance del MVP

`WhatsAppCloudDriver` completo y aprobación real de plantillas en Meta (tarda
días — en el MVP basta con la interfaz y el driver de Twilio implementado, más
el esqueleto del driver de Cloud con sus métodos lanzando `NotImplemented`),
`EmailSesDriver`, etiquetas de contactos,
campos personalizados avanzados, editor HTML drag-and-drop de email,
A/B testing, automatizaciones/flows, facturación.

---

## 11. Cómo quiero que trabajes

- Código **production-ready** con rutas de archivo exactas, no pseudocódigo ni
  resúmenes de alto nivel.
- Si una tarea es grande, propón el desglose antes de escribir 800 líneas.
- Cuando toques el motor de envío, escribe el test junto con el código.
- Si detectas que algo que pido rompe una restricción de la sección 2, dilo
  antes de implementarlo.
- Si una decisión tiene dos caminos razonables, plantéalos con el trade-off en
  vez de elegir en silencio.
