<?php
require_once '../config/config.php';
checkUserType(USER_ADMIN);

$db = getDB();
$user = getLoggedUser();

$usuario = null;
$isEdit = false;

// Se for edição, buscar dados do usuário
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $isEdit = true;

    try {
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            setAlert('Usuário não encontrado.', 'error');
            redirect('/admin/usuarios.php');
        }
    } catch (PDOException $e) {
        setAlert('Erro ao buscar usuário.', 'error');
        redirect('/admin/usuarios.php');
    }
}

$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome']);
    $email = sanitize($_POST['email']);
    $telefone = sanitize($_POST['telefone']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
    $tipo_usuario = $_POST['tipo_usuario'];
    $status = $_POST['status'];
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    // Validações
    if (empty($nome) || empty($email) || empty($cpf) || empty($tipo_usuario)) {
        $error = 'Preencha todos os campos obrigatórios';
    } elseif (!isValidEmail($email)) {
        $error = 'Email inválido';
    } elseif (!isValidCPF($cpf)) {
        $error = 'CPF inválido';
    } elseif (!in_array($tipo_usuario, ['admin', 'prestador', 'cliente'])) {
        $error = 'Tipo de usuário inválido';
    } elseif (!in_array($status, ['ativo', 'inativo'])) {
        $error = 'Status inválido';
    } elseif (!$isEdit && empty($senha)) {
        $error = 'A senha é obrigatória para novos usuários';
    } elseif (!empty($senha) && strlen($senha) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres';
    } elseif (!empty($senha) && $senha !== $confirmar_senha) {
        $error = 'As senhas não coincidem';
    } else {
        try {
            if ($isEdit) {
                // Verificar se email já existe (exceto para o próprio usuário)
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_POST['id']]);
                if ($stmt->fetch()) {
                    $error = 'Este email já está cadastrado';
                } else {
                    // Verificar se CPF já existe (exceto para o próprio usuário)
                    $stmt = $db->prepare("SELECT id FROM usuarios WHERE cpf = ? AND id != ?");
                    $stmt->execute([$cpf, $_POST['id']]);
                    if ($stmt->fetch()) {
                        $error = 'Este CPF já está cadastrado';
                    } else {
                        // Atualizar usuário
                        if (!empty($senha)) {
                            $senha_hash = hashPassword($senha);
                            $stmt = $db->prepare("
                                UPDATE usuarios 
                                SET nome = ?, email = ?, telefone = ?, cpf = ?, tipo_usuario = ?, status = ?, senha = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$nome, $email, $telefone, $cpf, $tipo_usuario, $status, $senha_hash, $_POST['id']]);
                        } else {
                            $stmt = $db->prepare("
                                UPDATE usuarios 
                                SET nome = ?, email = ?, telefone = ?, cpf = ?, tipo_usuario = ?, status = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$nome, $email, $telefone, $cpf, $tipo_usuario, $status, $_POST['id']]);
                        }

                        setAlert('Usuário atualizado com sucesso!', 'success');
                        redirect('/admin/usuarios.php');
                    }
                }
            } else {
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
                        // Inserir novo usuário
                        $senha_hash = hashPassword($senha);

                        $stmt = $db->prepare("
                            INSERT INTO usuarios (nome, email, telefone, cpf, senha, tipo_usuario, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$nome, $email, $telefone, $cpf, $senha_hash, $tipo_usuario, $status]);
                        $user_id = $db->lastInsertId();

                        // Se for prestador, criar perfil
                        if ($tipo_usuario === 'prestador') {
                            $stmt = $db->prepare("INSERT INTO perfil_prestador (prestador_id) VALUES (?)");
                            $stmt->execute([$user_id]);
                        }

                        setAlert('Usuário cadastrado com sucesso!', 'success');
                        redirect('/admin/usuarios.php');
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Erro ao salvar usuário. Tente novamente.';
        }
    }
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Editar' : 'Novo' ?> Usuário - Administrador</title>
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
            <li><a href="usuarios.php" class="active">👥 Usuários</a></li>
            <li><a href="servicos.php">🏥 Serviços</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="relatorios.php">📈 Relatórios</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><?= $isEdit ? 'Editar Usuário' : 'Novo Usuário' ?></h1>
            <p>Preencha os dados do usuário</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" id="userForm">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                <?php endif; ?>

                <h3 class="mb-3">Dados Pessoais</h3>

                <div class="form-group">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" required
                           value="<?= $usuario ? htmlspecialchars($usuario['nome']) : '' ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required
                               value="<?= $usuario ? htmlspecialchars($usuario['email']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="text" id="telefone" name="telefone"
                               maxlength="15" placeholder="(00) 00000-0000"
                               value="<?= $usuario ? htmlspecialchars($usuario['telefone']) : '' ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cpf">CPF *</label>
                        <input type="text" id="cpf" name="cpf" required
                               maxlength="14" placeholder="000.000.000-00"
                               value="<?= $usuario ? formatCPF($usuario['cpf']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="tipo_usuario">Tipo de Usuário *</label>
                        <select id="tipo_usuario" name="tipo_usuario" required>
                            <option value="">Selecione...</option>
                            <option value="admin" <?= ($usuario && $usuario['tipo_usuario'] === 'admin') ? 'selected' : '' ?>>
                                Administrador
                            </option>
                            <option value="prestador" <?= ($usuario && $usuario['tipo_usuario'] === 'prestador') ? 'selected' : '' ?>>
                                Prestador de Serviços
                            </option>
                            <option value="cliente" <?= ($usuario && $usuario['tipo_usuario'] === 'cliente') ? 'selected' : '' ?>>
                                Cliente
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="ativo" <?= ($usuario && $usuario['status'] === 'ativo') ? 'selected' : '' ?>>
                                Ativo
                            </option>
                            <option value="inativo" <?= ($usuario && $usuario['status'] === 'inativo') ? 'selected' : '' ?>>
                                Inativo
                            </option>
                        </select>
                    </div>
                </div>

                <h3 class="mb-3 mt-4">Senha</h3>
                <?php if ($isEdit): ?>
                    <p class="mb-3" style="color: var(--text-light); font-size: 0.875rem;">
                        Deixe em branco se não quiser alterar a senha
                    </p>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="senha">
                            <?= $isEdit ? 'Nova Senha' : 'Senha *' ?>
                        </label>
                        <input type="password" id="senha" name="senha"
                               minlength="6" <?= $isEdit ? '' : 'required' ?>>
                        <small>Mínimo de 6 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha">
                            <?= $isEdit ? 'Confirmar Nova Senha' : 'Confirmar Senha *' ?>
                        </label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha"
                               minlength="6" <?= $isEdit ? '' : 'required' ?>>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? '💾 Atualizar Usuário' : '➕ Cadastrar Usuário' ?>
                    </button>
                    <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    // Máscaras
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

    // Validação de senha
    document.getElementById('userForm').addEventListener('submit', function(e) {
        const senha = document.getElementById('senha').value;
        const confirmar = document.getElementById('confirmar_senha').value;

        if (senha && senha !== confirmar) {
            e.preventDefault();
            alert('As senhas não coincidem!');
        }
    });
</script>
</body>
</html>