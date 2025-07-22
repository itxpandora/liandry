<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

include 'db.php';

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if(!$data) {
    echo json_encode(["status" => "error", "message" => "Datos no recibidos"]);
    exit;
}

$query = "INSERT INTO usuarios (ci, nombres, apellidos, fecha_nacimiento, correo, telefono, password)
          VALUES (:ci, :nombres, :apellidos, :fecha_nacimiento, :correo, :telefono, :password)";
$stmt = $conn->prepare($query);
$stmt->bindParam(':ci', $data->ci);
$stmt->bindParam(':nombres', $data->nombres);
$stmt->bindParam(':apellidos', $data->apellidos);
$stmt->bindParam(':fecha_nacimiento', $data->fecha_nacimiento);
$stmt->bindParam(':correo', $data->correo);
$stmt->bindParam(':telefono', $data->telefono);
$hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
$stmt->bindParam(':password', $hashed_password);

if($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Error al crear usuario"]);
}
?>