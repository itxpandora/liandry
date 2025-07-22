<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"));
if (!$data || empty($data->correo)) {
    echo json_encode(["status" => "error", "message" => "Correo no recibido"]);
    exit;
}

$query = "SELECT correo, telefono FROM usuarios WHERE correo = :correo";
$stmt = $conn->prepare($query);
$stmt->bindParam(':correo', $data->correo);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(["status" => "success", "usuario" => $usuario]);
} else {
    echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
}
?>