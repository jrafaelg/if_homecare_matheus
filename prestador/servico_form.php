<?php
require_once '../config/config.php';
checkUserType(USER_PRESTADOR);

$db = getDB();
$user = getLoggedUser();

$servico_prestador = null;
$servico = null;
$isEdit = false;

// Se for edição
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $isEdit = true;

    try {
        $stmt = $db->prepare("
            SELECT ps.*, s.nome_servico, s.categoria, s.descricao
            FROM prestador_servicos ps
            JOIN servicos s ON ps.servico_id = s.id
            WHERE ps.id = ? AND ps.prestador_id = ?
        ");
        $stmt->execute([$id, $user['id']]);
        $servico_prestador = $stmt->fetch();

        if (!$servico_prestador) {
            setAlert('Serviço não encontrado.', 'error');
            redirect('/prestador/servicos.php');
        }

        $servico = [
            'id' => $servico_prestador['servico_id'],
            'nome_servico' => $servico_prestador['nome_servico'],
            'categoria' => $servico_prestador['categoria'],
            'descricao' => $servico_prestador['descricao']
        ];

    } catch (PDOException $e) {
        setAlert('Erro ao buscar serviço.', 'error');
        redirect('/prestador/servicos.php');
    }
}
// Se for novo serviço
elseif (isset($_GET['servico_id'])) {
    $servico_id = (int)$_GET['servico_id'];

    try {
        // Verificar se o serviço existe e está ativo
        $stmt = $db->prepare("SELECT * FROM servicos WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$servico_id]);
        $servico = $stmt->fetch();

        if (!$servico) {
            setAlert('Serviço não encontrado.', 'error');
            redirect('/prestador/servicos.php');
        }

        // Verificar se já não oferece este serviço
        $stmt = $db->prepare("SELECT id FROM prestador_servicos WHERE prestador_id = ? AND servico_id = ?");
        $stmt->execute([$user['id'], $servico_id]);
        if ($stmt->fetch()) {
            setAlert('Você já oferece este serviço.', 'error');
            redirect('/prestador/servicos.php');
        }

    } catch (PDOException $e) {
        setAlert('Erro ao buscar serviço.', 'error');
        redirect('/prestador/servicos.php');
    }
} else {
    setAlert('Serviço não especificado.', 'error');
    redirect('/prestador/servicos.php');
}

$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preco_hora = !empty($_POST['preco_hora']) ? floatval(str_replace(',', '.', str_replace('.', '', $_POST['preco_hora']))) : null;
    $preco_diaria = !empty($_POST['preco_diaria']) ? floatval(str_replace(',', '.', str_replace('.', '', $_POST['preco_diaria']))) : null;
    $experiencia_especifica = sanitize($_POST['experiencia_especifica'] ?? '');
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    $status = $_POST['status'];

    // Validações
    if (!$preco_hora && !$preco_diaria) {
        $error = 'Informe pelo menos um tipo de preço (hora ou diária)';
    } elseif ($preco_hora && $preco_hora <= 0) {
        $error = 'O preço por hora deve ser maior que zero';
    } elseif ($preco_diaria && $preco_diaria <= 0) {
        $error = 'O preço por diária deve ser maior que zero';
    } elseif (!in_array($status, ['ativo', 'inativo'])) {
        $error = 'Status inválido';
    } else {
        try {
            if ($isEdit) {
                // Atualizar serviço
                $stmt = $db->prepare("
                    UPDATE prestador_servicos 
                    SET preco_hora = ?, preco_diaria = ?, experiencia_especifica = ?, observacoes = ?, status = ?
                    WHERE id = ? AND prestador_id = ?
                ");
                $stmt->execute([$preco_hora, $preco_diaria, $experiencia_especifica, $observacoes, $status, $_POST['id'], $user['id']]);

                setAlert('Serviço atualizado com sucesso!', 'success');
            } else {
                // Inserir novo serviço
                $stmt = $db->prepare("
                    INSERT INTO prestador_servicos (prestador_id, servico_id, preco_hora, preco_diaria, experiencia_especifica, observacoes, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user['id'], $servico['id'], $preco_hora, $preco_diaria, $experiencia_especifica, $observacoes, $status]);

                setAlert('Serviço adicionado com sucesso! Agora você pode receber solicitações.', 'success');
            }

            redirect('/prestador/servicos.php');

        } catch (PDOException $e) {
            $error = 'Erro ao salvar serviço. Tente novamente.';
        }
    }
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Editar' : 'Adicionar' ?> Serviço - Prestador</title>
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
            <li><a href="servicos.php" class="active">🏥 Meus Serviços</a></li>
            <li><a href="solicitacoes.php">📋 Solicitações</a></li>
            <li><a href="agenda.php">📅 Agenda</a></li>
            <li><a href="avaliacoes.php">⭐ Avaliações</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><?= $isEdit ? 'Editar Serviço' : 'Adicionar Serviço' ?></h1>
            <p>Configure os detalhes do serviço que você oferece</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Informações do Serviço -->
        <div class="card">
            <div class="card-header">
                <h3>Informações do Serviço</h3>
            </div>
            <div style="background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); margin: 0 1.5rem 1.5rem;">
                <h4 style="color: var(--primary-color); margin: 0 0 0.5rem 0;">
                    <?= htmlspecialchars($servico['nome_servico']) ?>
                </h4>
                <p style="margin: 0 0 0.5rem 0;">
                    <strong>Categoria:</strong> <?= htmlspecialchars($servico['categoria']) ?>
                </p>
                <?php if ($servico['descricao']): ?>
                    <p style="margin: 0; color: var(--text-light); font-size: 0.875rem;">
                        <?= htmlspecialchars($servico['descricao']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulário -->
        <div class="card">
            <div class="card-header">
                <h3>Seus Valores e Informações</h3>
            </div>

            <form method="POST" id="servicoForm">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= $servico_prestador['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="preco_hora">💰 Preço por Hora</label>
                        <input type="text" id="preco_hora" name="preco_hora"
                               placeholder="0,00"
                               value="<?= $servico_prestador ? number_format($servico_prestador['preco_hora'], 2, ',', '.') : '' ?>">
                        <small>Valor que você cobra por hora de serviço</small>
                    </div>

                    <div class="form-group">
                        <label for="preco_diaria">💰 Preço por Diária</label>
                        <input type="text" id="preco_diaria" name="preco_diaria"
                               placeholder="0,00"
                               value="<?= $servico_prestador ? number_format($servico_prestador['preco_diaria'], 2, ',', '.') : '' ?>">
                        <small>Valor que você cobra por diária (período de 24h)</small>
                    </div>
                </div>

                <p style="color: var(--text-light); font-size: 0.875rem; margin-bottom: 1.5rem;">
                    ⚠️ Informe pelo menos um dos dois valores acima
                </p>

                <div class="form-group">
                    <label for="experiencia_especifica">📋 Experiência Específica neste Serviço</label>
                    <textarea id="experiencia_especifica" name="experiencia_especifica" rows="4"
                              placeholder="Descreva sua experiência específica com este tipo de serviço..."><?= $servico_prestador ? htmlspecialchars($servico_prestador['experiencia_especifica']) : '' ?></textarea>
                    <small>Exemplo: "10 anos de experiência em cuidados de pacientes pós-operatórios"</small>
                </div>

                <div class="form-group">
                    <label for="observacoes">📝 Observações Adicionais</label>
                    <textarea id="observacoes" name="observacoes" rows="3"
                              placeholder="Informações adicionais que os clientes devem saber..."><?= $servico_prestador ? htmlspecialchars($servico_prestador['observacoes']) : '' ?></textarea>
                    <small>Exemplo: "Disponível para atendimentos emergenciais", "Possuo carro próprio"</small>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="ativo" <?= (!$servico_prestador || $servico_prestador['status'] === 'ativo') ? 'selected' : '' ?>>
                            Ativo - Receber solicitações
                        </option>
                        <option value="inativo" <?= ($servico_prestador && $servico_prestador['status'] === 'inativo') ? 'selected' : '' ?>>
                            Inativo - Não receber solicitações
                        </option>
                    </select>
                    <small>Desative temporariamente se não quiser receber novas solicitações</small>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? '💾 Salvar Alterações' : '➕ Adicionar Serviço' ?>
                    </button>
                    <a href="servicos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    // Máscara de dinheiro
    function mascaraMoeda(campo) {
        campo.addEventListener('input', function(e) {
            let valor = e.target.value.replace(/\D/g, '');
            valor = (parseInt(valor) / 100).toFixed(2);
            valor = valor.replace('.', ',');
            valor = valor.replace(/(\d)(?=(\d{3})+\,)/g, '$1.');
            e.target.value = valor;
        });
    }

    mascaraMoeda(document.getElementById('preco_hora'));
    mascaraMoeda(document.getElementById('preco_diaria'));

    // Validação do formulário
    document.getElementById('servicoForm').addEventListener('submit', function(e) {
        const precoHora = document.getElementById('preco_hora').value;
        const precoDiaria = document.getElementById('preco_diaria').value;

        if (!precoHora && !precoDiaria) {
            e.preventDefault();
            alert('Informe pelo menos um tipo de preço (hora ou diária)!');
        }
    });
</script>
</body>
</html>