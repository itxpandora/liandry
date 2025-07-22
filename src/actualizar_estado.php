<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'];
$estado = $data['estado'] === 'aprobado' ? 1 : 0;

$sql = "UPDATE usuarios SET estado=? WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $estado, $id);
$stmt->execute();

echo json_encode(["success" => true]);
?>
