<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user = getLoggedUser();

$solicitacao_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$solicitacao_id) {
    setAlert('Solicitação não informada', 'error');
    redirect('/cliente/meus_agendamentos.php');
}

// Buscar dados da solicitação
try {
    $stmt = $db->prepare("
        SELECT s.*, 
               p.nome as prestador_nome, p.telefone as prestador_telefone,
               srv.nome_servico,
               e.rua, e.numero, e.bairro, e.cidade, e.estado,
               av.id as avaliacao_existente
        FROM solicitacoes s
        INNER JOIN usuarios p ON s.prestador_id = p.id
        INNER JOIN servicos srv ON s.servico_id = srv.id
        INNER JOIN enderecos e ON s.endereco_id = e.id
        LEFT JOIN avaliacoes av ON s.id = av.solicitacao_id
        WHERE s.id = ? AND s.cliente_id = ? AND s.status = 'concluida'
    ");
    $stmt->execute([$solicitacao_id, $user['id']]);
    $solicitacao = $stmt->fetch();

    if (!$solicitacao) {
        setAlert('Solicitação não encontrada ou não pode ser avaliada', 'error');
        redirect('/cliente/meus_agendamentos.php');
    }

    if ($solicitacao['avaliacao_existente']) {
        setAlert('Esta solicitação já foi avaliada', 'info');
        redirect('/cliente/meus_agendamentos.php');
    }

} catch (PDOException $e) {
    setAlert('Erro ao carregar dados da solicitação', 'error');
    redirect('/cliente/meus_agendamentos.php');
}

$errors = [];

// Processar avaliação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nota = (int)$_POST['nota'];
    $comentario = sanitize($_POST['comentario']);
    $pontos_positivos = sanitize($_POST['pontos_positivos']);
    $pontos_negativos = sanitize($_POST['pontos_negativos']);
    $recomenda = isset($_POST['recomenda']) ? 1 : 0;

    // Validações
    if ($nota < 1 || $nota > 5) {
        $errors[] = 'Selecione uma nota de 1 a 5 estrelas';
    }

    if (empty($comentario)) {
        $errors[] = 'O comentário é obrigatório';
    }

    if (strlen($comentario) < 10) {
        $errors[] = 'O comentário deve ter pelo menos 10 caracteres';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Inserir avaliação
            $stmt = $db->prepare("
                INSERT INTO avaliacoes (
                    solicitacao_id, cliente_id, prestador_id, nota, comentario,
                    pontos_positivos, pontos_negativos, recomenda, data_avaliacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $solicitacao_id,
                $user['id'],
                $solicitacao['prestador_id'],
                $nota,
                $comentario,
                $pontos_positivos,
                $pontos_negativos,
                $recomenda
            ]);

            // Recalcular média de avaliações do prestador
            $stmt = $db->prepare("
                SELECT AVG(nota) as media, COUNT(*) as total
                FROM avaliacoes 
                WHERE prestador_id = ?
            ");
            $stmt->execute([$solicitacao['prestador_id']]);
            $stats = $stmt->fetch();

            // Atualizar perfil do prestador
            $stmt = $db->prepare("
                UPDATE perfil_prestador 
                SET media_avaliacoes = ?, total_avaliacoes = ?
                WHERE prestador_id = ?
            ");
            $stmt->execute([
                round($stats['media'], 2),
                $stats['total'],
                $solicitacao['prestador_id']
            ]);

            // Criar notificação para o prestador
            $stmt = $db->prepare("
                INSERT INTO notificacoes (
                    usuario_id, tipo, titulo, mensagem, link, data_criacao
                ) VALUES (?, 'nova_avaliacao', 'Nova Avaliação Recebida!', ?, '/prestador/avaliacoes.php', NOW())
            ");
            $mensagem = $user['nome'] . ' avaliou seu atendimento com ' . $nota . ' estrela' . ($nota > 1 ? 's' : '') . '!';
            $stmt->execute([$solicitacao['prestador_id'], $mensagem]);

            $db->commit();

            setAlert('Avaliação enviada com sucesso! Obrigado pelo seu feedback.', 'success');
            redirect('/cliente/meus_agendamentos.php');

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Erro ao salvar avaliação. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliar Serviço - <?= SITE_NAME ?></title>
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
            <div class="d-flex justify-between align-center">
                <div>
                    <h1>⭐ Avaliar Serviço</h1>
                    <p>Compartilhe sua experiência com o atendimento</p>
                </div>
                <a href="meus_agendamentos.php" class="btn btn-secondary">← Voltar</a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulário de Avaliação -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Dados do Atendimento</h3>
                    </div>
                    <div class="atendimento-info">
                        <div class="info-row">
                            <strong>Prestador:</strong> <?= htmlspecialchars($solicitacao['prestador_nome']) ?>
                        </div>
                        <div class="info-row">
                            <strong>Serviço:</strong> <?= htmlspecialchars($solicitacao['nome_servico']) ?>
                        </div>
                        <div class="info-row">
                            <strong>Data:</strong> <?= formatDateBR($solicitacao['data_inicio']) ?>
                            <?php if ($solicitacao['data_fim']): ?>
                                até <?= formatDateBR($solicitacao['data_fim']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($solicitacao['horario_inicio']): ?>
                            <div class="info-row">
                                <strong>Horário:</strong> 
                                <?= date('H:i', strtotime($solicitacao['horario_inicio'])) ?>
                                <?php if ($solicitacao['horario_fim']): ?>
                                    às <?= date('H:i', strtotime($solicitacao['horario_fim'])) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <strong>Local:</strong> 
                            <?= htmlspecialchars($solicitacao['rua'] . ', ' . $solicitacao['numero'] . ' - ' . $solicitacao['bairro'] . ', ' . $solicitacao['cidade']) ?>
                        </div>
                        <?php if ($solicitacao['valor_total']): ?>
                            <div class="info-row">
                                <strong>Valor:</strong> <?= formatMoney($solicitacao['valor_total']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <strong>Concluído em:</strong> <?= formatDateTimeBR($solicitacao['data_conclusao']) ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Sua Avaliação</h3>
                    </div>
                    <form method="POST" class="avaliacao-form">
                        <!-- Nota com Estrelas -->
                        <div class="form-group">
                            <label>Nota Geral *</label>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" id="star<?= $i ?>" name="nota" value="<?= $i ?>" required>
                                    <label for="star<?= $i ?>" class="star">⭐</label>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-text">
                                <span id="rating-description">Clique nas estrelas para avaliar</span>
                            </div>
                        </div>

                        <!-- Comentário -->
                        <div class="form-group">
                            <label for="comentario">Comentário sobre o Atendimento *</label>
                            <textarea id="comentario" name="comentario" rows="4" required
                                      placeholder="Descreva como foi o atendimento, a qualidade do serviço, pontualidade, profissionalismo..."><?= isset($_POST['comentario']) ? htmlspecialchars($_POST['comentario']) : '' ?></textarea>
                            <small>Mínimo de 10 caracteres</small>
                        </div>

                        <!-- Pontos Positivos -->
                        <div class="form-group">
                            <label for="pontos_positivos">👍 Pontos Positivos</label>
                            <textarea id="pontos_positivos" name="pontos_positivos" rows="3"
                                      placeholder="O que você mais gostou no atendimento?"><?= isset($_POST['pontos_positivos']) ? htmlspecialchars($_POST['pontos_positivos']) : '' ?></textarea>
                        </div>

                        <!-- Pontos Negativos -->
                        <div class="form-group">
                            <label for="pontos_negativos">👎 Pontos a Melhorar</label>
                            <textarea id="pontos_negativos" name="pontos_negativos" rows="3"
                                      placeholder="Algo que poderia ter sido melhor?"><?= isset($_POST['pontos_negativos']) ? htmlspecialchars($_POST['pontos_negativos']) : '' ?></textarea>
                        </div>

                        <!-- Recomendação -->
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="recomenda" value="1" 
                                       <?= (isset($_POST['recomenda']) || !isset($_POST['nota'])) ? 'checked' : '' ?>>
                                <span>Eu recomendaria este prestador para outras pessoas</span>
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success btn-lg">
                                ⭐ Enviar Avaliação
                            </button>
                            <a href="meus_agendamentos.php" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar com Dicas -->
            <div class="col-md-4">
                <div class="card sticky-card">
                    <div class="card-header">
                        <h3>💡 Dicas para uma Boa Avaliação</h3>
                    </div>
                    <div class="card-body">
                        <div class="tips">
                            <div class="tip">
                                <strong>Seja Honesto</strong>
                                <p>Sua avaliação ajuda outros clientes e o prestador a melhorar</p>
                            </div>
                            <div class="tip">
                                <strong>Seja Específico</strong>
                                <p>Detalhe aspectos como pontualidade, qualidade técnica e atendimento</p>
                            </div>
                            <div class="tip">
                                <strong>Seja Construtivo</strong>
                                <p>Críticas construtivas ajudam no desenvolvimento profissional</p>
                            </div>
                            <div class="tip">
                                <strong>Considere o Contexto</strong>
                                <p>Leve em conta fatores externos que podem ter influenciado</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>📊 Critérios de Avaliação</h3>
                    </div>
                    <div class="card-body">
                        <div class="criterios">
                            <div class="criterio">
                                <strong>⭐ 1 Estrela:</strong> Muito insatisfeito
                            </div>
                            <div class="criterio">
                                <strong>⭐⭐ 2 Estrelas:</strong> Insatisfeito
                            </div>
                            <div class="criterio">
                                <strong>⭐⭐⭐ 3 Estrelas:</strong> Regular
                            </div>
                            <div class="criterio">
                                <strong>⭐⭐⭐⭐ 4 Estrelas:</strong> Satisfeito
                            </div>
                            <div class="criterio">
                                <strong>⭐⭐⭐⭐⭐ 5 Estrelas:</strong> Muito satisfeito
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.rating-stars input[type="radio"]');
    const ratingDescription = document.getElementById('rating-description');
    
    const descriptions = {
        1: '⭐ Muito insatisfeito - Serviço muito abaixo do esperado',
        2: '⭐⭐ Insatisfeito - Serviço abaixo do esperado',
        3: '⭐⭐⭐ Regular - Serviço atendeu o básico',
        4: '⭐⭐⭐⭐ Satisfeito - Bom serviço, recomendo',
        5: '⭐⭐⭐⭐⭐ Muito satisfeito - Excelente serviço!'
    };
    
    stars.forEach(star => {
        star.addEventListener('change', function() {
            const rating = this.value;
            ratingDescription.textContent = descriptions[rating];
            ratingDescription.className = 'rating-text rating-' + rating;
        });
    });
    
    // Contador de caracteres
    const comentario = document.getElementById('comentario');
    const counter = document.createElement('small');
    counter.style.color = 'var(--text-light)';
    comentario.parentNode.appendChild(counter);
    
    function updateCounter() {
        const length = comentario.value.length;
        counter.textContent = `${length} caracteres`;
        
        if (length < 10) {
            counter.style.color = 'var(--error-color)';
        } else {
            counter.style.color = 'var(--success-color)';
        }
    }
    
    comentario.addEventListener('input', updateCounter);
    updateCounter();
});
</script>
</body>
</html>