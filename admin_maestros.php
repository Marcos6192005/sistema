<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['id_rol'] != 1) {
    echo "Acceso denegado.";
    exit();
}

$conexion = new mysqli("localhost", "root", "", "demeritos_meritos_indet");

if ($conexion->connect_error) {
    die("Error: " . $conexion->connect_error);
}

if (isset($_GET['cambiar_estado'])) {
    $id = intval($_GET['cambiar_estado']);
    $conexion->query("UPDATE usuarios SET estado = NOT estado WHERE id_usuario = $id");
    header("Location: admin_maestros.php");
    exit();
}

// Obtenemos maestros
$sql = "SELECT m.id_maestro, u.id_usuario, m.nombre, m.apellido, u.estado
        FROM maestros m
        JOIN usuarios u ON m.id_usuario = u.id_usuario
        WHERE u.id_rol = 3";

$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administrar Maestros</title>
<style>
body { font-family: Arial; background: #eef1f5; padding: 20px; }
table { width: 100%; background: white; border-collapse: collapse; }
th, td { padding: 10px; border-bottom: 1px solid #ccc; text-align: center; }
.estado-activo { color: green; font-weight: bold; }
.estado-inactivo { color: red; font-weight: bold; }
button{ padding: 6px 12px; border: none; background: #003366; color: white; border-radius: 5px; cursor: pointer; }
button:hover { background: #0066bb; }
</style>
</head>
<body>

<h2>👨‍🏫 Maestros Registrados</h2>
<a href="registrar_maestro.php"><button>➕ Registrar Maestro</button></a><br><br>


<table>
<tr>
    <th>Nombre</th>
    <th>Estado</th>
    <th>Acción</th>
</tr>

<?php while($fila = $resultado->fetch_assoc()): ?>
<tr>
    <td><?= $fila['nombre'] . " " . $fila['apellido'] ?></td>

    <td class="<?= $fila['estado'] ? 'estado-activo':'estado-inactivo' ?>">
        <?= $fila['estado'] ? 'Activo' : 'Inactivo' ?>
    </td>

    <td>
        <a href="admin_maestros.php?cambiar_estado=<?= $fila['id_usuario'] ?>">
            <button>Cambiar Estado</button>
        </a>
    </td>
</tr>
<?php endwhile; ?>

</table>

<br>
<a href="ministra.php"><button>⬅️ Volver al Panel</button></a>

</body>
</html>
