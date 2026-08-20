-- =====================================================================
-- Migração 002 — expande o schema inicial para o escopo completo do MVP
-- (Agenda, Fila da Oficina, Orçamento com aprovação/PDF, Execução,
-- Saída/Recibo). Rode isto SOMENTE se o banco já foi criado com a
-- versão anterior de database/schema.sql. Instalações novas já nascem
-- com o schema completo e não precisam desta migração.
-- =====================================================================

USE oficina;

-- clientes
ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS cpf_cnpj VARCHAR(20) NULL AFTER telefone,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER cpf_cnpj,
    ADD COLUMN IF NOT EXISTS observacoes TEXT NULL AFTER email;
ALTER TABLE clientes ADD INDEX IF NOT EXISTS idx_clientes_cpf_cnpj (cpf_cnpj);

-- veiculos
ALTER TABLE veiculos
    ADD COLUMN IF NOT EXISTS marca VARCHAR(60) NULL AFTER placa,
    ADD COLUMN IF NOT EXISTS versao VARCHAR(100) NULL AFTER modelo,
    ADD COLUMN IF NOT EXISTS quilometragem INT UNSIGNED NULL AFTER ano,
    ADD COLUMN IF NOT EXISTS combustivel ENUM('gasolina','etanol','flex','diesel','gnv','eletrico','hibrido') NULL AFTER quilometragem,
    ADD COLUMN IF NOT EXISTS observacoes TEXT NULL AFTER combustivel;

-- agendamentos (nova tabela)
CREATE TABLE IF NOT EXISTS agendamentos (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id                  INT UNSIGNED NOT NULL,
    veiculo_id                  INT UNSIGNED NULL,
    data_hora                   DATETIME NOT NULL,
    servico_motivo              VARCHAR(255) NULL,
    observacoes                 TEXT NULL,
    previsao_duracao_minutos    INT UNSIGNED NULL,
    status                      ENUM('agendado','confirmado','chegou','cancelado','nao_compareceu') NOT NULL DEFAULT 'agendado',
    ordem_servico_id            INT UNSIGNED NULL,
    criado_por                  INT UNSIGNED NULL,
    criado_em                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agenda_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_agenda_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_agenda_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_agenda_data (data_hora),
    INDEX idx_agenda_status (status)
) ENGINE=InnoDB;

-- ordens_servico: novo status + campos de entrada/saída/fila
-- Passo 1: amplia o ENUM para conter os valores antigos E os novos ao
-- mesmo tempo (senão o UPDATE abaixo falha, pois nenhum dos dois
-- conjuntos de valores por si só cobre origem e destino da conversão).
ALTER TABLE ordens_servico
    MODIFY COLUMN status ENUM(
        'aberta','em_execucao','concluida',
        'recebido','em_diagnostico','aguardando_aprovacao','aguardando_peca','em_servico','pronto','entregue'
    ) NOT NULL DEFAULT 'aberta';

-- Passo 2: converte os valores antigos para os novos
UPDATE ordens_servico SET status = 'recebido'        WHERE status = 'aberta';
UPDATE ordens_servico SET status = 'em_servico'       WHERE status = 'em_execucao';
UPDATE ordens_servico SET status = 'pronto'           WHERE status = 'concluida';

-- Passo 3: agora sim, restringe o ENUM só ao conjunto novo
ALTER TABLE ordens_servico
    MODIFY COLUMN status ENUM('recebido','em_diagnostico','aguardando_aprovacao','aguardando_peca','em_servico','pronto','entregue') NOT NULL DEFAULT 'recebido';

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS agendamento_id INT UNSIGNED NULL AFTER veiculo_id,
    ADD COLUMN IF NOT EXISTS quilometragem_entrada INT UNSIGNED NULL AFTER data_entrada,
    ADD COLUMN IF NOT EXISTS motivo VARCHAR(255) NULL AFTER quilometragem_entrada,
    ADD COLUMN IF NOT EXISTS previsao_entrega DATETIME NULL AFTER reclamacao_cliente,
    ADD COLUMN IF NOT EXISTS responsavel_atendimento_id INT UNSIGNED NULL AFTER previsao_entrega,
    ADD COLUMN IF NOT EXISTS prioridade_manual INT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS prioridade_motivo VARCHAR(255) NULL AFTER prioridade_manual,
    ADD COLUMN IF NOT EXISTS prioridade_alterada_por INT UNSIGNED NULL AFTER prioridade_motivo,
    ADD COLUMN IF NOT EXISTS prioridade_alterada_em DATETIME NULL AFTER prioridade_alterada_por,
    ADD COLUMN IF NOT EXISTS pausada TINYINT(1) NOT NULL DEFAULT 0 AFTER prioridade_alterada_em,
    ADD COLUMN IF NOT EXISTS quilometragem_saida INT UNSIGNED NULL AFTER data_saida,
    ADD COLUMN IF NOT EXISTS valor_final DECIMAL(10,2) NULL AFTER quilometragem_saida,
    ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(60) NULL AFTER valor_final,
    ADD COLUMN IF NOT EXISTS responsavel_entrega_id INT UNSIGNED NULL AFTER forma_pagamento,
    ADD COLUMN IF NOT EXISTS data_limite_retorno DATE NULL AFTER responsavel_entrega_id,
    ADD COLUMN IF NOT EXISTS observacoes_finais TEXT NULL AFTER data_limite_retorno;

