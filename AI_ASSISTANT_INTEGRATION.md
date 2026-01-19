# Integración AI Assistant con GPS Courses - Sold Out y Waitlist

## Contexto

El plugin GPS Courses tiene funcionalidad de **Sold Out manual** y **Waitlist mejorada**. El AI Assistant del plugin de carritos abandonados puede usar la REST API o funciones PHP para identificar cuando un curso está sold out y guiar al usuario apropiadamente.

---

## OPCION 1: REST API (Recomendado para AI Assistant)

La forma más fácil de verificar disponibilidad es usando los endpoints REST públicos.

### Verificar Disponibilidad de un Evento/Curso

```
GET /wp-json/gps-courses/v1/availability/event/{event_id}
```

**Respuesta de ejemplo (curso disponible):**
```json
{
  "success": true,
  "event": {
    "id": 123,
    "title": "Implant Fundamentals Course",
    "url": "https://gpsdentaltraining.com/event/implant-fundamentals/",
    "start_date": "2025-03-15",
    "start_date_formatted": "March 15, 2025"
  },
  "availability": {
    "is_available": true,
    "is_sold_out": false,
    "has_active_tickets": true,
    "reason": "available"
  },
  "tickets": [
    {
      "id": 456,
      "name": "Early Bird",
      "price": 1500,
      "is_sold_out": false,
      "is_manual_sold_out": false,
      "stock": {
        "total": 20,
        "sold": 12,
        "available": 8,
        "unlimited": false
      }
    }
  ],
  "waitlist_enabled": false
}
```

**Respuesta de ejemplo (curso SOLD OUT):**
```json
{
  "success": true,
  "event": {
    "id": 123,
    "title": "Implant Fundamentals Course",
    "url": "https://gpsdentaltraining.com/event/implant-fundamentals/",
    "start_date": "2025-03-15",
    "start_date_formatted": "March 15, 2025"
  },
  "availability": {
    "is_available": false,
    "is_sold_out": true,
    "has_active_tickets": true,
    "reason": "sold_out"
  },
  "tickets": [
    {
      "id": 456,
      "name": "General Admission",
      "price": 1800,
      "is_sold_out": true,
      "is_manual_sold_out": true,
      "stock": {
        "total": 12,
        "sold": 7,
        "available": 5,
        "unlimited": false
      }
    }
  ],
  "waitlist_enabled": true
}
```

**Campos clave para el AI:**
| Campo | Descripción |
|-------|-------------|
| `availability.is_sold_out` | **TRUE** = curso no disponible, ofrecer waitlist |
| `availability.is_available` | **TRUE** = curso disponible para compra |
| `availability.reason` | `available`, `sold_out`, `manual_override`, o `no_tickets` |
| `tickets[].is_manual_sold_out` | **TRUE** = admin marcó como sold out aunque hay stock |
| `waitlist_enabled` | **TRUE** = el usuario puede unirse a la waitlist |

### Verificar Disponibilidad de un Ticket Específico

```
GET /wp-json/gps-courses/v1/availability/ticket/{ticket_id}
```

**Respuesta:**
```json
{
  "success": true,
  "ticket": {
    "id": 456,
    "name": "Early Bird",
    "price": 1500,
    "status": "active"
  },
  "event": {
    "id": 123,
    "title": "Implant Fundamentals Course",
    "url": "https://gpsdentaltraining.com/event/implant-fundamentals/"
  },
  "availability": {
    "is_sold_out": true,
    "is_manual_sold_out": true,
    "stock": {
      "total": 12,
      "sold": 7,
      "available": 5,
      "unlimited": false
    },
    "reason": "manual_override"
  },
  "waitlist_enabled": true
}
```

### Agregar Usuario a Waitlist

