<?php

// Necesito mostrar los números del 1 al 10,
// y al lado definir si es par o impar.

// Vamos a usar un bucle.
$data1 = ['Casa', 'Carro', 'Perro', 'Gato', 'Pato'];
$data2 = [
  0 => 'Casa2',
  1 => 'Carro2',
  8 => 'Perro2',
  7 =>'Gato2',
  9 =>'Pato2'
];
?>
<html>
<head>
  <title>Ejercicio de usar el FOR</title>
</head>
<body>
<p>Mostramos los números del 1 al 10 y al lado colocar si es par o impar</p>
<table border="1" align="center">
  <tr>
    <th>Número</th>
    <th>Posición en el arreglo</th>
  </tr>
  <?php for ($i = 0; $i <= 4; $i++) { ?>
    <tr>
      <td><?= $i;?></td>
      <td><?= $data1[$i];?></td>
    </tr>
   <?php } ?>
  <?php foreach ($data2 as $key => $value) { ?>
    <tr>
      <td><?= $key; ?></td>
      <td><?= $value; ?></td>
    </tr>
  <?php } ?>
</table>
</body>
</html>

