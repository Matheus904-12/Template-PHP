// ajax_cart_management.js

document.addEventListener('DOMContentLoaded', function() {
    // Este arquivo complementa compras.js com funções auxiliares para gerenciamento AJAX

    // Função para gerenciar todas as solicitações AJAX
    async function ajaxRequest(url, method, data) {
        try {
            let options = {
                method: method,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            };

            if (method === 'POST') {
                if (data instanceof FormData) {
                    options.body = data;
                } else if (typeof data === 'object') {
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify(data);
                }
            }

            const response = await fetch(url, options);

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Erro na requisição AJAX:', error);
            throw error;
        }
    }

    // Funções extras para cart_operations
    window.refreshCart = async function() {
        if (typeof loadCartItems === 'function') {
            await loadCartItems();
        } else {
            console.warn('Função loadCartItems não encontrada');
            
            try {
                const response = await ajaxRequest('api/compras/cart_operations.php', 'POST', {
                    action: 'get_cart'
                });
                
                if (response.status === 'success') {
                    // Atualiza contador do carrinho, se existir
                    const cartCounters = document.querySelectorAll('.cart-item-count');
                    if (cartCounters.length > 0) {
                        const itemCount = response.data.items.reduce((acc, item) => acc + parseInt(item.quantity), 0);
                        cartCounters.forEach(counter => {
                            counter.textContent = itemCount;
                        });
                    }
                }
            } catch (error) {
                console.error('Erro ao atualizar carrinho:', error);
            }
        }
    };

    window.refreshFavorites = async function() {
        if (typeof loadFavoriteItems === 'function') {
            await loadFavoriteItems();
        } else {
            console.warn('Função loadFavoriteItems não encontrada');
            
            try {
                const response = await ajaxRequest('api/compras/cart_operations.php', 'POST', {
                    action: 'get_favorites'
                });
                
                if (response.status === 'success') {
                    // Atualiza contador de favoritos, se existir
                    const favCounter = document.querySelector('.total-items-count');
                    if (favCounter) {
                        favCounter.textContent = response.data.items.length;
                    }
                }
            } catch (error) {
                console.error('Erro ao atualizar favoritos:', error);
            }
        }
    };

    // Adiciona handlers de produtos na pagina de produtos
    function initProductHandlers() {
        // Botões de adicionar ao carrinho em páginas de produtos
        const addToCartButtons = document.querySelectorAll('.add-cart:not(.initialized)');
        addToCartButtons.forEach(button => {
            button.classList.add('initialized');
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.closest('.product-box')?.dataset.id;
                if (productId && window.addToCart) {
                    window.addToCart(productId);
                }
            });
        });

        // Botões de adicionar aos favoritos
        const addToFavButtons = document.querySelectorAll('.save-item:not(.initialized)');
        addToFavButtons.forEach(button => {
            button.classList.add('initialized');
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.closest('.product-box')?.dataset.id;
                if (productId && window.addToFavorites) {
                    window.addToFavorites(productId);
                }
            });
        });
    }

    // Inicializa handlers quando a página carrega
    initProductHandlers();

    // Também inicializa após carregamento AJAX (se aplicável)
    document.addEventListener('ajaxContentLoaded', function() {
        initProductHandlers();
    });

    // Expõe a função ajaxRequest globalmente
    window.cartAjaxRequest = ajaxRequest;
});