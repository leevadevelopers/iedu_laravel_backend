# Documentação da Estrutura e Organização do Sistema iEdu Laravel Backend

## Visão Geral

O sistema iEdu é uma aplicação Laravel 12 multi-tenant para gestão educacional, desenvolvida com arquitetura modular e orientada a serviços. A aplicação gerencia diversos aspectos de uma instituição educacional, incluindo gestão acadêmica, transporte escolar, formulários dinâmicos e sistema de informações estudantis.

## Estrutura de Diretórios

### Diretórios Principais

```
iedu_laravel_backend/
├── app/                          # Código fonte da aplicação
├── config/                       # Arquivos de configuração
├── database/                     # Migrações, seeders e factories
├── public/                       # Arquivos públicos (assets, index.php)
├── resources/                    # Views, assets compilados
├── routes/                       # Definições de rotas
├── storage/                      # Logs, cache, uploads
├── tests/                        # Testes automatizados
├── vendor/                       # Dependências do Composer
├── artisan                       # CLI do Laravel
├── composer.json                 # Dependências PHP
├── package.json                  # Dependências Node.js
└── vite.config.js               # Configuração do Vite
```

## Arquitetura da Aplicação

### Padrões Arquiteturais

1. **Multi-Tenancy**: Sistema baseado em tenants para isolamento de dados
2. **Service Layer**: Lógica de negócio encapsulada em serviços
4. **Observer Pattern**: Para auditoria e eventos
5. **Workflow Engine**: Para automação de processos

### Estrutura Modular

A aplicação está organizada em módulos funcionais:

- **Academic**: Gestão acadêmica (disciplinas, turmas, notas)
- **Schedule**: Sistema de horários e aulas
- **Student**: Gestão de estudantes
- **Transport**: Sistema de transporte escolar
- **Forms**: Motor de formulários dinâmicos
- **Auth**: Autenticação e autorização
- **Tenant**: Gestão multi-tenant

## Módulos do Sistema

### 1. Módulo Acadêmico (`Academic`)

**Localização**: `app/Http/Controllers/API/V1/Academic/`

**Controladores**:
- `AcademicClassController`: Gestão de turmas
- `SubjectController`: Gestão de disciplinas
- `TeacherController`: Gestão de professores
- `GradeEntryController`: Lançamento de notas
- `GradeLevelController`: Níveis de avaliação
- `GradeScaleController`: Escalas de notas
- `GradingSystemController`: Sistema de avaliação
- `AnalyticsController`: Análises e relatórios
- `BulkOperationsController`: Operações em lote

**Modelos**:
- `AcademicClass`: Turmas acadêmicas
- `Subject`: Disciplinas
- `Teacher`: Professores
- `GradeEntry`: Entradas de notas
- `GradeLevel`: Níveis de avaliação
- `GradeScale`: Escalas de notas
- `GradingSystem`: Sistema de avaliação

**Funcionalidades**:
- Criação e gestão de turmas
- Matrícula de estudantes
- Lançamento de notas
- Sistema de avaliação configurável
- Relatórios acadêmicos
- Operações em lote

### 2. Módulo de Horários (`Schedule`)

**Localização**: `app/Http/Controllers/API/V1/Schedule/`

**Controladores**:
- `ScheduleController`: Gestão de horários
- `LessonController`: Gestão de aulas

**Modelos**:
- `Schedule`: Horários
- `Lesson`: Aulas
- `LessonAttendance`: Frequência
- `LessonContent`: Conteúdo das aulas
- `ScheduleConflict`: Conflitos de horário

**Funcionalidades**:
- Criação de grade horária
- Gestão de aulas
- Controle de frequência
- Detecção de conflitos
- Integração com sistema acadêmico

### 3. Módulo de Estudantes (`Student`)

**Localização**: `app/Http/Controllers/API/V1/Student/`

**Controladores**:
- `StudentController`: Gestão de estudantes
- `StudentDocumentController`: Documentos
- `StudentEnrollmentController`: Matrículas
- `FamilyRelationshipController`: Relacionamentos familiares

**Modelos**:
- `Student`: Estudantes
- `StudentDocument`: Documentos
- `StudentEnrollmentHistory`: Histórico de matrículas
- `FamilyRelationship`: Relacionamentos familiares

**Funcionalidades**:
- Cadastro completo de estudantes
- Gestão de documentos
- Histórico acadêmico
- Relacionamentos familiares
- Integração com formulários dinâmicos

### 4. Módulo de Transporte (`Transport`)

**Localização**: `app/Http/Controllers/API/V1/Transport/`

