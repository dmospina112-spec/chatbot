<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authUser = $_SESSION['auth_user'] ?? null;
if (!is_array($authUser) || (($authUser['rol'] ?? '') !== 'docente')) {
    header('Location: index.php');
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Docente</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/styles.css?v=20260408-22">
  <link rel="stylesheet" href="chatbot/chatbot.css">
</head>
<body class="site-body">
  <script>
    window.__panelUser = <?php echo json_encode($authUser, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>

  <div class="container py-5">
    <header class="page-header text-center mb-5">
      <img src="img/Logo.png" alt="Logo Institución Educativa" class="brand-logo img-fluid mb-3">
      <h1 class="text-primary">Panel Docente</h1>
      <h2 class="h4 text-primary">Institución Educativa Gilberto Alzate Avendaño</h2>
      <p id="userGreeting" class="text-muted small mt-2"></p>
    </header>
  </div>

  <?php include __DIR__ . '/panel-content.php'; ?>

  <script>
    (function () {
      const badge = document.getElementById('userGreeting');
      if (!badge || !window.__panelUser) {
        return;
      }
      const name = [window.__panelUser.nombre, window.__panelUser.apellido].filter(Boolean).join(' ');
      badge.textContent = name
        ? `Sesión activa: ${name} (${window.__panelUser.rol})`
        : `Sesión activa: ${window.__panelUser.rol}`;
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="js/script.js?v=20260408-8"></script>
  <script src="js/estudiantes.js?v=20260408-12"></script>

  <div id="chatbot-bubble" aria-hidden="false" title="Abrir asistente virtual">💬</div>
  <div id="chatbot-window" role="dialog" aria-label="Asistente Virtual">
    <div id="chatbot-header">
      Asistente Virtual
      <button class="close-btn" id="chatbot-close" aria-label="Cerrar chat" title="Cerrar (Esc)">✖</button>
    </div>
    <div id="chatbot-messages" aria-live="polite" aria-label="Historial de conversación"></div>
    <div id="chatbot-input-area">
      <input type="text" id="chatbot-input" placeholder="Escribe tu mensaje..." aria-label="Campo de entrada de mensajes">
      <button id="chatbot-send" title="Enviar mensaje (Enter)">Enviar</button>
    </div>
  </div>
  <script src="chatbot/chatbot.js" defer></script>
</body>
</html>
