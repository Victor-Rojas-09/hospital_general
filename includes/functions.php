<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function go(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_value(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function get_value(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = (string) ($_POST['_csrf'] ?? '');
    if ($provided === '' || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('La solicitud expiró o no es válida. Regresa e inténtalo nuevamente.');
    }
}

function flash(string $type, ?string $message = null): ?array
{
    if ($message !== null) {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
        return null;
    }

    $stored = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $stored;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_role(): string
{
    return (string) (current_user()['role'] ?? '');
}

function has_role(array $roles): bool
{
    return in_array(current_role(), $roles, true);
}

function require_login(): void
{
    if (!current_user()) {
        go('index.php');
    }
}

function require_role(array $roles): void
{
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        echo '<h1>Acceso denegado</h1><p>No tienes permisos para acceder a este módulo.</p>';
        exit;
    }
}

function encrypt_sensitive(string $plainText): string
{
    $key = hex2bin(APP_ENCRYPTION_KEY_HEX);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('La clave de cifrado debe tener exactamente 32 bytes en formato hexadecimal.');
    }

    $nonce = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt(
        $plainText,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );

    if ($cipherText === false) {
        throw new RuntimeException('No fue posible cifrar el dato.');
    }

    return base64_encode($nonce . $tag . $cipherText);
}

function decrypt_sensitive(?string $encoded): string
{
    if ($encoded === null || $encoded === '') {
        return '';
    }

    $key = hex2bin(APP_ENCRYPTION_KEY_HEX);
    $payload = base64_decode($encoded, true);
    if ($key === false || strlen($key) !== 32 || $payload === false || strlen($payload) < 28) {
        return '[dato no disponible]';
    }

    $nonce = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $cipherText = substr($payload, 28);
    $plainText = openssl_decrypt($cipherText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

    return $plainText === false ? '[no se pudo descifrar]' : $plainText;
}

function sensitive_index(string $kind, string $value): string
{
    $key = hex2bin(APP_ENCRYPTION_KEY_HEX);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Clave de cifrado inválida.');
    }

    $normalized = strtoupper((string) preg_replace('/[\s.\-]+/u', '', trim($value)));
    return hash_hmac('sha256', $kind . ':' . $normalized, $key);
}

function normalize_datetime(string $value): string
{
    return str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
}

function valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function valid_datetime_local(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $date !== false && $date->format('Y-m-d\TH:i') === $value;
}

function selected(string|int|null $current, string|int|null $expected): string
{
    return (string) $current === (string) $expected ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}
