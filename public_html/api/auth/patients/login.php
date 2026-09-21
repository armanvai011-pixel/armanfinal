<?php
/** Patient login. State is maintained by an HttpOnly PHP session cookie. */
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../helpers.php';
require_once __DIR__ . '/../middleware.php';

handleCors();
requireMethod('POST');
checkRateLimit('patient_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 900);
$input = getJsonInput();
$missing = validateRequired($input, ['phone', 'password']);
if ($missing) errorResponse('Missing required fields', 400, ['missing_fields' => $missing]);
$phone = sanitizePhone($input['phone']);
if (!$phone) errorResponse('Invalid phone number', 400);

try {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT pl.*, p.full_name, p.name_bn, p.gender, p.date_of_birth, p.register_number, p.photo_url FROM patient_login pl JOIN patients p ON pl.patient_id = p.id WHERE pl.phone = :phone LIMIT 1');
    $stmt->execute([':phone' => $phone]);
    $patient = $stmt->fetch();
    if (!$patient || !password_verify($input['password'], $patient['password_hash'])) errorResponse('Invalid phone number or password.', 401);
    if ($patient['status'] === 'pending') errorResponse('Your account is pending doctor approval. Please wait.', 403);
    if ($patient['status'] === 'rejected') errorResponse('Your account has been rejected.', 403);

    $token = bin2hex(random_bytes(64));
    $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    $db->prepare('INSERT INTO patient_sessions (patient_login_id, token, ip_address, user_agent, expires_at) VALUES (:id, :token, :ip, :agent, :expires)')->execute([
        ':id' => $patient['id'], ':token' => $token, ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? null, ':expires' => $expires,
    ]);
    setcookie('session_token', $token, [
        'expires' => time() + SESSION_LIFETIME, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    $db->prepare('UPDATE patient_login SET last_login_at = NOW() WHERE id = :id')->execute([':id' => $patient['id']]);
    unset($patient['password_hash']);
    successResponse(['patient' => $patient], 'Login successful');
} catch (Throwable $e) {
    error_log('Patient login error: ' . $e->getMessage());
    errorResponse('Login failed. Please try again.', 500);
}
