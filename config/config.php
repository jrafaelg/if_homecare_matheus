<?php
// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações Gerais do Sistema
define('SITE_NAME', 'Sistema Homecare');
define('SITE_URL', 'http://localhost/if_homecare');
define('BASE_PATH', __DIR__ . '/..');

// Configurações de Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de Upload
define('UPLOAD_DIR', BASE_PATH . '/assets/images/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Configurações de Paginação
define('ITEMS_PER_PAGE', 10);

// Configurações de Email (para futuras implementações)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu-email@gmail.com');
define('SMTP_PASS', 'sua-senha');
define('SMTP_FROM', 'noreply@homecare.com');

// Níveis de usuário
define('USER_ADMIN', 'admin');
define('USER_PRESTADOR', 'prestador');
define('USER_CLIENTE', 'cliente');

// Status de solicitações
define('STATUS_PENDENTE', 'pendente');
define('STATUS_ACEITA', 'aceita');
define('STATUS_RECUSADA', 'recusada');
define('STATUS_EM_ANDAMENTO', 'em_andamento');
define('STATUS_CONCLUIDA', 'concluida');
define('STATUS_CANCELADA', 'cancelada');

// Incluir arquivo de conexão
require_once BASE_PATH . '/database/conexao.php';

// Incluir funções auxiliares
require_once BASE_PATH . '/includes/funcoes.php';
?>