# 📊 Assessment & Grades Module

Sistema completo de gestão de avaliações e notas para o sistema SaaS multitenant de gestão académica.

---

## 📑 Índice

- [Visão Geral](#visão-geral)
- [Funcionalidades](#funcionalidades)
- [Instalação](#instalação)
- [Estrutura do Módulo](#estrutura-do-módulo)
- [Modelos de Dados](#modelos-de-dados)
- [Endpoints da API](#endpoints-da-api)
- [Permissões e Autorização](#permissões-e-autorização)
- [Eventos e Notificações](#eventos-e-notificações)
- [Workflows Integrados](#workflows-integrados)
- [Relatórios](#relatórios)
- [Exemplos de Uso](#exemplos-de-uso)

---

## 🎯 Visão Geral

O módulo **Assessment & Grades** permite:

- ✅ Gestão completa de avaliações (testes, trabalhos, exames, etc.)
- ✅ Entrada e gestão de notas com componentes configuráveis
- ✅ Publicação de resultados com bloqueio de avaliações
- ✅ Sistema de revisão de notas (pedidos e aprovação)
- ✅ Escalas de avaliação personalizáveis (0-20, A-F, 0-100%)
- ✅ Importação/exportação de pautas (CSV/Excel/PDF)
- ✅ Cálculo automático de médias ponderadas
- ✅ Relatórios e transcrições de alunos
- ✅ Auditoria completa de alterações de notas
- ✅ Notificações em tempo real via email e broadcast

**Características:**
- **Multitenant**: Isolamento completo de dados por tenant
- **Autenticação**: JWT (Tymon JWT Auth)
- **Autorização**: Spatie Laravel Permission
- **Eventos**: Broadcasting via Pusher
- **Jobs Assíncronos**: Para operações pesadas

---

## 🚀 Funcionalidades

### Para Professores
- Criar e configurar avaliações com múltiplos componentes
- Definir pesos e critérios de avaliação (rubrics)
- Inserir notas manualmente ou importar via CSV/Excel
- Publicar notas para alunos
- Bloquear avaliações após publicação
- Exportar pautas em PDF/CSV
- Responder a pedidos de revisão de nota

### Para Alunos e Encarregados
- Consultar notas publicadas
- Ver médias e transcrições por período
- Solicitar revisão de nota via formulário
- Receber notificações de novas notas

### Para Coordenadores/Administradores
- Gerir escalas de notas do tenant
- Configurar número de avaliações por período
- Definir políticas de arredondamento
- Gerir prazos para revisão de notas
- Visualizar relatórios de turmas
- Aprovar importações de pautas
- Aceder a auditoria completa

---

## 📦 Instalação

### 1. Executar Migrations

```bash
php artisan migrate
```

Isto criará as seguintes tabelas:
- `assessment_terms`
- `assessment_types`
- `assessments`
- `assessment_components`
- `grade_entries`
- `gradebooks`
- `gradebook_files`
- `grade_scales`
- `grade_scale_mappings`
- `grade_reviews`
- `assessment_resources`
- `assessment_settings`
- `grades_audit_logs`

### 2. Executar Seeders

```bash
# Permissões e Roles
php artisan db:seed --class=AssessmentPermissionsSeeder

# Tipos de Avaliação (Teste, Trabalho, Exame, etc.)
php artisan db:seed --class=AssessmentTypesSeeder

# Escalas de Notas (0-20, A-F, 0-100%)
php artisan db:seed --class=GradeScalesSeeder
```

### 3. Registar Policies no AuthServiceProvider

```php
// app/Providers/AuthServiceProvider.php

use App\Models\Assessment\Assessment;
use App\Policies\Assessment\AssessmentPolicy;
use App\Models\Assessment\GradeEntry;
use App\Policies\Assessment\GradeEntryPolicy;
use App\Models\Assessment\GradeReview;
use App\Policies\Assessment\GradeReviewPolicy;
use App\Models\Assessment\Gradebook;
use App\Policies\Assessment\GradebookPolicy;
use App\Models\Assessment\GradeScale;
use App\Policies\Assessment\GradeScalePolicy;
use App\Models\Assessment\AssessmentSettings;
use App\Policies\Assessment\AssessmentSettingsPolicy;

protected $policies = [
    Assessment::class => AssessmentPolicy::class,
    GradeEntry::class => GradeEntryPolicy::class,
    GradeReview::class => GradeReviewPolicy::class,
    Gradebook::class => GradebookPolicy::class,
    GradeScale::class => GradeScalePolicy::class,
    AssessmentSettings::class => AssessmentSettingsPolicy::class,
];
```

### 4. Registar Eventos no EventServiceProvider

```php
// app/Providers/EventServiceProvider.php

use App\Events\Assessment\AssessmentCreated;
use App\Listeners\Assessment\SendAssessmentCreatedNotification;
use App\Events\Assessment\AssessmentUpdated;
use App\Listeners\Assessment\SendAssessmentUpdatedNotification;
use App\Events\Assessment\GradesPublished;
use App\Listeners\Assessment\SendGradesPublishedNotification;
use App\Events\Assessment\GradeEntered;
use App\Listeners\Assessment\LogGradeChange;
use App\Events\Assessment\GradeReviewRequested;
use App\Listeners\Assessment\SendGradeReviewRequestedNotification;
use App\Events\Assessment\GradeReviewResolved;
use App\Listeners\Assessment\SendGradeReviewResolvedNotification;

protected $listen = [
    AssessmentCreated::class => [
        SendAssessmentCreatedNotification::class,
    ],
    AssessmentUpdated::class => [
        SendAssessmentUpdatedNotification::class,
    ],
    GradesPublished::class => [
        SendGradesPublishedNotification::class,
    ],
    GradeEntered::class => [
        LogGradeChange::class,
    ],
    GradeReviewRequested::class => [
        SendGradeReviewRequestedNotification::class,
    ],
    GradeReviewResolved::class => [
        SendGradeReviewResolvedNotification::class,
    ],
];
```

---

## 📂 Estrutura do Módulo

```
app/
├── Models/Assessment/
│   ├── Assessment.php
│   ├── AssessmentComponent.php
│   ├── AssessmentResource.php
│   ├── AssessmentSettings.php
│   ├── AssessmentTerm.php
│   ├── AssessmentType.php
│   ├── GradeEntry.php
│   ├── Gradebook.php
│   ├── GradebookFile.php
│   ├── GradeReview.php
│   ├── GradeScale.php
│   ├── GradeScaleMapping.php
│   └── GradesAuditLog.php
│
├── Http/
│   ├── Controllers/API/V1/Assessment/
│   │   ├── AssessmentController.php
│   │   ├── AssessmentSettingsController.php
│   │   ├── GradeEntryController.php
│   │   ├── GradeReviewController.php
│   │   ├── GradeScaleController.php
│   │   ├── GradebookController.php
│   │   └── ReportController.php
│   │
│   ├── Requests/Assessment/
│   │   ├── StoreAssessmentRequest.php
│   │   ├── UpdateAssessmentRequest.php
│   │   ├── StoreGradeEntryRequest.php
│   │   ├── UpdateGradeEntryRequest.php
│   │   ├── BulkImportGradesRequest.php
│   │   ├── StoreGradeReviewRequest.php
│   │   ├── UpdateGradeReviewRequest.php
│   │   ├── StoreGradeScaleRequest.php
│   │   └── [outros requests...]
│   │
│   └── Resources/Assessment/
│       ├── AssessmentResource.php
│       ├── GradeEntryResource.php
│       ├── GradeReviewResource.php
│       └── [outros resources...]
│
├── Services/Assessment/
│   ├── AssessmentService.php
│   ├── GradeService.php
│   ├── GradeReviewService.php
│   ├── GradeScaleService.php
│   └── ReportService.php
│
├── Events/Assessment/
│   ├── AssessmentCreated.php
│   ├── AssessmentUpdated.php
│   ├── GradesPublished.php
│   ├── GradeEntered.php
│   ├── GradeReviewRequested.php
│   └── GradeReviewResolved.php
│
├── Listeners/Assessment/
│   ├── SendAssessmentCreatedNotification.php
│   ├── SendAssessmentUpdatedNotification.php
│   ├── SendGradesPublishedNotification.php
│   ├── SendGradeReviewRequestedNotification.php
│   ├── SendGradeReviewResolvedNotification.php
│   └── LogGradeChange.php
│
├── Notifications/Assessment/
│   ├── AssessmentCreatedNotification.php
│   ├── AssessmentUpdatedNotification.php
│   ├── GradesPublishedNotification.php
│   ├── GradeReviewRequestedNotification.php
│   ├── GradeReviewResolvedNotification.php
│   └── AssessmentReminderNotification.php
│
├── Jobs/Assessment/
│   ├── ProcessBulkGradeImport.php
│   ├── PublishGrades.php
│   ├── GenerateGradebookReport.php
│   ├── CalculateStudentGPA.php
│   └── SendAssessmentReminder.php
│
└── Policies/Assessment/
    ├── AssessmentPolicy.php
    ├── GradeEntryPolicy.php
    ├── GradeReviewPolicy.php
    ├── GradebookPolicy.php
    ├── GradeScalePolicy.php
    └── AssessmentSettingsPolicy.php
```

---

## 🗄️ Modelos de Dados

### Principais Entidades

#### AssessmentTerm
Períodos de avaliação (ex: 1º Trimestre, 2º Semestre)

#### Assessment
Avaliações individuais (testes, trabalhos, exames)
- Relacionado com: Subject, Class, Teacher, AssessmentType
- Pode ter múltiplos AssessmentComponents
- Pode ser bloqueado (`is_locked`) após publicação

#### GradeEntry
Notas individuais de alunos
- Relacionado com: Assessment, Student, AssessmentComponent
- Suporta publicação incremental

#### GradeReview
Pedidos de revisão de nota
- Estados: pending, in_review, accepted, rejected, resolved

#### GradeScale
Escalas de avaliação (0-20, A-F, 0-100%)
- Configurável por tenant
- Pode ter uma escala padrão

---

## 🌐 Endpoints da API

### Assessments

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessments` | Listar avaliações |
| POST | `/api/v1/assessments` | Criar avaliação |
| GET | `/api/v1/assessments/{id}` | Ver detalhes |
| PUT | `/api/v1/assessments/{id}` | Atualizar avaliação |
| DELETE | `/api/v1/assessments/{id}` | Eliminar avaliação |
| PATCH | `/api/v1/assessments/{id}/status` | Alterar estado |
| POST | `/api/v1/assessments/{id}/lock` | Bloquear avaliação |

**Filtros disponíveis:** `search`, `term_id`, `subject_id`, `class_id`, `teacher_id`, `status`, `type_id`

### Grades

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessments/grades` | Listar notas |
| POST | `/api/v1/assessments/grades` | Inserir nota |
| PUT | `/api/v1/assessments/grades/{id}` | Atualizar nota |
| DELETE | `/api/v1/assessments/grades/{id}` | Eliminar nota |
| GET | `/api/v1/assessments/grades/student/{id}` | Notas do aluno |
| POST | `/api/v1/assessments/{id}/grades/publish` | Publicar notas |
| POST | `/api/v1/assessments/grades/bulk-import` | Importar notas (CSV/Excel) |

### Grade Reviews

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessments/grade-reviews` | Listar pedidos |
| POST | `/api/v1/assessments/grade-reviews` | Criar pedido |
| GET | `/api/v1/assessments/grade-reviews/{id}` | Ver detalhes |
| PUT | `/api/v1/assessments/grade-reviews/{id}` | Atualizar estado |
| DELETE | `/api/v1/assessments/grade-reviews/{id}` | Eliminar pedido |

### Grade Scales

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessments/grade-scales` | Listar escalas |
| POST | `/api/v1/assessments/grade-scales` | Criar escala |
| GET | `/api/v1/assessments/grade-scales/default` | Escala padrão |
| GET | `/api/v1/assessments/grade-scales/{id}` | Ver detalhes |
| PUT | `/api/v1/assessments/grade-scales/{id}` | Atualizar escala |
| DELETE | `/api/v1/assessments/grade-scales/{id}` | Eliminar escala |

### Gradebooks

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessments/gradebooks` | Listar pautas |
| POST | `/api/v1/assessments/gradebooks` | Upload de pauta |
| GET | `/api/v1/assessments/gradebooks/{id}` | Ver detalhes |
| GET | `/api/v1/assessments/gradebooks/{id}/download` | Download |
| POST | `/api/v1/assessments/gradebooks/{id}/approve` | Aprovar pauta |
| POST | `/api/v1/assessments/gradebooks/{id}/reject` | Rejeitar pauta |
| POST | `/api/v1/assessments/gradebooks/{id}/generate` | Gerar relatório |

### Reports

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessments/reports/class/{classId}/term/{termId}/grades-summary` | Sumário de turma |
| GET | `/api/v1/assessments/reports/student/{studentId}/term/{termId}/transcript` | Transcricão do aluno |

---

## 🔐 Permissões e Autorização

### Permissões Disponíveis

```
assessments.view
assessments.create
assessments.update
assessments.delete

grades.view
grades.enter
grades.bulk_import
grades.publish

grade_reviews.manage
grade_reviews.resolve

gradebooks.upload
gradebooks.download

grade_scales.manage

assessment.settings.manage
```

### Atribuição de Permissões por Role

| Permissão | tenant_admin | academic_coordinator | teacher | student | parent |
|-----------|--------------|---------------------|---------|---------|--------|
| assessments.view | ✅ | ✅ | ✅ (own) | ❌ | ❌ |
| assessments.create | ✅ | ✅ | ✅ | ❌ | ❌ |
| grades.view | ✅ | ✅ | ✅ (own class) | ✅ (own) | ✅ (children) |
| grades.enter | ✅ | ✅ | ✅ | ❌ | ❌ |
| grades.publish | ✅ | ✅ | ✅ | ❌ | ❌ |
| grade_reviews.manage | ✅ | ✅ | ❌ | ❌ | ❌ |
| grade_scales.manage | ✅ | ✅ | ❌ | ❌ | ❌ |

---

## 📡 Eventos e Notificações

### Eventos Disponíveis

1. **AssessmentCreated** - Quando uma avaliação é criada
2. **AssessmentUpdated** - Quando uma avaliação é atualizada
3. **GradesPublished** - Quando notas são publicadas
4. **GradeEntered** - Quando uma nota é inserida
5. **GradeReviewRequested** - Quando é pedida revisão
6. **GradeReviewResolved** - Quando revisão é resolvida

### Canais de Notificação

- **Email**: Notificações por email
- **Database**: Armazenadas na BD
- **Broadcast**: Tempo real via Pusher

### Exemplos de Notificações

```php
// Aluno recebe notificação quando notas são publicadas
event(new GradesPublished($assessment, $gradeEntries));

// Professor recebe quando há pedido de revisão
event(new GradeReviewRequested($gradeReview));
```

---

## 🔄 Workflows Integrados

### 1. Pedido de Revisão de Nota

```
Student/Parent → Submits Review Request
    ↓
System → Creates GradeReview (status: pending)
    ↓
System → Notifies Teacher
    ↓
Teacher → Reviews and Updates (status: accepted/rejected)
    ↓
System → Updates Grade (if accepted)
    ↓
System → Notifies Student/Parent
```

### 2. Publicação de Notas

```
Teacher → Enters all grades
    ↓
Teacher → Clicks "Publish Grades"
    ↓
System → Marks all entries as published
    ↓
System → Locks assessment
    ↓
System → Sends notifications to all students
    ↓
Students → Can view their grades
```

---

## 📊 Relatórios

### Class Grades Summary
Retorna sumário completo de uma turma com:
- Todas as avaliações do período
- Notas de todos os alunos
- Médias individuais
- Média da turma

### Student Transcript
Retorna transcrição completa do aluno com:
- Todas as avaliações por disciplina
- Notas detalhadas
- Médias por disciplina
- Média geral

---

## 💡 Exemplos de Uso

### Criar Avaliação

```bash
POST /api/v1/assessments
Authorization: Bearer {token}

{
  "term_id": 1,
  "subject_id": 5,
  "class_id": 10,
  "type_id": 1,
  "title": "Teste de Matemática - Álgebra",
  "description": "Teste sobre equações do 2º grau",
  "scheduled_date": "2025-11-15 10:00:00",
  "total_marks": 100,
  "weight": 20,
  "components": [
    {
      "name": "Parte Teórica",
      "weight_pct": 60,
      "max_marks": 60
    },
    {
      "name": "Parte Prática",
      "weight_pct": 40,
      "max_marks": 40
    }
  ]
}
```

### Inserir Nota

```bash
POST /api/v1/assessments/grades
Authorization: Bearer {token}

{
  "assessment_id": 1,
  "student_id": 50,
  "component_id": 1,
  "marks_awarded": 55,
  "remarks": "Bom desempenho"
}
```

### Solicitar Revisão de Nota

```bash
POST /api/v1/assessments/grade-reviews
Authorization: Bearer {token}

{
  "grade_entry_id": 100,
  "reason": "Penso que a resposta à questão 5 está correta",
  "details": "Na pergunta 5, apliquei o teorema de Pitágoras corretamente..."
}
```

### Publicar Notas

```bash
POST /api/v1/assessments/1/grades/publish
Authorization: Bearer {token}
```

---

## 🧪 Testing

Para executar os testes do módulo:

```bash
# Todos os testes
php artisan test

# Testes específicos de assessment
php artisan test --filter Assessment
```

---

## 🔧 Configuração

### Broadcasting (Pusher)

Configure as credenciais no `.env`:

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=eu
```

### Queue

Para jobs assíncronos:

```env
QUEUE_CONNECTION=database
```

Execute o worker:

```bash
php artisan queue:work
```

---

## 📝 Notas Importantes

1. **Tenant Scope**: Todos os modelos principais usam global scope de tenant automático
2. **Soft Deletes**: Maioria dos modelos usa soft deletes para auditoria
3. **Auditoria**: Todas as alterações de notas são registadas em `grades_audit_logs`
4. **Bloqueio**: Avaliações publicadas são bloqueadas e não podem ser alteradas (exceto por admins)
5. **Validação**: Todas as operações passam por Form Requests com validação completa

---

## 🐛 Troubleshooting

### Erro: "Permissão negada"
- Verificar se o utilizador tem a permissão necessária
- Verificar se as policies estão registadas
- Executar `php artisan permission:cache-reset`

### Erro: "Tenant não encontrado"
- Verificar se `tenant_id` está na sessão ou no token JWT
- Verificar se o middleware de tenant está ativo

### Notificações não estão a ser enviadas
- Verificar configuração do Pusher
- Verificar se o queue worker está a correr
- Verificar logs: `storage/logs/laravel.log`

---

## 📞 Suporte

Para questões e suporte:
- Documentação completa do sistema
- Issue tracker do projeto
- Email de suporte

---

**Desenvolvido  para o Sistema de Gestão Académica**

