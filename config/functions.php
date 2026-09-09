<?php
/** HM Café shared session, security, validation and helper functions. */
if (function_exists("mb_internal_encoding")) {
    mb_internal_encoding("UTF-8");
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "httponly" => true,
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "samesite" => "Lax"
    ]);
    session_start();
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function csrf_token(): string {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function verify_csrf(?string $token): bool {
    return is_string($token) && !empty($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token);
}

function is_logged_in(): bool {
    return isset($_SESSION["user_id"]) && is_numeric($_SESSION["user_id"]);
}

function is_admin(): bool {
    return is_logged_in() && (($_SESSION["role"] ?? "customer") === "admin");
}

function require_login(string $redirect = "index.php"): void {
    if (!is_logged_in()) {
        header("Location: login.php?redirect=" . urlencode($redirect));
        exit;
    }
}

function require_admin(string $loginUrl = "login.php"): void {
    if (!is_admin()) {
        header("Location: " . $loginUrl);
        exit;
    }
}

function safe_redirect(string $redirect, string $default = "index.php"): string {
    $redirect = trim($redirect);
    if ($redirect === "" || preg_match('/^[a-z][a-z0-9+\-.]*:/i', $redirect) || str_starts_with($redirect, "//") || str_contains($redirect, "\r") || str_contains($redirect, "\n")) {
        return $default;
    }
    if (preg_match('/^(\.\.\/|\.\/|[a-zA-Z0-9_\-\/]+\.php)/', $redirect)) {
        return $redirect;
    }
    return $default;
}

function flash(string $type, string $message): void {
    $_SESSION["flash"] = ["type" => $type, "message" => $message];
}

function get_flash(): ?array {
    $message = $_SESSION["flash"] ?? null;
    unset($_SESSION["flash"]);
    return $message;
}
