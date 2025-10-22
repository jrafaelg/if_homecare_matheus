<?php
require_once '../config/config.php';
checkUserType(USER_PRESTADOR);

$db = getDB();
$user = getLoggedUser();

// Buscar dados do usuário
try {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user['id']]);
    $usuario = $stmt->fetch();

    // Buscar perfil profissional
    $stmt = $db->prepare("SELECT * FROM perfil_prestador WHERE prestador_id = ?");
    $stmt->execute([$user['id']]);
    $perfil = $stmt->fetch();

    // Se não existir perfil, criar
    if (!$perfil) {
        $stmt = $db->prepare("INSERT INTO perfil_prestador (prestador_id) VALUES (?)");
        $stmt->execute([$user['id']]);

        $stmt = $db->prepare("SELECT * FROM perfil_prestador WHERE prestador_id = ?");
        $stmt->execute([$user['id']]);
        $perfil = $stmt->fetch();
    }

    // Buscar endereços
    $stmt = $db->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC");
    $stmt->execute([$user['id']]);
    $enderecos = $stmt->fetchAll();

} catch (PDOException $e) {
    setAlert('Erro ao buscar dados do perfil.', 'error');
    redirect('/prestador/index.php');
}

$error = '';
$tab = $_GET['tab'] ?? 'dados';

// Processar atualização de dados pessoais
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dados_pessoais') {
    $nome = sanitize($_POST['nome']);
    $telefone = sanitize($_POST['telefone']);
    $email = sanitize($_POST['email']);

    if (empty($nome) || empty($email)) {
        $error = 'Nome e email são obrigatórios';
    } elseif (!isValidEmail($email)) {
        $error = 'Email inválido';
    } else {
        try {
            // Verificar se email já existe (exceto o próprio)
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $error = 'Este email já está em uso';
            } else {
                $stmt = $db->prepare("UPDATE usuarios SET nome = ?, email = ?, telefone = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $telefone, $user['id']]);

                // Atualizar sessão
                $_SESSION['user_nome'] = $nome;
                $_SESSION['user_email'] = $email;

                setAlert('Dados pessoais atualizados com sucesso!', 'success');
                redirect('/prestador/perfil.php?tab=dados');
            }
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar dados';
        }
    }
}

// Processar atualização de perfil profissional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'perfil_profissional') {
    $descricao_profissional = sanitize($_POST['descricao_profissional']);
    $especialidades = sanitize($_POST['especialidades']);
    $formacao = sanitize($_POST['formacao']);
    $registro_profissional = sanitize($_POST['registro_profissional']);
    $anos_experiencia = !empty($_POST['anos_experiencia']) ? (int)$_POST['anos_experiencia'] : null;
    $certificados = sanitize($_POST['certificados']);
    $disponibilidade_geral = sanitize($_POST['disponibilidade_geral']);
    $raio_atendimento = !empty($_POST['raio_atendimento']) ? (int)$_POST['raio_atendimento'] : null;

    try {
        $stmt = $db->prepare("
            UPDATE perfil_prestador 
            SET descricao_profissional = ?, 
                especialidades = ?, 
                formacao = ?, 
                registro_profissional = ?,
                anos_experiencia = ?,
                certificados = ?,
                disponibilidade_geral = ?,
                raio_atendimento = ?
            WHERE prestador_id = ?
        ");
        $stmt->execute([
            $descricao_profissional,
            $especialidades,
            $formacao,
            $registro_profissional,
            $anos_experiencia,
            $certificados,
            $disponibilidade_geral,
            $raio_atendimento,
            $user['id']
        ]);

        setAlert('Perfil profissional atualizado com sucesso!', 'success');
        redirect('/prestador/perfil.php?tab=profissional');

    } catch (PDOException $e) {
        $error = 'Erro ao atualizar perfil profissional';
    }
}

// Processar alteração de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'alterar_senha') {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
        $error = 'Preencha todos os campos de senha';
    } elseif (strlen($nova_senha) < 6) {
        $error = 'A nova senha deve ter no mínimo 6 caracteres';
    } elseif ($nova_senha !== $confirmar_senha) {
        $error = 'A nova senha e a confirmação não coincidem';
    } else {
        try {
            // Verificar senha atual
            if (!password_verify($senha_atual, $usuario['senha'])) {
                $error = 'Senha atual incorreta';
            } else {
                $nova_senha_hash = hashPassword($nova_senha);
                $stmt = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt->execute([$nova_senha_hash, $user['id']]);

                setAlert('Senha alterada com sucesso!', 'success');
                redirect('/prestador/perfil.php?tab=senha');
            }
        } catch (PDOException $e) {
            $error = 'Erro ao alterar senha';
        }
    }
}

