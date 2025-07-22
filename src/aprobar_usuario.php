<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once 'db.php';

if (!isset($_GET['id']) || !isset($_GET['accion'])) {
    echo json_encode(["status" => "error", "message" => "Datos incompletos"]);
    exit;
}

$id = intval($_GET['id']);
$accion = $_GET['accion'];

if ($accion === 'aprobar') {
    $sql = "UPDATE usuarios SET estado = 1 WHERE id = ?";
} elseif ($accion === 'rechazar') {
    $sql = "DELETE FROM usuarios WHERE id = ?";
} else {
    echo json_encode(["status" => "error", "message" => "Acción no válida"]);
    exit;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Acción realizada"]);
} else {
    echo json_encode(["status" => "error", "message" => "Error al ejecutar acción"]);
}
?>
