// Adiciona a classe "scrolled" à navbar quando a página é rolada para baixo
window.addEventListener("scroll", () => {
    const navbar = document.querySelector(".navbar");
    if (window.scrollY > 50) {
        navbar.classList.add("scrolled");
    } else {
        navbar.classList.remove("scrolled");
    }
});


// Tela de carregamento antes de exibir o conteúdo principal
window.addEventListener('DOMContentLoaded', function () {
    var loadingScreen = document.getElementById('loading-screen');
    setTimeout(function () {
        loadingScreen.style.opacity = '0';
        setTimeout(function () {
            loadingScreen.style.display = 'none';
        }, 500);
    }, 1000);
});


// Solução simples e direta para o dropdown
document.addEventListener("DOMContentLoaded", function() {
    // Seletor para o botão de perfil e dropdown
    const profileBtn = document.getElementById("profile-btn");
    
    // Função para lidar com o clique no botão de perfil
    if (profileBtn) {
        profileBtn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation(); // Previne propagação do clique
            
            // Encontra o dropdown parent e alterna a classe active
            const dropdown = this.closest('.dropdown');
            if (dropdown) {
                dropdown.classList.toggle("active");
                
                // Feche outros dropdowns que possam estar abertos
                document.querySelectorAll('.dropdown.active').forEach(function(activeDropdown) {
                    if (activeDropdown !== dropdown) {
                        activeDropdown.classList.remove('active');
                    }
                });
            }
        });
    }
    
    // Função para fechar dropdown ao clicar fora
    document.addEventListener("click", function(e) {
        document.querySelectorAll('.dropdown.active').forEach(function(dropdown) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    });
    
    
    // Suporte para o dropdown mobile
    const mobileProfileBtn = document.getElementById("mobile-profile-btn");
    if (mobileProfileBtn) {
        mobileProfileBtn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.closest('.dropdown');
            if (dropdown) {
                dropdown.classList.toggle("active");
            }
        });
    }
});

// Ajusta o espaçamento do scroll para evitar que a navbar fixe sobre os títulos das seções
document.addEventListener("DOMContentLoaded", function () {
    const navbarHeight = document.querySelector(".navbar").offsetHeight;
    document.documentElement.style.setProperty("--scroll-padding", navbarHeight + "px");
});

/*  Menu Hamburguer */


document.addEventListener("DOMContentLoaded", () => {
    const menuBtn = document.getElementById("menu-btn");
    const mobileMenu = document.getElementById("mobile-menu");
    const toggleInput = document.getElementById("toggle-menu");

    // Mostrar/fechar menu ao clicar no ícone
    menuBtn.addEventListener("click", () => {
        toggleInput.checked = !toggleInput.checked; // Alterna o estado do checkbox

        if (toggleInput.checked) {
            mobileMenu.classList.add("active"); // Adiciona a classe active para abrir o menu
        } else {
            mobileMenu.classList.remove("active"); // Remove a classe active para fechar o menu
        }
    });

    // Fechar menu ao clicar fora
    document.addEventListener("click", (event) => {
        if (!mobileMenu.contains(event.target) && !menuBtn.contains(event.target)) {
            toggleInput.checked = false; // Desmarca o checkbox
            mobileMenu.classList.remove("active"); // Fecha o menu
        }
    });
});

document.addEventListener("DOMContentLoaded", function() {
    const dropdown = document.querySelector(".dropdown");

    if (dropdown) {  // Verifica se o elemento existe antes de adicionar eventos
        dropdown.addEventListener("click", function() {
            this.classList.toggle("active");
        });

        document.addEventListener("click", function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove("active");
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.message');
    let currentIndex = 0;
    
    // Função para alternar as mensagens
    function toggleMessages() {
        // Esconde a mensagem atual
        messages[currentIndex].classList.remove('active');
        
        // Atualiza para a próxima mensagem
        currentIndex = (currentIndex + 1) % messages.length;
        
        // Mostra a nova mensagem
        messages[currentIndex].classList.add('active');
    }
    
    // Configura o intervalo para alternar as mensagens a cada 3 segundos
    setInterval(toggleMessages, 3000);
});

