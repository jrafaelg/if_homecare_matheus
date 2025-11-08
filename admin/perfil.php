<?php
require_once '../config/config.php';
checkUserType(USER_ADMIN);
$db = getDB();
$user = getLoggedUser();
$errors = [];
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action']);
    
    if ($action === 'dados_pessoais') {
        $nome = sanitize($_POST['nome']);
        $email = sanitize($_POST['email']);
        $telefone = sanitize($_POST['telefone']);
        
        // Validações
        if (empty($nome)) $errors[] = 'Nome é obrigatório';
        if (empty($email)) $errors[] = 'Email é obrigatório';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
        
        // Verificar se email já existe (exceto o próprio usuário)
        if (!empty($email)) {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Este email já está sendo usado por outro usuário';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE usuarios 
                    SET nome = ?, email = ?, telefone = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $email, $telefone, $user['id']]);
                $success = 'Dados pessoais atualizados com sucesso!';
                // Atualizar dados na sessão
                $_SESSION['user']['nome'] = $nome;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['telefone'] = $telefone;
                $user = $_SESSION['user'];
            } catch (PDOException $e) {
                $errors[] = 'Erro ao atualizar dados pessoais';
            }
        }
    } elseif ($action === 'alterar_senha') {
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        
        // Validações
        if (empty($senha_atual)) $errors[] = 'Senha atual é obrigatória';
        if (empty($nova_senha)) $errors[] = 'Nova senha é obrigatória';
        if (strlen($nova_senha) < 6) $errors[] = 'Nova senha deve ter pelo menos 6 caracteres';
        if ($nova_senha !== $confirmar_senha) $errors[] = 'Confirmação de senha não confere';
        
        // Verificar senha atual
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
    } elseif ($action === 'configuracoes') {
        $timezone = sanitize($_POST['timezone']);
        $notificacoes_email = isset($_POST['notificacoes_email']) ? 1 : 0;
        $relatorios_automaticos = isset($_POST['relatorios_automaticos']) ? 1 : 0;
        
        try {
            // Verificar se já existe configuração para este admin
            $stmt = $db->prepare("SELECT id FROM admin_configuracoes WHERE admin_id = ?");
            $stmt->execute([$user['id']]);
            $config_exists = $stmt->fetch();
            
            if ($config_exists) {
                $stmt = $db->prepare("
                    UPDATE admin_configuracoes 
                    SET timezone = ?, notificacoes_email = ?, relatorios_automaticos = ?
                    WHERE admin_id = ?
                ");
                $stmt->execute([$timezone, $notificacoes_email, $relatorios_automaticos, $user['id']]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO admin_configuracoes (admin_id, timezone, notificacoes_email, relatorios_automaticos)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user['id'], $timezone, $notificacoes_email, $relatorios_automaticos]);
            }
            $success = 'Configurações salvas com sucesso!';
        } catch (PDOException $e) {
            $errors[] = 'Erro ao salvar configurações';
        }
    }
}

// Buscar configurações do admin
try {
    $stmt = $db->prepare("SELECT * FROM admin_configuracoes WHERE admin_id = ?");
    $stmt->execute([$user['id']]);
    $configuracoes = $stmt->fetch();
} catch (PDOException $e) {
    $configuracoes = [];
}

