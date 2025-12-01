# Implementation Plan

- [x] 1. Atualizar estrutura do banco de dados


  - Criar tabela disponibilidade_prestador
  - Criar tabela bloqueios_agenda
  - Adicionar índices apropriados
  - _Requirements: 3.5, 4.5_




- [ ] 2. Implementar página principal da agenda (prestador/agenda.php)
- [ ] 2.1 Criar estrutura base e autenticação
  - Criar arquivo prestador/agenda.php
  - Implementar autenticação de prestador
  - Conectar ao banco de dados

  - Processar parâmetros GET (mes, ano, status)
  - _Requirements: 1.1_

- [ ] 2.2 Implementar queries de dados
  - Criar query para agendamentos do mês
  - Criar query para próximos agendamentos

  - Criar query para bloqueios do mês
  - Criar query para estatísticas (total mês, semana, próximos 7 dias)
  - _Requirements: 1.1, 5.1, 5.2, 5.3_

- [ ] 2.3 Implementar geração do calendário
  - Calcular primeiro e último dia do mês
  - Gerar array de dias do mês

  - Identificar dias com agendamentos
  - Identificar dias com bloqueios
  - Marcar dia atual
  - _Requirements: 1.1, 1.3, 1.4, 1.5_

- [ ] 2.4 Criar interface HTML do calendário
  - Implementar cards de resumo estatístico

  - Implementar controles de navegação (anterior/próximo)
  - Implementar seletor de filtro por status
  - Implementar grid do calendário com dias da semana
  - Implementar lista de próximos 5 agendamentos
  - _Requirements: 1.1, 1.2, 5.4, 5.5, 6.1_



- [ ] 2.5 Implementar sistema de filtros
  - Aplicar filtro de status nas queries
  - Atualizar contadores por status
  - Manter filtro ao navegar entre meses
  - Atualizar URL com parâmetros de filtro
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_


- [ ] 3. Implementar modal de detalhes do dia
- [ ] 3.1 Criar endpoint AJAX para agendamentos do dia
  - Criar arquivo prestador/agenda_ajax.php
  - Implementar action 'dia' que retorna JSON
  - Buscar agendamentos do dia específico
  - Incluir dados de cliente, serviço e endereço
  - Ordenar por horário

  - _Requirements: 2.1, 2.2_

- [ ] 3.2 Criar modal HTML e estrutura
  - Implementar div modal oculta
  - Criar template para exibir agendamentos
  - Implementar seções: horário, cliente, serviço, endereço, observações
  - Adicionar badge de status
  - Adicionar botão de fechar modal
  - _Requirements: 2.3, 2.4, 2.5_

- [ ] 3.3 Adicionar informações de localização
  - Exibir endereço completo formatado
  - Exibir ponto de referência se disponível
  - Criar link para Google Maps com endereço codificado
  - Exibir informações de contato do cliente
  - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [ ] 4. Implementar página de disponibilidade (prestador/disponibilidade.php)
- [ ] 4.1 Criar estrutura base da página
  - Criar arquivo prestador/disponibilidade.php
  - Implementar autenticação de prestador
  - Criar sidebar com menu atualizado
  - Buscar disponibilidade atual do prestador
  - Buscar bloqueios futuros
  - _Requirements: 3.1_

- [ ] 4.2 Implementar formulário de disponibilidade semanal
  - Criar formulário com 7 dias da semana
  - Adicionar checkbox para marcar dia como disponível
  - Adicionar inputs de horário (início e fim) para cada dia
  - Preencher formulário com dados existentes
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 4.3 Processar salvamento de disponibilidade
  - Validar que horário fim > horário início
  - Validar dias da semana válidos
  - Implementar INSERT ... ON DUPLICATE KEY UPDATE
  - Salvar disponibilidade para cada dia
  - Exibir mensagem de sucesso/erro
  - _Requirements: 3.4, 3.5_

- [ ] 4.4 Implementar formulário de bloqueios
  - Criar formulário para novo bloqueio
  - Adicionar inputs de data início e fim
  - Adicionar select de motivo (férias, folga, compromisso, outro)
  - Adicionar textarea para observações
  - Validar data mínima (hoje)
  - _Requirements: 4.1, 4.2_

- [ ] 4.5 Processar criação e exclusão de bloqueios
  - Validar que data_fim >= data_inicio
  - Validar que datas não são passadas
  - Verificar conflitos com bloqueios existentes
  - Implementar INSERT de novo bloqueio
  - Implementar DELETE de bloqueio existente
  - Exibir mensagens de sucesso/erro
  - _Requirements: 4.2, 4.3, 4.4, 4.5_

- [ ] 4.6 Criar lista de bloqueios existentes
  - Buscar bloqueios futuros do prestador
  - Exibir lista com datas, motivo e observações
  - Adicionar botão de excluir para cada bloqueio
  - Ordenar por data de início
  - _Requirements: 4.1, 4.4_

- [ ] 5. Implementar JavaScript para interatividade (assets/js/agenda.js)
- [ ] 5.1 Criar funções de navegação
  - Implementar função mesAnterior()
  - Implementar função proximoMes()
  - Capturar valores de mês e ano atuais
  - Redirecionar com novos parâmetros mantendo filtro
  - _Requirements: 1.2_

