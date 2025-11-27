<?php
session_start();
require 'includes/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Datos del usuario
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $nombre_completo = $nombre . " " . $apellido;
    $correo = $_POST['correo'];
    $pass = $_POST['password'];
    $password_hash = password_hash($pass, PASSWORD_DEFAULT);

    // Datos del estudiante
    $nie = $_POST['nie'];
    $id_grado = $_POST['id_grado'];
    $id_seccion = $_POST['id_seccion'];
    $id_institucion = $_POST['id_institucion'];
    $direccion = $_POST['direccion'];
    $f_nacimiento = $_POST['f_nacimiento'];

    try {

        // 1️⃣ Crear usuario
        $stmt = $conn->prepare("
            INSERT INTO usuarios (nombre_completo, correo, password_hash, id_rol)
            VALUES (:nombre_completo, :correo, :password_hash, 5)
        ");

        $stmt->bindParam(':nombre_completo', $nombre_completo);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->execute();

        // 2️⃣ Obtener ID del usuario
        $id_usuario = $conn->lastInsertId();

        // 3️⃣ Insertar en tabla estudiante
        $stmt2 = $conn->prepare("
            INSERT INTO estudiante 
            (nie, nombre, apellido, id_grado, id_seccion, id_institucion, direccion, f_nacimiento, id_usuario)
            VALUES 
            (:nie, :nombre, :apellido, :id_grado, :id_seccion, :id_institucion, :direccion, :f_nacimiento, :id_usuario)
        ");

        $stmt2->bindParam(':nie', $nie);
        $stmt2->bindParam(':nombre', $nombre);
        $stmt2->bindParam(':apellido', $apellido);
        $stmt2->bindParam(':id_grado', $id_grado);
        $stmt2->bindParam(':id_seccion', $id_seccion);
        $stmt2->bindParam(':id_institucion', $id_institucion);
        $stmt2->bindParam(':direccion', $direccion);
        $stmt2->bindParam(':f_nacimiento', $f_nacimiento);
        $stmt2->bindParam(':id_usuario', $id_usuario);

        $stmt2->execute();

        header("Location: login.php?registrado_estudiante=1");
        exit();

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>