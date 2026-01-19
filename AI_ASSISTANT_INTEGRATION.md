# Integración AI Assistant con GPS Courses - Sold Out y Waitlist

## Contexto

El plugin GPS Courses ahora tiene funcionalidad de **Sold Out manual** y **Waitlist mejorada**. El AI Assistant del plugin de carritos abandonados necesita poder identificar cuando un curso está sold out y guiar al usuario apropiadamente.

---

## Funciones Disponibles en GPS Courses

El plugin GPS Courses expone las siguientes funciones que el AI Assistant puede usar:

### 1. Verificar si un ticket está Sold Out

```php
// Verificar si GPS Courses está activo
if (class_exists('\GPSC\Tickets')) {
    // Retorna true si el ticket está sold out (manual o por stock)
    $is_sold_out = \GPSC\Tickets::is_sold_out($ticket_id);
}
```

### 2. Verificar si es Sold Out Manual

```php
if (class_exists('\GPSC\Tickets')) {
    // Retorna true solo si fue marcado manualmente como sold out
    $is_manual = \GPSC\Tickets::is_manually_sold_out($ticket_id);
}
```

### 3. Obtener Stock de un Ticket

```php
if (class_exists('\GPSC\Tickets')) {
    $stock = \GPSC\Tickets::get_ticket_stock($ticket_id);
    // Retorna: ['total' => int, 'sold' => int, 'available' => int, 'unlimited' => bool]
}
```

### 4. Obtener Disponibilidad de un Evento

```php
if (class_exists('\GPSC\Tickets')) {
    $availability = \GPSC\Tickets::get_event_availability($event_id);
    // Retorna: ['total_available' => int, 'unlimited' => bool]
}
```

### 5. Agregar a Waitlist Programáticamente

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
        // Error: $result->get_error_message()
    } else {
        // Éxito: $result['id'] = waitlist ID, $result['position'] = posición en cola
    }
}
```

### 6. Verificar si Email ya está en Waitlist

```php
if (class_exists('\GPSC\Waitlist')) {
    global $wpdb;
    $table = $wpdb->prefix . 'gps_waitlist';

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table
         WHERE email = %s AND ticket_type_id = %d AND event_id = %d
         AND status IN ('waiting', 'notified')",
        $email, $ticket_id, $event_id
    ));

    if ($exists) {
        // Ya está en waitlist
    }
}
```

---

## Información de Base de Datos

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

### Meta Field para Sold Out Manual

- **Post Type:** `gps_ticket`
- **Meta Key:** `_gps_manual_sold_out`
- **Valores:** `'1'` (sold out) o `''`/`'0'` (disponible)

---

## Lógica Recomendada para el AI Assistant

### Cuando el usuario pregunta por un curso:

```php
function get_course_availability_info($event_id) {
    if (!class_exists('\GPSC\Tickets')) {
        return ['available' => true, 'message' => 'Course availability information not available.'];
    }

    // Obtener todos los tickets del evento
    $tickets = get_posts([
        'post_type' => 'gps_ticket',
        'meta_query' => [
            ['key' => '_gps_event_id', 'value' => $event_id]
        ],
        'posts_per_page' => -1
    ]);

    $all_sold_out = true;
    $ticket_info = [];

    foreach ($tickets as $ticket) {
        $is_sold_out = \GPSC\Tickets::is_sold_out($ticket->ID);
        $stock = \GPSC\Tickets::get_ticket_stock($ticket->ID);

        $ticket_info[] = [
            'name' => $ticket->post_title,
            'sold_out' => $is_sold_out,
            'available' => $stock['available'],
            'unlimited' => $stock['unlimited']
        ];

        if (!$is_sold_out) {
            $all_sold_out = false;
        }
    }

    if ($all_sold_out) {
        return [
            'available' => false,
            'message' => 'This course is currently sold out. You can join our waitlist to be notified when spots become available.',
            'action' => 'waitlist',
            'tickets' => $ticket_info
        ];
    }

    return [
        'available' => true,
        'message' => 'Tickets are available for this course.',
        'tickets' => $ticket_info
    ];
}
```

### Respuestas sugeridas del AI:

**Si el curso está disponible:**
> "Great news! Tickets are available for [Course Name]. Would you like me to help you complete your registration?"

**Si el curso está sold out:**
> "I see that [Course Name] is currently sold out. However, you can join our waitlist and we'll notify you immediately if a spot becomes available. Would you like me to add you to the waitlist? I'll just need your name and email."

**Si ya está en waitlist:**
> "You're already on the waitlist for [Course Name] at position #[X]. We'll notify you as soon as a spot opens up!"

---

## Obtener Información del Evento

```php
function get_event_details($event_id) {
    $event = get_post($event_id);
    if (!$event || $event->post_type !== 'gps_event') {
        return null;
    }

    return [
        'id' => $event->ID,
        'title' => $event->post_title,
        'start_date' => get_post_meta($event_id, '_gps_start_date', true),
        'end_date' => get_post_meta($event_id, '_gps_end_date', true),
        'location' => get_post_meta($event_id, '_gps_location', true),
        'ce_credits' => get_post_meta($event_id, '_gps_ce_credits', true),
        'url' => get_permalink($event_id)
    ];
}
```

---

## Flujo de Conversación Sugerido

```
Usuario: "I want to register for the Implant Course"

