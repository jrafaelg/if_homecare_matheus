<?php
require_once '../config/config.php';
checkUserType(USER_ADMIN);

$db = getDB();
$user = getLoggedUser();

$servico = null;
$isEdit = false;

// Se for edição, buscar dados do serviço
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $isEdit = true;

    try {
        $stmt = $db->prepare("SELECT * FROM servicos WHERE id = ?");
        $stmt->execute([$id]);
        $servico = $stmt->fetch();

        if (!$servico) {
            setAlert('Serviço não encontrado.', 'error');
            redirect('/admin/servicos.php');
        }
    } catch (PDOException $e) {
        setAlert('Erro ao buscar serviço.', 'error');
        redirect('/admin/servicos.php');
    }
}

$error = '';
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_servico = sanitize($_POST['nome_servico']);
    $descricao = sanitize($_POST['descricao']);
    $categoria = sanitize($_POST['categoria']);
    $status = $_POST['status'];

    // Validações
    if (empty($nome_servico)) {
        $error = 'O nome do serviço é obrigatório';
    } elseif (empty($categoria)) {
        $error = 'A categoria é obrigatória';
    } elseif (!in_array($status, ['ativo', 'inativo'])) {
        $error = 'Status inválido';
    } else {
        try {
            if ($isEdit) {
                // Atualizar serviço
                $stmt = $db->prepare("
                    UPDATE servicos 
                    SET nome_servico = ?, descricao = ?, categoria = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome_servico, $descricao, $categoria, $status, $_POST['id']]);

                setAlert('Serviço atualizado com sucesso!', 'success');
            } else {
                // Inserir novo serviço
                $stmt = $db->prepare("
                    INSERT INTO servicos (nome_servico, descricao, categoria, status)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$nome_servico, $descricao, $categoria, $status]);

                setAlert('Serviço cadastrado com sucesso!', 'success');
            }

            redirect('/admin/servicos.php');

        } catch (PDOException $e) {
            $error = 'Erro ao salvar serviço. Tente novamente.';
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
    <title><?= $isEdit ? 'Editar' : 'Novo' ?> Serviço - Administrador</title>
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
            <li><a href="servicos.php" class="active">🏥 Serviços</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="relatorios.php">📈 Relatórios</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><?= $isEdit ? 'Editar Serviço' : 'Novo Serviço' ?></h1>
            <p>Preencha os dados do serviço</p>
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
            <form method="POST">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= $servico['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="nome_servico">Nome do Serviço *</label>
                    <input type="text" id="nome_servico" name="nome_servico" required
                           value="<?= $servico ? htmlspecialchars($servico['nome_servico']) : '' ?>"
                           placeholder="Ex: Enfermagem Domiciliar">
                </div>

                <div class="form-group">
                    <label for="categoria">Categoria *</label>
                    <input type="text" id="categoria" name="categoria" required
                           value="<?= $servico ? htmlspecialchars($servico['categoria']) : '' ?>"
                           placeholder="Ex: Saúde, Cuidados, Terapia">
                    <small>Categoria do serviço para facilitar a organização</small>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="5"
                              placeholder="Descreva o serviço..."><?= $servico ? htmlspecialchars($servico['descricao']) : '' ?></textarea>
                    <small>Descrição detalhada do serviço que será exibida para os usuários</small>
                </div>

                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="ativo" <?= ($servico && $servico['status'] === 'ativo') ? 'selected' : '' ?>>
                            Ativo
                        </option>
                        <option value="inativo" <?= ($servico && $servico['status'] === 'inativo') ? 'selected' : '' ?>>
                            Inativo
                        </option>
                    </select>
                    <small>Apenas serviços ativos estarão disponíveis para os prestadores</small>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? '💾 Atualizar Serviço' : '➕ Cadastrar Serviço' ?>
                    </button>
                    <a href="servicos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>