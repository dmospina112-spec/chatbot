// Usuario 
/*
    Un objeto en JavaScript es una estructura de datos 
    que almacena pares clave-valor. En este caso:

        user → "admin"
        pass → "1234"*/
        const usuarioValido = {
          user: "admin",
          pass: "1234"
        };
        
        // Evento al enviar el formulario
        document.getElementById("loginForm").addEventListener("submit", function(e) {
          e.preventDefault();
        
          const user = document.getElementById("user").value.trim();
          const pass = document.getElementById("pass").value.trim();
          const mensaje = document.getElementById("loginMensaje");
        
          // Verificar credenciales
          if (user === usuarioValido.user && pass === usuarioValido.pass) {
            window.location.href = "index.html"; // redirigir
          } else {
            mensaje.style.color = "red";
            mensaje.textContent = "Usuario o contraseña incorrectos.";
          }
        });


