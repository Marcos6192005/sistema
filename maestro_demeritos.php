<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 4) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del maestro
$stmt = $conn->prepare("SELECT id_maestro, id_institucion FROM maestros WHERE id_usuario = :id");
$stmt->execute([':id' => $id_usuario]);
$maestro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$maestro) die("Error: Maestro no encontrado.");

$id_maestro = $maestro['id_maestro'];
$id_institucion = $maestro['id_institucion'];

$mensaje = "";
$tipo_mensaje = "";

// REGISTRAR DEMÉRITO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_demerito'])) {
    $id_estudiante = $_POST['id_estudiante'] ?? null;
    $motivo = trim($_POST['motivo'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $severidad = $_POST['severidad'] ?? '';

    if (!$id_estudiante || empty($motivo) || empty($categoria) || empty($severidad)) {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        try {
            // Verificar reincidencia
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM demeritos 
                WHERE id_estudiante = :id_est AND categoria = :cat
            ");
            $stmt->execute([':id_est' => $id_estudiante, ':cat' => $categoria]);
            $reincidencia = $stmt->fetch()['total'] + 1;

            // Insertar demérito
            $stmt = $conn->prepare("
                INSERT INTO demeritos (id_estudiante, id_maestro, id_usuario, id_institucion, 
                                      estado_validacion, reincidencia, motivo, categoria, severidad)
                VALUES (:id_est, :id_mae, :id_usr, :id_inst, 'Pendiente', :reinc, :motivo, :cat, :sev)
            ");
            $stmt->execute([
                ':id_est' => $id_estudiante,
                ':id_mae' => $id_maestro,
                ':id_usr' => $id_usuario,
                ':id_inst' => $id_institucion,
                ':reinc' => $reincidencia,
                ':motivo' => $motivo,
                ':cat' => $categoria,
                ':sev' => $severidad
            ]);

            $mensaje = "Demérito registrado exitosamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// LISTAR ESTUDIANTES
$estudiantes = $conn->prepare("
    SELECT e.*, g.nombre_grado, s.nombre_seccion
    FROM estudiante e
    LEFT JOIN grado g ON e.id_grado = g.id_grado
    LEFT JOIN seccion s ON e.id_seccion = s.id_seccion
    WHERE e.id_institucion = :id_inst AND e.estado_matriculado = TRUE
    ORDER BY e.apellido, e.nombre
");
$estudiantes->execute([':id_inst' => $id_institucion]);
$estudiantes = $estudiantes->fetchAll(PDO::FETCH_ASSOC);

// LISTAR DEMÉRITOS REGISTRADOS
$demeritos = $conn->prepare("
    SELECT d.*, e.nie, e.nombre, e.apellido, 
           g.nombre_grado, s.nombre_seccion
    FROM demeritos d
    INNER JOIN estudiante e ON d.id_estudiante = e.id_estudiante
    LEFT JOIN grado g ON e.id_grado = g.id_grado
    LEFT JOIN seccion s ON e.id_seccion = s.id_seccion
    WHERE d.id_maestro = :id_mae
    ORDER BY d.fecha_registro DESC
    LIMIT 50
");
$demeritos->execute([':id_mae' => $id_maestro]);
$demeritos = $demeritos->fetchAll(PDO::FETCH_ASSOC);

// Preseleccionar estudiante si viene por URL
$estudiante_preseleccionado = $_GET['estudiante'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Deméritos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root{--sb-bg:#16a085;--sb-hv:#138d75;--grad:linear-gradient(135deg,#16a085 0%,#138d75 100%)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#f8f9fa}
        .sb{position:fixed;top:0;left:0;height:100vh;width:260px;background:var(--sb-bg);padding:20px 0;z-index:1000;box-shadow:4px 0 10px rgba(0,0,0,.1);overflow-y:auto}
        .sb-hd{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px}
        .sb-hd h4{color:#fff;font-weight:600;font-size:1.3rem;margin-top:10px}
        .sb-hd i{font-size:2.5rem;color:#fff}
        .nl{color:rgba(255,255,255,.7);padding:12px 20px;display:flex;align-items:center;text-decoration:none;transition:.3s;margin:5px 10px;border-radius:8px;white-space:nowrap}
        .nl:hover{background:var(--sb-hv);color:#fff;transform:translateX(5px)}
        .nl.active{background:var(--grad);color:#fff}
        .nl i{margin-right:12px;font-size:1.2rem;width:25px;flex-shrink:0}
        .nl span{flex:1}
        .lo{margin-top:20px;width:calc(100% - 20px);margin-left:10px;margin-right:10px}
        .lo:hover{background:#e74c3c;color:#fff!important}
        .mc{margin-left:260px;padding:30px;min-height:100vh}
        .badge{padding:6px 12px}
        @media(max-width:768px){.sb{width:70px}.sb-hd h4,.nl span{display:none}.nl{justify-content:center}.nl i{margin-right:0}.mc{margin-left:70px}}
    </style>
</head>
<body>

<div class="sb">
    <div class="sb-hd">
        <i class="bi bi-person-video3"></i>
        <h4>Maestro</h4>
    </div>
    <nav>
        <a href="maestro_panel.php" class="nl"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
        <a href="maestro_estudiantes.php" class="nl"><i class="bi bi-people-fill"></i><span>Estudiantes</span></a>
        <a href="maestro_secciones.php" class="nl"><i class="bi bi-diagram-3-fill"></i><span>Mis Secciones</span></a>
        <a href="maestro_demeritos.php" class="nl active"><i class="bi bi-exclamation-triangle-fill"></i><span>Deméritos</span></a>
        <a href="logout.php" class="nl lo"><i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span></a>
    </nav>
</div>

<div class="mc">
    <h2 class="mb-4"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Gestión de Deméritos</h2>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show">
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulario -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Registrar Demérito</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Estudiante *</label>
                            <select name="id_estudiante" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($estudiantes as $est): ?>
                                <option value="<?= $est['id_estudiante'] ?>" 
                                    <?= $estudiante_preseleccionado == $est['id_estudiante'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($est['nie'] . ' - ' . $est['nombre'] . ' ' . $est['apellido']) ?>
                                    (<?= $est['nombre_grado'] ?> - <?= $est['nombre_seccion'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Categoría *</label>
                            <select name="categoria" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <option value="Conducta">Conducta</option>
                                <option value="Disciplina">Disciplina</option>
                                <option value="Académico">Académico</option>
                                <option value="Asistencia">Asistencia</option>
                                <option value="Uniforme">Uniforme</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Severidad *</label>
                            <select name="severidad" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <option value="Leve">Leve</option>
                                <option value="Moderado">Moderado</option>
                                <option value="Grave">Grave</option>
                                <option value="Muy Grave">Muy Grave</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Motivo/Descripción *</label>
                            <textarea name="motivo" class="form-control" rows="4" required placeholder="Describa detalladamente la falta cometida..."></textarea>
                        </div>

                        <button type="submit" name="registrar_demerito" class="btn btn-danger w-100">
                            <i class="bi bi-save"></i> Registrar Demérito
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista de deméritos -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Deméritos Registrados (<?= count($demeritos) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($demeritos)): ?>
                    <div class="alert alert-info">No hay deméritos registrados.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Estudiante</th>
                                    <th>Categoría</th>
                                    <th>Severidad</th>
                                    <th>Reinc.</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demeritos as $d): ?>
                                <tr>
                                    <td><small><?= date('d/m/Y', strtotime($d['fecha_registro'])) ?></small></td>
                                    <td>
                                        <strong><?= htmlspecialchars($d['nombre'] . ' ' . $d['apellido']) ?></strong>
                                        <br><small class="text-muted"><?= $d['nombre_grado'] ?> - <?= $d['nombre_seccion'] ?></small>
                                    </td>
                                    <td><span class="badge bg-info"><?= $d['categoria'] ?></span></td>
                                    <td>
                                        <?php
                                        $color = match($d['severidad']) {
                                            'Leve' => 'success',
                                            'Moderado' => 'warning',
                                            'Grave' => 'danger',
                                            'Muy Grave' => 'dark',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= $d['severidad'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= $d['reincidencia'] ?>°</span>
                                    </td>
                                    <td>
                                        <?php
                                        $color = match($d['estado_validacion']) {
                                            'Aprobado' => 'success',
                                            'Rechazado' => 'danger',
                                            default => 'warning'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= $d['estado_validacion'] ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="6" class="bg-light">
                                        <small><strong>Motivo:</strong> <?= htmlspecialchars($d['motivo']) ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>