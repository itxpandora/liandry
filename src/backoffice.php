<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db.php';

$sql = "SELECT id, nombres, apellidos, correo, estado FROM usuarios";
$result = $conn->query($sql);

$usuarios = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($usuarios as &$row) {
    $row["estado"] = $row["estado"] ? "aprobado" : "pendiente";
}

echo json_encode($usuarios);
?>