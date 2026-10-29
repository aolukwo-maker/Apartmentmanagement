<?php
require_once __DIR__ . '/config.php';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card auth-card">
            <h1>Access Denied</h1>
            <div class="alert error">You do not have permission to access this page.</div>
            <div class="auth-links">
                <a href="dashboard.php">Go to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>


