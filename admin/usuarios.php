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

        // Não permitir excluir o próprio usuário
        if ($id === $user['id']) {
            setAlert('Você não pode excluir sua própria conta!', 'error');
            redirect('/admin/usuarios.php');
        }

        try {
            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            setAlert('Usuário excluído com sucesso!', 'success');
        } catch (PDOException $e) {
            setAlert('Erro ao excluir usuário.', 'error');
        }
        redirect('/admin/usuarios.php');
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];

        // Não permitir desativar o próprio usuário
        if ($id === $user['id']) {
            setAlert('Você não pode desativar sua própria conta!', 'error');
            redirect('/admin/usuarios.php');
        }

        $novo_status = $_POST['status'] === 'ativo' ? 'inativo' : 'ativo';
        try {
            $stmt = $db->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
            $stmt->execute([$novo_status, $id]);
            setAlert('Status atualizado com sucesso!', 'success');
        } catch (PDOException $e) {
            setAlert('Erro ao atualizar status.', 'error');
        }
        redirect('/admin/usuarios.php');
    }
}

// Buscar usuários com filtros
$search = $_GET['search'] ?? '';
$tipo_filter = $_GET['tipo'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM solicitacoes WHERE cliente_id = u.id) as total_como_cliente,
          (SELECT COUNT(*) FROM solicitacoes WHERE prestador_id = u.id) as total_como_prestador
          FROM usuarios u WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.nome LIKE ? OR u.email LIKE ? OR u.cpf LIKE ? OR u.telefone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($tipo_filter)) {
    $query .= " AND u.tipo_usuario = ?";
    $params[] = $tipo_filter;
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY u.data_cadastro DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $usuarios = [];
    $error = "Erro ao buscar usuários";
}

// Estatísticas rápidas
try {
    $stmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN tipo_usuario = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN tipo_usuario = 'prestador' THEN 1 ELSE 0 END) as prestadores,
        SUM(CASE WHEN tipo_usuario = 'cliente' THEN 1 ELSE 0 END) as clientes,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
        SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) as inativos
        FROM usuarios
    ");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'admins' => 0, 'prestadores' => 0, 'clientes' => 0, 'ativos' => 0, 'inativos' => 0];
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Administrador</title>
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
            <h1>Gerenciar Usuários</h1>
            <p>Gerencie todos os usuários do sistema</p>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas Rápidas -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total de Usuários</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['admins'] ?></div>
                <div class="stat-label">Administradores</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['prestadores'] ?></div>
                <div class="stat-label">Prestadores</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['clientes'] ?></div>
                <div class="stat-label">Clientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['ativos'] ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['inativos'] ?></div>
                <div class="stat-label">Inativos</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>Lista de Usuários</h3>
                <a href="usuario_form.php" class="btn btn-primary">➕ Novo Usuário</a>
            </div>

            <!-- Filtros -->
            <form method="GET" class="mb-3" style="padding: 0 1.5rem;">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="search" placeholder="Buscar por nome, email, CPF ou telefone..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group" style="max-width: 180px;">
                        <select name="tipo">
                            <option value="">Todos os tipos</option>
                            <option value="admin" <?= $tipo_filter === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            <option value="prestador" <?= $tipo_filter === 'prestador' ? 'selected' : '' ?>>Prestador</option>
                            <option value="cliente" <?= $tipo_filter === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                        </select>
                    </div>
                    <div class="form-group" style="max-width: 180px;">
                        <select name="status">
                            <option value="">Todos os status</option>
                            <option value="ativo" <?= $status_filter === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                            <option value="inativo" <?= $status_filter === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                        <a href="usuarios.php" class="btn btn-secondary">Limpar</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>Tipo</th>
                        <th>Atividade</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="10" class="text-center">Nenhum usuário encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?= $usuario['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
                                    <?php if ($usuario['id'] === $user['id']): ?>
                                        <span class="badge badge-info" style="font-size: 0.7rem;">Você</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($usuario['email']) ?></td>
                                <td><?= formatCPF($usuario['cpf']) ?></td>
                                <td><?= formatPhone($usuario['telefone']) ?></td>
                                <td>
                                    <?php
                                    $tipo_badges = [
                                        'admin' => ['class' => 'badge-error', 'label' => 'Admin'],
                                        'prestador' => ['class' => 'badge-info', 'label' => 'Prestador'],
                                        'cliente' => ['class' => 'badge-secondary', 'label' => 'Cliente']
                                    ];
                                    $badge = $tipo_badges[$usuario['tipo_usuario']];
                                    ?>
                                    <span class="badge <?= $badge['class'] ?>">
                                                <?= $badge['label'] ?>
                                            </span>
                                </td>
                                <td>
                                    <?php if ($usuario['tipo_usuario'] === 'cliente'): ?>
                                        <small><?= $usuario['total_como_cliente'] ?> solicitação(ões)</small>
                                    <?php elseif ($usuario['tipo_usuario'] === 'prestador'): ?>
                                        <small><?= $usuario['total_como_prestador'] ?> atendimento(s)</small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($usuario['id'] !== $user['id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $usuario['status'] ?>">
                                            <button type="submit" class="badge <?= $usuario['status'] === 'ativo' ? 'badge-success' : 'badge-error' ?>"
                                                    style="border: none; cursor: pointer;">
                                                <?= $usuario['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-success">Ativo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDateBR($usuario['data_cadastro']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <a href="usuario_detalhes.php?id=<?= $usuario['id'] ?>"
                                           class="btn btn-primary btn-sm" title="Ver detalhes">👁️</a>
                                        <a href="usuario_form.php?id=<?= $usuario['id'] ?>"
                                           class="btn btn-primary btn-sm" title="Editar">✏️</a>
                                        <?php if ($usuario['id'] !== $user['id']): ?>
                                            <button onclick="confirmarExclusao(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nome']) ?>')"
                                                    class="btn btn-secondary btn-sm" title="Excluir">🗑️</button>
                                        <?php endif; ?>
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
        <p>Tem certeza que deseja excluir o usuário <strong id="deleteUserName"></strong>?</p>
        <p style="color: #ef4444; font-size: 0.875rem;">⚠️ Esta ação não pode ser desfeita!</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Confirmar Exclusão</button>
            </div>
        </form>
    </div>
</div>

<script>
    function confirmarExclusao(id, nome) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteUserName').textContent = nome;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function fecharModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
</script>
</body>
</html>