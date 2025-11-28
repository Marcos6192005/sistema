<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: login.php");
    exit();
}

$conexion = new mysqli("localhost", "root", "", "demeritos_meritos_indet");
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $correo = $_POST['correo'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Primero crear usuario con rol maestro (rol = 3)
    $conexion->query("INSERT INTO usuarios (nombre_completo, correo, password_hash, estado, id_rol)
                      VALUES ('$nombre $apellido', '$correo', '$pass', TRUE, 3)");

    $id_usuario = $conexion->insert_id;

    // Insertar en tabla maestro
    $conexion->query("INSERT INTO maestros (id_usuario, nombre, apellido)
                      VALUES ($id_usuario, '$nombre', '$apellido')");

    $mensaje = "Maestro registrado correctamente ✔";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registrar Maestro</title>
</head>
<body>

<h2>Registrar Maestro</h2>

<p style="color: green;"><?= $mensaje ?></p>

<form method="POST">
    Nombre: <input type="text" name="nombre" required><br><br>
    Apellido: <input type="text" name="apellido" required><br><br>
    Correo institucional: <input type="email" name="correo" required><br><br>
    Contraseña temporal: <input type="password" name="password" required><br><br>

    <button type="submit">Registrar</button>
</form>

<br>
<a href="admin_maestros.php"><button>⬅️ Volver</button></a>

</body>
</html>
