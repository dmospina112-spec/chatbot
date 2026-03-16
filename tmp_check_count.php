<?php
$c=new mysqli('localhost','root','juana20189','app_educativa_xampp',3306);
$c->set_charset('utf8mb4');
$r=$c->query("SELECT COUNT(*) total, SUM(activo=1) activos FROM estudiantes");
$row=$r->fetch_assoc();
echo 'total='.$row['total'].' activos='.$row['activos'].PHP_EOL;
$r2=$c->query("SELECT numero_matricula, CONCAT(apellido,' ',nombre) n FROM estudiantes WHERE activo=1 ORDER BY apellido,nombre");
$i=0; while($x=$r2->fetch_assoc()){ $i++; echo $i.'. '.$x['numero_matricula'].' - '.$x['n'].PHP_EOL; }
