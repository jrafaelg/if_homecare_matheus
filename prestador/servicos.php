<?php
require_once '../config/config.php';
checkUserType(USER_PRESTADOR);

$db = getDB();
$user = getLoggedUser();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $db->prepare("DELETE FROM prestador_servicos WHERE id = ? AND prestador_id = ?");
            $stmt->execute([$id, $user['id']]);
            setAlert('Serviço removido com sucesso!', 'success');
        } catch (PDOException $e) {
            setAlert('Erro ao remover serviço.', 'error');
        }
        redirect('/prestador/servicos.php');
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $novo_status = $_POST['status'] === 'ativo' ? 'inativo' : 'ativo';
        try {
            $stmt = $db->prepare("UPDATE prestador_servicos SET status = ? WHERE id = ? AND prestador_id = ?");
            $stmt->execute([$novo_status, $id, $user['id']]);
            setAlert('Status atualizado com sucesso!', 'success');
        } catch (PDOException $e) {
            setAlert('Erro ao atualizar status.', 'error');
        }
        redirect('/prestador/servicos.php');
    }
}

// Buscar serviços do prestador
try {
    $stmt = $db->prepare("
        SELECT ps.*, s.nome_servico, s.categoria, s.descricao
        FROM prestador_servicos ps
        JOIN servicos s ON ps.servico_id = s.id
        WHERE ps.prestador_id = ?
        ORDER BY s.nome_servico ASC
    ");
    $stmt->execute([$user['id']]);
    $meus_servicos = $stmt->fetchAll();

    // Buscar serviços disponíveis que o prestador ainda não oferece
    $stmt = $db->prepare("
        SELECT s.*
        FROM servicos s
        WHERE s.status = 'ativo'
        AND s.id NOT IN (
            SELECT servico_id 
            FROM prestador_servicos 
            WHERE prestador_id = ?
        )
        ORDER BY s.nome_servico ASC
    ");
    $stmt->execute([$user['id']]);
    $servicos_disponiveis = $stmt->fetchAll();

} catch (PDOException $e) {
    $meus_servicos = [];
    $servicos_disponiveis = [];
    $error = "Erro ao buscar serviços";
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Serviços - Prestador</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
            <li><a href="perfil.php">👤 Meu Perfil</a></li>
            <li><a href="servicos.php" class="active">🏥 Meus Serviços</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="agenda.php">📅 Agenda</a></li>
            <li><a href="avaliacoes.php">⭐ Avaliações</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>Meus Serviços</h1>
            <p>Gerencie os serviços que você oferece</p>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <?php if (empty($meus_servicos)): ?>
            <div class="alert alert-info">
                <strong>Bem-vindo!</strong> Você ainda não cadastrou nenhum serviço.
                Adicione os serviços que você oferece para começar a receber solicitações.
            </div>
        <?php endif; ?>

        <!-- Adicionar Novo Serviço -->
        <?php if (!empty($servicos_disponiveis)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>➕ Adicionar Novo Serviço</h3>
                </div>
                <p style="padding: 0 1.5rem; margin-bottom: 1rem;">
                    Selecione um serviço abaixo para começar a oferecê-lo:
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; padding: 0 1.5rem 1.5rem;">
                    <?php foreach ($servicos_disponiveis as $servico): ?>
                        <a href="servico_form.php?servico_id=<?= $servico['id'] ?>"
                           class="card"
                           style="padding: 1rem; text-decoration: none; border: 2px solid var(--border-color); transition: all 0.3s ease;">
                            <h4 style="margin: 0 0 0.5rem 0; color: var(--primary-color);">
                                <?= htmlspecialchars($servico['nome_servico']) ?>
                            </h4>
                            <p style="font-size: 0.875rem; color: var(--text-light); margin: 0;">
                                <?= htmlspecialchars($servico['categoria']) ?>
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Serviços Oferecidos -->
        <div class="card">
            <div class="card-header">
                <h3>Serviços que Você Oferece (<?= count($meus_servicos) ?>)</h3>
            </div>

            <?php if (empty($meus_servicos)): ?>
                <p style="padding: 1.5rem; text-align: center; color: var(--text-light);">
                    Nenhum serviço cadastrado ainda.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Categoria</th>
                            <th>Preço/Hora</th>
                            <th>Preço/Diária</th>
                            <th>Status</th>
                            <th>Cadastrado em</th>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($meus_servicos as $servico): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($servico['nome_servico']) ?></strong>
                                    <?php if ($servico['experiencia_especifica']): ?>
                                        <br><small style="color: var(--text-light);">
                                            <?= htmlspecialchars(substr($servico['experiencia_especifica'], 0, 50)) ?>...
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($servico['categoria']) ?></td>
                                <td>
                                    <?php if ($servico['preco_hora']): ?>
                                        <strong><?= formatMoney($servico['preco_hora']) ?></strong>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Não informado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($servico['preco_diaria']): ?>
                                        <strong><?= formatMoney($servico['preco_diaria']) ?></strong>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Não informado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $servico['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $servico['status'] ?>">
                                        <button type="submit"
                                                class="badge <?= $servico['status'] === 'ativo' ? 'badge-success' : 'badge-error' ?>"
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
                                        <button onclick="confirmarExclusao(<?= $servico['id'] ?>, '<?= htmlspecialchars($servico['nome_servico']) ?>')"
                                                class="btn btn-secondary btn-sm">Remover</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal de confirmação de exclusão -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 2rem; border-radius: 0.5rem; max-width: 400px;">
        <h3>Confirmar Remoção</h3>
        <p>Tem certeza que deseja remover o serviço <strong id="deleteServiceName"></strong>?</p>
        <p style="color: #f59e0b; font-size: 0.875rem;">⚠️ Você não receberá mais solicitações para este serviço.</p>
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
    function confirmarExclusao(id, nome) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteServiceName').textContent = nome;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function fecharModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    // Efeito hover nos cards de serviços disponíveis
    document.querySelectorAll('a.card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.borderColor = 'var(--primary-color)';
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = 'var(--shadow-md)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.borderColor = 'var(--border-color)';
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
</script>
</body>
</html>