# Sistema de Gestão Escolar - Multitenant Form Engine

Este sistema foi adaptado para gestão escolar com conceito multitenant e motor de formulários flexível.

## 🏫 Funcionalidades Principais

### 1. Gestão de Estudantes
- **Matrícula de estudantes** com dados completos
- **Gestão de turmas** e capacidade
- **Histórico acadêmico** integrado com formulários
- **Informações de contato** e emergência
- **Status de matrícula** (ativo, inativo, graduado, transferido, suspenso)

### 2. Gestão de Turmas
- **Criação de turmas** por nível escolar
- **Atribuição de professores**
- **Controle de capacidade** e vagas disponíveis
- **Horários e disciplinas**
- **Estatísticas da turma**

### 3. Gestão de Pais/Responsáveis
- **Cadastro de pais** com múltiplos relacionamentos
- **Contatos de emergência**
- **Preferências de comunicação**
- **Autorização para busca**

### 4. Sistema de Formulários Escolares
- **Formulários de matrícula** multistep
- **Formulários de frequência** diária
- **Relatórios de incidentes** comportamentais
- **Comunicação com pais**
- **Autorizações para excursões**
- **Informações de saúde** dos estudantes

## 🗄️ Estrutura do Banco de Dados

### Tabelas Principais
- `students` - Informações dos estudantes
- `school_classes` - Turmas escolares
- `student_parents` - Pais e responsáveis
- `form_templates` - Modelos de formulários
- `form_instances` - Instâncias de formulários preenchidos

### Relacionamentos
- Estudante → Turma (N:1)
- Estudante → Pais (N:1)
- Turma → Professor (N:1)
- Formulários → Estudante/Turma (N:1)

## 🚀 Como Usar

### 1. Configuração Inicial

```bash
# Executar migrações
php artisan migrate

# Executar seeders
php artisan db:seed --class=SchoolFormTemplatesSeeder
```

### 2. API Endpoints

#### Gestão de Estudantes
```http
POST /api/school/students/enroll
GET /api/school/students
GET /api/school/students/{id}/summary
```

#### Gestão de Turmas
```http
POST /api/school/classes
GET /api/school/classes
GET /api/school/classes/{id}/students
GET /api/school/classes/{id}/statistics
POST /api/school/students/assign-class
```

#### Formulários
```http
GET /api/school/form-templates
```

### 3. Exemplo de Matrícula

```json
POST /api/school/students/enroll
{
  "first_name": "João",
  "last_name": "Silva",
  "email": "joao.silva@email.com",
  "date_of_birth": "2010-05-15",
  "gender": "male",
  "grade_level": "8",
  "class_id": 1,
  "academic_year": "2025-2026",
  "parent": {
    "first_name": "Maria",
    "last_name": "Silva",
    "email": "maria.silva@email.com",
    "phone": "+55 11 99999-9999",
    "relationship_type": "mother",
    "is_primary_contact": true
  }
}
```

### 4. Exemplo de Criação de Turma

```json
POST /api/school/classes
{
  "class_name": "8º Ano A",
  "grade_level": "8",
  "academic_year": "2025-2026",
  "teacher_id": 1,
  "room_number": "Sala 201",
  "capacity": 35,
  "subjects": ["Matemática", "Português", "História", "Geografia"]
}
```

## 📋 Categorias de Formulários Escolares

### Categorias Disponíveis
- `student_enrollment` - Matrícula de estudantes
- `student_registration` - Registro anual
- `attendance` - Controle de frequência
- `grades` - Notas e avaliações
- `academic_records` - Registros acadêmicos
- `behavior_incident` - Incidentes comportamentais
- `parent_communication` - Comunicação com pais
- `teacher_evaluation` - Avaliação de professores
- `curriculum_planning` - Planejamento curricular
- `extracurricular` - Atividades extracurriculares
- `field_trip` - Excursões e passeios
- `parent_meeting` - Reuniões de pais
- `student_health` - Informações de saúde
- `special_education` - Educação especial
- `discipline` - Disciplina e comportamento
- `graduation` - Graduação e conclusão
- `scholarship` - Bolsas e auxílios

## 🔧 Configuração do Sistema

### 1. Variáveis de Ambiente
```env
# Configuração do banco de dados
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iedu_school
DB_USERNAME=root
DB_PASSWORD=

# URL do frontend para links públicos
FRONTEND_URL=http://localhost:3000
```

### 2. Middleware
O sistema utiliza os seguintes middlewares:
- `auth:api` - Autenticação JWT
- `tenant` - Contexto do tenant
- `cors` - Cross-origin resource sharing

### 3. Traits Utilizados
- `Tenantable` - Escopo de tenant
- `LogsActivityWithTenant` - Log de atividades
- `SoftDeletes` - Exclusão suave

## 📊 Relatórios e Estatísticas

### Estatísticas da Turma
- Capacidade total e vagas disponíveis
- Percentual de ocupação
- Distribuição por gênero
- Lista de estudantes matriculados

### Resumo Acadêmico do Estudante
- Informações básicas
- Turma atual
- Formulários submetidos por categoria
- Atividade recente

## 🔒 Segurança e Permissões

### Controle de Acesso
- Autenticação JWT obrigatória
- Escopo de tenant para isolamento de dados
- Validação de permissões por usuário
- Log de todas as atividades

### Validação de Dados
- Validação server-side rigorosa
- Sanitização de inputs
- Validação de relacionamentos
- Controle de integridade referencial

## 🚀 Próximos Passos

### Funcionalidades Planejadas
1. **Sistema de Notas** integrado com formulários
2. **Relatórios de Progresso** automáticos
3. **Comunicação em Massa** com pais
4. **Dashboard de Gestão** escolar
5. **Integração com Sistemas** externos
6. **Mobile App** para pais e estudantes

### Melhorias Técnicas
1. **Cache Redis** para performance
2. **Queue Jobs** para tarefas pesadas
3. **API Rate Limiting** para segurança
4. **WebSocket** para notificações em tempo real
5. **Testes Automatizados** completos

## 📞 Suporte

Para dúvidas ou suporte técnico:
- **Email**: suporte@iedu.com
- **Documentação**: https://docs.iedu.com
- **GitHub**: https://github.com/iedu/school-management

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo LICENSE para mais detalhes.
