# CRM Oficina Mecânica — Chat + Painel Web

Sistema de gestão de oficina mecânica operado principalmente pelo **chat do
Telegram** (o mecânico fala naturalmente — texto, áudio ou foto — e o sistema
interpreta e registra) e gerenciado pelo **painel web** (gestor/atendente):
agenda, fila da oficina, orçamentos com PDF, execução, saída e recibo.

## Escopo

- **MVP (Fase 1 — implementado):** clientes, veículos (placa como
  identificador único), agenda de atendimentos, entrada do veículo com
  geração automática de OS, fila da oficina (FIFO com prioridade manual
  registrada), diagnóstico (manual e sugerido por IA), orçamento rápido com
  itens/desconto/aprovação (total ou parcial) e PDF, execução do serviço,
  peças, garantia, saída do veículo com recibo em PDF, histórico completo por
  placa, anexos (fotos/áudio), log de auditoria do chat.
- **Fase 2 (não implementada — ver `PROXIMOS_PASSOS.md` conceitual abaixo):**
  WhatsApp completo (aprovação por link, avisos automáticos), estoque de
  peças, fornecedores, financeiro, lembretes de manutenção, CRM de
  pós-venda, dashboard gerencial avançado, permissões refinadas por usuário.

## Stack

- PHP 8.1+ (orientado a objetos, PSR-4, sem framework — camadas
  Controller → Service → Repository)
- MySQL 8+ / MariaDB 10.5+
- Telegram Bot API (webhook)
- Whisper (OpenAI) para transcrição de áudio
- Claude (Anthropic Messages API) para interpretar as mensagens do chat e
  sugerir diagnósticos — trocável por outro provedor via `App\Nlp\NlpInterface`
- Dompdf para gerar os PDFs de orçamento e recibo

## Estrutura de pastas

```
bootstrap.php              Autoload, .env, timezone, helpers
bin/configurar_webhook.php Script CLI para registrar o webhook no Telegram
database/schema.sql        Schema completo (instalação nova)
database/migrations/       Migrações incrementais (bancos já existentes)
public/                    Front controllers (painel web + webhook.php)
resources/views/           Partials de layout do painel
src/Config/                Env, Database (PDO), Container (DI simples)
src/Repository/            Uma classe por entidade, só prepared statements
src/Service/               Regras de negócio
src/Controller/            Controllers finos do painel web
src/Nlp/                   Interface de NLP + adaptador Claude
src/Telegram/              Cliente da Bot API
src/Chat/                  ChatOrchestrator — o "cérebro" do bot
src/Auth/                  Autenticação do painel
storage/                   Uploads (fotos/áudio/PDF) e logs — fora do Git
```

## Instalação

### 1. Requisitos

- PHP 8.1+ com extensões `pdo_mysql`, `curl`, `json`, `mbstring`
- MySQL 8+ ou MariaDB 10.5+
- Composer
- Um domínio com HTTPS (o Telegram exige HTTPS para o webhook)

### 2. Clonar e instalar dependências

```bash
composer install
```

### 3. Banco de dados

Instalação nova:

```bash
mysql -u root -p < database/schema.sql
```

Se você já tinha uma instalação anterior a este escopo expandido (Agenda,
Fila, Orçamento), rode a migração em vez de recriar o banco:

```bash
mysql -u root -p oficina < database/migrations/002_expandir_mvp.sql
```

### 4. Configuração

```bash
cp .env.example .env
```

Preencha `.env` com:

- Dados de conexão do banco (`DB_*`)
- Token do bot do Telegram (crie com [@BotFather](https://t.me/BotFather)) em `TELEGRAM_BOT_TOKEN`
- Um segredo aleatório em `TELEGRAM_WEBHOOK_SECRET`
- Sua chave da API da OpenAI em `WHISPER_API_KEY` (transcrição de áudio)
- Sua chave da API da Anthropic em `NLP_API_KEY` (interpretação das mensagens)
- `APP_URL` com o domínio público da aplicação (deve responder em HTTPS)

### 5. Registrar o webhook do Telegram

Aponte `public/` do projeto como document root do seu servidor web
(Apache/Nginx) e então rode:

```bash
php bin/configurar_webhook.php
```

Isso registra `https://SEU_DOMINIO/webhook.php` como endpoint do bot.

### 6. Primeiro acesso ao painel

Acesse `https://SEU_DOMINIO/login.php` com:

- **E-mail:** `admin@oficina.local`
- **Senha:** `troque-esta-senha`

**Troque essa senha imediatamente** (crie um novo usuário admin em
"Usuários" com senha própria, ou gere um novo hash com
`password_hash('sua-senha', PASSWORD_BCRYPT)` e atualize direto no banco).

### 7. Primeiro contato do mecânico com o bot

Basta o mecânico mandar uma mensagem para o bot no Telegram — o sistema
cria automaticamente um usuário com papel `mecanico` no primeiro contato.
Para promovê-lo a `atendente`/`admin` (acesso ao painel web), use a tela
"Usuários" (requer papel admin).

## Como o chat entende as mensagens

O mecânico pode escrever (ou falar) livremente, por exemplo:

> "Corolla prata 2018 ABC1D23 cliente Carlos 85 mil km, barulho na suspensão"

O `ChatOrchestrator` (`src/Chat/ChatOrchestrator.php`) manda o texto para a
IA (`src/Nlp/ClaudeNlpAdapter.php`), que devolve a intenção e os dados em
JSON. O sistema então executa a ação correspondente (cadastrar veículo,
abrir OS, registrar diagnóstico/serviço/peça/garantia, marcar entregue,
consultar histórico...).

**Regra de ouro:** se a placa não for identificada com certeza — por
exemplo, dois carros do mesmo modelo/cor em aberto ao mesmo tempo — o bot
**nunca adivinha**: ele pergunta de volta e guarda o contexto da conversa
(tabela `conversa_contexto`) até receber a resposta.

## Fila da oficina

A ordem padrão é sempre "quem entrou primeiro é atendido primeiro"
(`data_entrada`). Qualquer prioridade manual é uma **exceção registrada**:
o painel exige motivo e grava quem alterou (`fila_log`). OS pausadas
(aguardando peça/aprovação) saem da vez sem perder a data real de entrada,
permitindo medir depois tempo de permanência e gargalos.

## Segurança

- Todas as consultas usam PDO com prepared statements.
- Senhas do painel usam `password_hash`/`password_verify` (bcrypt).
- O webhook do Telegram valida o header `X-Telegram-Bot-Api-Secret-Token`.
- `.env` nunca deve ser versionado (já está no `.gitignore`).
- Rode o painel sempre atrás de HTTPS.

## Limitações conhecidas / próximos passos

- A view de Agenda usa listas simples por dia/semana/mês (sem calendário
  drag-and-drop).
- Aprovação de orçamento por link (WhatsApp) e avisos automáticos ficam
  para a Fase 2, junto com estoque, fornecedores e financeiro.
- O adaptador de NLP assume respostas em JSON estritas do Claude; ajuste o
  prompt em `ClaudeNlpAdapter` se trocar de modelo/provedor.
