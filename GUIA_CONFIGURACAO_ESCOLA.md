# 📚 Guia Completo de Configuração da Escola - iEdu

## Workflow do Dono da Escola: Do Login até Iniciar as Aulas

Este guia apresenta o processo completo de configuração de uma escola no sistema iEdu, desde o primeiro login como dono da escola até estar pronto para iniciar as aulas.

---

## 🔐 FASE 1: AUTENTICAÇÃO E ACESSO INICIAL

### Passo 1.1: Login no Sistema
- **Ação**: Acessar o sistema com credenciais de `school_owner`
- **Credenciais padrão** (após seed):
  - Email: `owner@example.com`
  - Senha: `password123`
- **Resultado**: Acesso ao painel do dono da escola

### Passo 1.2: Verificar Tenant e Permissões
- **Ação**: Confirmar que está associado ao tenant correto
- **Verificar**: 
  - Tenant ID está correto
  - Permissões de `school_owner` estão ativas
  - Status da conta está ativo

---

## 🏫 FASE 2: CONFIGURAÇÃO BÁSICA DA ESCOLA

### Passo 2.1: Criar/Configurar a Escola
**Ordem de Precedência**: ⭐ **CRÍTICO - Deve ser feito PRIMEIRO**

**Campos Obrigatórios:**
- `school_code`: Código único da escola
- `official_name`: Nome oficial completo
- `display_name`: Nome para exibição
- `short_name`: Nome curto/abreviação
- `school_type`: Tipo de escola (pre_primary, primary, secondary_general, etc.)
- `educational_levels`: Níveis educacionais (JSON)
- `grade_range_min` e `grade_range_max`: Faixa de séries
- `configured_grade_levels`: Séries configuradas (JSON)
- `email`: Email de contato
- `phone`: Telefone
- `country_code`: Código do país (ex: "MZ" para Moçambique)
- `city`: Cidade
- `academic_calendar_type`: Tipo de calendário (semester, trimester, quarter)
- `academic_year_start_month`: Mês de início do ano letivo (padrão: 8)
- `grading_system`: Sistema de avaliação (traditional_letter, percentage, points)
- `attendance_tracking_level`: Nível de controle de frequência
- `language_instruction`: Idiomas de instrução (JSON)
- `subscription_plan`: Plano de assinatura
- `feature_flags`: Funcionalidades habilitadas (JSON)
- `status`: Status inicial (deve ser "setup")

**Dependências:**
- ✅ Tenant deve existir
- ✅ Usuário dono da escola deve existir

**Próximos Passos Após:**
- Pode criar Academic Years
- Pode criar Subjects
- Pode criar Teachers
- Pode criar Students

---

## 📅 FASE 3: CONFIGURAÇÃO DO ANO LETIVO

### Passo 3.1: Criar Ano Letivo (Academic Year)
**Ordem de Precedência**: ⭐ **CRÍTICO - Deve ser feito ANTES de Terms**

**Campos Obrigatórios:**
- `school_id`: ID da escola (criada no Passo 2.1)
- `name`: Nome do ano letivo (ex: "2025-2026")
- `code`: Código do ano (ex: "AY2025")
- `year`: Ano (ex: "2025-2026")
- `start_date`: Data de início
- `end_date`: Data de término
- `term_structure`: Estrutura de períodos (semesters, trimesters, quarters)
- `total_terms`: Número total de períodos
- `status`: Status (deve iniciar como "planning")
- `is_current`: Marcar como ano atual (true/false)

**Dependências:**
- ✅ School deve existir (Passo 2.1)

**Próximos Passos Após:**
- Pode criar Academic Terms
- Pode criar Classes

### Passo 3.2: Criar Períodos Letivos (Academic Terms)
**Ordem de Precedência**: ⭐ **CRÍTICO - Deve ser feito ANTES de Classes**

**Campos Obrigatórios:**
- `academic_year_id`: ID do ano letivo (criado no Passo 3.1)
- `school_id`: ID da escola
- `name`: Nome do período (ex: "1º Trimestre", "Fall Semester")
- `type`: Tipo (semester, quarter, trimester)
- `term_number`: Número do período (1, 2, 3...)
- `start_date`: Data de início
- `end_date`: Data de término
- `instructional_days`: Número de dias letivos
- `status`: Status (deve iniciar como "planning")
- `is_current`: Marcar como período atual (true/false)

