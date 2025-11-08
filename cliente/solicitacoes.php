<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user = getLoggedUser();

// Filtros
$status_filtro = isset($_GET['status']) ? sanitize($_GET['status']) : 'todos';

// Buscar solicitações
$where_status = $status_filtro !== 'todos' ? "AND s.status = ?" : "";
$params = [$user['id']];
if ($status_filtro !== 'todos') $params[] = $status_filtro;

try {
    $stmt = $db->prepare("
        SELECT s.*, 
               p.nome as prestador_nome, 
               p.telefone as prestador_telefone,
               srv.nome_servico,
               e.rua, e.numero, e.bairro, e.cidade,
               a.id as avaliacao_id
        FROM solicitacoes s
        INNER JOIN usuarios p ON s.prestador_id = p.id
        INNER JOIN servicos srv ON s.servico_id = srv.id
        INNER JOIN enderecos e ON s.endereco_id = e.id
        LEFT JOIN avaliacoes a ON s.id = a.solicitacao_id
        WHERE s.cliente_id = ?
        $where_status
        ORDER BY s.data_solicitacao DESC
    ");
    $stmt->execute($params);
    $solicitacoes = $stmt->fetchAll();

    // Contadores por status
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'aceita' THEN 1 ELSE 0 END) as aceitas,
            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas,
            SUM(CASE WHEN status = 'recusada' THEN 1 ELSE 0 END) as recusadas,
            SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) as canceladas
        FROM solicitacoes
        WHERE cliente_id = ?
    ");
    $stmt->execute([$user['id']]);
    $contadores = $stmt->fetch();

} catch (PDOException $e) {
    $error = "Erro ao buscar solicitações";
    $solicitacoes = [];
    $contadores = ['total' => 0, 'pendentes' => 0, 'aceitas' => 0, 'em_andamento' => 0, 'concluidas' => 0, 'recusadas' => 0, 'canceladas' => 0];
}

// Labels de status
$status_labels = [
    'pendente' => 'Aguardando Resposta',
    'aceita' => 'Aceita',
    'recusada' => 'Recusada',
    'em_andamento' => 'Em Andamento',
    'concluida' => 'Concluída',
    'cancelada' => 'Cancelada'
];

$status_classes = [
    'pendente' => 'warning',
    'aceita' => 'info',
    'recusada' => 'error',
    'em_andamento' => 'info',
    'concluida' => 'success',
    'cancelada' => 'secondary'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Solicitações - <?= SITE_NAME ?></title>
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
            <li><a href="solicitacoes.php" class="active">📋 Minhas Solicitações</a></li>
            <li><a href="enderecos.php">📍 Meus Endereços</a></li>
            <li><a href="perfil.php">👤 Meu Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>📋 Minhas Solicitações</h1>
            <p>Acompanhe todas as suas solicitações de serviço</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Contadores -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $contadores['total'] ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $contadores['pendentes'] ?></div>
                <div class="stat-label">Aguardando</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $contadores['em_andamento'] ?></div>
                <div class="stat-label">Em Andamento</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $contadores['concluidas'] ?></div>
                <div class="stat-label">Concluídas</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="card-body">
                <div class="filters-inline">
                    <a href="solicitacoes.php?status=todos" class="filter-btn <?= $status_filtro === 'todos' ? 'active' : '' ?>">
                        Todas (<?= $contadores['total'] ?>)
                    </a>
                    <a href="solicitacoes.php?status=pendente" class="filter-btn <?= $status_filtro === 'pendente' ? 'active' : '' ?>">
                        Aguardando (<?= $contadores['pendentes'] ?>)
                    </a>
                    <a href="solicitacoes.php?status=em_andamento" class="filter-btn <?= $status_filtro === 'em_andamento' ? 'active' : '' ?>">
                        Em Andamento (<?= $contadores['em_andamento'] ?>)
                    </a>
                    <a href="solicitacoes.php?status=concluida" class="filter-btn <?= $status_filtro === 'concluida' ? 'active' : '' ?>">
                        Concluídas (<?= $contadores['concluidas'] ?>)
                    </a>
                </div>
            </div>
        </div>

        <!-- Lista de Solicitações -->
        <?php if (empty($solicitacoes)): ?>
            <div class="card">
                <div class="card-body text-center">
                    <p class="text-muted">Nenhuma solicitação encontrada</p>
                    <a href="buscar_prestadores.php" class="btn btn-primary mt-3">🔍 Buscar Prestadores</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($solicitacoes as $sol): ?>
                <div class="card solicitacao-card">
                    <div class="card-body">
                        <div class="solicitacao-header">
                            <div class="solicitacao-info">
                                <h3><?= htmlspecialchars($sol['nome_servico']) ?></h3>
                                <p class="text-muted">
                                    Prestador: <strong><?= htmlspecialchars($sol['prestador_nome']) ?></strong>
                                </p>
                            </div>
                            <div class="solicitacao-status">
                                <span class="badge badge-<?= $status_classes[$sol['status']] ?>">
                                    <?= $status_labels[$sol['status']] ?>
                                </span>
                            </div>
                        </div>

                        <div class="solicitacao-detalhes">
                            <div class="detalhe-item">
                                <span class="detalhe-label">📅 Data:</span>
                                <span><?= date('d/m/Y', strtotime($sol['data_inicio'])) ?></span>
                            </div>
                            <div class="detalhe-item">
                                <span class="detalhe-label">⏰ Horário:</span>
                                <span><?= substr($sol['horario_inicio'], 0, 5) ?></span>
                            </div>
                            <div class="detalhe-item">
                                <span class="detalhe-label">📍 Local:</span>
                                <span><?= htmlspecialchars($sol['rua']) ?>, <?= htmlspecialchars($sol['numero']) ?> - <?= htmlspecialchars($sol['bairro']) ?></span>
                            </div>
                            <div class="detalhe-item">
                                <span class="detalhe-label">💰 Valor:</span>
                                <span>R$ <?= number_format($sol['valor_total'], 2, ',', '.') ?></span>
                            </div>
                        </div>

                        <div class="solicitacao-acoes">
                            <a href="agendamento_detalhes.php?id=<?= $sol['id'] ?>" class="btn btn-primary btn-sm">
                                Ver Detalhes
                            </a>
                            
                            <?php if ($sol['status'] === 'concluida' && !$sol['avaliacao_id']): ?>
                                <a href="avaliar_servico.php?id=<?= $sol['id'] ?>" class="btn btn-success btn-sm">
                                    ⭐ Avaliar Serviço
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($sol['status'] === 'concluida' && $sol['avaliacao_id']): ?>
                                <span class="badge badge-success">✓ Avaliado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
