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
    <title>Reportes Nacionales</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef2f3;
            margin: 0;
        }
        .contenedor {
            width: 90%;
            margin: 20px auto;
            background: #ffffff;
            padding: 25px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        h1 {
            text-align: center;
            color: #003366;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }
        th {
            background: #003366;
            color: white;
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #003366;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .btn:hover {
            background: #0055a5;
        }
    </style>
</head>
<body>

<div class="contenedor">
    <h1>📊 Reportes Nacionales</h1>
    <p>Reporte de redenciones y deméritos por departamento.</p>

    <table>
        <tr>
            <th>Departamento</th>
            <th>Instituciones</th>
            <th>Estudiantes</th>
            <th>Redenciones</th>
            <th>Deméritos</th>
        </tr>
        <tr>
            <td>San Salvador</td>
            <td>125</td>
            <td>42,300</td>
            <td>5,210</td>
            <td>1,900</td>
        </tr>
        <tr>
            <td>La Libertad</td>
            <td>98</td>
            <td>31,100</td>
            <td>3,850</td>
            <td>1,450</td>
        </tr>
        <tr>
            <td>Santa Ana</td>
            <td>77</td>
            <td>25,900</td>
            <td>3,010</td>
            <td>1,210</td>
        </tr>
    </table>

    <a class="btn" href="ministra.php">⬅ Volver</a>
</div>

</body>
</html>
