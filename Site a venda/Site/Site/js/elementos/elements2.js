// Adiciona a classe "scrolled" à navbar quando a página é rolada para baixo
window.addEventListener("scroll", () => {
    const navbar = document.querySelector(".navbar2");
    if (window.scrollY > 50) {
        navbar.classList.add("scrolled");
    } else {
        navbar.classList.remove("scrolled");
    }
});

// Tela de carregamento
window.addEventListener('DOMContentLoaded', function() {
    var loadingScreen = document.getElementById('loading-screen');
    setTimeout(function() {
        loadingScreen.style.opacity = '0';
        setTimeout(function() {
            loadingScreen.style.display = 'none';
        }, 500);
    }, 1000);
});

// Dropdown do menu de perfil - VERSÃO CORRIGIDA
document.addEventListener("DOMContentLoaded", function() {
    const profileBtn = document.getElementById("profile-btn");
    const dropdown = document.querySelector(".dropdown");
    const dropdownMenu = document.querySelector(".dropdown-menu");
    
    if (profileBtn) {
        profileBtn.addEventListener("click", function(event) {
            event.preventDefault();
            event.stopPropagation(); // Impede que o clique se propague
            dropdown.classList.toggle("active");
        });
    }
    
    // Fechar dropdown quando clicar fora dele
    document.addEventListener("click", function(event) {
        if (dropdown && !dropdown.contains(event.target)) {
            dropdown.classList.remove("active");
        }
    });
    
    // Para dispositivos móveis
    const mobileProfileBtn = document.getElementById("mobile-profile-btn");
    if (mobileProfileBtn) {
        mobileProfileBtn.addEventListener("click", function(event) {
            event.preventDefault();
            event.stopPropagation();
            const mobileDropdown = mobileProfileBtn.closest('.dropdown');
            if (mobileDropdown) {
                mobileDropdown.classList.toggle("active");
            }
        });
    }
    
    // Adiciona CSS para mostrar o menu dropdown quando ativo
    if (!document.getElementById('dropdown-styles')) {
        const style = document.createElement('style');
        style.id = 'dropdown-styles';
        style.textContent = `
            .dropdown.active .dropdown-menu {
                display: block;
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
                transition: all 0.3s ease;
            }
        `;
        document.head.appendChild(style);
    }
});

// Redirecionamento para a página de login ao clicar no botão de login
document.addEventListener('DOMContentLoaded', function() {
    var loginBtn = document.getElementById('login-btn2');
    if (loginBtn) {
        loginBtn.addEventListener('click', function() {
            window.location.href = 'login_site.php';
        });
    }
    
    // Verificar se GSAP está disponível antes de executar animações
    if (typeof gsap !== 'undefined') {
        // Animação de entrada
        var tl = gsap.timeline();
        if (document.querySelector(".navbar2")) {
            tl.fromTo(".navbar2", { opacity: 0, y: -50 }, { opacity: 1, y: 0, duration: 1 })
              .fromTo(".logo2 img", { opacity: 0, x: -50 }, { opacity: 1, x: 0, duration: 1 }, "<")
              .fromTo(".nav-links2 a", { opacity: 0, y: -20 }, { opacity: 1, y: 0, stagger: 0.1, duration: 1 }, "<")
              .fromTo(".btn2", { opacity: 0, y: -20 }, { opacity: 1, y: 0, duration: 1 }, "<");
        }
    }
});

// Menu Hamburguer
document.addEventListener("DOMContentLoaded", () => {
    const menuBtn = document.getElementById("menu-btn");
    const mobileMenu = document.getElementById("mobile-menu");
    const toggleInput = document.getElementById("toggle-menu");
    
    if (menuBtn && mobileMenu && toggleInput) {
        menuBtn.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleInput.checked = !toggleInput.checked;
            mobileMenu.classList.toggle("active", toggleInput.checked);
        });
        
        // Fechar menu ao clicar fora
        document.addEventListener("click", (event) => {
            if (mobileMenu.classList.contains("active") && 
                !mobileMenu.contains(event.target) && 
                !menuBtn.contains(event.target)) {
                toggleInput.checked = false;
                mobileMenu.classList.remove("active");
            }
        });
        
        // Fechar menu quando clicar em links dentro do menu
        const mobileLinks = mobileMenu.querySelectorAll('a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', () => {
                toggleInput.checked = false;
                mobileMenu.classList.remove("active");
            });
        });
    }
});