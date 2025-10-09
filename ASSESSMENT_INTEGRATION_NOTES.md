# 📝 Assessment Module - Integration Notes

## ✅ Integração com Implementação Existente

Este documento descreve como o novo módulo de **Assessment & Grades** foi integrado com as tabelas e models já existentes no sistema.

---

## 🔄 Mudanças Principais

### 1. Uso da Tabela `grade_entries` Existente

Em vez de criar uma nova tabela, o módulo foi adaptado para usar a tabela `grade_entries` já existente:

**Campos da tabela existente:**
- `student_id`
- `class_id`
- `academic_term_id`
- `assessment_name` - Nome da avaliação
- `assessment_type` - Tipo (formative, summative, project, etc.)
- `assessment_date` - Data da avaliação
- `points_earned` - Pontos obtidos
- `points_possible` - Pontos possíveis
- `percentage_score` - Percentagem
- `letter_grade` - Nota em letra (A, B, C, etc.)
- `grade_category` - Categoria da nota
- `weight` - Peso da avaliação
- `teacher_comments` - Comentários do professor
- `private_notes` - Notas privadas
- `entered_by` - Professor que inseriu
- `modified_by` - Professor que modificou

**Model utilizado:**
- `App\Models\V1\Academic\GradeEntry`

### 2. Sistema Completo de Escalas de Avaliação ✅

A tabela `grade_scales` existente foi estendida com uma nova tabela `grade_scale_ranges` para suportar múltiplos formatos de notas:

**Tabelas:**
- ✅ `grade_scales` - Escalas de avaliação (existente)
- ✅ `grade_scale_ranges` - Intervalos/faixas de cada escala (NOVA)

**Models Criados:**
- ✅ `App\Models\V1\Academic\GradeScale` - Com métodos de conversão
- ✅ `App\Models\V1\Academic\GradeScaleRange` - Intervalos de notas
- ✅ `App\Models\V1\Academic\GradingSystem` - Sistema de notas

**Funcionalidades:**
- ✅ Suporta 4 tipos: **letter** (A-F), **points** (0-20, 0-10), **percentage** (0-100%), **standards**
- ✅ Conversão automática de notas numéricas para escalas
- ✅ Conversão entre diferentes escalas
- ✅ Cálculo de GPA (0.00-4.00)
- ✅ 4 escalas pré-configuradas (0-20 PT, A-F USA, 0-100%, 0-10 BR)
- ✅ API completa para gestão de escalas

**Escalas Pré-Configuradas:**
1. **Escala 0-20** (Sistema Português) - Padrão
2. **Escala A-F** (Sistema Americano)  
3. **Escala 0-100%** (Sistema Percentual)
4. **Escala 0-10** (Sistema Brasileiro)

**Ver documentação completa:** `README_GRADE_SCALES.md`

---

## 📦 Componentes Mantidos do Novo Módulo

### Migrations Criadas (9 novas tabelas)
✅ `assessment_terms` - Períodos de avaliação  
✅ `assessment_types` - Tipos de avaliação  
✅ `assessments` - Avaliações  
✅ `assessment_components` - Componentes de avaliação  
✅ `gradebooks` - Pautas  
✅ `gradebook_files` - Ficheiros de pautas  
✅ `grade_reviews` - Pedidos de revisão  
✅ `assessment_resources` - Recursos de avaliações  
✅ `assessment_settings` - Configurações  
✅ `grades_audit_logs` - Logs de auditoria  

### Models Criados (10 novos)
✅ `Assessment`  
✅ `AssessmentTerm`  
✅ `AssessmentType`  
✅ `AssessmentComponent`  
✅ `AssessmentResource`  
✅ `AssessmentSettings`  
✅ `Gradebook`  
✅ `GradebookFile`  
✅ `GradeReview`  
✅ `GradesAuditLog`  
✅ `GradeScale` (V1/Academic) - Sistema de escalas  
✅ `GradeScaleRange` (V1/Academic) - Intervalos de escalas  
✅ `GradingSystem` (V1/Academic) - Sistema de notas  

**Model adaptado:**
✅ `GradeEntry` (V1/Academic) - Adicionadas relações com `reviews()` e `auditLogs()`

### Controllers (6)
✅ `AssessmentController`  
✅ `AssessmentSettingsController`  
✅ `GradeEntryController` - Adaptado para usar modelo existente  
✅ `GradeReviewController`  
✅ `GradebookController`  
✅ `ReportController`  
✅ `GradeScaleController` - **NOVO** - Gestão completa de escalas  

### Services (5)
✅ `AssessmentService`  
✅ `GradeService` - Adaptado com conversão automática de escalas  
✅ `GradeReviewService`  
✅ `ReportService`  
✅ `GradeScaleService` - **NOVO** - Conversões e cálculos de GPA  

### Events & Listeners (6 + 6)
✅ Todos os eventos e listeners mantidos

### Notifications (6)
✅ Todas as notificações mantidas

### Jobs (5)
✅ Todos os jobs mantidos

### Policies (5)
✅ `AssessmentPolicy`  
✅ `AssessmentSettingsPolicy`  
✅ `GradeEntryPolicy` - Adaptada para modelo existente  
✅ `GradeReviewPolicy`  
✅ `GradebookPolicy`  

---

## 🔀 Mapeamento de Campos

### Ao Criar/Atualizar Notas

O `GradeService` faz o mapeamento automático:

```php
// Campos novos (API) → Campos existentes (BD)
'marks_awarded'    → 'points_earned'
'total_marks'      → 'points_possible'
'grade_value'      → 'letter_grade'
'remarks'          → 'teacher_comments'
'is_published'     → (lógica mantida no módulo Assessment)
'component_id'     → (armazenado via 'grade_category')
```

