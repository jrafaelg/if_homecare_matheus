<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user = getLoggedUser();

$prestador_id = isset($_GET['prestador']) ? (int)$_GET['prestador'] : 0;
$servico_id = isset($_GET['servico']) ? (int)$_GET['servico'] : 0;

if (!$prestador_id) {
    setAlert('Prestador não informado', 'error');
    redirect('/cliente/buscar_prestadores.php');
}

// Buscar dados do prestador e serviço
try {
    $stmt = $db->prepare("
        SELECT u.*, pp.*, ps.preco_hora, ps.preco_diaria, ps.experiencia_especifica,
               s.nome_servico, s.categoria
        FROM usuarios u
        INNER JOIN perfil_prestador pp ON u.id = pp.prestador_id
        LEFT JOIN prestador_servicos ps ON u.id = ps.prestador_id AND ps.servico_id = ?
        LEFT JOIN servicos s ON ps.servico_id = s.id
        WHERE u.id = ? AND u.tipo_usuario = 'prestador' AND u.status = 'ativo'
    ");
    $stmt->execute([$servico_id, $prestador_id]);
    $prestador = $stmt->fetch();

    if (!$prestador) {
        setAlert('Prestador não encontrado', 'error');
        redirect('/cliente/buscar_prestadores.php');
    }

    // Buscar todos os serviços do prestador se não foi especificado um
    if (!$servico_id) {
        $stmt = $db->prepare("
            SELECT ps.*, s.nome_servico, s.categoria
            FROM prestador_servicos ps
            INNER JOIN servicos s ON ps.servico_id = s.id
            WHERE ps.prestador_id = ? AND ps.status = 'ativo' AND s.status = 'ativo'
            ORDER BY s.nome_servico
        ");
        $stmt->execute([$prestador_id]);
        $servicos_disponiveis = $stmt->fetchAll();
    }

    // Buscar endereços do cliente
    $stmt = $db->prepare("
        SELECT * FROM enderecos 
        WHERE usuario_id = ? 
        ORDER BY principal DESC, id DESC
    ");
    $stmt->execute([$user['id']]);
    $enderecos = $stmt->fetchAll();

} catch (PDOException $e) {
    setAlert('Erro ao carregar dados', 'error');
    redirect('/cliente/buscar_prestadores.php');
}

// Processar solicitação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servico_selecionado = (int)$_POST['servico_id'];
    $endereco_id = (int)$_POST['endereco_id'];
    $tipo_agendamento = sanitize($_POST['tipo_agendamento']);
    $data_inicio = sanitize($_POST['data_inicio']);
    $data_fim = sanitize($_POST['data_fim']);
    $horario_inicio = sanitize($_POST['horario_inicio']);
    $horario_fim = sanitize($_POST['horario_fim']);
    $observacoes = sanitize($_POST['observacoes']);

    // Validações
    $errors = [];

    if (!$servico_selecionado) {
        $errors[] = 'Selecione um serviço';
    }

    if (!$endereco_id) {
        $errors[] = 'Selecione um endereço';
    }

    if (!in_array($tipo_agendamento, ['hora', 'diaria'])) {
        $errors[] = 'Tipo de agendamento inválido';
    }

    if (empty($data_inicio)) {
        $errors[] = 'Data de início é obrigatória';
    } elseif (strtotime($data_inicio) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Data de início não pode ser no passado';
    }

    if ($tipo_agendamento === 'hora') {
        if (empty($horario_inicio) || empty($horario_fim)) {
            $errors[] = 'Horários são obrigatórios para agendamento por hora';
        } elseif (strtotime($horario_fim) <= strtotime($horario_inicio)) {
            $errors[] = 'Horário de fim deve ser posterior ao de início';
        }
    }

    if ($tipo_agendamento === 'diaria' && !empty($data_fim)) {
        if (strtotime($data_fim) < strtotime($data_inicio)) {
            $errors[] = 'Data de fim deve ser posterior à data de início';
        }
    }

    if (empty($errors)) {
        try {
            // Buscar dados do serviço para calcular valor
            $stmt = $db->prepare("
                SELECT ps.preco_hora, ps.preco_diaria
                FROM prestador_servicos ps
                WHERE ps.prestador_id = ? AND ps.servico_id = ?
            ");
            $stmt->execute([$prestador_id, $servico_selecionado]);
            $servico_preco = $stmt->fetch();

            // Calcular valor total
            $valor_total = 0;
            if ($tipo_agendamento === 'hora' && $servico_preco['preco_hora']) {
                $inicio = new DateTime($horario_inicio);
                $fim = new DateTime($horario_fim);
                $diff = $fim->diff($inicio);
                $horas = $diff->h + ($diff->i / 60);
                $valor_total = $horas * $servico_preco['preco_hora'];
            } elseif ($tipo_agendamento === 'diaria' && $servico_preco['preco_diaria']) {
                if (!empty($data_fim)) {
                    $inicio = new DateTime($data_inicio);
                    $fim = new DateTime($data_fim);
                    $diff = $fim->diff($inicio);
                    $dias = $diff->days + 1;
                } else {
                    $dias = 1;
                }
                $valor_total = $dias * $servico_preco['preco_diaria'];
            }

            // Inserir solicitação
            $stmt = $db->prepare("
                INSERT INTO solicitacoes (
                    cliente_id, prestador_id, servico_id, endereco_id,
                    tipo_agendamento, data_inicio, data_fim, 
                    horario_inicio, horario_fim, valor_total,
                    observacoes_cliente, status, data_solicitacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())
            ");

            $stmt->execute([
                $user['id'],
                $prestador_id,
                $servico_selecionado,
                $endereco_id,
                $tipo_agendamento,
                $data_inicio,
                $data_fim ?: null,
                $horario_inicio ?: null,
                $horario_fim ?: null,
                $valor_total,
                $observacoes
            ]);

            $solicitacao_id = $db->lastInsertId();

            // Inserir histórico
            $stmt = $db->prepare("
                INSERT INTO historico_solicitacoes (
                    solicitacao_id, status_anterior, status_novo, 
                    observacao, usuario_id, data_alteracao
                ) VALUES (?, NULL, 'pendente', 'Solicitação criada pelo cliente', ?, NOW())
            ");
            $stmt->execute([$solicitacao_id, $user['id']]);

            // Criar notificação para o prestador
            $stmt = $db->prepare("
                INSERT INTO notificacoes (
                    usuario_id, tipo, titulo, mensagem, link, data_criacao
                ) VALUES (?, 'nova_solicitacao', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $prestador_id,
                'Nova Solicitação de Serviço',
                'Você recebeu uma nova solicitação de serviço de ' . $user['nome'],
                '/prestador/solicitacoes.php?id=' . $solicitacao_id
            ]);

            setAlert('Solicitação enviada com sucesso! O prestador será notificado.', 'success');
            redirect('/cliente/meus_agendamentos.php');

        } catch (PDOException $e) {
            $errors[] = 'Erro ao enviar solicitação. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Serviço - <?= SITE_NAME ?></title>
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
                    <h1>Solicitar Serviço</h1>
                    <p>Preencha os dados para solicitar o serviço</p>
                </div>
                <a href="prestador_detalhes.php?id=<?= $prestador_id ?>" class="btn btn-secondary">← Voltar ao Perfil</a>
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
            <!-- Formulário -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Dados da Solicitação</h3>
                    </div>
                    <form method="POST" class="card-body" id="solicitacaoForm">
                        <!-- Prestador Info -->
                        <div class="prestador-info-card">
                            <h4>Prestador Selecionado</h4>
                            <div class="prestador-resumo">
                                <strong><?= htmlspecialchars($prestador['nome']) ?></strong>
                                <?php if ($prestador['formacao']): ?>
                                    <p><?= htmlspecialchars($prestador['formacao']) ?></p>
                                <?php endif; ?>
                                <p>📞 <?= formatPhone($prestador['telefone']) ?></p>
                                <?php if ($prestador['total_avaliacoes'] > 0): ?>
                                    <p>⭐ <?= number_format($prestador['media_avaliacoes'], 1) ?> (<?= $prestador['total_avaliacoes'] ?> avaliações)</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Seleção de Serviço -->
                        <div class="form-group">
                            <label for="servico_id">Serviço Solicitado *</label>
                            <?php if ($servico_id && $prestador['nome_servico']): ?>
                                <input type="hidden" name="servico_id" value="<?= $servico_id ?>">
                                <div class="servico-selecionado">
                                    <h4><?= htmlspecialchars($prestador['nome_servico']) ?></h4>
                                    <?php if ($prestador['experiencia_especifica']): ?>
                                        <p><?= htmlspecialchars($prestador['experiencia_especifica']) ?></p>
                                    <?php endif; ?>
                                    <div class="precos">
                                        <?php if ($prestador['preco_hora']): ?>
                                            <span class="preco"><?= formatMoney($prestador['preco_hora']) ?>/hora</span>
                                        <?php endif; ?>
                                        <?php if ($prestador['preco_diaria']): ?>
                                            <span class="preco"><?= formatMoney($prestador['preco_diaria']) ?>/diária</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <select id="servico_id" name="servico_id" required>
                                    <option value="">Selecione um serviço...</option>
                                    <?php foreach ($servicos_disponiveis as $servico): ?>
                                        <option value="<?= $servico['servico_id'] ?>" 
                                                data-preco-hora="<?= $servico['preco_hora'] ?>"
                                                data-preco-diaria="<?= $servico['preco_diaria'] ?>">
                                            <?= htmlspecialchars($servico['nome_servico']) ?>
                                            <?php if ($servico['preco_hora']): ?>
                                                - <?= formatMoney($servico['preco_hora']) ?>/hora
                                            <?php endif; ?>
                                            <?php if ($servico['preco_diaria']): ?>
                                                - <?= formatMoney($servico['preco_diaria']) ?>/diária
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Endereço -->
                        <div class="form-group">
                            <label for="endereco_id">Endereço do Atendimento *</label>
                            <?php if (empty($enderecos)): ?>
                                <div class="alert alert-warning">
                                    Você precisa cadastrar um endereço primeiro.
                                    <a href="enderecos.php" class="btn btn-primary btn-sm">Cadastrar Endereço</a>
                                </div>
                            <?php else: ?>
                                <select id="endereco_id" name="endereco_id" required>
                                    <option value="">Selecione o endereço...</option>
                                    <?php foreach ($enderecos as $endereco): ?>
                                        <option value="<?= $endereco['id'] ?>">
                                            <?= htmlspecialchars($endereco['rua'] . ', ' . $endereco['numero']) ?>
                                            <?php if ($endereco['complemento']): ?>
                                                - <?= htmlspecialchars($endereco['complemento']) ?>
                                            <?php endif; ?>
                                            - <?= htmlspecialchars($endereco['bairro'] . ', ' . $endereco['cidade']) ?>
                                            <?php if ($endereco['principal']): ?>
                                                <strong>(Principal)</strong>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Tipo de Agendamento -->
                        <div class="form-group">
                            <label>Tipo de Agendamento *</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="tipo_agendamento" value="hora" required>
                                    <span>Por Hora</span>
                                    <small>Agendamento com horário específico</small>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="tipo_agendamento" value="diaria" required>
                                    <span>Diária</span>
                                    <small>Agendamento por dia(s) completo(s)</small>
                                </label>
                            </div>
                        </div>

                        <!-- Datas -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_inicio">Data de Início *</label>
                                <input type="date" id="data_inicio" name="data_inicio" required
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= isset($_POST['data_inicio']) ? $_POST['data_inicio'] : '' ?>">
                            </div>
                            <div class="form-group" id="data_fim_group" style="display: none;">
                                <label for="data_fim">Data de Fim</label>
                                <input type="date" id="data_fim" name="data_fim"
                                       value="<?= isset($_POST['data_fim']) ? $_POST['data_fim'] : '' ?>">
                                <small>Deixe em branco para apenas um dia</small>
                            </div>
                        </div>

                        <!-- Horários -->
                        <div class="form-row" id="horarios_group" style="display: none;">
                            <div class="form-group">
                                <label for="horario_inicio">Horário de Início</label>
                                <input type="time" id="horario_inicio" name="horario_inicio"
                                       value="<?= isset($_POST['horario_inicio']) ? $_POST['horario_inicio'] : '' ?>">
                            </div>
                            <div class="form-group">
                                <label for="horario_fim">Horário de Fim</label>
                                <input type="time" id="horario_fim" name="horario_fim"
                                       value="<?= isset($_POST['horario_fim']) ? $_POST['horario_fim'] : '' ?>">
                            </div>
                        </div>

                        <!-- Observações -->
                        <div class="form-group">
                            <label for="observacoes">Observações</label>
                            <textarea id="observacoes" name="observacoes" rows="4" 
                                      placeholder="Descreva detalhes importantes sobre o atendimento, necessidades especiais, etc."><?= isset($_POST['observacoes']) ? htmlspecialchars($_POST['observacoes']) : '' ?></textarea>
                        </div>

                        <!-- Valor Estimado -->
                        <div class="valor-estimado" id="valor_estimado" style="display: none;">
                            <h4>Valor Estimado</h4>
                            <div class="valor-display">
                                <span id="valor_calculado">R$ 0,00</span>
                                <small id="valor_detalhes"></small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success btn-lg">
                                📋 Enviar Solicitação
                            </button>
                            <a href="prestador_detalhes.php?id=<?= $prestador_id ?>" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar com Informações -->
            <div class="col-md-4">
                <div class="card sticky-card">
                    <div class="card-header">
                        <h3>Como Funciona</h3>
                    </div>
                    <div class="card-body">
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Envie a Solicitação</strong>
                                    <p>Preencha os dados e envie sua solicitação</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Aguarde a Resposta</strong>
                                    <p>O prestador tem até 24h para responder</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Confirmação</strong>
                                    <p>Você será notificado sobre a resposta</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <strong>Atendimento</strong>
                                    <p>O prestador comparece no local e horário</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($prestador['disponibilidade_geral']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Disponibilidade</h3>
                        </div>
                        <div class="card-body">
                            <p><?= nl2br(htmlspecialchars($prestador['disponibilidade_geral'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoAgendamento = document.querySelectorAll('input[name="tipo_agendamento"]');
    const horariosGroup = document.getElementById('horarios_group');
    const dataFimGroup = document.getElementById('data_fim_group');
    const valorEstimado = document.getElementById('valor_estimado');
    
    // Controlar exibição de campos baseado no tipo
    tipoAgendamento.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'hora') {
                horariosGroup.style.display = 'flex';
                dataFimGroup.style.display = 'none';
                document.getElementById('horario_inicio').required = true;
                document.getElementById('horario_fim').required = true;
                document.getElementById('data_fim').required = false;
            } else {
                horariosGroup.style.display = 'none';
                dataFimGroup.style.display = 'block';
                document.getElementById('horario_inicio').required = false;
                document.getElementById('horario_fim').required = false;
                document.getElementById('data_fim').required = false;
            }
            calcularValor();
        });
    });

    // Calcular valor estimado
    function calcularValor() {
        const tipo = document.querySelector('input[name="tipo_agendamento"]:checked');
        const servicoSelect = document.getElementById('servico_id');
        const dataInicio = document.getElementById('data_inicio').value;
        const dataFim = document.getElementById('data_fim').value;
        const horarioInicio = document.getElementById('horario_inicio').value;
        const horarioFim = document.getElementById('horario_fim').value;

        if (!tipo || !servicoSelect) return;

        let precoHora = 0;
        let precoDiaria = 0;

        // Pegar preços do serviço selecionado
        <?php if ($servico_id && $prestador['preco_hora']): ?>
            precoHora = <?= $prestador['preco_hora'] ?>;
        <?php endif; ?>
        <?php if ($servico_id && $prestador['preco_diaria']): ?>
            precoDiaria = <?= $prestador['preco_diaria'] ?>;
        <?php endif; ?>

        if (!<?= $servico_id ? 'true' : 'false' ?>) {
            const selectedOption = servicoSelect.options[servicoSelect.selectedIndex];
            if (selectedOption) {
                precoHora = parseFloat(selectedOption.dataset.precoHora) || 0;
                precoDiaria = parseFloat(selectedOption.dataset.precoDiaria) || 0;
            }
        }

        let valor = 0;
        let detalhes = '';

        if (tipo.value === 'hora' && precoHora > 0 && horarioInicio && horarioFim) {
            const inicio = new Date('2000-01-01 ' + horarioInicio);
            const fim = new Date('2000-01-01 ' + horarioFim);
            const diffMs = fim - inicio;
            const horas = diffMs / (1000 * 60 * 60);
            
            if (horas > 0) {
                valor = horas * precoHora;
                detalhes = `${horas.toFixed(1)} hora(s) × R$ ${precoHora.toFixed(2)}`;
            }
        } else if (tipo.value === 'diaria' && precoDiaria > 0 && dataInicio) {
            let dias = 1;
            if (dataFim) {
                const inicio = new Date(dataInicio);
                const fim = new Date(dataFim);
                const diffTime = fim - inicio;
                dias = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            }
            
            if (dias > 0) {
                valor = dias * precoDiaria;
                detalhes = `${dias} dia(s) × R$ ${precoDiaria.toFixed(2)}`;
            }
        }

        if (valor > 0) {
            document.getElementById('valor_calculado').textContent = 
                'R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            document.getElementById('valor_detalhes').textContent = detalhes;
            valorEstimado.style.display = 'block';
        } else {
            valorEstimado.style.display = 'none';
        }
    }

    // Event listeners para recalcular valor
    document.getElementById('data_inicio').addEventListener('change', calcularValor);
    document.getElementById('data_fim').addEventListener('change', calcularValor);
    document.getElementById('horario_inicio').addEventListener('change', calcularValor);
    document.getElementById('horario_fim').addEventListener('change', calcularValor);
    
    const servicoSelect = document.getElementById('servico_id');
    if (servicoSelect) {
        servicoSelect.addEventListener('change', calcularValor);
    }

    // Validar data mínima para data_fim
    document.getElementById('data_inicio').addEventListener('change', function() {
        document.getElementById('data_fim').min = this.value;
    });
});
</script>
</body>
</html>