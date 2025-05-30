<?php
$result = @file_get_contents('https://google.com/');
if ($result) {
    echo "Internet OK";
} else {
    echo "Internet BERMASALAH dari server";
}
?>
