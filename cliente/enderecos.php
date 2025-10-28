<?php
require_once '../config/config.php';
checkUserType(USER_CLIENTE);

$db = getDB();
$user = getLoggedUser();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action']);

    if ($action === 'adicionar' || $action === 'editar') {
        $endereco_id = isset($_POST['endereco_id']) ? (int)$_POST['endereco_id'] : 0;
        $rua = sanitize($_POST['rua']);
        $numero = sanitize($_POST['numero']);
        $complemento = sanitize($_POST['complemento']);
        $bairro = sanitize($_POST['bairro']);
        $cidade = sanitize($_POST['cidade']);
        $estado = sanitize($_POST['estado']);
        $cep = preg_replace('/[^0-9]/', '', $_POST['cep']);
        $referencia = sanitize($_POST['referencia']);
        $principal = isset($_POST['principal']) ? 1 : 0;

        // Validações
        $errors = [];

        if (empty($rua)) $errors[] = 'Rua é obrigatória';
        if (empty($numero)) $errors[] = 'Número é obrigatório';
        if (empty($bairro)) $errors[] = 'Bairro é obrigatório';
        if (empty($cidade)) $errors[] = 'Cidade é obrigatória';
        if (empty($estado)) $errors[] = 'Estado é obrigatório';
        if (empty($cep) || strlen($cep) !== 8) $errors[] = 'CEP deve ter 8 dígitos';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Se for principal, remover principal dos outros
                if ($principal) {
                    $stmt = $db->prepare("UPDATE enderecos SET principal = 0 WHERE usuario_id = ?");
                    $stmt->execute([$user['id']]);
                }

                if ($action === 'adicionar') {
                    $stmt = $db->prepare("
                        INSERT INTO enderecos (
                            usuario_id, rua, numero, complemento, bairro, 
                            cidade, estado, cep, referencia, principal
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user['id'], $rua, $numero, $complemento, $bairro,
                        $cidade, $estado, $cep, $referencia, $principal
                    ]);
                    $message = 'Endereço adicionado com sucesso';
                } else {
                    // Verificar se o endereço pertence ao usuário
                    $stmt = $db->prepare("SELECT id FROM enderecos WHERE id = ? AND usuario_id = ?");
                    $stmt->execute([$endereco_id, $user['id']]);
                    
                    if ($stmt->fetch()) {
                        $stmt = $db->prepare("
                            UPDATE enderecos SET 
                                rua = ?, numero = ?, complemento = ?, bairro = ?,
                                cidade = ?, estado = ?, cep = ?, referencia = ?, principal = ?
                            WHERE id = ? AND usuario_id = ?
                        ");
                        $stmt->execute([
                            $rua, $numero, $complemento, $bairro,
                            $cidade, $estado, $cep, $referencia, $principal,
                            $endereco_id, $user['id']
                        ]);
                        $message = 'Endereço atualizado com sucesso';
                    } else {
                        throw new Exception('Endereço não encontrado');
                    }
                }

                $db->commit();
                setAlert($message, 'success');
                redirect('/cliente/enderecos.php');

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Erro ao salvar endereço';
            }
        }
    } elseif ($action === 'excluir') {
        $endereco_id = (int)$_POST['endereco_id'];

        try {
            // Verificar se o endereço não está sendo usado em solicitações
            $stmt = $db->prepare("
                SELECT COUNT(*) as total FROM solicitacoes 
                WHERE endereco_id = ? AND status NOT IN ('cancelada', 'recusada')
            ");
            $stmt->execute([$endereco_id]);
            $em_uso = $stmt->fetch()['total'];

            if ($em_uso > 0) {
                setAlert('Não é possível excluir este endereço pois ele está sendo usado em solicitações ativas', 'error');
            } else {
                $stmt = $db->prepare("DELETE FROM enderecos WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$endereco_id, $user['id']]);
                
                if ($stmt->rowCount() > 0) {
                    setAlert('Endereço excluído com sucesso', 'success');
                } else {
                    setAlert('Endereço não encontrado', 'error');
                }
            }
        } catch (PDOException $e) {
            setAlert('Erro ao excluir endereço', 'error');
        }

        redirect('/cliente/enderecos.php');
    } elseif ($action === 'definir_principal') {
        $endereco_id = (int)$_POST['endereco_id'];

        try {
            $db->beginTransaction();

            // Remover principal de todos
            $stmt = $db->prepare("UPDATE enderecos SET principal = 0 WHERE usuario_id = ?");
            $stmt->execute([$user['id']]);

            // Definir o novo principal
            $stmt = $db->prepare("UPDATE enderecos SET principal = 1 WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$endereco_id, $user['id']]);

            $db->commit();
            setAlert('Endereço principal definido com sucesso', 'success');
        } catch (PDOException $e) {
            $db->rollBack();
            setAlert('Erro ao definir endereço principal', 'error');
        }

        redirect('/cliente/enderecos.php');
    }
}

// Buscar endereços
try {
    $stmt = $db->prepare("
        SELECT * FROM enderecos 
        WHERE usuario_id = ? 
        ORDER BY principal DESC, id DESC
    ");
    $stmt->execute([$user['id']]);
    $enderecos = $stmt->fetchAll();
} catch (PDOException $e) {
    $enderecos = [];
    $error = "Erro ao buscar endereços";
}

// Dados para edição
$endereco_edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($enderecos as $endereco) {
        if ($endereco['id'] === $edit_id) {
            $endereco_edit = $endereco;
            break;
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
    <title>Meus Endereços - <?= SITE_NAME ?></title>
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
            <li><a href="meus_agendamentos.php">📋 Meus Agendamentos</a></li>
            <li><a href="enderecos.php" class="active">📍 Meus Endereços</a></li>
            <li><a href="perfil.php">👤 Meu Perfil</a></li>
            <li><a href="../auth/logout.php">🚪 Sair</a></li>
        </ul>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1>Meus Endereços</h1>
            <p>Gerencie os endereços onde você recebe atendimento</p>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= $alert['message'] ?>
            </div>
        <?php endif; ?>

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
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3><?= $endereco_edit ? 'Editar Endereço' : 'Adicionar Novo Endereço' ?></h3>
                    </div>
                    <form method="POST" class="card-body">
                        <input type="hidden" name="action" value="<?= $endereco_edit ? 'editar' : 'adicionar' ?>">
                        <?php if ($endereco_edit): ?>
                            <input type="hidden" name="endereco_id" value="<?= $endereco_edit['id'] ?>">
                        <?php endif; ?>

                        <div class="form-row">
                            <div class="form-group" style="flex: 3;">
                                <label for="rua">Rua/Avenida *</label>
                                <input type="text" id="rua" name="rua" required
                                       value="<?= $endereco_edit ? htmlspecialchars($endereco_edit['rua']) : '' ?>"
                                       placeholder="Ex: Rua das Flores">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="numero">Número *</label>
                                <input type="text" id="numero" name="numero" required
                                       value="<?= $endereco_edit ? htmlspecialchars($endereco_edit['numero']) : '' ?>"
                                       placeholder="123">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="complemento">Complemento</label>
                            <input type="text" id="complemento" name="complemento"
                                   value="<?= $endereco_edit ? htmlspecialchars($endereco_edit['complemento']) : '' ?>"
                                   placeholder="Apartamento, bloco, etc.">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="bairro">Bairro *</label>
                                <input type="text" id="bairro" name="bairro" required
                                       value="<?= $endereco_edit ? htmlspecialchars($endereco_edit['bairro']) : '' ?>"
                                       placeholder="Centro">
                            </div>
                            <div class="form-group">
                                <label for="cep">CEP *</label>
                                <input type="text" id="cep" name="cep" required maxlength="9"
                                       value="<?= $endereco_edit ? formatCEP($endereco_edit['cep']) : '' ?>"
                                       placeholder="00000-000">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="cidade">Cidade *</label>
                                <input type="text" id="cidade" name="cidade" required
                                       value="<?= $endereco_edit ? htmlspecialchars($endereco_edit['cidade']) : '' ?>"
                                       placeholder="São Paulo">
                            </div>
                            <div class="form-group">
                                <label for="estado">Estado *</label>
                                <select id="estado" name="estado" required>
                                    <option value="">Selecione...</option>
                                    <option value="AC" <?= ($endereco_edit && $endereco_edit['estado'] === 'AC') ? 'selected' : '' ?>>Acre</option>
                                    <option value="AL" <?= ($endereco_edit && $endereco_edit['estado'] === 'AL') ? 'selected' : '' ?>>Alagoas</option>
                                    <option value="AP" <?= ($endereco_edit && $endereco_edit['estado'] === 'AP') ? 'selected' : '' ?>>Amapá</option>
                                    <option value="AM" <?= ($endereco_edit && $endereco_edit['estado'] === 'AM') ? 'selected' : '' ?>>Amazonas</option>
                                    <option value="BA" <?= ($endereco_edit && $endereco_edit['estado'] === 'BA') ? 'selected' : '' ?>>Bahia</option>
                                    <option value="CE" <?= ($endereco_edit && $endereco_edit['estado'] === 'CE') ? 'selected' : '' ?>>Ceará</option>
                                    <option value="DF" <?= ($endereco_edit && $endereco_edit['estado'] === 'DF') ? 'selected' : '' ?>>Distrito Federal</option>
                                    <option value="ES" <?= ($endereco_edit && $endereco_edit['estado'] === 'ES') ? 'selected' : '' ?>>Espírito Santo</option>
                                    <option value="GO" <?= ($endereco_edit && $endereco_edit['estado'] === 'GO') ? 'selected' : '' ?>>Goiás</option>
                                    <option value="MA" <?= ($endereco_edit && $endereco_edit['estado'] === 'MA') ? 'selected' : '' ?>>Maranhão</option>
                                    <option value="MT" <?= ($endereco_edit && $endereco_edit['estado'] === 'MT') ? 'selected' : '' ?>>Mato Grosso</option>
                                    <option value="MS" <?= ($endereco_edit && $endereco_edit['estado'] === 'MS') ? 'selected' : '' ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?= ($endereco_edit && $endereco_edit['estado'] === 'MG') ? 'selected' : '' ?>>Minas Gerais</option>
                                    <option value="PA" <?= ($endereco_edit && $endereco_edit['estado'] === 'PA') ? 'selected' : '' ?>>Pará</option>
                                    <option value="PB" <?= ($endereco_edit && $endereco_edit['estado'] === 'PB') ? 'selected' : '' ?>>Paraíba</option>
                                    <option value="PR" <?= ($endereco_edit && $endereco_edit['estado'] === 'PR') ? 'selected' : '' ?>>Paraná</option>
                                    <option value="PE" <?= ($endereco_edit && $endereco_edit['estado'] === 'PE') ? 'selected' : '' ?>>Pernambuco</option>
                                    <option value="PI" <?= ($endereco_edit && $endereco_edit['estado'] === 'PI') ? 'selected' : '' ?>>Piauí</option>
                                    <option value="RJ" <?= ($endereco_edit && $endereco_edit['estado'] === 'RJ') ? 'selected' : '' ?>>Rio de Janeiro</option>
                                    <option value="RN" <?= ($endereco_edit && $endereco_edit['estado'] === 'RN') ? 'selected' : '' ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?= ($endereco_edit && $endereco_edit['estado'] === 'RS') ? 'selected' : '' ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?= ($endereco_edit && $endereco_edit['estado'] === 'RO') ? 'selected' : '' ?>>Rondônia</option>
                                    <option value="RR" <?= ($endereco_edit && $endereco_edit['estado'] === 'RR') ? 'selected' : '' ?>>Roraima</option>
                                    <option value="SC" <?= ($endereco_edit && $endereco_edit['estado'] === 'SC') ? 'selected' : '' ?>>Santa Catarina</option>
                                    <option value="SP" <?= ($endereco_edit && $endereco_edit['estado'] === 'SP') ? 'selected' : '' ?>>São Paulo</option>
                                    <option value="SE" <?= ($endereco_edit && $endereco_edit['estado'] === 'SE') ? 'selected' : '' ?>>Sergipe</option>
                                    <option value="TO" <?= ($endereco_edit && $endereco_edit['estado'] === 'TO') ? 'selected' : '' ?>>Tocantins</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="referencia">Ponto de Referência</label>
                            <textarea id="referencia" name="referencia" rows="2"
                                      placeholder="Ex: Próximo ao shopping, em frente à farmácia..."><?= $endereco_edit ? htmlspecialchars($endereco_edit['referencia']) : '' ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="principal" value="1"
                                       <?= ($endereco_edit && $endereco_edit['principal']) ? 'checked' : '' ?>>
                                <span>Definir como endereço principal</span>
                            </label>
                            <small>O endereço principal será selecionado automaticamente nas solicitações</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">
                                <?= $endereco_edit ? '✏️ Atualizar' : '➕ Adicionar' ?> Endereço
                            </button>
                            <?php if ($endereco_edit): ?>
                                <a href="enderecos.php" class="btn btn-secondary">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Endereços -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Seus Endereços (<?= count($enderecos) ?>)</h3>
                    </div>

                    <?php if (empty($enderecos)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📍</div>
                            <h3>Nenhum endereço cadastrado</h3>
                            <p>Adicione um endereço para poder solicitar serviços</p>
                        </div>
                    <?php else: ?>
                        <div class="enderecos-list">
                            <?php foreach ($enderecos as $endereco): ?>
                                <div class="endereco-card <?= $endereco['principal'] ? 'endereco-principal' : '' ?>">
                                    <div class="endereco-header">
                                        <div class="endereco-info">
                                            <strong>
                                                <?= htmlspecialchars($endereco['rua'] . ', ' . $endereco['numero']) ?>
                                                <?php if ($endereco['complemento']): ?>
                                                    - <?= htmlspecialchars($endereco['complemento']) ?>
                                                <?php endif; ?>
                                            </strong>
                                            <p><?= htmlspecialchars($endereco['bairro'] . ', ' . $endereco['cidade'] . ' - ' . $endereco['estado']) ?></p>
                                            <p>CEP: <?= formatCEP($endereco['cep']) ?></p>
                                            <?php if ($endereco['referencia']): ?>
                                                <p class="referencia">📍 <?= htmlspecialchars($endereco['referencia']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($endereco['principal']): ?>
                                            <span class="badge badge-success">Principal</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="endereco-actions">
                                        <?php if (!$endereco['principal']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="definir_principal">
                                                <input type="hidden" name="endereco_id" value="<?= $endereco['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    ⭐ Tornar Principal
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="?edit=<?= $endereco['id'] ?>" class="btn btn-secondary btn-sm">
                                            ✏️ Editar
                                        </a>

                                        <form method="POST" style="display: inline;"
                                              onsubmit="return confirm('Tem certeza que deseja excluir este endereço?')">
                                            <input type="hidden" name="action" value="excluir">
                                            <input type="hidden" name="endereco_id" value="<?= $endereco['id'] ?>">
                                            <button type="submit" class="btn btn-error btn-sm">
                                                🗑️ Excluir
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Máscara para CEP
document.getElementById('cep').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 8) {
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

// Buscar CEP automaticamente
document.getElementById('cep').addEventListener('blur', function(e) {
    const cep = e.target.value.replace(/\D/g, '');
    
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('rua').value = data.logradouro || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.localidade || '';
                    document.getElementById('estado').value = data.uf || '';
                }
            })
            .catch(error => {
                console.log('Erro ao buscar CEP:', error);
            });
    }
});
</script>
</body>
</html>