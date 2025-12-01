# Requirements Document

## Introduction

Esta especificação define os requisitos para finalizar a área administrativa do sistema de agendamento de serviços de saúde domiciliar. O objetivo é remover o menu de solicitações do dashboard administrativo e implementar um sistema completo de relatórios gerenciais que permita ao administrador visualizar métricas, estatísticas e análises detalhadas sobre o funcionamento da plataforma.

## Glossary

- **Admin Dashboard**: Painel administrativo principal que exibe visão geral do sistema
- **Sistema de Relatórios**: Módulo que gera análises e estatísticas sobre usuários, serviços, solicitações e avaliações
- **Sidebar**: Menu lateral de navegação do painel administrativo
- **Métricas**: Indicadores quantitativos sobre o desempenho do sistema
- **Período de Análise**: Intervalo de tempo selecionado para geração de relatórios (7, 30, 90 ou 365 dias)

## Requirements

### Requirement 1

**User Story:** Como administrador, eu quero um dashboard limpo e focado em métricas gerais, para que eu possa ter uma visão rápida do sistema sem informações desnecessárias sobre solicitações individuais.

#### Acceptance Criteria

1. WHEN o administrador acessa o dashboard, THE Admin Dashboard SHALL exibir cards de estatísticas gerais sem incluir listagem de solicitações individuais
2. THE Admin Dashboard SHALL remover o link "Solicitações" do menu lateral (sidebar)
3. THE Admin Dashboard SHALL exibir métricas de atividade do sistema incluindo novos usuários, atendimentos concluídos, média de avaliações e volume financeiro dos últimos 30 dias
4. THE Admin Dashboard SHALL manter a tabela de últimos usuários cadastrados
5. THE Admin Dashboard SHALL substituir a tabela de últimas solicitações por um card de atividade do sistema

### Requirement 2

**User Story:** Como administrador, eu quero acessar relatórios detalhados do sistema, para que eu possa analisar o desempenho da plataforma em diferentes períodos e categorias.

#### Acceptance Criteria

1. WHEN o administrador clica em "Relatórios" no menu, THE Sistema de Relatórios SHALL exibir uma página dedicada com filtros de período e tipo de relatório
2. THE Sistema de Relatórios SHALL permitir seleção de período entre 7, 30, 90 ou 365 dias
3. THE Sistema de Relatórios SHALL oferecer três tipos de relatórios: Geral, Financeiro e Avaliações
4. THE Sistema de Relatórios SHALL incluir funcionalidade de impressão dos relatórios
5. WHEN o administrador altera filtros, THE Sistema de Relatórios SHALL atualizar automaticamente os dados exibidos

### Requirement 3

**User Story:** Como administrador, eu quero visualizar um relatório geral do sistema, para que eu possa entender o panorama completo de usuários, atendimentos e performance.

#### Acceptance Criteria

1. WHEN o relatório geral é selecionado, THE Sistema de Relatórios SHALL exibir cards com total de usuários, clientes, prestadores, atendimentos concluídos, volume financeiro e média de avaliações
2. THE Sistema de Relatórios SHALL exibir ranking dos top 10 prestadores ordenados por número de atendimentos e média de avaliação
3. THE Sistema de Relatórios SHALL exibir ranking dos top 10 clientes ordenados por número de solicitações e volume total
4. THE Sistema de Relatórios SHALL destacar visualmente o crescimento no período (novos usuários, novas solicitações)
5. THE Sistema de Relatórios SHALL calcular e exibir todas as métricas baseadas no período selecionado

### Requirement 4

**User Story:** Como administrador, eu quero visualizar um relatório financeiro detalhado, para que eu possa acompanhar o volume de transações e receita gerada pela plataforma.

#### Acceptance Criteria

1. WHEN o relatório financeiro é selecionado, THE Sistema de Relatórios SHALL exibir resumo com volume total, número de atendimentos e ticket médio do período
2. THE Sistema de Relatórios SHALL exibir tabela com breakdown diário mostrando data, número de atendimentos, volume do dia e ticket médio
3. THE Sistema de Relatórios SHALL exibir análise por tipo de serviço mostrando total de atendimentos, volume total e ticket médio por serviço
4. THE Sistema de Relatórios SHALL ordenar serviços por volume total em ordem decrescente
5. THE Sistema de Relatórios SHALL considerar apenas solicitações com status "concluída" nos cálculos financeiros

### Requirement 5

**User Story:** Como administrador, eu quero visualizar um relatório de avaliações, para que eu possa monitorar a satisfação dos clientes e qualidade dos serviços prestados.

#### Acceptance Criteria

1. WHEN o relatório de avaliações é selecionado, THE Sistema de Relatórios SHALL exibir distribuição visual de notas de 1 a 5 estrelas com quantidade e percentual
2. THE Sistema de Relatórios SHALL exibir barras de progresso proporcionais ao percentual de cada nota
3. THE Sistema de Relatórios SHALL listar as 20 avaliações mais recentes do período com nota, cliente, prestador, serviço, data e comentário
4. THE Sistema de Relatórios SHALL truncar comentários longos em 200 caracteres com indicação de continuação
5. THE Sistema de Relatórios SHALL calcular percentuais baseados no total de avaliações do período selecionado

### Requirement 6

**User Story:** Como administrador, eu quero gerenciar meu perfil e configurações, para que eu possa personalizar minhas preferências e manter meus dados atualizados.

#### Acceptance Criteria

1. WHEN o administrador acessa "Meu Perfil", THE Sistema SHALL exibir formulário para edição de dados pessoais (nome, email, telefone)
2. THE Sistema SHALL validar que o email não está sendo usado por outro usuário antes de permitir alteração
3. THE Sistema SHALL oferecer formulário separado para alteração de senha com validação de senha atual
4. THE Sistema SHALL exigir que nova senha tenha no mínimo 6 caracteres
5. THE Sistema SHALL permitir configuração de timezone, notificações por email e relatórios automáticos semanais

### Requirement 7

**User Story:** Como administrador, eu quero visualizar estatísticas rápidas no meu perfil, para que eu possa acompanhar minha atividade administrativa.

#### Acceptance Criteria

1. WHEN o administrador acessa seu perfil, THE Sistema SHALL exibir cards com usuários cadastrados no mês, solicitações do mês e avaliações do mês
2. THE Sistema SHALL exibir informações do sistema incluindo versão, última atualização, servidor, versão PHP e banco de dados
3. THE Sistema SHALL oferecer ações rápidas com links para gerenciar usuários, serviços, relatórios e voltar ao dashboard
4. THE Sistema SHALL exibir data de cadastro do administrador em formato brasileiro (dd/mm/yyyy)
5. THE Sistema SHALL aplicar máscara de telefone automaticamente no formato (XX) XXXXX-XXXX

### Requirement 8

**User Story:** Como administrador, eu quero que minhas configurações sejam persistidas no banco de dados, para que eu não precise reconfigurá-las a cada acesso.

#### Acceptance Criteria

1. WHEN o administrador salva configurações pela primeira vez, THE Sistema SHALL criar registro na tabela admin_configuracoes
2. WHEN o administrador já possui configurações salvas, THE Sistema SHALL atualizar o registro existente
3. THE Sistema SHALL armazenar timezone, notificacoes_email e relatorios_automaticos na tabela admin_configuracoes
4. THE Sistema SHALL carregar configurações salvas ao exibir o formulário de configurações
5. THE Sistema SHALL usar valores padrão (timezone: America/Sao_Paulo, notificacoes_email: true, relatorios_automaticos: false) quando não houver configurações salvas