ALTER TABLE ordens_servico
    ADD CONSTRAINT fk_os_agendamento FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_os_responsavel_atendimento FOREIGN KEY (responsavel_atendimento_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_os_prioridade_usuario FOREIGN KEY (prioridade_alterada_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_os_responsavel_entrega FOREIGN KEY (responsavel_entrega_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- fila_log (nova tabela)
CREATE TABLE IF NOT EXISTS fila_log (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL,
    acao                ENUM('entrada_fila','prioridade_alterada','pausada','retomada') NOT NULL,
    motivo              VARCHAR(255) NULL,
    usuario_id          INT UNSIGNED NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_filalog_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_filalog_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_filalog_os (ordem_servico_id)
) ENGINE=InnoDB;

-- orcamentos / orcamento_itens (novas tabelas)
CREATE TABLE IF NOT EXISTS orcamentos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL,
    desconto            DECIMAL(10,2) NOT NULL DEFAULT 0,
    total               DECIMAL(10,2) NOT NULL DEFAULT 0,
    validade_dias       INT UNSIGNED NOT NULL DEFAULT 7,
    data_validade       DATE NULL,
    status              ENUM('pendente','aprovado','parcialmente_aprovado','recusado') NOT NULL DEFAULT 'pendente',
    aprovado_em         DATETIME NULL,
    observacoes         TEXT NULL,
    pdf_caminho         VARCHAR(500) NULL,
    criado_por          INT UNSIGNED NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orcamento_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_orcamento_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_orcamento_os (ordem_servico_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orcamento_itens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orcamento_id    INT UNSIGNED NOT NULL,
    tipo            ENUM('peca','servico','mao_de_obra') NOT NULL,
    descricao       VARCHAR(300) NOT NULL,
    quantidade      DECIMAL(10,2) NOT NULL DEFAULT 1,
    valor_unitario  DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_total     DECIMAL(10,2) NOT NULL DEFAULT 0,
    aprovado        TINYINT(1) NOT NULL DEFAULT 1,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orcitem_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_orcitem_orcamento (orcamento_id)
) ENGINE=InnoDB;

-- servicos_realizados: campos de execução
ALTER TABLE servicos_realizados
    ADD COLUMN IF NOT EXISTS mecanico_responsavel_id INT UNSIGNED NULL AFTER descricao,
    ADD COLUMN IF NOT EXISTS data_inicio DATETIME NULL AFTER mecanico_responsavel_id,
    ADD COLUMN IF NOT EXISTS data_conclusao DATETIME NULL AFTER data_inicio;

ALTER TABLE servicos_realizados
    ADD CONSTRAINT fk_serv_mecanico FOREIGN KEY (mecanico_responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- pecas: quantidade
ALTER TABLE pecas
    ADD COLUMN IF NOT EXISTS quantidade DECIMAL(10,2) NOT NULL DEFAULT 1 AFTER descricao;

-- recibos (nova tabela)
CREATE TABLE IF NOT EXISTS recibos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id    INT UNSIGNED NOT NULL UNIQUE,
    pdf_caminho         VARCHAR(500) NOT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recibo_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- anexos: categoria + tipo documento
ALTER TABLE anexos
    MODIFY COLUMN tipo ENUM('foto','audio','documento') NOT NULL,
    ADD COLUMN IF NOT EXISTS categoria ENUM('entrada','avaria','painel_km','durante_servico','peca','documento','outro') NOT NULL DEFAULT 'outro' AFTER tipo;

-- NOTA: "ADD ... IF NOT EXISTS" e "ADD INDEX IF NOT EXISTS" exigem
-- MySQL 8.0.29+ / MariaDB 10.6+. Em versões mais antigas, remova o
-- "IF NOT EXISTS" das cláusulas e rode a migração manualmente,
-- ignorando erros de coluna já existente.
-- As cláusulas "ADD CONSTRAINT ... FOREIGN KEY" NÃO suportam
-- "IF NOT EXISTS" (não é uma sintaxe válida do MySQL/MariaDB) — por
-- isso esta migração deve ser executada apenas UMA VEZ. Rodá-la de
-- novo falhará com "Duplicate foreign key constraint name", o que é
-- esperado e seguro de ignorar.
