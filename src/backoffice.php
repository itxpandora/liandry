<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once 'db.php'; // Debe exponer $conn (PDO)

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    // No cache JSON
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 1) Si viene ?ver_pdf=ID -> devuelve el PDF binario del comprobante más reciente.
 */
if (isset($_GET['ver_pdf'])) {
    $idUsuario = (int)$_GET['ver_pdf'];

    // Buscar CI del usuario
    $q = $conn->prepare("SELECT ci FROM usuarios WHERE id = :id LIMIT 1");
    $q->execute([':id' => $idUsuario]);
    $u = $q->fetch(PDO::FETCH_ASSOC);

    if (!$u || empty($u['ci'])) {
        json_response(["status"=>"error","message"=>"Usuario o CI no encontrado"], 404);
    }

    // Comprobante más reciente
    $stmt = $conn->prepare("
        SELECT Filename, Mime, Contenido
        FROM ComprobantePago
        WHERE CI = :ci
        ORDER BY Fecha_Pago DESC
        LIMIT 1
    ");
    $stmt->execute([':ci' => $u['ci']]);
    $cp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cp || empty($cp['Contenido'])) {
        json_response(["status"=>"error","message"=>"Comprobante no encontrado"], 404);
    }

    // Emitir binario
    $mime = (!empty($cp['Mime']) && stripos($cp['Mime'], 'pdf') !== false) ? $cp['Mime'] : 'application/pdf';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . ($cp['Filename'] ?: ('comprobante_'.$idUsuario.'.pdf')) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $cp['Contenido'];
    exit;
}

/**
 * 2) POST JSON { action: "update_user", ... } -> editar datos de usuario
 *    Campos: id, ci, nombres, apellidos, correo, estado
 *    - Valida unicidad de CI (no repetido en otra fila)
 *    - Actualiza usuarios
 *    - Si cambia CI, también actualiza ComprobantePago.CI para mantener consistencia
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);
    if (!is_array($input)) {
        json_response(["status"=>"error","message"=>"JSON inválido"], 400);
    }

    if (isset($input['action']) && $input['action'] === 'update_user') {
        $id        = isset($input['id']) ? (int)$input['id'] : 0;
        $ci        = isset($input['ci']) ? trim($input['ci']) : '';
        $nombres   = isset($input['nombres']) ? trim($input['nombres']) : '';
        $apellidos = isset($input['apellidos']) ? trim($input['apellidos']) : '';
        $correo    = isset($input['correo']) ? trim($input['correo']) : '';
        $estado    = isset($input['estado']) ? (int)$input['estado'] : 0;

        if ($id <= 0 || $ci === '' || $nombres === '' || $apellidos === '') {
            json_response(['status'=>'error','message'=>'Datos incompletos'], 400);
        }

        try {
            $conn->beginTransaction();

            // CI anterior
            $stmt = $conn->prepare("SELECT ci FROM usuarios WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception('Usuario no encontrado');
            }
            $ciAnterior = $row['ci'];

            // Unicidad de CI en otra fila
            $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE ci = :ci AND id <> :id");
            $stmt->execute([':ci' => $ci, ':id' => $id]);
            $existe = (int)$stmt->fetchColumn() > 0;
            if ($existe) {
                throw new Exception('El CI ingresado ya está asignado a otro usuario');
            }

            // Actualizar tabla usuarios
            $stmt = $conn->prepare("
                UPDATE usuarios
                SET ci = :ci, nombres = :nombres, apellidos = :apellidos, correo = :correo, estado = :estado
                WHERE id = :id
            ");
            $stmt->execute([
                ':ci'        => $ci,
                ':nombres'   => $nombres,
                ':apellidos' => $apellidos,
                ':correo'    => $correo,
                ':estado'    => $estado,
                ':id'        => $id
            ]);

            // Si cambió el CI, actualizar referencias en ComprobantePago
            if ($ciAnterior !== $ci) {
                $stmt = $conn->prepare("UPDATE ComprobantePago SET CI = :nuevo WHERE CI = :anterior");
                $stmt->execute([':nuevo' => $ci, ':anterior' => $ciAnterior]);
            }

            $conn->commit();
            json_response(['status' => 'ok', 'message' => 'Usuario actualizado correctamente']);
        } catch (Exception $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            json_response(['status'=>'error','message'=>$e->getMessage()], 500);
        }
    }

        if (isset($input['action']) && $input['action'] === 'delete_user') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if ($id <= 0) {
            json_response(['status'=>'error','message'=>'ID inválido'], 400);
        }

        try {
            $conn->beginTransaction();

            // Buscar CI para limpiar ComprobantePago
            $stmt = $conn->prepare("SELECT ci FROM usuarios WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Usuario no encontrado");
            }
            $ci = $row['ci'];

            // Eliminar comprobantes asociados
            $stmt = $conn->prepare("DELETE FROM ComprobantePago WHERE CI = :ci");
            $stmt->execute([':ci' => $ci]);

            // Eliminar usuario
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $conn->commit();
            json_response(['status'=>'ok','message'=>'Usuario eliminado correctamente']);
        } catch (Exception $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            json_response(['status'=>'error','message'=>$e->getMessage()], 500);
        }
    }

    // Si llegó POST pero sin acción válida
    json_response(['status'=>'error','message'=>'Acción no soportada'], 400);
}

/**
 * 3) GET (sin ver_pdf): listado JSON para la tabla
 */
$sql = "
    SELECT
        u.id,
        CAST(u.ci AS CHAR) AS ci,
        u.nombres,
        u.apellidos,
        u.correo,
        u.estado,
        CASE WHEN EXISTS (
            SELECT 1 FROM ComprobantePago cp WHERE cp.CI = u.ci
        ) THEN 1 ELSE 0 END AS tiene_comprobante
    FROM usuarios u
    ORDER BY u.id ASC
";
$stmt = $conn->prepare($sql);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrega texto de estado (opcional)
foreach ($usuarios as &$row) {
    $row["estado_texto"] = ((int)$row["estado"] === 1) ? "aprobado" : "pendiente";
}
unset($row);

json_response($usuarios);
