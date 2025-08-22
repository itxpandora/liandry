<?php
header("Access-Control-Allow-Origin: *");
require_once 'db.php';

// --- Si viene ver_pdf, entregamos el PDF binario y salimos ---
if (isset($_GET['ver_pdf'])) {
    $idUsuario = (int)$_GET['ver_pdf'];

    // Buscamos CI del usuario
    $q = $conn->prepare("SELECT ci FROM usuarios WHERE id = :id LIMIT 1");
    $q->execute([':id' => $idUsuario]);
    $u = $q->fetch(PDO::FETCH_ASSOC);

    if (!$u || empty($u['ci'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(["status"=>"error","message"=>"Usuario o CI no encontrado"]);
        exit;
    }

    // Obtenemos el comprobante (más reciente)
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
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(["status"=>"error","message"=>"Comprobante no encontrado"]);
        exit;
    }

    // Emitimos el PDF
    $mime = (!empty($cp['Mime']) && stripos($cp['Mime'], 'pdf') !== false) ? $cp['Mime'] : 'application/pdf';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . ($cp['Filename'] ?: ('comprobante_'.$idUsuario.'.pdf')) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $cp['Contenido'];
    exit;
}

// --- Listado JSON para la tabla ---
header("Content-Type: application/json; charset=utf-8");
// Fuerza a no cachear el JSON
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$sql = "
    SELECT
        u.id,
        CAST(u.ci AS CHAR) AS ci,   -- <- CI garantizada en minúscula
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

// Texto de estado
foreach ($usuarios as &$row) {
    $row["estado_texto"] = ((int)$row["estado"] === 1) ? "aprobado" : "pendiente";
}
unset($row);

echo json_encode($usuarios, JSON_UNESCAPED_UNICODE);
