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
$nombre = $_SESSION['nombre_completo'];

// Obtener datos del maestro e institución
try {
    $stmt = $conn->prepare("
        SELECT m.id_maestro, m.id_institucion, i.nombre_institucion,
               mun.nombre_municipio, dep.nombre_departamento
        FROM maestros m
        LEFT JOIN institucion i ON m.id_institucion = i.id_institucion
        LEFT JOIN municipios mun ON i.id_municipio = mun.id_municipio
        LEFT JOIN departamentos dep ON i.id_departamento = dep.id_departamento
        WHERE m.id_usuario = :id_usuario
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $maestro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$maestro || !$maestro['id_institucion']) {
        die("<div class='alert alert-danger m-5'>Error: Maestro sin institución asignada.</div>");
    }

    $id_maestro = $maestro['id_maestro'];
    $id_institucion = $maestro['id_institucion'];
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Crear columna id_usuario en estudiante si no existe
try {
    $conn->exec("
        DO $$ 
        BEGIN 
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                          WHERE table_name='estudiante' AND column_name='id_usuario') THEN
                ALTER TABLE estudiante ADD COLUMN id_usuario INT;
            END IF;
        END $$;
    ");
} catch (PDOException $e) {
    // La columna ya existe, continuar
}

// Cargar SOLO las secciones asignadas al maestro
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT s.id_seccion, s.nombre_seccion, g.id_grado, g.nombre_grado, s.turno,
               COUNT(DISTINCT e.id_estudiante) as total_estudiantes
        FROM maestro_seccion ms
        INNER JOIN seccion s ON ms.id_seccion = s.id_seccion
        INNER JOIN grado g ON s.id_grado = g.id_grado
        LEFT JOIN estudiante e ON e.id_seccion = s.id_seccion AND e.id_institucion = :id_institucion
        WHERE ms.id_maestro = :id_maestro AND ms.id_institucion = :id_institucion
        GROUP BY s.id_seccion, s.nombre_seccion, g.id_grado, g.nombre_grado, s.turno
        ORDER BY g.nombre_grado, s.nombre_seccion
    ");
    $stmt->execute([
        ':id_maestro' => $id_maestro,
        ':id_institucion' => $id_institucion
    ]);
    $mis_secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mis_secciones = [];
}

// Agrupar secciones por grado para el selector
$secciones_por_grado = [];
foreach ($mis_secciones as $sec) {
    $id_grado = $sec['id_grado'];
    if (!isset($secciones_por_grado[$id_grado])) {
        $secciones_por_grado[$id_grado] = [
            'nombre_grado' => $sec['nombre_grado'],
            'secciones' => []
        ];
    }
    $secciones_por_grado[$id_grado]['secciones'][] = $sec;
}

$mensaje = "";
$tipo_mensaje = "";

