<?php
// casas.php — CRUD + upload de imágenes (img1..img5) con URLs absolutas y diagnóstico de errores
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Content-Type: application/json; charset=utf-8");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'db.php';

// Directorio físico y base pública ABSOLUTA (puerto 8000)
$UPLOAD_DIR = __DIR__ . '/uploads/casas';
$PUBLIC_BASE = 'http://localhost:8000/uploads/casas';

if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0775, true);

// para reportar errores de subida en la respuesta
$GLOBALS['_UP_ERRS'] = [];

function cleanName($name){
  $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
  return substr($name, 0, 180);
}
function detect_mime($tmp_path, $fallback_name=''){
  $mime = '';
  if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
      $mime = finfo_file($finfo, $tmp_path) ?: '';
      finfo_close($finfo);
    }
  } elseif (function_exists('mime_content_type')) {
    $mime = @mime_content_type($tmp_path) ?: '';
  }
  if ($mime) return $mime;

  // Fallback por extensión
  $ext = strtolower(pathinfo($fallback_name, PATHINFO_EXTENSION));
  if (in_array($ext, ['jpg','jpeg'])) return 'image/jpeg';
  if ($ext === 'png') return 'image/png';
  if ($ext === 'webp') return 'image/webp';
  return '';
}
function saveImageIfAny($key, $id, $UPLOAD_DIR, $PUBLIC_BASE){
  if (empty($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) return null;
  $f = $_FILES[$key];

  if (!isset($f['tmp_name']) || $f['error'] !== UPLOAD_ERR_OK) {
    $GLOBALS['_UP_ERRS'][$key] = 'Error PHP al subir (code '.$f['error'].')';
    return null;
  }

  // Tamaño: por defecto 8MB (podés subirlo si querés)
  $maxBytes = 8 * 1024 * 1024;
  if ($f['size'] > $maxBytes) {
    $GLOBALS['_UP_ERRS'][$key] = 'Archivo demasiado grande (max 8MB)';
    return null;
  }

  // MIME/Ext
  $mime = detect_mime($f['tmp_name'], $f['name']);
  $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  $ext = $extMap[$mime] ?? strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if ($ext === 'jpeg') $ext = 'jpg';
  if (!in_array($ext, ['jpg','png','webp'])) {
    $GLOBALS['_UP_ERRS'][$key] = 'Tipo no permitido (MIME: '.$mime.', ext: '.$ext.')';
    return null;
  }

  if (!is_uploaded_file($f['tmp_name'])) {
    $GLOBALS['_UP_ERRS'][$key] = 'No es un archivo subido por HTTP';
    return null;
  }

  if (!is_dir($UPLOAD_DIR)) {
    if (!@mkdir($UPLOAD_DIR, 0775, true)) {
      $GLOBALS['_UP_ERRS'][$key] = 'No se pudo crear carpeta de destino';
      return null;
    }
  }

  $fname = "casa{$id}_{$key}_" . uniqid() . "." . $ext;
  $dest = rtrim($UPLOAD_DIR,'/') . '/' . cleanName($fname);

  if (!@move_uploaded_file($f['tmp_name'], $dest)) {
    $GLOBALS['_UP_ERRS'][$key] = 'No se pudo mover el archivo al destino';
    return null;
  }

  // Devolvemos URL ABSOLUTA
  return rtrim($PUBLIC_BASE,'/') . '/' . basename($dest);
}

// Normaliza rutas a absolutas (para datos viejos en la BD)
function absolutize($url) {
  if (!$url) return $url;
  if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) return $url;
  if (strpos($url, '/uploads/') === 0) return 'http://localhost:8000' . $url;
  return $url;
}

$method = $_SERVER['REQUEST_METHOD'];
$_method_override = isset($_GET['_method']) ? strtoupper($_GET['_method']) : null;
if ($method === 'POST' && $_method_override === 'DELETE') $method = 'DELETE';

