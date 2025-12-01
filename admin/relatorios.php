<?php
require_once '../config/config.php';
checkUserType(USER_ADMIN);
$db = getDB();
$user = getLoggedUser();

// Filtros
$periodo = isset($_GET['periodo']) ? sanitize($_GET['periodo']) : '30';
$tipo_relatorio = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : 'geral';

// Definir período
$data_inicio = date('Y-m-d', strtotime("-{$periodo} days"));
$data_fim = date('Y-m-d');

try {
    // Relatório Geral
    if ($tipo_relatorio === 'geral') {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT u.id) as total_usuarios,
                COUNT(DISTINCT CASE WHEN u.tipo_usuario = 'cliente' THEN u.id END) as total_clientes,
                COUNT(DISTINCT CASE WHEN u.tipo_usuario = 'prestador' THEN u.id END) as total_prestadores,
                COUNT(DISTINCT CASE WHEN u.data_cadastro >= ? THEN u.id END) as novos_usuarios,
                COUNT(DISTINCT s.id) as total_solicitacoes,
                COUNT(DISTINCT CASE WHEN s.status = 'concluida' THEN s.id END) as solicitacoes_concluidas,
                COUNT(DISTINCT CASE WHEN s.data_solicitacao >= ? THEN s.id END) as solicitacoes_periodo,
                COALESCE(SUM(CASE WHEN s.status = 'concluida' AND s.data_conclusao >= ? THEN s.valor_total END), 0) as volume_financeiro,
                COUNT(DISTINCT a.id) as total_avaliacoes,
                COALESCE(AVG(a.nota), 0) as media_avaliacoes
            FROM usuarios u
            LEFT JOIN solicitacoes s ON (u.id = s.cliente_id OR u.id = s.prestador_id)
            LEFT JOIN avaliacoes a ON s.id = a.solicitacao_id AND a.data_avaliacao >= ?
        ");
        $stmt->execute([$data_inicio, $data_inicio, $data_inicio, $data_inicio]);
        $relatorio_geral = $stmt->fetch();

        // Top Prestadores
        $stmt = $db->prepare("
            SELECT u.nome, u.email, 
                   COUNT(s.id) as total_atendimentos,
                   COALESCE(AVG(a.nota), 0) as media_avaliacao,
                   COALESCE(SUM(s.valor_total), 0) as volume_total
            FROM usuarios u
            INNER JOIN solicitacoes s ON u.id = s.prestador_id
            LEFT JOIN avaliacoes a ON s.id = a.solicitacao_id
            WHERE u.tipo_usuario = 'prestador' 
            AND s.status = 'concluida'
            AND s.data_conclusao >= ?
            GROUP BY u.id, u.nome, u.email
            ORDER BY total_atendimentos DESC, media_avaliacao DESC
            LIMIT 10
        ");
        $stmt->execute([$data_inicio]);
        $top_prestadores = $stmt->fetchAll();

        // Top Clientes
        $stmt = $db->prepare("
            SELECT u.nome, u.email,
                   COUNT(s.id) as total_solicitacoes,
                   COALESCE(SUM(s.valor_total), 0) as volume_total
            FROM usuarios u
            INNER JOIN solicitacoes s ON u.id = s.cliente_id
            WHERE u.tipo_usuario = 'cliente'
            AND s.data_solicitacao >= ?
            GROUP BY u.id, u.nome, u.email
            ORDER BY total_solicitacoes DESC, volume_total DESC
            LIMIT 10
        ");
        $stmt->execute([$data_inicio]);
        $top_clientes = $stmt->fetchAll();
    }
    
    // Relatório Financeiro
    if ($tipo_relatorio === 'financeiro') {
        $stmt = $db->prepare("
            SELECT 
                DATE(s.data_conclusao) as data,
                COUNT(s.id) as atendimentos,
                SUM(s.valor_total) as volume_dia
            FROM solicitacoes s
            WHERE s.status = 'concluida'
            AND s.data_conclusao >= ?
            AND s.data_conclusao <= ?
            GROUP BY DATE(s.data_conclusao)
            ORDER BY data DESC
        ");
        $stmt->execute([$data_inicio, $data_fim]);
        $relatorio_financeiro = $stmt->fetchAll();

        // Resumo por serviço
        $stmt = $db->prepare("
            SELECT srv.nome_servico,
                   COUNT(s.id) as total_atendimentos,
                   SUM(s.valor_total) as volume_total,
                   AVG(s.valor_total) as ticket_medio
            FROM solicitacoes s
            INNER JOIN servicos srv ON s.servico_id = srv.id
            WHERE s.status = 'concluida'
            AND s.data_conclusao >= ?
            GROUP BY srv.id, srv.nome_servico
            ORDER BY volume_total DESC
        ");
        $stmt->execute([$data_inicio]);
        $servicos_financeiro = $stmt->fetchAll();
    }
    // Relatório de Avaliações
    if ($tipo_relatorio === 'avaliacoes') {
        $stmt = $db->prepare("
            SELECT 
                a.nota,
                COUNT(*) as quantidade,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM avaliacoes WHERE data_avaliacao >= ?)), 2) as percentual
            FROM avaliacoes a
            WHERE a.data_avaliacao >= ?
            GROUP BY a.nota
            ORDER BY a.nota DESC
        ");
        $stmt->execute([$data_inicio, $data_inicio]);
        $distribuicao_notas = $stmt->fetchAll();

        // Avaliações recentes
        $stmt = $db->prepare("
            SELECT a.*, u_cliente.nome as cliente_nome, u_prestador.nome as prestador_nome, srv.nome_servico
            FROM avaliacoes a
            INNER JOIN usuarios u_cliente ON a.cliente_id = u_cliente.id
            INNER JOIN usuarios u_prestador ON a.prestador_id = u_prestador.id
            INNER JOIN solicitacoes s ON a.solicitacao_id = s.id
            INNER JOIN servicos srv ON s.servico_id = srv.id
            WHERE a.data_avaliacao >= ?
            ORDER BY a.data_avaliacao DESC
            LIMIT 20
        ");
        $stmt->execute([$data_inicio]);
        $avaliacoes_recentes = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $error = "Erro ao gerar relatórios: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - <?= SITE_NAME ?></title>
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
            <li><a href="servicos.php">🏥 Serviços</a></li>
            <li><a href="relatorios.php" class="active">📈 Relatórios</a></li>
            <li><a href="perfil.php">👤 Meu Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>📈 Relatórios do Sistema</h1>
            <p>Análise detalhada das atividades e performance</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card">
            <div class="card-header">
                <h3>Filtros de Relatório</h3>
            </div>
            <form method="GET" class="filters-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo">Tipo de Relatório</label>
                        <select id="tipo" name="tipo" onchange="this.form.submit()">
                            <option value="geral" <?= $tipo_relatorio === 'geral' ? 'selected' : '' ?>>
                                📊 Relatório Geral
                            </option>
                            <option value="financeiro" <?= $tipo_relatorio === 'financeiro' ? 'selected' : '' ?>>
                                💰 Relatório Financeiro
                            </option>
                            <option value="avaliacoes" <?= $tipo_relatorio === 'avaliacoes' ? 'selected' : '' ?>>
                                ⭐ Relatório de Avaliações
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="periodo">Período</label>
                        <select id="periodo" name="periodo" onchange="this.form.submit()">
                            <option value="7" <?= $periodo === '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                            <option value="30" <?= $periodo === '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                            <option value="90" <?= $periodo === '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                            <option value="365" <?= $periodo === '365' ? 'selected' : '' ?>>Último ano</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" onclick="window.print()" class="btn btn-secondary">
                            🖨️ Imprimir
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($tipo_relatorio === 'geral'): ?>
            <!-- Relatório Geral -->
            <div class="card">
                <div class="card-header">
                    <h3>📊 Visão Geral - Últimos <?= $periodo ?> dias</h3>
                </div>
                <div class="relatorio-geral">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($relatorio_geral['total_usuarios']) ?></div>
                            <div class="stat-label">Total de Usuários</div>
                            <div class="stat-change">+<?= $relatorio_geral['novos_usuarios'] ?> novos</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($relatorio_geral['total_clientes']) ?></div>
                            <div class="stat-label">Clientes</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($relatorio_geral['total_prestadores']) ?></div>
                            <div class="stat-label">Prestadores</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($relatorio_geral['solicitacoes_concluidas']) ?></div>
                            <div class="stat-label">Atendimentos Concluídos</div>
                            <div class="stat-change">+<?= $relatorio_geral['solicitacoes_periodo'] ?> no período</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">R$ <?= number_format($relatorio_geral['volume_financeiro'], 2, ',', '.') ?></div>
                            <div class="stat-label">Volume Financeiro</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($relatorio_geral['media_avaliacoes'], 1) ?> ⭐</div>
                            <div class="stat-label">Média de Avaliações</div>
                            <div class="stat-change"><?= $relatorio_geral['total_avaliacoes'] ?> avaliações</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Prestadores e Clientes -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3>🏆 Top Prestadores</h3>
                        </div>
                        <div class="ranking-list">
                            <?php if (empty($top_prestadores)): ?>
                                <p class="text-center text-muted">Nenhum prestador encontrado no período</p>
                            <?php else: ?>
                                <?php foreach ($top_prestadores as $index => $prestador): ?>
                                    <div class="ranking-item">
                                        <div class="ranking-position"><?= $index + 1 ?>º</div>
                                        <div class="ranking-info">
                                            <strong><?= htmlspecialchars($prestador['nome']) ?></strong>
                                            <p><?= $prestador['total_atendimentos'] ?> atendimentos • <?= number_format($prestador['media_avaliacao'], 1) ?> ⭐</p>
                                            <small>R$ <?= number_format($prestador['volume_total'], 2, ',', '.') ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3>👑 Top Clientes</h3>
                        </div>
                        <div class="ranking-list">
                            <?php if (empty($top_clientes)): ?>
                                <p class="text-center text-muted">Nenhum cliente encontrado no período</p>
                            <?php else: ?>
                                <?php foreach ($top_clientes as $index => $cliente): ?>
                                    <div class="ranking-item">
                                        <div class="ranking-position"><?= $index + 1 ?>º</div>
                                        <div class="ranking-info">
                                            <strong><?= htmlspecialchars($cliente['nome']) ?></strong>
                                            <p><?= $cliente['total_solicitacoes'] ?> solicitações</p>
                                            <small>R$ <?= number_format($cliente['volume_total'], 2, ',', '.') ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($tipo_relatorio === 'financeiro'): ?>
            <!-- Relatório Financeiro -->
            <div class="card">
                <div class="card-header">
                    <h3>💰 Relatório Financeiro - Últimos <?= $periodo ?> dias</h3>
                </div>
                <div class="financial-summary">
                    <?php 
                    $volume_total = array_sum(array_column($relatorio_financeiro, 'volume_dia'));
                    $atendimentos_total = array_sum(array_column($relatorio_financeiro, 'atendimentos'));
                    $ticket_medio = $atendimentos_total > 0 ? $volume_total / $atendimentos_total : 0;
                    ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">R$ <?= number_format($volume_total, 2, ',', '.') ?></div>
                            <div class="stat-label">Volume Total</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($atendimentos_total) ?></div>
                            <div class="stat-label">Atendimentos</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">R$ <?= number_format($ticket_medio, 2, ',', '.') ?></div>
                            <div class="stat-label">Ticket Médio</div>
                        </div>
                    </div>
                </div>
                <div class="financial-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Atendimentos</th>
                                <th>Volume</th>
                                <th>Ticket Médio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($relatorio_financeiro)): ?>
                                <tr><td colspan="4" class="text-center">Nenhum dado financeiro no período</td></tr>
                            <?php else: ?>
                                <?php foreach ($relatorio_financeiro as $dia): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($dia['data'])) ?></td>
                                        <td><?= $dia['atendimentos'] ?></td>
                                        <td>R$ <?= number_format($dia['volume_dia'], 2, ',', '.') ?></td>
                                        <td>R$ <?= number_format($dia['atendimentos'] > 0 ? $dia['volume_dia'] / $dia['atendimentos'] : 0, 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Volume por Serviço -->
            <div class="card">
                <div class="card-header">
                    <h3>📊 Volume por Tipo de Serviço</h3>
                </div>
                <div class="services-financial">
                    <?php if (empty($servicos_financeiro)): ?>
                        <p class="text-center text-muted">Nenhum serviço encontrado no período</p>
                    <?php else: ?>
                        <?php foreach ($servicos_financeiro as $servico): ?>
                            <div class="service-financial-item">
                                <div class="service-info">
                                    <strong><?= htmlspecialchars($servico['nome_servico']) ?></strong>
                                    <p><?= $servico['total_atendimentos'] ?> atendimentos</p>
                                </div>
                                <div class="service-values">
                                    <div class="volume">R$ <?= number_format($servico['volume_total'], 2, ',', '.') ?></div>
                                    <div class="ticket">Ticket: R$ <?= number_format($servico['ticket_medio'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($tipo_relatorio === 'avaliacoes'): ?>
            <!-- Relatório de Avaliações -->
            <div class="card">
                <div class="card-header">
                    <h3>⭐ Relatório de Avaliações - Últimos <?= $periodo ?> dias</h3>
                </div>
                <div class="avaliacoes-summary">
                    <div class="distribuicao-visual">
                        <?php if (empty($distribuicao_notas)): ?>
                            <p class="text-center text-muted">Nenhuma avaliação encontrada no período</p>
                        <?php else: ?>
                            <?php foreach ($distribuicao_notas as $nota): ?>
                                <div class="nota-bar">
                                    <span class="nota-label"><?= $nota['nota'] ?> ⭐</span>
                                    <div class="progress-bar">
                                        <div class="progress" style="width: <?= $nota['percentual'] ?>%"></div>
                                    </div>
                                    <span class="nota-stats"><?= $nota['quantidade'] ?> (<?= $nota['percentual'] ?>%)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Avaliações Recentes -->
            <div class="card">
                <div class="card-header">
                    <h3>📝 Avaliações Recentes</h3>
                </div>
                <div class="avaliacoes-recentes">
                    <?php if (empty($avaliacoes_recentes)): ?>
                        <p class="text-center text-muted">Nenhuma avaliação recente encontrada</p>
                    <?php else: ?>
                        <?php foreach ($avaliacoes_recentes as $avaliacao): ?>
                            <div class="avaliacao-item">
                                <div class="avaliacao-header">
                                    <div class="avaliacao-nota"><?= $avaliacao['nota'] ?> ⭐</div>
                                    <div class="avaliacao-info">
                                        <strong><?= htmlspecialchars($avaliacao['cliente_nome']) ?></strong>
                                        <span>→</span>
                                        <strong><?= htmlspecialchars($avaliacao['prestador_nome']) ?></strong>
                                        <p><?= htmlspecialchars($avaliacao['nome_servico']) ?></p>
                                    </div>
                                    <div class="avaliacao-data">
                                        <?= date('d/m/Y', strtotime($avaliacao['data_avaliacao'])) ?>
                                    </div>
                                </div>
                                <?php if ($avaliacao['comentario']): ?>
                                    <div class="avaliacao-comentario">
                                        <p><?= htmlspecialchars(substr($avaliacao['comentario'], 0, 200)) ?><?= strlen($avaliacao['comentario']) > 200 ? '...' : '' ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<style>
@media print {
    .sidebar, .page-header p, .filters-form { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .card { break-inside: avoid; }
}
</style>
</body>
</html>
