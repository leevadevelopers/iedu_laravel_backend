# 📊 Sistema de Escalas de Avaliação - Grade Scales

Sistema completo de escalas de avaliação que suporta múltiplos formatos de notas (A-F, 0-20, 0-100%, 0-10, etc.).

---

## 🎯 Visão Geral

O sistema de escalas de avaliação permite que cada escola/tenant configure e utilize diferentes sistemas de notas, com conversão automática entre escalas e cálculo de GPA.

### Tipos de Escalas Suportadas

1. **Letter** (Letras): A, B, C, D, F (sistema americano)
2. **Points** (Pontos): 0-20 (português), 0-10 (brasileiro)
3. **Percentage** (Percentual): 0-100%
4. **Standards** (Baseado em padrões): Personalizado

---

## 📋 Estrutura de Dados

### Tabela: `grade_scales`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | bigint | ID único |
| `grading_system_id` | bigint | Sistema de notas |
| `school_id` | bigint | Escola |
| `tenant_id` | bigint | Tenant |
| `name` | string | Nome da escala |
| `scale_type` | enum | letter, percentage, points, standards |
| `is_default` | boolean | Se é a escala padrão |

### Tabela: `grade_scale_ranges`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | bigint | ID único |
| `grade_scale_id` | bigint | Escala de notas |
| `min_value` | decimal | Valor mínimo |
| `max_value` | decimal | Valor máximo |
| `display_label` | string | Label (A, B, 18-20, etc.) |
| `description` | string | Descrição (Excelente, Bom, etc.) |
| `color` | string | Cor para UI (#10B981) |
| `gpa_equivalent` | decimal | Equivalente GPA (0.00-4.00) |
| `is_passing` | boolean | Se é nota de aprovação |
| `order` | integer | Ordem de exibição |

---

## 🚀 Instalação

### 1. Executar Migration

```bash
php artisan migrate
```

### 2. Executar Seeder

```bash
php artisan db:seed --class=GradeScalesSeeder
```

Isto criará 4 escalas pré-configuradas:
- ✅ **Escala 0-20** (Sistema Português) - PADRÃO
- ✅ **Escala A-F** (Sistema Americano)
- ✅ **Escala 0-100%** (Sistema Percentual)
- ✅ **Escala 0-10** (Sistema Brasileiro)

---

## 📡 Endpoints da API

### Listar Escalas

```http
GET /api/v1/assessments/grade-scales

Query Parameters:
- grading_system_id: int (filtrar por sistema)
- school_id: int (filtrar por escola)
- scale_type: string (letter, percentage, points, standards)
- only_default: boolean (apenas escalas padrão)
- only_active: boolean (apenas sistemas ativos)
- per_page: int (paginação, padrão: 15)
```

**Resposta:**
```json
{
  "success": true,
  "message": "Grade scales retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Escala 0-20",
      "scale_type": "points",
      "is_default": true,
      "ranges": [
        {
          "id": 1,
          "min_value": 18,
          "max_value": 20,
          "display_label": "18-20",
          "description": "Excelente",
          "color": "#10B981",
          "gpa_equivalent": 4.0,
          "is_passing": true
        }
      ]
    }
  ]
}
```

### Obter Escala Padrão

```http
GET /api/v1/assessments/grade-scales/default?school_id=1
```

### Criar Nova Escala

```http
POST /api/v1/assessments/grade-scales

Body:
{
  "grading_system_id": 1,
  "school_id": 1,
  "name": "Escala Customizada",
  "scale_type": "points",
  "is_default": false,
  "ranges": [
    {
      "min_value": 9,
      "max_value": 10,
      "display_label": "9-10",
      "description": "Excelente",
      "color": "#10B981",
      "gpa_equivalent": 4.0,
      "is_passing": true,
      "order": 0
    },
    {
      "min_value": 7,
      "max_value": 8.99,
      "display_label": "7-8.9",
      "description": "Bom",
      "color": "#3B82F6",
      "gpa_equivalent": 3.0,
      "is_passing": true,
      "order": 1
    }
  ]
}
```

### Converter Nota

```http
POST /api/v1/assessments/grade-scales/{id}/convert

Body:
{
  "score": 85
}

Response:
{
  "success": true,
  "data": {
    "original_score": 85,
    "scale_name": "Escala 0-20",
    "scale_type": "points",
    "grade": "16-17",
    "description": "Muito Bom",
    "color": "#3B82F6",
    "gpa_equivalent": 3.7,
    "is_passing": true
  }
}
```

### Converter Entre Escalas

```http
POST /api/v1/assessments/grade-scales/convert-between

Body:
{
  "score": 18,
  "from_scale_id": 1,  // Escala 0-20
  "to_scale_id": 2     // Escala A-F
}

Response:
{
  "success": true,
  "data": {
    "from_scale": "Escala 0-20",
    "from_score": 18,
    "percentage": 90,
    "to_scale": "Escala A-F",
    "to_grade": "A"
  }
}
```

### Calcular GPA

```http
POST /api/v1/assessments/grade-scales/{id}/calculate-gpa

Body:
{
  "grades": [
    { "score": 18, "weight": 1 },
    { "score": 16, "weight": 1 },
    { "score": 19, "weight": 2 }
  ]
}

Response:
{
  "success": true,
  "data": {
    "gpa": 3.85,
    "scale": "Escala 0-20",
    "grades_count": 3
  }
}
```

### Adicionar/Atualizar Intervalo

```http
POST /api/v1/assessments/grade-scales/{id}/ranges

Body:
{
  "range_id": null,  // null para criar, ID para atualizar
  "min_value": 5,
  "max_value": 5.99,
  "display_label": "5-5.9",
  "description": "Suficiente",
  "color": "#FBBF24",
  "gpa_equivalent": 2.0,
  "is_passing": true,
  "order": 4
}
```

### Deletar Intervalo

```http
DELETE /api/v1/assessments/grade-scales/ranges/{rangeId}
```

---

## 💡 Uso no Código

### 1. Conversão Automática ao Inserir Nota

```php
// Ao inserir uma nota, o sistema automaticamente converte para a escala padrão
$gradeEntry = $gradeService->enterGrade([
    'student_id' => 1,
    'class_id' => 1,
    'academic_term_id' => 1,
    'assessment_name' => 'Teste de Matemática',
    'assessment_type' => 'summative',
    'points_earned' => 85,
    'points_possible' => 100,
    'use_grade_scale' => true  // Usar escala padrão
]);

// A nota será automaticamente convertida:
// 85/100 = 85% → "16-17" (Muito Bom) na escala 0-20
```

### 2. Aplicar Escala a Nota Existente

```php
$gradeService->applyGradeScale($gradeEntry, $gradeScaleId);
```

### 3. Obter Informação Detalhada da Nota

```php
$gradeInfo = $gradeService->getGradeWithScale(85, $gradeScaleId);
// Retorna: ['label' => 'A', 'description' => 'Excelente', 'color' => '#10B981', ...]
```

### 4. Usar no Model

```php
$gradeScale = GradeScale::with('ranges')->find(1);

// Converter nota
$grade = $gradeScale->convertScoreToGrade(85);

// Apenas o label
$label = $gradeScale->getGradeLabel(85);

// Verificar se passa
$isPassing = $gradeScale->isPassing(85);

// GPA equivalente
$gpa = $gradeScale->getGPAEquivalent(85);

// Converter de percentagem
$grade = $gradeScale->convertFromPercentage(85);
```

---

## 🎨 Escalas Pré-Configuradas

### 1. Escala 0-20 (Portugal) - PADRÃO

| Intervalo | Label | Descrição | Cor | GPA | Passa? |
|-----------|-------|-----------|-----|-----|--------|
| 18-20 | 18-20 | Excelente | 🟢 Verde | 4.0 | ✅ |
| 16-17.99 | 16-17 | Muito Bom | 🔵 Azul | 3.7 | ✅ |
| 14-15.99 | 14-15 | Bom | 🔷 Ciano | 3.3 | ✅ |
| 12-13.99 | 12-13 | Suficiente | 🟠 Laranja | 3.0 | ✅ |
| 10-11.99 | 10-11 | Satisfaz | 🟡 Amarelo | 2.0 | ✅ |
| 0-9.99 | 0-9 | Insuficiente | 🔴 Vermelho | 0.0 | ❌ |

### 2. Escala A-F (EUA)

| Intervalo | Label | GPA |
|-----------|-------|-----|
| 93-100 | A | 4.0 |
| 90-92.99 | A- | 3.7 |
| 87-89.99 | B+ | 3.3 |
| 83-86.99 | B | 3.0 |
| 80-82.99 | B- | 2.7 |
| ... | ... | ... |
| 0-59.99 | F | 0.0 |

### 3. Escala 0-100%

| Intervalo | Label | Descrição |
|-----------|-------|-----------|
| 90-100% | 90-100% | Excelente |
| 80-89.99% | 80-89% | Muito Bom |
| 70-79.99% | 70-79% | Bom |
| 60-69.99% | 60-69% | Satisfatório |
| 50-59.99% | 50-59% | Suficiente |
| 0-49.99% | 0-49% | Insuficiente |

### 4. Escala 0-10 (Brasil)

| Intervalo | Label | Descrição |
|-----------|-------|-----------|
| 9-10 | 9-10 | Excelente |
| 8-8.99 | 8-8.9 | Ótimo |
| 7-7.99 | 7-7.9 | Bom |
| 6-6.99 | 6-6.9 | Satisfatório |
| 5-5.99 | 5-5.9 | Suficiente |
| 0-4.99 | 0-4.9 | Insuficiente |

---

## 🔄 Fluxo de Conversão

```
Nota Numérica (85/100)
       ↓
Percentagem (85%)
       ↓
Escala Padrão (0-20)
       ↓
Label (16-17)
       ↓
Descrição (Muito Bom)
       ↓
GPA (3.7)
```

---

## ⚙️ Configuração

### Definir Escala Padrão

```php
// Apenas uma escala por escola pode ser padrão
$gradeScale->update(['is_default' => true]);
// Automaticamente remove is_default das outras escalas
```

### Validação de Intervalos

O sistema valida automaticamente:
- ✅ Intervalos não podem sobrepor
- ✅ min_value <= max_value
- ✅ Todos os valores devem ser numéricos

---

## 📊 Relatórios e Analytics

### Distribuição de Notas

```php
// Obter distribuição por escala
$distribution = GradeScaleRange::where('grade_scale_id', $scaleId)
    ->withCount('gradeEntries')
    ->get();
```

### GPA Médio da Turma

```php
$grades = GradeEntry::where('class_id', $classId)
    ->where('academic_term_id', $termId)
    ->get();

$gpa = $gradeScaleService->calculateGPA(
    $grades->map(fn($g) => ['score' => $g->percentage_score, 'weight' => $g->weight])->toArray(),
    $gradeScale
);
```

---

## 🎯 Casos de Uso

### 1. Escola Internacional

- **Escala A-F** para relatórios internacionais
- **Escala 0-20** para uso interno
- Conversão automática entre escalas

### 2. Ensino Superior

- **Escala 0-20** para avaliação
- **GPA 0-4** para transcript
- Cálculo automático de GPA

### 3. Ensino Básico

- **Escala 0-10** simplificada
- Notas descritivas (Excelente, Bom, etc.)
- Cores para visualização rápida

---

## 🔧 Troubleshooting

### Erro: "Range overlap detected"

**Causa:** Intervalos de notas sobrepõem-se.  
**Solução:** Ajustar min_value e max_value para não haver overlap.

### Erro: "Cannot delete default grade scale"

**Causa:** Tentativa de deletar escala padrão.  
**Solução:** Definir outra escala como padrão primeiro.

### Nota não converte automaticamente

**Causa:** `use_grade_scale` está false ou não há escala padrão.  
**Solução:** Definir uma escala como padrão ou passar `use_grade_scale: true`.

---

## 📝 Exemplos Práticos

### Criar Escala Customizada

```bash
curl -X POST http://localhost:8000/api/v1/assessments/grade-scales \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "grading_system_id": 1,
    "name": "Minha Escala",
    "scale_type": "points",
    "ranges": [
      {
        "min_value": 8,
        "max_value": 10,
        "display_label": "Ótimo",
        "color": "#10B981",
        "gpa_equivalent": 4.0,
        "is_passing": true
      }
    ]
  }'
```

---

**Sistema de Escalas de Avaliação completo e funcional! 🎉**

