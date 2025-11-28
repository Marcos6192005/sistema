<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 2) {
    header("Location: index.php");
    exit();
}

$nombre = $_SESSION['nombre_completo'];

// Obtener filtros
$filtro_grado = isset($_GET['grado']) ? $_GET['grado'] : '';
$filtro_seccion = isset($_GET['seccion']) ? $_GET['seccion'] : '';
$buscar = isset($_GET['buscar']) ? $_GET['buscar'] : '';

// Consultar estudiantes
try {
    $sql = "
        SELECT 
            e.id_estudiante,
            e.nie,
            e.nombre,
            e.apellido,
            g.nombre_grado,
            s.nombre_seccion,
            s.turno
        FROM estudiante e
        INNER JOIN grado g ON e.id_grado = g.id_grado
        INNER JOIN seccion s ON e.id_seccion = s.id_seccion
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filtro_grado) {
        $sql .= " AND e.id_grado = :grado";
        $params[':grado'] = $filtro_grado;
    }
    
    if ($filtro_seccion) {
        $sql .= " AND e.id_seccion = :seccion";
        $params[':seccion'] = $filtro_seccion;
    }
    
    if ($buscar) {
        $sql .= " AND (e.nombre ILIKE :buscar OR e.apellido ILIKE :buscar OR e.nie ILIKE :buscar)";
        $params[':buscar'] = "%$buscar%";
    }
    
    $sql .= " ORDER BY g.nombre_grado, s.nombre_seccion, e.apellido, e.nombre";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener grados para el filtro
    $stmt_grados = $conn->query("SELECT id_grado, nombre_grado FROM grado ORDER BY nombre_grado");
    $grados = $stmt_grados->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener secciones para el filtro
    $stmt_secciones = $conn->query("SELECT id_seccion, nombre_seccion FROM seccion ORDER BY nombre_seccion");
    $secciones = $stmt_secciones->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Configuración del menú
$menuItems = [
    ['url' => 'director.php', 'icono' => 'speedometer2', 'texto' => 'Dashboard'],
    ['url' => 'estudiantes.php', 'icono' => 'people-fill', 'texto' => 'Estudiantes'],
    ['url' => 'maestros.php', 'icono' => 'person-badge-fill', 'texto' => 'Maestros'],
    ['url' => 'grados.php', 'icono' => 'collection-fill', 'texto' => 'Grados y Secciones'],
    ['url' => 'demeritos.php', 'icono' => 'flag-fill', 'texto' => 'Deméritos'],
    ['url' => 'redenciones.php', 'icono' => 'hand-thumbs-up-fill', 'texto' => 'Redenciones'],
    ['url' => 'reportes.php', 'icono' => 'file-earmark-bar-graph-fill', 'texto' => 'Reportes']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiantes - Director</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root{--sb-bg:#1a1d29;--sb-hv:#2d3142}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#f8f9fa}
        .sb{position:fixed;top:0;left:0;height:100vh;width:260px;background:var(--sb-bg);padding:20px 0;z-index:1000;box-shadow:4px 0 10px rgba(0,0,0,.1);overflow-y:auto}
        .sb-hd{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px}
        .sb-hd h4{color:#fff;font-weight:600;font-size:1.3rem;margin-top:10px}
        .sb-hd i{font-size:2.5rem;color:#667eea}
        .nl{color:rgba(255,255,255,.7);padding:12px 20px;display:flex;align-items:center;text-decoration:none;transition:.3s;margin:5px 10px;border-radius:8px;white-space:nowrap}
        .nl:hover{background:var(--sb-hv);color:#fff;transform:translateX(5px)}
        .nl.active{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
        .nl i{margin-right:12px;font-size:1.2rem;width:25px;flex-shrink:0}
        .nl span{flex:1}
        .lo{margin-top:20px;width:calc(100% - 20px);margin-left:10px;margin-right:10px}
        .lo:hover{background:#dc3545;color:#fff!important}
        .mc{margin-left:260px;padding:30px;min-height:100vh}
        .card{border:none;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
        .table-container{background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
        .table{margin:0}
        .table thead{background:#f8f9fa;border-bottom:2px solid #667eea}
        .table thead th{color:#2d3142;font-weight:600;border:none;padding:15px}
        .table tbody td{padding:15px;vertical-align:middle;border-color:#f0f0f0}
        .badge-turno{padding:6px 12px;border-radius:20px;font-size:.85rem}
        .search-box{position:relative}
        .search-box input{padding-left:40px;border-radius:25px;border:2px solid #e9ecef}
        .search-box input:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,.25)}
        .search-box i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#6c757d}
        .filter-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
        @media(max-width:768px){.sb{width:70px}.sb-hd h4,.nl span{display:none}.nl{justify-content:center}.nl i{margin-right:0}.mc{margin-left:70px}}
    </style>
</head>
<body>
    <div class="sb">
        <div class="sb-hd">
            <i class="bi bi-mortarboard-fill"></i>
            <h4>Director</h4>
        </div>
        <nav>
            <?php foreach($menuItems as $i): ?>
            <a href="<?= $i['url'] ?>" class="nl <?= basename($_SERVER['PHP_SELF']) == basename($i['url']) ? 'active' : '' ?>">
                <i class="bi bi-<?= $i['icono'] ?>"></i>
                <span><?= $i['texto'] ?></span>
            </a>
            <?php endforeach; ?>
            <a href="logout.php" class="nl lo">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar sesión</span>
            </a>
        </nav>
    </div>

    <div class="mc">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-people-fill text-primary"></i> Estudiantes</h2>
                <p class="text-muted mb-0">Total de estudiantes: <strong><?= count($estudiantes) ?></strong></p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o NIE" value="<?= htmlspecialchars($buscar) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="grado" class="form-select">
                        <option value="">Todos los grados</option>
                        <?php foreach($grados as $g): ?>
                        <option value="<?= $g['id_grado'] ?>" <?= $filtro_grado == $g['id_grado'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nombre_grado']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="seccion" class="form-select">
                        <option value="">Todas las secciones</option>
                        <?php foreach($secciones as $s): ?>
                        <option value="<?= $s['id_seccion'] ?>" <?= $filtro_seccion == $s['id_seccion'] ? 'selected' : '' ?>>
                            Sección <?= htmlspecialchars($s['nombre_seccion']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabla de estudiantes -->
        <div class="table-container">
            <?php if (empty($estudiantes)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No se encontraron estudiantes con los filtros seleccionados.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>NIE</th>
                            <th>Estudiante</th>
                            <th>Grado</th>
                            <th>Sección</th>
                            <th>Turno</th>
                            <th>Maestro Responsable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($estudiantes as $est): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($est['nie']) ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                        <i class="bi bi-person-fill text-primary"></i>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($est['nombre'] . ' ' . $est['apellido']) ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($est['nombre_grado']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($est['nombre_seccion']) ?></span></td>
                            <td>
                                <span class="badge-turno <?= $est['turno'] == 'Matutino' ? 'bg-warning' : 'bg-info' ?> text-dark">
                                    <i class="bi bi-<?= $est['turno'] == 'Matutino' ? 'sun' : 'moon' ?>-fill"></i>
                                    <?= htmlspecialchars($est['turno']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted"><i class="bi bi-dash-circle"></i> No disponible</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>