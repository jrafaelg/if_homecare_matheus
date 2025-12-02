<?php
// Script para gerar hashes de senha corretos
// Senha padrão para todos os usuários de teste: "123456"

$senha = "123456";
$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "Hash da senha '$senha': $hash\n";
echo "\nUse este hash no script SQL para substituir os hashes incorretos.\n";
echo "\nPara testar o login:\n";
echo "Email: joao.silva@email.com\n";
echo "Senha: 123456\n";
