<?php
session_start();

// Si no es director (rol 2), sacarlo
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 2) {
    header("Location: login.php");
    exit();
}

$nombre = $_SESSION['nombre_completo'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard Director</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    body {
        background: #f5f7fa;
    }
    .card {
        border-radius: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transition: .2s ease;
    }
    .card:hover {
        transform: translateY(-4px);
    }
    .sidebar {
        height: 100vh;
        background: #154360;
        padding-top: 25px;
    }
    .sidebar a {
        color: #fff;
        font-size: 1rem;
        padding: 12px;
        display: block;
        border-radius: 6px;
        margin-bottom: 10px;
        text-decoration: none;
    }
    .sidebar a:hover {
        background: #1b4f72;
    }
</style>

</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <h4 class="text-white text-center mb-4">
                <i class="bi bi-mortarboard-fill"></i> Director
            </h4>

            <a href="director.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="director_estudiantes.php"><i class="bi bi-people-fill"></i> Estudiantes</a>
            <a href="director_maestros.php"><i class="bi bi-person-badge-fill"></i> Maestros</a>
            <a href="director_grados.php"><i class="bi bi-collection-fill"></i> Grados y secciones</a>
            <a href="director_demeritos.php"><i class="bi bi-flag-fill"></i> Deméritos</a>
            <a href="director_redenciones.php"><i class="bi bi-hand-thumbs-up-fill"></i> Redenciones</a>
            <a href="reportes.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Reportes</a>
            <a href="logout.php" class="mt-5 text-danger"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
        </div>

        <!-- Contenido principal -->
        <div class="col-md-10 p-4">

            <h2 class="mb-4">Bienvenido, <?php echo $nombre; ?></h2>

            <div class="row g-4">

                <!-- Cards del dashboard -->
                <div class="col-md-3">
                    <div class="card p-3 text-center">
                        <i class="bi bi-people-fill text-primary" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2">Estudiantes</h5>
                        <p class="text-muted">Administración general</p>
                        <a href="director_estudiantes.php" class="btn btn-outline-primary btn-sm">Ver más</a>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 text-center">
                        <i class="bi bi-person-badge-fill text-success" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2">Maestros</h5>
                        <p class="text-muted">Supervisión docente</p>
                        <a href="director_maestros.php" class="btn btn-outline-success btn-sm">Ver más</a>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 text-center">
                        <i class="bi bi-collection-fill text-warning" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2">Grados y Secciones</h5>
                        <p class="text-muted">Gestión académica</p>
                        <a href="director_grados.php" class="btn btn-outline-warning btn-sm">Ver más</a>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 text-center">
                        <i class="bi bi-flag-fill text-danger" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2">Deméritos</h5>
                        <p class="text-muted">Revisar conductas</p>
                        <a href="director_demeritos.php" class="btn btn-outline-danger btn-sm">Ver más</a>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 text-center">
                        <i class="bi bi-hand-thumbs-up-fill text-info" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2">Redenciones</h5>
                        <p class="text-muted">Actividades correctivas</p>
                        <a href="director_redenciones.php" class="btn btn-outline-info btn-sm">Ver más</a>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 text-center">
                        <i class="bi bi-file-earmark-bar-graph-fill text-secondary" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2">Reportes</h5>
                        <p class="text-muted">Generación de informes</p>
                        <a href="reportes.php" class="btn btn-outline-secondary btn-sm">Ver más</a>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

</body>
</html>
