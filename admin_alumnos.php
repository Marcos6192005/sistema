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
    $conexion->query("UPDATE estudiante SET estado_matriculado = NOT estado_matriculado WHERE id_estudiante = $id");
    header("Location: admin_alumnos.php");
    exit();
}

$sql = "SELECT id_estudiante, nie, nombre, apellido, estado_matriculado FROM estudiante";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administrar Alumnos</title>
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

<h2>🎓 Estudiantes Registrados</h2>
<a href="registrar_alumno.php"><button>➕ Registrar Alumno</button></a><br><br>


<table>
<tr>
    <th>NIE</th>
    <th>Nombre</th>
    <th>Estado</th>
    <th>Acción</th>
</tr>

<?php while($fila = $resultado->fetch_assoc()): ?>
<tr>
    <td><?= $fila['nie'] ?></td>
    <td><?= $fila['nombre'] . " " . $fila['apellido'] ?></td>

    <td class="<?= $fila['estado_matriculado'] ? 'estado-activo':'estado-inactivo' ?>">
        <?= $fila['estado_matriculado'] ? 'Matriculado' : 'Retirado' ?>
    </td>

    <td>
        <a href="admin_alumnos.php?cambiar_estado=<?= $fila['id_estudiante'] ?>">
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
