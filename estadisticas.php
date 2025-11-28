<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estadísticas</title>
</head>
<body style="font-family: Arial; background:#eef2f3;">
    <div style="width:80%; margin:40px auto; background:white; padding:20px; border-radius:8px;">
        <h1 style="text-align:center; color:#003366;">📈 Estadísticas del Sistema</h1>
        <p>Estas estadísticas representan datos simulados del sistema académico nacional.</p>

        <ul>
            <li>Estudiantes activos en el sistema: <strong>200,000</strong></li>
            <li>Promedio de méritos por estudiante: <strong>3.2</strong></li>
            <li>Promedio de deméritos por estudiante: <strong>1.1</strong></li>
            <li>Instituciones registradas: <strong>900</strong></li>
        </ul>

        <a style="background:#003366; padding:10px; color:white; border-radius:6px; text-decoration:none;" href="ministra.php">⬅ Volver</a>
    </div>
</body>
</html>
