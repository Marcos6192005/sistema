<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: login.php");
    exit();
}

$conexion = new mysqli("localhost", "root", "", "demeritos_meritos_indet");
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nie = $_POST['nie'];
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];

    // Insertar alumno
    $conexion->query("INSERT INTO estudiante (nie, nombre, apellido)
                      VALUES ('$nie', '$nombre', '$apellido')");

    $mensaje = "Alumno registrado correctamente ✔";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registrar Alumno</title>
</head>
<body>

<h2>Registrar Alumno</h2>

<p style="color: green;"><?= $mensaje ?></p>

<form method="POST">
    NIE: <input type="text" name="nie" required><br><br>
    Nombre: <input type="text" name="nombre" required><br><br>
    Apellido: <input type="text" name="apellido" required><br><br>

    <button type="submit">Registrar</button>
</form>

<br>
<a href="admin_alumnos.php"><button>⬅️ Volver</button></a>

</body>
</html>
