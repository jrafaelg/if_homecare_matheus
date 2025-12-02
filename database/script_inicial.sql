-- Criação do Banco de Dados
CREATE DATABASE if_homecare;
USE if_homecare;

-- Tabela de Usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    cpf VARCHAR(14) UNIQUE,
    tipo_usuario ENUM('admin', 'prestador', 'cliente') NOT NULL,
    foto_perfil VARCHAR(255) DEFAULT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_tipo_usuario (tipo_usuario),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Endereços
CREATE TABLE enderecos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    rua VARCHAR(150) NOT NULL,
    numero VARCHAR(10) NOT NULL,
    complemento VARCHAR(100),
    bairro VARCHAR(100) NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    estado VARCHAR(2) NOT NULL,
    cep VARCHAR(10) NOT NULL,
    referencia TEXT,
    principal BOOLEAN DEFAULT FALSE,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Serviços
CREATE TABLE servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_servico VARCHAR(100) NOT NULL,
    descricao TEXT,
    categoria VARCHAR(50),
    icone VARCHAR(100),
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categoria (categoria),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Perfil do Prestador
CREATE TABLE perfil_prestador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL UNIQUE,
    descricao_profissional TEXT,
    especialidades TEXT,
    formacao VARCHAR(200),
    registro_profissional VARCHAR(50),
    anos_experiencia INT,
    certificados TEXT,
    disponibilidade_geral TEXT,
    raio_atendimento INT COMMENT 'Raio de atendimento em KM',
    media_avaliacoes DECIMAL(3,2) DEFAULT 0.00,
    total_avaliacoes INT DEFAULT 0,
    total_atendimentos INT DEFAULT 0,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_prestador (prestador_id),
    INDEX idx_media_avaliacoes (media_avaliacoes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Serviços oferecidos pelo Prestador
CREATE TABLE prestador_servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    servico_id INT NOT NULL,
    preco_hora DECIMAL(10,2),
    preco_diaria DECIMAL(10,2),
    experiencia_especifica TEXT COMMENT 'Experiência específica neste serviço',
    observacoes TEXT,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_prestador_servico (prestador_id, servico_id),
    INDEX idx_prestador (prestador_id),
    INDEX idx_servico (servico_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Solicitações de Serviço
CREATE TABLE solicitacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    prestador_id INT NOT NULL,
    servico_id INT NOT NULL,
    endereco_id INT NOT NULL,
    tipo_agendamento ENUM('hora', 'diaria') NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE,
    horario_inicio TIME,
    horario_fim TIME,
    valor_total DECIMAL(10,2),
    status ENUM('pendente', 'aceita', 'recusada', 'em_andamento', 'concluida', 'cancelada') DEFAULT 'pendente',
    observacoes_cliente TEXT,
    observacoes_prestador TEXT,
    motivo_recusa TEXT,
    data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_resposta TIMESTAMP NULL,
    data_conclusao TIMESTAMP NULL,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (prestador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE,
    FOREIGN KEY (endereco_id) REFERENCES enderecos(id) ON DELETE CASCADE,
    INDEX idx_cliente (cliente_id),
    INDEX idx_prestador (prestador_id),
    INDEX idx_status (status),
    INDEX idx_data_inicio (data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Histórico de Status das Solicitações
CREATE TABLE historico_solicitacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id INT NOT NULL,
    status_anterior ENUM('pendente', 'aceita', 'recusada', 'em_andamento', 'concluida', 'cancelada'),
    status_novo ENUM('pendente', 'aceita', 'recusada', 'em_andamento', 'concluida', 'cancelada') NOT NULL,
    observacao TEXT,
    usuario_id INT COMMENT 'Quem fez a alteração',
    data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_solicitacao (solicitacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Avaliações
CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id INT NOT NULL UNIQUE,
    cliente_id INT NOT NULL,
    prestador_id INT NOT NULL,
    nota INT NOT NULL CHECK (nota >= 1 AND nota <= 5),
    comentario TEXT,
    pontos_positivos TEXT,
    pontos_negativos TEXT,
    recomenda BOOLEAN DEFAULT TRUE,
    data_avaliacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (prestador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_prestador (prestador_id),
    INDEX idx_nota (nota),
    INDEX idx_data (data_avaliacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Notificações
CREATE TABLE notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL COMMENT 'nova_solicitacao, solicitacao_aceita, etc',
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    link VARCHAR(255),
    lida BOOLEAN DEFAULT FALSE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_lida (lida),
    INDEX idx_data (data_criacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Configurações do Administrador
CREATE TABLE admin_configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    timezone VARCHAR(50) DEFAULT 'America/Sao_Paulo',
    notificacoes_email BOOLEAN DEFAULT TRUE,
    relatorios_automaticos BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_admin_configuracoes_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir usuário admin padrão (senha: admin123 - hash MD5 para exemplo)
INSERT INTO usuarios (nome, email, senha, tipo_usuario, status) 
VALUES ('Administrador', 'admin@homecare.com', '$2y$10$j7LlmvimThHEpUsxi/SKxuHM6/ZpwZeCQRgtVHzZj8w/DHTAkeOPi', 'admin', 'ativo');

-- Inserir alguns serviços de exemplo
INSERT INTO servicos (nome_servico, descricao, categoria, status) VALUES
('Enfermagem', 'Serviços de enfermagem domiciliar', 'Saúde', 'ativo'),
('Fisioterapia', 'Fisioterapia e reabilitação em domicílio', 'Saúde', 'ativo'),
('Cuidador de Idosos', 'Cuidados e acompanhamento de pessoas idosas', 'Cuidados', 'ativo'),
('Nutricionista', 'Acompanhamento nutricional domiciliar', 'Saúde', 'ativo'),
('Auxiliar de Enfermagem', 'Auxílio em cuidados básicos de saúde', 'Saúde', 'ativo');

-- 
Tabela de Disponibilidade do Prestador
CREATE TABLE disponibilidade_prestador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    dia_semana ENUM('domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado') NOT NULL,
    disponivel BOOLEAN DEFAULT TRUE,
    horario_inicio TIME,
    horario_fim TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_prestador_dia (prestador_id, dia_semana),
    INDEX idx_prestador (prestador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Bloqueios de Agenda
CREATE TABLE bloqueios_agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    motivo ENUM('ferias', 'folga', 'compromisso', 'outro') NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_prestador (prestador_id),
    INDEX idx_datas (data_inicio, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
