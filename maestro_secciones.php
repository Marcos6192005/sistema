<?php
// Mostrar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

// Verificar que sea maestro
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 4) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'] ?? null;
$nombre = $_SESSION['nombre_completo'] ?? 'Usuario';

if (!$id_usuario) {
    die("Error: No se encontró el ID de usuario en la sesión.");
}

// Obtener el ID del maestro y su institución
try {
    $stmt = $conn->prepare("
        SELECT m.id_maestro, m.id_institucion, i.nombre_institucion, i.id_departamento, i.id_municipio,
               mu.nombre_municipio, dep.nombre_departamento
        FROM maestros m
        LEFT JOIN institucion i ON m.id_institucion = i.id_institucion
        LEFT JOIN municipios mu ON i.id_municipio = mu.id_municipio
        LEFT JOIN departamentos dep ON i.id_departamento = dep.id_departamento
        WHERE m.id_usuario = :id_usuario
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $maestro_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$maestro_data) {
        die("Error: No se encontró información del maestro.");
    }

    $id_maestro = $maestro_data['id_maestro'];
    $id_institucion = $maestro_data['id_institucion'];

    if (!$id_institucion) {
        die("<div class='alert alert-danger m-5'>Error: Este maestro no tiene una institución asignada. Por favor contacte al administrador del sistema.</div>");
    }

    $institucion = $maestro_data;
} catch (PDOException $e) {
    die("Error al obtener datos del maestro: " . $e->getMessage());
}

$mensaje = "";
$tipo_mensaje = "";
$editando = false;
$seccion_editar = null;

