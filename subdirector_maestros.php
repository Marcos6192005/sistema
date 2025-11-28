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

// Procesar búsqueda y filtros
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_secciones = isset($_GET['secciones']) ? $_GET['secciones'] : '';

// Construir query con filtros
$query = "
    SELECT m.*, u.correo, u.estado,
           i.nombre_institucion,
           COUNT(DISTINCT ms.id_asignacion) as total_secciones,
           GROUP_CONCAT(
               CONCAT(g.nombre_grado, ' - ', s.nombre_seccion, ' (', s.turno, ')')
               SEPARATOR ', '
           ) as secciones_asignadas
    FROM maestros m
    INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
    LEFT JOIN institucion i ON m.id_institucion = i.id_institucion
    LEFT JOIN maestro_seccion ms ON m.id_maestro = ms.id_maestro
    LEFT JOIN seccion s ON ms.id_seccion = s.id_seccion
    LEFT JOIN grado g ON s.id_grado = g.id_grado
    WHERE 1=1
";

$params = [];

if (!empty($buscar)) {
    $query .= " AND (m.nombre LIKE :buscar OR m.apellido LIKE :buscar OR u.correo LIKE :buscar)";
    $params[':buscar'] = "%$buscar%";
}

if ($filtro_estado === 'activo') {
    $query .= " AND u.estado = TRUE";
} elseif ($filtro_estado === 'inactivo') {
    $query .= " AND u.estado = FALSE";
}

$query .= " GROUP BY m.id_maestro, m.id_usuario, m.nombre, m.apellido, m.direccion, 
            m.f_nacimiento, m.id_institucion, m.fecha_registro, u.correo, u.estado, i.nombre_institucion";

if ($filtro_secciones === 'con_secciones') {
    $query .= " HAVING total_secciones > 0";
} elseif ($filtro_secciones === 'sin_secciones') {
    $query .= " HAVING total_secciones = 0";
}

$query .= " ORDER BY m.apellido, m.nombre";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $maestros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $maestros = [];
    $error_message = "Error al obtener maestros: " . $e->getMessage();
}

