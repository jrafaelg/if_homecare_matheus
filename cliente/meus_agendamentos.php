<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user = getLoggedUser();

// Filtros
$status_filtro = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action']);
    $solicitacao_id = (int)$_POST['solicitacao_id'];

    if ($action === 'cancelar') {
        try {
            // Verificar se a solicitação pertence ao cliente e pode ser cancelada
            $stmt = $db->prepare("
                SELECT status FROM solicitacoes 
                WHERE id = ? AND cliente_id = ? AND status IN ('pendente', 'aceita')
            ");
            $stmt->execute([$solicitacao_id, $user['id']]);
            $solicitacao = $stmt->fetch();

            if ($solicitacao) {
                // Atualizar status
                $stmt = $db->prepare("
                    UPDATE solicitacoes 
                    SET status = 'cancelada', data_atualizacao = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$solicitacao_id]);

                // Inserir histórico
                $stmt = $db->prepare("
                    INSERT INTO historico_solicitacoes (
                        solicitacao_id, status_anterior, status_novo, 
                        observacao, usuario_id, data_alteracao
                    ) VALUES (?, ?, 'cancelada', 'Cancelado pelo cliente', ?, NOW())
                ");
                $stmt->execute([$solicitacao_id, $solicitacao['status'], $user['id']]);

                setAlert('Solicitação cancelada com sucesso', 'success');
            } else {
                setAlert('Não foi possível cancelar esta solicitação', 'error');
            }
        } catch (PDOException $e) {
            setAlert('Erro ao cancelar solicitação', 'error');
        }
    }

    redirect('/cliente/meus_agendamentos.php' . ($status_filtro ? '?status=' . $status_filtro : ''));
}

// Buscar solicitações
$where_conditions = ['s.cliente_id = ?'];
$params = [$user['id']];

