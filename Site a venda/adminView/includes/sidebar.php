<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link href="assets/css/styles.css" rel="stylesheet">

<div class="sidebar z-50" id="mySidebar">
    <div class="side-header text-center">
<br>
        <h5 class="text-white mt-2">Olá, Administrador</h5>
    </div>

    <hr class="border-white">

    <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">×</a>

    <a href="#" onclick="showDashboard()" class="flex items-center gap-2 px-4 py-2 text-gray-300 hover:bg-gray-800 transition">
        <i class="fa fa-home"></i> Painel
    </a>

    <a href="#" onclick="loadPage('../visualizar_clientes.php')" class="flex items-center gap-2 px-4 py-2 text-gray-300 hover:bg-gray-800 transition">
        <i class="fa fa-user-friends"></i> Clientes
    </a>
    <a href="#" onclick="loadPage('../visualizar_pedidos.php')" class="flex items-center gap-2 px-4 py-2 text-gray-300 hover:bg-gray-800 transition">
        <i class="fa fa-receipt"></i> Pedidos
    </a>
    <a href="#" onclick="loadPage('../visualizar_rastreio.php')" class="flex items-center gap-2 px-4 py-2 text-gray-300 hover:bg-gray-800 transition">
        <i class="fa-solid fa-truck-fast"></i> Rastreio
    </a>
    <a href="#" onclick="loadPage('../visualizar_configuracao.php')" class="flex items-center gap-2 px-4 py-2 text-gray-300 hover:bg-gray-800 transition">
        <i class="fa-solid fa-cog"></i> Configurações
    </a>

    <!-- 🔽 Dropdown com Tailwind -->
    <!-- Dropdown de Edição do Site -->
    <div class="relative group">
        <button class="w-full text-left flex items-center justify-between px-4 py-2 text-gray-300 hover:bg-gray-800 transition-all">
            <i class="fa-solid fa-edit"></i> Editar Site
            <i class="fa-solid fa-chevron-down group-hover:rotate-180 transition-transform"></i>
        </button>
        <div class="absolute left-0 w-full bg-gray-900 shadow-lg rounded-md hidden group-hover:block overflow-y-auto max-h-60">
            <a href="#" onclick="loadPage('../view/editar_index.php')" class="block px-4 py-2 text-gray-300 hover:bg-gray-800">Editar Página Inicial</a>
            <a href="#" onclick="loadPage('../view/editar_galeria.php')" class="block px-4 py-2 text-gray-300 hover:bg-gray-800">Editar Galeria</a>
            <a href="#" onclick="loadPage('../view/editar_blog.php')" class="block px-4 py-2 text-gray-300 hover:bg-gray-800">Editar Blog</a>
        </div>
    </div>

</div>

<style>
    /* Scrollbar estilizada para navegadores WebKit (Chrome, Edge, Safari) */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #1a1a2e;
        /* Fundo escuro indigo */
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: #3f3f7d;
        /* Tom de índigo mais escuro */
        border-radius: 10px;
        transition: background 0.3s ease;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #5757a3;
        /* Um tom um pouco mais claro ao passar o mouse */
    }
</style>

<!-- Script para abrir e fechar o dropdown -->
<script>
    function toggleDropdown() {
        let dropdown = document.getElementById("dropdownMenu");
        dropdown.classList.toggle("hidden");
    }

    function loadPage(page) {
        $.ajax({
            url: page,
            type: "GET",
            success: function(response) {
                $("#defaultContent").hide();
                $(".allContent-section").html(response).show();
            },
            error: function(xhr, status, error) {
                console.error("Erro ao carregar a página:", error);
                $(".allContent-section").html("<p class='text-danger'>Erro ao carregar a página.</p>");
            }
        });
    }

    function showDashboard() {
        $("#defaultContent").show();
        $(".allContent-section").empty().hide();
    }
</script>