- [ ] 5.2 Implementar clique em dia do calendário
  - Adicionar event listener em todos os dias
  - Capturar data do dia clicado (data-date)
  - Chamar função carregarAgendamentosDia()
  - _Requirements: 2.1_

- [ ] 5.3 Implementar carregamento AJAX
  - Criar função carregarAgendamentosDia(data)
  - Fazer fetch para agenda_ajax.php
  - Processar resposta JSON
  - Chamar função mostrarModalAgendamentos()
  - _Requirements: 2.1, 2.2_

- [ ] 5.4 Implementar exibição do modal
  - Criar função mostrarModalAgendamentos(dados)

  - Gerar HTML dos agendamentos dinamicamente
  - Exibir modal (display: block)
  - Implementar função de fechar modal
  - _Requirements: 2.3_

- [ ] 5.5 Implementar mudança de filtro
  - Adicionar event listener no select de status

  - Redirecionar com novo parâmetro de status
  - Manter mês e ano atuais
  - _Requirements: 6.1, 6.2, 6.3_

- [ ] 6. Adicionar estilos CSS para agenda
- [x] 6.1 Criar estilos para calendário

  - Estilizar grid do calendário (.calendario)
  - Estilizar header com dias da semana
  - Estilizar células de dias (.dia)
  - Criar classes para estados: .dia-atual, .dia-com-agendamento, .dia-bloqueado, .dia-passado
  - Adicionar indicadores visuais (badges de contagem)
  - _Requirements: 1.1, 1.3, 1.4, 1.5_

- [ ] 6.2 Criar estilos para controles e filtros
  - Estilizar barra de navegação (.agenda-controls)
  - Estilizar botões de navegação
  - Estilizar select de filtro
  - Estilizar cards de resumo estatístico
  - _Requirements: 1.2, 6.1_



- [ ] 6.3 Criar estilos para modal
  - Estilizar overlay do modal
  - Estilizar conteúdo do modal (.modal-agendamento)
  - Estilizar detalhes de agendamento (.agendamento-detalhe)
  - Estilizar badges de horário e status
  - Estilizar seções de informação
  - _Requirements: 2.3_

- [ ] 6.4 Criar estilos para disponibilidade e bloqueios
  - Estilizar formulário de disponibilidade (.dia-disponibilidade)
  - Estilizar inputs de horário
  - Estilizar lista de bloqueios (.bloqueios-lista)
  - Estilizar item de bloqueio (.bloqueio-item)
  - _Requirements: 3.1, 4.1_

- [ ] 6.5 Adicionar responsividade e impressão
  - Criar media queries para mobile
  - Adaptar calendário para telas pequenas
  - Criar estilos @media print
  - Ocultar elementos desnecessários na impressão
  - _Requirements: 9.3, 9.4_

- [ ] 7. Atualizar menu em páginas do prestador
- [ ] 7.1 Adicionar link "Agenda" no menu
  - Atualizar sidebar em prestador/index.php
  - Atualizar sidebar em prestador/solicitacoes.php
  - Atualizar sidebar em prestador/servicos.php
  - Atualizar sidebar em prestador/perfil.php
  - _Requirements: 1.1_

- [ ] 8. Implementar funcionalidades adicionais
- [ ] 8.1 Implementar alertas de conflito
  - Verificar sobreposição de horários ao aceitar solicitação
  - Exibir alerta visual se houver conflito
  - Destacar dias com múltiplos agendamentos no calendário
  - Exibir aviso se mais de 3 agendamentos no mesmo dia
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 8.2 Implementar estatísticas de produtividade
  - Calcular total de horas trabalhadas no mês
  - Calcular taxa de ocupação (dias com agendamentos / dias disponíveis)
  - Identificar serviço mais solicitado
  - Calcular média de agendamentos por dia útil
  - Exibir em card ou seção separada
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ] 9. Validar e testar implementação completa
- [ ] 9.1 Testar calendário e navegação
  - Verificar exibição correta do mês atual
  - Testar navegação entre meses
  - Verificar destaque do dia atual
  - Verificar indicadores em dias com agendamentos
  - Testar clique em dia e exibição de modal
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [ ] 9.2 Testar disponibilidade e bloqueios
  - Testar salvamento de disponibilidade semanal
  - Testar validações de horário
  - Testar criação de bloqueios
  - Testar validações de datas
  - Testar exclusão de bloqueios
  - Verificar exibição no calendário
  - _Requirements: 3.1-3.5, 4.1-4.5_

- [ ] 9.3 Testar filtros e interatividade
  - Testar filtro por status
  - Verificar atualização do calendário
  - Testar manutenção de filtro ao navegar
  - Testar carregamento AJAX de agendamentos
  - Testar abertura e fechamento de modal
  - _Requirements: 6.1-6.5, 2.1-2.5_

- [ ] 9.4 Testar responsividade e impressão
  - Testar layout em mobile
  - Testar calendário em telas pequenas
  - Testar funcionalidade de impressão
  - Verificar formatação do PDF/impressão
  - _Requirements: 9.3, 9.4_
