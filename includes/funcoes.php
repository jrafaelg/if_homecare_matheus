<?php


/**
 * Funções Auxiliares do Sistema
 */

// Redirecionar para uma página
function redirect($url)
{

    //echo (SITE_URL . $url);
    //exit;

    header("Location: " . SITE_URL . $url);
    exit();
}

// Verificar se usuário está logado
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Obter dados do usuário logado
function getLoggedUser()
{
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'nome' => $_SESSION['user_nome'],
        'email' => $_SESSION['user_email'],
        'tipo' => $_SESSION['user_tipo']
    ];
}

// Verificar tipo de usuário
function checkUserType($tipo)
{
    if (!isLoggedIn()) {
        redirect('/auth/login.php');
    }

    if ($_SESSION['user_tipo'] !== $tipo) {
        redirect('/index.php');
    }
}

// Limpar dados de entrada
function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Validar email
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validar CPF
function isValidCPF($cpf)
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);

    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

// Formatar CPF
function formatCPF($cpf)
{
    if (empty($cpf)) return null;
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

// Formatar telefone
function formatPhone($phone)
{
    if (empty($phone)) return null;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
    } else if (strlen($phone) == 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
    }
    return $phone;
}

// Formatar CEP
function formatCEP($cep)
{
    if (empty($cep)) return null;
    $cep = preg_replace('/[^0-9]/', '', $cep);
    return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
}

// Formatar data brasileira
function formatDateBR($date)
{
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}

// Formatar data e hora brasileira
function formatDateTimeBR($datetime)
{
    if (empty($datetime)) return '';
    return date('d/m/Y H:i', strtotime($datetime));
}

// Formatar moeda
function formatMoney($value)
{
    if (empty($value)) return '';
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Upload de arquivo
function uploadFile($file, $folder = '')
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erro no upload do arquivo'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Arquivo muito grande'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido'];
    }

    $filename = uniqid() . '.' . $extension;
    $uploadPath = UPLOAD_DIR . $folder;

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $destination = $uploadPath . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'Erro ao salvar arquivo'];
}

// Gerar hash de senha
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verificar senha
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

// Gerar token aleatório
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

// Exibir mensagem de alerta
function setAlert($message, $type = 'info')
{
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type // success, error, warning, info
    ];
}

// Obter e limpar mensagem de alerta
function getAlert()
{
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// Calcular idade
function calculateAge($birthdate)
{
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    return $today->diff($birthDate)->y;
}

// Gerar slug
function generateSlug($string)
{
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}
