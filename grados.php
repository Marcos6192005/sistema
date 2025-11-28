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
$buscar = isset($_GET['buscar']) ? $_GET['buscar'] : '';

// Consultar grados y secciones
try {
    $sql = "
        SELECT 
            g.id_grado,
            g.nombre_grado,
            s.id_seccion,
            s.nombre_seccion,
            s.turno,
            COUNT(e.id_estudiante) as total_estudiantes
        FROM grado g
        LEFT JOIN seccion s ON g.id_grado = s.id_grado
        LEFT JOIN estudiante e ON s.id_seccion = e.id_seccion
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filtro_grado) {
        $sql .= " AND g.id_grado = :grado";
        $params[':grado'] = $filtro_grado;
    }
    
    if ($buscar) {
        $sql .= " AND (g.nombre_grado ILIKE :buscar OR s.nombre_seccion ILIKE :buscar)";
        $params[':buscar'] = "%$buscar%";
    }
    
    $sql .= " GROUP BY g.id_grado, g.nombre_grado, s.id_seccion, s.nombre_seccion, s.turno";
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
                'secciones' => [],
                'total_estudiantes' => 0
            ];
        }
        if ($row['id_seccion']) {
            $grados[$id_grado]['secciones'][] = [
                'nombre_seccion' => $row['nombre_seccion'],
                'turno' => $row['turno'],
                'total_estudiantes' => $row['total_estudiantes']
            ];
            $grados[$id_grado]['total_estudiantes'] += $row['total_estudiantes'];
        }
    }
    
    // Obtener lista de grados para el filtro
    $stmt_grados = $conn->query("SELECT id_grado, nombre_grado FROM grado ORDER BY nombre_grado");
    $lista_grados = $stmt_grados->fetchAll(PDO::FETCH_ASSOC);
    
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
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grados y Secciones - Director</title>
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
        .filter-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
        .search-box{position:relative}
        .search-box input{padding-left:40px;border-radius:25px;border:2px solid #e9ecef}
        .search-box input:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,.25)}
        .search-box i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#6c757d}
        .grado-card{background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px;border-left:5px solid #667eea;transition:.3s}
        .grado-card:hover{transform:translateX(5px);box-shadow:0 4px 15px rgba(0,0,0,.12)}
        .grado-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .grado-header h4{margin:0;color:#2d3142;font-weight:700}
        .stat-badge{background:#667eea;color:#fff;padding:8px 16px;border-radius:20px;font-weight:600}
        .seccion-item{background:#f8f9fa;border-radius:8px;padding:15px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;transition:.3s}
        .seccion-item:hover{background:#e9ecef;transform:translateX(3px)}
        .seccion-info{display:flex;align-items:center;gap:15px}
        .seccion-badge{padding:6px 12px;border-radius:20px;font-size:.85rem;font-weight:600}
        .turno-matutino{background:#fff3cd;color:#856404}
        .turno-vespertino{background:#cfe2ff;color:#084298}
        .estudiantes-count{color:#6c757d;font-size:.9rem}
        .estudiantes-count i{color:#667eea}
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
                <h2><i class="bi bi-collection-fill text-warning"></i> Grados y Secciones</h2>
                <p class="text-muted mb-0">Organización académica de la institución</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar grado o sección" value="<?= htmlspecialchars($buscar) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="grado" class="form-select">
                        <option value="">Todos los grados</option>
                        <?php foreach($lista_grados as $g): ?>
                        <option value="<?= $g['id_grado'] ?>" <?= $filtro_grado == $g['id_grado'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nombre_grado']) ?>
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
                    <h4><i class="bi bi-book"></i> <?= htmlspecialchars($grado['nombre_grado']) ?></h4>
                    <small class="text-muted">
                        <?= count($grado['secciones']) ?> <?= count($grado['secciones']) == 1 ? 'sección' : 'secciones' ?>
                    </small>
                </div>
                <div class="stat-badge">
                    <i class="bi bi-people-fill"></i> <?= $grado['total_estudiantes'] ?> estudiantes
                </div>
            </div>

            <?php if (empty($grado['secciones'])): ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle"></i> Este grado no tiene secciones asignadas.
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($grado['secciones'] as $seccion): ?>
                <div class="col-md-6">
                    <div class="seccion-item">
                        <div class="seccion-info">
                            <div class="bg-primary bg-opacity-10 rounded p-2">
                                <i class="bi bi-grid-3x3-gap-fill text-primary" style="font-size:1.5rem"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">
                                    <strong>Sección <?= htmlspecialchars($seccion['nombre_seccion']) ?></strong>
                                </h6>
                                <span class="seccion-badge <?= $seccion['turno'] == 'Matutino' ? 'turno-matutino' : 'turno-vespertino' ?>">
                                    <i class="bi bi-<?= $seccion['turno'] == 'Matutino' ? 'sun' : 'moon' ?>-fill"></i>
                                    <?= htmlspecialchars($seccion['turno']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="estudiantes-count">
                                <i class="bi bi-people-fill"></i>
                                <strong><?= $seccion['total_estudiantes'] ?></strong> estudiantes
                            </div>
                            <small class="text-muted">Encargado: No disponible</small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>