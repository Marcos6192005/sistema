<?php   
require 'db_connect.php';

$id = $_POST['id_departamento'];

$stmt = $conn->prepare("SELECT * FROM municipios WHERE id_departamento = ?");
$stmt->execute([$id]);

echo '<option value="">Seleccione un municipio</option>';

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<option value='{$row['id_municipio']}'>{$row['nombre_municipio']}</option>";
}
