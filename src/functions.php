<?php
/**
 * Funciones del sistema (versión adaptada para tests con SQLite en memoria)
 */

/**
 * Registrar una casa en la tabla `casas`.
 * Devuelve ['ok'=>true] o ['ok'=>false,'error'=>'mensaje']
 */
function registrarCasa(PDO $conn, string $titulo, string $descripcion, string $imagen): array
{
    // Verificar duplicado
    try {
        $stmt = $conn->prepare("SELECT 1 FROM casas WHERE titulo = ? LIMIT 1");
        $stmt->execute([$titulo]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Título duplicado'];
        }
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Error verificando duplicado: ' . $e->getMessage()];
    }

    // Validar imagen
    $ext = strtolower(pathinfo($imagen, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
        return ['ok' => false, 'error' => 'Formato de imagen inválido'];
    }

    // Insertar
    try {
        $stmt = $conn->prepare("INSERT INTO casas (titulo, descripcion, img1) VALUES (?, ?, ?)");
        $stmt->execute([$titulo, $descripcion, $imagen]);
        return ['ok' => true];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Error insertando casa: ' . $e->getMessage()];
    }
}

/**
 * Registrar un usuario en la tabla `usuarios`.
 * Devuelve ['ok'=>true] o ['ok'=>false,'error'=>'mensaje']
 */
function registrarUsuario(PDO $conn, int $ci, string $nombres, string $apellidos, string $fecha_nacimiento, string $correo, $telefono, string $password): array
{
    try {
        // Verificar CI duplicado
        $stmt = $conn->prepare("SELECT 1 FROM usuarios WHERE ci = ? LIMIT 1");
        $stmt->execute([$ci]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Usuario duplicado'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO usuarios (ci, nombres, apellidos, fecha_nacimiento, correo, telefono, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ci, $nombres, $apellidos, $fecha_nacimiento, $correo, $telefono, $hash]);

        return ['ok' => true];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Error registrando usuario: ' . $e->getMessage()];
    }
}

/**
 * Procesar acción en el backoffice ('aceptado' o 'eliminar').
 * Devuelve true si tuvo éxito, false si no.
 */
function procesarAccionUsuario(PDO $conn, string $accion, int $ci): bool
{
    try {
        if ($accion === 'aceptado') {
            $stmt = $conn->prepare("UPDATE usuarios SET estado = 1 WHERE ci = ?");
            $stmt->execute([$ci]);
            return $stmt->rowCount() > 0;
        }

        if ($accion === 'eliminar') {
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE ci = ?");
            $stmt->execute([$ci]);
            return $stmt->rowCount() > 0;
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Login de usuario: verifica correo y password.
 * Devuelve ['ok'=>true,'usuario'=>...] o ['ok'=>false]
 */
function loginUsuario(PDO $conn, string $correo, string $password): array
{
    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE correo = ? LIMIT 1");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            return ['ok' => false, 'error' => 'Usuario no encontrado'];
        }

        if (password_verify($password, $usuario['password'])) {
            return ['ok' => true, 'usuario' => $usuario];
        }

        return ['ok' => false, 'error' => 'Contraseña incorrecta'];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Error login: ' . $e->getMessage()];
    }
}
