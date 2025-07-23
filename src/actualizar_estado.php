<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
include 'db.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->id) || !isset($data->estado)) {
    echo json_encode(["status" => "error", "message" => "Faltan parámetros"]);
    exit;
}

$id = $data->id;
$estado = $data->estado;

try {
    $query = "UPDATE usuarios SET estado = :estado WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':estado', $estado);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    echo json_encode(["status" => "ok", "message" => "Estado actualizado"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}
?>