**Dependências:**
- ✅ Academic Year deve existir (Passo 3.1)
- ✅ School deve existir (Passo 2.1)

**Próximos Passos Após:**
- Pode criar Classes
- Pode criar Assessments
- Pode criar Grade Entries

---

## 📊 FASE 4: CONFIGURAÇÃO DO SISTEMA DE AVALIAÇÃO

### Passo 4.1: Criar Escala de Notas (Grade Scale)
**Ordem de Precedência**: ⭐ **IMPORTANTE - Antes de Grade Levels**

**Campos Obrigatórios:**
- `school_id`: ID da escola
- `name`: Nome da escala (ex: "Escala Padrão A-F")
- `code`: Código único (opcional)
- `scale_type`: Tipo (letter, percentage, points, standards)
- `min_value`: Valor mínimo
- `max_value`: Valor máximo
- `passing_grade`: Nota mínima para aprovação
- `is_default`: Marcar como padrão (true/false)
- `status`: Status (active/inactive)

**Dependências:**
- ✅ School deve existir (Passo 2.1)

**Próximos Passos Após:**
- Pode criar Grade Levels

### Passo 4.2: Criar Níveis de Nota (Grade Levels)
**Ordem de Precedência**: ⭐ **IMPORTANTE - Após Grade Scale**

**Campos Obrigatórios:**
- `grade_scale_id`: ID da escala (criada no Passo 4.1)
- `school_id`: ID da escola
- `grade_value`: Valor da nota (ex: "A", "95", "4.0")
- `display_value`: Valor para exibição
- `numeric_value`: Valor numérico
- `percentage_min`: Percentual mínimo
- `percentage_max`: Percentual máximo
- `is_passing`: Se é nota de aprovação (true/false)
- `sort_order`: Ordem de classificação

**Dependências:**
- ✅ Grade Scale deve existir (Passo 4.1)
- ✅ School deve existir (Passo 2.1)

**Próximos Passos Após:**
- Sistema de avaliação pronto para uso

### Passo 4.3: Criar Tipos de Avaliação (Assessment Types)
**Ordem de Precedência**: ⭐ **IMPORTANTE - Antes de Assessments**

**Campos Obrigatórios:**
- `tenant_id`: ID do tenant
- `name`: Nome do tipo (ex: "Teste", "Trabalho", "Exame")
- `code`: Código único (opcional)
- `default_weight`: Peso padrão (ex: 20.00%)
- `max_score`: Pontuação máxima padrão
- `grading_scale`: Tipo de escala (percentage, numeric)
- `is_active`: Se está ativo (true/false)

**Dependências:**
- ✅ Tenant deve existir

**Próximos Passos Após:**
- Pode criar Assessments

---

## 👨‍🏫 FASE 5: CONFIGURAÇÃO DE PROFESSORES

### Passo 5.1: Criar Conta de Usuário para Professor
**Ordem de Precedência**: ⭐ **IMPORTANTE - Antes de Teacher Profile**

**Ações:**
- Criar usuário no sistema com role "teacher"
- Definir email e senha
- Associar ao tenant e escola

**Dependências:**
- ✅ School deve existir (Passo 2.1)
- ✅ Tenant deve existir

**Próximos Passos Após:**
- Pode criar Teacher Profile

### Passo 5.2: Criar Perfil de Professor (Teacher)
**Ordem de Precedência**: ⭐ **IMPORTANTE - Após User criado**

**Campos Obrigatórios:**
- `school_id`: ID da escola
- `user_id`: ID do usuário (criado no Passo 5.1)
- `employee_id`: ID único do funcionário
- `first_name`: Primeiro nome
- `last_name`: Sobrenome
- `hire_date`: Data de contratação
- `employment_type`: Tipo (full_time, part_time, substitute, etc.)
- `status`: Status (active, inactive, etc.)

**Campos Opcionais Importantes:**
- `email`: Email de contato
- `phone`: Telefone
- `department`: Departamento
- `position`: Cargo
- `specializations_json`: Especializações (JSON)
- `certifications_json`: Certificações (JSON)

**Dependências:**
- ✅ School deve existir (Passo 2.1)
- ✅ User deve existir (Passo 5.1)

**Próximos Passos Após:**
- Pode atribuir professores a Classes
- Pode criar Schedules

