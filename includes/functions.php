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

function getParejaConfig(array $pareja): array {
    $defaultConfig = [
        'hero' => [
            'subtitulo' => '¡Nos casamos!',
            'foto_portada' => '',
            'musica_url' => '',
            'autoplay' => true,
            'sobre_apertura' => true,
        ],
        'historia' => [
            'activo' => false,
            'titulo' => 'Nuestra Historia',
            'texto' => '',
        ],
        'eventos' => [],
        'vestimenta' => [
            'activo' => false,
            'codigo' => 'Formal / Elegante',
            'nota' => '',
            'colores_evitar' => '',
        ],
        'galeria' => [],
        'regalos' => [
            'activo' => true,
            'titulo' => 'Mesa de Regalos',
            'mensaje' => '',
            'link_externo' => '',
        ],
        'extras' => [
            'permitir_cancion' => true,
            'permitir_restriccion' => true,
            'whatsapp_contacto' => '',
            'mensaje_whatsapp' => 'Hola, tengo una consulta sobre la boda',
        ],
    ];

    if (!empty($pareja['config_json'])) {
        $stored = json_decode($pareja['config_json'], true);
        if (is_array($stored)) {
            $defaultConfig = array_replace_recursive($defaultConfig, $stored);
        }
    }
    return $defaultConfig;
}

function subirArchivoMultimedia(array $file, int $idPareja, string $tipo = 'imagen'): ?string {
    $uploadDir = __DIR__ . "/../assets/uploads/{$idPareja}/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $tmpName = $file['tmp_name'] ?? '';
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_OK || !$tmpName || !is_uploaded_file($tmpName)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);

    if ($tipo === 'imagen') {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            return null;
        }
        $maxWidth = 1600;
        $quality = 82;
        $ext = '.webp';
    } elseif ($tipo === 'audio') {
        $allowedMimes = ['audio/mpeg', 'audio/mp3'];
        if (!in_array($mime, $allowedMimes, true)) {
            return null;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return null;
        }
        $ext = '.mp3';
    } else {
        return null;
    }

    $filename = bin2hex(random_bytes(8)) . $ext;
    $destPath = $uploadDir . $filename;

    if ($tipo === 'imagen') {
        $src = null;
        if ($mime === 'image/jpeg') $src = imagecreatefromjpeg($tmpName);
        elseif ($mime === 'image/png') $src = imagecreatefrompng($tmpName);
        elseif ($mime === 'image/webp') $src = imagecreatefromwebp($tmpName);

        if (!$src) return null;

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        if ($srcW > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int)round($srcH * $maxWidth / $srcW);
            $dst = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            imagedestroy($src);
            $src = $dst;
        }

        $result = imagewebp($src, $destPath, $quality);
        imagedestroy($src);
        return $result ? "assets/uploads/{$idPareja}/{$filename}" : null;
    } else {
        if (move_uploaded_file($tmpName, $destPath)) {
            return "assets/uploads/{$idPareja}/{$filename}";
        }
        return null;
    }
}