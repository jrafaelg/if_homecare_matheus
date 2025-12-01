# Implementation Plan

- [x] 1. Atualizar estrutura do banco de dados


  - Criar tabela admin_configuracoes no script SQL
  - Adicionar índice para admin_id
  - Definir valores padrão apropriados
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_




- [ ] 2. Refatorar dashboard administrativo (admin/index.php)
- [ ] 2.1 Remover menu de solicitações da sidebar
  - Remover link "Solicitações" do menu lateral


  - Atualizar classe "active" dos outros itens
  - _Requirements: 1.2_



- [ ] 2.2 Remover tabela de últimas solicitações
  - Remover query SQL de últimas solicitações
  - Remover bloco HTML da tabela de solicitações

  - _Requirements: 1.5_



- [ ] 2.3 Adicionar estatísticas de atividade do sistema
  - Criar query SQL para métricas dos últimos 30 dias (novos usuários, atendimentos, média avaliações, volume financeiro)
  - Criar card HTML de atividade do sistema com 4 métricas
  - Mesclar estatísticas extras com array $stats existente


  - _Requirements: 1.3, 1.4_

- [ ] 3. Implementar sistema de relatórios (admin/relatorios.php)
- [ ] 3.1 Criar estrutura base da página de relatórios
  - Criar arquivo admin/relatorios.php
  - Implementar autenticação e conexão com banco


  - Criar sidebar com menu atualizado
  - Implementar formulário de filtros (período e tipo)
  - _Requirements: 2.1, 2.2, 2.3_

- [ ] 3.2 Implementar relatório geral
  - Criar query SQL para métricas gerais do período
  - Criar query SQL para top 10 prestadores


  - Criar query SQL para top 10 clientes
  - Implementar interface HTML com cards de métricas
  - Implementar rankings em duas colunas
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 3.3 Implementar relatório financeiro

  - Criar query SQL para breakdown diário
  - Criar query SQL para análise por tipo de serviço
  - Calcular volume total, atendimentos e ticket médio

  - Implementar interface HTML com resumo financeiro


  - Implementar tabela de breakdown diário
  - Implementar lista de serviços com volumes
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 3.4 Implementar relatório de avaliações

  - Criar query SQL para distribuição de notas
  - Criar query SQL para 20 avaliações recentes
  - Calcular percentuais de cada nota
  - Implementar interface HTML com barras de progresso
  - Implementar lista de avaliações com truncamento de comentários
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 3.5 Adicionar funcionalidade de impressão

  - Adicionar botão de impressão no formulário de filtros
  - Implementar CSS @media print para ocultar elementos desnecessários
  - _Requirements: 2.4_

- [ ] 4. Implementar página de perfil do administrador (admin/perfil.php)
- [ ] 4.1 Criar estrutura base da página de perfil
  - Criar arquivo admin/perfil.php
  - Implementar autenticação e conexão com banco

  - Criar sidebar com menu atualizado
  - Buscar dados do usuário logado
  - _Requirements: 6.1_

- [ ] 4.2 Implementar formulário de dados pessoais
  - Criar formulário HTML para nome, email e telefone
  - Implementar validações (nome obrigatório, email válido)
  - Verificar unicidade de email (exceto próprio usuário)

  - Implementar UPDATE na tabela usuarios
  - Atualizar dados na sessão após salvar
  - Exibir mensagens de erro/sucesso
  - _Requirements: 6.1, 6.2_

- [x] 4.3 Implementar formulário de alteração de senha

  - Criar formulário HTML para senha atual, nova senha e confirmação
  - Implementar validações (senha atual correta, nova senha >= 6 chars, confirmação igual)
  - Verificar senha atual com password_verify()

  - Fazer hash da nova senha com password_hash()


  - Implementar UPDATE na tabela usuarios
  - Exibir mensagens de erro/sucesso
  - _Requirements: 6.3, 6.4_


