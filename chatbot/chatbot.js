document.addEventListener("DOMContentLoaded", function () {
    const bubble = document.getElementById("chatbot-bubble");
    const windowChat = document.getElementById("chatbot-window");
    const sendBtn = document.getElementById("chatbot-send");
    const input = document.getElementById("chatbot-input");
    const messages = document.getElementById("chatbot-messages");
    const closeBtn = document.getElementById("chatbot-close");

    if (!bubble || !windowChat || !sendBtn || !input || !messages) return;

    // Base de conocimiento - FAQ
    const faq = {
        1: {
            pregunta: "¿Cómo puedo acceder a las plantillas dentro de la aplicación?",
            respuesta: "Ingresa a la aplicación con tu usuario y contraseña."
        },
        2: {
            pregunta: "¿Cómo elijo una plantilla para mi clase?",
            respuesta: "Selecciona la plantilla que mejor se adapte a tu necesidad."
        },
        3: {
            pregunta: "¿Puedo modificar una plantilla antes de compartirla?",
            respuesta: "Sí, puedes seleccionar otras opciones."
        },
        4: {
            pregunta: "Salir",
            respuesta: "Un gusto ayudarte profe. ¡Hasta pronto!"
        }
    };

    let chatInitialized = false;

    // Toggle ventana
    function toggleChat() {
        if (windowChat.style.display === "flex") {
            windowChat.style.display = "none";
            bubble.setAttribute('aria-hidden', 'false');
            windowChat.setAttribute('aria-hidden', 'true');
        } else {
            windowChat.style.display = "flex";
            windowChat.style.flexDirection = 'column';
            input.focus();
            bubble.setAttribute('aria-hidden', 'true');
            windowChat.setAttribute('aria-hidden', 'false');
            
            // Mostrar menú inicial si es la primera vez
            if (!chatInitialized) {
                showWelcomeMenu();
                chatInitialized = true;
            }
        }
    }

    // Mostrar mensaje del bot
    function appendMessage(text, sender, isHtml = false) {
        const div = document.createElement('div');
        div.className = 'message ' + sender;
        if (isHtml) {
            div.innerHTML = text;
        } else {
            div.textContent = text;
        }
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    // Mostrar menú de bienvenida
    function showWelcomeMenu() {
        messages.innerHTML = '';
        appendMessage('¡Hola profe! ¿En qué puedo ayudarte?', 'bot');
        
        // Crear contenedor de opciones
        const opcionsDiv = document.createElement('div');
        opcionsDiv.className = 'message bot menu-options';
        
        let menuHTML = '<div class="menu-container">';
        for (let i = 1; i <= 4; i++) {
            menuHTML += `<button class="menu-btn" data-option="${i}"><strong>${i}.</strong> ${faq[i].pregunta}</button>`;
        }
        menuHTML += '</div>';
        
        opcionsDiv.innerHTML = menuHTML;
        messages.appendChild(opcionsDiv);
        messages.scrollTop = messages.scrollHeight;

        // Agregar event listeners a los botones
        document.querySelectorAll('.menu-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const option = this.getAttribute('data-option');
                handleMenuSelection(option);
            });
        });

        // Ocultar input durante el menú
        input.style.display = 'none';
        sendBtn.style.display = 'none';
    }

    // Manejar selección del menú
    function handleMenuSelection(option) {
        const selectedFaq = faq[option];
        
        // Mostrar pregunta del usuario
        appendMessage(selectedFaq.pregunta, 'user');
        
        // Mostrar respuesta del bot
        setTimeout(() => {
            if (option == 4) {
                appendMessage(selectedFaq.respuesta, 'bot');
                setTimeout(() => {
                    toggleChat(); // Cerrar chat después de despedida
                }, 1500);
            } else {
                appendMessage(selectedFaq.respuesta, 'bot');
                setTimeout(() => {
                    showWelcomeMenu(); // Volver al menú
                }, 1500);
            }
        }, 400);
    }

    bubble.addEventListener("click", toggleChat);
    if (closeBtn) closeBtn.addEventListener('click', toggleChat);

    // Enviar mensaje personalizado
    function sendMessage() {
        const text = input.value.trim();
        if (text === '') return;
        appendMessage(text, 'user');
        input.value = '';

        setTimeout(() => {
            appendMessage('Gracias por tu mensaje. Usa las opciones del menú para más información.', 'bot');
        }, 300);
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') sendMessage();
    });

    // Cerrar con Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && windowChat.style.display === 'flex') {
            toggleChat();
        }
    });

    // Cerrar al hacer clic fuera (excepto en botones)
    document.addEventListener('click', function (e) {
        const target = e.target;
        if (windowChat.style.display === 'flex' && 
            !windowChat.contains(target) && 
            !bubble.contains(target) &&
            !target.classList.contains('menu-btn')) {
            toggleChat();
        }
    });
});