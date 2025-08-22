<?php
// CORS (ajustá el origen si tu frontend corre en otro puerto/host)
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// DEBUG (podés desactivar en prod)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';

// Responder preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  echo json_encode(["status" => "ok"]);
  exit;
}

// Detectar Content-Type (puede venir con boundary, etc.)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

// --------------------------------------------------------------------------------------
// RUTA 1: SUBIDA DE COMPROBANTE (multipart/form-data)
// --------------------------------------------------------------------------------------
if (stripos($contentType, 'multipart/form-data') !== false && isset($_FILES['comprobante'])) {
  try {
    // 1) Obtener correo
    $correo = $_POST['correo'] ?? $_POST['correo_original'] ?? '';
    if (!$correo) {
      echo json_encode(["status" => "error", "message" => "Correo faltante"]);
      exit;
    }

    // 2) Buscar CI del usuario por correo
    $q = $conn->prepare("SELECT u.ci FROM usuarios u WHERE u.correo = :c LIMIT 1");
    $q->execute([':c' => $correo]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['ci'])) {
      echo json_encode([
        "status" => "error",
        "message" => "El usuario no tiene CI asignado o no existe. Verificá tus datos."
      ]);
      exit;
    }
    $ci = (int)$row['ci'];

    // 3) Validar que exista en Persona (requisito de FK)
    $qp = $conn->prepare("SELECT 1 FROM Persona p WHERE p.CI = :ci LIMIT 1");
    $qp->execute([':ci' => $ci]);
    if (!$qp->fetchColumn()) {
      echo json_encode([
        "status"  => "error",
        "message" => "El CI $ci no existe en Persona. Debe cargarse la persona antes de subir el comprobante."
      ]);
      exit;
    }

    // 4) Validaciones del archivo
    $f = $_FILES['comprobante'] ?? null;
    if (!$f) {
      echo json_encode(["status" => "error", "message" => "Archivo no recibido"]);
      exit;
    }

    if ($f['error'] !== UPLOAD_ERR_OK) {
      $map = [
        UPLOAD_ERR_INI_SIZE   => "El archivo excede upload_max_filesize (php.ini). upload_max_filesize=" . ini_get('upload_max_filesize') . ", post_max_size=" . ini_get('post_max_size'),
        UPLOAD_ERR_FORM_SIZE  => "El archivo excede MAX_FILE_SIZE del formulario.",
        UPLOAD_ERR_PARTIAL    => "El archivo se subió parcialmente.",
        UPLOAD_ERR_NO_FILE    => "No se subió ningún archivo.",
        UPLOAD_ERR_NO_TMP_DIR => "Falta el directorio temporal en el servidor.",
        UPLOAD_ERR_CANT_WRITE => "No se pudo escribir el archivo en disco.",
        UPLOAD_ERR_EXTENSION  => "Una extensión de PHP detuvo la subida."
      ];
      $code = (int)$f['error'];
      $msg  = $map[$code] ?? ("Error de subida (código $code).");
      echo json_encode(["status" => "error", "message" => $msg]);
      exit;
    }

    // Límite de 5 MB (política de la app)
    if ($f['size'] > 5 * 1024 * 1024) {
      echo json_encode(["status" => "error", "message" => "Archivo > 5MB"]);
      exit;
    }

    // Verificación MIME real
    $fi   = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fi, $f['tmp_name']);
    finfo_close($fi);
    if ($mime !== 'application/pdf') {
      echo json_encode(["status" => "error", "message" => "Solo se admite PDF"]);
      exit;
    }

    // 5) Guardar en BD (BLOB)
    $contenido = file_get_contents($f['tmp_name']);

    // Sugerencia: si tu MySQL está en STRICT, asegurate que el campo Contenido sea LONGBLOB si esperás PDFs grandes.
    $stmt = $conn->prepare("
      INSERT INTO ComprobantePago
        (CI, Fecha_Pago, Forma_Pago, Filename, Mime, Size, Contenido, Estado)
      VALUES
        (:ci, CURDATE(), 'Tarjeta', :fn, :mime, :sz, :blob, 'pendiente')
      ON DUPLICATE KEY UPDATE
        Filename  = VALUES(Filename),
        Mime      = VALUES(Mime),
        Size      = VALUES(Size),
        Contenido = VALUES(Contenido),
        Estado    = 'pendiente',
        Fecha_Pago = VALUES(Fecha_Pago),
        Forma_Pago = VALUES(Forma_Pago)
    ");

    $stmt->bindValue(':ci',   $ci,               PDO::PARAM_INT);
    $stmt->bindValue(':fn',   $f['name']);
    $stmt->bindValue(':mime', $mime);
    $stmt->bindValue(':sz',   (int)$f['size'],   PDO::PARAM_INT);
    $stmt->bindValue(':blob', $contenido,        PDO::PARAM_LOB);

    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Comprobante guardado"]);
    exit;

  } catch (PDOException $e) {
    // FK u otras violaciones de integridad
    if ($e->getCode() === '23000') {
      echo json_encode([
        "status"  => "error",
        "message" => "No se pudo guardar por restricción de clave foránea o unicidad. Verificá que el CI exista en Persona y que la clave no esté duplicada."
      ]);
      exit;
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
  } catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
  }
}

// --------------------------------------------------------------------------------------
// RUTA 2: ACTUALIZAR DATOS DE PERFIL (application/json)
// --------------------------------------------------------------------------------------
$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->correo) || !isset($data->telefono) || !isset($data->correo_original)) {
  echo json_encode(["status" => "error", "message" => "Datos incompletos"]);
  exit;
}

$tel = (string)$data->telefono;
if (!preg_match('/^[0-9]{9}$/', $tel)) {
  echo json_encode(["status" => "error", "message" => "Teléfono inválido"]);
  exit;
}

try {
  $sql = "UPDATE usuarios SET correo = :correo, telefono = :telefono";
  if (!empty($data->password)) {
    $sql .= ", password = :password";
  }
  $sql .= " WHERE correo = :correo_original";

  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':correo', $data->correo);
  $stmt->bindParam(':telefono', $tel);
  $stmt->bindParam(':correo_original', $data->correo_original);

  if (!empty($data->password)) {
    $hashed = password_hash($data->password, PASSWORD_DEFAULT);
    $stmt->bindParam(':password', $hashed);
  }

  $stmt->execute();

  echo json_encode(["status" => "success"]);
  exit;

} catch (PDOException $e) {
  echo json_encode(["status" => "error", "message" => $e->getMessage()]);
  exit;
}
