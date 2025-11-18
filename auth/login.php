<?php
require_once '../config/config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    $tipo = $_SESSION['user_tipo'];
    redirect("/$tipo/index.php");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        $error = 'Preencha todos os campos';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($senha, $user['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_tipo'] = $user['tipo_usuario'];

                // Redirecionar para o dashboard correspondente
                redirect("/{$user['tipo_usuario']}/index.php");
            } else {
                $error = 'Email ou senha incorretos';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao realizar login. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1><?= SITE_NAME ?></h1>
            <p>Faça login para continuar</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>

        <div class="auth-footer">
            <p>Não tem uma conta?</p>
            <a href="registro.php" class="btn btn-secondary btn-block">Criar Conta</a>
        </div>
    </div>
</div>
</body>
</html>