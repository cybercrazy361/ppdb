<?php
$password_plain = 'Tutukhi123*';
$password_hashed = password_hash($password_plain, PASSWORD_BCRYPT);
echo $password_hashed;
?>
