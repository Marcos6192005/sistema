<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$nombre = $_SESSION['nombre_completo'] ?? 'Ministra';
$dui = $_SESSION['dui'] ?? 'N/A';
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
        .card h2 {
            margin-top: 0;
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
    Bienvenida: <?php echo $nombre; ?> | DUI: <?php echo $dui; ?>
</div>

<div class="contenedor">

    <!-- NUEVO: Ingresar datos -->
    <div class="card">
        <h2>📝 Ingresar Nuevos Datos</h2>
        <p>Registrar nuevos usuarios, instituciones y centros escolares.</p>

        <a href="registro_director.php">➕ Registrar Director</a><br>
        <a href="registro_subdirector.php">➕ Registrar Subdirector</a><br>
    </div>

    <!-- Reportes Nacionales -->
    <div class="card">
        <h2>📊 Reportes Nacionales</h2>
        <p>Consulte los reportes de méritos y deméritos a nivel nacional.</p>
        <a href="reportes_nacionales.php">Ver reportes nacionales</a>
    </div>

    <!-- Estadísticas Generales -->
    <div class="card">
        <h2>📈 Estadísticas del Sistema</h2>
        <p>Visualice estadísticas globales de estudiantes, directores, maestros y más.</p>
        <a href="estadisticas.php">Ver estadísticas</a>
    </div>

    <!-- Administrar usuarios -->
    <div class="card">
        <h2>👥 Administrar Usuarios</h2>
        <p>Gestione directores, subdirectores, maestros y alumnos.</p>
        
        <a href="admin_directores.php">Directores</a><br>
        <a href="admin_subdirectores.php">Subdirectores</a><br>
        <a href="admin_maestros.php">Maestros</a><br>
        <a href="admin_alumnos.php">Alumnos</a>
    </div>

    <!-- Historial -->
    <div class="card">
        <h2>📚 Historial de Méritos y Deméritos</h2>
        <p>Revise los registros históricos de todo el sistema.</p>
        <a href="historial_meritos_demeritos.php">Ver historial</a>
    </div>

    <!-- Configuración del sistema -->
    <div class="card">
        <h2>⚙️ Configuraciones del Sistema</h2>
        <p>Opciones avanzadas para modificar parámetros y reglas del sistema.</p>
        <a href="configuraciones.php">Configurar sistema</a>
    </div>

    <!-- Cerrar sesión -->
    <div class="card">
        <h2>🔓 Cerrar Sesión</h2>
        <a href="logout.php">Cerrar</a>
    </div>

</div>

</body>
</html>
