<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connect.php';

// Verificar que sea estudiante (rol 5)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 5) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];
$nombre = $_SESSION['nombre_completo'];

// Procesar confirmación de demérito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_demerito'])) {
    $id_demeritos = $_POST['id_demeritos'];
    
    try {
        // Primero verificar que el demérito existe y pertenece al estudiante
        $stmt_check = $conn->prepare("
            SELECT d.estado_validacion, d.id_estudiante, e.id_estudiante as estudiante_actual
            FROM demeritos d
            INNER JOIN estudiante e ON e.id_usuario = :id_usuario
            WHERE d.id_demeritos = :id_demeritos
        ");
        $stmt_check->execute([
            ':id_demeritos' => $id_demeritos,
            ':id_usuario' => $id_usuario
        ]);
        $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$check) {
            $_SESSION['mensaje'] = "El demérito no existe o no te pertenece.";
            $_SESSION['tipo_mensaje'] = "danger";
        } elseif ($check['estado_validacion'] != 'Pendiente') {
            $_SESSION['mensaje'] = "Este demérito ya fue confirmado anteriormente.";
            $_SESSION['tipo_mensaje'] = "info";
        } else {
            // Actualizar el demérito
            $stmt = $conn->prepare("
                UPDATE demeritos 
                SET estado_validacion = 'Aprobado',
                    fecha_validacion = NOW()
                WHERE id_demeritos = :id_demeritos 
                AND id_estudiante = :id_estudiante
                AND estado_validacion = 'Pendiente'
            ");
            $stmt->execute([
                ':id_demeritos' => $id_demeritos,
                ':id_estudiante' => $check['estudiante_actual']
            ]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['mensaje'] = "Demérito confirmado exitosamente.";
                $_SESSION['tipo_mensaje'] = "success";
            } else {
                $_SESSION['mensaje'] = "No se pudo confirmar el demérito. Intenta nuevamente.";
                $_SESSION['tipo_mensaje'] = "danger";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Error al confirmar el demérito: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = "danger";
    }
    
    header("Location: estudiante_panel.php");
    exit();
}

// Obtener datos del estudiante
try {
    $stmt = $conn->prepare("
        SELECT e.*, g.nombre_grado, s.nombre_seccion, s.turno,
               i.nombre_institucion, m.nombre_municipio, dep.nombre_departamento
        FROM estudiante e
        INNER JOIN grado g ON e.id_grado = g.id_grado
        INNER JOIN seccion s ON e.id_seccion = s.id_seccion
        LEFT JOIN institucion i ON e.id_institucion = i.id_institucion
        LEFT JOIN municipios m ON i.id_municipio = m.id_municipio
        LEFT JOIN departamentos dep ON i.id_departamento = dep.id_departamento
        WHERE e.id_usuario = :id_usuario
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) {
        die("<div class='alert alert-danger m-5'>Error: No se encontró información del estudiante.</div>");
    }

    $id_estudiante = $estudiante['id_estudiante'];
    $id_institucion = $estudiante['id_institucion'];
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Obtener estadísticas de deméritos
try {
    // Total de deméritos
    $stmt_total = $conn->prepare("
        SELECT COUNT(*) as total FROM demeritos WHERE id_estudiante = :id_estudiante
    ");
    $stmt_total->execute([':id_estudiante' => $id_estudiante]);
    $total_demeritos = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

    // Deméritos por estado
    $stmt_estados = $conn->prepare("
        SELECT estado_validacion, COUNT(*) as cantidad
        FROM demeritos 
        WHERE id_estudiante = :id_estudiante
        GROUP BY estado_validacion
    ");
    $stmt_estados->execute([':id_estudiante' => $id_estudiante]);
    $estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

    $pendientes = 0;
    $aprobados = 0;

    foreach ($estados as $estado) {
        if ($estado['estado_validacion'] == 'Pendiente') {
            $pendientes = $estado['cantidad'];
        } elseif ($estado['estado_validacion'] == 'Aprobado') {
            $aprobados = $estado['cantidad'];
        }
    }

    // Deméritos por severidad
    $stmt_severidad = $conn->prepare("
        SELECT severidad, COUNT(*) as cantidad
        FROM demeritos 
        WHERE id_estudiante = :id_estudiante AND estado_validacion = 'Aprobado'
        GROUP BY severidad
    ");
    $stmt_severidad->execute([':id_estudiante' => $id_estudiante]);
    $severidades = $stmt_severidad->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $total_demeritos = 0;
    $pendientes = 0;
    $aprobados = 0;
    $severidades = [];
}

// Listar deméritos del estudiante
try {
    $stmt = $conn->prepare("
        SELECT d.*, 
               m.nombre as maestro_nombre, m.apellido as maestro_apellido,
               dir.nombre as director_nombre, dir.apellido as director_apellido,
               d.fecha_registro
        FROM demeritos d
        LEFT JOIN maestros m ON d.id_maestro = m.id_maestro
        LEFT JOIN director dir ON d.id_director = dir.id_director
        WHERE d.id_estudiante = :id_estudiante
        ORDER BY 
            CASE WHEN d.estado_validacion = 'Pendiente' THEN 0 ELSE 1 END,
            d.fecha_registro DESC
    ");
    $stmt->execute([':id_estudiante' => $id_estudiante]);
    $demeritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $demeritos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Estudiante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root{--sb-bg:#3498db;--sb-hv:#2980b9;--grad:linear-gradient(135deg,#3498db 0%,#2c3e50 100%)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#f8f9fa}
        .sb{position:fixed;top:0;left:0;height:100vh;width:260px;background:var(--sb-bg);padding:20px 0;z-index:1000;box-shadow:4px 0 10px rgba(0,0,0,.1);overflow-y:auto}
        .sb-hd{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px}
        .sb-hd h4{color:#fff;font-weight:600;font-size:1.3rem;margin-top:10px}
        .sb-hd i{font-size:2.5rem;color:#fff}
        .sb-hd p{color:rgba(255,255,255,.8);font-size:.9rem;margin-top:5px}
        .nl{color:rgba(255,255,255,.7);padding:12px 20px;display:flex;align-items:center;text-decoration:none;transition:.3s;margin:5px 10px;border-radius:8px;white-space:nowrap}
        .nl:hover{background:var(--sb-hv);color:#fff;transform:translateX(5px)}
        .nl.active{background:rgba(255,255,255,.15);color:#fff;border-left:3px solid #fff}
        .nl i{margin-right:12px;font-size:1.2rem;width:25px;flex-shrink:0}
        .nl span{flex:1}
        .lo{margin-top:20px;width:calc(100% - 20px);margin-left:10px;margin-right:10px}
        .lo:hover{background:#e74c3c;color:#fff!important}
        .mc{margin-left:260px;padding:30px;min-height:100vh}
        .info-card{background:#fff;padding:25px;border-radius:12px;box-shadow:0 3px 10px rgba(0,0,0,.08);margin-bottom:25px;border-left:5px solid #3498db}
        .info-card h5{color:#3498db;margin-bottom:15px;font-weight:600}
        .stat-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);text-align:center;transition:.3s}
        .stat-box:hover{transform:translateY(-5px);box-shadow:0 5px 15px rgba(0,0,0,.15)}
        .stat-box h3{font-size:2.5rem;font-weight:700;margin:10px 0}
        .stat-box p{color:#7f8c8d;margin:0;font-size:.9rem}
        .stat-total{border-left:4px solid #3498db}
        .stat-pendiente{border-left:4px solid #f39c12}
        .stat-aprobado{border-left:4px solid #27ae60}
        .badge{padding:8px 15px;font-size:.85rem}
        .demerito-item{background:#fff;padding:20px;border-radius:10px;margin-bottom:15px;border-left:4px solid #95a5a6;transition:.3s}
        .demerito-item:hover{box-shadow:0 3px 12px rgba(0,0,0,.1)}
        .demerito-pendiente{background:#fff9e6;border-left-color:#f39c12;box-shadow:0 2px 8px rgba(243,156,18,.2)}
        .demerito-grave{border-left-color:#e74c3c}
        .demerito-moderado{border-left-color:#f39c12}
        .demerito-leve{border-left-color:#3498db}
        .timeline-date{color:#7f8c8d;font-size:.85rem}
        .btn-confirmar{background:#27ae60;color:#fff;border:none;padding:8px 20px;border-radius:6px;transition:.3s}
        .btn-confirmar:hover{background:#229954;transform:scale(1.05)}
        .alerta-pendiente{background:#fff3cd;border-left:4px solid #f39c12;padding:15px;border-radius:8px;margin-bottom:20px}
        @media(max-width:768px){.sb{width:70px}.sb-hd h4,.sb-hd p,.nl span{display:none}.nl{justify-content:center}.nl i{margin-right:0}.mc{margin-left:70px}}
    </style>
</head>
<body>

<div class="sb">
    <div class="sb-hd">
        <i class="bi bi-person-circle"></i>
        <h4>Estudiante</h4>
        <p><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']) ?></p>
    </div>
    <nav>
        <a href="estudiante.php" class="nl active"><i class="bi bi-speedometer2"></i><span>Mi Dashboard</span></a>
        <a href="estudiante_demeritos.php" class="nl"><i class="bi bi-flag-fill"></i><span>Mis Deméritos</span></a>
        <a href="logout.php" class="nl lo"><i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span></a>
    </nav>
</div>

<div class="mc">
    <h2 class="mb-4"><i class="bi bi-speedometer2"></i> Mi Dashboard</h2>

    <!-- Mensajes de éxito/error -->
    <?php if (isset($_SESSION['mensaje'])): ?>
    <div class="alert alert-<?= $_SESSION['tipo_mensaje'] ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $_SESSION['tipo_mensaje'] == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
        <?= htmlspecialchars($_SESSION['mensaje']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php 
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo_mensaje']);
    endif; 
    ?>

    <!-- Alerta de deméritos pendientes -->
    <?php if ($pendientes > 0): ?>
    <div class="alerta-pendiente">
        <h5><i class="bi bi-exclamation-triangle-fill"></i> Atención: Tienes <?= $pendientes ?> demérito(s) pendiente(s) de confirmar</h5>
        <p class="mb-0">Por favor, revisa y confirma los deméritos pendientes en la sección de historial a continuación.</p>
    </div>
    <?php endif; ?>

    <!-- Información del estudiante -->
    <div class="info-card">
        <h5><i class="bi bi-person-badge"></i> Información Personal</h5>
        <div class="row">
            <div class="col-md-6">
                <p><strong>NIE:</strong> <?= htmlspecialchars($estudiante['nie']) ?></p>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']) ?></p>
                <p><strong>Grado:</strong> <?= htmlspecialchars($estudiante['nombre_grado']) ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Sección:</strong> <?= htmlspecialchars($estudiante['nombre_seccion']) ?> (<?= htmlspecialchars($estudiante['turno']) ?>)</p>
                <p><strong>Institución:</strong> <?= htmlspecialchars($estudiante['nombre_institucion']) ?></p>
                <p><strong>Ubicación:</strong> <?= htmlspecialchars($estudiante['nombre_municipio']) ?>, <?= htmlspecialchars($estudiante['nombre_departamento']) ?></p>
            </div>
        </div>
    </div>

    <!-- Estadísticas de deméritos -->
    <h4 class="mb-3"><i class="bi bi-bar-chart-fill"></i> Estadísticas de Conducta</h4>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-box stat-total">
                <i class="bi bi-flag-fill" style="font-size:2rem;color:#3498db"></i>
                <h3><?= $total_demeritos ?></h3>
                <p>Total Deméritos</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-box stat-pendiente">
                <i class="bi bi-clock-fill" style="font-size:2rem;color:#f39c12"></i>
                <h3><?= $pendientes ?></h3>
                <p>Por Confirmar</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-box stat-aprobado">
                <i class="bi bi-check-circle-fill" style="font-size:2rem;color:#27ae60"></i>
                <h3><?= $aprobados ?></h3>
                <p>Confirmados</p>
            </div>
        </div>
    </div>

    <!-- Deméritos por severidad -->
    <?php if (!empty($severidades)): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-pie-chart-fill"></i> Deméritos Confirmados por Severidad</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($severidades as $sev): ?>
                        <div class="col-md-4 text-center">
                            <h2 class="mb-0"><?= $sev['cantidad'] ?></h2>
                            <span class="badge 
                                <?php 
                                    if ($sev['severidad'] == 'Grave') echo 'bg-danger';
                                    elseif ($sev['severidad'] == 'Moderado') echo 'bg-warning';
                                    else echo 'bg-info';
                                ?>">
                                <?= htmlspecialchars($sev['severidad']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Historial de deméritos -->
    <h4 class="mb-3"><i class="bi bi-journal-text"></i> Historial de Deméritos</h4>
    
    <?php if (empty($demeritos)): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i> ¡Excelente! No tienes deméritos registrados. Sigue así.
    </div>
    <?php else: ?>
    <div class="row">
        <div class="col-md-12">
            <?php foreach ($demeritos as $dem): ?>
            <div class="demerito-item demerito-<?= strtolower($dem['severidad']) ?> <?= $dem['estado_validacion'] == 'Pendiente' ? 'demerito-pendiente' : '' ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h5 class="mb-2">
                            <span class="badge 
                                <?php 
                                    if ($dem['severidad'] == 'Grave') echo 'bg-danger';
                                    elseif ($dem['severidad'] == 'Moderado') echo 'bg-warning text-dark';
                                    else echo 'bg-info';
                                ?>">
                                <?= htmlspecialchars($dem['severidad']) ?>
                            </span>
                            <?= htmlspecialchars($dem['categoria']) ?>
                        </h5>
                        <p class="mb-2"><strong>Motivo:</strong> <?= htmlspecialchars($dem['motivo']) ?></p>
                        <p class="mb-2 timeline-date">
                            <i class="bi bi-calendar3"></i> 
                            <?= date('d/m/Y H:i', strtotime($dem['fecha_registro'])) ?>
                        </p>
                        <p class="mb-0 text-muted">
                            <i class="bi bi-person-fill"></i> 
                            Registrado por: 
                            <?php if ($dem['maestro_nombre']): ?>
                                Prof. <?= htmlspecialchars($dem['maestro_nombre'] . ' ' . $dem['maestro_apellido']) ?>
                            <?php elseif ($dem['director_nombre']): ?>
                                Director(a) <?= htmlspecialchars($dem['director_nombre'] . ' ' . $dem['director_apellido']) ?>
                            <?php else: ?>
                                Sistema
                            <?php endif; ?>
                        </p>
                        <?php if ($dem['reincidencia'] > 1): ?>
                        <p class="mb-0 mt-2">
                            <span class="badge bg-dark">
                                <i class="bi bi-exclamation-triangle"></i> Reincidencia: <?= $dem['reincidencia'] ?> veces
                            </span>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="text-end ms-3">
                        <?php if ($dem['estado_validacion'] == 'Pendiente'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de confirmar este demérito? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="id_demeritos" value="<?= $dem['id_demeritos'] ?>">
                            <button type="submit" name="confirmar_demerito" class="btn-confirmar">
                                <i class="bi bi-check-circle"></i> Confirmar
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle-fill"></i> Confirmado
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mensaje motivacional -->
    <?php if ($total_demeritos == 0): ?>
    <div class="info-card" style="border-left-color:#27ae60;background:linear-gradient(135deg,#e8f5e9 0%,#c8e6c9 100%)">
        <h5 style="color:#27ae60"><i class="bi bi-trophy-fill"></i> ¡Comportamiento Ejemplar!</h5>
        <p class="mb-0">Mantén tu excelente conducta. Tu esfuerzo es reconocido por toda la institución.</p>
    </div>
    <?php elseif ($aprobados > 3): ?>
    <div class="info-card" style="border-left-color:#f39c12;background:#fff3cd">
        <h5 style="color:#f39c12"><i class="bi bi-info-circle-fill"></i> Recomendación</h5>
        <p class="mb-0">Has acumulado varios deméritos. Te recomendamos reflexionar sobre tu conducta y mejorar tu comportamiento en el aula.</p>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>