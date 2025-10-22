<?php
require_once '../config/config.php';
checkUserType(USER_ADMIN);

$db = getDB();
$user = getLoggedUser();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $db->prepare("DELETE FROM servicos WHERE id = ?");
            $stmt->execute([$id]);
            setAlert('Serviço excluído com sucesso!', 'success');
        } catch (PDOException $e) {
            setAlert('Erro ao excluir serviço. Verifique se não há prestadores vinculados.', 'error');
        }
        redirect('/admin/servicos.php');
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $novo_status = $_POST['status'] === 'ativo' ? 'inativo' : 'ativo';
        try {
            $stmt = $db->prepare("UPDATE servicos SET status = ? WHERE id = ?");
            $stmt->execute([$novo_status, $id]);
            setAlert('Status atualizado com sucesso!', 'success');
        } catch (PDOException $e) {
            setAlert('Erro ao atualizar status.', 'error');
        }
        redirect('/admin/servicos.php');
    }
}

// Buscar todos os serviços
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM servicos WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nome_servico LIKE ? OR descricao LIKE ? OR categoria LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY nome_servico ASC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $servicos = $stmt->fetchAll();
} catch (PDOException $e) {
    $servicos = [];
    $error = "Erro ao buscar serviços";
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Serviços - Administrador</title>
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
            <h1>Gerenciar Serviços</h1>
            <p>Cadastre e gerencie os serviços disponíveis no sistema</p>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>Serviços Cadastrados</h3>
                <a href="servico_form.php" class="btn btn-primary">➕ Novo Serviço</a>
            </div>

            <!-- Filtros -->
            <form method="GET" class="mb-3" style="padding: 0 1.5rem;">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="search" placeholder="Buscar por nome, descrição ou categoria..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group" style="max-width: 200px;">
                        <select name="status">
                            <option value="">Todos os status</option>
                            <option value="ativo" <?= $status_filter === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                            <option value="inativo" <?= $status_filter === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                        <a href="servicos.php" class="btn btn-secondary">Limpar</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome do Serviço</th>
                        <th>Categoria</th>
                        <th>Descrição</th>
                        <th>Status</th>
                        <th>Data Cadastro</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($servicos)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                Nenhum serviço encontrado.
                                <a href="servico_form.php">Cadastrar primeiro serviço</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servicos as $servico): ?>
                            <tr>
                                <td><?= $servico['id'] ?></td>
                                <td><strong><?= htmlspecialchars($servico['nome_servico']) ?></strong></td>
                                <td><?= htmlspecialchars($servico['categoria'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(substr($servico['descricao'] ?? '', 0, 80)) ?>...</td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $servico['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $servico['status'] ?>">
                                        <button type="submit" class="badge <?= $servico['status'] === 'ativo' ? 'badge-success' : 'badge-error' ?>"
                                                style="border: none; cursor: pointer;">
                                            <?= $servico['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                                        </button>
                                    </form>
                                </td>
                                <td><?= formatDateBR($servico['data_cadastro']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="servico_form.php?id=<?= $servico['id'] ?>"
                                           class="btn btn-primary btn-sm">Editar</a>
                                        <button onclick="confirmarExclusao(<?= $servico['id'] ?>)"
                                                class="btn btn-secondary btn-sm">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal de confirmação de exclusão -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 2rem; border-radius: 0.5rem; max-width: 400px;">
        <h3>Confirmar Exclusão</h3>
        <p>Tem certeza que deseja excluir este serviço?</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function confirmarExclusao(id) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function fecharModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
</script>
</body>
</html>