# 🚀 Assessment Module - Quick Start Guide

Guia rápido de instalação e configuração do módulo de Assessment & Grades.

---

## ⚡ Instalação Rápida

### 1. Executar Migrations

```bash
php artisan migrate
```

### 2. Executar Seeders

```bash
php artisan db:seed --class=AssessmentPermissionsSeeder
php artisan db:seed --class=AssessmentTypesSeeder
php artisan db:seed --class=GradeScalesSeeder
```

**Isto criará:**
- ✅ Todas as permissões do módulo
- ✅ 6 tipos de avaliação pré-configurados
- ✅ 4 escalas de notas (0-20, A-F, 0-100%, 0-10)

### 3. Limpar Cache de Permissões

```bash
php artisan permission:cache-reset
php artisan config:clear
php artisan cache:clear
```

### 4. Iniciar Queue Worker (opcional, mas recomendado)

```bash
php artisan queue:work
```

---

## 📋 Checklist de Verificação

- [ ] Migrations executadas com sucesso
- [ ] Seeders executados (permissões, tipos e escalas)
- [ ] Service Provider registado em `bootstrap/providers.php`
- [ ] Rotas registadas em `routes/api.php`
- [ ] Cache de permissões limpo
- [ ] Broadcasting configurado (Pusher) se necessário
- [ ] Queue worker a correr

---

## 🧪 Teste Rápido

### Criar uma Avaliação (via API)

```bash
curl -X POST http://localhost:8000/api/v1/assessments \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "term_id": 1,
    "subject_id": 1,
    "class_id": 1,
    "type_id": 1,
    "title": "Teste de Matemática",
    "total_marks": 100,
    "scheduled_date": "2025-11-15 10:00:00"
  }'
```

### Listar Avaliações

```bash
curl -X GET http://localhost:8000/api/v1/assessments \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Inserir Nota com Conversão Automática

```bash
curl -X POST http://localhost:8000/api/v1/assessments/grades \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "student_id": 1,
    "class_id": 1,
    "academic_term_id": 1,
    "assessment_name": "Teste de Matemática",
    "assessment_type": "summative",
    "points_earned": 85,
    "points_possible": 100,
    "use_grade_scale": true
  }'
```
**O sistema converterá automaticamente:** 85/100 = 85% → "16-17" (Muito Bom)

### Converter Entre Escalas

```bash
curl -X POST http://localhost:8000/api/v1/assessments/grade-scales/convert-between \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "score": 18,
    "from_scale_id": 1,
    "to_scale_id": 2
  }'
```
**Resultado:** 18 (escala 0-20) → A (escala A-F)

---

## 🔑 Permissões Principais

Assegure-se de que os utilizadores têm as permissões adequadas:

**Professor:**
```php
$teacher->givePermissionTo([
    'assessments.view',
    'assessments.create',
    'assessments.update',
    'grades.enter',
    'grades.publish',
]);
```

**Aluno:**
```php
$student->givePermissionTo('grades.view');
```

**Admin:**
```php
$admin->givePermissionTo([
    'assessments.*',
    'grades.*',
    'grade_scales.manage',
    'assessment.settings.manage',
]);
```

---

## 📊 Dados de Teste

### Tipos de Avaliação Criados
- Teste (20%)
- Trabalho (15%)
- Exame (40%)
- Apresentação (10%)
- Projeto (25%)
- Participação (5%)

### Escalas de Notas Criadas
- **0-20**: Sistema Português (padrão) - 6 intervalos
- **A-F**: Sistema Americano - 12 intervalos (A, A-, B+, B, B-, etc.)
- **0-100%**: Sistema Percentual - 6 intervalos
- **0-10**: Sistema Brasileiro - 6 intervalos

**Funcionalidades das Escalas:**
- ✅ Conversão automática ao inserir notas
- ✅ Conversão entre diferentes escalas
- ✅ Cálculo de GPA (0.00-4.00)
- ✅ Identificação de notas de aprovação/reprovação
- ✅ Cores para UI
- ✅ Descrições em português

---

## 🐛 Problemas Comuns

### Erro: "Class not found"
```bash
composer dump-autoload
```

### Erro: "Permission denied"
```bash
php artisan permission:cache-reset
```

### Eventos não estão a ser disparados
Verificar se o EventServiceProvider tem os listeners registados.

### Notificações não são enviadas
```bash
# Verificar queue worker
php artisan queue:work

# Verificar logs
tail -f storage/logs/laravel.log
```

---

## 📚 Próximos Passos

1. Consultar o **README_Assessment.md** completo
2. Testar todos os endpoints via Postman/Insomnia
3. Configurar Broadcasting (Pusher) para notificações em tempo real
4. Integrar com o frontend
5. Personalizar escalas de notas conforme necessário

---

## ✅ Estrutura Criada

```
✅ 13 Migrations
✅ 13 Models
✅ 7 Controllers
✅ 16 Form Requests
✅ 14 API Resources
✅ 6 Events
✅ 6 Listeners
✅ 6 Notifications
✅ 5 Jobs
✅ 5 Services
✅ 6 Policies
✅ 3 Seeders
✅ 1 Service Provider
✅ Rotas completas
✅ Documentação
```

---

**Total de arquivos criados: 100+**

**Está tudo pronto! 🎉**