```
POST /wp-json/gps-courses/v1/waitlist/add
Content-Type: application/json

{
  "ticket_id": 456,
  "event_id": 123,
  "email": "user@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "555-123-4567"
}
```

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Successfully added to waitlist",
  "data": {
    "waitlist_id": 789,
    "position": 3,
    "email": "user@example.com",
    "event": "Implant Fundamentals Course",
    "ticket": "Early Bird"
  }
}
```

**Respuesta si ya está en waitlist:**
```json
{
  "success": false,
  "error": "You're already on the waitlist for this ticket!",
  "error_code": "already_on_waitlist"
}
```

### Verificar si Email está en Waitlist

```
GET /wp-json/gps-courses/v1/waitlist/check?email=user@example.com&event_id=123
```

**Respuesta:**
```json
{
  "success": true,
  "email": "user@example.com",
  "on_waitlist": true,
  "entries": [
    {
      "id": 789,
      "event_id": 123,
      "event_title": "Implant Fundamentals Course",
      "ticket_id": 456,
      "ticket_title": "Early Bird",
      "position": 3,
      "status": "waiting",
      "created_at": "2025-01-15 10:30:00"
    }
  ],
  "count": 1
}
```

---

## OPCION 2: Funciones PHP (Para integración directa en código)

### Verificar Disponibilidad de Curso

```php
// Función simple que retorna toda la info necesaria
$availability = \GPSC\gps_check_course_availability($event_id);

// $availability contiene:
// [
//     'is_sold_out' => bool,      // TRUE si TODOS los tickets están sold out
//     'is_available' => bool,     // TRUE si hay tickets disponibles
//     'reason' => string,         // 'available', 'sold_out', 'manual_override', 'no_tickets'
//     'tickets' => array,         // Info detallada de cada ticket
//     'message' => string,        // Mensaje legible para el usuario
// ]

if ($availability['is_sold_out']) {
    // Ofrecer waitlist
    echo $availability['message'];
    // "Implant Course is currently sold out. Customers can join the waitlist..."
}
```

### Verificar Disponibilidad de Ticket

```php
$ticket_status = \GPSC\gps_check_ticket_availability($ticket_id);

// $ticket_status contiene:
// [
//     'is_sold_out' => bool,
//     'is_manual' => bool,        // TRUE si fue marcado manualmente
//     'available' => int,         // Cantidad disponible (-1 si unlimited)
//     'reason' => string,         // 'available', 'stock_depleted', 'manual_override'
// ]
```

### Métodos de Clase Directos

```php
// Verificar si GPS Courses está activo
if (class_exists('\GPSC\Tickets')) {
    // Verificar si ticket está sold out (manual O por stock)
    $is_sold_out = \GPSC\Tickets::is_sold_out($ticket_id);

    // Verificar si es sold out MANUAL específicamente
    $is_manual = \GPSC\Tickets::is_manually_sold_out($ticket_id);

    // Obtener info de stock
    $stock = \GPSC\Tickets::get_ticket_stock($ticket_id);
    // Retorna: ['total' => int, 'sold' => int, 'available' => int, 'unlimited' => bool]
}
```

### Agregar a Waitlist via PHP

```php
if (class_exists('\GPSC\Waitlist')) {
    $result = \GPSC\Waitlist::add_to_waitlist(
        $ticket_id,      // ID del tipo de ticket
        $event_id,       // ID del evento
        $email,          // Email del usuario (requerido)
        $first_name,     // Nombre (opcional)
        $last_name,      // Apellido (opcional)
        $phone,          // Teléfono (opcional)
        $user_id         // ID de usuario WP si está logueado (opcional, 0 si no)
    );

    if (is_wp_error($result)) {
        echo $result->get_error_message();
    } else {
        echo "Added to waitlist at position #" . $result['position'];
    }
}
```

---

## Lógica Recomendada para el AI Assistant

### Flujo de Decisión

```
1. Usuario menciona un curso o tiene carrito abandonado con un curso

2. AI hace llamada a:
   GET /wp-json/gps-courses/v1/availability/event/{event_id}

3. Verifica la respuesta:

   SI availability.is_available = true:
      → "Great news! Tickets are available for [Course Name].
         Would you like me to help you complete your registration?"

   SI availability.is_sold_out = true:
      → Verificar si ya está en waitlist:
         GET /wp-json/gps-courses/v1/waitlist/check?email={user_email}&event_id={event_id}

      SI on_waitlist = true:
         → "You're already on the waitlist for [Course Name] at position #{X}.
            We'll notify you as soon as a spot opens up!"

      SI on_waitlist = false:
         → "I see that [Course Name] is currently sold out. However, you can join
            our waitlist and we'll notify you immediately if a spot becomes available.
            Would you like me to add you? I'll just need your name and email."

