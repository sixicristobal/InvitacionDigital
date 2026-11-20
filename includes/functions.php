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

// Geocodifica una dirección con Nominatim (OSM) y devuelve un mapa estático como data URI.
// Devuelve: ['img' => 'data:image/png;base64,...' | null, 'mapsUrl' => 'https://maps.google.com/?q=...']
function geocodeMap($direccion, $zoom=16){
    $result = ['img' => null, 'mapsUrl' => 'https://www.google.com/maps/search/?api=1&query=' . urlencode($direccion)];
    if (!$direccion) return $result;

    $query = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($direccion);
    $ctx = stream_context_create([
        'http' => ['header' => "User-Agent: InvitacionDigitalBoda/1.0\r\n", 'timeout' => 8],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $resp = @file_get_contents($query, false, $ctx);
    if ($resp === false) return $result;

    $data = json_decode($resp, true);
    if (!isset($data[0]['lat']) || !isset($data[0]['lon'])) return $result;

    $lat = $data[0]['lat'];
    $lon = $data[0]['lon'];
    $mapUrl = "https://staticmap.openstreetmap.de/staticmap.php?lat={$lat}&lon={$lon}&zoom={$zoom}&size=400x250&maptype=map";
    $mapCtx = stream_context_create(['http' => ['timeout' => 15], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $img = @file_get_contents($mapUrl, false, $mapCtx);
    if ($img === false || $img === '') return $result;

    $result['img'] = 'data:image/png;base64,' . base64_encode($img);
    return $result;
}