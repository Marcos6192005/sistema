<?php
session_start();

echo "<h2>Información de la Sesión Actual</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";
echo "<h3>Variables Importantes:</h3>";
echo "Rol actual: " . (isset($_SESSION['rol']) ? $_SESSION['rol'] : 'NO DEFINIDO') . "<br>";
echo "ID Usuario: " . (isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : 'NO DEFINIDO') . "<br>";
echo "Nombre: " . (isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : 'NO DEFINIDO') . "<br>";

echo "<hr>";
echo "<h3>Guía de Roles:</h3>";
echo "1 = Administrador<br>";
echo "2 = Maestro<br>";
echo "3 = Director<br>";
echo "4 = Subdirector<br>";
echo "5 = Estudiante<br>";
?>