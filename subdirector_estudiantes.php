<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connect.php';

// Verificar que sea subdirector (rol 3)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 3) {
    header("Location: index.php");
    exit();
}

$nombre = $_SESSION['nombre_completo'];

// Procesar búsqueda
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_grado = isset($_GET['grado']) ? $_GET['grado'] : '';
$filtro_seccion = isset($_GET['seccion']) ? $_GET['seccion'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Obtener lista de grados para el filtro
try {
    $stmt_grados = $conn->query("SELECT id_grado, nombre_grado FROM grado ORDER BY nombre_grado");
    $grados = $stmt_grados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $grados = [];
}

// Obtener lista de secciones para el filtro
try {
    $stmt_secciones = $conn->query("SELECT id_seccion, nombre_seccion, turno FROM seccion ORDER BY nombre_seccion");
    $secciones = $stmt_secciones->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $secciones = [];
}

// Construir query con filtros
$query = "
    SELECT e.*, 
           g.nombre_grado, 
           s.nombre_seccion, 
           s.turno,
           i.nombre_institucion,
           (SELECT COUNT(*) FROM demeritos WHERE id_estudiante = e.id_estudiante) as total_demeritos,
           (SELECT COUNT(*) FROM demeritos WHERE id_estudiante = e.id_estudiante AND estado_validacion = 'Pendiente') as demeritos_pendientes,
           (SELECT COUNT(*) FROM demeritos WHERE id_estudiante = e.id_estudiante AND estado_validacion = 'Aprobado') as demeritos_aprobados
    FROM estudiante e
    INNER JOIN grado g ON e.id_grado = g.id_grado
    INNER JOIN seccion s ON e.id_seccion = s.id_seccion
    LEFT JOIN institucion i ON e.id_institucion = i.id_institucion
    WHERE 1=1
";

$params = [];

if (!empty($buscar)) {
    $query .= " AND (e.nie LIKE :buscar OR e.nombre LIKE :buscar OR e.apellido LIKE :buscar)";
    $params[':buscar'] = "%$buscar%";
}

if (!empty($filtro_grado)) {
    $query .= " AND e.id_grado = :grado";
    $params[':grado'] = $filtro_grado;
}

if (!empty($filtro_seccion)) {
    $query .= " AND e.id_seccion = :seccion";
    $params[':seccion'] = $filtro_seccion;
}

if ($filtro_estado === 'con_demeritos') {
    $query .= " AND (SELECT COUNT(*) FROM demeritos WHERE id_estudiante = e.id_estudiante) > 0";
} elseif ($filtro_estado === 'sin_demeritos') {
    $query .= " AND (SELECT COUNT(*) FROM demeritos WHERE id_estudiante = e.id_estudiante) = 0";
} elseif ($filtro_estado === 'pendientes') {
    $query .= " AND (SELECT COUNT(*) FROM demeritos WHERE id_estudiante = e.id_estudiante AND estado_validacion = 'Pendiente') > 0";
}

$query .= " ORDER BY e.apellido, e.nombre";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $estudiantes = [];
    $error_message = "Error al obtener estudiantes: " . $e->getMessage();
}

