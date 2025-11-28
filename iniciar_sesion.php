<?php

session_start();
if($_SERVER['REQUEST_METHOD']=="POST"){
    include 'db_connect.php';
    $nombreUsuario=addslashes(trim($_POST['usuario'])) ?? '';
    $password=addslashes(trim($_POST['password'])) ?? '';
    if($nombreUsuario=='' || $password== ''){
        exit("Estas intentando de hacer algo indebido en el sistemas, Te vamos a reportar");
    }
    $query_select = "SELECT * FROM usuarios WHERE nombre_usuario = '".$nombreUsuario."' AND clave_usuario='".$password."'";
        $result_select = pg_query($conn, $query_select);
        if (!$result_select) {
            die("Error al leer usuario: " . pg_last_error($conn));
        }
    $row = pg_fetch_assoc($result_select);
    if($row){
        $_SESSION['usuario']=$nombreUsuario;
            header('location:index.php');
    }else{
    header('location:index.php?error=true');
    }
}
?>