// Processar exclusão de endereço
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_endereco') {
    $endereco_id = (int)$_POST['endereco_id'];

    try {
        $stmt = $db->prepare("DELETE FROM enderecos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$endereco_id, $user['id']]);

        setAlert('Endereço removido com sucesso!', 'success');
        redirect('/prestador/perfil.php?tab=enderecos');
    } catch (PDOException $e) {
        $error = 'Erro ao remover endereço';
    }
}

// Recarregar dados após alterações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user['id']]);
    $usuario = $stmt->fetch();

    $stmt = $db->prepare("SELECT * FROM perfil_prestador WHERE prestador_id = ?");
    $stmt->execute([$user['id']]);
    $perfil = $stmt->fetch();

    $stmt = $db->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC");
    $stmt->execute([$user['id']]);
    $enderecos = $stmt->fetchAll();
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Prestador</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        .tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            color: var(--text-light);
            font-size: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-bottom: -2px;
        }
        .tab:hover {
            color: var(--primary-color);
        }
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
<div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><?= SITE_NAME ?></h3>
            <p>Prestador de Serviços</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php">📊 Dashboard</a></li>
            <li><a href="perfil.php" class="active">👤 Meu Perfil</a></li>
            <li><a href="servicos.php">🏥 Meus Serviços</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="agenda.php">📅 Agenda</a></li>
            <li><a href="avaliacoes.php">⭐ Avaliações</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>Meu Perfil</h1>
            <p>Gerencie suas informações pessoais e profissionais</p>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Tabs de Navegação -->
        <div class="tabs">
            <a href="?tab=dados" class="tab <?= $tab === 'dados' ? 'active' : '' ?>">
                📋 Dados Pessoais
            </a>
            <a href="?tab=profissional" class="tab <?= $tab === 'profissional' ? 'active' : '' ?>">
                💼 Perfil Profissional
            </a>
            <a href="?tab=enderecos" class="tab <?= $tab === 'enderecos' ? 'active' : '' ?>">
                📍 Endereços
            </a>
            <a href="?tab=senha" class="tab <?= $tab === 'senha' ? 'active' : '' ?>">
                🔒 Segurança
            </a>
        </div>

        <!-- Tab: Dados Pessoais -->
        <div class="tab-content <?= $tab === 'dados' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h3>Dados Pessoais</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="dados_pessoais">

                    <div class="form-group">
                        <label for="nome">Nome Completo *</label>
                        <input type="text" id="nome" name="nome" required
                               value="<?= htmlspecialchars($usuario['nome']) ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($usuario['email']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone"
                                   maxlength="15" placeholder="(00) 00000-0000"
                                   value="<?= htmlspecialchars($usuario['telefone']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>CPF</label>
                            <input type="text" value="<?= formatCPF($usuario['cpf']) ?>" disabled>
                            <small>O CPF não pode ser alterado</small>
                        </div>

                        <div class="form-group">
                            <label>Data de Cadastro</label>
                            <input type="text" value="<?= formatDateTimeBR($usuario['data_cadastro']) ?>" disabled>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
                </form>
            </div>
        </div>

        <!-- Tab: Perfil Profissional -->
        <div class="tab-content <?= $tab === 'profissional' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h3>Perfil Profissional</h3>
                    <p style="font-weight: normal; color: var(--text-light); font-size: 0.875rem;">
                        Complete seu perfil para aumentar suas chances de contratação
                    </p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="perfil_profissional">

                    <div class="form-group">
                        <label for="descricao_profissional">📝 Descrição Profissional</label>
                        <textarea id="descricao_profissional" name="descricao_profissional" rows="5"
                                  placeholder="Apresente-se profissionalmente. Ex: 'Enfermeira com 10 anos de experiência em atendimento domiciliar...'"><?= htmlspecialchars($perfil['descricao_profissional'] ?? '') ?></textarea>
                        <small>Esta descrição será exibida no seu perfil público</small>
                    </div>

                    <div class="form-group">
                        <label for="especialidades">⭐ Especialidades</label>
                        <textarea id="especialidades" name="especialidades" rows="3"
                                  placeholder="Liste suas especialidades. Ex: 'Cuidados geriátricos, Curativos, Administração de medicamentos...'"><?= htmlspecialchars($perfil['especialidades'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="formacao">🎓 Formação</label>
                            <input type="text" id="formacao" name="formacao"
                                   placeholder="Ex: Técnico em Enfermagem - UNIFESP"
                                   value="<?= htmlspecialchars($perfil['formacao'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="registro_profissional">📋 Registro Profissional</label>
                            <input type="text" id="registro_profissional" name="registro_profissional"
                                   placeholder="Ex: COREN 123456-SP"
                                   value="<?= htmlspecialchars($perfil['registro_profissional'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="anos_experiencia">📅 Anos de Experiência</label>
                            <input type="number" id="anos_experiencia" name="anos_experiencia"
                                   min="0" max="50" placeholder="Ex: 10"
                                   value="<?= $perfil['anos_experiencia'] ?? '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="raio_atendimento">📍 Raio de Atendimento (km)</label>
                            <input type="number" id="raio_atendimento" name="raio_atendimento"
                                   min="0" max="100" placeholder="Ex: 20"
                                   value="<?= $perfil['raio_atendimento'] ?? '' ?>">
                            <small>Distância máxima que você atende</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="certificados">🏆 Certificados e Cursos</label>
                        <textarea id="certificados" name="certificados" rows="3"
                                  placeholder="Liste seus certificados e cursos complementares..."><?= htmlspecialchars($perfil['certificados'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="disponibilidade_geral">🕐 Disponibilidade de Horário</label>
                        <textarea id="disponibilidade_geral" name="disponibilidade_geral" rows="3"
                                  placeholder="Ex: 'Segunda a sexta: 8h às 18h | Sábados: 8h às 12h | Plantões noturnos sob consulta'"><?= htmlspecialchars($perfil['disponibilidade_geral'] ?? '') ?></textarea>
                        <small>Informe sua disponibilidade geral de horários</small>
                    </div>

                    <div style="background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                        <strong>💡 Dica:</strong> Quanto mais completo seu perfil, maior a chance de receber solicitações!
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Salvar Perfil Profissional</button>
                </form>
            </div>

            <!-- Estatísticas do Perfil -->
            <div class="card">
                <div class="card-header">
                    <h3>📊 Suas Estatísticas</h3>
                </div>
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="stat-card">
                        <div class="stat-value">⭐ <?= number_format($perfil['media_avaliacoes'], 1) ?></div>
                        <div class="stat-label">Média de Avaliações</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $perfil['total_avaliacoes'] ?></div>
                        <div class="stat-label">Total de Avaliações</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $perfil['total_atendimentos'] ?></div>
                        <div class="stat-label">Atendimentos Realizados</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Endereços -->
        <div class="tab-content <?= $tab === 'enderecos' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <h3>Meus Endereços</h3>
                    <a href="endereco_form.php" class="btn btn-primary">➕ Novo Endereço</a>
                </div>

                <?php if (empty($enderecos)): ?>
                    <p style="padding: 1.5rem; text-align: center; color: var(--text-light);">
                        Nenhum endereço cadastrado.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                            <tr>
                                <th>Endereço</th>
                                <th>Bairro</th>
                                <th>Cidade/UF</th>
                                <th>CEP</th>
                                <th>Principal</th>
                                <th>Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($enderecos as $endereco): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($endereco['rua']) ?>, <?= htmlspecialchars($endereco['numero']) ?>
                                        <?= $endereco['complemento'] ? ' - ' . htmlspecialchars($endereco['complemento']) : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($endereco['bairro']) ?></td>
                                    <td><?= htmlspecialchars($endereco['cidade']) ?>/<?= htmlspecialchars($endereco['estado']) ?></td>
                                    <td><?= formatCEP($endereco['cep']) ?></td>
                                    <td>
                                        <?php if ($endereco['principal']): ?>
                                            <span class="badge badge-success">Principal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="endereco_form.php?id=<?= $endereco['id'] ?>"
                                               class="btn btn-primary btn-sm">Editar</a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Tem certeza que deseja remover este endereço?')">
                                                <input type="hidden" name="action" value="delete_endereco">
                                                <input type="hidden" name="endereco_id" value="<?= $endereco['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Remover</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Segurança (Alterar Senha) -->
        <div class="tab-content <?= $tab === 'senha' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h3>🔒 Alterar Senha</h3>
                </div>
                <form method="POST" id="senhaForm">
                    <input type="hidden" name="action" value="alterar_senha">

                    <div class="form-group">
                        <label for="senha_atual">Senha Atual *</label>
                        <input type="password" id="senha_atual" name="senha_atual" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nova_senha">Nova Senha *</label>
                            <input type="password" id="nova_senha" name="nova_senha" required minlength="6">
                            <small>Mínimo de 6 caracteres</small>
                        </div>

                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Nova Senha *</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">🔒 Alterar Senha</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>💡 Dicas de Segurança</h3>
                </div>
                <ul style="padding-left: 2rem; color: var(--text-light);">
                    <li>Use uma senha forte com pelo menos 8 caracteres</li>
                    <li>Combine letras maiúsculas, minúsculas, números e símbolos</li>
                    <li>Não compartilhe sua senha com ninguém</li>
                    <li>Troque sua senha regularmente</li>
                    <li>Não use a mesma senha em diferentes sites</li>
                </ul>
            </div>
        </div>
    </main>
</div>

<script>
    // Máscara de telefone
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

    // Validação de senha
    document.getElementById('senhaForm')?.addEventListener('submit', function(e) {
        const novaSenha = document.getElementById('nova_senha').value;
        const confirmarSenha = document.getElementById('confirmar_senha').value;

        if (novaSenha !== confirmarSenha) {
            e.preventDefault();
            alert('A nova senha e a confirmação não coincidem!');
        }
    });
</script>
</body>
</html>