4. SI el usuario quiere unirse a waitlist:
   POST /wp-json/gps-courses/v1/waitlist/add
   {
     "ticket_id": {first_ticket_id},
     "event_id": {event_id},
     "email": "{user_email}",
     "first_name": "{user_name}",
     ...
   }

   → "Perfect! You've been added to the waitlist at position #{position}.
      You'll receive a confirmation email shortly. When a spot opens up,
      you'll have 48 hours to complete your purchase."
```

### Respuestas Sugeridas

**Si el curso está disponible:**
> "Great news! Tickets are available for [Course Name]. Would you like me to help you complete your registration?"

**Si el curso está sold out:**
> "I see that [Course Name] is currently sold out. However, you can join our waitlist and we'll notify you immediately if a spot becomes available. Would you like me to add you to the waitlist? I'll just need your name and email."

**Si ya está en waitlist:**
> "You're already on the waitlist for [Course Name] at position #[X]. We'll notify you as soon as a spot opens up!"

---

## Información de Base de Datos

### Meta Field para Sold Out Manual

- **Post Type:** `gps_ticket`
- **Meta Key:** `_gps_manual_sold_out`
- **Valores:** `'1'` (sold out) o `''`/`'0'` (disponible)

### Tabla: wp_gps_waitlist

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | BIGINT | ID único |
| user_id | BIGINT | ID de usuario WP (NULL si guest) |
| email | VARCHAR(255) | Email del interesado |
| first_name | VARCHAR(100) | Nombre |
| last_name | VARCHAR(100) | Apellido |
| phone | VARCHAR(50) | Teléfono |
| ticket_type_id | BIGINT | ID del ticket (post type gps_ticket) |
| event_id | BIGINT | ID del evento (post type gps_event) |
| position | INT | Posición en la cola |
| status | VARCHAR(20) | waiting, notified, converted, expired, removed |
| created_at | DATETIME | Fecha de registro |
| notified_at | DATETIME | Fecha de notificación |
| expires_at | DATETIME | Fecha de expiración (48h después de notificación) |

---

## Estados de Waitlist

| Estado | Descripción |
|--------|-------------|
| `waiting` | En espera de que se abra un lugar |
| `notified` | Se le notificó que hay lugar disponible (tiene 48h) |
| `converted` | Completó la compra exitosamente |
| `expired` | No compró dentro de las 48h |
| `removed` | Removido manualmente por admin |

---

## Notas Importantes

1. **Sold Out Manual vs Stock:** Un ticket puede estar sold out por:
   - `manual_override`: Admin lo marcó manualmente (aún puede haber stock real)
   - `stock_depleted`: El stock se agotó naturalmente

2. **Expiración de Notificaciones:** Cuando se notifica a alguien, tienen 48 horas para comprar.

3. **Posiciones en Cola:** Se reordenan automáticamente cuando alguien convierte o es removido.

4. **URL del Curso:** Siempre incluir el enlace para que el usuario pueda ver detalles o registrarse manualmente.

---

## Ejemplo de Integración en PHP

```php
/**
 * Función helper para el AI Assistant usando REST API internamente
 */
function ai_get_course_status($event_id, $user_email = '') {
    // Usar REST API internamente
    $request = new WP_REST_Request('GET', '/gps-courses/v1/availability/event/' . $event_id);
    $response = rest_do_request($request);

    if ($response->is_error()) {
        return ['error' => 'Course not found'];
    }

    $data = $response->get_data();

    // Si está sold out y tenemos email, verificar waitlist
    if ($data['availability']['is_sold_out'] && $user_email) {
        $waitlist_request = new WP_REST_Request('GET', '/gps-courses/v1/waitlist/check');
        $waitlist_request->set_query_params([
            'email' => $user_email,
            'event_id' => $event_id,
        ]);
        $waitlist_response = rest_do_request($waitlist_request);
        $waitlist_data = $waitlist_response->get_data();

        $data['user_waitlist_status'] = $waitlist_data;
    }

    return $data;
}
```

---

## Contacto

Para preguntas sobre esta integración, contactar al equipo de desarrollo.
