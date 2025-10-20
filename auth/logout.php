<?php
require_once '../config/config.php';

// Destruir sessão
session_destroy();

// Redirecionar para login
redirect('/auth/login.php');
?>