<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput ?: '', true);

if (!is_array($payload)) {
    $payload = $_POST;
}

function field(array $payload, string $key): string
{
    $value = $payload[$key] ?? '';
    return trim((string) $value);
}

$honeypot = field($payload, 'website');

if ($honeypot !== '') {
    echo json_encode(['ok' => true, 'message' => 'Solicitud recibida.']);
    exit;
}

$name = field($payload, 'nombre');
$email = field($payload, 'email');
$phone = field($payload, 'telefono');
$message = field($payload, 'mensaje');

if ($name === '' || $email === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Nombre y correo son obligatorios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'El correo no es valido.']);
    exit;
}

$to = 'contacto@tacadvisors.com.co';
$subject = 'Nueva solicitud de consulta - TAC ADVISORS';
$safeName = str_replace(["\r", "\n"], ' ', $name);
$safeEmail = str_replace(["\r", "\n"], ' ', $email);

$bodyLines = [
    'Nueva solicitud recibida desde tacadvisors.com.co',
    '',
    'Nombre: ' . $name,
    'Correo: ' . $email,
    'Telefono: ' . ($phone !== '' ? $phone : 'No indicado'),
    '',
    'Mensaje:',
    $message !== '' ? $message : 'No indicado',
    '',
    'Fecha: ' . date('Y-m-d H:i:s'),
];

$body = implode("\n", $bodyLines);

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: TAC ADVISORS <contacto@tacadvisors.com.co>',
    'Reply-To: ' . $safeName . ' <' . $safeEmail . '>',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = mail($to, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No pudimos enviar la solicitud.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Solicitud enviada correctamente.']);
