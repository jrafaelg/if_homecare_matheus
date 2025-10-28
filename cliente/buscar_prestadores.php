<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user = getLoggedUser();

// Parâmetros de busca
$servico_id = isset($_GET['servico']) ? (int)$_GET['servico'] : 0;
$cidade = isset($_GET['cidade']) ? sanitize($_GET['cidade']) : '';
$ordenacao = isset($_GET['ordenacao']) ? sanitize($_GET['ordenacao']) : 'avaliacao';

// Buscar todos os serviços para o filtro
try {
    $stmt = $db->query("SELECT * FROM servicos WHERE status = 'ativo' ORDER BY nome_servico");
    $servicos = $stmt->fetchAll();
} catch (PDOException $e) {
    $servicos = [];
}

// Construir query de busca de prestadores
$where_conditions = [];
$params = [];

$sql = "
    SELECT DISTINCT 
        u.id, u.nome, u.telefone, u.foto_perfil,
        pp.descricao_profissional, pp.especialidades, pp.formacao,
        pp.anos_experiencia, pp.media_avaliacoes, pp.total_avaliacoes,
        pp.total_atendimentos, pp.raio_atendimento,
        ps.preco_hora, ps.preco_diaria, ps.experiencia_especifica,
        s.nome_servico,
        e.cidade, e.bairro, e.estado
    FROM usuarios u
    INNER JOIN perfil_prestador pp ON u.id = pp.prestador_id
    INNER JOIN prestador_servicos ps ON u.id = ps.prestador_id
    INNER JOIN servicos s ON ps.servico_id = s.id
    LEFT JOIN enderecos e ON u.id = e.usuario_id AND e.principal = 1
    WHERE u.tipo_usuario = 'prestador' 
    AND u.status = 'ativo'
    AND ps.status = 'ativo'
    AND s.status = 'ativo'
";

// Filtro por serviço
if ($servico_id > 0) {
    $where_conditions[] = "s.id = ?";
    $params[] = $servico_id;
}

// Filtro por cidade
if (!empty($cidade)) {
    $where_conditions[] = "e.cidade LIKE ?";
    $params[] = "%$cidade%";
}

// Adicionar condições WHERE
if (!empty($where_conditions)) {
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

// Ordenação
switch ($ordenacao) {
    case 'avaliacao':
        $sql .= " ORDER BY pp.media_avaliacoes DESC, pp.total_avaliacoes DESC";
        break;
    case 'preco_menor':
        $sql .= " ORDER BY ps.preco_hora ASC";
        break;
    case 'preco_maior':
        $sql .= " ORDER BY ps.preco_hora DESC";
        break;
    case 'experiencia':
        $sql .= " ORDER BY pp.anos_experiencia DESC";
        break;
    case 'atendimentos':
        $sql .= " ORDER BY pp.total_atendimentos DESC";
        break;
    default:
        $sql .= " ORDER BY pp.media_avaliacoes DESC, pp.total_avaliacoes DESC";
}

// Executar busca
$prestadores = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prestadores = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erro ao buscar prestadores: " . $e->getMessage();
}

