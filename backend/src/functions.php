<?php
declare(strict_types=1);
function answer(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
function database(): PDO {
    return new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_NAME').';charset=utf8mb4', getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function cache(): Redis {
    $r = new Redis();
    $r->connect(getenv('REDIS_HOST'), (int)getenv('REDIS_PORT'), 3);
    $r->setOption(Redis::OPT_READ_TIMEOUT, 3);
    return $r;
}
function validateActivity(array $input): array {
    $errors = []; $data = [];
    foreach (['titulo'=>150, 'descripcion'=>2000] as $field=>$max) {
        $value = $input[$field] ?? ($field === 'descripcion' ? '' : null);
        if (!is_string($value)) { $errors[$field] = 'Debe ser texto.'; continue; }
        $value = trim($value);
        if (mb_strlen($value) > $max || ($field === 'titulo' && mb_strlen($value) < 3)) $errors[$field] = "Longitud inválida: título 3–150 y descripción hasta 2000 caracteres.";
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) $errors[$field] = 'Contiene caracteres de control no permitidos.';
        $data[$field] = $value;
    }
    foreach (['estado'=>['pendiente','en_proceso','completada'], 'prioridad'=>['baja','media','alta']] as $field=>$values) {
        $value = $input[$field] ?? ($field === 'estado' ? 'pendiente' : 'media');
        if (!is_string($value) || !in_array($value, $values, true)) $errors[$field] = 'Selecciona un valor válido.';
        $data[$field] = $value;
    }
    $date = $input['fecha_limite'] ?? null;
    if ($date === '') $date = null;
    if ($date !== null) {
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date || substr($date,0,4) < '1000') $errors['fecha_limite'] = 'Usa una fecha válida entre los años 1000 y 9999.';
    }
    $data['fecha_limite'] = $date;
    return [$data, $errors];
}
function notification(array $activity, string $event): bool {
    try {
        require_once '/opt/agenda/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host = getenv('SMTP_HOST'); $mail->Port = (int)getenv('SMTP_PORT');
        $mail->SMTPAuth = false; $mail->SMTPAutoTLS = false; $mail->Timeout = 5;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(getenv('SMTP_FROM'), 'Agenda de actividades');
        $mail->addAddress(getenv('SMTP_TO'));
        $mail->Subject = 'Actividad '.$event.': '.$activity['titulo'];
        $mail->Body = "Actividad #".$activity['id']."\nTítulo: ".$activity['titulo']."\nDescripción: ".$activity['descripcion']."\nEstado: ".$activity['estado']."\nPrioridad: ".$activity['prioridad']."\nFecha límite: ".($activity['fecha_limite'] ?? 'Sin fecha')."\n\nCorreo de prueba capturado por Mailpit.";
        return $mail->send();
    } catch (Throwable $e) { error_log('No fue posible enviar la notificación SMTP.'); return false; }
}
