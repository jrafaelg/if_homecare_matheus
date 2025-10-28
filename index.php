<?php
require_once 'config/config.php';

// Se estiver logado, redirecionar para dashboard
if (isLoggedIn()) {
    $tipo = $_SESSION['user_tipo'];
    redirect("/$tipo/index.php");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Serviços de Saúde em Casa</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="header-content">
            <h1 class="logo"><?= SITE_NAME ?></h1>
            <nav class="header-nav">
                <a href="auth/login.php" class="btn btn-outline">Entrar</a>
                <a href="auth/registro.php" class="btn btn-primary">Cadastrar</a>
            </nav>
        </div>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h2>Cuidados de Saúde no Conforto do Seu Lar</h2>
                <p>Conectamos você aos melhores profissionais de saúde para atendimento domiciliar</p>
                <div class="hero-buttons">
                    <a href="auth/registro.php" class="btn btn-primary btn-lg">Encontrar Profissionais</a>
                    <a href="auth/registro.php" class="btn btn-secondary btn-lg">Oferecer Serviços</a>
                </div>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <h2>Como Funciona</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🔍</div>
                    <h3>Busque Profissionais</h3>
                    <p>Encontre prestadores qualificados próximos a você</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Agende o Serviço</h3>
                    <p>Escolha data, horário e tipo de atendimento</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🏠</div>
                    <h3>Receba em Casa</h3>
                    <p>Profissional vai até você no conforto do seu lar</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⭐</div>
                    <h3>Avalie o Serviço</h3>
                    <p>Compartilhe sua experiência e ajude outros</p>
                </div>
            </div>
        </div>
    </section>

    <section class="services">
        <div class="container">
            <h2>Serviços Disponíveis</h2>
            <div class="services-grid">
                <div class="service-card">
                    <h3>🏥 Enfermagem</h3>
                    <p>Cuidados de enfermagem profissional</p>
                </div>
                <div class="service-card">
                    <h3>💪 Fisioterapia</h3>
                    <p>Reabilitação e tratamento físico</p>
                </div>
                <div class="service-card">
                    <h3>👵 Cuidador de Idosos</h3>
                    <p>Acompanhamento e cuidados especializados</p>
                </div>
                <div class="service-card">
                    <h3>🥗 Nutricionista</h3>
                    <p>Orientação nutricional personalizada</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <h2>Pronto para Começar?</h2>
            <p>Crie sua conta gratuitamente e tenha acesso aos melhores profissionais de saúde</p>
            <a href="auth/register.php" class="btn btn-primary btn-lg">Cadastrar Agora</a>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Todos os direitos reservados.</p>
    </div>
</footer>
</body>
</html>