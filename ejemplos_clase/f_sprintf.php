<?php

/**
 * La función sprintf() se usa para crear cadenas formateadas
 * El primer argumento es la cadena de texto, que contiene símbolos que
 * representan los valores a sustituir en ella, como por ejemplo %s, %d
 * que indican que en su lugar se insertaran una cadena de texto y una
 * variable numérica respectivamente.
 * A partir del segundo argumento, en orden según aparecen en la cadena
 * del primer argumento, están los valores por los que seran sustituidos
 * los símbolos
 */

$nombre = 'Carlos';
$edad = 46;
$ciudad = 'Madrid';

// "El profesor Carlos tiene 46 años y vive en Madrid."
echo "El profesor " . $nombre . " tiene " . $edad . " años y vive en " . $ciudad . "." . PHP_EOL;
echo sprintf("El profesor %s tiene %d años y vive en %s.", $nombre, $edad, $ciudad);