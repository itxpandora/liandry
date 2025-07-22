<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if(!$data || !$data->correo) {
    echo json_encode(["status" => "error", "message" => "Datos incompletos"]);
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
    $stmt->bindParam(':telefono', $data->telefono);
    $stmt->bindParam(':correo_original', $data->correo_original);

    if (!empty($data->password)) {
        $hashed = password_hash($data->password, PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $hashed);
    }

    $stmt->execute();

    echo json_encode(["status" => "success"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>