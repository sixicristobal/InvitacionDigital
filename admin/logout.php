<?php
require_once __DIR__ . '/../includes/functions.php';
session_destroy();
redirigir(ADMIN_URL.'/login.php');
PHP
echo done