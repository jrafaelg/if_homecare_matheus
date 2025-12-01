<?php
require_once '../config/config.php';
checkUserType(USER_PRESTADOR);

header('Content-Type: application/json');

$db = getDB();
$user = getLoggedUser();

$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';

if ($action === 'dia') {
    $data = isset($_GET['data']) ? sanitize($_GET['data']) : '';
    
    if (empty($data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        echo json_encode(['error' => 'Data inválida']);
        exit;
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
            AND s.data_inicio = ?
            AND s.status IN ('aceita', 'em_andamento', 'concluida')
            ORDER BY s.horario_inicio
        ");
        $stmt->execute([$user['id'], $data]);
        $agendamentos = $stmt->fetchAll();
        
        echo json_encode([
            'data' => $data,
            'agendamentos' => $agendamentos
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'error' => 'Erro ao buscar agendamentos',
            'data' => $data,
            'agendamentos' => []
        ]);
    }
}
?>
