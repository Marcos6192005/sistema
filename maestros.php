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

// Verificar que sea director
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 2) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'] ?? null;
$nombre = $_SESSION['nombre_completo'] ?? 'Usuario';

if (!$id_usuario) {
    die("Error: No se encontró el ID de usuario en la sesión.");
}

// Obtener el ID del director y su institución
try {
    $stmt = $conn->prepare("
        SELECT d.id_director, d.id_institucion, i.nombre_institucion, i.id_departamento, i.id_municipio,
               m.nombre_municipio, dep.nombre_departamento
        FROM director d
        LEFT JOIN institucion i ON d.id_institucion = i.id_institucion
        LEFT JOIN municipios m ON i.id_municipio = m.id_municipio
        LEFT JOIN departamentos dep ON i.id_departamento = dep.id_departamento
        WHERE d.id_usuario = :id_usuario
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $director_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$director_data) {
        die("Error: No se encontró información del director.");
    }

    $id_director = $director_data['id_director'];
    $id_institucion = $director_data['id_institucion'];

    if (!$id_institucion) {
        die("<div class='alert alert-danger m-5'>Error: Este director no tiene una institución asignada. Por favor contacte al administrador del sistema.</div>");
    }

    $institucion = $director_data;
} catch (PDOException $e) {
    die("Error al obtener datos del director: " . $e->getMessage());
}

// Crear tabla de asignaciones si no existe
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS maestro_seccion (
            id_asignacion SERIAL PRIMARY KEY,
            id_maestro INT NOT NULL,
            id_seccion INT NOT NULL,
            id_institucion INT NOT NULL,
            fecha_asignacion TIMESTAMP DEFAULT NOW(),
            UNIQUE(id_maestro, id_seccion)
        )
    ");
} catch (PDOException $e) {
    // La tabla ya existe, continuar
}

$mensaje = "";
$tipo_mensaje = "";

