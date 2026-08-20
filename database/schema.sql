-- =====================================================================
-- CRM Oficina Mecânica — Script de criação do banco de dados
-- Motor: MySQL 8+ / MariaDB 10.5+
-- Charset: utf8mb4
-- Cobre o escopo completo do MVP (Fase 1): clientes, veículos, agenda,
-- entrada do veículo, OS, fila da oficina, diagnóstico, orçamento (com
-- aprovação e PDF), execução do serviço, saída, recibo e histórico.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS oficina
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oficina;

-- ---------------------------------------------------------------------
-- usuarios (mecânicos, atendentes, admins)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120) NOT NULL,
    telefone        VARCHAR(20)  NULL,
    telegram_id     VARCHAR(64)  NULL UNIQUE,
    papel           ENUM('admin','mecanico','atendente') NOT NULL DEFAULT 'mecanico',
    email           VARCHAR(150) NULL UNIQUE,
    senha_hash      VARCHAR(255) NULL,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- clientes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(150) NOT NULL,
    telefone    VARCHAR(20)  NULL,
    cpf_cnpj    VARCHAR(20)  NULL,
    email       VARCHAR(150) NULL,
    observacoes TEXT NULL,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_clientes_nome (nome),
    INDEX idx_clientes_telefone (telefone),
    INDEX idx_clientes_cpf_cnpj (cpf_cnpj)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- veiculos — placa é o identificador único e principal
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS veiculos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id      INT UNSIGNED NOT NULL,
    placa           VARCHAR(10) NOT NULL,
    marca           VARCHAR(60)  NULL,
    modelo          VARCHAR(100) NOT NULL,
    versao          VARCHAR(100) NULL,
    cor             VARCHAR(40)  NULL,
    ano             SMALLINT UNSIGNED NULL,
    quilometragem   INT UNSIGNED NULL COMMENT 'última quilometragem conhecida do veículo',
    combustivel     ENUM('gasolina','etanol','flex','diesel','gnv','eletrico','hibrido') NULL,
    observacoes     TEXT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_veiculos_placa (placa),
    CONSTRAINT fk_veiculos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_veiculos_modelo (modelo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- agendamentos — feitos ANTES da chegada do veículo na oficina
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agendamentos (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id                  INT UNSIGNED NOT NULL,
    veiculo_id                  INT UNSIGNED NULL COMMENT 'pode ainda não haver veículo cadastrado no momento do agendamento',
    data_hora                   DATETIME NOT NULL,
    servico_motivo              VARCHAR(255) NULL,
    observacoes                 TEXT NULL,
    previsao_duracao_minutos    INT UNSIGNED NULL,
    status                      ENUM('agendado','confirmado','chegou','cancelado','nao_compareceu')
                                    NOT NULL DEFAULT 'agendado',
    ordem_servico_id            INT UNSIGNED NULL COMMENT 'preenchido quando o agendamento vira uma OS',
    criado_por                  INT UNSIGNED NULL,
    criado_em                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agenda_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_agenda_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_agenda_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_agenda_data (data_hora),
    INDEX idx_agenda_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ordens_servico — entrada do veículo, fila, status, saída
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ordens_servico (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    veiculo_id                  INT UNSIGNED NOT NULL,
    agendamento_id               INT UNSIGNED NULL,

    -- Entrada
    data_entrada                DATETIME NOT NULL COMMENT 'data/hora real de entrada — preservada permanentemente para a fila',
    quilometragem_entrada       INT UNSIGNED NULL,
    motivo                      VARCHAR(255) NULL COMMENT 'motivo da entrada (curto, ex.: revisão, barulho no motor)',
    reclamacao_cliente          TEXT NULL,
    previsao_entrega            DATETIME NULL,
    responsavel_atendimento_id  INT UNSIGNED NULL,

    -- Status / fluxo
    status                      ENUM('recebido','em_diagnostico','aguardando_aprovacao','aguardando_peca','em_servico','pronto','entregue')
                                    NOT NULL DEFAULT 'recebido',

    -- Fila de prioridade (a ordem "natural" é sempre por data_entrada; estes campos
    -- só registram uma EXCEÇÃO manual, mantendo o motivo e quem alterou)
    prioridade_manual           INT NULL COMMENT 'menor valor = mais prioritário; NULL = segue ordem natural por data_entrada',
    prioridade_motivo           VARCHAR(255) NULL,
    prioridade_alterada_por     INT UNSIGNED NULL,
    prioridade_alterada_em      DATETIME NULL,
    pausada                     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'aguardando peça/aprovação: não ocupa a vez dos próximos, mas mantém o histórico de entrada',

    -- Saída
    data_saida                  DATETIME NULL,
    quilometragem_saida         INT UNSIGNED NULL,
    valor_final                 DECIMAL(10,2) NULL,
    forma_pagamento             VARCHAR(60) NULL,
    responsavel_entrega_id      INT UNSIGNED NULL,
    data_limite_retorno         DATE NULL COMMENT 'prazo para retorno gratuito',
    observacoes_finais          TEXT NULL,

    criado_por                  INT UNSIGNED NULL COMMENT 'usuarios.id (quem abriu a OS)',
    criado_em                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_os_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_os_agendamento FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_os_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_os_responsavel_atendimento FOREIGN KEY (responsavel_atendimento_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_os_prioridade_usuario FOREIGN KEY (prioridade_alterada_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_os_responsavel_entrega FOREIGN KEY (responsavel_entrega_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX idx_os_status (status),
    INDEX idx_os_veiculo (veiculo_id),
    INDEX idx_os_data_entrada (data_entrada)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- fila_log — histórico de alterações de prioridade e pausas na fila
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fila_log (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL,
    acao                ENUM('entrada_fila','prioridade_alterada','pausada','retomada') NOT NULL,
    motivo              VARCHAR(255) NULL,
    usuario_id          INT UNSIGNED NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_filalog_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_filalog_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_filalog_os (ordem_servico_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- diagnosticos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS diagnosticos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL,
    descricao           VARCHAR(500) NOT NULL,
    origem              ENUM('sugerido_por_ia','informado_pelo_mecanico') NOT NULL,
    status              ENUM('sugerido','confirmado','descartado') NOT NULL DEFAULT 'sugerido',
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_diag_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_diag_os (ordem_servico_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- orcamentos — orçamento rápido, com aprovação e PDF
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orcamentos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL,
    desconto            DECIMAL(10,2) NOT NULL DEFAULT 0,
    total               DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'soma dos itens - desconto, recalculado a cada alteração',
    validade_dias       INT UNSIGNED NOT NULL DEFAULT 7,
    data_validade       DATE NULL,
    status              ENUM('pendente','aprovado','parcialmente_aprovado','recusado') NOT NULL DEFAULT 'pendente',
    aprovado_em         DATETIME NULL,
    observacoes         TEXT NULL,
    pdf_caminho         VARCHAR(500) NULL,
    criado_por          INT UNSIGNED NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orcamento_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_orcamento_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_orcamento_os (ordem_servico_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- orcamento_itens — peças, serviços e mão de obra do orçamento
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orcamento_itens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orcamento_id    INT UNSIGNED NOT NULL,
    tipo            ENUM('peca','servico','mao_de_obra') NOT NULL,
    descricao       VARCHAR(300) NOT NULL,
    quantidade      DECIMAL(10,2) NOT NULL DEFAULT 1,
    valor_unitario  DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_total     DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'quantidade * valor_unitario, calculado em aplicação',
    aprovado        TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'permite aprovação parcial: item a item',
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orcitem_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_orcitem_orcamento (orcamento_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- servicos_realizados — execução do serviço
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS servicos_realizados (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id            INT UNSIGNED NOT NULL,
    descricao                   VARCHAR(500) NOT NULL,
    mecanico_responsavel_id     INT UNSIGNED NULL,
    data_inicio                 DATETIME NULL,
    data_conclusao               DATETIME NULL,
    data                         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'data de registro (compatibilidade)',
    CONSTRAINT fk_serv_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_serv_mecanico FOREIGN KEY (mecanico_responsavel_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_serv_os (ordem_servico_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- pecas — peças efetivamente usadas na execução
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pecas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL,
    descricao           VARCHAR(300) NOT NULL,
    quantidade          DECIMAL(10,2) NOT NULL DEFAULT 1,
    comprada_por        ENUM('oficina','cliente') NOT NULL DEFAULT 'oficina',
    valor               DECIMAL(10,2) NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_peca_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_peca_os (ordem_servico_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- garantias — data_fim é calculada em aplicação (data_saida + prazo_dias)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS garantias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL,
    prazo_dias          INT UNSIGNED NOT NULL DEFAULT 90,
    data_inicio         DATE NOT NULL,
    data_fim            DATE NOT NULL,
    observacoes         VARCHAR(500) NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_garantia_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_garantia_data_fim (data_fim)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- recibos — PDF de saída/pagamento, salvo no histórico
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS recibos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL UNIQUE,
    pdf_caminho         VARCHAR(500) NOT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recibo_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- anexos (fotos/áudios vinculados a uma OS)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS anexos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL,
    tipo                ENUM('foto','audio','documento') NOT NULL,
    categoria           ENUM('entrada','avaria','painel_km','durante_servico','peca','documento','outro')
                            NOT NULL DEFAULT 'outro',
    caminho_arquivo     VARCHAR(500) NOT NULL,
    transcricao         TEXT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_anexo_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_anexo_os (ordem_servico_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- conversa_contexto — guarda o "estado pendente" de uma conversa no chat
-- (ex.: aguardando o mecânico responder qual placa, após um pedido de
-- esclarecimento). Uma linha por chat_id do Telegram.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversa_contexto (
    chat_id             VARCHAR(64) PRIMARY KEY,
    intencao_pendente   VARCHAR(60) NULL,
    dados_pendentes     TEXT NULL COMMENT 'JSON com os dados já coletados antes do esclarecimento',
    atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- interacoes_chat (log bruto de tudo que chega pelo chat)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS interacoes_chat (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id                  INT UNSIGNED NULL,
    mensagem_original           TEXT NULL,
    tipo                        ENUM('texto','audio','foto') NOT NULL,
    transcricao_ou_interpretacao TEXT NULL,
    acao_executada              VARCHAR(100) NULL,
    payload_json                TEXT NULL COMMENT 'dados estruturados extraídos pela IA',
    sucesso                     TINYINT(1) NOT NULL DEFAULT 1,
    criado_em                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_interacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_interacao_usuario (usuario_id),
    INDEX idx_interacao_criado (criado_em)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Usuário admin padrão (senha: "troque-esta-senha" — TROCAR após instalar)
-- Hash gerado com password_hash('troque-esta-senha', PASSWORD_BCRYPT)
-- ---------------------------------------------------------------------
INSERT INTO usuarios (nome, papel, email, senha_hash, ativo)
VALUES (
    'Administrador',
    'admin',
    'admin@oficina.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1
)
ON DUPLICATE KEY UPDATE nome = nome;
