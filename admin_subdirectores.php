<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Validar Rol por ID → Ministra debería tener id_rol = 1 (ejemplo)
if ($_SESSION['id_rol'] != 1) {
    echo "Acceso denegado.";
    exit();
}

// Conexión a la BD
$conexion = new mysqli("localhost", "root", "", "demeritos_meritos_indet");

if ($conexion->connect_error) {
    die("Error en conexión: " . $conexion->connect_error);
}

// Cambiar estado
if (isset($_GET['cambiar_estado'])) {
    $id = intval($_GET['cambiar_estado']);
    $conexion->query("UPDATE usuarios 
                      SET estado = NOT estado
                      WHERE id_usuario = $id");
    header("Location: admin_subdirectores.php");
    exit();
}

// Obtener Subdirectores (id_rol = 2 como ejemplo)
$resultado = $conexion->query("SELECT u.id_usuario, u.nombre_completo, u.estado,
                                      r.nombre_rol
                               FROM usuarios u
                               JOIN rol r ON u.id_rol = r.id_rol
                               WHERE r.nombre_rol = 'Subdirector'");
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administrar Subdirectores</title>

<style>
body { font-family: Arial; background: #eef1f5; padding: 20px; }
table { width: 100%; background: white; border-collapse: collapse; }
th, td { padding: 10px; border-bottom: 1px solid #ccc; text-align: center; }
.estado-activo { color: green; font-weight: bold; }
.estado-inactivo { color: red; font-weight: bold; }
button{
    padding: 6px 12px; border: none;
    background: #003366; color: white;
    border-radius: 5px; cursor: pointer;
}
button:hover { background: #0066bb; }
</style>

</head>
<body>

<h2>👥 Subdirectores Registrados</h2>

<table>
<tr>
    <th>Nombre</th>
    <th>Rol</th>
    <th>Estado</th>
    <th>Acción</th>
</tr>

<?php while($fila = $resultado->fetch_assoc()): ?>
<tr>
    <td><?= $fila['nombre_completo'] ?></td>
    <td><?= $fila['nombre_rol'] ?></td>

    <td class="<?= $fila['estado'] ? 'estado-activo':'estado-inactivo' ?>">
        <?= $fila['estado'] ? 'Activo' : 'Inactivo' ?>
    </td>

    <td>
        <a href="admin_subdirectores.php?cambiar_estado=<?= $fila['id_usuario'] ?>">
            <button>Cambiar Estado</button>
        </a>
    </td>
</tr>
<?php endwhile; ?>

</table>

<br>
<a href="ministra.php">
    <button>⬅️ Volver al Panel</button>
</a>

</body>
</html>
