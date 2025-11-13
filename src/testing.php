<?php
use PHPUnit\Framework\TestCase;

// Si se ejecuta directamente con "php testing.php", mostramos un mensaje útil
if (basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"])) {
    echo "\n=====================\n";
    echo " Ejecutando tests...\n";
    echo "=====================\n\n";

    // Ejecuta PHPUnit automáticamente desde PHP
    system('php phpunit.phar --testdox ' . __FILE__);
    exit;
}

class Testing extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        // Base de datos SQLite en memoria
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->conn->exec("
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                correo TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                nombres TEXT,
                apellidos TEXT
            );
        ");

        $this->conn->exec("
            CREATE TABLE casas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo TEXT UNIQUE NOT NULL,
                descripcion TEXT,
                precio REAL,
                img1 TEXT
            );
        ");
    }

    // ---------- FUNCIONES SIMULADAS ----------
    private function registrarUsuario($correo, $password, $nombre, $apellido)
    {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Correo inválido'];
        }

        $check = $this->conn->prepare("SELECT 1 FROM usuarios WHERE correo = ?");
        $check->execute([$correo]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => 'Correo duplicado'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO usuarios (correo, password, nombres, apellidos) VALUES (?, ?, ?, ?)");
        $stmt->execute([$correo, $hash, $nombre, $apellido]);

        return ['ok' => true];
    }

    private function loginUsuario($correo, $password)
    {
        $stmt = $this->conn->prepare("SELECT password FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return ['ok' => false, 'error' => 'Usuario no encontrado'];
        if (!password_verify($password, $row['password'])) {
            return ['ok' => false, 'error' => 'Contraseña incorrecta'];
        }

        return ['ok' => true];
    }

    private function registrarCasa($titulo, $descripcion, $precio, $imagen)
    {
        if (!preg_match('/\.(jpg|jpeg|png)$/i', $imagen)) {
            return ['ok' => false, 'error' => 'Formato de imagen inválido'];
        }

        $check = $this->conn->prepare("SELECT 1 FROM casas WHERE titulo = ?");
        $check->execute([$titulo]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => 'Título duplicado'];
        }

        $stmt = $this->conn->prepare("INSERT INTO casas (titulo, descripcion, precio, img1) VALUES (?, ?, ?, ?)");
        $stmt->execute([$titulo, $descripcion, $precio, $imagen]);

        return ['ok' => true];
    }

    // ---------- TESTS ----------
    public function testRegistroUsuarioValido()
    {
        $res = $this->registrarUsuario("correo@test.com", "123456", "Manu", "Pérez");
        $this->assertTrue($res['ok']);
    }

    public function testRegistroCorreoDuplicado()
    {
        $this->registrarUsuario("correo@test.com", "123456", "Manu", "Pérez");
        $res = $this->registrarUsuario("correo@test.com", "123456", "Juan", "López");
        $this->assertFalse($res['ok']);
        $this->assertEquals("Correo duplicado", $res['error']);
    }

    public function testLoginExitoso()
    {
        $this->registrarUsuario("correo@test.com", "123456", "Manu", "Pérez");
        $res = $this->loginUsuario("correo@test.com", "123456");
        $this->assertTrue($res['ok']);
    }

    public function testLoginContrasenaIncorrecta()
    {
        $this->registrarUsuario("correo@test.com", "123456", "Manu", "Pérez");
        $res = $this->loginUsuario("correo@test.com", "999999");
        $this->assertFalse($res['ok']);
        $this->assertEquals("Contraseña incorrecta", $res['error']);
    }

    public function testRegistrarCasaValida()
    {
        $res = $this->registrarCasa("Casa1", "Linda casa", 100000, "foto.jpg");
        $this->assertTrue($res['ok']);
    }

    public function testRegistrarCasaDuplicada()
    {
        $this->registrarCasa("Casa1", "Linda casa", 100000, "foto.jpg");
        $res = $this->registrarCasa("Casa1", "Otra casa", 200000, "otra.png");
        $this->assertFalse($res['ok']);
        $this->assertEquals("Título duplicado", $res['error']);
    }

    public function testRegistrarCasaFormatoInvalido()
    {
        $res = $this->registrarCasa("Casa2", "Sin imagen válida", 90000, "documento.pdf");
        $this->assertFalse($res['ok']);
        $this->assertEquals("Formato de imagen inválido", $res['error']);
    }
}
