<?php
require_once '../config/config.php';
checkUserType(USER_PRESTADOR);
$db = getDB();
$user = getLoggedUser();

// Parâmetros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$status_filtro = isset($_GET['status']) ? sanitize($_GET['status']) : 'todos';

// Validar mês e ano
if ($mes < 1 || $mes > 12) $mes = (int)date('n');
if ($ano < 2020 || $ano > 2030) $ano = (int)date('Y');

// Nomes dos meses
$meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
          'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// Calcular datas
$primeiro_dia = mktime(0, 0, 0, $mes, 1, $ano);
$ultimo_dia = mktime(0, 0, 0, $mes + 1, 0, $ano);
$dias_no_mes = date('t', $primeiro_dia);
$dia_semana_inicio = date('w', $primeiro_dia);

// Buscar agendamentos do mês
$params = [$user['id'], $mes, $ano];
$status_condition = "s.status IN ('aceita', 'em_andamento', 'concluida')";

if ($status_filtro !== 'todos') {
    $status_condition = "s.status = ?";
    $params[] = $status_filtro;
}

try {
    $stmt = $db->prepare("
        SELECT s.*, 
               u.nome as cliente_nome, 
               u.telefone as cliente_telefone,
               srv.nome_servico,
               e.rua, e.numero, e.bairro, e.cidade, e.complemento, e.referencia
        FROM solicitacoes s
        INNER JOIN usuarios u ON s.cliente_id = u.id
        INNER JOIN servicos srv ON s.servico_id = srv.id
        INNER JOIN enderecos e ON s.endereco_id = e.id
        WHERE s.prestador_id = ?
        AND MONTH(s.data_inicio) = ?
        AND YEAR(s.data_inicio) = ?
        AND $status_condition
        ORDER BY s.data_inicio, s.horario_inicio
    ");
    $stmt->execute($params);
    $agendamentos = $stmt->fetchAll();
} catch (PDOException $e) {
    $agendamentos = [];
    $error_message = "Erro ao carregar agendamentos: " . $e->getMessage();
}

// Organizar agendamentos por dia
$agendamentos_por_dia = [];
foreach ($agendamentos as $ag) {
    $dia = (int)date('j', strtotime($ag['data_inicio']));
    if (!isset($agendamentos_por_dia[$dia])) {
        $agendamentos_por_dia[$dia] = [];
    }
    $agendamentos_por_dia[$dia][] = $ag;
}

// Buscar bloqueios
try {
    $stmt = $db->prepare("
        SELECT * FROM bloqueios_agenda
        WHERE prestador_id = ?
        AND ((MONTH(data_inicio) = ? AND YEAR(data_inicio) = ?)
             OR (MONTH(data_fim) = ? AND YEAR(data_fim) = ?)
             OR (data_inicio <= ? AND data_fim >= ?))
    ");
    $primeiro_dia_mes = sprintf('%04d-%02d-01', $ano, $mes);
    $ultimo_dia_mes = date('Y-m-t', strtotime($primeiro_dia_mes));
    $stmt->execute([$user['id'], $mes, $ano, $mes, $ano, $ultimo_dia_mes, $primeiro_dia_mes]);
    $bloqueios = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tabela pode não existir ainda
    $bloqueios = [];
}

// Organizar bloqueios por dia
$dias_bloqueados = [];
foreach ($bloqueios as $bloqueio) {
    $inicio = strtotime($bloqueio['data_inicio']);
    $fim = strtotime($bloqueio['data_fim']);
    
    for ($d = $inicio; $d <= $fim; $d += 86400) {
        if (date('n', $d) == $mes && date('Y', $d) == $ano) {
            $dia = (int)date('j', $d);
            $dias_bloqueados[$dia] = true;
        }
    }
}

// Estatísticas
try {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(COUNT(*), 0) as total_mes,
            COALESCE(SUM(CASE WHEN WEEK(data_inicio) = WEEK(CURDATE()) THEN 1 ELSE 0 END), 0) as total_semana,
            COALESCE(SUM(CASE WHEN data_inicio BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) as proximos_7
        FROM solicitacoes
        WHERE prestador_id = ?
        AND MONTH(data_inicio) = ?
        AND YEAR(data_inicio) = ?
        AND status IN ('aceita', 'em_andamento', 'concluida')
    ");
    $stmt->execute([$user['id'], $mes, $ano]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total_mes' => 0, 'total_semana' => 0, 'proximos_7' => 0];
}

// Próximos agendamentos
try {
    $stmt = $db->prepare("
        SELECT s.*, u.nome as cliente_nome, srv.nome_servico
        FROM solicitacoes s
        INNER JOIN usuarios u ON s.cliente_id = u.id
        INNER JOIN servicos srv ON s.servico_id = srv.id
        WHERE s.prestador_id = ?
        AND s.status IN ('aceita', 'em_andamento')
        AND s.data_inicio >= CURDATE()
        ORDER BY s.data_inicio, s.horario_inicio
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $proximos = $stmt->fetchAll();
} catch (PDOException $e) {
    $proximos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Agenda - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><?= SITE_NAME ?></h3>
            <p>Prestador</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php">📊 Dashboard</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="servicos.php">🏥 Meus Serviços</a></li>
            <li><a href="avaliacoes.php">⭐ Avaliações</a></li>
            <li><a href="perfil.php">👤 Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>📅 Minha Agenda</h1>
            <p>Gerencie seus agendamentos e disponibilidade</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_mes'] ?? 0 ?></div>
                <div class="stat-label">Agendamentos do Mês</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_semana'] ?? 0 ?></div>
                <div class="stat-label">Esta Semana</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['proximos_7'] ?? 0 ?></div>
                <div class="stat-label">Próximos 7 Dias</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Controles -->
                <div class="card">
                    <div class="agenda-controls">
                        <button onclick="mesAnterior()" class="btn btn-secondary">← Anterior</button>
                        <h3><?= $meses[$mes] ?> <?= $ano ?></h3>
                        <button onclick="proximoMes()" class="btn btn-secondary">Próximo →</button>
                        <select id="filtro-status" onchange="aplicarFiltro()">
                            <option value="todos" <?= $status_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="aceita" <?= $status_filtro === 'aceita' ? 'selected' : '' ?>>Aceitos</option>
                            <option value="em_andamento" <?= $status_filtro === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                            <option value="concluida" <?= $status_filtro === 'concluida' ? 'selected' : '' ?>>Concluídos</option>
                        </select>
                        <button onclick="window.print()" class="btn btn-info">🖨️ Imprimir</button>
                    </div>

                    <!-- Calendário -->
                    <div class="calendario">
                        <div class="calendario-header">
                            <div>Dom</div><div>Seg</div><div>Ter</div>
                            <div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
                        </div>
                        <div class="calendario-body">
                            <?php
                            // Dias vazios antes do primeiro dia
                            for ($i = 0; $i < $dia_semana_inicio; $i++) {
                                echo '<div class="dia dia-vazio"></div>';
                            }
                            
                            // Dias do mês
                            $hoje = date('Y-m-d');
                            for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
                                $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                                $classes = ['dia'];
                                
                                if ($data == $hoje) $classes[] = 'dia-atual';
                                if ($data < $hoje) $classes[] = 'dia-passado';
                                if (isset($agendamentos_por_dia[$dia])) $classes[] = 'dia-com-agendamento';
                                if (isset($dias_bloqueados[$dia])) $classes[] = 'dia-bloqueado';
                                
                                $count = isset($agendamentos_por_dia[$dia]) ? count($agendamentos_por_dia[$dia]) : 0;
                                
                                echo '<div class="' . implode(' ', $classes) . '" data-date="' . $data . '" onclick="verDia(\'' . $data . '\')">';
                                echo '<span class="dia-numero">' . $dia . '</span>';
                                if ($count > 0) {
                                    echo '<span class="dia-indicador">' . $count . '</span>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Próximos Agendamentos -->
                <div class="card">
                    <div class="card-header">
                        <h3>Próximos Agendamentos</h3>
                    </div>
                    <div class="proximos-agendamentos">
                        <?php if (empty($proximos)): ?>
                            <p class="text-center text-muted">Nenhum agendamento próximo</p>
                        <?php else: ?>
                            <?php foreach ($proximos as $prox): ?>
                                <div class="agendamento-item">
                                    <div class="agendamento-data">
                                        <?= date('d/m', strtotime($prox['data_inicio'])) ?>
                                    </div>
                                    <div class="agendamento-info">
                                        <strong><?= substr($prox['horario_inicio'], 0, 5) ?></strong>
                                        <p><?= htmlspecialchars($prox['cliente_nome']) ?></p>
                                        <small><?= htmlspecialchars($prox['nome_servico']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Legenda -->
                <div class="card">
                    <div class="card-header">
                        <h3>Legenda</h3>
                    </div>
                    <div class="legenda">
                        <div class="legenda-item">
                            <span class="legenda-cor dia-atual"></span>
                            <span>Hoje</span>
                        </div>
                        <div class="legenda-item">
                            <span class="legenda-cor dia-com-agendamento"></span>
                            <span>Com agendamento</span>
                        </div>
                        <div class="legenda-item">
                            <span class="legenda-cor dia-bloqueado"></span>
                            <span>Bloqueado</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal -->
<div id="modal-agendamentos" class="modal" style="display:none">
    <div class="modal-content">
        <span class="modal-close" onclick="fecharModal()">&times;</span>
        <div id="modal-body"></div>
    </div>
</div>

<input type="hidden" id="mes-atual" value="<?= $mes ?>">
<input type="hidden" id="ano-atual" value="<?= $ano ?>">

<script>
function mesAnterior() {
    let mes = parseInt(document.getElementById('mes-atual').value);
    let ano = parseInt(document.getElementById('ano-atual').value);
    mes--;
    if (mes < 1) { mes = 12; ano--; }
    window.location.href = `agenda.php?mes=${mes}&ano=${ano}&status=<?= $status_filtro ?>`;
}

function proximoMes() {
    let mes = parseInt(document.getElementById('mes-atual').value);
    let ano = parseInt(document.getElementById('ano-atual').value);
    mes++;
    if (mes > 12) { mes = 1; ano++; }
    window.location.href = `agenda.php?mes=${mes}&ano=${ano}&status=<?= $status_filtro ?>`;
}

function aplicarFiltro() {
    const status = document.getElementById('filtro-status').value;
    const mes = document.getElementById('mes-atual').value;
    const ano = document.getElementById('ano-atual').value;
    window.location.href = `agenda.php?mes=${mes}&ano=${ano}&status=${status}`;
}

function verDia(data) {
    fetch(`agenda_ajax.php?action=dia&data=${data}`)
        .then(response => response.json())
        .then(dados => mostrarModal(dados))
        .catch(error => console.error('Erro:', error));
}

function mostrarModal(dados) {
    const modal = document.getElementById('modal-agendamentos');
    const body = document.getElementById('modal-body');
    
    let html = '<h3>Agendamentos de ' + formatarData(dados.data) + '</h3>';
    
    if (dados.agendamentos.length === 0) {
        html += '<p class="text-center">Nenhum agendamento neste dia</p>';
    } else {
        dados.agendamentos.forEach(ag => {
            html += `
                <div class="agendamento-detalhe">
                    <div class="horario-badge">${formatarHorario(ag.horario_inicio)} - ${ag.horario_fim ? formatarHorario(ag.horario_fim) : 'Fim do dia'}</div>
                    <div class="info-section">
                        <h4>Cliente</h4>
                        <p><strong>${ag.cliente_nome}</strong></p>
                        <p>📞 ${ag.cliente_telefone || 'Não informado'}</p>
                    </div>
                    <div class="info-section">
                        <h4>Serviço</h4>
                        <p>${ag.nome_servico}</p>
                        <p>Tipo: ${ag.tipo_agendamento === 'hora' ? 'Por hora' : 'Diária'}</p>
                    </div>
                    <div class="info-section">
                        <h4>Endereço</h4>
                        <p>${ag.rua}, ${ag.numero} - ${ag.bairro}</p>
                        <p>${ag.cidade}</p>
                        ${ag.referencia ? '<p>Ref: ' + ag.referencia + '</p>' : ''}
                        <a href="https://maps.google.com/?q=${encodeURIComponent(ag.rua + ', ' + ag.numero + ', ' + ag.cidade)}" target="_blank" class="btn btn-sm btn-info">
                            📍 Abrir no Google Maps
                        </a>
                    </div>
                    ${ag.observacoes_cliente ? '<div class="info-section"><h4>Observações</h4><p>' + ag.observacoes_cliente + '</p></div>' : ''}
                    <div class="status-badge status-${ag.status}">${getStatusLabel(ag.status)}</div>
                </div>
            `;
        });
    }
    
    body.innerHTML = html;
    modal.style.display = 'block';
}

function fecharModal() {
    document.getElementById('modal-agendamentos').style.display = 'none';
}

function formatarData(data) {
    const partes = data.split('-');
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

function formatarHorario(horario) {
    if (!horario) return '';
    // Se vier no formato HH:MM:SS, pegar apenas HH:MM
    if (horario.length > 5) {
        return horario.substring(0, 5);
    }
    return horario;
}

function getStatusLabel(status) {
    const labels = {
        'aceita': 'Aceito',
        'em_andamento': 'Em Andamento',
        'concluida': 'Concluído'
    };
    return labels[status] || status;
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('modal-agendamentos');
    if (event.target == modal) {
        fecharModal();
    }
}
</script>

<style>
@media print {
    .sidebar, .page-header p, .agenda-controls button, .proximos-agendamentos, .legenda { display: none !important; }
    .main-content { margin-left: 0 !important; }
}
</style>
</body>
</html>
