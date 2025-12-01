<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user_session = getLoggedUser();
$errors = [];
$success = '';

// Buscar dados completos do usuário
try {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_session['id']]);
    $user = $stmt->fetch();
    if (!$user) {
        redirect('/auth/logout.php');
    }
} catch (PDOException $e) {
    die("Erro ao buscar dados do usuário");
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action']);
    
    if ($action === 'dados_pessoais') {
        $nome = sanitize($_POST['nome']);
        $email = sanitize($_POST['email']);
        $telefone = sanitize($_POST['telefone']);
        $cpf = sanitize($_POST['cpf']);
        
        // Validações
        if (empty($nome)) $errors[] = 'Nome é obrigatório';
        if (empty($email)) $errors[] = 'Email é obrigatório';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
        
        // Verificar se email já existe
        if (!empty($email)) {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Este email já está sendo usado';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE usuarios 
                    SET nome = ?, email = ?, telefone = ?, cpf = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $email, $telefone, $cpf, $user['id']]);
                $success = 'Dados atualizados com sucesso!';
                
                // Atualizar sessão
                $_SESSION['user']['nome'] = $nome;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['telefone'] = $telefone;
                $_SESSION['user']['cpf'] = $cpf;
                $user = $_SESSION['user'];
            } catch (PDOException $e) {
                $errors[] = 'Erro ao atualizar dados';
            }
        }
    } elseif ($action === 'alterar_senha') {
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        
        if (empty($senha_atual)) $errors[] = 'Senha atual é obrigatória';
        if (empty($nova_senha)) $errors[] = 'Nova senha é obrigatória';
        if (strlen($nova_senha) < 6) $errors[] = 'Nova senha deve ter pelo menos 6 caracteres';
        if ($nova_senha !== $confirmar_senha) $errors[] = 'Confirmação de senha não confere';
        
        if (!empty($senha_atual) && !password_verify($senha_atual, $user['senha'])) {
            $errors[] = 'Senha atual incorreta';
        }
        
        if (empty($errors)) {
            try {
                $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt->execute([$nova_senha_hash, $user['id']]);
                $success = 'Senha alterada com sucesso!';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao alterar senha';
            }
        }
    }
}

// Buscar estatísticas do cliente
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_solicitacoes,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas,
            SUM(CASE WHEN status = 'concluida' THEN valor_total ELSE 0 END) as valor_total
        FROM solicitacoes
        WHERE cliente_id = ?
    ");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total_solicitacoes' => 0, 'concluidas' => 0, 'valor_total' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><?= SITE_NAME ?></h3>
            <p>Cliente</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php">📊 Dashboard</a></li>
            <li><a href="buscar_prestadores.php">🔍 Buscar Prestadores</a></li>
            <li><a href="solicitacoes.php">📋 Minhas Solicitações</a></li>
            <li><a href="enderecos.php">📍 Meus Endereços</a></li>
            <li><a href="perfil.php" class="active">👤 Meu Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>👤 Meu Perfil</h1>
            <p>Gerencie suas informações pessoais</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_solicitacoes'] ?></div>
                <div class="stat-label">Total de Solicitações</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value">R$ <?= number_format($stats['valor_total'], 2, ',', '.') ?></div>
                <div class="stat-label">Valor Total Investido</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <!-- Dados Pessoais -->
                <div class="card">
                    <div class="card-header">
                        <h3>Dados Pessoais</h3>
                    </div>
                    <form method="POST" class="card-body">
                        <input type="hidden" name="action" value="dados_pessoais">
                        
                        <div class="form-group">
                            <label for="nome">Nome Completo *</label>
                            <input type="text" id="nome" name="nome" required
                                   value="<?= htmlspecialchars($user['nome']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($user['email']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone"
                                   value="<?= htmlspecialchars($user['telefone'] ?? '') ?>"
                                   placeholder="(11) 99999-9999">
                        </div>
                        
                        <div class="form-group">
                            <label for="cpf">CPF</label>
                            <input type="text" id="cpf" name="cpf"
                                   value="<?= htmlspecialchars($user['cpf'] ?? '') ?>"
                                   placeholder="000.000.000-00">
                        </div>
                        
                        <div class="form-group">
                            <label>Membro desde</label>
                            <input type="text" value="<?= isset($user['data_cadastro']) ? date('d/m/Y', strtotime($user['data_cadastro'])) : 'N/A' ?>" readonly class="readonly">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            💾 Salvar Alterações
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Alterar Senha -->
                <div class="card">
                    <div class="card-header">
                        <h3>Alterar Senha</h3>
                    </div>
                    <form method="POST" class="card-body">
                        <input type="hidden" name="action" value="alterar_senha">
                        
                        <div class="form-group">
                            <label for="senha_atual">Senha Atual *</label>
                            <input type="password" id="senha_atual" name="senha_atual" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nova_senha">Nova Senha *</label>
                            <input type="password" id="nova_senha" name="nova_senha" required
                                   minlength="6" placeholder="Mínimo 6 caracteres">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Nova Senha *</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            🔒 Alterar Senha
                        </button>
                    </form>
                </div>

                <!-- Ações Rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h3>Ações Rápidas</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="buscar_prestadores.php" class="btn btn-primary btn-block">
                            🔍 Buscar Prestadores
                        </a>
                        <a href="solicitacoes.php" class="btn btn-info btn-block">
                            📋 Minhas Solicitações
                        </a>
                        <a href="enderecos.php" class="btn btn-success btn-block">
                            📍 Gerenciar Endereços
                        </a>
                        <a href="index.php" class="btn btn-secondary btn-block">
                            📊 Voltar ao Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Máscara para telefone
document.getElementById('telefone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
        e.target.value = value;
    }
});

// Máscara para CPF
document.getElementById('cpf').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    }
});

// Validação de confirmação de senha
document.getElementById('confirmar_senha').addEventListener('input', function() {
    const novaSenha = document.getElementById('nova_senha').value;
    const confirmarSenha = this.value;
    
    if (novaSenha !== confirmarSenha) {
        this.setCustomValidity('As senhas não conferem');
    } else {
        this.setCustomValidity('');
    }
});
</script>
</body>
</html>
