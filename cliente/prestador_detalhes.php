<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user = getLoggedUser();

$prestador_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$servico_selecionado = isset($_GET['servico']) ? (int)$_GET['servico'] : 0;

if (!$prestador_id) {
    redirect('/cliente/buscar_prestadores.php');
}

// Buscar dados do prestador
try {
    $stmt = $db->prepare("
        SELECT u.*, pp.*, e.rua, e.numero, e.bairro, e.cidade, e.estado, e.cep
        FROM usuarios u
        INNER JOIN perfil_prestador pp ON u.id = pp.prestador_id
        LEFT JOIN enderecos e ON u.id = e.usuario_id AND e.principal = 1
        WHERE u.id = ? AND u.tipo_usuario = 'prestador' AND u.status = 'ativo'
    ");
    $stmt->execute([$prestador_id]);
    $prestador = $stmt->fetch();

    if (!$prestador) {
        setAlert('Prestador não encontrado', 'error');
        redirect('/cliente/buscar_prestadores.php');
    }

    // Buscar serviços oferecidos pelo prestador
    $stmt = $db->prepare("
        SELECT ps.*, s.nome_servico, s.categoria
        FROM prestador_servicos ps
        INNER JOIN servicos s ON ps.servico_id = s.id
        WHERE ps.prestador_id = ? AND ps.status = 'ativo' AND s.status = 'ativo'
        ORDER BY s.nome_servico
    ");
    $stmt->execute([$prestador_id]);
    $servicos = $stmt->fetchAll();

    // Buscar avaliações do prestador
    $stmt = $db->prepare("
        SELECT a.*, u.nome as cliente_nome, s.nome_servico, sol.data_inicio
        FROM avaliacoes a
        INNER JOIN usuarios u ON a.cliente_id = u.id
        INNER JOIN solicitacoes sol ON a.solicitacao_id = sol.id
        INNER JOIN servicos s ON sol.servico_id = s.id
        WHERE a.prestador_id = ?
        ORDER BY a.data_avaliacao DESC
        LIMIT 10
    ");
    $stmt->execute([$prestador_id]);
    $avaliacoes = $stmt->fetchAll();

    // Calcular estatísticas das avaliações
    $stats_avaliacoes = [
        'nota_5' => 0, 'nota_4' => 0, 'nota_3' => 0, 'nota_2' => 0, 'nota_1' => 0
    ];
    
    foreach ($avaliacoes as $avaliacao) {
        $stats_avaliacoes['nota_' . $avaliacao['nota']]++;
    }

} catch (PDOException $e) {
    setAlert('Erro ao carregar dados do prestador', 'error');
    redirect('/cliente/buscar_prestadores.php');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($prestador['nome']) ?> - <?= SITE_NAME ?></title>
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
            <li><a href="buscar_prestadores.php" class="active">🔍 Buscar Prestadores</a></li>
            <li><a href="meus_agendamentos.php">📋 Meus Agendamentos</a></li>
            <li><a href="enderecos.php">📍 Meus Endereços</a></li>
            <li><a href="perfil.php">👤 Meu Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <div class="d-flex justify-between align-center">
                <div>
                    <h1><?= htmlspecialchars($prestador['nome']) ?></h1>
                    <p>Perfil do Prestador de Serviços</p>
                </div>
                <a href="buscar_prestadores.php" class="btn btn-secondary">← Voltar à Busca</a>
            </div>
        </div>

        <div class="row">
            <!-- Coluna Principal -->
            <div class="col-md-8">
                <!-- Informações Básicas -->
                <div class="card">
                    <div class="prestador-profile-header">
                        <div class="prestador-avatar-large">
                            <?php if ($prestador['foto_perfil']): ?>
                                <img src="../assets/images/uploads/<?= $prestador['foto_perfil'] ?>" 
                                     alt="<?= htmlspecialchars($prestador['nome']) ?>">
                            <?php else: ?>
                                <div class="avatar-placeholder-large">
                                    <?= strtoupper(substr($prestador['nome'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="prestador-profile-info">
                            <h2><?= htmlspecialchars($prestador['nome']) ?></h2>
                            <?php if ($prestador['formacao']): ?>
                                <p class="prestador-formacao">🎓 <?= htmlspecialchars($prestador['formacao']) ?></p>
                            <?php endif; ?>
                            <?php if ($prestador['registro_profissional']): ?>
                                <p class="prestador-registro">📋 <?= htmlspecialchars($prestador['registro_profissional']) ?></p>
                            <?php endif; ?>
                            <?php if ($prestador['cidade']): ?>
                                <p class="prestador-local">
                                    📍 <?= htmlspecialchars($prestador['cidade'] . ' - ' . $prestador['estado']) ?>
                                </p>
                            <?php endif; ?>
                            <p class="prestador-contato">📞 <?= formatPhone($prestador['telefone']) ?></p>
                        </div>
                    </div>

                    <div class="prestador-stats-detailed">
                        <div class="stat-item">
                            <div class="stat-value">
                                <?php if ($prestador['total_avaliacoes'] > 0): ?>
                                    ⭐ <?= number_format($prestador['media_avaliacoes'], 1) ?>
                                <?php else: ?>
                                    ⭐ --
                                <?php endif; ?>
                            </div>
                            <div class="stat-label"><?= $prestador['total_avaliacoes'] ?> avaliações</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $prestador['total_atendimentos'] ?></div>
                            <div class="stat-label">atendimentos realizados</div>
                        </div>
                        <?php if ($prestador['anos_experiencia']): ?>
                            <div class="stat-item">
                                <div class="stat-value"><?= $prestador['anos_experiencia'] ?></div>
                                <div class="stat-label">anos de experiência</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($prestador['raio_atendimento']): ?>
                            <div class="stat-item">
                                <div class="stat-value"><?= $prestador['raio_atendimento'] ?>km</div>
                                <div class="stat-label">raio de atendimento</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Descrição Profissional -->
                <?php if ($prestador['descricao_profissional']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Sobre o Profissional</h3>
                        </div>
                        <div class="card-body">
                            <p><?= nl2br(htmlspecialchars($prestador['descricao_profissional'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Especialidades -->
                <?php if ($prestador['especialidades']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Especialidades</h3>
                        </div>
                        <div class="card-body">
                            <div class="tags-list">
                                <?php 
                                $especialidades = explode(',', $prestador['especialidades']);
                                foreach ($especialidades as $esp): 
                                ?>
                                    <span class="tag tag-large"><?= htmlspecialchars(trim($esp)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Serviços Oferecidos -->
                <div class="card">
                    <div class="card-header">
                        <h3>Serviços Oferecidos</h3>
                    </div>
                    <div class="servicos-oferecidos">
                        <?php foreach ($servicos as $servico): ?>
                            <div class="servico-item <?= $servico['id'] == $servico_selecionado ? 'servico-destacado' : '' ?>">
                                <div class="servico-info">
                                    <h4><?= htmlspecialchars($servico['nome_servico']) ?></h4>
                                    <?php if ($servico['experiencia_especifica']): ?>
                                        <p><?= htmlspecialchars($servico['experiencia_especifica']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($servico['observacoes']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($servico['observacoes']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="servico-precos">
                                    <?php if ($servico['preco_hora']): ?>
                                        <div class="preco">
                                            <strong><?= formatMoney($servico['preco_hora']) ?></strong>
                                            <span>/hora</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($servico['preco_diaria']): ?>
                                        <div class="preco">
                                            <strong><?= formatMoney($servico['preco_diaria']) ?></strong>
                                            <span>/diária</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="servico-action">
                                    <a href="solicitar_servico.php?prestador=<?= $prestador['id'] ?>&servico=<?= $servico['servico_id'] ?>" 
                                       class="btn btn-success">
                                        Solicitar
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Avaliações -->
                <?php if (!empty($avaliacoes)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Avaliações dos Clientes</h3>
                        </div>
                        
                        <!-- Resumo das Avaliações -->
                        <div class="avaliacoes-resumo">
                            <div class="avaliacao-geral">
                                <div class="nota-geral">
                                    <?= number_format($prestador['media_avaliacoes'], 1) ?>
                                </div>
                                <div class="estrelas">⭐⭐⭐⭐⭐</div>
                                <div class="total"><?= $prestador['total_avaliacoes'] ?> avaliações</div>
                            </div>
                            <div class="distribuicao-notas">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <div class="nota-linha">
                                        <span><?= $i ?> ⭐</span>
                                        <div class="barra-progresso">
                                            <div class="progresso" style="width: <?= $prestador['total_avaliacoes'] > 0 ? ($stats_avaliacoes['nota_' . $i] / $prestador['total_avaliacoes']) * 100 : 0 ?>%"></div>
                                        </div>
                                        <span><?= $stats_avaliacoes['nota_' . $i] ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Lista de Avaliações -->
                        <div class="avaliacoes-lista">
                            <?php foreach ($avaliacoes as $avaliacao): ?>
                                <div class="avaliacao-item">
                                    <div class="avaliacao-header">
                                        <div class="cliente-info">
                                            <strong><?= htmlspecialchars($avaliacao['cliente_nome']) ?></strong>
                                            <span class="servico"><?= htmlspecialchars($avaliacao['nome_servico']) ?></span>
                                        </div>
                                        <div class="avaliacao-meta">
                                            <div class="nota">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?= $i <= $avaliacao['nota'] ? '⭐' : '☆' ?>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="data"><?= formatDateBR($avaliacao['data_avaliacao']) ?></div>
                                        </div>
                                    </div>
                                    <?php if ($avaliacao['comentario']): ?>
                                        <div class="avaliacao-comentario">
                                            <p><?= nl2br(htmlspecialchars($avaliacao['comentario'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($avaliacao['pontos_positivos'] || $avaliacao['pontos_negativos']): ?>
                                        <div class="avaliacao-pontos">
                                            <?php if ($avaliacao['pontos_positivos']): ?>
                                                <div class="pontos-positivos">
                                                    <strong>👍 Pontos Positivos:</strong>
                                                    <p><?= htmlspecialchars($avaliacao['pontos_positivos']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($avaliacao['pontos_negativos']): ?>
                                                <div class="pontos-negativos">
                                                    <strong>👎 Pontos a Melhorar:</strong>
                                                    <p><?= htmlspecialchars($avaliacao['pontos_negativos']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Ações Rápidas -->
                <div class="card sticky-card">
                    <div class="card-header">
                        <h3>Contratar Serviços</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($servico_selecionado > 0): ?>
                            <?php 
                            $servico_destaque = array_filter($servicos, function($s) use ($servico_selecionado) {
                                return $s['servico_id'] == $servico_selecionado;
                            });
                            $servico_destaque = reset($servico_destaque);
                            ?>
                            <?php if ($servico_destaque): ?>
                                <div class="servico-destaque">
                                    <h4><?= htmlspecialchars($servico_destaque['nome_servico']) ?></h4>
                                    <div class="precos-destaque">
                                        <?php if ($servico_destaque['preco_hora']): ?>
                                            <div class="preco-item">
                                                <span class="valor"><?= formatMoney($servico_destaque['preco_hora']) ?></span>
                                                <span class="periodo">/hora</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($servico_destaque['preco_diaria']): ?>
                                            <div class="preco-item">
                                                <span class="valor"><?= formatMoney($servico_destaque['preco_diaria']) ?></span>
                                                <span class="periodo">/diária</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="solicitar_servico.php?prestador=<?= $prestador['id'] ?>&servico=<?= $servico_selecionado ?>" 
                                       class="btn btn-success btn-block btn-lg">
                                        Solicitar Este Serviço
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Escolha um dos serviços oferecidos por este prestador:</p>
                            <?php foreach ($servicos as $servico): ?>
                                <a href="solicitar_servico.php?prestador=<?= $prestador['id'] ?>&servico=<?= $servico['servico_id'] ?>" 
                                   class="btn btn-outline btn-block mb-2">
                                    <?= htmlspecialchars($servico['nome_servico']) ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informações Adicionais -->
                <?php if ($prestador['certificados'] || $prestador['disponibilidade_geral']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Informações Adicionais</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($prestador['certificados']): ?>
                                <div class="info-item">
                                    <strong>📜 Certificações:</strong>
                                    <p><?= nl2br(htmlspecialchars($prestador['certificados'])) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($prestador['disponibilidade_geral']): ?>
                                <div class="info-item">
                                    <strong>🕒 Disponibilidade:</strong>
                                    <p><?= nl2br(htmlspecialchars($prestador['disponibilidade_geral'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>