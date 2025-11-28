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

// Obtener filtros
$filtro_grado = isset($_GET['grado']) ? $_GET['grado'] : '';
$filtro_turno = isset($_GET['turno']) ? $_GET['turno'] : '';
$buscar = isset($_GET['buscar']) ? $_GET['buscar'] : '';

// Consultar grados y secciones con información de maestros encargados
try {
    $sql = "
        SELECT 
            g.id_grado,
            g.nombre_grado,
            g.estado as grado_estado,
            s.id_seccion,
            s.nombre_seccion,
            s.turno,
            s.estado as seccion_estado,
            COUNT(DISTINCT e.id_estudiante) as total_estudiantes,
            COUNT(DISTINCT ms.id_maestro) as total_maestros,
            GROUP_CONCAT(
                CONCAT(m.nombre, ' ', m.apellido)
                SEPARATOR ', '
            ) as maestros_asignados
        FROM grado g
        LEFT JOIN seccion s ON g.id_grado = s.id_grado
        LEFT JOIN estudiante e ON s.id_seccion = e.id_seccion
        LEFT JOIN maestro_seccion ms ON s.id_seccion = ms.id_seccion
        LEFT JOIN maestros m ON ms.id_maestro = m.id_maestro
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filtro_grado) {
        $sql .= " AND g.id_grado = :grado";
        $params[':grado'] = $filtro_grado;
    }
    
    if ($filtro_turno) {
        $sql .= " AND s.turno = :turno";
        $params[':turno'] = $filtro_turno;
    }
    
    if ($buscar) {
        $sql .= " AND (g.nombre_grado ILIKE :buscar OR s.nombre_seccion ILIKE :buscar)";
        $params[':buscar'] = "%$buscar%";
    }
    
    $sql .= " GROUP BY g.id_grado, g.nombre_grado, g.estado, s.id_seccion, s.nombre_seccion, s.turno, s.estado";
    $sql .= " ORDER BY g.nombre_grado, s.nombre_seccion";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar por grados
    $grados = [];
    foreach ($resultados as $row) {
        $id_grado = $row['id_grado'];
        if (!isset($grados[$id_grado])) {
            $grados[$id_grado] = [
                'nombre_grado' => $row['nombre_grado'],
                'estado' => $row['grado_estado'],
                'secciones' => [],
                'total_estudiantes' => 0,
                'total_maestros' => 0
            ];
        }
        if ($row['id_seccion']) {
            $grados[$id_grado]['secciones'][] = [
                'id_seccion' => $row['id_seccion'],
                'nombre_seccion' => $row['nombre_seccion'],
                'turno' => $row['turno'],
                'estado' => $row['seccion_estado'],
                'total_estudiantes' => $row['total_estudiantes'],
                'total_maestros' => $row['total_maestros'],
                'maestros_asignados' => $row['maestros_asignados']
            ];
            $grados[$id_grado]['total_estudiantes'] += $row['total_estudiantes'];
            $grados[$id_grado]['total_maestros'] += $row['total_maestros'];
        }
    }
    
    // Obtener lista de grados para el filtro
    $stmt_grados = $conn->query("SELECT id_grado, nombre_grado FROM grado WHERE estado = TRUE ORDER BY nombre_grado");
    $lista_grados = $stmt_grados->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Obtener estadísticas generales
