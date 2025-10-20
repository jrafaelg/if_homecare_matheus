<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user = getLoggedUser();

// Buscar estatísticas do cliente
$stats = [
    'total_solicitacoes' => 0,
    'pendentes' => 0,
    'em_andamento' => 0,
    'concluidas' => 0,
    'avaliacoes_pendentes' => 0
];

try {
    // Total de solicitações
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE cliente_id = ?");
    $stmt->execute([$user['id']]);
    $stats['total_solicitacoes'] = $stmt->fetch()['total'];

    // Solicitações pendentes
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE cliente_id = ? AND status = 'pendente'");
    $stmt->execute([$user['id']]);
    $stats['pendentes'] = $stmt->fetch()['total'];

    // Em andamento
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE cliente_id = ? AND status IN ('aceita', 'em_andamento')");
    $stmt->execute([$user['id']]);
    $stats['em_andamento'] = $stmt->fetch()['total'];

    // Concluídas
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE cliente_id = ? AND status = 'concluida'");
    $stmt->execute([$user['id']]);
    $stats['concluidas'] = $stmt->fetch()['total'];

    // Avaliações pendentes
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM solicitacoes s
        LEFT JOIN avaliacoes a ON s.id = a.solicitacao_id
        WHERE s.cliente_id = ? AND s.status = 'concluida' AND a.id IS NULL
    ");
    $stmt->execute([$user['id']]);
    $stats['avaliacoes_pendentes'] = $stmt->fetch()['total'];

    // Últimas solicitações
    $stmt = $db->prepare("
        SELECT s.*, 
               p.nome as prestador_nome, 
               p.telefone as prestador_telefone,
               srv.nome_servico
        FROM solicitacoes s
        JOIN usuarios p ON s.prestador_id = p.id
        JOIN servicos srv ON s.servico_id = srv.id
        WHERE s.cliente_id = ?
        ORDER BY s.data_solicitacao DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $solicitacoes = $stmt->fetchAll();

    // Prestadores recentemente contratados
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.nome, p.telefone, srv.nome_servico,
               pp.media_avaliacoes, pp.total_avaliacoes
        FROM solicitacoes s
        JOIN usuarios p ON s.prestador_id = p.id
        JOIN servicos srv ON s.servico_id = srv.id
        LEFT JOIN perfil_prestador pp ON p.id = pp.prestador_id
        WHERE s.cliente_id = ?
        ORDER BY s.data_solicitacao DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $prestadores_recentes = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Erro ao buscar dados";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cliente</title>
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
            <li><a href="index.php" class="active">📊 Dashboard</a></li>
            <li><a href="buscar.php">🔍 Buscar Prestadores</a></li>
            <li><a href="solicitacoes.php">📋 Minhas Solicitações</a></li>
            <li><a href="enderecos.php">📍 Meus Endereços</a></li>
            <li><a href="perfil.php">👤 Meu Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Bem-vindo, <?= htmlspecialchars($user['nome']) ?>!</p>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['pendentes'] ?></div>
                <div class="stat-label">Aguardando Resposta</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['em_andamento'] ?></div>
                <div class="stat-label">Em Andamento</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['concluidas'] ?></div>
                <div class="stat-label">Concluídas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_solicitacoes'] ?></div>
                <div class="stat-label">Total de Solicitações</div>
            </div>
        </div>

        <?php if ($stats['avaliacoes_pendentes'] > 0): ?>
            <div class="alert alert-info">
                <strong>Atenção!</strong> Você tem <?= $stats['avaliacoes_pendentes'] ?> serviço(s) concluído(s) aguardando sua avaliação.
                <a href="solicitacoes.php">Avaliar agora</a>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-3 mb-3">
            <a href="buscar.php" class="btn btn-primary btn-lg">🔍 Buscar Prestadores</a>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>Minhas Solicitações</h3>
                <a href="solicitacoes.php" class="btn btn-primary btn-sm">Ver Todas</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Prestador</th>
                        <th>Serviço</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($solicitacoes)): ?>
                        <tr><td colspan="5" class="text-center">
                                Você ainda não fez nenhuma solicitação.<br>
                                <a href="buscar.php">Buscar prestadores agora</a>
                            </td></tr>
                    <?php else: ?>
                        <?php foreach ($solicitacoes as $solicitacao): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($solicitacao['prestador_nome']) ?><br>
                                    <small><?= formatPhone($solicitacao['prestador_telefone']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($solicitacao['nome_servico']) ?></td>
                                <td><?= formatDateBR($solicitacao['data_inicio']) ?></td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'pendente' => 'badge-warning',
                                        'aceita' => 'badge-info',
                                        'recusada' => 'badge-error',
                                        'em_andamento' => 'badge-info',
                                        'concluida' => 'badge-success',
                                        'cancelada' => 'badge-secondary'
                                    ];
                                    $status_label = [
                                        'pendente' => 'Pendente',
                                        'aceita' => 'Aceita',
                                        'recusada' => 'Recusada',
                                        'em_andamento' => 'Em Andamento',
                                        'concluida' => 'Concluída',
                                        'cancelada' => 'Cancelada'
                                    ];
                                    ?>
                                    <span class="badge <?= $status_class[$solicitacao['status']] ?>">
                                                <?= $status_label[$solicitacao['status']] ?>
                                            </span>
                                </td>
                                <td>
                                    <a href="solicitacoes.php?id=<?= $solicitacao['id'] ?>" class="btn btn-secondary btn-sm">
                                        Detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($prestadores_recentes)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Prestadores que você já contratou</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Último Serviço</th>
                            <th>Avaliação</th>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prestadores_recentes as $prestador): ?>
                            <tr>
                                <td><?= htmlspecialchars($prestador['nome']) ?></td>
                                <td><?= formatPhone($prestador['telefone']) ?></td>
                                <td><?= htmlspecialchars($prestador['nome_servico']) ?></td>
                                <td>
                                    <?php if ($prestador['total_avaliacoes'] > 0): ?>
                                        ⭐ <?= number_format($prestador['media_avaliacoes'], 1) ?>
                                        (<?= $prestador['total_avaliacoes'] ?> avaliações)
                                    <?php else: ?>
                                        <span class="text-light">Sem avaliações</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="prestador_detalhes.php?id=<?= $prestador['id'] ?>" class="btn btn-primary btn-sm">
                                        Ver Perfil
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>