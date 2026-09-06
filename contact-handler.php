<?php
header('Content-Type: application/json; charset=utf-8');

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

$to = 'info@bokaWorks.pl';
$subjectText = 'Nowe zapytanie ze strony bokaWorks' . ($package !== '' ? " ($package)" : '');
$subject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';

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

$headers  = "From: bokaWorks – formularz kontaktowy <info@bokaWorks.pl>\r\n";
$headers .= 'Reply-To: ' . str_replace(["\r", "\n"], '', "$name <$email>") . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = @mail($to, $subject, $body, $headers);

if ($sent) {
    respond(true);
}

respond(false, 'Nie udało się wysłać wiadomości. Zadzwoń do nas lub napisz bezpośrednio na info@bokaWorks.pl.', 500);
