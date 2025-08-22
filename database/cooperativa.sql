CREATE DATABASE cooperativa;
USE cooperativa;

SELECT * FROM cooperativa.usuarios;

ALTER TABLE ComprobantePago
  ADD COLUMN Size INT UNSIGNED AFTER Mime;

CREATE TABLE Persona (
    CI INT PRIMARY KEY CHECK (CI > 350000),
    Nombres VARCHAR(50),
    Apellidos VARCHAR(50),
    Domicilio VARCHAR(100),
    Telefono VARCHAR(20),
    Correo VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ci INT NOT NULL,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE NOT NULL,
  correo VARCHAR(255) NOT NULL,
  telefono INT(9) NOT NULL,
  estado BOOLEAN NOT NULL DEFAULT 0, 
  password VARCHAR(255) NOT NULL
);

CREATE TABLE Admin (
    CI INT PRIMARY KEY,
    FOREIGN KEY (CI) REFERENCES Persona(CI)
);

CREATE TABLE UnidadHabitacional (
    ID INT PRIMARY KEY CHECK (ID > 0),
    Direccion VARCHAR(100),
    Tamano INT CHECK (Tamano > 25),
    Baños INT,
    Dormitorios INT
);

CREATE TABLE ComprobanteHoras (
    Fecha_Horas DATE PRIMARY KEY CHECK (Fecha_Horas <= CURRENT_DATE),
    Horas INT CHECK (Horas > 21),
    Estatus VARCHAR(20) CHECK (Estatus IN ('Al dia', 'Atrasado'))
);

CREATE TABLE ComprobantePago (
    CI INT(11) NOT NULL,
    Fecha_Pago DATE NOT NULL CHECK (Fecha_Pago <= CURRENT_DATE),
    Forma_Pago VARCHAR(20) NOT NULL CHECK (Forma_Pago IN ('Tarjeta', 'Paypal')),
    Filename VARCHAR(255) NOT NULL,
    Mime VARCHAR(100) NOT NULL,
    Size INT(10) UNSIGNED NOT NULL,
    Contenido LONGBLOB,
    Estado ENUM('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
    PRIMARY KEY (CI, Fecha_Pago),
    FOREIGN KEY (CI) REFERENCES Persona(CI)
);

CREATE TABLE Pertenece (
    CI INT,
    ID_Unidad INT,
    PRIMARY KEY (CI, ID_Unidad),
    FOREIGN KEY (CI) REFERENCES Persona(CI),
    FOREIGN KEY (ID_Unidad) REFERENCES UnidadHabitacional(ID)
);

CREATE TABLE Verifica (
    CI_Admin INT,
    Fecha_Verificacion DATE,
    ID_Unidad INT,
    PRIMARY KEY (CI_Admin, Fecha_Verificacion, ID_Unidad),
    FOREIGN KEY (CI_Admin) REFERENCES Admin(CI),
    FOREIGN KEY (ID_Unidad) REFERENCES UnidadHabitacional(ID)
);

CREATE TABLE Autoriza (
    CI_Admin INT,
    CI_Usuario INT,
    Fecha_Autorizacion DATE,
    PRIMARY KEY (CI_Admin, CI_Usuario, Fecha_Autorizacion),
    FOREIGN KEY (CI_Admin) REFERENCES Admin(CI),
    FOREIGN KEY (CI_Usuario) REFERENCES Usuario(CI)
);

CREATE TABLE Gestiona (
    CI_Admin INT,
    ID_Unidad INT,
    PRIMARY KEY (CI_Admin, ID_Unidad),
    FOREIGN KEY (CI_Admin) REFERENCES Admin(CI),
    FOREIGN KEY (ID_Unidad) REFERENCES UnidadHabitacional(ID)
);