if (!empty($status_filtro)) {
    $where_conditions[] = 's.status = ?';
    $params[] = $status_filtro;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Contar total
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM solicitacoes s
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);

    // Buscar solicitações
    $stmt = $db->prepare("
        SELECT s.*, 
               p.nome as prestador_nome, p.telefone as prestador_telefone,
               srv.nome_servico,
               e.rua, e.numero, e.bairro, e.cidade, e.estado,
               av.nota as avaliacao_nota, av.id as avaliacao_id
        FROM solicitacoes s
        INNER JOIN usuarios p ON s.prestador_id = p.id
        INNER JOIN servicos srv ON s.servico_id = srv.id
        INNER JOIN enderecos e ON s.endereco_id = e.id
        LEFT JOIN avaliacoes av ON s.id = av.solicitacao_id
        WHERE $where_clause
        ORDER BY s.data_solicitacao DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $solicitacoes = $stmt->fetchAll();

    // Estatísticas
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'aceita' THEN 1 ELSE 0 END) as aceitas,
            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas,
            SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
            SUM(CASE WHEN status = 'recusada' THEN 1 ELSE 0 END) as recusadas
        FROM solicitacoes s
        WHERE s.cliente_id = ?
    ");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    $error = "Erro ao buscar solicitações";
    $solicitacoes = [];
    $stats = [];
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Agendamentos - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><?= SITE_NAME ?></h3>
            <p>Cliente</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php">📊 Dashboard</a></li>
            <li><a href="buscar_prestadores.php">🔍 Buscar Prestadores</a></li>
            <li><a href="meus_agendamentos.php" class="active">📋 Meus Agendamentos</a></li>
            <li><a href="enderecos.php">📍 Meus Endereços</a></li>
            <li><a href="perfil.php">👤 Meu Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>Meus Agendamentos</h1>
            <p>Gerencie suas solicitações de serviço</p>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['pendentes'] ?? 0 ?></div>
                <div class="stat-label">Aguardando Resposta</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= ($stats['aceitas'] ?? 0) + ($stats['em_andamento'] ?? 0) ?></div>
                <div class="stat-label">Em Andamento</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['concluidas'] ?? 0 ?></div>
                <div class="stat-label">Concluídas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="card-header">
                <h3>Filtrar Agendamentos</h3>
            </div>
            <div class="filters">
                <a href="meus_agendamentos.php" 
                   class="filter-btn <?= empty($status_filtro) ? 'active' : '' ?>">
                    Todos (<?= $stats['total'] ?? 0 ?>)
                </a>
                <a href="?status=pendente" 
                   class="filter-btn <?= $status_filtro === 'pendente' ? 'active' : '' ?>">
                    Pendentes (<?= $stats['pendentes'] ?? 0 ?>)
                </a>
                <a href="?status=aceita" 
                   class="filter-btn <?= $status_filtro === 'aceita' ? 'active' : '' ?>">
                    Aceitas (<?= $stats['aceitas'] ?? 0 ?>)
                </a>
                <a href="?status=em_andamento" 
                   class="filter-btn <?= $status_filtro === 'em_andamento' ? 'active' : '' ?>">
                    Em Andamento (<?= $stats['em_andamento'] ?? 0 ?>)
                </a>
                <a href="?status=concluida" 
                   class="filter-btn <?= $status_filtro === 'concluida' ? 'active' : '' ?>">
                    Concluídas (<?= $stats['concluidas'] ?? 0 ?>)
                </a>
            </div>
        </div>

        <!-- Lista de Agendamentos -->
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>Suas Solicitações</h3>
                <a href="buscar_prestadores.php" class="btn btn-primary">+ Nova Solicitação</a>
            </div>

            <?php if (empty($solicitacoes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3>Nenhuma solicitação encontrada</h3>
                    <p>Você ainda não fez nenhuma solicitação de serviço.</p>
                    <a href="buscar_prestadores.php" class="btn btn-primary">Buscar Prestadores</a>
                </div>
            <?php else: ?>
                <div class="agendamentos-list">
                    <?php foreach ($solicitacoes as $solicitacao): ?>
                        <div class="agendamento-card">
                            <div class="agendamento-header">
                                <div class="agendamento-info">
                                    <h4><?= htmlspecialchars($solicitacao['nome_servico']) ?></h4>
                                    <p class="prestador">
                                        <strong><?= htmlspecialchars($solicitacao['prestador_nome']) ?></strong>
                                        - <?= formatPhone($solicitacao['prestador_telefone']) ?>
                                    </p>
                                </div>
                                <div class="agendamento-status">
                                    <?php
                                    $status_classes = [
                                        'pendente' => 'badge-warning',
                                        'aceita' => 'badge-info',
                                        'recusada' => 'badge-error',
                                        'em_andamento' => 'badge-info',
                                        'concluida' => 'badge-success',
                                        'cancelada' => 'badge-secondary'
                                    ];
                                    $status_labels = [
                                        'pendente' => 'Aguardando Resposta',
                                        'aceita' => 'Aceita',
                                        'recusada' => 'Recusada',
                                        'em_andamento' => 'Em Andamento',
                                        'concluida' => 'Concluída',
                                        'cancelada' => 'Cancelada'
                                    ];
                                    ?>
                                    <span class="badge <?= $status_classes[$solicitacao['status']] ?>">
                                        <?= $status_labels[$solicitacao['status']] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="agendamento-details">
                                <div class="detail-item">
                                    <strong>📅 Data:</strong>
                                    <?= formatDateBR($solicitacao['data_inicio']) ?>
                                    <?php if ($solicitacao['data_fim']): ?>
                                        até <?= formatDateBR($solicitacao['data_fim']) ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($solicitacao['horario_inicio']): ?>
                                    <div class="detail-item">
                                        <strong>🕒 Horário:</strong>
                                        <?= date('H:i', strtotime($solicitacao['horario_inicio'])) ?>
                                        <?php if ($solicitacao['horario_fim']): ?>
                                            às <?= date('H:i', strtotime($solicitacao['horario_fim'])) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="detail-item">
                                    <strong>📍 Local:</strong>
                                    <?= htmlspecialchars($solicitacao['rua'] . ', ' . $solicitacao['numero'] . ' - ' . $solicitacao['bairro'] . ', ' . $solicitacao['cidade']) ?>
                                </div>

                                <?php if ($solicitacao['valor_total']): ?>
                                    <div class="detail-item">
                                        <strong>💰 Valor:</strong>
                                        <?= formatMoney($solicitacao['valor_total']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($solicitacao['observacoes_cliente']): ?>
                                    <div class="detail-item">
                                        <strong>📝 Observações:</strong>
                                        <?= nl2br(htmlspecialchars($solicitacao['observacoes_cliente'])) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($solicitacao['observacoes_prestador']): ?>
                                    <div class="detail-item">
                                        <strong>💬 Resposta do Prestador:</strong>
                                        <?= nl2br(htmlspecialchars($solicitacao['observacoes_prestador'])) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($solicitacao['motivo_recusa']): ?>
                                    <div class="detail-item">
                                        <strong>❌ Motivo da Recusa:</strong>
                                        <?= nl2br(htmlspecialchars($solicitacao['motivo_recusa'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="agendamento-actions">
                                <?php if ($solicitacao['status'] === 'pendente' || $solicitacao['status'] === 'aceita'): ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Tem certeza que deseja cancelar esta solicitação?')">
                                        <input type="hidden" name="action" value="cancelar">
                                        <input type="hidden" name="solicitacao_id" value="<?= $solicitacao['id'] ?>">
                                        <button type="submit" class="btn btn-error btn-sm">Cancelar</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($solicitacao['status'] === 'concluida' && !$solicitacao['avaliacao_id']): ?>
                                    <a href="avaliar_servico.php?id=<?= $solicitacao['id'] ?>" 
                                       class="btn btn-warning btn-sm">⭐ Avaliar</a>
                                <?php endif; ?>

                                <?php if ($solicitacao['avaliacao_id']): ?>
                                    <span class="badge badge-success">✅ Avaliado</span>
                                <?php endif; ?>

                                <a href="agendamento_detalhes.php?id=<?= $solicitacao['id'] ?>" 
                                   class="btn btn-secondary btn-sm">Ver Detalhes</a>
                            </div>

                            <div class="agendamento-meta">
                                <small>
                                    Solicitado em <?= formatDateTimeBR($solicitacao['data_solicitacao']) ?>
                                    <?php if ($solicitacao['data_resposta']): ?>
                                        • Respondido em <?= formatDateTimeBR($solicitacao['data_resposta']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $status_filtro ? '&status=' . $status_filtro : '' ?>" 
                               class="btn btn-secondary">← Anterior</a>
                        <?php endif; ?>

                        <span class="pagination-info">
                            Página <?= $page ?> de <?= $total_pages ?> 
                            (<?= $total_records ?> registros)
                        </span>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $status_filtro ? '&status=' . $status_filtro : '' ?>" 
                               class="btn btn-secondary">Próxima →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>