// Obtener estadísticas generales
try {
    $stmt_stats = $conn->query("
        SELECT 
            COUNT(*) as total_maestros,
            SUM(CASE WHEN u.estado = TRUE THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN u.estado = FALSE THEN 1 ELSE 0 END) as inactivos,
            (SELECT COUNT(DISTINCT m2.id_maestro) 
             FROM maestros m2 
             LEFT JOIN maestro_seccion ms2 ON m2.id_maestro = ms2.id_maestro 
             WHERE ms2.id_asignacion IS NOT NULL) as con_secciones,
            (SELECT COUNT(DISTINCT m2.id_maestro) 
             FROM maestros m2 
             LEFT JOIN maestro_seccion ms2 ON m2.id_maestro = ms2.id_maestro 
             WHERE ms2.id_asignacion IS NULL) as sin_secciones
        FROM maestros m
        INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
    ");
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_maestros' => 0, 'activos' => 0, 'inactivos' => 0, 'con_secciones' => 0, 'sin_secciones' => 0];
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
    <title>Gestión de Maestros - Subdirector</title>
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
        .stat-info{border-left-color:#17a2b8}
        .maestro-card{background:#fff;border-radius:10px;padding:20px;margin-bottom:15px;box-shadow:0 2px 8px rgba(0,0,0,.08);transition:.3s;border-left:4px solid #667eea}
        .maestro-card:hover{box-shadow:0 4px 15px rgba(0,0,0,.15);transform:translateX(5px)}
        .maestro-card.activo{border-left-color:#28a745;background:#f0fff4}
        .maestro-card.inactivo{border-left-color:#dc3545;background:#fff5f5}
        .maestro-card.sin-asignacion{border-left-color:#ffc107;background:#fffbf0}
        .badge-custom{padding:6px 12px;font-size:.85rem;border-radius:6px}
        .search-section{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:25px}
        .secciones-list{font-size:.85rem;color:#6c757d;margin-top:8px;line-height:1.6}
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
        <h2><i class="bi bi-person-badge-fill"></i> Gestión de Maestros</h2>
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
                <i class="bi bi-person-badge-fill" style="font-size:2.5rem;color:#667eea"></i>
                <h3><?= $stats['total_maestros'] ?></h3>
                <p>Total Maestros</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card stat-success">
                <i class="bi bi-check-circle-fill" style="font-size:2.5rem;color:#28a745"></i>
                <h3><?= $stats['activos'] ?></h3>
                <p>Activos</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card stat-info">
                <i class="bi bi-collection-fill" style="font-size:2.5rem;color:#17a2b8"></i>
                <h3><?= $stats['con_secciones'] ?></h3>
                <p>Con Secciones</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card stat-warning">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:2.5rem;color:#ffc107"></i>
                <h3><?= $stats['sin_secciones'] ?></h3>
                <p>Sin Asignación</p>
            </div>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="search-section">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><i class="bi bi-search"></i> Buscar</label>
                <input type="text" name="buscar" class="form-control" placeholder="Nombre, apellido o correo" value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-funnel"></i> Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="activo" <?= $filtro_estado == 'activo' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $filtro_estado == 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-diagram-3"></i> Asignaciones</label>
                <select name="secciones" class="form-select">
                    <option value="">Todos</option>
                    <option value="con_secciones" <?= $filtro_secciones == 'con_secciones' ? 'selected' : '' ?>>Con secciones</option>
                    <option value="sin_secciones" <?= $filtro_secciones == 'sin_secciones' ? 'selected' : '' ?>>Sin secciones</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
        </form>
        <?php if (!empty($buscar) || !empty($filtro_estado) || !empty($filtro_secciones)): ?>
        <div class="mt-2">
            <a href="subdirector_maestros.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Limpiar filtros
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Lista de maestros -->
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="bi bi-list-ul"></i> 
                Listado de Maestros 
                <span class="badge bg-light text-dark"><?= count($maestros) ?> resultados</span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($maestros)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No se encontraron maestros con los filtros aplicados.
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach($maestros as $m): 
                    $clase_card = 'maestro-card';
                    if (!$m['estado']) {
                        $clase_card .= ' inactivo';
                    } elseif ($m['total_secciones'] == 0) {
                        $clase_card .= ' sin-asignacion';
                    } else {
                        $clase_card .= ' activo';
                    }
                ?>
                <div class="col-lg-6 col-md-12">
                    <div class="<?= $clase_card ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h5 class="mb-2">
                                    <i class="bi bi-person-circle"></i>
                                    <?= htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?>
                                </h5>
                                <p class="mb-1 text-muted">
                                    <i class="bi bi-envelope"></i> 
                                    <small><?= htmlspecialchars($m['correo']) ?></small>
                                </p>
                                <?php if ($m['direccion']): ?>
                                <p class="mb-1 text-muted">
                                    <i class="bi bi-geo-alt"></i> 
                                    <small><?= htmlspecialchars($m['direccion']) ?></small>
                                </p>
                                <?php endif; ?>
                                <?php if ($m['nombre_institucion']): ?>
                                <p class="mb-2 text-muted">
                                    <i class="bi bi-building"></i> 
                                    <small><?= htmlspecialchars($m['nombre_institucion']) ?></small>
                                </p>
                                <?php endif; ?>
                                
                                <div class="mt-2">
                                    <?php if ($m['estado']): ?>
                                    <span class="badge bg-success badge-custom">
                                        <i class="bi bi-check-circle"></i> Activo
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-danger badge-custom">
                                        <i class="bi bi-x-circle"></i> Inactivo
                                    </span>
                                    <?php endif; ?>
                                    
                                    <span class="badge bg-info badge-custom">
                                        <i class="bi bi-collection"></i> <?= $m['total_secciones'] ?> secciones
                                    </span>
                                    
                                    <?php if ($m['f_nacimiento']): ?>
                                    <span class="badge bg-secondary badge-custom">
                                        <i class="bi bi-calendar"></i> 
                                        <?= date('d/m/Y', strtotime($m['f_nacimiento'])) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($m['secciones_asignadas']): ?>
                                <div class="secciones-list">
                                    <strong><i class="bi bi-diagram-3"></i> Secciones asignadas:</strong><br>
                                    <?= htmlspecialchars($m['secciones_asignadas']) ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning alert-sm mt-2 mb-0" style="padding:8px;font-size:.85rem">
                                    <i class="bi bi-exclamation-triangle"></i> Sin secciones asignadas
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="ms-3 text-end">
                                <a href="subdirector_maestro_detalle.php?id=<?= $m['id_maestro'] ?>" class="btn btn-sm btn-outline-primary mb-2">
                                    <i class="bi bi-eye"></i> Ver detalle
                                </a>
                                <br>
                                <small class="text-muted">
                                    ID: <?= $m['id_maestro'] ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Leyenda -->
    <div class="card mt-3">
        <div class="card-body">
            <h6 class="card-title"><i class="bi bi-info-circle"></i> Leyenda de colores</h6>
            <div class="row">
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#28a745;border-radius:3px;margin-right:10px"></div>
                        <span>Maestros activos con secciones asignadas</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#ffc107;border-radius:3px;margin-right:10px"></div>
                        <span>Maestros activos sin secciones asignadas</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#dc3545;border-radius:3px;margin-right:10px"></div>
                        <span>Maestros inactivos</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nota informativa -->
    <div class="alert alert-info mt-3">
        <i class="bi bi-info-circle-fill"></i> 
        <strong>Nota:</strong> Como subdirector, puedes visualizar y consultar la información de los maestros. 
        Para realizar modificaciones (agregar, editar o asignar secciones), contacta al director de la institución.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>