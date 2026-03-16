<?php
register_shutdown_function(static function () {
    @unlink(__FILE__);
});
error_reporting(E_ALL);
ini_set('display_errors', '1');
$mysqli = new mysqli('localhost', 'root', 'juana20189', 'app_educativa_xampp');
if ($mysqli->connect_errno) {
    echo 'connect error ' . $mysqli->connect_error . "\n";
    exit(1);
}
$mysqli->set_charset('utf8mb4');
$result = $mysqli->query("SHOW COLUMNS FROM docentes LIKE 'rol'");
if ($result && $result->num_rows === 0) {
    $mysqli->query("ALTER TABLE docentes ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'docente' AFTER nombre");
}
$mysqli->query("UPDATE docentes SET rol = 'administrador' WHERE usuario = 'admin'");
$mysqli->close();
echo 'OK';
?>