**Controladores**:
- `TransportRouteController`: Rotas de transporte
- `FleetBusController`: Frota de ônibus
- `StudentTransportController`: Transporte de estudantes
- `TransportSubscriptionController`: Assinaturas
- `TransportTrackingController`: Rastreamento GPS
- `ParentPortalController`: Portal dos pais
- `DriverPortalController`: Portal do motorista
- `BusStopController`: Pontos de parada
- `TransportIncidentController`: Incidentes

**Modelos**:
- `TransportRoute`: Rotas
- `FleetBus`: Frota
- `StudentTransportSubscription`: Assinaturas
- `StudentTransportEvent`: Eventos de transporte
- `TransportTracking`: Rastreamento
- `TransportIncident`: Incidentes
- `BusStop`: Pontos de parada

**Funcionalidades**:
- Gestão de rotas e paradas
- Controle da frota
- Rastreamento GPS em tempo real
- Portal para pais e motoristas
- Gestão de incidentes
- Sistema de QR codes

### 5. Módulo de Formulários (`Forms`)

**Localização**: `app/Http/Controllers/API/V1/Forms/`

**Controladores**:
- `FormTemplateController`: Templates de formulários
- `FormInstanceController`: Instâncias de formulários
- `PublicFormController`: Formulários públicos
- `PublicFormTemplateController`: Templates públicos

**Modelos**:
- `FormTemplate`: Templates
- `FormInstance`: Instâncias
- `FormWorkflow`: Workflows
- `FormWorkflowStep`: Etapas do workflow

**Funcionalidades**:
- Criação dinâmica de formulários
- Sistema de workflows
- Validação inteligente
- Formulários públicos
- Auditoria completa

### 6. Módulo de Autenticação (`Auth`)

**Localização**: `app/Http/Controllers/API/V1/Auth/`

**Controladores**:
- `AuthController`: Autenticação principal
- `PasswordController`: Gestão de senhas

**Funcionalidades**:
- Login/logout
- JWT tokens
- Recuperação de senha
- Multi-tenancy

### 7. Módulo de Usuários (`User`)

**Localização**: `app/Http/Controllers/API/V1/`

**Controladores**:
- `UserController`: Gestão de usuários
- `UserProfileController`: Perfis de usuário
- `PermissionController`: Permissões

**Funcionalidades**:
- CRUD de usuários
- Gestão de perfis
- Sistema de permissões baseado em roles
- Integração com Spatie Laravel Permission

## Arquivos Core da Aplicação

### 1. BaseModel (`app/Models/BaseModel.php`)

**Funcionalidades**:
- Herança base para todos os modelos
- Escopo automático de tenant
- Métodos utilitários para multi-tenancy

```php
abstract class BaseModel extends Model
{
    // Escopo automático de tenant
    protected static function boot(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Lógica de escopo de tenant
        });
    }
    
    // Método para escopo manual de tenant
    public function scopeTenantScope(Builder $query, ?int $tenantId = null): Builder
}
```

### 2. Controller Base (`app/Http/Controllers/Controller.php`)

**Funcionalidades**:
- Herança base para todos os controladores
- Traits de autorização

### 3. Configurações Específicas

#### Form Engine (`config/form_engine.php`)
- Configuração do motor de formulários
- Validações inteligentes
- Workflows
- Segurança

#### Permissions (`config/permission.php`)
- Configuração do sistema de permissões
- Multi-tenancy
- Cache de permissões

### 4. Service Providers

#### AppServiceProvider (`app/Providers/AppServiceProvider.php`)
- Registro de serviços
- Configuração de rate limiting
- Observers
- Configuração de tenant

## Serviços da Aplicação

### Serviços Core

**Localização**: `app/Services/`

#### TenantService
- Gestão de contexto de tenant
- Isolamento de dados

#### ActivityLogService
- Auditoria de ações
- Log de atividades

#### FormEngineService
- Motor de formulários dinâmicos
- Validação inteligente
- Workflows

#### WorkflowService
- Automação de processos
- Aprovações
- Notificações

### Serviços por Módulo

#### Academic Services (`app/Services/V1/Academic/`)
- `AcademicClassService`: Lógica de turmas
- `SubjectService`: Lógica de disciplinas
- `TeacherService`: Lógica de professores
- `GradeEntryService`: Lógica de notas
- `AnalyticsService`: Análises e relatórios

