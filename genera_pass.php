<?php
// Sostituisci 'la_tua_password' con quella che vuoi usare davvero
$password_da_nascondere = 'IPDicembre2025.';
echo password_hash($password_da_nascondere, PASSWORD_DEFAULT);
?>