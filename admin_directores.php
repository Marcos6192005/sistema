<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Datos de ejemplo sin base de datos
$directores = [
    ["nombre" => "Carlos Pérez", "institucion" => "Instituto Nacional Santa Ana", "estado" => "Activo"],
    ["nombre" => "María Gómez", "institucion" => "Centro Escolar La Paz", "estado" => "Inactivo"],
    ["nombre" => "José Herrera", "institucion" => "Instituto Nacional El Salvador", "estado" => "Activo"],
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Directores</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef2f7;
            margin: 0;
            padding: 0;
        }
        .header {
            background: #003366;
            color: #fff;
            padding: 15px;
            text-align: center;
            font-size: 22px;
        }
        table {
            width: 90%;
            margin: 25px auto;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.15);
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }
        th {
            background: #003366;
            color: white;
        }
        .activo {
            color: green;
            font-weight: bold;
        }
        .inactivo {
            color: red;
            font-weight: bold;
        }
        a {
            display: inline-block;
            margin: 20px auto;
            background: #003366;
            color: white;
            padding: 10px 14px;
            text-decoration: none;
            border-radius: 5px;
        }
        a:hover {
            background: #0055a5;
        }
    </style>
</head>
<body>

<div class="header">
    👥 Administrar Directores
</div>

<table>
    <tr>
        <th>Nombre</th>
        <th>Institución</th>
        <th>Estado</th>
    </tr>

    <?php foreach($directores as $d): ?>
        <tr>
            <td><?php echo $d['nombre']; ?></td>
            <td><?php echo $d['institucion']; ?></td>
            <td class="<?php echo strtolower($d['estado']); ?>">
                <?php echo $d['estado']; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<div style="text-align:center;">
    <a href="ministra.php">Volver al Panel</a>
</div>

</body>
</html>
