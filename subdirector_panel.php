<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 3) {
    header("Location: index.php");
    exit();
}

$nombre = $_SESSION['nombre_completo'];

// ============================================
// CONFIGURACIÓN ÚNICA - Solo edita aquí
// ============================================
$config = [
    'titulo_sistema' => 'Subdirector',
    'icono_sistema' => 'person-workspace',
    'color_sidebar' => '#2c3e50',
    
    'modulos' => [
        ['titulo' => 'Estudiantes', 'icono' => 'people-fill', 'color' => 'primary', 'url' => 'estudiantes.php'],
        ['titulo' => 'Maestros', 'icono' => 'person-badge-fill', 'color' => 'success', 'url' => 'maestros.php'],
        ['titulo' => 'Grados y Secciones', 'icono' => 'collection-fill', 'color' => 'warning', 'url' => 'grados.php'],
        ['titulo' => 'Deméritos', 'icono' => 'flag-fill', 'color' => 'danger', 'url' => 'demeritos.php'],
        ['titulo' => 'Redenciones', 'icono' => 'hand-thumbs-up-fill', 'color' => 'info', 'url' => 'redenciones.php'],
        ['titulo' => 'Reportes', 'icono' => 'file-earmark-bar-graph-fill', 'color' => 'secondary', 'url' => 'reportes.php']
    ]
];

// Auto-generar menú desde módulos + Dashboard al inicio
$menuItems = [['url' => 'subdirector.php', 'icono' => 'speedometer2', 'texto' => 'Dashboard']];
foreach($config['modulos'] as $m) {
    $menuItems[] = ['url' => $m['url'], 'icono' => $m['icono'], 'texto' => $m['titulo']];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard <?= $config['titulo_sistema'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root{--sb-bg:<?= $config['color_sidebar'] ?>;--sb-hv:#2d3142;--grad:linear-gradient(135deg,#667eea 0%,#764ba2 100%)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}
        .sb{position:fixed;top:0;left:0;height:100vh;width:260px;background:var(--sb-bg);padding:20px 0;z-index:1000;box-shadow:4px 0 10px rgba(0,0,0,.1);overflow-y:auto}
        .sb-hd{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px}
        .sb-hd h4{color:#fff;font-weight:600;font-size:1.3rem;margin-top:10px}
        .sb-hd i{font-size:2.5rem;color:#667eea}
        .nl{color:rgba(255,255,255,.7);padding:12px 20px;display:flex;align-items:center;text-decoration:none;transition:.3s;margin:5px 10px;border-radius:8px;white-space:nowrap}
        .nl:hover{background:var(--sb-hv);color:#fff;transform:translateX(5px)}
        .nl.active{background:var(--grad);color:#fff}
        .nl i{margin-right:12px;font-size:1.2rem;width:25px;flex-shrink:0}
        .nl span{flex:1}
        .lo{margin-top:20px;width:calc(100% - 20px);margin-left:10px;margin-right:10px}
        .lo:hover{background:#dc3545;color:#fff!important}
        .mc{margin-left:260px;padding:30px;min-height:100vh}
        .ch{background:#fff;padding:25px 30px;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:30px}
        .ch h2{font-weight:700;color:#2d3142;margin:0}
        .cd{background:#fff;border-radius:15px;padding:25px;box-shadow:0 4px 15px rgba(0,0,0,.08);transition:.3s;border:none;height:100%;position:relative;overflow:hidden}
        .cd::before{content:'';position:absolute;top:0;left:0;width:100%;height:4px;background:var(--cc);transform:scaleX(0);transition:.3s}
        .cd:hover::before{transform:scaleX(1)}
        .cd:hover{transform:translateY(-8px);box-shadow:0 8px 25px rgba(0,0,0,.15)}
        .ic{width:70px;height:70px;border-radius:15px;display:flex;align-items:center;justify-content:center;margin-bottom:20px;font-size:2rem}
        .cd h5{font-weight:700;color:#2d3142;margin-bottom:8px}
        .cd p{color:#6c757d;font-size:.9rem;margin-bottom:20px}
        .cd .btn{width:100%;padding:10px;border-radius:8px;font-weight:600}
        .bg-primary-light{background:rgba(102,126,234,.1);color:#667eea}
        .bg-success-light{background:rgba(40,167,69,.1);color:#28a745}
        .bg-warning-light{background:rgba(255,193,7,.1);color:#ffc107}
        .bg-danger-light{background:rgba(220,53,69,.1);color:#dc3545}
        .bg-info-light{background:rgba(23,162,184,.1);color:#17a2b8}
        .bg-secondary-light{background:rgba(108,117,125,.1);color:#6c757d}
        @keyframes fi{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        .cd{animation:fi .5s ease forwards;opacity:0}
        <?php foreach($config['modulos'] as $i => $m): ?>.cd:nth-child(<?= $i+1 ?>){animation-delay:<?= ($i+1)*0.1 ?>s}<?php endforeach; ?>
        @media(max-width:768px){.sb{width:70px}.sb-hd h4,.nl span{display:none}.nl{justify-content:center}.nl i{margin-right:0}.mc{margin-left:70px}}
    </style>
</head>
<body>
    <div class="sb">
        <div class="sb-hd">
            <i class="bi bi-<?= $config['icono_sistema'] ?>"></i>
            <h4><?= $config['titulo_sistema'] ?></h4>
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
        <div class="ch">
            <h2>👋 Bienvenido, <?= htmlspecialchars($nombre) ?></h2>
            <p class="text-muted mb-0">Panel de administración</p>
        </div>
        <div class="row g-4">
            <?php foreach($config['modulos'] as $m): ?>
            <div class="col-lg-4 col-md-6">
                <div class="cd" style="--cc:var(--bs-<?= $m['color'] ?>)">
                    <div class="ic bg-<?= $m['color'] ?>-light">
                        <i class="bi bi-<?= $m['icono'] ?>"></i>
                    </div>
                    <h5><?= $m['titulo'] ?></h5>
                    <p><?= $m['descripcion'] ?? 'Gestionar ' . strtolower($m['titulo']) ?></p>
                    <a href="<?= $m['url'] ?>" class="btn btn-outline-<?= $m['color'] ?>">
                        Ir al módulo <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>