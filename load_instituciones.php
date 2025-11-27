<?php
require 'db_connect.php';

$id = $_POST['id_distrito'];

$stmt = $conn->prepare("SELECT * FROM institucion WHERE id_distrito = ?");
$stmt->execute([$id]);

echo '<option value="">Seleccione una institución</option>';

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<option value='{$row['id_institucion']}'>{$row['nombre_institucion']}</option>";
}
