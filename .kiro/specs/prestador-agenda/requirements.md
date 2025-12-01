# Requirements Document

## Introduction

Esta especificação define os requisitos para implementar um sistema de agenda completo para prestadores de serviços de saúde domiciliar. O objetivo é permitir que prestadores gerenciem sua disponibilidade, visualizem seus agendamentos em formato de calendário, e controlem horários disponíveis e bloqueados para melhor organização de seus atendimentos.

## Glossary

- **Agenda**: Sistema de gerenciamento de disponibilidade e agendamentos do prestador
- **Disponibilidade**: Horários em que o prestador está disponível para atender
- **Bloqueio**: Período em que o prestador não está disponível para novos agendamentos
- **Calendário**: Visualização mensal dos agendamentos e disponibilidade
- **Slot de Horário**: Intervalo de tempo específico (ex: 08:00-09:00)
- **Agendamento**: Solicitação de serviço aceita e confirmada com data e horário

## Requirements

### Requirement 1

**User Story:** Como prestador, eu quero visualizar minha agenda em formato de calendário, para que eu possa ter uma visão clara dos meus compromissos mensais.

#### Acceptance Criteria

1. WHEN o prestador acessa a página de agenda, THE Sistema SHALL exibir um calendário mensal com o mês atual
2. THE Sistema SHALL permitir navegação entre meses (anterior/próximo)
3. THE Sistema SHALL destacar visualmente o dia atual no calendário
4. THE Sistema SHALL exibir indicadores visuais nos dias com agendamentos confirmados
5. THE Sistema SHALL exibir indicadores visuais nos dias com bloqueios de disponibilidade

### Requirement 2

**User Story:** Como prestador, eu quero visualizar os detalhes dos meus agendamentos diários, para que eu possa me preparar adequadamente para cada atendimento.

#### Acceptance Criteria

1. WHEN o prestador clica em um dia do calendário, THE Sistema SHALL exibir lista de agendamentos daquele dia
2. THE Sistema SHALL exibir para cada agendamento: horário, cliente, serviço, endereço e status
3. THE Sistema SHALL permitir visualizar detalhes completos do agendamento em modal
4. THE Sistema SHALL exibir informações de contato do cliente no modal de detalhes
5. THE Sistema SHALL ordenar agendamentos por horário de início

### Requirement 3

**User Story:** Como prestador, eu quero gerenciar minha disponibilidade semanal padrão, para que o sistema saiba quando estou disponível para novos agendamentos.

#### Acceptance Criteria

1. WHEN o prestador acessa configurações de disponibilidade, THE Sistema SHALL exibir formulário com os 7 dias da semana
2. THE Sistema SHALL permitir marcar cada dia como disponível ou indisponível
3. WHEN um dia está marcado como disponível, THE Sistema SHALL permitir definir horário de início e fim
4. THE Sistema SHALL validar que horário de fim seja posterior ao horário de início
5. THE Sistema SHALL salvar disponibilidade padrão na tabela disponibilidade_prestador

### Requirement 4

**User Story:** Como prestador, eu quero bloquear datas específicas, para que eu possa indicar períodos de férias, folgas ou compromissos pessoais.

#### Acceptance Criteria

1. WHEN o prestador acessa bloqueios de agenda, THE Sistema SHALL exibir lista de bloqueios futuros
2. THE Sistema SHALL permitir criar novo bloqueio informando data inicial, data final e motivo
3. THE Sistema SHALL validar que data final seja igual ou posterior à data inicial
4. THE Sistema SHALL permitir excluir bloqueios futuros
5. THE Sistema SHALL salvar bloqueios na tabela bloqueios_agenda

### Requirement 5

**User Story:** Como prestador, eu quero ver um resumo da minha agenda, para que eu possa ter uma visão rápida dos próximos compromissos.

#### Acceptance Criteria

1. WHEN o prestador acessa a agenda, THE Sistema SHALL exibir card com total de agendamentos do mês
2. THE Sistema SHALL exibir card com agendamentos da semana atual
3. THE Sistema SHALL exibir card com próximos agendamentos (próximos 7 dias)
4. THE Sistema SHALL exibir lista dos 5 próximos agendamentos com data, horário e cliente
5. THE Sistema SHALL destacar agendamentos do dia atual

### Requirement 6

**User Story:** Como prestador, eu quero filtrar minha agenda por status, para que eu possa focar em agendamentos específicos.

#### Acceptance Criteria

1. THE Sistema SHALL permitir filtrar agendamentos por status: todos, aceita, em_andamento, concluida
2. WHEN um filtro é aplicado, THE Sistema SHALL atualizar o calendário mostrando apenas dias com agendamentos do status selecionado
3. THE Sistema SHALL atualizar a lista de agendamentos ao clicar em um dia respeitando o filtro
4. THE Sistema SHALL manter o filtro selecionado ao navegar entre meses
5. THE Sistema SHALL exibir contador de agendamentos por status

### Requirement 7

**User Story:** Como prestador, eu quero visualizar informações de localização dos agendamentos, para que eu possa planejar meus deslocamentos.

#### Acceptance Criteria

1. WHEN o prestador visualiza detalhes de um agendamento, THE Sistema SHALL exibir endereço completo do atendimento
2. THE Sistema SHALL exibir ponto de referência se disponível
3. THE Sistema SHALL exibir link para abrir endereço no Google Maps
4. THE Sistema SHALL calcular e exibir distância estimada do prestador ao local (se raio_atendimento configurado)
5. THE Sistema SHALL agrupar agendamentos do mesmo dia por proximidade geográfica quando possível

### Requirement 8

**User Story:** Como prestador, eu quero receber alertas sobre conflitos de horário, para que eu possa evitar sobrecarga de trabalho.

#### Acceptance Criteria

1. WHEN o prestador aceita uma nova solicitação, THE Sistema SHALL verificar se há conflito com agendamentos existentes
2. THE Sistema SHALL exibir alerta visual se houver sobreposição de horários
3. THE Sistema SHALL permitir que o prestador decida se aceita mesmo com conflito
4. THE Sistema SHALL destacar no calendário dias com múltiplos agendamentos
5. THE Sistema SHALL exibir aviso se o prestador tiver mais de 3 agendamentos no mesmo dia

### Requirement 9

**User Story:** Como prestador, eu quero exportar minha agenda, para que eu possa ter um backup ou compartilhar com terceiros.

#### Acceptance Criteria

1. THE Sistema SHALL permitir exportar agenda do mês em formato PDF
2. THE Sistema SHALL incluir no PDF: calendário visual, lista de agendamentos e bloqueios
3. THE Sistema SHALL permitir imprimir agenda diretamente do navegador
4. THE Sistema SHALL formatar impressão de forma otimizada removendo elementos desnecessários
5. THE Sistema SHALL incluir informações de contato dos clientes na exportação

### Requirement 10

**User Story:** Como prestador, eu quero visualizar estatísticas da minha agenda, para que eu possa analisar minha produtividade.

#### Acceptance Criteria

1. THE Sistema SHALL exibir total de horas trabalhadas no mês
2. THE Sistema SHALL exibir taxa de ocupação (dias com agendamentos / dias disponíveis)
3. THE Sistema SHALL exibir serviço mais solicitado no período
4. THE Sistema SHALL exibir gráfico de agendamentos por dia da semana
5. THE Sistema SHALL calcular média de agendamentos por dia útil
