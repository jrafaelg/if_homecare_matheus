<?php
require_once '../config/config.php';
checkUserType(USER_ADMIN);

$db = getDB();
$user = getLoggedUser();

if (!isset($_GET['id'])) {
    setAlert('Usuário não encontrado.', 'error');
    redirect('/admin/usuarios.php');
}

$id = (int)$_GET['id'];

try {
    // Buscar dados do usuário
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        setAlert('Usuário não encontrado.', 'error');
        redirect('/admin/usuarios.php');
    }

    // Buscar endereços
    $stmt = $db->prepare("SELECT * FROM enderecos WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $enderecos = $stmt->fetchAll();

    // Buscar perfil do prestador se for prestador
    $perfil_prestador = null;
    $servicos_prestador = [];
    if ($usuario['tipo_usuario'] === 'prestador') {
        $stmt = $db->prepare("SELECT * FROM perfil_prestador WHERE prestador_id = ?");
        $stmt->execute([$id]);
        $perfil_prestador = $stmt->fetch();

        // Buscar serviços oferecidos
        $stmt = $db->prepare("
            SELECT ps.*, s.nome_servico, s.categoria
            FROM prestador_servicos ps
            JOIN servicos s ON ps.servico_id = s.id
            WHERE ps.prestador_id = ?
        ");
        $stmt->execute([$id]);
        $servicos_prestador = $stmt->fetchAll();
    }

    // Buscar solicitações
    if ($usuario['tipo_usuario'] === 'cliente') {
        $stmt = $db->prepare("
            SELECT s.*, p.nome as prestador_nome, srv.nome_servico
            FROM solicitacoes s
            JOIN usuarios p ON s.prestador_id = p.id
            JOIN servicos srv ON s.servico_id = srv.id
            WHERE s.cliente_id = ?
            ORDER BY s.data_solicitacao DESC
            LIMIT 10
        ");
        $stmt->execute([$id]);
        $solicitacoes = $stmt->fetchAll();
    } elseif ($usuario['tipo_usuario'] === 'prestador') {
        $stmt = $db->prepare("
            SELECT s.*, c.nome as cliente_nome, srv.nome_servico
            FROM solicitacoes s
            JOIN usuarios c ON s.cliente_id = c.id
            JOIN servicos srv ON s.servico_id = srv.id
            WHERE s.prestador_id = ?
            ORDER BY s.data_solicitacao DESC
            LIMIT 10
        ");
        $stmt->execute([$id]);
        $solicitacoes = $stmt->fetchAll();
    }

    // Buscar avaliações se for prestador
    $avaliacoes = [];
    if ($usuario['tipo_usuario'] === 'prestador') {
        $stmt = $db->prepare("
            SELECT a.*, c.nome as cliente_nome, s.data_inicio
            FROM avaliacoes a
            JOIN usuarios c ON a.cliente_id = c.id
            JOIN solicitacoes s ON a.solicitacao_id = s.id
            WHERE a.prestador_id = ?
            ORDER BY a.data_avaliacao DESC
            LIMIT 10
        ");
        $stmt->execute([$id]);
        $avaliacoes = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    setAlert('Erro ao buscar dados do usuário.', 'error');
    redirect('/admin/usuarios.php');
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Usuário - Administrador</title>
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
            <li><a href="usuarios.php" class="active">👥 Usuários</a></li>
            <li><a href="servicos.php">🏥 Serviços</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="relatorios.php">📈 Relatórios</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header d-flex justify-between align-center">
            <div>
                <h1>Detalhes do Usuário</h1>
                <p>Informações completas sobre o usuário</p>
            </div>
            <a href="usuarios.php" class="btn btn-secondary">← Voltar</a>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Dados Principais -->
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>Informações Pessoais</h3>
                <div style="display: flex; gap: 0.5rem;">
                        <span class="badge <?= $usuario['status'] === 'ativo' ? 'badge-success' : 'badge-error' ?>">
                            <?= ucfirst($usuario['status']) ?>
                        </span>
                    <?php
                    $tipo_badges = [
                        'admin' => ['class' => 'badge-error', 'label' => 'Administrador'],
                        'prestador' => ['class' => 'badge-info', 'label' => 'Prestador'],
                        'cliente' => ['class' => 'badge-secondary', 'label' => 'Cliente']
                    ];
                    $badge = $tipo_badges[$usuario['tipo_usuario']];
                    ?>
                    <span class="badge <?= $badge['class'] ?>">
                            <?= $badge['label'] ?>
                        </span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div>
                    <strong>Nome Completo:</strong><br>
                    <?= htmlspecialchars($usuario['nome']) ?>
                </div>
                <div>
                    <strong>Email:</strong><br>
                    <?= htmlspecialchars($usuario['email']) ?>
                </div>
                <div>
                    <strong>CPF:</strong><br>
                    <?= formatCPF($usuario['cpf']) ?>
                </div>
                <div>
                    <strong>Telefone:</strong><br>
                    <?= formatPhone($usuario['telefone']) ?>
                </div>
                <div>
                    <strong>Data de Cadastro:</strong><br>
                    <?= formatDateTimeBR($usuario['data_cadastro']) ?>
                </div>
                <div>
                    <strong>Última Atualização:</strong><br>
                    <?= formatDateTimeBR($usuario['data_atualizacao']) ?>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                <a href="usuario_form.php?id=<?= $usuario['id'] ?>" class="btn btn-primary">✏️ Editar Usuário</a>
            </div>
        </div>

        <!-- Perfil do Prestador -->
        <?php if ($usuario['tipo_usuario'] === 'prestador' && $perfil_prestador): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Perfil Profissional</h3>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div>
                        <strong>Anos de Experiência:</strong><br>
                        <?= $perfil_prestador['anos_experiencia'] ?? '-' ?> anos
                    </div>
                    <div>
                        <strong>Formação:</strong><br>
                        <?= htmlspecialchars($perfil_prestador['formacao'] ?? '-') ?>
                    </div>
                    <div>
                        <strong>Registro Profissional:</strong><br>
                        <?= htmlspecialchars($perfil_prestador['registro_profissional'] ?? '-') ?>
                    </div>
                    <div>
                        <strong>Média de Avaliações:</strong><br>
                        ⭐ <?= number_format($perfil_prestador['media_avaliacoes'], 1) ?>
                        (<?= $perfil_prestador['total_avaliacoes'] ?> avaliações)
                    </div>
                    <div>
                        <strong>Total de Atendimentos:</strong><br>
                        <?= $perfil_prestador['total_atendimentos'] ?> atendimentos
                    </div>
                    <div>
                        <strong>Raio de Atendimento:</strong><br>
                        <?= $perfil_prestador['raio_atendimento'] ?? '-' ?> km
                    </div>
                </div>

                <?php if ($perfil_prestador['descricao_profissional']): ?>
                    <div style="margin-top: 1.5rem;">
                        <strong>Descrição Profissional:</strong><br>
                        <p><?= nl2br(htmlspecialchars($perfil_prestador['descricao_profissional'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($perfil_prestador['especialidades']): ?>
                    <div style="margin-top: 1rem;">
                        <strong>Especialidades:</strong><br>
                        <p><?= nl2br(htmlspecialchars($perfil_prestador['especialidades'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Serviços Oferecidos -->
            <?php if (!empty($servicos_prestador)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Serviços Oferecidos</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                            <tr>
                                <th>Serviço</th>
                                <th>Categoria</th>
                                <th>Preço/Hora</th>
                                <th>Preço/Diária</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($servicos_prestador as $servico): ?>
                                <tr>
                                    <td><?= htmlspecialchars($servico['nome_servico']) ?></td>
                                    <td><?= htmlspecialchars($servico['categoria']) ?></td>
                                    <td><?= $servico['preco_hora'] ? formatMoney($servico['preco_hora']) : '-' ?></td>
                                    <td><?= $servico['preco_diaria'] ? formatMoney($servico['preco_diaria']) : '-' ?></td>
                                    <td>
                                        <span class="badge <?= $servico['status'] === 'ativo' ? 'badge-success' : 'badge-error' ?>">
                                            <?= ucfirst($servico['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Avaliações Recebidas -->
            <?php if (!empty($avaliacoes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Últimas Avaliações Recebidas</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Data Serviço</th>
                                <th>Nota</th>
                                <th>Comentário</th>
                                <th>Data Avaliação</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($avaliacoes as $avaliacao): ?>
                                <tr>
                                    <td><?= htmlspecialchars($avaliacao['cliente_nome']) ?></td>
                                    <td><?= formatDateBR($avaliacao['data_inicio']) ?></td>
                                    <td>
                                        <span style="color: #f59e0b; font-weight: bold;">
                                            <?= str_repeat('⭐', $avaliacao['nota']) ?> (<?= $avaliacao['nota'] ?>)
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(substr($avaliacao['comentario'], 0, 50)) ?>...</td>
                                    <td><?= formatDateBR($avaliacao['data_avaliacao']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Endereços -->
        <?php if (!empty($enderecos)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Endereços Cadastrados</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                        <tr>
                            <th>Endereço</th>
                            <th>Bairro</th>
                            <th>Cidade/UF</th>
                            <th>CEP</th>
                            <th>Principal</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($enderecos as $endereco): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($endereco['rua']) ?>, <?= htmlspecialchars($endereco['numero']) ?>
                                    <?= $endereco['complemento'] ? ' - ' . htmlspecialchars($endereco['complemento']) : '' ?>
                                </td>
                                <td><?= htmlspecialchars($endereco['bairro']) ?></td>
                                <td><?= htmlspecialchars($endereco['cidade']) ?>/<?= htmlspecialchars($endereco['estado']) ?></td>
                                <td><?= formatCEP($endereco['cep']) ?></td>
                                <td>
                                    <?php if ($endereco['principal']): ?>
                                        <span class="badge badge-success">Principal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Histórico de Solicitações -->
        <?php if (!empty($solicitacoes)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Últimas Solicitações (<?= $usuario['tipo_usuario'] === 'cliente' ? 'Contratadas' : 'Recebidas' ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                        <tr>
                            <th><?= $usuario['tipo_usuario'] === 'cliente' ? 'Prestador' : 'Cliente' ?></th>
                            <th>Serviço</th>
                            <th>Data Início</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($solicitacoes as $solicitacao): ?>
                            <tr>
                                <td><?= htmlspecialchars($usuario['tipo_usuario'] === 'cliente' ? $solicitacao['prestador_nome'] : $solicitacao['cliente_nome']) ?></td>
                                <td><?= htmlspecialchars($solicitacao['nome_servico']) ?></td>
                                <td><?= formatDateBR($solicitacao['data_inicio']) ?></td>
                                <td>
                                        <span class="badge badge-secondary">
                                            <?= ucfirst($solicitacao['tipo_agendamento']) ?>
                                        </span>
                                </td>
                                <td><?= $solicitacao['valor_total'] ? formatMoney($solicitacao['valor_total']) : '-' ?></td>
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
                                    ?>
                                    <span class="badge <?= $status_class[$solicitacao['status']] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $solicitacao['status'])) ?>
                                        </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>