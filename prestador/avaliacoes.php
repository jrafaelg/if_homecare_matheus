<?php
require_once '../config/config.php';
checkUserType(USER_PRESTADOR);

$db = getDB();
$user = getLoggedUser();

// Filtros
$nota_filtro = isset($_GET['nota']) ? (int)$_GET['nota'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Buscar avaliações
$where_conditions = ['a.prestador_id = ?'];
$params = [$user['id']];

if ($nota_filtro > 0 && $nota_filtro <= 5) {
    $where_conditions[] = 'a.nota = ?';
    $params[] = $nota_filtro;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Contar total
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM avaliacoes a
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);

    // Buscar avaliações
    $stmt = $db->prepare("
        SELECT a.*, 
               c.nome as cliente_nome,
               s.nome_servico,
               sol.data_inicio, sol.data_conclusao, sol.valor_total
        FROM avaliacoes a
        INNER JOIN usuarios c ON a.cliente_id = c.id
        INNER JOIN solicitacoes sol ON a.solicitacao_id = sol.id
        INNER JOIN servicos s ON sol.servico_id = s.id
        WHERE $where_clause
        ORDER BY a.data_avaliacao DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $avaliacoes = $stmt->fetchAll();

    // Estatísticas gerais
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            AVG(nota) as media,
            SUM(CASE WHEN nota = 5 THEN 1 ELSE 0 END) as nota_5,
            SUM(CASE WHEN nota = 4 THEN 1 ELSE 0 END) as nota_4,
            SUM(CASE WHEN nota = 3 THEN 1 ELSE 0 END) as nota_3,
            SUM(CASE WHEN nota = 2 THEN 1 ELSE 0 END) as nota_2,
            SUM(CASE WHEN nota = 1 THEN 1 ELSE 0 END) as nota_1,
            SUM(CASE WHEN recomenda = 1 THEN 1 ELSE 0 END) as recomendacoes
        FROM avaliacoes a
        WHERE a.prestador_id = ?
    ");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch();

    // Buscar perfil para comparar
    $stmt = $db->prepare("SELECT * FROM perfil_prestador WHERE prestador_id = ?");
    $stmt->execute([$user['id']]);
    $perfil = $stmt->fetch();

} catch (PDOException $e) {
    $error = "Erro ao buscar avaliações";
    $avaliacoes = [];
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Avaliações - <?= SITE_NAME ?></title>
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
            <li><a href="servicos.php">🏥 Meus Serviços</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="avaliacoes.php" class="active">⭐ Avaliações</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>⭐ Minhas Avaliações</h1>
            <p>Veja o que os clientes estão falando sobre seus serviços</p>
        </div>

        <?php if (empty($avaliacoes) && empty($nota_filtro)): ?>
            <div class="empty-state">
                <div class="empty-icon">⭐</div>
                <h3>Nenhuma avaliação ainda</h3>
                <p>Quando você concluir atendimentos, os clientes poderão avaliar seus serviços aqui.</p>
                <a href="solicitacoes.php" class="btn btn-primary">Ver Solicitações</a>
            </div>
        <?php else: ?>
            <!-- Resumo das Avaliações -->
            <div class="card">
                <div class="card-header">
                    <h3>📊 Resumo das Suas Avaliações</h3>
                </div>
                <div class="avaliacoes-resumo-completo">
                    <div class="resumo-principal">
                        <div class="nota-geral-grande">
                            <div class="nota-numero"><?= $stats['total'] > 0 ? number_format($stats['media'], 1) : '0.0' ?></div>
                            <div class="estrelas-grandes">
                                <?php 
                                $media_int = $stats['total'] > 0 ? round($stats['media']) : 0;
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <span class="estrela <?= $i <= $media_int ? 'ativa' : '' ?>">⭐</span>
                                <?php endfor; ?>
                            </div>
                            <div class="total-avaliacoes"><?= $stats['total'] ?> avaliações</div>
                        </div>

                        <div class="estatisticas-detalhadas">
                            <div class="stat-item">
                                <div class="stat-value"><?= $stats['total'] > 0 ? round(($stats['recomendacoes'] / $stats['total']) * 100) : 0 ?>%</div>
                                <div class="stat-label">Recomendam</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $perfil['total_atendimentos'] ?></div>
                                <div class="stat-label">Atendimentos</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $stats['total'] > 0 ? round(($stats['total'] / max($perfil['total_atendimentos'], 1)) * 100) : 0 ?>%</div>
                                <div class="stat-label">Taxa de Avaliação</div>
                            </div>
                        </div>
                    </div>

                    <div class="distribuicao-notas-detalhada">
                        <h4>Distribuição das Notas</h4>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="nota-linha-detalhada">
                                <span class="nota-label"><?= $i ?> ⭐</span>
                                <div class="barra-progresso-detalhada">
                                    <div class="progresso" style="width: <?= $stats['total'] > 0 ? ($stats['nota_' . $i] / $stats['total']) * 100 : 0 ?>%"></div>
                                </div>
                                <span class="nota-count"><?= $stats['nota_' . $i] ?></span>
                                <span class="nota-percent">(<?= $stats['total'] > 0 ? round(($stats['nota_' . $i] / $stats['total']) * 100) : 0 ?>%)</span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card">
                <div class="card-header">
                    <h3>Filtrar Avaliações</h3>
                </div>
                <div class="filters">
                    <a href="avaliacoes.php" 
                       class="filter-btn <?= $nota_filtro === 0 ? 'active' : '' ?>">
                        Todas (<?= $stats['total'] ?>)
                    </a>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <a href="?nota=<?= $i ?>" 
                           class="filter-btn <?= $nota_filtro === $i ? 'active' : '' ?>">
                            <?= $i ?> ⭐ (<?= $stats['nota_' . $i] ?>)
                        </a>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Lista de Avaliações -->
            <div class="card">
                <div class="card-header">
                    <h3>Avaliações dos Clientes</h3>
                </div>

                <?php if (empty($avaliacoes)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3>Nenhuma avaliação encontrada</h3>
                        <p>Não há avaliações com os filtros selecionados.</p>
                        <a href="avaliacoes.php" class="btn btn-secondary">Ver Todas</a>
                    </div>
                <?php else: ?>
                    <div class="avaliacoes-lista-prestador">
                        <?php foreach ($avaliacoes as $avaliacao): ?>
                            <div class="avaliacao-card-prestador">
                                <div class="avaliacao-header-prestador">
                                    <div class="cliente-info-prestador">
                                        <div class="cliente-avatar">
                                            <?= strtoupper(substr($avaliacao['cliente_nome'], 0, 2)) ?>
                                        </div>
                                        <div class="cliente-dados">
                                            <strong><?= htmlspecialchars($avaliacao['cliente_nome']) ?></strong>
                                            <p><?= htmlspecialchars($avaliacao['nome_servico']) ?></p>
                                            <small><?= formatDateBR($avaliacao['data_inicio']) ?></small>
                                        </div>
                                    </div>
                                    <div class="avaliacao-nota-prestador">
                                        <div class="nota-grande"><?= $avaliacao['nota'] ?></div>
                                        <div class="estrelas-pequenas">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?= $i <= $avaliacao['nota'] ? '⭐' : '☆' ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="data-avaliacao"><?= formatDateBR($avaliacao['data_avaliacao']) ?></div>
                                    </div>
                                </div>

                                <?php if ($avaliacao['comentario']): ?>
                                    <div class="avaliacao-comentario-prestador">
                                        <h5>💬 Comentário</h5>
                                        <p><?= nl2br(htmlspecialchars($avaliacao['comentario'])) ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($avaliacao['pontos_positivos'] || $avaliacao['pontos_negativos']): ?>
                                    <div class="avaliacao-pontos-prestador">
                                        <?php if ($avaliacao['pontos_positivos']): ?>
                                            <div class="pontos-positivos-prestador">
                                                <h5>👍 Pontos Positivos</h5>
                                                <p><?= nl2br(htmlspecialchars($avaliacao['pontos_positivos'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($avaliacao['pontos_negativos']): ?>
                                            <div class="pontos-negativos-prestador">
                                                <h5>👎 Pontos a Melhorar</h5>
                                                <p><?= nl2br(htmlspecialchars($avaliacao['pontos_negativos'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="avaliacao-footer-prestador">
                                    <div class="recomendacao">
                                        <?php if ($avaliacao['recomenda']): ?>
                                            <span class="badge badge-success">👍 Recomenda</span>
                                        <?php else: ?>
                                            <span class="badge badge-error">👎 Não Recomenda</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($avaliacao['valor_total']): ?>
                                        <div class="valor-servico">
                                            Valor: <?= formatMoney($avaliacao['valor_total']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $nota_filtro ? '&nota=' . $nota_filtro : '' ?>" 
                                   class="btn btn-secondary">← Anterior</a>
                            <?php endif; ?>

                            <span class="pagination-info">
                                Página <?= $page ?> de <?= $total_pages ?> 
                                (<?= $total_records ?> avaliações)
                            </span>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $nota_filtro ? '&nota=' . $nota_filtro : '' ?>" 
                                   class="btn btn-secondary">Próxima →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Dicas para Melhorar -->
            <?php if ($stats['total'] > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>💡 Dicas para Melhorar suas Avaliações</h3>
                    </div>
                    <div class="dicas-melhoria">
                        <?php if ($stats['media'] < 4): ?>
                            <div class="dica urgente">
                                <strong>⚠️ Atenção:</strong> Sua média está abaixo de 4 estrelas. 
                                Analise os pontos negativos mencionados e trabalhe para melhorar.
                            </div>
                        <?php endif; ?>
                        
                        <div class="dica">
                            <strong>🕐 Pontualidade:</strong> Chegue sempre no horário combinado ou avise com antecedência sobre atrasos.
                        </div>
                        <div class="dica">
                            <strong>🗣️ Comunicação:</strong> Mantenha uma comunicação clara e profissional com os clientes.
                        </div>
                        <div class="dica">
                            <strong>🧼 Higiene:</strong> Mantenha sempre boa apresentação pessoal e use EPIs quando necessário.
                        </div>
                        <div class="dica">
                            <strong>📱 Feedback:</strong> Peça feedback durante o atendimento para ajustar se necessário.
                        </div>
                        <div class="dica">
                            <strong>📚 Capacitação:</strong> Mantenha-se atualizado com cursos e certificações na sua área.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>