- [ ] 4.4 Implementar formulário de configurações
  - Criar formulário HTML para timezone, notificações e relatórios automáticos
  - Verificar se já existe configuração para o admin
  - Implementar INSERT se não existe
  - Implementar UPDATE se já existe
  - Buscar configurações ao carregar página


  - Exibir mensagens de erro/sucesso
  - _Requirements: 6.5, 8.1, 8.2, 8.3, 8.4, 8.5_


- [x] 4.5 Adicionar estatísticas rápidas e informações do sistema


  - Criar query SQL para estatísticas do mês (usuários, solicitações, avaliações)
  - Implementar 3 cards de estatísticas rápidas
  - Implementar card de informações do sistema (versão, servidor, PHP, banco)
  - Implementar card de ações rápidas com links


  - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 4.6 Adicionar JavaScript para máscaras e validações


  - Implementar máscara de telefone no formato (XX) XXXXX-XXXX


  - Implementar validação de confirmação de senha em tempo real
  - _Requirements: 7.5_

- [ ] 5. Atualizar estilos CSS para novas funcionalidades
- [x] 5.1 Adicionar estilos para atividade do sistema

  - Criar estilos para .activity-stats, .activity-item, .activity-icon, .activity-info
  - Implementar hover effects e transições
  - _Requirements: 1.3_

- [ ] 5.2 Adicionar estilos para sistema de relatórios
  - Criar estilos para .filters-form, .relatorio-geral, .stat-change
  - Criar estilos para rankings (.ranking-list, .ranking-item, .ranking-position)

  - Criar estilos para relatório financeiro (.financial-summary, .financial-table, .services-financial)
  - Criar estilos para relatório de avaliações (.avaliacoes-summary, .distribuicao-visual, .nota-bar, .progress-bar)
  - Implementar CSS @media print
  - _Requirements: 2.1, 3.1, 4.1, 5.1_

- [ ] 5.3 Adicionar estilos para perfil do administrador
  - Criar estilos para .system-info, .info-item, .quick-actions
  - Criar estilos para .readonly input
  - Adicionar responsividade para mobile

  - _Requirements: 6.1, 7.2, 7.3_

- [ ] 6. Atualizar menus em outras páginas administrativas
- [ ] 6.1 Atualizar sidebar em admin/usuarios.php
  - Remover link "Solicitações"
  - Adicionar link "Relatórios"
  - Adicionar link "Meu Perfil"
  - _Requirements: 1.2_

- [ ] 6.2 Atualizar sidebar em admin/servicos.php
  - Remover link "Solicitações"
  - Adicionar link "Relatórios"
  - Adicionar link "Meu Perfil"
  - _Requirements: 1.2_

- [ ] 7. Validar e testar implementação completa
- [ ] 7.1 Testar dashboard refatorado
  - Verificar que menu "Solicitações" não aparece
  - Verificar 6 cards de estatísticas
  - Verificar card de atividade do sistema
  - Confirmar que tabela de solicitações foi removida
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [ ] 7.2 Testar sistema de relatórios
  - Testar filtros de período e tipo
  - Testar relatório geral (métricas e rankings)
  - Testar relatório financeiro (cálculos e breakdown)
  - Testar relatório de avaliações (distribuição e lista)
  - Testar funcionalidade de impressão
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 3.1-3.5, 4.1-4.5, 5.1-5.5_

- [ ] 7.3 Testar perfil do administrador
  - Testar edição de dados pessoais
  - Testar validação de email duplicado
  - Testar alteração de senha
  - Testar validação de senha atual
  - Testar salvamento de configurações
  - Verificar máscara de telefone
  - Verificar estatísticas rápidas
  - _Requirements: 6.1-6.5, 7.1-7.5, 8.1-8.5_

- [ ] 7.4 Testar responsividade
  - Testar dashboard em mobile
  - Testar relatórios em mobile
  - Testar perfil em mobile
  - _Requirements: Todos_
