<?php
// Mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';
session_start();

$mensaje = "";

// 1️⃣ Cargar departamentos
$departamentos = $conn->query("
    SELECT id_departamento, nombre_departamento 
    FROM departamentos 
    ORDER BY nombre_departamento ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Datos personales
    $nombre       = trim($_POST['nombre'] ?? '');
    $apellido     = trim($_POST['apellido'] ?? '');
    $correo       = trim($_POST['correo'] ?? '');
    $password     = $_POST['password'] ?? '';
    $direccion    = trim($_POST['direccion'] ?? '');
    $f_nacimiento = $_POST['f_nacimiento'] ?? '';
    $id_institucion = $_POST['id_institucion'] ?? null;

    if (empty($nombre) || empty($apellido) || empty($correo) || empty($password)) {
        $mensaje = "Todos los campos obligatorios deben completarse.";
    } else {
        try {

            // 2️⃣ Crear usuario (rol 3 = Subdirector)
            $nombre_completo = $nombre . " " . $apellido;
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO usuarios (nombre_completo, correo, password_hash, id_rol)
                VALUES (:nombre_completo, :correo, :password_hash, 3)
            ");

            $stmt->bindParam(':nombre_completo', $nombre_completo);
            $stmt->bindParam(':correo', $correo);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->execute();

            // Obtener id_usuario
            $id_usuario = $conn->lastInsertId();

            // 3️⃣ Insertar en tabla subdirector
            $stmt2 = $conn->prepare("
                INSERT INTO subdirector 
                (id_usuario, nombre, apellido, direccion, f_nacimiento, id_institucion)
                VALUES 
                (:id_usuario, :nombre, :apellido, :direccion, :f_nacimiento, :id_institucion)
            ");

            $stmt2->bindParam(':id_usuario', $id_usuario);
            $stmt2->bindParam(':nombre', $nombre);
            $stmt2->bindParam(':apellido', $apellido);
            $stmt2->bindParam(':direccion', $direccion);
            $stmt2->bindParam(':f_nacimiento', $f_nacimiento);
            $stmt2->bindParam(':id_institucion', $id_institucion);

            $stmt2->execute();

            header("Location: index.php?registrado_subdirector=1");
            exit();

        } catch (PDOException $e) {
            $mensaje = "Error al registrar subdirector: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Subdirector</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">
    <div class="card shadow col-md-8 mx-auto">
        <div class="card-body">

            <h2 class="mb-4">Registrar Subdirector</h2>

            <?php if (!empty($mensaje)): ?>
            <div class="alert alert-danger"><?= $mensaje ?></div>
            <?php endif; ?>

            <form method="POST">

                <!-- DATOS PERSONALES -->
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Apellido *</label>
                        <input type="text" name="apellido" class="form-control" required>
                    </div>
                </div>

                <!-- CORREO -->
                <div class="mb-3">
                    <label class="form-label">Correo Institucional *</label>
                    <input type="email" name="correo" class="form-control" required>
                </div>

                <!-- PASSWORD -->
                <div class="mb-3">
                    <label class="form-label">Contraseña *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <!-- DIRECCIÓN -->
                <div class="mb-3">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="direccion" class="form-control">
                </div>

                <!-- NACIMIENTO -->
                <div class="mb-3">
                    <label class="form-label">Fecha de nacimiento</label>
                    <input type="date" name="f_nacimiento" class="form-control">
                </div>

                <!-- ASIGNAR INSTITUCIÓN -->
                <h4 class="mt-4">Asignar Institución</h4>
                <hr>

                <div class="mb-3">
                    <label class="form-label">Departamento</label>
                    <select id="departamento" class="form-control" required>
                        <option value="">Seleccione un departamento</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id_departamento'] ?>"><?= $d['nombre_departamento'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Municipio/Distrito</label>
                    <select id="municipio" class="form-control" required>
                        <option value="">Seleccione un municipio</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Institución</label>
                    <select name="id_institucion" id="institucion" class="form-control" required>
                        <option value="">Seleccione una institución</option>
                    </select>
                </div>

                <button class="btn btn-primary w-100" type="submit">Registrar Subdirector</button>
            </form>

        </div>
    </div>
</div>

<!-- AJAX -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function(){

    $("#departamento").change(function(){
        let id = $(this).val();

        $("#municipio").html("<option>Cargando...</option>");
        $("#institucion").html("<option>Seleccione una institución</option>");

        $.post("load_municipios.php", { id_departamento: id }, function(data){
            $("#municipio").html(data);
        });
    });

    $("#municipio").change(function(){
        let id = $(this).val();

        $("#institucion").html("<option>Cargando...</option>");

        $.post("load_instituciones.php", { id_municipio: id }, function(data){
            $("#institucion").html(data);
        });
    });

});
</script>

</body>
</html>
