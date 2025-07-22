<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Datos no recibidos"]);
    exit;
}

$query = "SELECT * FROM usuarios WHERE correo = :email";
$stmt = $conn->prepare($query);
$stmt->bindParam(':email', $data->email);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario['estado'] != 1) {
        echo json_encode(["status" => "error", "message" => "Tu cuenta está pendiente de aprobación"]);
        exit;
    }

    if (password_verify($data->password, $usuario['password'])) {
        echo json_encode(["status" => "success", "usuario" => $usuario]);
    } else {
        echo json_encode(["status" => "error", "message" => "Contraseña incorrecta"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
}
?>
