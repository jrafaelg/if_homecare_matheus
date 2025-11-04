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

// Buscar dados completos da solicitação
try {
    $stmt = $db->prepare("
        SELECT s.*, 
               p.nome as prestador_nome, p.telefone as prestador_telefone, p.email as prestador_email,
               srv.nome_servico, srv.categoria,
               e.rua, e.numero, e.complemento, e.bairro, e.cidade, e.estado, e.cep, e.referencia,
               pp.formacao, pp.registro_profissional, pp.anos_experiencia,
               av.nota as avaliacao_nota, av.comentario as avaliacao_comentario, av.data_avaliacao
        FROM solicitacoes s
        INNER JOIN usuarios p ON s.prestador_id = p.id
        INNER JOIN servicos srv ON s.servico_id = srv.id
        INNER JOIN enderecos e ON s.endereco_id = e.id
        LEFT JOIN perfil_prestador pp ON p.id = pp.prestador_id
        LEFT JOIN avaliacoes av ON s.id = av.solicitacao_id
        WHERE s.id = ? AND s.cliente_id = ?
    ");
    $stmt->execute([$solicitacao_id, $user['id']]);
    $solicitacao = $stmt->fetch();

    if (!$solicitacao) {
        setAlert('Solicitação não encontrada', 'error');
        redirect('/cliente/meus_agendamentos.php');
    }

    // Buscar histórico de status
    $stmt = $db->prepare("
        SELECT h.*, u.nome as usuario_nome
        FROM historico_solicitacoes h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.solicitacao_id = ?
        ORDER BY h.data_alteracao ASC
    ");
    $stmt->execute([$solicitacao_id]);
    $historico = $stmt->fetchAll();

} catch (PDOException $e) {
    setAlert('Erro ao carregar dados da solicitação', 'error');
    redirect('/cliente/meus_agendamentos.php');
}

$status_labels = [
    'pendente' => 'Aguardando Resposta',
    'aceita' => 'Aceita',
    'recusada' => 'Recusada',
    'em_andamento' => 'Em Andamento',
    'concluida' => 'Concluída',
    'cancelada' => 'Cancelada'
];

$status_classes = [
    'pendente' => 'badge-warning',
    'aceita' => 'badge-info',
    'recusada' => 'badge-error',
    'em_andamento' => 'badge-success',
    'concluida' => 'badge-success',
    'cancelada' => 'badge-secondary'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Agendamento - <?= SITE_NAME ?></title>
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
                    <h1>Detalhes do Agendamento</h1>
                    <p>Informações completas da sua solicitação</p>
                </div>
                <a href="meus_agendamentos.php" class="btn btn-secondary">← Voltar</a>
            </div>
        </div>

        <div class="row">
            <!-- Informações Principais -->
            <div class="col-md-8">
                <!-- Status Atual -->
                <div class="card">
                    <div class="card-header">
                        <h3>Status Atual</h3>
                    </div>
                    <div class="status-atual">
                        <div class="status-info">
                            <span class="badge <?= $status_classes[$solicitacao['status']] ?> badge-large">
                                <?= $status_labels[$solicitacao['status']] ?>
                            </span>
                            <div class="status-detalhes">
                                <?php if ($solicitacao['status'] === 'pendente'): ?>
                                    <p>Sua solicitação foi enviada e está aguardando resposta do prestador.</p>
                                <?php elseif ($solicitacao['status'] === 'aceita'): ?>
                                    <p>Sua solicitação foi aceita! O prestador comparecerá no local e horário combinados.</p>
                                <?php elseif ($solicitacao['status'] === 'recusada'): ?>
                                    <p>Infelizmente sua solicitação foi recusada pelo prestador.</p>
                                <?php elseif ($solicitacao['status'] === 'em_andamento'): ?>
                                    <p>O atendimento está em andamento. O prestador iniciou o serviço.</p>
                                <?php elseif ($solicitacao['status'] === 'concluida'): ?>
                                    <p>Atendimento concluído com sucesso!</p>
                                <?php elseif ($solicitacao['status'] === 'cancelada'): ?>
                                    <p>Esta solicitação foi cancelada.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações do Serviço -->
                <div class="card">
                    <div class="card-header">
                        <h3>Informações do Serviço</h3>
                    </div>
                    <div class="detalhes-grid">
                        <div class="detalhe-item">
                            <strong>Serviço:</strong>
                            <span><?= htmlspecialchars($solicitacao['nome_servico']) ?></span>
                        </div>
                        <div class="detalhe-item">
                            <strong>Categoria:</strong>
                            <span><?= htmlspecialchars($solicitacao['categoria']) ?></span>
                        </div>
                        <div class="detalhe-item">
                            <strong>Tipo:</strong>
                            <span><?= ucfirst($solicitacao['tipo_agendamento']) ?></span>
                        </div>
                        <div class="detalhe-item">
                            <strong>Data de Início:</strong>
                            <span><?= formatDateBR($solicitacao['data_inicio']) ?></span>
                        </div>
                        <?php if ($solicitacao['data_fim']): ?>
                            <div class="detalhe-item">
                                <strong>Data de Fim:</strong>
                                <span><?= formatDateBR($solicitacao['data_fim']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($solicitacao['horario_inicio']): ?>
                            <div class="detalhe-item">
                                <strong>Horário:</strong>
                                <span>
                                    <?= date('H:i', strtotime($solicitacao['horario_inicio'])) ?>
                                    <?php if ($solicitacao['horario_fim']): ?>
                                        às <?= date('H:i', strtotime($solicitacao['horario_fim'])) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ($solicitacao['valor_total']): ?>
                            <div class="detalhe-item">
                                <strong>Valor Total:</strong>
                                <span class="valor-destaque"><?= formatMoney($solicitacao['valor_total']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Endereço do Atendimento -->
                <div class="card">
                    <div class="card-header">
                        <h3>Local do Atendimento</h3>
                    </div>
                    <div class="endereco-completo">
                        <div class="endereco-principal">
                            <?= htmlspecialchars($solicitacao['rua'] . ', ' . $solicitacao['numero']) ?>
                            <?php if ($solicitacao['complemento']): ?>
                                - <?= htmlspecialchars($solicitacao['complemento']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="endereco-secundario">
                            <?= htmlspecialchars($solicitacao['bairro'] . ', ' . $solicitacao['cidade'] . ' - ' . $solicitacao['estado']) ?>
                        </div>
                        <div class="endereco-cep">
                            CEP: <?= formatCEP($solicitacao['cep']) ?>
                        </div>
                        <?php if ($solicitacao['referencia']): ?>
                            <div class="endereco-referencia">
                                <strong>Referência:</strong> <?= htmlspecialchars($solicitacao['referencia']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Observações -->
                <?php if ($solicitacao['observacoes_cliente'] || $solicitacao['observacoes_prestador'] || $solicitacao['motivo_recusa']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Observações</h3>
                        </div>
                        <div class="observacoes-lista">
                            <?php if ($solicitacao['observacoes_cliente']): ?>
                                <div class="observacao-item">
                                    <strong>📝 Suas Observações:</strong>
                                    <p><?= nl2br(htmlspecialchars($solicitacao['observacoes_cliente'])) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($solicitacao['observacoes_prestador']): ?>
                                <div class="observacao-item">
                                    <strong>💬 Observações do Prestador:</strong>
                                    <p><?= nl2br(htmlspecialchars($solicitacao['observacoes_prestador'])) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($solicitacao['motivo_recusa']): ?>
                                <div class="observacao-item recusa">
                                    <strong>❌ Motivo da Recusa:</strong>
                                    <p><?= nl2br(htmlspecialchars($solicitacao['motivo_recusa'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Avaliação -->
                <?php if ($solicitacao['status'] === 'concluida'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Avaliação do Serviço</h3>
                        </div>
                        <?php if ($solicitacao['avaliacao_nota']): ?>
                            <div class="avaliacao-existente">
                                <div class="avaliacao-nota">
                                    <span class="nota-numero"><?= $solicitacao['avaliacao_nota'] ?></span>
                                    <div class="estrelas">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?= $i <= $solicitacao['avaliacao_nota'] ? '⭐' : '☆' ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="avaliacao-comentario">
                                    <p><?= nl2br(htmlspecialchars($solicitacao['avaliacao_comentario'])) ?></p>
                                    <small>Avaliado em <?= formatDateTimeBR($solicitacao['data_avaliacao']) ?></small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="avaliacao-pendente">
                                <p>Que tal avaliar este atendimento? Sua opinião é muito importante!</p>
                                <a href="avaliar_servico.php?id=<?= $solicitacao['id'] ?>" class="btn btn-warning">
                                    ⭐ Avaliar Serviço
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Histórico -->
                <div class="card">
                    <div class="card-header">
                        <h3>Histórico da Solicitação</h3>
                    </div>
                    <div class="timeline">
                        <?php foreach ($historico as $item): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <strong><?= $status_labels[$item['status_novo']] ?></strong>
                                        <span class="timeline-date"><?= formatDateTimeBR($item['data_alteracao']) ?></span>
                                    </div>
                                    <?php if ($item['observacao']): ?>
                                        <p><?= htmlspecialchars($item['observacao']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($item['usuario_nome']): ?>
                                        <small>Por: <?= htmlspecialchars($item['usuario_nome']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Informações do Prestador -->
                <div class="card sticky-card">
                    <div class="card-header">
                        <h3>Prestador</h3>
                    </div>
                    <div class="prestador-info-detalhes">
                        <div class="prestador-nome">
                            <strong><?= htmlspecialchars($solicitacao['prestador_nome']) ?></strong>
                        </div>
                        <?php if ($solicitacao['formacao']): ?>
                            <div class="prestador-formacao">
                                🎓 <?= htmlspecialchars($solicitacao['formacao']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($solicitacao['registro_profissional']): ?>
                            <div class="prestador-registro">
                                📋 <?= htmlspecialchars($solicitacao['registro_profissional']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($solicitacao['anos_experiencia']): ?>
                            <div class="prestador-experiencia">
                                📅 <?= $solicitacao['anos_experiencia'] ?> anos de experiência
                            </div>
                        <?php endif; ?>
                        <div class="prestador-contato">
                            📞 <?= formatPhone($solicitacao['prestador_telefone']) ?>
                        </div>
                        <div class="prestador-email">
                            ✉️ <?= htmlspecialchars($solicitacao['prestador_email']) ?>
                        </div>
                    </div>
                    <div class="prestador-acoes">
                        <a href="prestador_detalhes.php?id=<?= $solicitacao['prestador_id'] ?>" 
                           class="btn btn-primary btn-block">
                            Ver Perfil Completo
                        </a>
                    </div>
                </div>

                <!-- Datas Importantes -->
                <div class="card">
                    <div class="card-header">
                        <h3>Datas Importantes</h3>
                    </div>
                    <div class="datas-importantes">
                        <div class="data-item">
                            <strong>Solicitado em:</strong>
                            <span><?= formatDateTimeBR($solicitacao['data_solicitacao']) ?></span>
                        </div>
                        <?php if ($solicitacao['data_resposta']): ?>
                            <div class="data-item">
                                <strong>Respondido em:</strong>
                                <span><?= formatDateTimeBR($solicitacao['data_resposta']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($solicitacao['data_conclusao']): ?>
                            <div class="data-item">
                                <strong>Concluído em:</strong>
                                <span><?= formatDateTimeBR($solicitacao['data_conclusao']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="data-item">
                            <strong>Última atualização:</strong>
                            <span><?= formatDateTimeBR($solicitacao['data_atualizacao']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Ações Disponíveis -->
                <?php if (in_array($solicitacao['status'], ['pendente', 'aceita'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Ações</h3>
                        </div>
                        <div class="acoes-disponiveis">
                            <form method="POST" action="meus_agendamentos.php" 
                                  onsubmit="return confirm('Tem certeza que deseja cancelar esta solicitação?')">
                                <input type="hidden" name="action" value="cancelar">
                                <input type="hidden" name="solicitacao_id" value="<?= $solicitacao['id'] ?>">
                                <button type="submit" class="btn btn-error btn-block">
                                    🚫 Cancelar Solicitação
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>