---

## 📚 FASE 6: CONFIGURAÇÃO DE DISCIPLINAS

### Passo 6.1: Criar Disciplinas (Subjects)
**Ordem de Precedência**: ⭐ **CRÍTICO - Antes de Classes**

**Campos Obrigatórios:**
- `school_id`: ID da escola
- `name`: Nome da disciplina (ex: "Matemática", "Português")
- `code`: Código único (ex: "MAT", "POR")
- `subject_area`: Área (mathematics, science, language_arts, etc.)
- `grade_levels`: Séries aplicáveis (JSON)
- `status`: Status (active/inactive)

**Campos Opcionais Importantes:**
- `description`: Descrição
- `credit_hours`: Carga horária
- `is_core_subject`: Se é disciplina obrigatória
- `is_elective`: Se é optativa
- `learning_standards_json`: Padrões curriculares (JSON)

**Dependências:**
- ✅ School deve existir (Passo 2.1)

**Próximos Passos Após:**
- Pode criar Classes
- Pode criar Assessments

---

## 👥 FASE 7: CONFIGURAÇÃO DE TURMAS

### Passo 7.1: Criar Turmas (Classes)
**Ordem de Precedência**: ⭐ **CRÍTICO - Antes de Matrículas e Schedules**

**Campos Obrigatórios:**
- `school_id`: ID da escola
- `subject_id`: ID da disciplina (criada no Passo 6.1)
- `academic_year_id`: ID do ano letivo (criado no Passo 3.1)
- `academic_term_id`: ID do período (criado no Passo 3.2) - opcional
- `name`: Nome da turma (ex: "7ª Classe Matemática - Seção A")
- `section`: Seção (ex: "A", "B", "Advanced")
- `grade_level`: Nível/série (ex: "7", "8", "T1")
- `max_students`: Capacidade máxima
- `status`: Status (draft, planned, active, etc.)

**Campos Opcionais Importantes:**
- `primary_teacher_id`: ID do professor principal (criado no Passo 5.2)
- `additional_teachers_json`: Professores adicionais (JSON)
- `room_number`: Número da sala
- `schedule_json`: Horário (JSON)

**Dependências:**
- ✅ School deve existir (Passo 2.1)
- ✅ Subject deve existir (Passo 6.1)
- ✅ Academic Year deve existir (Passo 3.1)
- ✅ Academic Term deve existir (Passo 3.2) - recomendado
- ⚠️ Teacher (opcional, mas recomendado)

**Próximos Passos Após:**
- Pode matricular estudantes
- Pode criar Schedules
- Pode criar Assessments
- Pode criar Grade Entries

---

## 👨‍🎓 FASE 8: CONFIGURAÇÃO DE ESTUDANTES

### Passo 8.1: Criar Conta de Usuário para Estudante
**Ordem de Precedência**: ⭐ **IMPORTANTE - Antes de Student Profile**

**Ações:**
- Criar usuário no sistema com role "student"
- Definir email e senha (ou usar identificador único)
- Associar ao tenant e escola

**Dependências:**
- ✅ School deve existir (Passo 2.1)
- ✅ Tenant deve existir

**Próximos Passos Após:**
- Pode criar Student Profile

### Passo 8.2: Criar Perfil de Estudante (Student)
**Ordem de Precedência**: ⭐ **IMPORTANTE - Após User criado**

**Campos Obrigatórios:**
- `school_id`: ID da escola
- `user_id`: ID do usuário (criado no Passo 8.1)
- `student_code`: Código único do estudante
- `first_name`: Primeiro nome
- `last_name`: Sobrenome
- `date_of_birth`: Data de nascimento
- `gender`: Gênero
- `enrollment_date`: Data de matrícula
- `status`: Status (active, inactive, graduated, etc.)

**Campos Opcionais Importantes:**
- `email`: Email de contato
- `phone`: Telefone
- `address_json`: Endereço (JSON)
- `guardian_info_json`: Informações do responsável (JSON)
- `medical_info_json`: Informações médicas (JSON)

**Dependências:**
- ✅ School deve existir (Passo 2.1)
- ✅ User deve existir (Passo 8.1)

**Próximos Passos Após:**
- Pode matricular em Classes

