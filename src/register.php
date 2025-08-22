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

try {
    $conn->beginTransaction();

    $ci = $data->ci;

    // 1) Insertar en Persona si no existe
    $checkPersona = $conn->prepare("SELECT 1 FROM Persona WHERE CI = :ci");
    $checkPersona->bindParam(':ci', $ci);
    $checkPersona->execute();

    if ($checkPersona->rowCount() === 0) {
        $insertPersona = $conn->prepare("
            INSERT INTO Persona (CI, Nombres, Apellidos, Domicilio, Telefono, Correo)
            VALUES (:ci, :nombres, :apellidos, '', :telefono, :correo)
        ");
        $insertPersona->bindParam(':ci', $ci);
        $insertPersona->bindParam(':nombres', $data->nombres);
        $insertPersona->bindParam(':apellidos', $data->apellidos);
        $insertPersona->bindParam(':telefono', $data->telefono);
        $insertPersona->bindParam(':correo', $data->correo);
        $insertPersona->execute();
    }

    // 2) Insertar en usuarios
    $insertUsuario = $conn->prepare("
        INSERT INTO usuarios (ci, nombres, apellidos, fecha_nacimiento, correo, telefono, password)
        VALUES (:ci, :nombres, :apellidos, :fecha_nacimiento, :correo, :telefono, :password)
    ");
    $insertUsuario->bindParam(':ci', $ci);
    $insertUsuario->bindParam(':nombres', $data->nombres);
    $insertUsuario->bindParam(':apellidos', $data->apellidos);
    $insertUsuario->bindParam(':fecha_nacimiento', $data->fecha_nacimiento);
    $insertUsuario->bindParam(':correo', $data->correo);
    $insertUsuario->bindParam(':telefono', $data->telefono);
    $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
    $insertUsuario->bindParam(':password', $hashed_password);
    $insertUsuario->execute();

    $conn->commit();
    echo json_encode(["status" => "success"]);

} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(["status" => "error", "message" => "Error en el registro: " . $e->getMessage()]);
}
?>