// AGREGAR MAESTRO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agregar_maestro'])) {
    $nombre_m = trim($_POST['nombre'] ?? '');
    $apellido_m = trim($_POST['apellido'] ?? '');
    $correo_m = trim($_POST['correo'] ?? '');
    $password_m = $_POST['password'] ?? '';
    $direccion = trim($_POST['direccion'] ?? '');
    $f_nacimiento = $_POST['f_nacimiento'] ?? null;

    if (empty($nombre_m) || empty($apellido_m) || empty($correo_m) || empty($password_m)) {
        $mensaje = "Todos los campos obligatorios deben llenarse.";
        $tipo_mensaje = "danger";
    } else {
        try {
            $conn->beginTransaction();

            // Crear usuario
            $nombre_completo = $nombre_m . " " . $apellido_m;
            $password_hash = password_hash($password_m, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO usuarios (nombre_completo, correo, password_hash, id_rol, estado)
                VALUES (:nombre_completo, :correo, :password_hash, 4, TRUE)
            ");
            $stmt->execute([
                ':nombre_completo' => $nombre_completo,
                ':correo' => $correo_m,
                ':password_hash' => $password_hash
            ]);

            $id_usuario_maestro = $conn->lastInsertId();

            // Crear maestro asignado a la institución del director
            $stmt2 = $conn->prepare("
                INSERT INTO maestros (id_usuario, nombre, apellido, direccion, f_nacimiento, id_institucion)
                VALUES (:id_usuario, :nombre, :apellido, :direccion, :f_nacimiento, :id_institucion)
            ");
            $stmt2->execute([
                ':id_usuario' => $id_usuario_maestro,
                ':nombre' => $nombre_m,
                ':apellido' => $apellido_m,
                ':direccion' => $direccion,
                ':f_nacimiento' => $f_nacimiento ?: null,
                ':id_institucion' => $id_institucion
            ]);

            $conn->commit();
            $mensaje = "Maestro agregado exitosamente.";
            $tipo_mensaje = "success";

        } catch (PDOException $e) {
            $conn->rollBack();
            $mensaje = "Error al agregar maestro: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// ASIGNAR SECCIÓN A MAESTRO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['asignar_seccion'])) {
    $id_maestro = $_POST['id_maestro'] ?? null;
    $id_seccion = $_POST['id_seccion'] ?? null;

    if (empty($id_maestro) || empty($id_seccion)) {
        $mensaje = "Debe seleccionar un maestro y una sección.";
        $tipo_mensaje = "danger";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO maestro_seccion (id_maestro, id_seccion, id_institucion)
                VALUES (:id_maestro, :id_seccion, :id_institucion)
            ");
            $stmt->execute([
                ':id_maestro' => $id_maestro,
                ':id_seccion' => $id_seccion,
                ':id_institucion' => $id_institucion
            ]);

            $mensaje = "Sección asignada exitosamente al maestro.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'duplicate') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
                $mensaje = "Esta sección ya está asignada a este maestro.";
                $tipo_mensaje = "warning";
            } else {
                $mensaje = "Error al asignar sección: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }
}

// ELIMINAR ASIGNACIÓN DE SECCIÓN
if (isset($_GET['eliminar_asignacion'])) {
    $id_asignacion = $_GET['eliminar_asignacion'];
    
    try {
        $stmt = $conn->prepare("
            DELETE FROM maestro_seccion 
            WHERE id_asignacion = :id_asignacion AND id_institucion = :id_institucion
        ");
        $stmt->execute([
            ':id_asignacion' => $id_asignacion,
            ':id_institucion' => $id_institucion
        ]);

        $mensaje = "Asignación eliminada correctamente.";
        $tipo_mensaje = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al eliminar asignación: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// ELIMINAR MAESTRO (solo de su institución)
if (isset($_GET['eliminar'])) {
    $id_maestro = $_GET['eliminar'];
    
    try {
        // Verificar que el maestro pertenece a la institución del director
        $stmt = $conn->prepare("
            SELECT id_usuario 
            FROM maestros 
            WHERE id_maestro = :id_maestro AND id_institucion = :id_institucion
        ");
        $stmt->execute([
            ':id_maestro' => $id_maestro,
            ':id_institucion' => $id_institucion
        ]);
        $maestro_data = $stmt->fetch();

        if ($maestro_data) {
            $conn->beginTransaction();

            // Eliminar asignaciones de secciones
            $stmt = $conn->prepare("DELETE FROM maestro_seccion WHERE id_maestro = :id_maestro");
            $stmt->execute([':id_maestro' => $id_maestro]);

            // Eliminar maestro
            $stmt = $conn->prepare("DELETE FROM maestros WHERE id_maestro = :id_maestro");
            $stmt->execute([':id_maestro' => $id_maestro]);

            // Eliminar usuario
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = :id_usuario");
            $stmt->execute([':id_usuario' => $maestro_data['id_usuario']]);

            $conn->commit();
            $mensaje = "Maestro eliminado correctamente.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "No tienes permiso para eliminar este maestro.";
            $tipo_mensaje = "warning";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// LISTAR MAESTROS (solo de la institución del director)
try {
    $stmt = $conn->prepare("
        SELECT m.*, u.correo, u.estado,
               COUNT(ms.id_asignacion) as total_secciones
        FROM maestros m
        INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
        LEFT JOIN maestro_seccion ms ON m.id_maestro = ms.id_maestro
        WHERE m.id_institucion = :id_institucion
        GROUP BY m.id_maestro, m.id_usuario, m.nombre, m.apellido, m.direccion, 
                 m.f_nacimiento, m.id_institucion, m.fecha_registro, u.correo, u.estado
        ORDER BY m.apellido, m.nombre
    ");
    $stmt->execute([':id_institucion' => $id_institucion]);
    $maestros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al listar maestros: " . $e->getMessage());
}

// LISTAR SECCIONES disponibles (TODAS las activas)
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT s.id_seccion, s.nombre_seccion, g.nombre_grado, s.turno
        FROM seccion s
        INNER JOIN grado g ON s.id_grado = g.id_grado
        WHERE s.estado = TRUE AND g.estado = TRUE
        ORDER BY g.nombre_grado, s.nombre_seccion
    ");
    $stmt->execute();
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $secciones = [];
}

// LISTAR ASIGNACIONES
try {
    $stmt = $conn->prepare("
        SELECT ms.id_asignacion, m.nombre, m.apellido, s.nombre_seccion, g.nombre_grado, s.turno
        FROM maestro_seccion ms
        INNER JOIN maestros m ON ms.id_maestro = m.id_maestro
        INNER JOIN seccion s ON ms.id_seccion = s.id_seccion
        INNER JOIN grado g ON s.id_grado = g.id_grado
        WHERE ms.id_institucion = :id_institucion
        ORDER BY m.apellido, m.nombre, g.nombre_grado, s.nombre_seccion
    ");
    $stmt->execute([':id_institucion' => $id_institucion]);
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $asignaciones = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Maestros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root{--sb-bg:#1a1d29;--sb-hv:#2d3142;--grad:linear-gradient(135deg,#667eea 0%,#764ba2 100%)}
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
        .lo{margin-top:20px;width:calc(100% - 20px);margin-left:10px;margin-right:10px}
        .lo:hover{background:#dc3545;color:#fff!important}
        .mc{margin-left:260px;padding:30px;min-height:100vh}
        .info-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px;border-left:4px solid #667eea}
        .info-box h5{color:#667eea;margin-bottom:10px}
        .badge{padding:6px 12px}
        .nav-tabs .nav-link{color:#667eea;font-weight:500}
        .nav-tabs .nav-link.active{background:var(--grad);color:#fff;border:none}
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
        <a href="director_panel.php" class="nl"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
        <a href="estudiantes.php" class="nl"><i class="bi bi-people-fill"></i><span>Estudiantes</span></a>
        <a href="maestros.php" class="nl active"><i class="bi bi-person-badge-fill"></i><span>Maestros</span></a>
        <a href="grados.php" class="nl"><i class="bi bi-collection-fill"></i><span>Grados y secciones</span></a>
        <a href="demeritos.php" class="nl"><i class="bi bi-flag-fill"></i><span>Deméritos</span></a>
        <a href="director_redenciones.php" class="nl"><i class="bi bi-hand-thumbs-up-fill"></i><span>Redenciones</span></a>
        <a href="logout.php" class="nl lo"><i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span></a>
    </nav>
</div>

<div class="mc">
    <h2 class="mb-4"><i class="bi bi-person-badge-fill"></i> Gestión de Maestros</h2>

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

    <!-- Tabs de navegación -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#maestros">
                <i class="bi bi-people"></i> Maestros
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#asignaciones">
                <i class="bi bi-diagram-3"></i> Asignar Secciones
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- TAB 1: MAESTROS -->
        <div class="tab-pane fade show active" id="maestros">
            <div class="row">
                <!-- Formulario agregar -->
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Agregar Maestro</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" name="nombre" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Apellido *</label>
                                    <input type="text" name="apellido" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Correo *</label>
                                    <input type="email" name="correo" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contraseña *</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" name="direccion" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" name="f_nacimiento" class="form-control">
                                </div>
                                <button type="submit" name="agregar_maestro" class="btn btn-primary w-100">
                                    <i class="bi bi-save"></i> Guardar Maestro
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Lista de maestros -->
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Maestros Registrados (<?= count($maestros) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($maestros)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No hay maestros registrados en esta institución.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre Completo</th>
                                            <th>Correo</th>
                                            <th>Secciones</th>
                                            <th>Estado</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($maestros as $m): ?>
                                        <tr>
                                            <td><?= $m['id_maestro'] ?></td>
                                            <td><strong><?= htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?></strong></td>
                                            <td><small><?= htmlspecialchars($m['correo']) ?></small></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-collection"></i> <?= $m['total_secciones'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($m['estado']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="?eliminar=<?= $m['id_maestro'] ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('¿Eliminar este maestro?')">
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

        <!-- TAB 2: ASIGNACIONES -->
        <div class="tab-pane fade" id="asignaciones">
            <div class="row">
                <!-- Formulario asignar sección -->
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Asignar Sección</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($maestros)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> No hay maestros disponibles.
                            </div>
                            <?php elseif (empty($secciones)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> No hay secciones disponibles.
                            </div>
                            <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Maestro *</label>
                                    <select name="id_maestro" class="form-select" required>
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach ($maestros as $m): ?>
                                        <option value="<?= $m['id_maestro'] ?>">
                                            <?= htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sección *</label>
                                    <select name="id_seccion" class="form-select" required>
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach ($secciones as $s): ?>
                                        <option value="<?= $s['id_seccion'] ?>">
                                            <?= htmlspecialchars($s['nombre_grado']) ?> - 
                                            <?= htmlspecialchars($s['nombre_seccion']) ?> 
                                            (<?= htmlspecialchars($s['turno']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="asignar_seccion" class="btn btn-success w-100">
                                    <i class="bi bi-check-circle"></i> Asignar
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Lista de asignaciones -->
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Asignaciones (<?= count($asignaciones) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($asignaciones)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No hay asignaciones registradas.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Maestro</th>
                                            <th>Grado</th>
                                            <th>Sección</th>
                                            <th>Turno</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($asignaciones as $asig): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($asig['nombre'] . ' ' . $asig['apellido']) ?></strong></td>
                                            <td><?= htmlspecialchars($asig['nombre_grado']) ?></td>
                                            <td><?= htmlspecialchars($asig['nombre_seccion']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($asig['turno']) ?></span></td>
                                            <td class="text-center">
                                                <a href="?eliminar_asignacion=<?= $asig['id_asignacion'] ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('¿Eliminar esta asignación?')">
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>