#### Transport Services (`app/Services/V1/Transport/`)
- `TransportRouteService`: Lógica de rotas
- `FleetManagementService`: Gestão da frota
- `StudentTransportService`: Transporte de estudantes
- `TransportTrackingService`: Rastreamento GPS
- `ParentPortalService`: Portal dos pais
- `DriverPortalService`: Portal do motorista

## Traits da Aplicação

**Localização**: `app/Traits/`

### ApiResponseTrait
- Padronização de respostas da API
- Formatação consistente

### HasAuditTrail
- Auditoria automática
- Rastreamento de mudanças

### MultiTenant
- Funcionalidades de multi-tenancy
- Isolamento de dados

### HasWorkflow
- Integração com sistema de workflows
- Aprovações automáticas

## Rotas da Aplicação

### Estrutura de Rotas

**Localização**: `routes/`

#### API Routes (`routes/api.php`)
- Agrupamento por versão (v1)
- Middleware de autenticação
- Middleware de tenant

#### Módulos de Rotas (`routes/modules/`)
- `academic/academic.php`: Rotas acadêmicas
- `transport/transport.php`: Rotas de transporte
- `forms.php`: Rotas de formulários
- `auth.php`: Rotas de autenticação
- `students.php`: Rotas de estudantes
- `schedule/schedule.php`: Rotas de horários

### Padrões de Rotas

```php
// Exemplo de agrupamento
Route::middleware(['auth:api', 'tenant'])->group(function () {
    Route::prefix('subjects')->name('subjects.')->group(function () {
        Route::get('/', [SubjectController::class, 'index'])->name('index');
        Route::post('/', [SubjectController::class, 'store'])->name('store');
        // ... outras rotas
    });
});
```

## Dependências Principais

### PHP (composer.json)
- **Laravel Framework**: ^12.0
- **JWT Auth**: ^2.2 (autenticação)
- **Spatie Laravel Permission**: * (permissões)
- **Laravel Auditing**: ^14.0 (auditoria)
- **Activity Log**: ^4.10 (logs de atividade)
- **Twilio SDK**: ^8.7 (notificações SMS)
- **Pusher**: ^7.2 (WebSockets)
- **Endroid QR Code**: ^6.0 (QR codes)

### Node.js (package.json)
- **Vite**: Build tool
- **Laravel Mix**: Compilação de assets

## Configurações de Ambiente

### Variáveis Importantes
- `FORM_AI_ENABLED`: Habilita IA nos formulários
- `FORM_STRICT_COMPLIANCE`: Modo de conformidade rigorosa
- `TENANT_ID`: ID do tenant atual

### Middleware
- `auth:api`: Autenticação JWT
- `tenant`: Escopo de tenant
- `throttle:api`: Rate limiting

## Padrões de Desenvolvimento

### Convenções de Nomenclatura
- **Controladores**: PascalCase + Controller
- **Modelos**: PascalCase singular
- **Serviços**: PascalCase + Service
- **Rotas**: kebab-case
- **Métodos**: camelCase

### Estrutura de Resposta da API
```json
{
    "status": "success|error",
    "data": {},
    "meta": {
        "total": 100,
        "per_page": 15,
        "current_page": 1,
        "last_page": 7
    },
    "message": "Mensagem descritiva"
}
```

### Tratamento de Erros
- Validação com Form Requests
- Exceções customizadas
- Logs estruturados
- Respostas padronizadas

## Segurança

### Multi-Tenancy
- Isolamento automático de dados por tenant
- Escopo global em todos os modelos
- Middleware de verificação de tenant

### Autenticação e Autorização
- JWT tokens
- Sistema de permissões baseado em roles
- Middleware de verificação de permissões

### Auditoria
- Log de todas as ações
- Rastreamento de mudanças
- Observers automáticos

## Performance

### Cache
- Cache de permissões
- Cache de configurações
- Cache de formulários

### Otimizações
- Eager loading de relacionamentos
- Paginação em todas as listagens
- Rate limiting
- Compressão de respostas

## Monitoramento

### Logs
- Logs estruturados
- Diferentes níveis de log
- Integração com Laravel Pail

### Métricas
- Rate limiting
- Performance de queries
- Uso de memória

## Conclusão

O sistema iEdu Laravel Backend é uma aplicação robusta e bem estruturada, seguindo as melhores práticas do Laravel e padrões de arquitetura modernos. A organização modular facilita a manutenção e expansão do sistema, enquanto o sistema multi-tenant garante o isolamento adequado dos dados entre diferentes instituições educacionais.

A documentação apresentada fornece uma visão completa da estrutura, módulos, controladores e arquivos core da aplicação, servindo como guia para desenvolvedores e administradores do sistema.