// AGREGAR ESTUDIANTE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agregar_estudiante'])) {
    $nie = trim($_POST['nie'] ?? '');
    $nombre_est = trim($_POST['nombre'] ?? '');
    $apellido_est = trim($_POST['apellido'] ?? '');
    $id_seccion = $_POST['id_seccion'] ?? null;
    $direccion = trim($_POST['direccion'] ?? '');
    $f_nacimiento = $_POST['f_nacimiento'] ?? null;
    $password_estudiante = $_POST['password_estudiante'] ?? '';

    if (empty($nie) || empty($nombre_est) || empty($apellido_est) || !$id_seccion || empty($password_estudiante)) {
        $mensaje = "Todos los campos obligatorios deben completarse, incluyendo la contraseña.";
        $tipo_mensaje = "danger";
    } else {
        try {
            // Verificar que la sección pertenezca al maestro
            $stmt_check = $conn->prepare("
                SELECT s.id_grado 
                FROM maestro_seccion ms
                INNER JOIN seccion s ON ms.id_seccion = s.id_seccion
                WHERE ms.id_maestro = :id_maestro 
                AND ms.id_seccion = :id_seccion 
                AND ms.id_institucion = :id_institucion
            ");
            $stmt_check->execute([
                ':id_maestro' => $id_maestro,
                ':id_seccion' => $id_seccion,
                ':id_institucion' => $id_institucion
            ]);
            $seccion_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$seccion_data) {
                $mensaje = "No tienes permiso para agregar estudiantes a esta sección.";
                $tipo_mensaje = "warning";
            } else {
                $conn->beginTransaction();

                // Crear usuario para el estudiante
                $nie_limpio = preg_replace('/[^0-9]/', '', $nie); // Quitar guiones
                $correo_estudiante = "nie" . $nie_limpio . "@estudiante.edu.sv";
                $nombre_completo = $nombre_est . " " . $apellido_est;
                $password_hash = password_hash($password_estudiante, PASSWORD_DEFAULT);

                // Verificar si el rol estudiante existe (rol 5)
                $stmt_rol = $conn->query("SELECT id_rol FROM rol WHERE id_rol = 5");
                if (!$stmt_rol->fetch()) {
                    // Crear rol estudiante si no existe
                    $conn->exec("INSERT INTO rol (id_rol, nombre_rol) VALUES (5, 'Estudiante')");
                }

                // Crear usuario
                $stmt_user = $conn->prepare("
                    INSERT INTO usuarios (nombre_completo, correo, password_hash, id_rol, estado)
                    VALUES (:nombre_completo, :correo, :password_hash, 5, TRUE)
                ");
                $stmt_user->execute([
                    ':nombre_completo' => $nombre_completo,
                    ':correo' => $correo_estudiante,
                    ':password_hash' => $password_hash
                ]);

                $id_usuario_estudiante = $conn->lastInsertId();

                // Crear estudiante con referencia al usuario
                $stmt = $conn->prepare("
                    INSERT INTO estudiante (nie, nombre, apellido, id_grado, id_seccion, id_institucion, direccion, f_nacimiento, estado_matriculado, id_usuario)
                    VALUES (:nie, :nombre, :apellido, :id_grado, :id_seccion, :id_institucion, :direccion, :f_nacimiento, TRUE, :id_usuario)
                ");
                $stmt->execute([
                    ':nie' => $nie,
                    ':nombre' => $nombre_est,
                    ':apellido' => $apellido_est,
                    ':id_grado' => $seccion_data['id_grado'],
                    ':id_seccion' => $id_seccion,
                    ':id_institucion' => $id_institucion,
                    ':direccion' => $direccion,
                    ':f_nacimiento' => $f_nacimiento ?: null,
                    ':id_usuario' => $id_usuario_estudiante
                ]);

                $conn->commit();
                $mensaje = "Estudiante agregado exitosamente. Credenciales: <strong>" . htmlspecialchars($correo_estudiante) . "</strong> / Contraseña: (la que asignaste)";
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            if (strpos($e->getMessage(), 'correo') !== false) {
                $mensaje = "Error: Ya existe un estudiante con ese NIE.";
            } else {
                $mensaje = "Error: " . $e->getMessage();
            }
            $tipo_mensaje = "danger";
        }
    }
}

// ELIMINAR ESTUDIANTE
if (isset($_GET['eliminar'])) {
    try {
        // Verificar que el estudiante esté en una sección del maestro
        $stmt_check = $conn->prepare("
            SELECT e.id_estudiante, e.id_usuario
            FROM estudiante e
            INNER JOIN maestro_seccion ms ON e.id_seccion = ms.id_seccion
            WHERE e.id_estudiante = :id_estudiante 
            AND ms.id_maestro = :id_maestro
            AND e.id_institucion = :id_institucion
        ");
        $stmt_check->execute([
            ':id_estudiante' => $_GET['eliminar'],
            ':id_maestro' => $id_maestro,
            ':id_institucion' => $id_institucion
        ]);
        $estudiante_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($estudiante_data) {
            $conn->beginTransaction();

            // Eliminar estudiante
            $stmt = $conn->prepare("DELETE FROM estudiante WHERE id_estudiante = :id");
            $stmt->execute([':id' => $_GET['eliminar']]);

            // Eliminar usuario asociado si existe
            if ($estudiante_data['id_usuario']) {
                $stmt_del_user = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = :id_usuario");
                $stmt_del_user->execute([':id_usuario' => $estudiante_data['id_usuario']]);
            }

            $conn->commit();
            $mensaje = "Estudiante y sus credenciales eliminados.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "No tienes permiso para eliminar este estudiante.";
            $tipo_mensaje = "warning";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// LISTAR ESTUDIANTES (solo de las secciones del maestro)
try {
    $stmt = $conn->prepare("
        SELECT e.*, g.nombre_grado, s.nombre_seccion, s.turno, u.correo as correo_acceso
        FROM estudiante e
        INNER JOIN grado g ON e.id_grado = g.id_grado
        INNER JOIN seccion s ON e.id_seccion = s.id_seccion
        INNER JOIN maestro_seccion ms ON e.id_seccion = ms.id_seccion
        LEFT JOIN usuarios u ON e.id_usuario = u.id_usuario
        WHERE ms.id_maestro = :id_maestro 
        AND e.id_institucion = :id_institucion
        ORDER BY g.nombre_grado, s.nombre_seccion, e.apellido, e.nombre
    ");
    $stmt->execute([
        ':id_maestro' => $id_maestro,
        ':id_institucion' => $id_institucion
    ]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $estudiantes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Estudiantes</title>
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
        .info-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px;border-left:4px solid #16a085}
        .info-box h5{color:#16a085;margin-bottom:10px}
        .badge{padding:6px 12px}
        .alert-no-secciones{background:#fff3cd;border-left:4px solid #ffc107;padding:20px;border-radius:10px;margin-bottom:20px}
        .password-preview{font-family:monospace;background:#f8f9fa;padding:5px 10px;border-radius:4px;display:inline-block;margin-top:5px}
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
        <a href="maestro_estudiantes.php" class="nl active"><i class="bi bi-people-fill"></i><span>Estudiantes</span></a>
        <a href="maestro_secciones.php" class="nl"><i class="bi bi-grid-3x3-gap-fill"></i><span>Secciones</span></a>
        <a href="maestro_demeritos.php" class="nl"><i class="bi bi-flag-fill"></i><span>Deméritos</span></a>
        <a href="logout.php" class="nl lo"><i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span></a>
    </nav>
</div>

<div class="mc">
    <h2 class="mb-4"><i class="bi bi-people-fill"></i> Gestión de Estudiantes</h2>

    <div class="info-box">
        <h5><i class="bi bi-building"></i> Institución</h5>
        <p><strong><?= htmlspecialchars($maestro['nombre_institucion']) ?></strong> - <?= htmlspecialchars($maestro['nombre_municipio']) ?>, <?= htmlspecialchars($maestro['nombre_departamento']) ?></p>
    </div>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show">
        <?= $mensaje ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (empty($mis_secciones)): ?>
    <div class="alert-no-secciones">
        <h5><i class="bi bi-exclamation-triangle"></i> Sin Secciones Asignadas</h5>
        <p>No tienes secciones asignadas aún. Contacta al director para que te asigne las secciones en las que impartirás clases.</p>
        <a href="maestro.php" class="btn btn-primary mt-2">
            <i class="bi bi-arrow-left"></i> Volver al Dashboard
        </a>
    </div>
    <?php else: ?>

    <!-- Resumen de secciones -->
    <div class="row mb-4">
        <?php foreach ($mis_secciones as $sec): ?>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted"><?= htmlspecialchars($sec['nombre_grado']) ?></h6>
                    <h4><?= htmlspecialchars($sec['nombre_seccion']) ?></h4>
                    <span class="badge bg-info"><?= htmlspecialchars($sec['turno']) ?></span>
                    <p class="mt-2 mb-0">
                        <strong><?= $sec['total_estudiantes'] ?></strong> estudiantes
                    </p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <!-- Formulario -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Agregar Estudiante</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">NIE *</label>
                            <input type="text" name="nie" id="nieInput" class="form-control" required placeholder="Ej: 123456-7">
                            <small class="text-muted">El correo será: <span id="correoPreview" class="password-preview">nie______@estudiante.edu.sv</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="apellido" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sección *</label>
                            <select name="id_seccion" class="form-select" required>
                                <option value="">-- Seleccionar sección --</option>
                                <?php foreach ($secciones_por_grado as $id_grado => $data): ?>
                                    <optgroup label="<?= htmlspecialchars($data['nombre_grado']) ?>">
                                        <?php foreach ($data['secciones'] as $sec): ?>
                                        <option value="<?= $sec['id_seccion'] ?>">
                                            <?= htmlspecialchars($sec['nombre_seccion']) ?> - <?= htmlspecialchars($sec['turno']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña para el estudiante *</label>
                            <input type="text" name="password_estudiante" class="form-control" required placeholder="Ej: nie123456">
                            <small class="text-muted">El estudiante usará esta contraseña para acceder</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha Nacimiento</label>
                            <input type="date" name="f_nacimiento" class="form-control">
                        </div>
                        <button type="submit" name="agregar_estudiante" class="btn btn-primary w-100">
                            <i class="bi bi-save"></i> Guardar Estudiante
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Mis Estudiantes (<?= count($estudiantes) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($estudiantes)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No hay estudiantes registrados en tus secciones.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>NIE</th>
                                    <th>Nombre</th>
                                    <th>Grado/Sección</th>
                                    <th>Correo Acceso</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $est): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($est['nie']) ?></strong></td>
                                    <td><?= htmlspecialchars($est['nombre'] . ' ' . $est['apellido']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($est['nombre_grado']) ?> -
                                        <?= htmlspecialchars($est['nombre_seccion']) ?>
                                        <small class="text-muted">(<?= htmlspecialchars($est['turno']) ?>)</small>
                                    </td>
                                    <td><small><?= htmlspecialchars($est['correo_acceso'] ?? 'Sin acceso') ?></small></td>
                                    <td>
                                        <?php if ($est['estado_matriculado']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="maestro_demeritos.php?estudiante=<?= $est['id_estudiante'] ?>" 
                                           class="btn btn-sm btn-warning" title="Registrar demérito">
                                            <i class="bi bi-flag"></i>
                                        </a>
                                        <a href="?eliminar=<?= $est['id_estudiante'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('¿Eliminar estudiante y sus credenciales?')">
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
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Preview del correo basado en NIE
document.getElementById('nieInput').addEventListener('input', function() {
    const nie = this.value.replace(/[^0-9]/g, '');
    const preview = document.getElementById('correoPreview');
    if (nie.length > 0) {
        preview.textContent = 'nie' + nie + '@estudiante.edu.sv';
    } else {
        preview.textContent = 'nie______@estudiante.edu.sv';
    }
});
</script>
</body>
</html>