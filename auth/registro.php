<?php
require_once '../config/config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    $tipo = $_SESSION['user_tipo'];
    redirect("/$tipo/index.php");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome']);
    $email = sanitize($_POST['email']);
    $telefone = sanitize($_POST['telefone']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $tipo_usuario = $_POST['tipo_usuario'];

    // Validações
    if (empty($nome) || empty($email) || empty($cpf) || empty($senha) || empty($tipo_usuario)) {
        $error = 'Preencha todos os campos obrigatórios';
    } elseif (!isValidEmail($email)) {
        $error = 'Email inválido';
    } elseif (!isValidCPF($cpf)) {
        $error = 'CPF inválido';
    } elseif (strlen($senha) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres';
    } elseif ($senha !== $confirmar_senha) {
        $error = 'As senhas não coincidem';
    } elseif (!in_array($tipo_usuario, ['cliente', 'prestador'])) {
        $error = 'Tipo de usuário inválido';
    } else {
        try {
            $db = getDB();

            // Verificar se email já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Este email já está cadastrado';
            } else {
                // Verificar se CPF já existe
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE cpf = ?");
                $stmt->execute([$cpf]);
                if ($stmt->fetch()) {
                    $error = 'Este CPF já está cadastrado';
                } else {
                    // Inserir usuário
                    $senha_hash = hashPassword($senha);

                    $stmt = $db->prepare("
                        INSERT INTO usuarios (nome, email, telefone, cpf, senha, tipo_usuario, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'ativo')
                    ");

                    $stmt->execute([$nome, $email, $telefone, $cpf, $senha_hash, $tipo_usuario]);
                    $user_id = $db->lastInsertId();

                    // Se for prestador, criar perfil
                    if ($tipo_usuario === 'prestador') {
                        $stmt = $db->prepare("
                            INSERT INTO perfil_prestador (prestador_id) VALUES (?)
                        ");
                        $stmt->execute([$user_id]);
                    }

                    $success = 'Cadastro realizado com sucesso! Faça login para continuar.';

                    // Limpar campos
                    $_POST = [];
                }
            }
        } catch (PDOException $e) {
            $error = 'Erro ao realizar cadastro. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
<div class="auth-container">
    <div class="auth-card register-card">
        <div class="auth-header">
            <h1><?= SITE_NAME ?></h1>
            <p>Crie sua conta gratuitamente</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success ?>
                <a href="login.php">Clique aqui para fazer login</a>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form" id="registerForm">
            <div class="form-group">
                <label for="tipo_usuario">Tipo de Conta *</label>
                <select id="tipo_usuario" name="tipo_usuario" required>
                    <option value="">Selecione...</option>
                    <option value="cliente" <?= (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'cliente') ? 'selected' : '' ?>>
                        Cliente - Contratar Serviços
                    </option>
                    <option value="prestador" <?= (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'prestador') ? 'selected' : '' ?>>
                        Prestador - Oferecer Serviços
                    </option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" required
                           value="<?= isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : '' ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cpf">CPF *</label>
                    <input type="text" id="cpf" name="cpf" required
                           maxlength="14" placeholder="000.000.000-00"
                           value="<?= isset($_POST['cpf']) ? htmlspecialchars($_POST['cpf']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone"
                           maxlength="15" placeholder="(00) 00000-0000"
                           value="<?= isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : '' ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="senha">Senha *</label>
                    <input type="password" id="senha" name="senha" required minlength="6">
                    <small>Mínimo de 6 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Senha *</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Criar Conta</button>
        </form>

        <div class="auth-footer">
            <p>Já tem uma conta?</p>
            <a href="login.php" class="btn btn-secondary btn-block">Fazer Login</a>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
    // Máscaras para CPF e Telefone
    document.getElementById('cpf').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        }
    });

    document.getElementById('telefone').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = value;
        }
    });

    // Validação de confirmação de senha
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const senha = document.getElementById('senha').value;
        const confirmar = document.getElementById('confirmar_senha').value;

        if (senha !== confirmar) {
            e.preventDefault();
            alert('As senhas não coincidem!');
        }
    });
</script>
</body>
</html>