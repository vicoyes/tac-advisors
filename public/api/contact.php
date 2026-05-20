<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "message" => "Metodo no permitido."]);
    exit;
}

$configPath = __DIR__ . "/smtp-config.php";

if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "message" => "Configuracion SMTP no disponible."]);
    exit;
}

$smtp = require $configPath;

$rawInput = file_get_contents("php://input");
$payload = json_decode($rawInput ?: "", true);

if (!is_array($payload)) {
    $payload = $_POST;
}

function field(array $payload, string $key): string
{
    return trim((string) ($payload[$key] ?? ""));
}

function smtpRead($socket): string
{
    $response = "";

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (strlen($line) >= 4 && $line[3] === " ") {
            break;
        }
    }

    return $response;
}

function smtpCommand($socket, ?string $command, array $expected): string
{
    if ($command !== null) {
        fwrite($socket, $command . "\r\n");
    }

    $response = smtpRead($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expected, true)) {
        throw new RuntimeException("SMTP error: " . trim($response));
    }

    return $response;
}

function smtpSend(array $smtp, string $subject, string $body, string $replyToEmail, string $replyToName): void
{
    $host = (string) ($smtp["host"] ?? "smtp.hostinger.com");
    $port = (int) ($smtp["port"] ?? 465);
    $encryption = strtolower((string) ($smtp["encryption"] ?? "ssl"));
    $username = (string) ($smtp["username"] ?? "");
    $password = (string) ($smtp["password"] ?? "");
    $fromEmail = (string) ($smtp["from_email"] ?? $username);
    $fromName = (string) ($smtp["from_name"] ?? "TAC ADVISORS");
    $toEmail = (string) ($smtp["to_email"] ?? $fromEmail);

    if ($username === "" || $password === "" || $fromEmail === "" || $toEmail === "") {
        throw new RuntimeException("Configuracion SMTP incompleta.");
    }

    $transportHost = $encryption === "ssl" ? "ssl://" . $host : $host;
    $socket = fsockopen($transportHost, $port, $errno, $errstr, 20);

    if (!$socket) {
        throw new RuntimeException("No fue posible conectar al servidor SMTP.");
    }

    stream_set_timeout($socket, 20);

    try {
        smtpCommand($socket, null, [220]);
        smtpCommand($socket, "EHLO tacadvisors.com.co", [250]);

        if ($encryption === "tls") {
            smtpCommand($socket, "STARTTLS", [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException("No fue posible iniciar TLS.");
            }

            smtpCommand($socket, "EHLO tacadvisors.com.co", [250]);
        }

        smtpCommand($socket, "AUTH LOGIN", [334]);
        smtpCommand($socket, base64_encode($username), [334]);
        smtpCommand($socket, base64_encode($password), [235]);
        smtpCommand($socket, "MAIL FROM:<" . $fromEmail . ">", [250]);
        smtpCommand($socket, "RCPT TO:<" . $toEmail . ">", [250, 251]);
        smtpCommand($socket, "DATA", [354]);

        $safeReplyName = str_replace(["\r", "\n"], " ", $replyToName);
        $safeReplyEmail = str_replace(["\r", "\n"], " ", $replyToEmail);
        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $date = date(DATE_RFC2822);
        $messageBody = preg_replace("/^\./m", "..", $body);

        $message = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "Date: " . $date,
            "From: " . $fromName . " <" . $fromEmail . ">",
            "To: TAC ADVISORS <" . $toEmail . ">",
            "Reply-To: " . $safeReplyName . " <" . $safeReplyEmail . ">",
            "Subject: " . $encodedSubject,
            "",
            $messageBody,
            ".",
        ]);

        fwrite($socket, $message . "\r\n");
        smtpCommand($socket, null, [250]);
        smtpCommand($socket, "QUIT", [221]);
    } finally {
        fclose($socket);
    }
}

$honeypot = field($payload, "website");

if ($honeypot !== "") {
    echo json_encode(["ok" => true, "message" => "Solicitud recibida."]);
    exit;
}

$name = field($payload, "nombre");
$email = field($payload, "email");
$phone = field($payload, "telefono");
$message = field($payload, "mensaje");

if ($name === "" || $email === "") {
    http_response_code(422);
    echo json_encode(["ok" => false, "message" => "Nombre y correo son obligatorios."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(["ok" => false, "message" => "El correo no es valido."]);
    exit;
}

$subject = "Nueva solicitud de consulta - TAC ADVISORS";
$body = implode("\n", [
    "Nueva solicitud recibida desde tacadvisors.com.co",
    "",
    "Nombre: " . $name,
    "Correo: " . $email,
    "Telefono: " . ($phone !== "" ? $phone : "No indicado"),
    "",
    "Mensaje:",
    $message !== "" ? $message : "No indicado",
    "",
    "Fecha: " . date("Y-m-d H:i:s"),
]);

try {
    smtpSend($smtp, $subject, $body, $email, $name);
    echo json_encode(["ok" => true, "message" => "Solicitud enviada correctamente."]);
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode(["ok" => false, "message" => "No pudimos enviar la solicitud."]);
}
