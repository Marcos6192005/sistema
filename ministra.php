<?php
session_start();
<<<<<<< HEAD
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require "db.php";

$usuario_id = $_SESSION['usuario_id'];

$stmt = $pdo->prepare("SELECT nombre_completo, correo, estado, id_rol FROM usuarios WHERE id_usuario = :id");
$stmt->execute(['id' => $usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || $usuario['id_rol'] != 1) { 
    echo "Acceso denegado.";
    exit();
}

$nombre = $usuario['nombre_completo'];
$dui = $usuario['correo']; // ⚠️ Cámbialo si luego agregas el campo DUI real
=======
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$nombre = $_SESSION['nombre_completo'] ?? 'Ministra';
$dui = $_SESSION['dui'] ?? 'N/A';
>>>>>>> b88fd3d629337341a1c4cff9acf51386e9cd6ce0
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Ministra</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            padding: 0;
        }
        .header {
            background: #003366;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 20px;
        }
        .contenedor {
            padding: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 6px;
            box-shadow: 0px 2px 4px rgba(0,0,0,0.15);
        }
        a {
            display: inline-block;
            padding: 10px 14px;
            background: #003366;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 4px 0;
        }
        a:hover {
            background: #0055a5;
        }
    </style>
</head>
<body>

<div class="header">
    Panel de la Ministra de Educación<br>
    Bienvenida: <?php echo $nombre; ?> | Identificador: <?php echo $dui; ?>
</div>

<div class="contenedor">

<<<<<<< HEAD
=======
    <!-- NUEVO: Ingresar datos -->
    <div class="card">
        <h2>📝 Ingresar Nuevos Datos</h2>
        <p>Registrar nuevos usuarios, instituciones y centros escolares.</p>

        <a href="registro_director.php">➕ Registrar Director</a><br>
        <a href="registro_subdirector.php">➕ Registrar Subdirector</a><br>
    </div>

    <!-- Reportes Nacionales -->
>>>>>>> b88fd3d629337341a1c4cff9acf51386e9cd6ce0
    <div class="card">
        <h2>📊 Reportes Nacionales</h2>
        <a href="reportes_nacionales.php">Ver reportes nacionales</a>
    </div>

    <div class="card">
        <h2>📈 Estadísticas del Sistema</h2>
        <a href="estadisticas.php">Ver estadísticas</a>
    </div>

    <div class="card">
        <h2>👥 Administrar Usuarios</h2>
        <a href="admin_directores.php">Directores</a><br>
        <a href="admin_subdirectores.php">Subdirectores</a><br>
        <a href="admin_maestros.php">Maestros</a><br>
        <a href="admin_alumnos.php">Alumnos</a>
    </div>

    <div class="card">
        <h2>📚 Historial de Méritos y Deméritos</h2>
        <a href="historial_meritos_demeritos.php">Ver historial</a>
    </div>

    <div class="card">
        <h2>⚙️ Configuraciones del Sistema</h2>
        <a href="configuraciones.php">Configurar sistema</a>
    </div>

    <div class="card">
        <h2>🔓 Cerrar Sesión</h2>
        <a href="logout.php">Cerrar</a>
    </div>

</div>

</body>
</html>
