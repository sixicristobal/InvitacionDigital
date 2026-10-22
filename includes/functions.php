<?php
// includes/functions.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function pdo() { global $pdo; return $pdo; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function generarToken($len=32){ return bin2hex(random_bytes($len/2)); }

function redirigir($url){ header('Location: '.$url); exit; }

function estaLogueado(){ return isset($_SESSION['admin_id']); }

function requiereLogin(){ if(!estaLogueado()){ redirigir(ADMIN_URL.'/login.php'); } }

function flash($msg, $tipo='success'){ $_SESSION['flash']=['msg'=>$msg,'tipo'=>$tipo]; }

function getFlash(){ if(isset($_SESSION['flash'])){ $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f;} return null; }