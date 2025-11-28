<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'db_connect.php';

session_start();

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if(empty($correo) || empty($password)) {
        $error = 'Por favor completa todos los campos';
    } else {

        // Consulta a BD
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE correo = :correo LIMIT 1");
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();


        if ($stmt->rowCount() > 0) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            
            // Verificación de contraseña
           if (password_verify($password, $usuario['password_hash'])) {

                // Guardar sesión
                $_SESSION['usuario'] = $usuario['correo'];
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                $_SESSION['rol'] = $usuario['id_rol'];

                // Redirección por rol
                switch ($usuario['id_rol']) {

                    case 1:
                        header("Location: ministra.php");
                        break;

                    case 2:
                        header("Location: director_panel.php");
                        break;

                    case 3:
                        header("Location: subdirector_panel.php");
                        break;

                    case 4:
                        header("Location: maestro_panel.php");
                        break;

                    case 5:
                        header("Location: estudiante_panel.php");
                        break;

                    default:
                        header("Location: index.php");
                        break;
                }

                exit();

            } else {
                $error = 'Contraseña incorrecta';
            }

        } else {
            $error = 'Correo no registrado';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Sistema de Registro de Instituciones Educativas</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- aqui va el css -->
    <style>
        :root {
            --primary-soft: #6C9BCF;
            --secondary-soft: #A8D5BA;
            --accent-soft: #F4A5AE;
            --background-soft: #F8F9FA;
            --text-soft: #5A6C7D;
            --hover-soft: #E8F4F8;
            --border-soft: #E1E8ED;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #F5F7FA 0%, #E8F4F8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
        }
        
        .login-card {
            background: #FFFFFF;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(108, 155, 207, 0.15);
            overflow: hidden;
            border: 1px solid var(--border-soft);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-soft) 0%, #5A8AB8 100%);
            padding: 3rem 2rem;
            text-align: center;
            color: white;
        }
        
        .login-header i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.95;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.85rem;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .login-body {
            padding: 2.5rem 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-soft);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .form-control {
            border: 2px solid var(--border-soft);
            border-radius: 10px;
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-soft);
            box-shadow: 0 0 0 0.25rem rgba(108, 155, 207, 0.15);
        }
        
        .input-group-text {
            background: var(--background-soft);
            border: 2px solid var(--border-soft);
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: var(--text-soft);
            padding: 0.875rem 1rem;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: var(--primary-soft);
            background: #F0F7FF;
            color: var(--primary-soft);
        }
        
        .input-group:focus-within .form-control {
            border-color: var(--primary-soft);
        }
        
        .btn-login {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, var(--primary-soft) 0%, #5A8AB8 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 1.05rem;
            transition: all 0.3s ease;
            margin-top: 1.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 155, 207, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
            padding: 1rem;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #FFF0F2 0%, #FFE8EB 100%);
            color: #C85A63;
        }
        
        .helper-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .info-box {
            background: linear-gradient(135deg, #F0F7FF 0%, var(--hover-soft) 100%);
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            text-align: center;
        }
        
        .info-box i {
            color: var(--primary-soft);
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        
        .info-box p {
            margin: 0;
            color: var(--text-soft);
            font-size: 0.9rem;
        }
        
        @media (max-width: 576px) {
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .login-header {
                padding: 2.5rem 1.5rem;
            }
            
            .login-header i {
                font-size: 3rem;
            }
            
            .login-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>

</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-mortarboard-fill"></i>
                <h2>Sistema Educativo</h2>
                <p>Gestión de Instituciones Educativas</p>
            </div>
            
            <div class="login-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php" id="loginForm">
                    <div class="mb-3">
                        <label for="correo" class="form-label">
                            <i class="bi bi-envelope-fill"></i> Correo Electrónico
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope-fill"></i>
                            </span>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="correo" 
                                name="correo" 
                                placeholder="tu-correo@ejemplo.com"
                                required
                                autocomplete="email"
                                autofocus
                            >
                        </div>
                        <small class="helper-text">Ingresa el correo registrado en el sistema</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock-fill"></i> Contraseña
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                placeholder="Tu contraseña"
                                required
                                autocomplete="current-password"
                            >
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> Ingresar al Sistema
                    </button>
                </form>
                
                <div class="info-box">
                    <i class="bi bi-info-circle-fill"></i>
                    <p>Tu rol será detectado automáticamente según tu correo registrado</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>