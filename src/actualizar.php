<?php
include 'db.php';

$data = json_decode(file_get_contents("php://input"));

if(!$data) {
    echo json_encode(["message" => "Datos no recibidos"]);
    exit;
}

$query = "UPDATE usuarios SET nombre = :nombre, email = :email, password = :password WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':nombre', $data->nombre);
$stmt->bindParam(':email', $data->email);
$stmt->bindParam(':password', $data->password);
$stmt->bindParam(':id', $data->id);

if($stmt->execute()) {
    echo json_encode(["message" => "Datos actualizados correctamente"]);
} else {
    echo json_encode(["message" => "Error al actualizar datos"]);
}
?>
