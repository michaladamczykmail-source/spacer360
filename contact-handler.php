<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../api/phpmailer/src/Exception.php';
require __DIR__ . '/../api/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../api/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function respond($success, $error = '', $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Nieprawidłowa metoda żądania.', 405);
}

function clean_line($value) {
    $value = trim((string) ($value ?? ''));
    return str_replace(["\r", "\n"], ' ', $value);
}

// Honeypot: bots tend to fill every field, humans never see/fill this one.
$honeypot = clean_line($_POST['website'] ?? '');
if ($honeypot !== '') {
    respond(true);
}

$name    = clean_line($_POST['name'] ?? '');
$phone   = clean_line($_POST['phone'] ?? '');
$email   = clean_line($_POST['email'] ?? '');
$company = clean_line($_POST['company'] ?? '');
$nip     = clean_line($_POST['nip'] ?? '');
$package = clean_line($_POST['package'] ?? '');
$message = trim((string) ($_POST['message'] ?? ''));
$consent = isset($_POST['consent']) && $_POST['consent'] === 'on';

if ($name === '' || mb_strlen($name) > 200) {
    respond(false, 'Podaj poprawne imię i nazwisko.', 422);
}
if ($phone === '' || mb_strlen($phone) > 40) {
    respond(false, 'Podaj poprawny numer telefonu.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Podaj poprawny adres e-mail.', 422);
}
if (mb_strlen($message) > 5000) {
    respond(false, 'Wiadomość jest zbyt długa.', 422);
}
if (!$consent) {
    respond(false, 'Zgoda na przetwarzanie danych jest wymagana.', 422);
}

$configPath = __DIR__ . '/../api/contact-config.php';
if (!is_file($configPath)) {
    error_log('contact-handler.php: brak pliku contact-config.php');
    respond(false, 'Nie udało się wysłać wiadomości. Zadzwoń do nas lub napisz bezpośrednio na info@bokaWorks.pl.', 500);
}
$config = require $configPath;

$bodyLines = [
    "Imię i nazwisko: $name",
    "Telefon: $phone",
    "E-mail: $email",
    'Nazwa firmy: ' . ($company !== '' ? $company : '-'),
    'NIP: ' . ($nip !== '' ? $nip : '-'),
    'Wybrany pakiet: ' . ($package !== '' ? $package : '-'),
    '',
    'Wiadomość:',
    $message !== '' ? $message : '-',
];
$body = implode("\n", $bodyLines);

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->Port = (int) $config['smtp_port'];
    $mail->SMTPSecure = $config['smtp_secure'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_pass'];
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email'], $config['to_name']);
    $mail->addReplyTo($email, $name);

    $mail->Subject = 'Nowe zapytanie ze strony bokaWorks' . ($package !== '' ? " ($package)" : '');
    $mail->isHTML(false);
    $mail->Body = $body;

    $mail->send();
    respond(true);
} catch (PHPMailerException $e) {
    error_log('contact-handler.php mail error: ' . $mail->ErrorInfo);
    respond(false, 'Nie udało się wysłać wiadomości. Zadzwoń do nas lub napisz bezpośrednio na info@bokaWorks.pl.', 500);
}