// Obtener estadísticas generales
try {
    $stmt_stats = $conn->query("
        SELECT 
            COUNT(*) as total_estudiantes,
            (SELECT COUNT(*) FROM estudiante WHERE (SELECT COUNT(*) FROM demeritos WHERE id_estudiante = estudiante.id_estudiante) > 0) as con_demeritos,
            (SELECT COUNT(*) FROM estudiante WHERE (SELECT COUNT(*) FROM demeritos WHERE id_estudiante = estudiante.id_estudiante) = 0) as sin_demeritos,
            (SELECT COUNT(DISTINCT id_estudiante) FROM demeritos WHERE estado_validacion = 'Pendiente') as con_pendientes
    ");
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_estudiantes' => 0, 'con_demeritos' => 0, 'sin_demeritos' => 0, 'con_pendientes' => 0];
}

// Configuración del menú
$menuItems = [
    ['url' => 'subdirector_panel.php', 'icono' => 'speedometer2', 'texto' => 'Dashboard'],
    ['url' => 'subdirector_estudiantes.php', 'icono' => 'people-fill', 'texto' => 'Estudiantes'],
    ['url' => 'subdirector_maestros.php', 'icono' => 'person-badge-fill', 'texto' => 'Maestros'],
    ['url' => 'subdirector_grados.php', 'icono' => 'collection-fill', 'texto' => 'Grados y Secciones'],
    ['url' => 'subdirector_demeritos.php', 'icono' => 'flag-fill', 'texto' => 'Deméritos'],
    ['url' => 'subdirector_redenciones.php', 'icono' => 'hand-thumbs-up-fill', 'texto' => 'Redenciones'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Estudiantes - Subdirector</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root{--sb-bg:#2c3e50;--sb-hv:#2d3142;--grad:linear-gradient(135deg,#667eea 0%,#764ba2 100%)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#f8f9fa}
        .sb{position:fixed;top:0;left:0;height:100vh;width:260px;background:var(--sb-bg);padding:20px 0;z-index:1000;box-shadow:4px 0 10px rgba(0,0,0,.1);overflow-y:auto}
        .sb-hd{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px}
        .sb-hd h4{color:#fff;font-weight:600;font-size:1.3rem;margin-top:10px}
        .sb-hd i{font-size:2.5rem;color:#667eea}
        .nl{color:rgba(255,255,255,.7);padding:12px 20px;display:flex;align-items:center;text-decoration:none;transition:.3s;margin:5px 10px;border-radius:8px;white-space:nowrap}
        .nl:hover{background:var(--sb-hv);color:#fff;transform:translateX(5px)}
        .nl.active{background:var(--grad);color:#fff}
        .nl i{margin-right:12px;font-size:1.2rem;width:25px;flex-shrink:0}
        .nl span{flex:1}
        .lo{margin-top:20px}
        .lo:hover{background:#dc3545;color:#fff!important}
        .mc{margin-left:260px;padding:30px;min-height:100vh}
        .stat-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.08);text-align:center;transition:.3s;border-left:4px solid}
        .stat-card:hover{transform:translateY(-5px);box-shadow:0 5px 15px rgba(0,0,0,.15)}
        .stat-card h3{font-size:2.5rem;font-weight:700;margin:10px 0}
        .stat-card p{color:#6c757d;margin:0;font-size:.9rem}
        .stat-primary{border-left-color:#667eea}
        .stat-success{border-left-color:#28a745}
        .stat-warning{border-left-color:#ffc107}
        .stat-danger{border-left-color:#dc3545}
        .filter-card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:25px}
        .student-card{background:#fff;border-radius:10px;padding:20px;margin-bottom:15px;box-shadow:0 2px 8px rgba(0,0,0,.08);transition:.3s;border-left:4px solid #667eea}
        .student-card:hover{box-shadow:0 4px 15px rgba(0,0,0,.15);transform:translateX(5px)}
        .student-card.alerta{border-left-color:#dc3545;background:#fff5f5}
        .student-card.pendiente{border-left-color:#ffc107;background:#fffbf0}
        .student-card.limpio{border-left-color:#28a745;background:#f0fff4}
        .badge-custom{padding:6px 12px;font-size:.85rem;border-radius:6px}
        .search-section{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:25px}
        @media(max-width:768px){.sb{width:70px}.sb-hd h4,.nl span{display:none}.nl{justify-content:center}.nl i{margin-right:0}.mc{margin-left:70px}}
    </style>
</head>
<body>

<div class="sb">
    <div class="sb-hd">
        <i class="bi bi-person-workspace"></i>
        <h4>Subdirector</h4>
    </div>
    <nav>
        <?php foreach($menuItems as $item): ?>
        <a href="<?= $item['url'] ?>" class="nl <?= basename($_SERVER['PHP_SELF']) == basename($item['url']) ? 'active' : '' ?>">
            <i class="bi bi-<?= $item['icono'] ?>"></i>
            <span><?= $item['texto'] ?></span>
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
        <h2><i class="bi bi-people-fill"></i> Gestión de Estudiantes</h2>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card stat-primary">
                <i class="bi bi-people-fill" style="font-size:2.5rem;color:#667eea"></i>
                <h3><?= $stats['total_estudiantes'] ?></h3>
                <p>Total Estudiantes</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card stat-success">
                <i class="bi bi-check-circle-fill" style="font-size:2.5rem;color:#28a745"></i>
                <h3><?= $stats['sin_demeritos'] ?></h3>
                <p>Sin Deméritos</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card stat-warning">
                <i class="bi bi-clock-fill" style="font-size:2.5rem;color:#ffc107"></i>
                <h3><?= $stats['con_pendientes'] ?></h3>
                <p>Con Pendientes</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card stat-danger">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:2.5rem;color:#dc3545"></i>
                <h3><?= $stats['con_demeritos'] ?></h3>
                <p>Con Deméritos</p>
            </div>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="search-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-search"></i> Buscar</label>
                <input type="text" name="buscar" class="form-control" placeholder="NIE, nombre o apellido" value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-collection"></i> Grado</label>
                <select name="grado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach($grados as $g): ?>
                    <option value="<?= $g['id_grado'] ?>" <?= $filtro_grado == $g['id_grado'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['nombre_grado']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-grid-3x3"></i> Sección</label>
                <select name="seccion" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach($secciones as $s): ?>
                    <option value="<?= $s['id_seccion'] ?>" <?= $filtro_seccion == $s['id_seccion'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nombre_seccion']) ?> - <?= htmlspecialchars($s['turno']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-funnel"></i> Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="sin_demeritos" <?= $filtro_estado == 'sin_demeritos' ? 'selected' : '' ?>>Sin deméritos</option>
                    <option value="con_demeritos" <?= $filtro_estado == 'con_demeritos' ? 'selected' : '' ?>>Con deméritos</option>
                    <option value="pendientes" <?= $filtro_estado == 'pendientes' ? 'selected' : '' ?>>Con pendientes</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
        </form>
        <?php if (!empty($buscar) || !empty($filtro_grado) || !empty($filtro_seccion) || !empty($filtro_estado)): ?>
        <div class="mt-2">
            <a href="subdirector_estudiantes.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Limpiar filtros
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Lista de estudiantes -->
    <div class="filter-card">
        <h5 class="mb-3">
            <i class="bi bi-list-ul"></i> 
            Listado de Estudiantes 
            <span class="badge bg-primary"><?= count($estudiantes) ?> resultados</span>
        </h5>

        <?php if (empty($estudiantes)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No se encontraron estudiantes con los filtros aplicados.
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach($estudiantes as $est): 
                $clase_card = 'student-card';
                if ($est['demeritos_pendientes'] > 0) {
                    $clase_card .= ' pendiente';
                } elseif ($est['total_demeritos'] > 0) {
                    $clase_card .= ' alerta';
                } else {
                    $clase_card .= ' limpio';
                }
            ?>
            <div class="col-lg-6 col-md-12">
                <div class="<?= $clase_card ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5 class="mb-2">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($est['nombre'] . ' ' . $est['apellido']) ?>
                            </h5>
                            <p class="mb-1 text-muted">
                                <strong>NIE:</strong> <?= htmlspecialchars($est['nie']) ?>
                            </p>
                            <p class="mb-1">
                                <span class="badge bg-primary badge-custom">
                                    <i class="bi bi-book"></i> <?= htmlspecialchars($est['nombre_grado']) ?>
                                </span>
                                <span class="badge bg-info badge-custom">
                                    <i class="bi bi-grid"></i> <?= htmlspecialchars($est['nombre_seccion']) ?> - <?= htmlspecialchars($est['turno']) ?>
                                </span>
                            </p>
                            <div class="mt-2">
                                <?php if ($est['total_demeritos'] == 0): ?>
                                <span class="badge bg-success badge-custom">
                                    <i class="bi bi-emoji-smile"></i> Sin deméritos
                                </span>
                                <?php else: ?>
                                <span class="badge bg-danger badge-custom">
                                    <i class="bi bi-flag-fill"></i> <?= $est['total_demeritos'] ?> deméritos
                                </span>
                                <?php if ($est['demeritos_pendientes'] > 0): ?>
                                <span class="badge bg-warning text-dark badge-custom">
                                    <i class="bi bi-clock"></i> <?= $est['demeritos_pendientes'] ?> pendientes
                                </span>
                                <?php endif; ?>
                                <?php if ($est['demeritos_aprobados'] > 0): ?>
                                <span class="badge bg-secondary badge-custom">
                                    <i class="bi bi-check-circle"></i> <?= $est['demeritos_aprobados'] ?> confirmados
                                </span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ms-3">
                            <a href="subdirector_estudiante_detalle.php?id=<?= $est['id_estudiante'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Ver detalle
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Leyenda -->
    <div class="card mt-3">
        <div class="card-body">
            <h6 class="card-title"><i class="bi bi-info-circle"></i> Leyenda de colores</h6>
            <div class="row">
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#28a745;border-radius:3px;margin-right:10px"></div>
                        <span>Sin deméritos (Excelente conducta)</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#ffc107;border-radius:3px;margin-right:10px"></div>
                        <span>Con deméritos pendientes de confirmar</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#dc3545;border-radius:3px;margin-right:10px"></div>
                        <span>Con deméritos confirmados</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>