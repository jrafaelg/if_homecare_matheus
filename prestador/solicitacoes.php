<?php
require_once '../config/config.php';
checkUserType(USER_PRESTADOR);

$db = getDB();
$user = getLoggedUser();

// Filtros
$status_filtro = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action']);
    $solicitacao_id = (int)$_POST['solicitacao_id'];

    if ($action === 'aceitar' || $action === 'recusar') {
        $observacoes = sanitize($_POST['observacoes_prestador'] ?? '');
        $motivo_recusa = sanitize($_POST['motivo_recusa'] ?? '');

        try {
            // Verificar se a solicitação pertence ao prestador e está pendente
            $stmt = $db->prepare("
                SELECT * FROM solicitacoes 
                WHERE id = ? AND prestador_id = ? AND status = 'pendente'
            ");
            $stmt->execute([$solicitacao_id, $user['id']]);
            $solicitacao = $stmt->fetch();

            if ($solicitacao) {
                $db->beginTransaction();

                $novo_status = $action === 'aceitar' ? 'aceita' : 'recusada';

                // Atualizar status da solicitação
                $stmt = $db->prepare("
                    UPDATE solicitacoes 
                    SET status = ?, observacoes_prestador = ?, motivo_recusa = ?, 
                        data_resposta = NOW(), data_atualizacao = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $novo_status, 
                    $observacoes, 
                    $action === 'recusar' ? $motivo_recusa : null, 
                    $solicitacao_id
                ]);

                // Inserir histórico
                $stmt = $db->prepare("
                    INSERT INTO historico_solicitacoes (
                        solicitacao_id, status_anterior, status_novo, 
                        observacao, usuario_id, data_alteracao
                    ) VALUES (?, 'pendente', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $solicitacao_id, 
                    $novo_status, 
                    $action === 'aceitar' ? 'Solicitação aceita pelo prestador' : 'Solicitação recusada pelo prestador',
                    $user['id']
                ]);

                // Criar notificação para o cliente
                $titulo = $action === 'aceitar' ? 'Solicitação Aceita!' : 'Solicitação Recusada';
                $mensagem = $action === 'aceitar' 
                    ? 'Sua solicitação foi aceita por ' . $user['nome'] . '. Prepare-se para o atendimento!'
                    : 'Sua solicitação foi recusada por ' . $user['nome'] . '.';

                $stmt = $db->prepare("
                    INSERT INTO notificacoes (
                        usuario_id, tipo, titulo, mensagem, link, data_criacao
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $solicitacao['cliente_id'],
                    $action === 'aceitar' ? 'solicitacao_aceita' : 'solicitacao_recusada',
                    $titulo,
                    $mensagem,
                    '/cliente/meus_agendamentos.php'
                ]);

                $db->commit();
                
                $message = $action === 'aceitar' 
                    ? 'Solicitação aceita com sucesso!' 
                    : 'Solicitação recusada com sucesso!';
                setAlert($message, 'success');
            } else {
                setAlert('Solicitação não encontrada ou já foi respondida', 'error');
            }
        } catch (PDOException $e) {
            $db->rollBack();
            setAlert('Erro ao processar solicitação', 'error');
        }
    } elseif ($action === 'iniciar') {
        try {
            // Verificar se a solicitação pode ser iniciada
            $stmt = $db->prepare("
                SELECT * FROM solicitacoes 
                WHERE id = ? AND prestador_id = ? AND status = 'aceita'
            ");
            $stmt->execute([$solicitacao_id, $user['id']]);
            $solicitacao = $stmt->fetch();

            if ($solicitacao) {
                $db->beginTransaction();

                // Atualizar status
                $stmt = $db->prepare("
                    UPDATE solicitacoes 
                    SET status = 'em_andamento', data_atualizacao = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$solicitacao_id]);

                // Inserir histórico
                $stmt = $db->prepare("
                    INSERT INTO historico_solicitacoes (
                        solicitacao_id, status_anterior, status_novo, 
                        observacao, usuario_id, data_alteracao
                    ) VALUES (?, 'aceita', 'em_andamento', 'Atendimento iniciado', ?, NOW())
                ");
                $stmt->execute([$solicitacao_id, $user['id']]);

                // Notificar cliente
                $stmt = $db->prepare("
                    INSERT INTO notificacoes (
                        usuario_id, tipo, titulo, mensagem, link, data_criacao
                    ) VALUES (?, 'atendimento_iniciado', 'Atendimento Iniciado', 
                             'O atendimento com " . $user['nome'] . " foi iniciado!', 
                             '/cliente/meus_agendamentos.php', NOW())
                ");
                $stmt->execute([$solicitacao['cliente_id']]);

                $db->commit();
                setAlert('Atendimento iniciado com sucesso!', 'success');
            }
        } catch (PDOException $e) {
            $db->rollBack();
            setAlert('Erro ao iniciar atendimento', 'error');
        }
    } elseif ($action === 'concluir') {
        try {
            // Verificar se a solicitação pode ser concluída
            $stmt = $db->prepare("
                SELECT * FROM solicitacoes 
                WHERE id = ? AND prestador_id = ? AND status = 'em_andamento'
            ");
            $stmt->execute([$solicitacao_id, $user['id']]);
            $solicitacao = $stmt->fetch();

            if ($solicitacao) {
                $db->beginTransaction();

                // Atualizar status
                $stmt = $db->prepare("
                    UPDATE solicitacoes 
                    SET status = 'concluida', data_conclusao = NOW(), data_atualizacao = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$solicitacao_id]);

                // Inserir histórico
                $stmt = $db->prepare("
                    INSERT INTO historico_solicitacoes (
                        solicitacao_id, status_anterior, status_novo, 
                        observacao, usuario_id, data_alteracao
                    ) VALUES (?, 'em_andamento', 'concluida', 'Atendimento concluído', ?, NOW())
                ");
                $stmt->execute([$solicitacao_id, $user['id']]);

                // Atualizar estatísticas do prestador
                $stmt = $db->prepare("
                    UPDATE perfil_prestador 
                    SET total_atendimentos = total_atendimentos + 1
                    WHERE prestador_id = ?
                ");
                $stmt->execute([$user['id']]);

                // Notificar cliente
                $stmt = $db->prepare("
                    INSERT INTO notificacoes (
                        usuario_id, tipo, titulo, mensagem, link, data_criacao
                    ) VALUES (?, 'atendimento_concluido', 'Atendimento Concluído', 
                             'Seu atendimento foi concluído! Que tal avaliar o serviço?', 
                             '/cliente/meus_agendamentos.php', NOW())
                ");
                $stmt->execute([$solicitacao['cliente_id']]);

                $db->commit();
                setAlert('Atendimento concluído com sucesso!', 'success');
            }
        } catch (PDOException $e) {
            $db->rollBack();
            setAlert('Erro ao concluir atendimento', 'error');
        }
    }

    redirect('/prestador/solicitacoes.php' . ($status_filtro ? '?status=' . $status_filtro : ''));
}

// Buscar solicitações
$where_conditions = ['s.prestador_id = ?'];
$params = [$user['id']];

if (!empty($status_filtro)) {
    $where_conditions[] = 's.status = ?';
    $params[] = $status_filtro;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Contar total
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM solicitacoes s
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);

    // Buscar solicitações
    $stmt = $db->prepare("
        SELECT s.*, 
               c.nome as cliente_nome, c.telefone as cliente_telefone, c.email as cliente_email,
               srv.nome_servico,
               e.rua, e.numero, e.complemento, e.bairro, e.cidade, e.estado, e.referencia
        FROM solicitacoes s
        INNER JOIN usuarios c ON s.cliente_id = c.id
        INNER JOIN servicos srv ON s.servico_id = srv.id
        INNER JOIN enderecos e ON s.endereco_id = e.id
        WHERE $where_clause
        ORDER BY 
            CASE 
                WHEN s.status = 'pendente' THEN 1
                WHEN s.status = 'aceita' THEN 2
                WHEN s.status = 'em_andamento' THEN 3
                ELSE 4
            END,
            s.data_solicitacao DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $solicitacoes = $stmt->fetchAll();

    // Estatísticas
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'aceita' THEN 1 ELSE 0 END) as aceitas,
            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas,
            SUM(CASE WHEN status = 'recusada' THEN 1 ELSE 0 END) as recusadas
        FROM solicitacoes s
        WHERE s.prestador_id = ?
    ");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    $error = "Erro ao buscar solicitações";
    $solicitacoes = [];
    $stats = [];
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitações - <?= SITE_NAME ?></title>
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
            <li><a href="solicitacoes.php" class="active">📋 Solicitações</a></li>
            <li><a href="agenda.php">📅 Agenda</a></li>
            <li><a href="avaliacoes.php">⭐ Avaliações</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>Solicitações de Serviço</h1>
            <p>Gerencie as solicitações recebidas dos clientes</p>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card urgent">
                <div class="stat-value"><?= $stats['pendentes'] ?? 0 ?></div>
                <div class="stat-label">Aguardando Resposta</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['aceitas'] ?? 0 ?></div>
                <div class="stat-label">Aceitas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['em_andamento'] ?? 0 ?></div>
                <div class="stat-label">Em Andamento</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['concluidas'] ?? 0 ?></div>
                <div class="stat-label">Concluídas</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="card-header">
                <h3>Filtrar Solicitações</h3>
            </div>
            <div class="filters">
                <a href="solicitacoes.php" 
                   class="filter-btn <?= empty($status_filtro) ? 'active' : '' ?>">
                    Todas (<?= $stats['total'] ?? 0 ?>)
                </a>
                <a href="?status=pendente" 
                   class="filter-btn urgent <?= $status_filtro === 'pendente' ? 'active' : '' ?>">
                    🔔 Pendentes (<?= $stats['pendentes'] ?? 0 ?>)
                </a>
                <a href="?status=aceita" 
                   class="filter-btn <?= $status_filtro === 'aceita' ? 'active' : '' ?>">
                    Aceitas (<?= $stats['aceitas'] ?? 0 ?>)
                </a>
                <a href="?status=em_andamento" 
                   class="filter-btn <?= $status_filtro === 'em_andamento' ? 'active' : '' ?>">
                    Em Andamento (<?= $stats['em_andamento'] ?? 0 ?>)
                </a>
                <a href="?status=concluida" 
                   class="filter-btn <?= $status_filtro === 'concluida' ? 'active' : '' ?>">
                    Concluídas (<?= $stats['concluidas'] ?? 0 ?>)
                </a>
            </div>
        </div>

        <!-- Lista de Solicitações -->
        <div class="card">
            <div class="card-header">
                <h3>Suas Solicitações</h3>
            </div>

            <?php if (empty($solicitacoes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3>Nenhuma solicitação encontrada</h3>
                    <p>Você ainda não recebeu solicitações de serviço.</p>
                </div>
            <?php else: ?>
                <div class="solicitacoes-list">
                    <?php foreach ($solicitacoes as $solicitacao): ?>
                        <div class="solicitacao-card status-<?= $solicitacao['status'] ?>">
                            <div class="solicitacao-header">
                                <div class="solicitacao-info">
                                    <h4><?= htmlspecialchars($solicitacao['nome_servico']) ?></h4>
                                    <p class="cliente">
                                        <strong><?= htmlspecialchars($solicitacao['cliente_nome']) ?></strong>
                                        - <?= formatPhone($solicitacao['cliente_telefone']) ?>
                                    </p>
                                </div>
                                <div class="solicitacao-status">
                                    <?php
                                    $status_classes = [
                                        'pendente' => 'badge-warning',
                                        'aceita' => 'badge-info',
                                        'recusada' => 'badge-error',
                                        'em_andamento' => 'badge-success',
                                        'concluida' => 'badge-success',
                                        'cancelada' => 'badge-secondary'
                                    ];
                                    $status_labels = [
                                        'pendente' => '⏳ Aguardando Resposta',
                                        'aceita' => '✅ Aceita',
                                        'recusada' => '❌ Recusada',
                                        'em_andamento' => '🔄 Em Andamento',
                                        'concluida' => '✅ Concluída',
                                        'cancelada' => '🚫 Cancelada'
                                    ];
                                    ?>
                                    <span class="badge <?= $status_classes[$solicitacao['status']] ?>">
                                        <?= $status_labels[$solicitacao['status']] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="solicitacao-details">
                                <div class="detail-item">
                                    <strong>📅 Data:</strong>
                                    <?= formatDateBR($solicitacao['data_inicio']) ?>
                                    <?php if ($solicitacao['data_fim']): ?>
                                        até <?= formatDateBR($solicitacao['data_fim']) ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($solicitacao['horario_inicio']): ?>
                                    <div class="detail-item">
                                        <strong>🕒 Horário:</strong>
                                        <?= date('H:i', strtotime($solicitacao['horario_inicio'])) ?>
                                        <?php if ($solicitacao['horario_fim']): ?>
                                            às <?= date('H:i', strtotime($solicitacao['horario_fim'])) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="detail-item">
                                    <strong>📍 Local:</strong>
                                    <?= htmlspecialchars($solicitacao['rua'] . ', ' . $solicitacao['numero']) ?>
                                    <?php if ($solicitacao['complemento']): ?>
                                        - <?= htmlspecialchars($solicitacao['complemento']) ?>
                                    <?php endif; ?>
                                    <br>
                                    <?= htmlspecialchars($solicitacao['bairro'] . ', ' . $solicitacao['cidade'] . ' - ' . $solicitacao['estado']) ?>
                                    <?php if ($solicitacao['referencia']): ?>
                                        <br><em>Ref: <?= htmlspecialchars($solicitacao['referencia']) ?></em>
                                    <?php endif; ?>
                                </div>

                                <?php if ($solicitacao['valor_total']): ?>
                                    <div class="detail-item">
                                        <strong>💰 Valor:</strong>
                                        <?= formatMoney($solicitacao['valor_total']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($solicitacao['observacoes_cliente']): ?>
                                    <div class="detail-item">
                                        <strong>📝 Observações do Cliente:</strong>
                                        <?= nl2br(htmlspecialchars($solicitacao['observacoes_cliente'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ações baseadas no status -->
                            <div class="solicitacao-actions">
                                <?php if ($solicitacao['status'] === 'pendente'): ?>
                                    <button type="button" class="btn btn-success" 
                                            onclick="abrirModalResposta(<?= $solicitacao['id'] ?>, 'aceitar')">
                                        ✅ Aceitar
                                    </button>
                                    <button type="button" class="btn btn-error" 
                                            onclick="abrirModalResposta(<?= $solicitacao['id'] ?>, 'recusar')">
                                        ❌ Recusar
                                    </button>
                                <?php elseif ($solicitacao['status'] === 'aceita'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="iniciar">
                                        <input type="hidden" name="solicitacao_id" value="<?= $solicitacao['id'] ?>">
                                        <button type="submit" class="btn btn-primary">
                                            🚀 Iniciar Atendimento
                                        </button>
                                    </form>
                                <?php elseif ($solicitacao['status'] === 'em_andamento'): ?>
                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Tem certeza que deseja marcar como concluído?')">
                                        <input type="hidden" name="action" value="concluir">
                                        <input type="hidden" name="solicitacao_id" value="<?= $solicitacao['id'] ?>">
                                        <button type="submit" class="btn btn-success">
                                            ✅ Marcar como Concluído
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a href="solicitacao_detalhes.php?id=<?= $solicitacao['id'] ?>" 
                                   class="btn btn-secondary btn-sm">Ver Detalhes</a>
                            </div>

                            <div class="solicitacao-meta">
                                <small>
                                    Solicitado em <?= formatDateTimeBR($solicitacao['data_solicitacao']) ?>
                                    <?php if ($solicitacao['data_resposta']): ?>
                                        • Respondido em <?= formatDateTimeBR($solicitacao['data_resposta']) ?>
                                    <?php endif; ?>
                                    <?php if ($solicitacao['data_conclusao']): ?>
                                        • Concluído em <?= formatDateTimeBR($solicitacao['data_conclusao']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $status_filtro ? '&status=' . $status_filtro : '' ?>" 
                               class="btn btn-secondary">← Anterior</a>
                        <?php endif; ?>

                        <span class="pagination-info">
                            Página <?= $page ?> de <?= $total_pages ?> 
                            (<?= $total_records ?> registros)
                        </span>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $status_filtro ? '&status=' . $status_filtro : '' ?>" 
                               class="btn btn-secondary">Próxima →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal de Resposta -->
<div id="modalResposta" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitulo">Responder Solicitação</h3>
            <span class="modal-close" onclick="fecharModal()">&times;</span>
        </div>
        <form method="POST" id="formResposta">
            <input type="hidden" name="action" id="modalAction">
            <input type="hidden" name="solicitacao_id" id="modalSolicitacaoId">
            
            <div class="modal-body">
                <div class="form-group" id="observacoesGroup">
                    <label for="observacoes_prestador">Observações</label>
                    <textarea id="observacoes_prestador" name="observacoes_prestador" rows="3"
                              placeholder="Adicione observações sobre o atendimento..."></textarea>
                </div>

                <div class="form-group" id="motivoRecusaGroup" style="display: none;">
                    <label for="motivo_recusa">Motivo da Recusa *</label>
                    <textarea id="motivo_recusa" name="motivo_recusa" rows="3"
                              placeholder="Explique o motivo da recusa..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn" id="btnConfirmar">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalResposta(solicitacaoId, acao) {
    document.getElementById('modalSolicitacaoId').value = solicitacaoId;
    document.getElementById('modalAction').value = acao;
    
    const modal = document.getElementById('modalResposta');
    const titulo = document.getElementById('modalTitulo');
    const btnConfirmar = document.getElementById('btnConfirmar');
    const observacoesGroup = document.getElementById('observacoesGroup');
    const motivoRecusaGroup = document.getElementById('motivoRecusaGroup');
    const motivoRecusa = document.getElementById('motivo_recusa');
    
    if (acao === 'aceitar') {
        titulo.textContent = 'Aceitar Solicitação';
        btnConfirmar.textContent = '✅ Aceitar';
        btnConfirmar.className = 'btn btn-success';
        observacoesGroup.style.display = 'block';
        motivoRecusaGroup.style.display = 'none';
        motivoRecusa.required = false;
    } else {
        titulo.textContent = 'Recusar Solicitação';
        btnConfirmar.textContent = '❌ Recusar';
        btnConfirmar.className = 'btn btn-error';
        observacoesGroup.style.display = 'block';
        motivoRecusaGroup.style.display = 'block';
        motivoRecusa.required = true;
    }
    
    modal.style.display = 'block';
}

function fecharModal() {
    document.getElementById('modalResposta').style.display = 'none';
    document.getElementById('formResposta').reset();
}

// Fechar modal clicando fora
window.onclick = function(event) {
    const modal = document.getElementById('modalResposta');
    if (event.target === modal) {
        fecharModal();
    }
}
</script>
</body>
</html>