// Buscar cidades disponíveis para o filtro
try {
    $stmt = $db->query("
        SELECT DISTINCT e.cidade 
        FROM enderecos e 
        INNER JOIN usuarios u ON e.usuario_id = u.id 
        WHERE u.tipo_usuario = 'prestador' AND u.status = 'ativo'
        AND e.cidade IS NOT NULL AND e.cidade != ''
        ORDER BY e.cidade
    ");
    $cidades = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $cidades = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Prestadores - <?= SITE_NAME ?></title>
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
            <h1>Buscar Prestadores</h1>
            <p>Encontre o profissional ideal para suas necessidades</p>
        </div>

        <!-- Filtros de Busca -->
        <div class="card">
            <div class="card-header">
                <h3>🔍 Filtros de Busca</h3>
            </div>
            <form method="GET" class="search-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="servico">Tipo de Serviço</label>
                        <select id="servico" name="servico">
                            <option value="">Todos os serviços</option>
                            <?php foreach ($servicos as $servico): ?>
                                <option value="<?= $servico['id'] ?>" 
                                    <?= $servico_id == $servico['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($servico['nome_servico']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cidade">Cidade</label>
                        <input type="text" id="cidade" name="cidade" 
                               placeholder="Digite a cidade..."
                               value="<?= htmlspecialchars($cidade) ?>"
                               list="cidades-list">
                        <datalist id="cidades-list">
                            <?php foreach ($cidades as $cidade_option): ?>
                                <option value="<?= htmlspecialchars($cidade_option) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label for="ordenacao">Ordenar por</label>
                        <select id="ordenacao" name="ordenacao">
                            <option value="avaliacao" <?= $ordenacao == 'avaliacao' ? 'selected' : '' ?>>
                                Melhor Avaliação
                            </option>
                            <option value="preco_menor" <?= $ordenacao == 'preco_menor' ? 'selected' : '' ?>>
                                Menor Preço
                            </option>
                            <option value="preco_maior" <?= $ordenacao == 'preco_maior' ? 'selected' : '' ?>>
                                Maior Preço
                            </option>
                            <option value="experiencia" <?= $ordenacao == 'experiencia' ? 'selected' : '' ?>>
                                Mais Experiência
                            </option>
                            <option value="atendimentos" <?= $ordenacao == 'atendimentos' ? 'selected' : '' ?>>
                                Mais Atendimentos
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                        <a href="buscar_prestadores.php" class="btn btn-secondary">Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Resultados -->
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Prestadores Encontrados (<?= count($prestadores) ?>)</h3>
            </div>

            <?php if (empty($prestadores)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <h3>Nenhum prestador encontrado</h3>
                    <p>Tente ajustar os filtros de busca ou remover alguns critérios.</p>
                </div>
            <?php else: ?>
                <div class="prestadores-grid">
                    <?php foreach ($prestadores as $prestador): ?>
                        <div class="prestador-card">
                            <div class="prestador-header">
                                <div class="prestador-avatar">
                                    <?php if ($prestador['foto_perfil']): ?>
                                        <img src="../assets/images/uploads/<?= $prestador['foto_perfil'] ?>" 
                                             alt="<?= htmlspecialchars($prestador['nome']) ?>">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?= strtoupper(substr($prestador['nome'], 0, 2)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="prestador-info">
                                    <h4><?= htmlspecialchars($prestador['nome']) ?></h4>
                                    <p class="prestador-servico"><?= htmlspecialchars($prestador['nome_servico']) ?></p>
                                    <?php if ($prestador['cidade']): ?>
                                        <p class="prestador-local">
                                            📍 <?= htmlspecialchars($prestador['cidade'] . ' - ' . $prestador['estado']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="prestador-stats">
                                <div class="stat">
                                    <span class="stat-value">
                                        <?php if ($prestador['total_avaliacoes'] > 0): ?>
                                            ⭐ <?= number_format($prestador['media_avaliacoes'], 1) ?>
                                        <?php else: ?>
                                            ⭐ --
                                        <?php endif; ?>
                                    </span>
                                    <span class="stat-label">
                                        (<?= $prestador['total_avaliacoes'] ?> avaliações)
                                    </span>
                                </div>
                                <div class="stat">
                                    <span class="stat-value"><?= $prestador['total_atendimentos'] ?></span>
                                    <span class="stat-label">atendimentos</span>
                                </div>
                                <?php if ($prestador['anos_experiencia']): ?>
                                    <div class="stat">
                                        <span class="stat-value"><?= $prestador['anos_experiencia'] ?></span>
                                        <span class="stat-label">anos exp.</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($prestador['descricao_profissional']): ?>
                                <div class="prestador-description">
                                    <p><?= htmlspecialchars(substr($prestador['descricao_profissional'], 0, 150)) ?>
                                        <?= strlen($prestador['descricao_profissional']) > 150 ? '...' : '' ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($prestador['especialidades']): ?>
                                <div class="prestador-tags">
                                    <?php 
                                    $especialidades = explode(',', $prestador['especialidades']);
                                    foreach (array_slice($especialidades, 0, 3) as $esp): 
                                    ?>
                                        <span class="tag"><?= htmlspecialchars(trim($esp)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="prestador-pricing">
                                <?php if ($prestador['preco_hora']): ?>
                                    <div class="price">
                                        <strong><?= formatMoney($prestador['preco_hora']) ?></strong>
                                        <span>/hora</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($prestador['preco_diaria']): ?>
                                    <div class="price">
                                        <strong><?= formatMoney($prestador['preco_diaria']) ?></strong>
                                        <span>/diária</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="prestador-actions">
                                <a href="prestador_detalhes.php?id=<?= $prestador['id'] ?>&servico=<?= $servico_id ?>" 
                                   class="btn btn-primary btn-block">
                                    Ver Perfil Completo
                                </a>
                                <a href="solicitar_servico.php?prestador=<?= $prestador['id'] ?>&servico=<?= $servico_id ?>" 
                                   class="btn btn-success btn-block">
                                    Solicitar Serviço
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
// Submeter formulário automaticamente quando mudar filtros
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.search-form');
    const selects = form.querySelectorAll('select');
    
    selects.forEach(select => {
        select.addEventListener('change', function() {
            form.submit();
        });
    });
});
</script>
</body>
</html>