### Passo 8.3: Matricular Estudante em Turmas (Student Class Enrollment)
**Ordem de Precedência**: ⭐ **CRÍTICO - Antes de iniciar aulas**

**Campos Obrigatórios:**
- `student_id`: ID do estudante (criado no Passo 8.2)
- `class_id`: ID da turma (criada no Passo 7.1)
- `academic_year_id`: ID do ano letivo
- `academic_term_id`: ID do período
- `enrollment_date`: Data de matrícula
- `status`: Status (enrolled, active, completed, etc.)

**Dependências:**
- ✅ Student deve existir (Passo 8.2)
- ✅ Class deve existir (Passo 7.1)
- ✅ Academic Year deve existir (Passo 3.1)
- ✅ Academic Term deve existir (Passo 3.2)

**Próximos Passos Após:**
- Estudante pode participar de aulas
- Pode receber notas
- Pode ter frequência registrada

---

## 📋 FASE 9: CONFIGURAÇÃO DE HORÁRIOS

### Passo 9.1: Criar Horários (Schedules)
**Ordem de Precedência**: ⭐ **IMPORTANTE - Antes de Lessons**

**Campos Obrigatórios:**
- `school_id`: ID da escola
- `academic_year_id`: ID do ano letivo
- `academic_term_id`: ID do período
- `class_id`: ID da turma
- `teacher_id`: ID do professor
- `day_of_week`: Dia da semana (1-7 ou Monday-Sunday)
- `start_time`: Hora de início
- `end_time`: Hora de término
- `room_number`: Número da sala (opcional)
- `status`: Status (active, inactive)

**Dependências:**
- ✅ School deve existir (Passo 2.1)
- ✅ Academic Year deve existir (Passo 3.1)
- ✅ Academic Term deve existir (Passo 3.2)
- ✅ Class deve existir (Passo 7.1)
- ✅ Teacher deve existir (Passo 5.2)

**Próximos Passos Após:**
- Pode criar Lessons (aulas)

### Passo 9.2: Criar Aulas (Lessons)
**Ordem de Precedência**: ⭐ **OPCIONAL - Para registro detalhado de aulas**

**Campos Obrigatórios:**
- `schedule_id`: ID do horário (criado no Passo 9.1)
- `class_id`: ID da turma
- `teacher_id`: ID do professor
- `lesson_date`: Data da aula
- `start_time`: Hora de início
- `end_time`: Hora de término
- `status`: Status (scheduled, completed, cancelled)

**Campos Opcionais Importantes:**
- `topic`: Tópico da aula
- `objectives`: Objetivos (JSON)
- `materials_needed`: Materiais necessários (JSON)

**Dependências:**
- ✅ Schedule deve existir (Passo 9.1)
- ✅ Class deve existir (Passo 7.1)
- ✅ Teacher deve existir (Passo 5.2)

**Próximos Passos Após:**
- Pode registrar frequência (Lesson Attendance)
- Pode adicionar conteúdo da aula (Lesson Content)

---

## ✅ FASE 10: VERIFICAÇÕES FINAIS E ATIVAÇÃO

### Passo 10.1: Verificar Configurações Completas
**Checklist de Verificação:**

- [ ] ✅ Escola criada e configurada (Passo 2.1)
- [ ] ✅ Ano letivo criado e marcado como atual (Passo 3.1)
- [ ] ✅ Períodos letivos criados (Passo 3.2)
- [ ] ✅ Escala de notas configurada (Passo 4.1 e 4.2)
- [ ] ✅ Tipos de avaliação criados (Passo 4.3)
- [ ] ✅ Professores cadastrados (Passo 5.1 e 5.2)
- [ ] ✅ Disciplinas criadas (Passo 6.1)
- [ ] ✅ Turmas criadas (Passo 7.1)
- [ ] ✅ Estudantes cadastrados (Passo 8.1 e 8.2)
- [ ] ✅ Estudantes matriculados em turmas (Passo 8.3)
- [ ] ✅ Horários criados (Passo 9.1)
- [ ] ✅ Status da escola alterado para "active"

### Passo 10.2: Ativar Ano Letivo e Período
**Ações:**
- Alterar status do Academic Year para "active"
- Alterar status do Academic Term atual para "active"
- Marcar `is_current = true` no período atual

