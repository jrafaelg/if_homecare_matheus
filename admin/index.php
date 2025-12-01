<?php
require_once '../config/config.php';
checkUserType(USER_ADMIN);

$db = getDB();

// Buscar estatísticas
$stats = [
    'total_usuarios' => 0,
    'total_prestadores' => 0,
    'total_clientes' => 0,
    'total_servicos' => 0,
    'total_solicitacoes' => 0,
    'solicitacoes_pendentes' => 0,
    'solicitacoes_concluidas' => 0
];

try {
    // Total de usuários
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
    $stats['total_usuarios'] = $stmt->fetch()['total'];

    // Total de prestadores
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'prestador'");
    $stats['total_prestadores'] = $stmt->fetch()['total'];

    // Total de clientes
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'cliente'");
    $stats['total_clientes'] = $stmt->fetch()['total'];

    // Total de serviços
    $stmt = $db->query("SELECT COUNT(*) as total FROM servicos WHERE status = 'ativo'");
    $stats['total_servicos'] = $stmt->fetch()['total'];

    // Total de solicitações
    $stmt = $db->query("SELECT COUNT(*) as total FROM solicitacoes");
    $stats['total_solicitacoes'] = $stmt->fetch()['total'];

    // Solicitações pendentes
    $stmt = $db->query("SELECT COUNT(*) as total FROM solicitacoes WHERE status = 'pendente'");
    $stats['solicitacoes_pendentes'] = $stmt->fetch()['total'];

    // Solicitações concluídas
    $stmt = $db->query("SELECT COUNT(*) as total FROM solicitacoes WHERE status = 'concluida'");
    $stats['solicitacoes_concluidas'] = $stmt->fetch()['total'];

    // Últimos usuários cadastrados
    $stmt = $db->query("SELECT * FROM usuarios ORDER BY data_cadastro DESC LIMIT 5");
    $ultimos_usuarios = $stmt->fetchAll();

    // Estatísticas de atividade (últimos 30 dias)
    $stmt = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM usuarios WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as novos_usuarios_mes,
            (SELECT COUNT(*) FROM solicitacoes WHERE status = 'concluida' AND data_conclusao >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as atendimentos_mes,
            (SELECT AVG(nota) FROM avaliacoes WHERE data_avaliacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as media_geral,
            (SELECT SUM(valor_total) FROM solicitacoes WHERE status = 'concluida' AND data_conclusao >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as volume_mes
    ");
    $stats_atividade = $stmt->fetch();
    // Mesclar com stats existentes
    $stats = array_merge($stats, $stats_atividade);



} catch (PDOException $e) {
    $error = "Erro ao buscar estatísticas";
}

$user = getLoggedUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Administrador</title>
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
            <li><a href="index.php" class="active">📊 Dashboard</a></li>
            <li><a href="usuarios.php">👥 Usuários</a></li>
            <li><a href="servicos.php">🏥 Serviços</a></li>
            <li><a href="relatorios.php">� Relaltórios</a></li>
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
                <div class="stat-value"><?= $stats['total_usuarios'] ?></div>
                <div class="stat-label">Total de Usuários</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_prestadores'] ?></div>
                <div class="stat-label">Prestadores</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_clientes'] ?></div>
                <div class="stat-label">Clientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_servicos'] ?></div>
                <div class="stat-label">Serviços Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_solicitacoes'] ?></div>
                <div class="stat-label">Total Solicitações</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['solicitacoes_pendentes'] ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Últimos Usuários Cadastrados</h3>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Data Cadastro</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ultimos_usuarios)): ?>
                        <tr><td colspan="5" class="text-center">Nenhum usuário encontrado</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimos_usuarios as $usuario): ?>
                            <tr>
                                <td><?= htmlspecialchars($usuario['nome']) ?></td>
                                <td><?= htmlspecialchars($usuario['email']) ?></td>
                                <td>
                                            <span class="badge badge-info">
                                                <?= ucfirst($usuario['tipo_usuario']) ?>
                                            </span>
                                </td>
                                <td><?= formatDateTimeBR($usuario['data_cadastro']) ?></td>
                                <td>
                                    <?php if ($usuario['status'] === 'ativo'): ?>
                                        <span class="badge badge-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Inativo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Atividade do Sistema -->
        <div class="card">
            <div class="card-header">
                <h3>📊 Atividade do Sistema (Últimos 30 dias)</h3>
            </div>
            <div class="activity-stats">
                <div class="activity-item">
                    <div class="activity-icon">👥</div>
                    <div class="activity-info">
                        <strong>Novos Usuários</strong>
                        <span><?= $stats['novos_usuarios_mes'] ?? 0 ?></span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">🏥</div>
                    <div class="activity-info">
                        <strong>Atendimentos Concluídos</strong>
                        <span><?= $stats['atendimentos_mes'] ?? 0 ?></span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">⭐</div>
                    <div class="activity-info">
                        <strong>Média de Avaliações</strong>
                        <span><?= isset($stats['media_geral']) ? number_format($stats['media_geral'], 1) . ' ⭐' : 'N/A' ?></span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">💰</div>
                    <div class="activity-info">
                        <strong>Volume Financeiro</strong>
                        <span><?= isset($stats['volume_mes']) ? 'R$ ' . number_format($stats['volume_mes'], 2, ',', '.') : 'R$ 0,00' ?></span>
                    </div>
                </div>
            </div>
        </div>


    </main>
</div>
</body>
</html>