AI: [Busca el evento por nombre]
    [Verifica disponibilidad con get_course_availability_info()]

    SI disponible:
        "The Implant Fundamentals Course on [fecha] has tickets available!
         Would you like me to help you complete your registration?"

    SI sold out:
        "The Implant Fundamentals Course is currently sold out, but I can
         add you to our waitlist. You'll be notified immediately when a
         spot becomes available. Can I have your name and email to add
         you to the waitlist?"

Usuario: "Yes, add me to the waitlist"

AI: [Recopila información: nombre, email, teléfono opcional]
    [Usa \GPSC\Waitlist::add_to_waitlist()]

    "Perfect! I've added you to the waitlist at position #[X]. You'll
     receive an email confirmation shortly, and we'll notify you as soon
     as a spot opens up. The notification will be valid for 48 hours,
     so please act quickly when you receive it!"
```

---

## Notas Importantes

1. **Sold Out Manual vs Stock:** Un ticket puede estar sold out por dos razones:
   - Manualmente marcado por admin (override)
   - Stock agotado naturalmente

2. **Expiración de Notificaciones:** Cuando se notifica a alguien de la waitlist, tienen 48 horas para comprar antes de que expire.

3. **Posiciones en Cola:** Las posiciones se reordenan automáticamente cuando alguien es removido o convierte.

4. **Estados de Waitlist:**
   - `waiting` - En espera de notificación
   - `notified` - Notificado, tiene 48h para comprar
   - `converted` - Completó la compra
   - `expired` - No compró en las 48h
   - `removed` - Removido manualmente por admin

5. **URL del Curso:** Siempre incluir el enlace al curso cuando sea relevante para que el usuario pueda ver detalles o registrarse en waitlist manualmente.

---

## Ejemplo de Implementación Completa

```php
// En el plugin de AI Assistant, agregar esta función helper
function gps_check_course_and_respond($event_id, $user_email = '') {
    // Verificar que GPS Courses esté activo
    if (!class_exists('\GPSC\Tickets')) {
        return [
            'type' => 'error',
            'message' => 'Course system not available.'
        ];
    }

    // Obtener info del evento
    $event = get_post($event_id);
    if (!$event) {
        return [
            'type' => 'not_found',
            'message' => 'Course not found.'
        ];
    }

    // Obtener tickets
    $tickets = get_posts([
        'post_type' => 'gps_ticket',
        'meta_query' => [
            ['key' => '_gps_event_id', 'value' => $event_id],
            ['key' => '_gps_ticket_status', 'value' => 'active']
        ],
        'posts_per_page' => -1
    ]);

    $available_tickets = [];
    $sold_out_tickets = [];

    foreach ($tickets as $ticket) {
        if (\GPSC\Tickets::is_sold_out($ticket->ID)) {
            $sold_out_tickets[] = $ticket;
        } else {
            $stock = \GPSC\Tickets::get_ticket_stock($ticket->ID);
            $available_tickets[] = [
                'ticket' => $ticket,
                'stock' => $stock
            ];
        }
    }

    // Si hay tickets disponibles
    if (!empty($available_tickets)) {
        return [
            'type' => 'available',
            'event' => $event,
            'tickets' => $available_tickets,
            'message' => sprintf(
                'Great news! %s has %d ticket type(s) available.',
                $event->post_title,
                count($available_tickets)
            ),
            'url' => get_permalink($event_id)
        ];
    }

    // Todo sold out - verificar waitlist
    if (!empty($user_email) && class_exists('\GPSC\Waitlist')) {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        // Verificar si ya está en waitlist para algún ticket
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table
             WHERE email = %s AND event_id = %d AND status IN ('waiting', 'notified')
             ORDER BY position ASC LIMIT 1",
            $user_email, $event_id
        ));

        if ($existing) {
            return [
                'type' => 'already_on_waitlist',
                'event' => $event,
                'position' => $existing->position,
                'status' => $existing->status,
                'message' => sprintf(
                    "You're already on the waitlist for %s at position #%d!",
                    $event->post_title,
                    $existing->position
                )
            ];
        }
    }

    return [
        'type' => 'sold_out',
        'event' => $event,
        'tickets' => $sold_out_tickets,
        'message' => sprintf(
            '%s is currently sold out. Would you like to join the waitlist?',
            $event->post_title
        ),
        'url' => get_permalink($event_id)
    ];
}
```

---

## Contacto

Para preguntas sobre esta integración, contactar al equipo de desarrollo.