### Passo 10.3: Ativar Turmas
**Ações:**
- Alterar status das Classes de "planned" para "active"
- Verificar que todas têm professores atribuídos
- Verificar que todas têm estudantes matriculados

### Passo 10.4: Ativar Escola
**Ações:**
- Alterar status da School de "setup" para "active"
- Definir `onboarding_completed_at` com data atual

---

## 🎓 FASE 11: INICIAR AS AULAS

### Passo 11.1: Criar Primeira Aula (Lesson)
**Ações:**
- Criar Lesson para cada turma no primeiro dia de aulas
- Registrar data, horário e tópico
- Status: "scheduled" ou "completed"

### Passo 11.2: Registrar Frequência (Opcional)
**Ações:**
- Criar Lesson Attendance para cada estudante
- Marcar presença/ausência
- Adicionar observações se necessário

### Passo 11.3: Criar Avaliações (Assessments)
**Ações:**
- Criar Assessments para as turmas
- Associar ao tipo de avaliação (Assessment Type)
- Definir data, peso e pontuação máxima
- Status: "draft" ou "scheduled"

---

## 📊 RESUMO DA ORDEM DE PRECEDÊNCIA

```
1. Login como School Owner
   ↓
2. Criar Escola (School) ⭐ CRÍTICO
   ↓
3. Criar Ano Letivo (Academic Year) ⭐ CRÍTICO
   ↓
4. Criar Períodos Letivos (Academic Terms) ⭐ CRÍTICO
   ↓
5. Criar Escala de Notas (Grade Scale) ⭐ IMPORTANTE
   ↓
6. Criar Níveis de Nota (Grade Levels)
   ↓
7. Criar Tipos de Avaliação (Assessment Types) ⭐ IMPORTANTE
   ↓
8. Criar Professores (Users + Teachers) ⭐ IMPORTANTE
   ↓
9. Criar Disciplinas (Subjects) ⭐ CRÍTICO
   ↓
10. Criar Turmas (Classes) ⭐ CRÍTICO
    ↓
11. Criar Estudantes (Users + Students) ⭐ IMPORTANTE
    ↓
12. Matricular Estudantes (Student Class Enrollment) ⭐ CRÍTICO
    ↓
13. Criar Horários (Schedules) ⭐ IMPORTANTE
    ↓
14. Verificar e Ativar Tudo ✅
    ↓
15. Criar Primeiras Aulas (Lessons) 🎓
    ↓
16. Sistema Pronto para Uso! 🎉
```

---

## 🔗 DEPENDÊNCIAS VISUAIS

```
Tenant
  └─ School (Passo 2.1)
      ├─ Academic Year (Passo 3.1)
      │   └─ Academic Term (Passo 3.2)
      │       ├─ Classes (Passo 7.1)
      │       │   ├─ Student Enrollments (Passo 8.3)
      │       │   ├─ Schedules (Passo 9.1)
      │       │   │   └─ Lessons (Passo 9.2)
      │       │   └─ Assessments
      │       │       └─ Grade Entries
      │       └─ Subjects (Passo 6.1)
      ├─ Grade Scale (Passo 4.1)
      │   └─ Grade Levels (Passo 4.2)
      ├─ Assessment Types (Passo 4.3)
      ├─ Teachers (Passo 5.2)
      │   └─ Users (Passo 5.1)
      └─ Students (Passo 8.2)
          └─ Users (Passo 8.1)
```

---

## ⚠️ PONTOS DE ATENÇÃO

1. **Ordem é Crítica**: Não pule etapas - cada fase depende da anterior
2. **Status Inicial**: Muitas entidades começam com status "planning" ou "draft"
3. **Ativação Progressiva**: Ative apenas quando tudo estiver configurado
4. **Validações**: O sistema valida dependências - erros indicam falta de configuração
5. **Multi-Tenancy**: Sempre verifique que está no tenant correto
6. **Academic Term**: Classes podem funcionar sem term, mas é recomendado ter

---

## 📝 NOTAS FINAIS

- Este workflow garante que todas as dependências sejam respeitadas
- Alguns passos podem ser feitos em paralelo (ex: criar vários professores ao mesmo tempo)
- A ordem apresentada é a ordem mínima de precedência
- Após completar este workflow, o sistema estará pronto para uso operacional

---

**Última atualização**: Dezembro 2025
**Versão do Sistema**: iEdu Laravel Backend v1.0