// Buscar estatísticas do admin
try {
    $stmt = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM usuarios WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as usuarios_mes,
            (SELECT COUNT(*) FROM solicitacoes WHERE data_solicitacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as solicitacoes_mes,
            (SELECT COUNT(*) FROM avaliacoes WHERE data_avaliacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as avaliacoes_mes
    ");
    $stats_admin = $stmt->fetch();
} catch (PDOException $e) {
    $stats_admin = [];
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
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><?= SITE_NAME ?></h3>
            <p>Administrador</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php">📊 Dashboard</a></li>
            <li><a href="usuarios.php">👥 Usuários</a></li>
            <li><a href="servicos.php">🏥 Serviços</a></li>
            <li><a href="relatorios.php">📈 Relatórios</a></li>
            <li><a href="perfil.php" class="active">👤 Meu Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>👤 Meu Perfil</h1>
            <p>Gerencie suas informações pessoais e configurações</p>
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

        <!-- Estatísticas Rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats_admin['usuarios_mes'] ?? 0 ?></div>
                <div class="stat-label">Usuários este mês</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats_admin['solicitacoes_mes'] ?? 0 ?></div>
                <div class="stat-label">Solicitações este mês</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats_admin['avaliacoes_mes'] ?? 0 ?></div>
                <div class="stat-label">Avaliações este mês</div>
            </div>
        </div>

        <div class="row">
            <!-- Dados Pessoais -->
            <div class="col-md-6">
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
                                   value="<?= htmlspecialchars($user['telefone']) ?>"
                                   placeholder="(11) 99999-9999">
                        </div>
                        
                        <div class="form-group">
                            <label>Tipo de Usuário</label>
                            <input type="text" value="Administrador" readonly class="readonly">
                        </div>
                        
                        <div class="form-group">
                            <label>Membro desde</label>
                            <input type="text" value="<?= date('d/m/Y', strtotime($user['data_cadastro'])) ?>" readonly class="readonly">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            💾 Salvar Alterações
                        </button>
                    </form>
                </div>

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
            </div>

            <!-- Configurações -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Configurações do Sistema</h3>
                    </div>
                    <form method="POST" class="card-body">
                        <input type="hidden" name="action" value="configuracoes">
                        
                        <div class="form-group">
                            <label for="timezone">Fuso Horário</label>
                            <select id="timezone" name="timezone">
                                <option value="America/Sao_Paulo" <?= ($configuracoes['timezone'] ?? 'America/Sao_Paulo') === 'America/Sao_Paulo' ? 'selected' : '' ?>>
                                    São Paulo (GMT-3)
                                </option>
                                <option value="America/Manaus" <?= ($configuracoes['timezone'] ?? '') === 'America/Manaus' ? 'selected' : '' ?>>
                                    Manaus (GMT-4)
                                </option>
                                <option value="America/Rio_Branco" <?= ($configuracoes['timezone'] ?? '') === 'America/Rio_Branco' ? 'selected' : '' ?>>
                                    Rio Branco (GMT-5)
                                </option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="notificacoes_email" value="1"
                                       <?= ($configuracoes['notificacoes_email'] ?? 1) ? 'checked' : '' ?>>
                                <span>Receber notificações por email</span>
                            </label>
                            <small>Receba alertas sobre atividades importantes do sistema</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="relatorios_automaticos" value="1"
                                       <?= ($configuracoes['relatorios_automaticos'] ?? 0) ? 'checked' : '' ?>>
                                <span>Relatórios automáticos semanais</span>
                            </label>
                            <small>Receba relatórios de atividade por email toda segunda-feira</small>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            ⚙️ Salvar Configurações
                        </button>
                    </form>
                </div>

                <!-- Informações do Sistema -->
                <div class="card">
                    <div class="card-header">
                        <h3>Informações do Sistema</h3>
                    </div>
                    <div class="system-info">
                        <div class="info-item">
                            <strong>Versão do Sistema:</strong>
                            <span>1.0.0</span>
                        </div>
                        <div class="info-item">
                            <strong>Última Atualização:</strong>
                            <span><?= date('d/m/Y H:i') ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Servidor:</strong>
                            <span><?= $_SERVER['SERVER_NAME'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>PHP:</strong>
                            <span><?= PHP_VERSION ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Banco de Dados:</strong>
                            <span>MySQL</span>
                        </div>
                    </div>
                </div>

                <!-- Ações Administrativas -->
                <div class="card">
                    <div class="card-header">
                        <h3>Ações Rápidas</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="usuarios.php" class="btn btn-primary btn-block">
                            👥 Gerenciar Usuários
                        </a>
                        <a href="servicos.php" class="btn btn-info btn-block">
                            🏥 Gerenciar Serviços
                        </a>
                        <a href="relatorios.php" class="btn btn-success btn-block">
                            📈 Ver Relatórios
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
