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

    // Últimas solicitações
    $stmt = $db->query("
        SELECT s.*, 
               c.nome as cliente_nome, 
               p.nome as prestador_nome,
               srv.nome_servico
        FROM solicitacoes s
        JOIN usuarios c ON s.cliente_id = c.id
        JOIN usuarios p ON s.prestador_id = p.id
        JOIN servicos srv ON s.servico_id = srv.id
        ORDER BY s.data_solicitacao DESC
        LIMIT 5
    ");
    $ultimas_solicitacoes = $stmt->fetchAll();

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
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="relatorios.php">📈 Relatórios</a></li>
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

        <div class="card">
            <div class="card-header">
                <h3>Últimas Solicitações</h3>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Prestador</th>
                        <th>Serviço</th>
                        <th>Data Início</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ultimas_solicitacoes)): ?>
                        <tr><td colspan="5" class="text-center">Nenhuma solicitação encontrada</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimas_solicitacoes as $solicitacao): ?>
                            <tr>
                                <td><?= htmlspecialchars($solicitacao['cliente_nome']) ?></td>
                                <td><?= htmlspecialchars($solicitacao['prestador_nome']) ?></td>
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