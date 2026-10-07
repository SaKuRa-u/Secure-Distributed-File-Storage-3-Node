<?php
function csrf_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(): void {
    $token_from_form    = $_POST['csrf_token'] ?? '';
    $token_from_session = $_SESSION['csrf_token'] ?? '';

    if (empty($token_from_session) ||
        !hash_equals($token_from_session, $token_from_form)) {
        http_response_code(403);
        die("403 Forbidden: CSRF token tidak valid. Silakan muat ulang halaman.");
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): void {
    $token = csrf_generate();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
?>
