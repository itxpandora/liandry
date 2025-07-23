<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db.php';

$sql = "SELECT id, nombres, apellidos, correo, estado FROM usuarios";
$result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['aprobar'])) {
        $id = $_POST['id'];
        $query = "UPDATE usuarios SET estado = 1 WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    if (isset($_POST['rechazar'])) {
        $id = $_POST['id'];
        $query = "DELETE FROM usuarios WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }
}

$query = "SELECT id, nombres, apellidos, correo, estado FROM usuarios";
$stmt = $conn->prepare($query);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($usuarios as &$row) {
    $row["estado_texto"] = $row["estado"] ? "aprobado" : "pendiente";
}

echo json_encode($usuarios);

?>