try {
  switch ($method) {
    case 'GET': {
      $solo = isset($_GET['solo_activas']) ? (int)$_GET['solo_activas'] : 0;
      $sql = "SELECT id, titulo, descripcion, banos, dormitorios, metros,
                     img1, img2, img3, img4, img5, activo
              FROM casas";
      if ($solo === 1) $sql .= " WHERE activo = 1";
      $sql .= " ORDER BY id DESC";
      $st = $conn->prepare($sql); $st->execute();
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);

      foreach ($rows as &$r) {
        $r['img1'] = absolutize($r['img1'] ?? null);
        $r['img2'] = absolutize($r['img2'] ?? null);
        $r['img3'] = absolutize($r['img3'] ?? null);
        $r['img4'] = absolutize($r['img4'] ?? null);
        $r['img5'] = absolutize($r['img5'] ?? null);
      }
      unset($r);

      echo json_encode($rows, JSON_UNESCAPED_UNICODE);
      break;
    }

    case 'POST': {
      $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
      $isMultipart = stripos($ctype, 'multipart/form-data') !== false;

      if ($isMultipart) {
        // FormData con archivos
        $id          = (int)($_POST['id'] ?? 0);
        $titulo      = trim($_POST['titulo'] ?? '');
        $descripcion = $_POST['descripcion'] ?? '';
        $banos       = (int)($_POST['banos'] ?? 1);
        $dormitorios = (int)($_POST['dormitorios'] ?? 1);
        $metros      = (int)($_POST['metros'] ?? 0);
        $activo      = (int)($_POST['activo'] ?? 1);

        // URLs opcionales si no hay archivo
        $urls = [
          'img1'=>trim($_POST['url1'] ?? ''),
          'img2'=>trim($_POST['url2'] ?? ''),
          'img3'=>trim($_POST['url3'] ?? ''),
          'img4'=>trim($_POST['url4'] ?? ''),
          'img5'=>trim($_POST['url5'] ?? ''),
        ];

        if ($titulo === '') { http_response_code(422); echo json_encode(["status"=>"error","message"=>"El título es obligatorio"]); break; }

        if ($id > 0) {
          // UPDATE base
          $conn->prepare("UPDATE casas SET titulo=:t, descripcion=:d, banos=:b, dormitorios=:do, metros=:m, activo=:a WHERE id=:id")
               ->execute([':t'=>$titulo,':d'=>$descripcion,':b'=>$banos,':do'=>$dormitorios,':m'=>$metros,':a'=>$activo,':id'=>$id]);

          // Archivos y/o URLs
          $cols = [];
          foreach (['img1','img2','img3','img4','img5'] as $k) {
            if ($p = saveImageIfAny($k, $id, $UPLOAD_DIR, $PUBLIC_BASE)) $cols[$k] = $p;
          }
          foreach ($urls as $k=>$u) if ($u !== '') $cols[$k] = $u;

          if ($cols) {
            $set = []; $params = [':id'=>$id];
            foreach ($cols as $k=>$v){ $set[] = "$k=:$k"; $params[":$k"]=$v; }
            $conn->prepare("UPDATE casas SET ".implode(',', $set)." WHERE id=:id")->execute($params);
          }

          echo json_encode(["status"=>"ok","message"=>"Casa actualizada","id"=>$id,"upload_errors"=>$GLOBALS['_UP_ERRS']]);
          break;

        } else {
          // INSERT base
          $conn->prepare("INSERT INTO casas (titulo,descripcion,banos,dormitorios,metros,activo) VALUES (:t,:d,:b,:do,:m,:a)")
               ->execute([':t'=>$titulo,':d'=>$descripcion,':b'=>$banos,':do'=>$dormitorios,':m'=>$metros,':a'=>$activo]);
          $id = (int)$conn->lastInsertId();

          // Archivos y/o URLs
          $cols = [];
          foreach (['img1','img2','img3','img4','img5'] as $k) {
            if ($p = saveImageIfAny($k, $id, $UPLOAD_DIR, $PUBLIC_BASE)) $cols[$k] = $p;
          }
          foreach ($urls as $k=>$u) if ($u !== '') $cols[$k] = $u;

          if ($cols) {
            $set = []; $params = [':id'=>$id];
            foreach ($cols as $k=>$v){ $set[] = "$k=:$k"; $params[":$k"]=$v; }
            $conn->prepare("UPDATE casas SET ".implode(',', $set)." WHERE id=:id")->execute($params);
          }

          echo json_encode(["status"=>"ok","message"=>"Casa creada","id"=>$id,"upload_errors"=>$GLOBALS['_UP_ERRS']]);
          break;
        }

      } else {
        // JSON (compatibilidad)
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { http_response_code(400); echo json_encode(["status"=>"error","message"=>"JSON inválido"]); break; }

        $id          = (int)($body['id'] ?? 0);
        $titulo      = trim($body['titulo'] ?? '');
        $descripcion = $body['descripcion'] ?? '';
        $banos       = (int)($body['banos'] ?? 1);
        $dormitorios = (int)($body['dormitorios'] ?? 1);
        $metros      = (int)($body['metros'] ?? 0);
        $activo      = (int)($body['activo'] ?? 1);
        $img1 = absolutize($body['img1'] ?? null);
        $img2 = absolutize($body['img2'] ?? null);
        $img3 = absolutize($body['img3'] ?? null);
        $img4 = absolutize($body['img4'] ?? null);
        $img5 = absolutize($body['img5'] ?? null);

        if ($titulo === '') { http_response_code(422); echo json_encode(["status"=>"error","message"=>"El título es obligatorio"]); break; }

        if ($id > 0) {
          $ok = $conn->prepare("UPDATE casas SET
                    titulo=:t, descripcion=:d, banos=:b, dormitorios=:do, metros=:m,
                    img1=:i1, img2=:i2, img3=:i3, img4=:i4, img5=:i5, activo=:a
                  WHERE id=:id")->execute([
            ':t'=>$titulo, ':d'=>$descripcion, ':b'=>$banos, ':do'=>$dormitorios, ':m'=>$metros,
            ':i1'=>$img1, ':i2'=>$img2, ':i3'=>$img3, ':i4'=>$img4, ':i5'=>$img5, ':a'=>$activo, ':id'=>$id
          ]);
          echo json_encode($ok?["status"=>"ok","message"=>"Casa actualizada"] : ["status"=>"error","message"=>"No se pudo actualizar"]);
        } else {
          $ok = $conn->prepare("INSERT INTO casas (titulo, descripcion, banos, dormitorios, metros, img1, img2, img3, img4, img5, activo)
                  VALUES (:t,:d,:b,:do,:m,:i1,:i2,:i3,:i4,:i5,:a)")->execute([
            ':t'=>$titulo, ':d'=>$descripcion, ':b'=>$banos, ':do'=>$dormitorios, ':m'=>$metros,
            ':i1'=>$img1, ':i2'=>$img2, ':i3'=>$img3, ':i4'=>$img4, ':i5'=>$img5, ':a'=>$activo
          ]);
          echo json_encode($ok?["status"=>"ok","message"=>"Casa creada","id"=>$conn->lastInsertId()] : ["status"=>"error","message"=>"No se pudo crear"]);
        }
      }
      break;
    }

    case 'DELETE': {
      $body = json_decode(file_get_contents('php://input'), true);
      $id = (int)($body['id'] ?? 0);
      if ($id<=0) { http_response_code(400); echo json_encode(["status"=>"error","message"=>"ID inválido"]); break; }
      $ok = $conn->prepare("DELETE FROM casas WHERE id=:id")->execute([':id'=>$id]);
      echo json_encode($ok?["status"=>"ok","message"=>"Casa eliminada"] : ["status"=>"error","message"=>"No se pudo eliminar"]);
      break;
    }

    default:
      http_response_code(405);
      echo json_encode(["status"=>"error","message"=>"Método no permitido"]);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["status"=>"error","message"=>"Error interno","detail"=>$e->getMessage()]);
}
