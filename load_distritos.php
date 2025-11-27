<?php
require 'db_connect.php';

$id = $_POST['id_municipio'];

$stmt = $conn->prepare("SELECT * FROM distritos WHERE id_municipio = ?");
$stmt->execute([$id]);

echo '<option value="">Seleccione un distrito</option>';

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<option value='{$row['id_distrito']}'>{$row['nombre_distrito']}</option>";
}