try {
    $stmt_stats = $conn->query("
        SELECT 
            COUNT(DISTINCT g.id_grado) as total_grados,
            COUNT(DISTINCT s.id_seccion) as total_secciones,
            COUNT(DISTINCT e.id_estudiante) as total_estudiantes,
            COUNT(DISTINCT ms.id_maestro) as total_maestros_asignados
        FROM grado g
        LEFT JOIN seccion s ON g.id_grado = s.id_grado
        LEFT JOIN estudiante e ON s.id_seccion = e.id_seccion
        LEFT JOIN maestro_seccion ms ON s.id_seccion = ms.id_seccion
        WHERE g.estado = TRUE AND s.estado = TRUE
    ");
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_grados' => 0, 'total_secciones' => 0, 'total_estudiantes' => 0, 'total_maestros_asignados' => 0];
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
    <title>Grados y Secciones - Subdirector</title>
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
        .stat-info{border-left-color:#17a2b8}
        .filter-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
        .search-box{position:relative}
        .search-box input{padding-left:40px;border-radius:25px;border:2px solid #e9ecef}
        .search-box input:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,.25)}
        .search-box i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#6c757d}
        .grado-card{background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px;border-left:5px solid #667eea;transition:.3s}
        .grado-card:hover{transform:translateX(5px);box-shadow:0 4px 15px rgba(0,0,0,.12)}
        .grado-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .grado-header h4{margin:0;color:#2d3142;font-weight:700}
        .stat-badge{background:#667eea;color:#fff;padding:8px 16px;border-radius:20px;font-weight:600;font-size:.9rem}
        .stat-badge i{margin-right:5px}
        .seccion-item{background:#f8f9fa;border-radius:8px;padding:18px;margin-bottom:10px;transition:.3s;border-left:3px solid #dee2e6}
        .seccion-item:hover{background:#e9ecef;transform:translateX(3px);border-left-color:#667eea}
        .seccion-header{display:flex;justify-content:between;align-items:start;gap:15px}
        .seccion-icon{background:#667eea;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
        .seccion-content{flex:1}
        .seccion-title{font-size:1.1rem;font-weight:700;color:#2d3142;margin-bottom:8px}
        .seccion-badges{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
        .seccion-badge{padding:5px 12px;border-radius:20px;font-size:.8rem;font-weight:600;display:inline-flex;align-items:center;gap:5px}
        .turno-matutino{background:#fff3cd;color:#856404}
        .turno-vespertino{background:#cfe2ff;color:#084298}
        .maestros-list{font-size:.85rem;color:#6c757d;margin-top:8px;padding-top:8px;border-top:1px dashed #dee2e6}
        .maestros-list strong{color:#495057}
        .estudiantes-badge{background:#e7f3ff;color:#0056b3;padding:5px 12px;border-radius:20px;font-size:.85rem;font-weight:600}
        .sin-maestros{color:#dc3545;font-size:.85rem;font-style:italic}
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
                <h2><i class="bi bi-collection-fill text-warning"></i> Grados y Secciones</h2>
                <p class="text-muted mb-0">Organización académica de la institución</p>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card stat-primary">
                    <i class="bi bi-book-fill" style="font-size:2.5rem;color:#667eea"></i>
                    <h3><?= $stats['total_grados'] ?></h3>
                    <p>Grados Activos</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card stat-info">
                    <i class="bi bi-grid-3x3-gap-fill" style="font-size:2.5rem;color:#17a2b8"></i>
                    <h3><?= $stats['total_secciones'] ?></h3>
                    <p>Secciones Activas</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card stat-success">
                    <i class="bi bi-people-fill" style="font-size:2.5rem;color:#28a745"></i>
                    <h3><?= $stats['total_estudiantes'] ?></h3>
                    <p>Total Estudiantes</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card stat-warning">
                    <i class="bi bi-person-badge-fill" style="font-size:2.5rem;color:#ffc107"></i>
                    <h3><?= $stats['total_maestros_asignados'] ?></h3>
                    <p>Maestros Asignados</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar grado o sección" value="<?= htmlspecialchars($buscar) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="grado" class="form-select">
                        <option value="">Todos los grados</option>
                        <?php foreach($lista_grados as $g): ?>
                        <option value="<?= $g['id_grado'] ?>" <?= $filtro_grado == $g['id_grado'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nombre_grado']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="turno" class="form-select">
                        <option value="">Todos los turnos</option>
                        <option value="Matutino" <?= $filtro_turno == 'Matutino' ? 'selected' : '' ?>>Matutino</option>
                        <option value="Vespertino" <?= $filtro_turno == 'Vespertino' ? 'selected' : '' ?>>Vespertino</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
            </form>
            <?php if (!empty($buscar) || !empty($filtro_grado) || !empty($filtro_turno)): ?>
            <div class="mt-2">
                <a href="subdirector_grados.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Limpiar filtros
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lista de Grados y Secciones -->
        <?php if (empty($grados)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No se encontraron grados con los filtros seleccionados.
        </div>
        <?php else: ?>
        <?php foreach ($grados as $grado): ?>
        <div class="grado-card">
            <div class="grado-header">
                <div>
                    <h4><i class="bi bi-book-fill"></i> <?= htmlspecialchars($grado['nombre_grado']) ?></h4>
                    <small class="text-muted">
                        <i class="bi bi-grid-3x3-gap"></i>
                        <?= count($grado['secciones']) ?> <?= count($grado['secciones']) == 1 ? 'sección' : 'secciones' ?>
                        • 
                        <i class="bi bi-person-badge"></i>
                        <?= $grado['total_maestros'] ?> <?= $grado['total_maestros'] == 1 ? 'maestro' : 'maestros' ?>
                    </small>
                </div>
                <div class="stat-badge">
                    <i class="bi bi-people-fill"></i><?= $grado['total_estudiantes'] ?> estudiantes
                </div>
            </div>

            <?php if (empty($grado['secciones'])): ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle"></i> Este grado no tiene secciones asignadas.
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($grado['secciones'] as $seccion): ?>
                <div class="col-lg-6 col-md-12">
                    <div class="seccion-item">
                        <div class="seccion-header">
                            <div class="seccion-icon">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </div>
                            <div class="seccion-content">
                                <div class="seccion-title">
                                    Sección <?= htmlspecialchars($seccion['nombre_seccion']) ?>
                                </div>
                                <div class="seccion-badges">
                                    <span class="seccion-badge <?= $seccion['turno'] == 'Matutino' ? 'turno-matutino' : 'turno-vespertino' ?>">
                                        <i class="bi bi-<?= $seccion['turno'] == 'Matutino' ? 'sun' : 'moon' ?>-fill"></i>
                                        <?= htmlspecialchars($seccion['turno']) ?>
                                    </span>
                                    <span class="estudiantes-badge">
                                        <i class="bi bi-people-fill"></i>
                                        <?= $seccion['total_estudiantes'] ?> estudiantes
                                    </span>
                                    <span class="badge bg-info">
                                        <i class="bi bi-person-badge"></i>
                                        <?= $seccion['total_maestros'] ?> <?= $seccion['total_maestros'] == 1 ? 'maestro' : 'maestros' ?>
                                    </span>
                                </div>
                                <div class="maestros-list">
                                    <?php if ($seccion['maestros_asignados']): ?>
                                        <strong><i class="bi bi-person-check-fill"></i> Encargados:</strong>
                                        <?= htmlspecialchars($seccion['maestros_asignados']) ?>
                                    <?php else: ?>
                                        <span class="sin-maestros">
                                            <i class="bi bi-exclamation-circle"></i> Sin maestros asignados
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Nota informativa -->
        <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle-fill"></i> 
            <strong>Nota:</strong> Como subdirector, puedes visualizar la organización académica completa. 
            Para realizar cambios en grados, secciones o asignaciones, contacta al director de la institución.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>