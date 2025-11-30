<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificado de Matrícula</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            margin: 40px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .school-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .certificate-title {
            font-size: 16px;
            font-weight: bold;
            margin: 30px 0;
            text-decoration: underline;
        }
        .content {
            text-align: justify;
            margin: 20px 0;
        }
        .signature-section {
            margin-top: 60px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 300px;
            margin: 50px auto 10px;
        }
        .date {
            text-align: right;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-name">{{ $school->official_name ?? 'ESCOLA' }}</div>
        <div>Certificado de Matrícula</div>
    </div>

    <div class="certificate-title">CERTIFICADO DE MATRÍCULA</div>

    <div class="content">
        <p>
            Certificamos que <strong>{{ $student->first_name }} {{ $student->middle_name ?? '' }} {{ $student->last_name }}</strong>,
            portador(a) do BI/Passaporte Nº <strong>{{ $student->national_id ?? 'N/A' }}</strong>,
            está devidamente matriculado(a) nesta instituição de ensino no ano letivo de <strong>{{ $student->currentAcademicYear->name ?? date('Y') }}</strong>,
            frequentando a <strong>{{ $student->current_grade_level ?? 'N/A' }}</strong>.
        </p>

        @if($document->purpose)
        <p><strong>Finalidade:</strong> {{ $document->purpose }}</p>
        @endif

        @if($document->notes)
        <p><strong>Observações:</strong> {{ $document->notes }}</p>
        @endif
    </div>

    <div class="date">
        <p>{{ $date }}</p>
    </div>

    <div class="signature-section">
        <div class="signature-line"></div>
        <p><strong>{{ $document->signed_by ?? 'Diretor' }}</strong></p>
    </div>
</body>
</html>

