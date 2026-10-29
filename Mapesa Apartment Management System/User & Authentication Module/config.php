<?php
session_start();

$host = 'localhost';
$dbName = 'apartment_management';
$dbUser = 'map3sa';
$dbPass = '4945Mapesa#';

$conn = new mysqli($host, $dbUser, $dbPass);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

if (!$conn->select_db($dbName)) {
    die('Unable to select database: ' . $conn->error);
}

$conn->set_charset('utf8mb4');

define('APP_NAME', 'Mapesa User & Authentication Module');

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserId(): ?int
{
    return isLoggedIn() ? (int) $_SESSION['user_id'] : null;
}

function setFlash(string $key, string $message): void
{
    $_SESSION[$key] = $message;
}

function getFlash(string $key): ?string
{
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }

    return null;
}