// EDITAR - Cargar datos
if (isset($_GET['editar'])) {
    $id_seccion = $_GET['editar'];
    try {
        $stmt = $conn->prepare("
            SELECT s.* 
            FROM seccion s
            INNER JOIN grado g ON s.id_grado = g.id_grado
            INNER JOIN estudiante e ON e.id_seccion = s.id_seccion
            WHERE s.id_seccion = :id_seccion 
            AND e.id_institucion = :id_institucion
            LIMIT 1
        ");
        $stmt->execute([
            ':id_seccion' => $id_seccion,
            ':id_institucion' => $id_institucion
        ]);
        $seccion_editar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($seccion_editar) {
            $editando = true;
        } else {
            $mensaje = "No tienes permiso para editar esta sección.";
            $tipo_mensaje = "warning";
        }
    } catch (PDOException $e) {
        $mensaje = "Error al cargar sección: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// AGREGAR SECCIÓN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agregar_seccion'])) {
    $nombre_seccion = trim($_POST['nombre_seccion'] ?? '');
    $id_grado = $_POST['id_grado'] ?? null;
    $especialidad = trim($_POST['especialidad'] ?? '');
    $turno = $_POST['turno'] ?? '';

    if (empty($nombre_seccion) || empty($id_grado) || empty($turno)) {
        $mensaje = "Nombre de sección, grado y turno son obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        try {
            // Verificar que el grado pertenece a la institución (a través de estudiantes)
            $stmt_check = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM estudiante 
                WHERE id_grado = :id_grado AND id_institucion = :id_institucion
            ");
            $stmt_check->execute([
                ':id_grado' => $id_grado,
                ':id_institucion' => $id_institucion
            ]);
            $grado_valido = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($grado_valido['count'] > 0 || true) { // Permitir crear sección aunque no haya estudiantes
                $stmt = $conn->prepare("
                    INSERT INTO seccion (nombre_seccion, id_grado, especialidad, turno, estado)
                    VALUES (:nombre_seccion, :id_grado, :especialidad, :turno, TRUE)
                ");
                $stmt->execute([
                    ':nombre_seccion' => $nombre_seccion,
                    ':id_grado' => $id_grado,
                    ':especialidad' => $especialidad,
                    ':turno' => $turno
                ]);

                $mensaje = "Sección agregada exitosamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "El grado seleccionado no pertenece a tu institución.";
                $tipo_mensaje = "warning";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al agregar sección: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// ACTUALIZAR SECCIÓN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_seccion'])) {
    $id_seccion = $_POST['id_seccion'];
    $nombre_seccion = trim($_POST['nombre_seccion'] ?? '');
    $id_grado = $_POST['id_grado'] ?? null;
    $especialidad = trim($_POST['especialidad'] ?? '');
    $turno = $_POST['turno'] ?? '';
    $estado = isset($_POST['estado']) ? 1 : 0;

    if (empty($nombre_seccion) || empty($id_grado) || empty($turno)) {
        $mensaje = "Nombre de sección, grado y turno son obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE seccion 
                SET nombre_seccion = :nombre_seccion,
                    id_grado = :id_grado,
                    especialidad = :especialidad,
                    turno = :turno,
                    estado = :estado
                WHERE id_seccion = :id_seccion
            ");
            $stmt->execute([
                ':nombre_seccion' => $nombre_seccion,
                ':id_grado' => $id_grado,
                ':especialidad' => $especialidad,
                ':turno' => $turno,
                ':estado' => $estado,
                ':id_seccion' => $id_seccion
            ]);

            $mensaje = "Sección actualizada correctamente.";
            $tipo_mensaje = "success";
            $editando = false;
            $seccion_editar = null;
            
            // Redirigir para limpiar URL
            header("Location: maestro_secciones.php");
            exit();
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// ELIMINAR SECCIÓN
if (isset($_GET['eliminar'])) {
    $id_seccion = $_GET['eliminar'];
    
    try {
        // Verificar que no tenga estudiantes asignados
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM estudiante 
            WHERE id_seccion = :id_seccion AND id_institucion = :id_institucion
        ");
        $stmt_check->execute([
            ':id_seccion' => $id_seccion,
            ':id_institucion' => $id_institucion
        ]);
        $result = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $mensaje = "No se puede eliminar: la sección tiene {$result['count']} estudiante(s) asignado(s).";
            $tipo_mensaje = "warning";
        } else {
            $stmt = $conn->prepare("DELETE FROM seccion WHERE id_seccion = :id_seccion");
            $stmt->execute([':id_seccion' => $id_seccion]);

            $mensaje = "Sección eliminada correctamente.";
            $tipo_mensaje = "success";
        }
    } catch (PDOException $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// LISTAR SECCIONES (de la institución del maestro)
try {
    $stmt = $conn->prepare("
        SELECT s.*, 
               g.nombre_grado, 
               g.nivel,
               COUNT(DISTINCT e.id_estudiante) as total_estudiantes
        FROM seccion s
        INNER JOIN grado g ON s.id_grado = g.id_grado
        LEFT JOIN estudiante e ON e.id_seccion = s.id_seccion AND e.id_institucion = :id_institucion
        WHERE s.id_grado IN (
            SELECT DISTINCT id_grado FROM estudiante WHERE id_institucion = :id_institucion
        )
        GROUP BY s.id_seccion, s.nombre_seccion, s.especialidad, s.turno, s.estado, 
                 g.nombre_grado, g.nivel, s.fecha_registro
        ORDER BY g.nombre_grado, s.nombre_seccion
    ");
    $stmt->execute([':id_institucion' => $id_institucion]);
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al listar secciones: " . $e->getMessage());
}

// OBTENER GRADOS disponibles en la institución
try {
    $stmt_grados = $conn->prepare("
        SELECT DISTINCT g.id_grado, g.nombre_grado, g.nivel
        FROM grado g
        INNER JOIN estudiante e ON e.id_grado = g.id_grado
        WHERE e.id_institucion = :id_institucion AND g.estado = TRUE
        ORDER BY g.nombre_grado
    ");
    $stmt_grados->execute([':id_institucion' => $id_institucion]);
    $grados = $stmt_grados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $grados = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Secciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root{--sb-bg:#16a085;--sb-hv:#138871;--grad:linear-gradient(135deg,#16a085 0%,#0e6655 100%)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#f8f9fa}
        .sb{position:fixed;top:0;left:0;height:100vh;width:260px;background:var(--sb-bg);padding:20px 0;z-index:1000;box-shadow:4px 0 10px rgba(0,0,0,.1);overflow-y:auto}
        .sb-hd{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px}
        .sb-hd h4{color:#fff;font-weight:600;font-size:1.3rem;margin-top:10px}
        .sb-hd i{font-size:2.5rem;color:#fff}
        .nl{color:rgba(255,255,255,.7);padding:12px 20px;display:flex;align-items:center;text-decoration:none;transition:.3s;margin:5px 10px;border-radius:8px;white-space:nowrap}
        .nl:hover{background:var(--sb-hv);color:#fff;transform:translateX(5px)}
        .nl.active{background:rgba(255,255,255,.15);color:#fff;border-left:3px solid #fff}
        .nl i{margin-right:12px;font-size:1.2rem;width:25px;flex-shrink:0}
        .nl span{flex:1}
        .lo{margin-top:20px;width:calc(100% - 20px);margin-left:10px;margin-right:10px}
        .lo:hover{background:#dc3545;color:#fff!important}
        .mc{margin-left:260px;padding:30px;min-height:100vh}
        .info-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px;border-left:4px solid #16a085}
        .info-box h5{color:#16a085;margin-bottom:10px}
        .badge{padding:6px 12px}
        .card-header{background:var(--grad)!important;color:#fff!important}
        .btn-primary{background:var(--grad);border:none}
        .btn-primary:hover{opacity:.9}
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
        <a href="maestro_secciones.php" class="nl active"><i class="bi bi-grid-3x3-gap-fill"></i><span>Secciones</span></a>
        <a href="maestro_demeritos.php" class="nl"><i class="bi bi-flag-fill"></i><span>Deméritos</span></a>
        <a href="logout.php" class="nl lo"><i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span></a>
    </nav>
</div>

<div class="mc">
    <h2 class="mb-4"><i class="bi bi-grid-3x3-gap-fill"></i> Gestión de Secciones</h2>

    <!-- Información de la institución -->
    <div class="info-box">
        <h5><i class="bi bi-building"></i> Institución Asignada</h5>
        <p><strong>Nombre:</strong> <?= htmlspecialchars($institucion['nombre_institucion'] ?? 'Sin nombre') ?></p>
        <p><strong>Ubicación:</strong> <?= htmlspecialchars($institucion['nombre_municipio'] ?? 'N/A') ?>, <?= htmlspecialchars($institucion['nombre_departamento'] ?? 'N/A') ?></p>
    </div>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show">
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulario agregar/editar -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-<?= $editando ? 'pencil' : 'plus-circle' ?>"></i> 
                        <?= $editando ? 'Editar Sección' : 'Agregar Sección' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($grados)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> No hay grados disponibles en tu institución. Contacta al director.
                    </div>
                    <?php else: ?>
                    <form method="POST">
                        <?php if ($editando): ?>
                            <input type="hidden" name="id_seccion" value="<?= $seccion_editar['id_seccion'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nombre de Sección *</label>
                            <input type="text" name="nombre_seccion" class="form-control" 
                                   value="<?= $editando ? htmlspecialchars($seccion_editar['nombre_seccion']) : '' ?>" 
                                   required placeholder="Ej: Sección A">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Grado *</label>
                            <select name="id_grado" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($grados as $g): ?>
                                <option value="<?= $g['id_grado'] ?>" 
                                        <?= ($editando && $seccion_editar['id_grado'] == $g['id_grado']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['nombre_grado']) ?> - <?= htmlspecialchars($g['nivel']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Especialidad</label>
                            <input type="text" name="especialidad" class="form-control" 
                                   value="<?= $editando ? htmlspecialchars($seccion_editar['especialidad'] ?? '') : '' ?>" 
                                   placeholder="Ej: Ciencias, Humanidades">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Turno *</label>
                            <select name="turno" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                <option value="Matutino" <?= ($editando && $seccion_editar['turno'] == 'Matutino') ? 'selected' : '' ?>>Matutino</option>
                                <option value="Vespertino" <?= ($editando && $seccion_editar['turno'] == 'Vespertino') ? 'selected' : '' ?>>Vespertino</option>
                                <option value="Nocturno" <?= ($editando && $seccion_editar['turno'] == 'Nocturno') ? 'selected' : '' ?>>Nocturno</option>
                            </select>
                        </div>

                        <?php if ($editando): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="estado" class="form-check-input" id="estado" 
                                   <?= $seccion_editar['estado'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="estado">Sección Activa</label>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <?php if ($editando): ?>
                                <button type="submit" name="actualizar_seccion" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Actualizar Sección
                                </button>
                                <a href="maestro_secciones.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                            <?php else: ?>
                                <button type="submit" name="agregar_seccion" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Guardar Sección
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Lista de secciones -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Secciones Registradas (<?= count($secciones) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($secciones)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No hay secciones registradas en esta institución.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Grado</th>
                                    <th>Sección</th>
                                    <th>Especialidad</th>
                                    <th>Turno</th>
                                    <th>Estudiantes</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($secciones as $sec): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sec['nombre_grado']) ?></strong></td>
                                    <td><?= htmlspecialchars($sec['nombre_seccion']) ?></td>
                                    <td><small><?= htmlspecialchars($sec['especialidad'] ?? 'N/A') ?></small></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($sec['turno']) ?></span></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <i class="bi bi-people-fill"></i> <?= $sec['total_estudiantes'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($sec['estado']): ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?editar=<?= $sec['id_seccion'] ?>" 
                                           class="btn btn-sm btn-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?eliminar=<?= $sec['id_seccion'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('¿Eliminar esta sección? Esta acción no se puede deshacer.')"
                                           title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </a>
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