### Relacionamento Assessment ↔ GradeEntry

```php
// No modelo Assessment
public function gradeEntries()
{
    return $this->hasMany(GradeEntry::class, 'assessment_name', 'title')
                ->where('class_id', $this->class_id)
                ->where('academic_term_id', $this->term_id);
}
```

---

## 🔧 Alterações Necessárias nos Form Requests

### StoreGradeEntryRequest
Campos obrigatórios adaptados:
```php
'student_id' => 'required|exists:students,id',
'class_id' => 'required|exists:classes,id',
'academic_term_id' => 'required|exists:academic_terms,id',
'assessment_name' => 'required|string|max:255',
'assessment_type' => 'required|in:formative,summative,project,...',
'points_earned' => 'nullable|numeric|min:0',
'points_possible' => 'nullable|numeric|min:0',
```

---

## 📊 Endpoints API

### Grade Entries (usando tabela existente)

```
GET    /api/v1/assessments/grades
POST   /api/v1/assessments/grades
GET    /api/v1/assessments/grades/{id}
PUT    /api/v1/assessments/grades/{id}
DELETE /api/v1/assessments/grades/{id}
GET    /api/v1/assessments/grades/student/{studentId}
POST   /api/v1/assessments/grades/bulk-import
POST   /api/v1/assessments/{id}/grades/publish
```

### Assessments (novas tabelas)

```
GET    /api/v1/assessments
POST   /api/v1/assessments
GET    /api/v1/assessments/{id}
PUT    /api/v1/assessments/{id}
DELETE /api/v1/assessments/{id}
PATCH  /api/v1/assessments/{id}/status
POST   /api/v1/assessments/{id}/lock
```

---

## 🚫 Componentes Removidos/Recriados

Para evitar conflitos com a implementação existente:

### Removidos (duplicados):
❌ `database/migrations/2025_10_09_000005_create_grade_entries_table.php` (duplicado)  
❌ `database/migrations/2025_10_09_000008_create_grade_scales_table.php` (duplicado)  
❌ `database/migrations/2025_10_09_000009_create_grade_scale_mappings_table.php` (estrutura diferente)  
❌ `app/Models/Assessment/GradeEntry.php` (duplicado)  
❌ `app/Models/Assessment/GradeScale.php` (duplicado)  
❌ `app/Models/Assessment/GradeScaleMapping.php` (substituído por GradeScaleRange)  

### Recriados (adaptados):
✅ `app/Models/V1/Academic/GradeScale.php` - Recriado com métodos avançados  
✅ `app/Models/V1/Academic/GradeScaleRange.php` - Nova estrutura  
✅ `app/Http/Controllers/API/V1/Assessment/GradeScaleController.php` - Recriado  
✅ `app/Services/Assessment/GradeScaleService.php` - Recriado  
✅ `database/seeders/GradeScalesSeeder.php` - Recriado com 4 escalas  

---

## 💡 Exemplos de Uso

### Criar uma Avaliação

```bash
POST /api/v1/assessments
{
  "term_id": 1,
  "subject_id": 5,
  "class_id": 10,
  "type_id": 1,
  "title": "Teste de Matemática",
  "total_marks": 100,
  "scheduled_date": "2025-11-15"
}
```

### Inserir Nota com Conversão Automática

```bash
POST /api/v1/assessments/grades
{
  "student_id": 50,
  "class_id": 10,
  "academic_term_id": 1,
  "assessment_name": "Teste de Matemática",
  "assessment_type": "summative",
  "assessment_date": "2025-11-15",
  "points_earned": 85,
  "points_possible": 100,
  "use_grade_scale": true,  // Usa escala padrão automaticamente
  "teacher_comments": "Bom desempenho"
}

// Sistema converte automaticamente:
// 85/100 = 85% → "16-17" (Muito Bom) na escala 0-20
```

### Converter Nota Entre Escalas

```bash
POST /api/v1/assessments/grade-scales/convert-between
{
  "score": 18,           // Nota na escala 0-20
  "from_scale_id": 1,    // Escala 0-20
  "to_scale_id": 2       // Escala A-F
}

// Retorna: { "to_grade": "A" }
```

### Solicitar Revisão de Nota

```bash
POST /api/v1/assessments/grade-reviews
{
  "grade_entry_id": 100,
  "reason": "Penso que a resposta está correta",
  "details": "Na questão 5..."
}
```

---

## ✅ Benefícios da Integração

1. **Sem Duplicação**: Reutiliza tabelas existentes
2. **Compatibilidade**: Mantém dados históricos
3. **Flexibilidade**: Permite usar funcionalidades novas e antigas
4. **Auditoria**: Adiciona logs sem quebrar estrutura existente
5. **Escalabilidade**: Novos recursos (reviews, workflows) sem migração de dados

---

## 📝 Próximos Passos

1. ✅ Implementação concluída e integrada
2. ✅ Sistema de Grade Scales completo e funcional
3. ⏳ Testar endpoints com dados reais
4. ⏳ Implementar importação de CSV/Excel
5. ⏳ Adicionar relatórios avançados com GPA
6. ⏳ Interface UI para gestão visual de escalas

---

## 🔗 Documentação Relacionada

- `README_Assessment.md` - Documentação completa do módulo
- `README_GRADE_SCALES.md` - **NOVO** - Sistema de escalas de avaliação
- `ASSESSMENT_QUICK_START.md` - Guia de instalação rápida
- `app/Models/V1/Academic/GradeEntry.php` - Model existente adaptado

---

**Última Atualização:** 9 de Outubro de 2025

