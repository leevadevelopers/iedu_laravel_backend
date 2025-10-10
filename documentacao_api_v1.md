# Documentação da API v1 - iEDU Laravel Backend

## 📋 Sumário

1. [Visão Geral do Sistema](#visão-geral-do-sistema)
2. [Módulos do Sistema](#módulos-do-sistema)
3. [Autenticação e Segurança](#autenticação-e-segurança)
4. [Roles e Permissões](#roles-e-permissões)
5. [Configuração Inicial](#configuração-inicial)
6. [Form Engine iEDU](#form-engine-iedu)
7. [Rotas da API](#rotas-da-api)
8. [Controllers](#controllers)
9. [Funcionalidades Principais](#funcionalidades-principais)
10. [Padrões e Convenções](#padrões-e-convenções)

---

## 🔍 Visão Geral do Sistema

O **iEDU Laravel Backend** é uma aplicação Laravel 12 multi-tenant para gestão educacional completa, desenvolvida com arquitetura modular e orientada a serviços. A aplicação gerencia diversos aspectos de uma instituição educacional, incluindo:

- Gestão Acadêmica
- Sistema de Avaliação
- Transporte Escolar
- Biblioteca
- Finanças
- Formulários Dinâmicos
- Sistema de Informações Estudantis

### Tecnologias Principais

- **Framework**: Laravel 12
- **Autenticação**: JWT (tymon/jwt-auth)
- **Permissões**: Spatie Laravel Permission
- **Multi-tenancy**: Sistema customizado
- **Auditoria**: Laravel Auditing
- **Notificações**: Twilio SDK, Pusher
- **QR Codes**: Endroid QR Code

### Base URL

```
https://api.iedu.com/api/v1
```

### Arquitetura

- **Multi-Tenancy**: Isolamento de dados por instituição
- **Service Layer**: Lógica de negócio encapsulada
- **RESTful API**: Padrões REST para todas as rotas
- **Observer Pattern**: Auditoria automática
- **Workflow Engine**: Automação de processos

---

## 🏗️ Módulos do Sistema

### 1. **Módulo de Autenticação (Auth)**

Gerencia login, logout, recuperação de senha e tokens JWT.

**Endpoints principais:**
- `POST /auth/sign-in` - Login
- `POST /auth/sign-up` - Registro
- `POST /auth/logout` - Logout
- `POST /auth/refresh` - Refresh Token
- `GET /auth/me` - Dados do usuário autenticado
- `POST /auth/change-password` - Alterar senha
- `POST /auth/forgot-password` - Recuperação de senha
- `POST /auth/reset-password` - Reset de senha

---

### 2. **Módulo de Usuários (Users)**

Gerenciamento completo de usuários e perfis.

**Endpoints principais:**

#### Usuários
- `GET /users` - Listar usuários
- `GET /users/{id}` - Obter usuário específico
- `GET /users/lookup` - Busca rápida de usuários
- `GET /users/active` - Usuários ativos

#### Perfil do Usuário
- `GET /user/profile` - Obter perfil
- `PUT /user/profile` - Atualizar perfil
- `PATCH /user/profile/fields` - Atualizar campos específicos
- `POST /user/avatar` - Upload de avatar
- `POST /user/switch-tenant` - Trocar organização
- `GET /user/tenants` - Listar organizações do usuário

#### Análises
- `GET /users-analytics/statistics` - Estatísticas de usuários
- `GET /users-analytics/activity` - Atividades dos usuários

---

### 3. **Módulo de Tenants (Multi-tenancy)**

Gerenciamento de organizações e multi-tenancy.

**Endpoints principais:**
- `GET /tenants` - Listar organizações
- `POST /tenants` - Criar organização
- `GET /tenants/current` - Organização atual
- `POST /tenants/switch` - Trocar organização

#### Usuários do Tenant
- `GET /tenants/users` - Listar usuários
- `POST /tenants/users` - Adicionar usuário
- `DELETE /tenants/users/{userId}` - Remover usuário
- `PUT /tenants/users/{userId}/role` - Atualizar role

#### Configurações e Branding
- `GET /tenants/settings` - Obter configurações
- `PUT /tenants/settings` - Atualizar configurações
- `GET /tenants/branding` - Obter branding
- `PUT /tenants/branding` - Atualizar branding

#### Convites
- `GET /tenants/invitations` - Listar convites
- `POST /tenants/invitations` - Enviar convite
- `DELETE /tenants/invitations/{invitation}` - Cancelar convite
- `POST /tenants/invitations/accept` - Aceitar convite

---

### 4. **Módulo Acadêmico (Academic)**

Gerenciamento completo do sistema acadêmico.

**Endpoints principais:**

#### Disciplinas
- `GET /subjects` - Listar disciplinas
- `POST /subjects` - Criar disciplina
- `GET /subjects/{id}` - Obter disciplina
- `PUT /subjects/{id}` - Atualizar disciplina
- `DELETE /subjects/{id}` - Excluir disciplina
- `GET /subjects/core` - Disciplinas obrigatórias
- `GET /subjects/electives` - Disciplinas optativas
- `GET /subjects/grade-level/{gradeLevel}` - Por nível

#### Turmas
- `GET /classes` - Listar turmas
- `POST /classes` - Criar turma
- `GET /classes/{class}` - Obter turma
- `PUT /classes/{class}` - Atualizar turma
- `DELETE /classes/{class}` - Excluir turma
- `GET /classes/teacher` - Turmas do professor
- `POST /classes/{class}/students` - Matricular estudante
- `DELETE /classes/{class}/students` - Remover estudante
- `GET /classes/{class}/roster` - Lista da turma

#### Professores
- `GET /teachers` - Listar professores
- `POST /teachers` - Criar professor
- `GET /teachers/{id}` - Obter professor
- `PUT /teachers/{id}` - Atualizar professor
- `DELETE /teachers/{id}` - Excluir professor
- `GET /teachers/search` - Buscar professores
- `GET /teachers/{id}/workload` - Carga horária
- `GET /teachers/{id}/classes` - Turmas do professor
- `GET /teachers/{id}/statistics` - Estatísticas
- `GET /teachers/{id}/dashboard` - Dashboard do professor

#### Sistemas de Notas
- `GET /grading-systems` - Listar sistemas
- `POST /grading-systems` - Criar sistema
- `GET /grading-systems/{gradingSystem}` - Obter sistema
- `PUT /grading-systems/{gradingSystem}` - Atualizar
- `DELETE /grading-systems/{gradingSystem}` - Excluir
- `POST /grading-systems/{gradingSystem}/set-primary` - Definir como principal

#### Escalas de Notas
- `GET /grade-scales` - Listar escalas
- `POST /grade-scales` - Criar escala
- `GET /grade-scales/{gradeScale}` - Obter escala
- `PUT /grade-scales/{gradeScale}` - Atualizar
- `DELETE /grade-scales/{gradeScale}` - Excluir
- `POST /grade-scales/{gradeScale}/set-default` - Definir padrão

#### Lançamento de Notas
- `GET /grade-entries` - Listar notas
- `POST /grade-entries` - Lançar nota
- `POST /grade-entries/bulk` - Lançamento em lote
- `GET /grade-entries/student` - Notas do estudante
- `GET /grade-entries/class` - Notas da turma
- `GET /grade-entries/gpa/calculate` - Calcular GPA

#### Análises Acadêmicas
- `GET /analytics/academic-overview` - Visão geral
- `GET /analytics/grade-distribution` - Distribuição de notas
- `GET /analytics/subject-performance` - Desempenho por disciplina
- `GET /analytics/teacher-stats` - Estatísticas de professores
- `GET /analytics/class-stats/{class}` - Estatísticas da turma

#### Operações em Lote
- `POST /bulk/class-creation` - Criar turmas em lote
- `POST /bulk/student-enrollment` - Matricular em lote
- `POST /bulk/grade-import` - Importar notas
- `POST /bulk/report-cards` - Gerar boletins

---

### 5. **Módulo de Escolas (Schools)**

Gerenciamento de escolas e anos letivos.

**Endpoints principais:**

#### Escolas
- `GET /schools` - Listar escolas
- `POST /schools` - Criar escola
- `GET /schools/{school}` - Obter escola
- `PUT /schools/{school}` - Atualizar escola
- `DELETE /schools/{school}` - Excluir escola
- `GET /schools/{school}/dashboard` - Dashboard
- `GET /schools/{school}/statistics` - Estatísticas
- `GET /schools/{school}/students` - Estudantes da escola

#### Anos Acadêmicos
- `GET /academic-years` - Listar anos
- `POST /academic-years` - Criar ano
- `GET /academic-years/{academicYear}` - Obter ano
- `PUT /academic-years/{academicYear}` - Atualizar
- `DELETE /academic-years/{academicYear}` - Excluir
- `GET /academic-years/by-school/{schoolId}` - Por escola
- `GET /academic-years/current/{schoolId}` - Ano atual
- `POST /academic-years/{academicYear}/set-as-current` - Definir atual
- `POST /academic-years/bulk-create` - Criar em lote

#### Períodos Acadêmicos
- `GET /academic-terms` - Listar períodos
- `POST /academic-terms` - Criar período
- `GET /academic-terms/{academicTerm}` - Obter período
- `PUT /academic-terms/{academicTerm}` - Atualizar
- `DELETE /academic-terms/{academicTerm}` - Excluir
- `POST /academic-terms/{academicTerm}/set-as-current` - Definir atual

---

### 6. **Módulo de Estudantes (Students)**

Gerenciamento completo de estudantes.

**Endpoints principais:**

#### Estudantes
- `GET /students` - Listar estudantes
- `POST /students` - Criar estudante
- `GET /students/{student}` - Obter estudante
- `PUT /students/{student}` - Atualizar estudante
- `DELETE /students/{student}` - Excluir estudante
- `GET /students/{student}/academic-summary` - Resumo acadêmico
- `POST /students/{student}/transfer` - Transferir estudante

#### Documentos
- `GET /student-documents` - Listar documentos
- `POST /student-documents` - Criar documento
- `POST /student-documents/upload-file` - Upload de arquivo
- `GET /student-documents/{document}/download` - Download
- `GET /student-documents/by-student/{studentId}` - Por estudante
- `GET /student-documents/requiring-attention` - Requerem atenção
- `POST /student-documents/bulk-update-status` - Atualizar status em lote

#### Matrículas
- `GET /student-enrollments` - Listar matrículas
- `POST /student-enrollments` - Criar matrícula
- `GET /student-enrollments/by-student/{studentId}` - Por estudante
- `GET /student-enrollments/current/{studentId}` - Matrícula atual
- `POST /student-enrollments/bulk/enroll` - Matricular em lote
- `POST /student-enrollments/bulk/transfer` - Transferir em lote

#### Relacionamentos Familiares
- `GET /family-relationships` - Listar relacionamentos
- `POST /family-relationships` - Criar relacionamento
- `GET /family-relationships/by-student/{studentId}` - Por estudante
- `GET /family-relationships/primary-contact/{studentId}` - Contato principal
- `GET /family-relationships/emergency-contacts/{studentId}` - Contatos de emergência
- `POST /family-relationships/set-primary-contact/{studentId}` - Definir contato principal

---

### 7. **Módulo de Avaliação (Assessment)**

Sistema completo de avaliações e notas.

**Endpoints principais:**

#### Períodos de Avaliação
- `GET /v1/assessments/terms` - Listar períodos
- `POST /v1/assessments/terms` - Criar período
- `GET /v1/assessments/terms/current` - Período atual
- `GET /v1/assessments/terms/{assessmentTerm}` - Obter período
- `PUT /v1/assessments/terms/{assessmentTerm}` - Atualizar
- `DELETE /v1/assessments/terms/{assessmentTerm}` - Excluir
- `POST /v1/assessments/terms/{assessmentTerm}/publish` - Publicar
- `POST /v1/assessments/terms/{assessmentTerm}/activate` - Ativar

#### Tipos de Avaliação
- `GET /v1/assessments/types` - Listar tipos
- `POST /v1/assessments/types` - Criar tipo
- `GET /v1/assessments/types/active` - Tipos ativos
- `GET /v1/assessments/types/{assessmentType}` - Obter tipo
- `PUT /v1/assessments/types/{assessmentType}` - Atualizar
- `DELETE /v1/assessments/types/{assessmentType}` - Excluir

#### Avaliações
- `GET /v1/assessments` - Listar avaliações
- `POST /v1/assessments` - Criar avaliação
- `GET /v1/assessments/{assessment}` - Obter avaliação
- `PUT /v1/assessments/{assessment}` - Atualizar
- `DELETE /v1/assessments/{assessment}` - Excluir
- `PATCH /v1/assessments/{assessment}/status` - Atualizar status
- `POST /v1/assessments/{assessment}/lock` - Bloquear

#### Notas
- `GET /v1/assessments/grades` - Listar notas
- `POST /v1/assessments/grades` - Lançar nota
- `GET /v1/assessments/grades/student/{studentId}` - Notas do estudante
- `POST /v1/assessments/grades/bulk-import` - Importar em lote
- `POST /v1/assessments/{assessment}/grades/publish` - Publicar notas

#### Escalas de Notas
- `GET /v1/assessments/grade-scales` - Listar escalas
- `POST /v1/assessments/grade-scales` - Criar escala
- `GET /v1/assessments/grade-scales/default` - Escala padrão
- `POST /v1/assessments/grade-scales/{gradeScale}/convert` - Converter nota

#### Boletins
- `GET /v1/assessments/gradebooks` - Listar boletins
- `POST /v1/assessments/gradebooks` - Criar boletim
- `GET /v1/assessments/gradebooks/{gradebook}` - Obter boletim
- `GET /v1/assessments/gradebooks/{gradebook}/download` - Download
- `POST /v1/assessments/gradebooks/{gradebook}/approve` - Aprovar

#### Relatórios
- `GET /v1/assessments/reports/class/{classId}/term/{termId}/grades-summary` - Resumo da turma
- `GET /v1/assessments/reports/student/{studentId}/term/{termId}/transcript` - Histórico escolar

---

### 8. **Módulo de Biblioteca (Library)**

Gerenciamento completo da biblioteca escolar.

**Endpoints principais:**

#### Livros
- `GET /library/books` - Listar livros
- `POST /library/books` - Criar livro
- `GET /library/books/search` - Buscar livros
- `GET /library/books/{book}` - Obter livro
- `PUT /library/books/{book}` - Atualizar livro
- `DELETE /library/books/{book}` - Excluir livro
- `GET /library/books/{book}/copies` - Obter cópias
- `POST /library/books/{book}/copies` - Adicionar cópia

#### Empréstimos
- `GET /library/loans` - Listar empréstimos
- `POST /library/loans` - Criar empréstimo
- `GET /library/loans/my-loans` - Meus empréstimos
- `GET /library/loans/overdue` - Empréstimos atrasados
- `GET /library/loans/{loan}` - Obter empréstimo
- `PATCH /library/loans/{loan}/return` - Devolver livro
- `PATCH /library/loans/{loan}/renew` - Renovar empréstimo

#### Reservas
- `GET /library/reservations` - Listar reservas
- `POST /library/reservations` - Criar reserva
- `GET /library/reservations/my-reservations` - Minhas reservas
- `GET /library/reservations/{reservation}` - Obter reserva
- `PATCH /library/reservations/{reservation}/cancel` - Cancelar reserva
- `PATCH /library/reservations/{reservation}/ready` - Marcar como pronto

#### Coleções
- `GET /library/collections` - Listar coleções
- `POST /library/collections` - Criar coleção
- `GET /library/collections/{collection}` - Obter coleção
- `PUT /library/collections/{collection}` - Atualizar
- `DELETE /library/collections/{collection}` - Excluir

#### Autores
- `GET /library/authors` - Listar autores
- `POST /library/authors` - Criar autor
- `GET /library/authors/{author}` - Obter autor
- `PUT /library/authors/{author}` - Atualizar
- `DELETE /library/authors/{author}` - Excluir

#### Editoras
- `GET /library/publishers` - Listar editoras
- `POST /library/publishers` - Criar editora
- `GET /library/publishers/{publisher}` - Obter editora
- `PUT /library/publishers/{publisher}` - Atualizar
- `DELETE /library/publishers/{publisher}` - Excluir

#### Incidentes
- `GET /library/incidents` - Listar incidentes
- `POST /library/incidents` - Criar incidente
- `GET /library/incidents/{libraryIncident}` - Obter incidente
- `PATCH /library/incidents/{libraryIncident}/resolve` - Resolver
- `PATCH /library/incidents/{libraryIncident}/close` - Fechar

#### Estatísticas
- `GET /library/statistics/dashboard` - Dashboard
- `GET /library/statistics/popular-books` - Livros populares
- `GET /library/statistics/loan-stats` - Estatísticas de empréstimos

---

### 9. **Módulo Financeiro (Finance)**

Gerenciamento financeiro completo.

**Endpoints principais:**

#### Contas Financeiras
- `GET /finance/accounts` - Listar contas
- `POST /finance/accounts` - Criar conta
- `GET /finance/accounts/{account}` - Obter conta
- `PUT /finance/accounts/{account}` - Atualizar
- `DELETE /finance/accounts/{account}` - Excluir
- `GET /finance/accounts/{account}/transactions` - Transações

#### Faturas
- `GET /finance/invoices` - Listar faturas
- `POST /finance/invoices` - Criar fatura
- `GET /finance/invoices/my-invoices` - Minhas faturas
- `GET /finance/invoices/overdue` - Faturas vencidas
- `GET /finance/invoices/{invoice}` - Obter fatura
- `PUT /finance/invoices/{invoice}` - Atualizar
- `DELETE /finance/invoices/{invoice}` - Excluir
- `POST /finance/invoices/{invoice}/issue` - Emitir
- `POST /finance/invoices/{invoice}/cancel` - Cancelar
- `POST /finance/invoices/{invoice}/send` - Enviar
- `GET /finance/invoices/{invoice}/download` - Download

#### Pagamentos
- `GET /finance/payments` - Listar pagamentos
- `POST /finance/payments` - Criar pagamento
- `GET /finance/payments/{payment}` - Obter pagamento
- `POST /finance/payments/{payment}/refund` - Reembolsar
- `GET /finance/payments/{payment}/receipt` - Obter recibo

#### Taxas
- `GET /finance/fees` - Listar taxas
- `POST /finance/fees` - Criar taxa
- `GET /finance/fees/{fee}` - Obter taxa
- `PUT /finance/fees/{fee}` - Atualizar
- `DELETE /finance/fees/{fee}` - Excluir
- `POST /finance/fees/apply` - Aplicar taxa
- `POST /finance/fees/bulk-apply` - Aplicar em lote

#### Despesas
- `GET /finance/expenses` - Listar despesas
- `POST /finance/expenses` - Criar despesa
- `GET /finance/expenses/{expense}` - Obter despesa
- `PUT /finance/expenses/{expense}` - Atualizar
- `DELETE /finance/expenses/{expense}` - Excluir
- `GET /finance/expenses/{expense}/receipt` - Obter recibo

#### Relatórios Financeiros
- `GET /finance/reports/summary` - Resumo financeiro
- `GET /finance/reports/income-statement` - DRE
- `GET /finance/reports/balance-sheet` - Balanço patrimonial
- `GET /finance/reports/cash-flow` - Fluxo de caixa
- `GET /finance/reports/accounts-receivable` - Contas a receber
- `GET /finance/reports/accounts-payable` - Contas a pagar
- `GET /finance/reports/revenue-by-category` - Receitas por categoria
- `GET /finance/reports/expenses-by-category` - Despesas por categoria

#### Dashboard
- `GET /finance/dashboard` - Dashboard financeiro

---

### 10. **Módulo de Transporte (Transport)**

Sistema completo de transporte escolar com rastreamento GPS.

**Endpoints principais:**

#### Rotas de Transporte
- `GET /transport/routes` - Listar rotas
- `POST /transport/routes` - Criar rota
- `GET /transport/routes/active` - Rotas ativas
- `GET /transport/routes/{route}` - Obter rota
- `PUT /transport/routes/{route}` - Atualizar
- `DELETE /transport/routes/{route}` - Excluir
- `POST /transport/routes/{route}/optimize` - Otimizar rota

#### Pontos de Parada
- `GET /transport/stops` - Listar paradas
- `POST /transport/stops` - Criar parada
- `GET /transport/stops/route/{route}` - Paradas por rota
- `GET /transport/stops/{stop}` - Obter parada
- `PUT /transport/stops/{stop}` - Atualizar
- `DELETE /transport/stops/{stop}` - Excluir
- `POST /transport/stops/{stop}/reorder` - Reordenar

#### Frota de Ônibus
- `GET /transport/fleet` - Listar frota
- `POST /transport/fleet` - Adicionar veículo
- `GET /transport/fleet/statistics` - Estatísticas da frota
- `GET /transport/fleet/{fleet}` - Obter veículo
- `PUT /transport/fleet/{fleet}` - Atualizar
- `DELETE /transport/fleet/{fleet}` - Excluir
- `GET /transport/fleet/available` - Veículos disponíveis
- `POST /transport/fleet/{bus}/assign` - Atribuir motorista
- `POST /transport/fleet/{bus}/maintenance` - Registrar manutenção

#### Transporte de Estudantes
- `GET /transport/students` - Listar estudantes
- `POST /transport/students/subscribe` - Inscrever estudante
- `GET /transport/students/{subscription}` - Obter inscrição
- `PUT /transport/students/{subscription}` - Atualizar
- `POST /transport/students/checkin` - Check-in
- `POST /transport/students/checkout` - Check-out
- `POST /transport/students/validate-qr` - Validar QR Code
- `GET /transport/students/{subscription}/qr-code` - Gerar QR Code
- `GET /transport/students/roster` - Lista de estudantes

#### Assinaturas de Transporte
- `GET /transport/subscriptions` - Listar assinaturas
- `POST /transport/subscriptions` - Criar assinatura
- `GET /transport/subscriptions/statistics` - Estatísticas
- `GET /transport/subscriptions/expiring` - Expirando
- `GET /transport/subscriptions/{subscription}` - Obter assinatura
- `PUT /transport/subscriptions/{subscription}` - Atualizar
- `DELETE /transport/subscriptions/{subscription}` - Excluir
- `POST /transport/subscriptions/{subscription}/activate` - Ativar
- `POST /transport/subscriptions/{subscription}/suspend` - Suspender
- `POST /transport/subscriptions/{subscription}/renew` - Renovar

#### Rastreamento GPS
- `GET /transport/tracking/eta` - Tempo estimado de chegada
- `POST /transport/tracking/location` - Atualizar localização
- `GET /transport/tracking/active-buses` - Ônibus ativos
- `GET /transport/tracking/bus/{bus}/location` - Localização do ônibus
- `GET /transport/tracking/route-progress` - Progresso da rota
- `GET /transport/tracking/bus/{bus}/history` - Histórico de rastreamento

#### Eventos de Transporte
- `GET /transport/events` - Listar eventos
- `POST /transport/events` - Criar evento
- `GET /transport/events/statistics` - Estatísticas
- `GET /transport/events/recent` - Eventos recentes
- `GET /transport/events/{event}` - Obter evento

#### Incidentes de Transporte
- `GET /transport/incidents` - Listar incidentes
- `POST /transport/incidents` - Criar incidente
- `GET /transport/incidents/{incident}` - Obter incidente
- `PUT /transport/incidents/{incident}` - Atualizar
- `POST /transport/incidents/{incident}/assign` - Atribuir responsável
- `POST /transport/incidents/{incident}/resolve` - Resolver

#### Portal dos Pais
- `GET /parent/transport/dashboard` - Dashboard do pai
- `GET /parent/transport/student/{student}/status` - Status do estudante
- `GET /parent/transport/student/{student}/location` - Localização do ônibus
- `GET /parent/transport/student/{student}/history` - Histórico
- `GET /parent/transport/student/{student}/route-map` - Mapa da rota
- `POST /parent/transport/student/{student}/request-change` - Solicitar mudança
- `GET /parent/transport/notifications` - Notificações

#### Portal do Motorista
- `GET /driver/transport/dashboard` - Dashboard do motorista
- `GET /driver/transport/today-routes` - Rotas de hoje
- `GET /driver/transport/assigned-students` - Estudantes atribuídos
- `POST /driver/transport/start-route` - Iniciar rota
- `POST /driver/transport/end-route` - Finalizar rota
- `POST /driver/transport/daily-checklist` - Checklist diário
- `POST /driver/transport/report-incident` - Reportar incidente

---

### 11. **Módulo de Formulários (Forms)**

Motor de formulários dinâmicos com workflow.

**Endpoints principais:**

#### Templates de Formulários
- `GET /form-templates` - Listar templates
- `POST /form-templates` - Criar template
- `GET /form-templates/{template}` - Obter template
- `PUT /form-templates/{template}` - Atualizar
- `DELETE /form-templates/{template}` - Excluir
- `POST /form-templates/{template}/duplicate` - Duplicar
- `GET /form-templates/{template}/versions` - Versões
- `POST /form-templates/{template}/versions/{versionId}/restore` - Restaurar versão
- `GET /form-templates/{template}/export` - Exportar
- `POST /form-templates/import` - Importar

#### Acesso Público
- `POST /form-templates/{template}/public-token` - Gerar token público
- `DELETE /form-templates/{template}/public-token` - Revogar token
- `PUT /form-templates/{template}/public-settings` - Configurações públicas

#### Instâncias de Formulários
- `GET /form-instances` - Listar instâncias
- `POST /form-instances` - Criar instância
- `GET /form-instances/{instance}` - Obter instância
- `PUT /form-instances/{instance}` - Atualizar
- `DELETE /form-instances/{instance}` - Excluir
- `POST /form-instances/{instance}/submit` - Submeter
- `POST /form-instances/{instance}/auto-save` - Auto-salvar
- `GET /form-instances/{instance}/validate` - Validar

#### Workflow
- `GET /form-instances/{instance}/workflow` - Obter workflow
- `POST /form-instances/{instance}/workflow` - Ação de workflow
- `POST /form-instances/{instance}/approve` - Aprovar
- `POST /form-instances/{instance}/reject` - Rejeitar

#### Formulários Públicos
- `GET /public/forms/{token}` - Obter formulário público
- `POST /public/forms/{token}/create-instance` - Criar instância
- `PUT /public/forms/{token}/update-instance` - Atualizar instância
- `POST /public/forms/{token}/submit-instance` - Submeter
- `POST /public/forms/{token}/validate-instance` - Validar

---

### 12. **Módulo de Permissões (Permissions)**

Gerenciamento de roles e permissões.

**Endpoints principais:**

#### Permissões
- `GET /permissions` - Listar permissões
- `GET /permissions/matrix` - Matriz de permissões

#### Roles
- `GET /permissions/roles` - Listar roles
- `POST /permissions/roles` - Criar role
- `GET /permissions/roles/{role}` - Obter role
- `PUT /permissions/roles/{role}` - Atualizar
- `DELETE /permissions/roles/{role}` - Excluir
- `GET /permissions/roles/{role}/permissions` - Permissões do role
- `PUT /permissions/roles/{role}/permissions` - Atualizar permissões

#### Atribuições
- `POST /permissions/users/assign-role` - Atribuir role ao usuário
- `DELETE /permissions/users/remove-role` - Remover role do usuário
- `GET /permissions/user` - Permissões do usuário
- `PUT /permissions/user` - Atualizar permissões do usuário

---

### 13. **Módulo de Upload de Arquivos**

Gerenciamento de uploads de arquivos.

**Endpoints principais:**
- `POST /v1/files/upload` - Upload de arquivo único
- `POST /v1/files/upload-multiple` - Upload de múltiplos arquivos
- `DELETE /v1/files/delete` - Deletar arquivo
- `GET /v1/files/info` - Informações do arquivo

---

## 🔐 Autenticação e Segurança

### Sistema de Autenticação

O sistema utiliza **JWT (JSON Web Tokens)** para autenticação.

#### Fluxo de Autenticação

1. **Login**: O usuário envia credenciais para `/auth/sign-in`
2. **Recebe Token**: O servidor retorna um token JWT
3. **Usa Token**: O cliente envia o token em todas as requisições
4. **Refresh**: Quando expira, usa `/auth/refresh` para renovar

### Configuração de Tokens JWT

```php
'ttl' => 60,                    // Token válido por 60 minutos
'refresh_ttl' => 20160,         // Refresh válido por 2 semanas
'algo' => 'HS256',              // Algoritmo de hash
'blacklist_enabled' => true,    // Lista negra habilitada
```

### Como Usar a Autenticação

#### 1. Login

```http
POST /api/v1/auth/sign-in
Content-Type: application/json

{
  "email": "usuario@exemplo.com",
  "password": "senha123"
}
```

**Resposta:**
```json
{
  "status": "success",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "name": "João Silva",
      "email": "usuario@exemplo.com"
    }
  }
}
```

#### 2. Usando o Token

Inclua o token no header `Authorization` de todas as requisições:

```http
GET /api/v1/users
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

#### 3. Refresh Token

Quando o token expirar, use o endpoint de refresh:

```http
POST /api/v1/auth/refresh
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### Middleware de Segurança

#### Middleware `auth:api`
- Valida o token JWT
- Verifica se o usuário está autenticado
- Carrega os dados do usuário

#### Middleware `tenant`
- Valida o contexto do tenant
- Isola os dados por organização
- Previne acesso entre organizações

#### Middleware `throttle:api`
- Limita requisições por IP
- Previne ataques de força bruta
- Configurável por rota

### Multi-Tenancy

O sistema implementa **isolamento de dados por organização (tenant)**:

- Cada requisição está vinculada a um tenant
- Os dados são filtrados automaticamente
- Impossível acessar dados de outra organização
- Usuários podem pertencer a múltiplos tenants

#### Trocar de Tenant

```http
POST /api/v1/user/switch-tenant
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "tenant_id": 2
}
```

---

## 👥 Roles e Permissões

### Roles Disponíveis

O sistema utiliza o pacote **Spatie Laravel Permission** para gerenciar roles e permissões.

#### Roles do Sistema

| Role | Nome de Exibição | Descrição | Sistema |
|------|------------------|-----------|---------|
| `super_admin` | Super Administrator | Acesso completo a todas as funcionalidades | ✅ |
| `owner` | Organization Owner | Proprietário da organização com acesso total | ✅ |
| `admin` | Administrator | Acesso administrativo à maioria das funcionalidades | ❌ |
| `tenant_admin` | Tenant Administrator | Administrador dentro do escopo do tenant | ❌ |
| `teacher` | Teacher | Acesso de professor ao sistema | ❌ |
| `student` | Student | Acesso de estudante ao sistema | ❌ |
| `parent` | Parent | Acesso de pai/responsável ao sistema | ❌ |
| `librarian` | Librarian | Acesso ao gerenciamento da biblioteca | ❌ |
| `finance_manager` | Finance Manager | Acesso ao gerenciamento financeiro | ❌ |
| `guest` | Guest | Acesso de visitante ao sistema | ❌ |

#### Roles de Formulários

| Role | Nome de Exibição | Descrição |
|------|------------------|-----------|
| `form_designer` | Form Designer | Pode criar e editar templates de formulários |
| `form_reviewer` | Form Reviewer | Pode revisar e aprovar submissões de formulários |
| `form_submitter` | Form Submitter | Pode submeter formulários e ver próprias submissões |
| `form_analyst` | Form Analyst | Pode ver análises e exportar dados |

### Categorias de Permissões

#### Formulários (`forms`)
- `forms.view` - Visualizar formulários
- `forms.admin` - Administrar formulários
- `forms.view_all` - Ver todos os formulários
- `forms.create` - Criar formulários
- `forms.edit_all` - Editar todos os formulários
- `forms.delete` - Deletar formulários
- `forms.workflow` - Gerenciar workflows
- `forms.create_template` - Criar templates
- `forms.edit_template` - Editar templates
- `forms.delete_template` - Deletar templates
- `forms.manage_public_access` - Gerenciar acesso público

#### Tenants (`tenants`)
- `tenants.create` - Criar organizações
- `tenants.manage_users` - Gerenciar usuários
- `tenants.manage_settings` - Gerenciar configurações
- `tenants.view` - Visualizar organizações

#### Usuários (`users`)
- `users.view` - Visualizar usuários
- `users.manage` - Gerenciar usuários
- `users.manage_roles` - Gerenciar roles
- `users.manage_permissions` - Gerenciar permissões
- `users.create` - Criar usuários
- `users.edit` - Editar usuários
- `users.delete` - Deletar usuários

#### Times (`teams`)
- `teams.view` - Visualizar times
- `teams.manage` - Gerenciar times
- `teams.invite` - Convidar membros
- `teams.remove` - Remover membros
- `teams.assign_roles` - Atribuir roles

### Como Verificar Permissões

#### No Código (Backend)
```php
// Verificar se tem permissão
if ($user->can('forms.create')) {
    // Pode criar formulários
}

// Verificar se tem role
if ($user->hasRole('admin')) {
    // É administrador
}

// Verificar múltiplas permissões
if ($user->hasAllPermissions(['forms.create', 'forms.edit_all'])) {
    // Tem todas as permissões
}
```

#### Via API
```http
GET /api/v1/permissions/user
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Resposta:**
```json
{
  "data": {
    "roles": ["admin", "form_designer"],
    "permissions": [
      "forms.view",
      "forms.create",
      "users.view"
    ]
  }
}
```

---

## ⚙️ Configuração Inicial

### Requisitos do Sistema

- **PHP**: >= 8.2
- **Laravel**: 12.x
- **Banco de Dados**: MySQL 8.0+ ou PostgreSQL 13+
- **Composer**: 2.x
- **Node.js**: 18.x ou superior (para assets)

### Variáveis de Ambiente

Crie um arquivo `.env` com as seguintes configurações:

```env
# Aplicação
APP_NAME=iEDU
APP_ENV=local
APP_KEY=base64:GENERATED_KEY
APP_DEBUG=true
APP_URL=http://localhost:8000

# Banco de Dados
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iedu_db
DB_USERNAME=root
DB_PASSWORD=

# JWT Authentication
JWT_SECRET=GENERATED_JWT_SECRET
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256
JWT_BLACKLIST_ENABLED=true

# Multi-Tenancy
TENANT_ID=1

# Form Engine
FORM_AI_ENABLED=false
FORM_STRICT_COMPLIANCE=false

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null

# Twilio (SMS)
TWILIO_SID=your_twilio_sid
TWILIO_AUTH_TOKEN=your_twilio_token
TWILIO_PHONE_NUMBER=+1234567890

# Pusher (WebSockets)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1
```

### Instalação

#### 1. Clonar o Repositório
```bash
git clone https://github.com/iedu/laravel-backend.git
cd laravel-backend
```

#### 2. Instalar Dependências
```bash
# PHP Dependencies
composer install

# Node Dependencies
npm install
```

#### 3. Configurar Ambiente
```bash
# Copiar arquivo de exemplo
cp .env.example .env

# Gerar chave da aplicação
php artisan key:generate

# Gerar chave JWT
php artisan jwt:secret
```

#### 4. Configurar Banco de Dados
```bash
# Executar migrations
php artisan migrate

# Executar seeders
php artisan db:seed
```

#### 5. Criar Link Simbólico para Storage
```bash
php artisan storage:link
```

#### 6. Compilar Assets
```bash
npm run build
```

#### 7. Iniciar Servidor
```bash
php artisan serve
```

A API estará disponível em: `http://localhost:8000/api/v1`

### Seeding Inicial

O sistema cria automaticamente:

- **Super Admin**: email: `admin@iedu.com`, senha: `password`
- **Tenant Padrão**: Organização de demonstração
- **Roles e Permissões**: Todos os roles e permissões básicos
- **Dados de Teste**: Escolas, professores, estudantes (opcional)

### Configurações de Performance

#### Cache
```bash
# Limpar cache
php artisan cache:clear

# Cache de configuração
php artisan config:cache

# Cache de rotas
php artisan route:cache

# Cache de views
php artisan view:cache
```

#### Filas (Queues)
```bash
# Iniciar worker de fila
php artisan queue:work

# Com supervisor (produção)
supervisor -c /etc/supervisor/supervisord.conf
```

#### Schedule (Agendador)
```bash
# Adicionar ao crontab
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Configuração de Produção

#### 1. Otimizações
```bash
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

#### 2. Configurações do .env
```env
APP_ENV=production
APP_DEBUG=false
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

#### 3. Permissões de Arquivos
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 📝 Form Engine iEDU

O **Form Engine iEDU** é um motor de formulários dinâmicos com validação inteligente, workflows e integração com IA.

### Características Principais

#### 1. **Templates Dinâmicos**
- Criação visual de formulários
- Campos customizáveis
- Validação inteligente
- Versionamento automático

#### 2. **Categorias Educacionais**

O sistema possui categorias específicas para educação:

| Categoria | Descrição |
|-----------|-----------|
| `student_enrollment` | Matrícula de estudantes |
| `student_registration` | Registro anual |
| `attendance` | Controle de presença |
| `grades` | Notas e avaliações |
| `academic_records` | Registros acadêmicos |
| `behavior_incident` | Incidentes comportamentais |
| `parent_communication` | Comunicação com pais |
| `teacher_evaluation` | Avaliação de professores |
| `curriculum_planning` | Planejamento curricular |
| `extracurricular` | Atividades extracurriculares |
| `field_trip` | Passeios escolares |
| `parent_meeting` | Reuniões com pais |
| `student_health` | Saúde do estudante |
| `special_education` | Educação especial |
| `discipline` | Disciplina |
| `graduation` | Formatura |
| `scholarship` | Bolsas de estudo |

#### 3. **Sistema de Workflow**

- Fluxos de aprovação customizáveis
- Múltiplos níveis de aprovação
- Notificações automáticas
- SLA configurável
- Escalação automática

#### 4. **Validação Inteligente**

- Validação em tempo real
- Regras de negócio específicas
- Compliance educacional (FERPA, IDEA)
- Validação de campos dependentes

#### 5. **Formulários Públicos**

- Geração de tokens públicos
- Acesso sem autenticação
- Configurações de expiração
- Limite de submissões

### Configuração do Form Engine

Arquivo: `config/form_engine.php`

```php
return [
    'defaults' => [
        'auto_save_interval' => 30,     // Segundos
        'max_file_size' => '10MB',
        'form_timeout' => 3600,         // 1 hora
    ],
    
    'intelligence' => [
        'enable_suggestions' => true,
        'enable_auto_population' => true,
        'enable_smart_validation' => true,
        'enable_ai_assistance' => env('FORM_AI_ENABLED', false),
    ],
    
    'validation' => [
        'enable_real_time_validation' => true,
        'enable_compliance_checking' => true,
        'strict_compliance_mode' => env('FORM_STRICT_COMPLIANCE', false),
    ],
    
    'workflow' => [
        'enable_workflows' => true,
        'default_sla_hours' => 72,
        'enable_escalations' => true,
        'max_escalation_levels' => 3,
    ],
    
    'security' => [
        'enable_field_encryption' => false,
        'encrypted_field_types' => ['password', 'ssn', 'personal_id'],
        'enable_audit_logging' => true,
    ],
];
```

### Tipos de Campos Suportados

- **text**: Campo de texto simples
- **textarea**: Área de texto
- **number**: Campo numérico
- **email**: Email com validação
- **date**: Seletor de data
- **time**: Seletor de hora
- **datetime**: Data e hora
- **select**: Lista suspensa
- **radio**: Botões de opção
- **checkbox**: Caixas de seleção
- **file_upload**: Upload de arquivos
- **image_upload**: Upload de imagens
- **currency**: Valor monetário
- **phone**: Número de telefone
- **url**: URL com validação
- **password**: Senha
- **hidden**: Campo oculto

### Exemplo de Uso

#### Criar Template de Formulário

```http
POST /api/v1/form-templates
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "name": "Matrícula de Estudante",
  "category": "student_enrollment",
  "description": "Formulário de matrícula para novos estudantes",
  "fields": [
    {
      "name": "student_name",
      "type": "text",
      "label": "Nome Completo",
      "required": true,
      "validation_rules": ["required", "string", "max:255"]
    },
    {
      "name": "birth_date",
      "type": "date",
      "label": "Data de Nascimento",
      "required": true,
      "validation_rules": ["required", "date", "before:today"]
    },
    {
      "name": "grade_level",
      "type": "select",
      "label": "Série",
      "required": true,
      "options": ["1º Ano", "2º Ano", "3º Ano"],
      "validation_rules": ["required"]
    }
  ],
  "workflow": {
    "enabled": true,
    "steps": [
      {
        "name": "approval_coordinator",
        "role": "coordinator",
        "sla_hours": 24
      },
      {
        "name": "approval_director",
        "role": "director",
        "sla_hours": 48
      }
    ]
  }
}
```

#### Criar Instância de Formulário

```http
POST /api/v1/form-instances
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "form_template_id": 1,
  "data": {
    "student_name": "Maria Silva",
    "birth_date": "2015-05-10",
    "grade_level": "3º Ano"
  }
}
```

#### Submeter Formulário

```http
POST /api/v1/form-instances/1/submit
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### Compliance e Auditoria

O Form Engine registra automaticamente:

- Quem criou/editou o formulário
- Quando foi criado/editado
- Todas as alterações de dados
- Workflow e aprovações
- Acessos ao formulário

---

## 📚 Controllers

### Estrutura dos Controllers

Todos os controllers seguem o padrão Laravel e estão organizados por módulo:

```
app/Http/Controllers/
├── API/
│   ├── V1/
│   │   ├── Academic/
│   │   │   ├── AcademicClassController.php
│   │   │   ├── SubjectController.php
│   │   │   ├── TeacherController.php
│   │   │   ├── GradeEntryController.php
│   │   │   ├── GradeLevelController.php
│   │   │   ├── GradeScaleController.php
│   │   │   ├── GradingSystemController.php
│   │   │   ├── AnalyticsController.php
│   │   │   └── BulkOperationsController.php
│   │   ├── Assessment/
│   │   │   ├── AssessmentController.php
│   │   │   ├── AssessmentTermController.php
│   │   │   ├── AssessmentTypeController.php
│   │   │   ├── GradeEntryController.php
│   │   │   ├── GradeReviewController.php
│   │   │   ├── GradebookController.php
│   │   │   ├── AssessmentSettingsController.php
│   │   │   ├── ReportController.php
│   │   │   └── GradeScaleController.php
│   │   ├── Auth/
│   │   │   ├── AuthController.php
│   │   │   └── PasswordController.php
│   │   ├── Financial/
│   │   │   ├── InvoiceController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── FeeController.php
│   │   │   ├── ExpenseController.php
│   │   │   ├── FinancialAccountController.php
│   │   │   └── FinancialReportController.php
│   │   ├── Forms/
│   │   │   ├── FormTemplateController.php
│   │   │   ├── FormInstanceController.php
│   │   │   ├── PublicFormController.php
│   │   │   └── PublicFormTemplateController.php
│   │   ├── Library/
│   │   │   ├── BookController.php
│   │   │   ├── LoanController.php
│   │   │   ├── ReservationController.php
│   │   │   ├── IncidentController.php
│   │   │   ├── CollectionController.php
│   │   │   ├── AuthorController.php
│   │   │   ├── PublisherController.php
│   │   │   └── BookFileController.php
│   │   ├── School/
│   │   │   ├── SchoolController.php
│   │   │   ├── AcademicYearController.php
│   │   │   └── AcademicTermController.php
│   │   ├── Student/
│   │   │   ├── StudentController.php
│   │   │   ├── StudentDocumentController.php
│   │   │   ├── StudentEnrollmentController.php
│   │   │   └── FamilyRelationshipController.php
│   │   ├── Transport/
│   │   │   ├── TransportRouteController.php
│   │   │   ├── FleetBusController.php
│   │   │   ├── FleetController.php
│   │   │   ├── BusStopController.php
│   │   │   ├── StudentTransportController.php
│   │   │   ├── TransportSubscriptionController.php
│   │   │   ├── TransportTrackingController.php
│   │   │   ├── TransportIncidentController.php
│   │   │   ├── TransportEventsController.php
│   │   │   ├── ParentPortalController.php
│   │   │   └── DriverPortalController.php
│   │   ├── UserController.php
│   │   ├── UserProfileController.php
│   │   └── FileUploadController.php
│   ├── PermissionController.php
│   └── TenantController.php
```

### Padrões dos Controllers

#### Métodos Padrão (CRUD)

Todos os controllers resource seguem o padrão:

- `index()` - Lista recursos (GET)
- `store()` - Cria recurso (POST)
- `show($id)` - Mostra recurso específico (GET)
- `update($id)` - Atualiza recurso (PUT/PATCH)
- `destroy($id)` - Deleta recurso (DELETE)

#### Trait ApiResponseTrait

Todos os controllers usam o trait para padronizar respostas:

```php
use App\Traits\ApiResponseTrait;

class ExemploController extends Controller
{
    use ApiResponseTrait;
    
    public function index()
    {
        $data = Model::paginate();
        return $this->successResponse($data);
    }
}
```

#### Service Layer

Controllers delegam lógica de negócio para Services:

```php
public function store(Request $request)
{
    $data = $this->service->create($request->validated());
    return $this->successResponse($data, 'Criado com sucesso', 201);
}
```

---

## 🚀 Funcionalidades Principais

### 1. **Multi-Tenancy**

- Isolamento completo de dados por organização
- Escopo global automático em todos os modelos
- Usuários podem pertencer a múltiplos tenants
- Troca de contexto via API

### 2. **Sistema de Avaliação Completo**

- Múltiplos períodos de avaliação
- Tipos de avaliação customizáveis
- Escalas de notas configuráveis
- GPA e médias automáticas
- Boletins e históricos escolares

### 3. **Transporte Escolar com GPS**

- Rastreamento em tempo real
- QR Codes para check-in/out
- Portal para pais e motoristas
- Otimização de rotas
- Gestão de incidentes
- Notificações automáticas

### 4. **Biblioteca Digital**

- Catálogo completo de livros
- Sistema de empréstimos
- Reservas online
- Controle de multas e atrasos
- Estatísticas de uso

### 5. **Gestão Financeira**

- Faturas e recibos
- Controle de pagamentos
- Gestão de despesas
- Relatórios financeiros
- Integração com meios de pagamento

### 6. **Form Engine Inteligente**

- Criação visual de formulários
- Workflows de aprovação
- Validação inteligente
- Formulários públicos
- Compliance educacional

### 7. **Sistema de Permissões Granular**

- Roles hierárquicos
- Permissões por recurso
- Verificação automática
- Auditoria de acessos

### 8. **Auditoria Completa**

- Log de todas as ações
- Rastreamento de mudanças
- Histórico de versões
- Relatórios de auditoria

### 9. **Notificações Multi-canal**

- Email
- SMS (Twilio)
- Push Notifications
- WhatsApp (via Twilio)
- WebSockets (Pusher)

### 10. **APIs RESTful**

- Documentação completa
- Versionamento
- Rate limiting
- CORS configurável
- Paginação automática

---

## 📐 Padrões e Convenções

### Padrão de Respostas da API

Todas as respostas seguem o formato padronizado:

#### Sucesso
```json
{
  "status": "success",
  "data": {
    // Dados retornados
  },
  "meta": {
    "total": 100,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7,
    "from": 1,
    "to": 15
  },
  "message": "Operação realizada com sucesso"
}
```

#### Erro
```json
{
  "status": "error",
  "message": "Mensagem de erro descritiva",
  "errors": {
    "field_name": [
      "Mensagem de validação"
    ]
  },
  "code": 422
}
```

### Códigos HTTP

| Código | Significado | Uso |
|--------|-------------|-----|
| 200 | OK | Requisição bem-sucedida |
| 201 | Created | Recurso criado com sucesso |
| 204 | No Content | Sucesso sem retorno de dados |
| 400 | Bad Request | Requisição inválida |
| 401 | Unauthorized | Não autenticado |
| 403 | Forbidden | Sem permissão |
| 404 | Not Found | Recurso não encontrado |
| 422 | Unprocessable Entity | Erro de validação |
| 429 | Too Many Requests | Rate limit excedido |
| 500 | Internal Server Error | Erro interno do servidor |

### Paginação

Todas as listagens são paginadas automaticamente:

```http
GET /api/v1/students?page=2&per_page=20
```

**Parâmetros:**
- `page`: Número da página (padrão: 1)
- `per_page`: Itens por página (padrão: 15, máx: 100)

**Resposta:**
```json
{
  "data": [...],
  "meta": {
    "total": 100,
    "per_page": 20,
    "current_page": 2,
    "last_page": 5,
    "from": 21,
    "to": 40
  },
  "links": {
    "first": "http://api.iedu.com/api/v1/students?page=1",
    "last": "http://api.iedu.com/api/v1/students?page=5",
    "prev": "http://api.iedu.com/api/v1/students?page=1",
    "next": "http://api.iedu.com/api/v1/students?page=3"
  }
}
```

### Filtros e Ordenação

```http
GET /api/v1/students?
    filter[name]=João&
    filter[status]=active&
    sort=-created_at,name&
    include=enrollments,documents
```

**Parâmetros:**
- `filter[campo]`: Filtrar por campo
- `sort`: Ordenar (use `-` para desc)
- `include`: Incluir relacionamentos

### Busca

```http
GET /api/v1/students?search=João Silva
```

### Versionamento

A API é versionada via URL:

- **v1**: `/api/v1/...` (atual)
- **v2**: `/api/v2/...` (futuro)

### Rate Limiting

- **Limite padrão**: 60 requisições por minuto
- **Header de resposta**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`
- **Quando exceder**: HTTP 429

### CORS

CORS está configurado para aceitar requisições de origens permitidas.

**Headers incluídos:**
- `Access-Control-Allow-Origin`
- `Access-Control-Allow-Methods`
- `Access-Control-Allow-Headers`

### Convenções de Nomenclatura

#### URLs e Rotas
- **kebab-case**: `/api/v1/academic-years`
- **Plural**: `/users`, `/students`, `/books`
- **Recursos aninhados**: `/schools/{school}/students`

#### Campos JSON
- **snake_case**: `student_name`, `birth_date`, `grade_level`

#### Métodos PHP
- **camelCase**: `getStudents()`, `createEnrollment()`

#### Classes PHP
- **PascalCase**: `StudentController`, `AcademicYearService`

#### Constantes
- **UPPERCASE**: `MAX_FILE_SIZE`, `DEFAULT_PAGINATION`

### Tratamento de Erros

```php
try {
    // Operação
} catch (ValidationException $e) {
    return response()->json([
        'status' => 'error',
        'message' => 'Erro de validação',
        'errors' => $e->errors()
    ], 422);
} catch (Exception $e) {
    Log::error('Erro: ' . $e->getMessage());
    return response()->json([
        'status' => 'error',
        'message' => 'Erro interno do servidor'
    ], 500);
}
```

### Logs

O sistema registra logs em diferentes níveis:

- **emergency**: Sistema inutilizável
- **alert**: Ação imediata necessária
- **critical**: Condições críticas
- **error**: Erros que não requerem ação imediata
- **warning**: Avisos
- **notice**: Eventos normais mas significantes
- **info**: Eventos informativos
- **debug**: Informações detalhadas de debug

```php
Log::info('Usuário criado', ['user_id' => $user->id]);
Log::error('Falha ao enviar email', ['error' => $e->getMessage()]);
```

---

## 📞 Suporte e Contato

Para dúvidas, sugestões ou problemas:

- **Documentação Técnica**: Ver arquivos `.md` no repositório
- **Issues**: GitHub Issues
- **Email**: dev@iedu.com

---

## 📄 Licença

Este projeto é proprietário e confidencial. Todos os direitos reservados © 2024 iEDU.

---

## 🔄 Changelog

### v1.0.0 (2024-10)
- Lançamento inicial
- Todos os módulos principais implementados
- Form Engine com compliance educacional
- Sistema de transporte com GPS
- Multi-tenancy completo
- Sistema de permissões granular

---

**Última atualização**: Outubro 2024  
**Versão da API**: v1  
**Versão do Laravel**: 12.x

