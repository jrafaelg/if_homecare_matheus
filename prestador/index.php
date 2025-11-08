<?php
require_once '../config/config.php';
checkUserType(USER_PRESTADOR);

$db = getDB();
$user = getLoggedUser();

// Buscar estatísticas do prestador
$stats = [
    'total_solicitacoes' => 0,
    'pendentes' => 0,
    'em_andamento' => 0,
    'concluidas' => 0,
    'servicos_oferecidos' => 0,
    'media_avaliacoes' => 0
];

try {
    // Total de solicitações
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE prestador_id = ?");
    $stmt->execute([$user['id']]);
    $stats['total_solicitacoes'] = $stmt->fetch()['total'];

    // Solicitações pendentes
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE prestador_id = ? AND status = 'pendente'");
    $stmt->execute([$user['id']]);
    $stats['pendentes'] = $stmt->fetch()['total'];

    // Em andamento
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE prestador_id = ? AND status = 'em_andamento'");
    $stmt->execute([$user['id']]);
    $stats['em_andamento'] = $stmt->fetch()['total'];

    // Concluídas
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE prestador_id = ? AND status = 'concluida'");
    $stmt->execute([$user['id']]);
    $stats['concluidas'] = $stmt->fetch()['total'];

    // Serviços oferecidos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM prestador_servicos WHERE prestador_id = ? AND status = 'ativo'");
    $stmt->execute([$user['id']]);
    $stats['servicos_oferecidos'] = $stmt->fetch()['total'];

    // Média de avaliações
    $stmt = $db->prepare("SELECT media_avaliacoes FROM perfil_prestador WHERE prestador_id = ?");
    $stmt->execute([$user['id']]);
    $perfil = $stmt->fetch();
    $stats['media_avaliacoes'] = $perfil ? number_format($perfil['media_avaliacoes'], 1) : '0.0';

    // Últimas solicitações
    $stmt = $db->prepare("
        SELECT s.*, 
               c.nome as cliente_nome, 
               c.telefone as cliente_telefone,
               srv.nome_servico,
               e.cidade, e.bairro
        FROM solicitacoes s
        JOIN usuarios c ON s.cliente_id = c.id
        JOIN servicos srv ON s.servico_id = srv.id
        LEFT JOIN enderecos e ON s.endereco_id = e.id
        WHERE s.prestador_id = ?
        ORDER BY s.data_solicitacao DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $solicitacoes = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Erro ao buscar dados";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Prestador</title>
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
            <li><a href="index.php" class="active">📊 Dashboard</a></li>
            <li><a href="perfil.php">👤 Meu Perfil</a></li>
            <li><a href="servicos.php">🏥 Meus Serviços</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="avaliacoes.php">⭐ Avaliações</a></li>
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
                <div class="stat-label">Solicitações Pendentes</div>
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
                <div class="stat-value"><?= $stats['servicos_oferecidos'] ?></div>
                <div class="stat-label">Serviços Oferecidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['media_avaliacoes'] ?> ⭐</div>
                <div class="stat-label">Média de Avaliações</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_solicitacoes'] ?></div>
                <div class="stat-label">Total de Atendimentos</div>
            </div>
        </div>

        <?php if ($stats['pendentes'] > 0): ?>
            <div class="alert alert-warning">
                <strong>Atenção!</strong> Você tem <?= $stats['pendentes'] ?> solicitação(ões) aguardando sua resposta.
                <a href="solicitacoes.php">Ver agora</a>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>Últimas Solicitações</h3>
                <a href="solicitacoes.php" class="btn btn-primary btn-sm">Ver Todas</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Serviço</th>
                        <th>Data</th>
                        <th>Local</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($solicitacoes)): ?>
                        <tr><td colspan="6" class="text-center">Nenhuma solicitação encontrada</td></tr>
                    <?php else: ?>
                        <?php foreach ($solicitacoes as $solicitacao): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($solicitacao['cliente_nome']) ?><br>
                                    <small><?= formatPhone($solicitacao['cliente_telefone']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($solicitacao['nome_servico']) ?></td>
                                <td><?= formatDateBR($solicitacao['data_inicio']) ?></td>
                                <td><?= htmlspecialchars($solicitacao['cidade'] . ' - ' . $solicitacao['bairro']) ?></td>
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
                                    <?php if ($solicitacao['status'] === 'pendente'): ?>
                                        <a href="solicitacoes.php?id=<?= $solicitacao['id'] ?>" class="btn btn-primary btn-sm">
                                            Responder
                                        </a>
                                    <?php else: ?>
                                        <a href="solicitacoes.php?id=<?= $solicitacao['id'] ?>" class="btn btn-secondary btn-sm">
                                            Detalhes
                                        </a>
                                    <?php endif; ?>
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
</body>
</html>