<!DOCTYPE html>
<html>
<body>
    <h2>Sobre tu solicitud</h2>
    <p>Lamentablemente, tu solicitud para registrarte como profesional fue rechazada.</p>
    @if(($application->notes ?? '') !== '')
        <p>Motivo: {{ $application->notes }}</p>
    @endif
    <p>Puedes volver a intentarlo corrigiendo la información o documentos.</p>
    <p>Saludos,<br>Equipo Psicoguía</p>
</body>
<html>
