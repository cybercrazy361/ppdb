<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Tes PHP jalan!<br>";

$hasil = @file_get_contents('https://google.com/');
if ($hasil) {
    echo "Internet OK";
} else {
    echo "Internet BERMASALAH dari server";
}
