document.addEventListener('DOMContentLoaded', function() {
    // Array para armazenar dados do carrinho localmente
    let cart_data = [];
    
    // Inicializar dados do carrinho
    function initCartData() {
        const cartItems = document.querySelectorAll('.cart-box');
        cart_data = Array.from(cartItems).map(item => {
            const id = item.dataset.id;
            const price = parseFloat(item.querySelector('.item-price').textContent.replace('R$ ', '').replace(',', '.'));
            const quantity = parseInt(item.querySelector('.number').textContent);
            return { id, price, quantity };
        });
    }
    
    // Inicializar os dados do carrinho quando a página carrega
    initCartData();
    
    // Função para adicionar ao carrinho
    async function addToCart(productId) {
        try {
            console.log("Adicionando produto ao carrinho:", productId);
            // Mostrar feedback imediato
            showToast('Adicionando produto...', 'info');
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'add');
            formData.append('quantity', 1);

            const response = await fetch('api/compras/update_cart.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Erro na resposta do servidor: ' + response.status);
            }

            const data = await response.json();
            console.log("Resposta do servidor:", data);

            if (data.success) {
                // Atualizar contador do carrinho
                updateCartCounter(1, true);
                
                if (data.item) {
                    // Adicionar novo item ao DOM sem recarregar a página
                    addCartItemToDOM(data.item);
                    
                    // Atualizar dados locais
                    cart_data.push({
                        id: data.item.product_id,
                        price: parseFloat(data.item.preco),
                        quantity: parseInt(data.item.quantity)
                    });
                    
                    updateTotalPrice();
                }
                
                showToast('Produto adicionado ao carrinho');
            } else {
                showToast(data.message || 'Erro ao adicionar produto', 'error');
            }
        } catch (error) {
            console.error('Erro ao adicionar ao carrinho:', error);
            showToast('Erro ao adicionar produto: ' + error.message, 'error');
        }
    }

    // Função para adicionar item ao DOM
    function addCartItemToDOM(item) {
        const cartList = document.querySelector('.compras-coluna:first-child .item-lista');
        const emptyMessage = cartList.querySelector('.empty-message');
        
        if (emptyMessage) {
            emptyMessage.remove();
        }
        
        // Criar novo elemento de item do carrinho
        const cartItemHTML = `
            <div class="item cart-box" data-id="${item.product_id}">
                <div class="item-image">
                    <img src="${item.imagem_path || ''}" alt="${item.nome || ''}">
                </div>
                <div class="item-info">
                    <h3>${item.nome}</h3>
                    <p class="item-price">R$ ${parseFloat(item.preco).toFixed(2).replace('.', ',')}</p>
                    <div class="quantidade-controle">
                        <button class="decrement"><i class="fas fa-minus"></i></button>
                        <span class="number">${item.quantity}</span>
                        <button class="increment"><i class="fas fa-plus"></i></button>
                    </div>
                    <p class="item-subtotal">Subtotal: R$ ${(parseFloat(item.preco) * parseInt(item.quantity)).toFixed(2).replace('.', ',')}</p>
                    <div class="item-actions">
                        <a href="javascript:void(0)" class="btn-action cart-remove"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
            </div>
        `;
        
        // Inserir antes do total
        const totalElement = cartList.querySelector('.cart-total');
        if (totalElement) {
            totalElement.insertAdjacentHTML('beforebegin', cartItemHTML);
        } else {
            // Caso não haja elemento de total, adicionar ao final e criar um
            cartList.insertAdjacentHTML('beforeend', cartItemHTML);
            cartList.insertAdjacentHTML('beforeend', `
                <div class="cart-total">
                    <p>Total: <strong class="total-price">R$ 0,00</strong></p>
                    <a href="checkout.php" class="btn-checkout">Finalizar Compra</a>
                </div>
            `);
        }
        
        // Não vamos adicionar event listeners individuais aqui, pois usaremos delegação de eventos
    }
    
    // Atualizar subtotal de um item do carrinho
    function updateCartItemSubtotal(cartItem, quantity) {
        const priceElement = cartItem.querySelector('.item-price');
        const subtotalElement = cartItem.querySelector('.item-subtotal');
        
        if (priceElement && subtotalElement) {
            const price = parseFloat(priceElement.textContent.replace('R$ ', '').replace(',', '.'));
            const subtotal = price * quantity;
            subtotalElement.textContent = `Subtotal: R$ ${subtotal.toFixed(2).replace('.', ',')}`;
            
            // Atualizar dados locais
            const productId = cartItem.dataset.id;
            const itemIndex = cart_data.findIndex(item => item.id === productId);
            if (itemIndex !== -1) {
                cart_data[itemIndex].quantity = quantity;
            }
            
            // Atualizar total geral
            updateTotalPrice();
        }
    }

    // Função para atualizar a quantidade de um item no carrinho
    async function updateCartItemQuantity(productId, quantity) {
        try {
            console.log("Atualizando quantidade do produto:", productId, "para", quantity);
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'update');
            formData.append('quantity', quantity);

            const response = await fetch('api/compras/update_cart.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Erro na resposta do servidor: ' + response.status);
            }

            const data = await response.json();
            console.log("Resposta ao atualizar quantidade:", data);

            if (data.success) {
                // Já atualizamos a UI para feedback imediato antes da chamada
                showToast('Quantidade atualizada');
            } else {
                // Reverter UI se houver erro
                const cartBox = document.querySelector(`.cart-box[data-id="${productId}"]`);
                const itemIndex = cart_data.findIndex(item => item.id === productId);
                
                if (cartBox && itemIndex !== -1) {
                    const oldQuantity = cart_data[itemIndex].quantity;
                    const quantitySpan = cartBox.querySelector('.number');
                    if (quantitySpan) {
                        quantitySpan.textContent = oldQuantity;
                    }
                    updateCartItemSubtotal(cartBox, oldQuantity);
                }
                
                showToast(data.message || 'Erro ao atualizar quantidade', 'error');
            }
        } catch (error) {
            console.error('Erro ao atualizar quantidade:', error);
            showToast('Erro ao atualizar quantidade: ' + error.message, 'error');
            
            // Reverter UI em caso de erro
            const cartBox = document.querySelector(`.cart-box[data-id="${productId}"]`);
            const itemIndex = cart_data.findIndex(item => item.id === productId);
            
            if (cartBox && itemIndex !== -1) {
                const oldQuantity = cart_data[itemIndex].quantity;
                const quantitySpan = cartBox.querySelector('.number');
                if (quantitySpan) {
                    quantitySpan.textContent = oldQuantity;
                }
                updateCartItemSubtotal(cartBox, oldQuantity);
            }
        }
    }

    // Função para remover um item do carrinho
    async function removeCartItem(productId) {
        try {
            console.log("Removendo produto do carrinho:", productId);
            
            const formData = new FormData();
            formData.append('product_id', productId); 
            formData.append('action', 'remove');

            const response = await fetch('api/compras/update_cart.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Erro na resposta do servidor: ' + response.status);
            }

            const data = await response.json();
            console.log("Resposta ao remover do carrinho:", data);

            if (data.success) {
                // Remover o elemento do DOM com animação
                const cartBox = document.querySelector(`.cart-box[data-id="${productId}"]`);
                if (cartBox) {
                    cartBox.style.height = cartBox.offsetHeight + 'px';
                    cartBox.style.opacity = '0';
                    cartBox.style.transform = 'translateX(20px)';
                    cartBox.style.overflow = 'hidden';
                    
                    setTimeout(() => {
                        cartBox.style.height = '0';
                        cartBox.style.padding = '0';
                        cartBox.style.margin = '0';
                        
                        setTimeout(() => {
                            cartBox.remove();
                            
                            // Atualizar dados locais
                            cart_data = cart_data.filter(item => item.id !== productId);
                            updateTotalPrice();
                            
                            // Atualizar contador
                            updateCartCounter(-1, true); 
                            
                            // Verificar se o carrinho ficou vazio
                            checkEmptyCart();
                        }, 200);
                    }, 300);
                }
                
                showToast('Produto removido do carrinho');
            } else {
                // Restaurar a visualização normal se houver erro
                const cartBox = document.querySelector(`.cart-box[data-id="${productId}"]`);
                if (cartBox) {
                    cartBox.style.opacity = '1';
                }
                
                showToast(data.message || 'Erro ao remover produto', 'error');
            }
        } catch (error) {
            console.error('Erro ao remover do carrinho:', error);
            showToast('Erro ao remover produto: ' + error.message, 'error');
            
            // Restaurar a visualização normal se houver erro
            const cartBox = document.querySelector(`.cart-box[data-id="${productId}"]`);
            if (cartBox) {
                cartBox.style.opacity = '1';
            }
        }
    }
    
    // Verificar se o carrinho está vazio e adicionar mensagem apropriada
    function checkEmptyCart() {
        const cartList = document.querySelector('.compras-coluna:first-child .item-lista');
        const cartItems = cartList.querySelectorAll('.cart-box');
        const totalElement = cartList.querySelector('.cart-total');
        
        if (cartItems.length === 0) {
            // Remover o elemento de total
            if (totalElement) {
                totalElement.remove();
            }
            
            // Adicionar mensagem de carrinho vazio
            const emptyHTML = `
                <div class="empty-message">
                    <p>Seu carrinho está vazio.</p>
                    <a href="compras.php" class="btn-shop">Ver Produtos</a>
                </div>
            `;
            
            cartList.innerHTML = emptyHTML;
        }
    }
    
    // Função para atualizar o preço total
    function updateTotalPrice() {
        const totalPriceElement = document.querySelector('.total-price');
        if (!totalPriceElement) return;

        let total = 0;
        cart_data.forEach(item => {
            total += parseFloat(item.price) * parseInt(item.quantity);
        });

        totalPriceElement.innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
    }
    
    // Atualizar contadores
    function updateCartCounter(change, isCart = true) {
        const selector = isCart ? '.compras-coluna:first-child .count-badge' : '.compras-coluna:nth-child(2) .count-badge';
        const counterElement = document.querySelector(selector);
        
        if (counterElement) {
            let currentCount = parseInt(counterElement.textContent);
            if (isNaN(currentCount)) currentCount = 0;
            
            // Se change é um número, adicionar/subtrair
            if (typeof change === 'number') {
                currentCount += change;
            } else {
                // Se change é um valor absoluto, usar esse valor
                currentCount = parseInt(change);
            }
            
            // Garantir que o contador nunca seja negativo
            if (currentCount < 0) currentCount = 0;
            
            counterElement.textContent = currentCount;
            
            // Adicionar animação de pulso
            counterElement.classList.add('pulse');
            setTimeout(() => {
                counterElement.classList.remove('pulse');
            }, 500);
        }
    }

    // Função para adicionar aos favoritos
    async function addToFavorites(productId) {
        try {
            console.log("Adicionando produto aos favoritos:", productId);
            // Mostrar feedback imediato
            showToast('Adicionando aos favoritos...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'add_to_favorites');
            formData.append('product_id', productId);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Erro na resposta do servidor: ' + response.status);
            }

            const data = await response.json();
            console.log("Resposta ao adicionar aos favoritos:", data);

            if (data.status === 'success') {
                if (data.item) {
                    // Adicionar item aos favoritos no DOM
                    addFavoriteItemToDOM(data.item);
                    
                    // Atualizar contador
                    updateCartCounter(1, false);
                }
                
                showToast('Produto adicionado aos favoritos');
            } else {
                showToast(data.message || 'Erro ao adicionar aos favoritos', 'error');
            }
        } catch (error) {
            console.error('Erro ao adicionar aos favoritos:', error);
            showToast('Erro ao adicionar aos favoritos: ' + error.message, 'error');
        }
    }
    
    // Função para adicionar item aos favoritos no DOM
    function addFavoriteItemToDOM(item) {
        const favoritesList = document.querySelector('.compras-coluna:nth-child(2) .item-lista');
        const emptyMessage = favoritesList.querySelector('.empty-message');
        
        if (emptyMessage) {
            emptyMessage.remove();
        }
        
        // Criar novo elemento de item favorito
        const favoriteItemHTML = `
            <div class="item saved-box" data-id="${item.id}">
                <div class="item-image">
                    <img src="${item.imagem_path || ''}" alt="${item.nome}">
                </div>
                <div class="item-info">
                    <h3>${item.nome}</h3>
                    <p class="item-price">R$ ${parseFloat(item.preco).toFixed(2).replace('.', ',')}</p>
                    <div class="item-actions">
                        <a href="javascript:void(0)" class="btn-action move-to-cart"><i class="fas fa-shopping-cart"></i></a>
                        <a href="javascript:void(0)" class="btn-action saved-remove"><i class="fas fa-heart-broken"></i></a>
                    </div>
                </div>
            </div>
        `;
        
        // Adicionar ao DOM
        favoritesList.insertAdjacentHTML('beforeend', favoriteItemHTML);
        
        // Não vamos adicionar event listeners individuais aqui, pois usaremos delegação de eventos
    }

    // Função para remover dos favoritos
    async function removeFavoriteItem(productId) {
        try {
            console.log("Removendo produto dos favoritos:", productId);
            
            const formData = new FormData();
            formData.append('action', 'remove_from_favorites');
            formData.append('product_id', productId);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Erro na resposta do servidor: ' + response.status);
            }

            const data = await response.json();
            console.log("Resposta ao remover dos favoritos:", data);

            if (data.status === 'success') {
                // Remover o elemento do DOM com animação
                const savedBox = document.querySelector(`.saved-box[data-id="${productId}"]`);
                if (savedBox) {
                    savedBox.style.height = savedBox.offsetHeight + 'px';
                    savedBox.style.opacity = '0';
                    savedBox.style.transform = 'translateX(20px)';
                    savedBox.style.overflow = 'hidden';
                    
                    setTimeout(() => {
                        savedBox.style.height = '0';
                        savedBox.style.padding = '0';
                        savedBox.style.margin = '0';
                        
                        setTimeout(() => {
                            savedBox.remove();
                            
                            // Atualizar contador
                            updateCartCounter(-1, false);
                            
                            // Verificar se não há mais favoritos
                            checkEmptyFavorites();
                        }, 200);
                    }, 300);
                }
                
                showToast('Produto removido dos favoritos');
            } else {
                // Restaurar visualização normal
                const savedBox = document.querySelector(`.saved-box[data-id="${productId}"]`);
                if (savedBox) {
                    savedBox.style.opacity = '1';
                }
                
                showToast(data.message || 'Erro ao remover dos favoritos', 'error');
            }
        } catch (error) {
            console.error('Erro ao remover dos favoritos:', error);
            showToast('Erro ao remover dos favoritos: ' + error.message, 'error');
            
            // Restaurar visualização normal
            const savedBox = document.querySelector(`.saved-box[data-id="${productId}"]`);
            if (savedBox) {
                savedBox.style.opacity = '1';
            }
        }
    }
    
    // Verificar se a lista de favoritos está vazia
    function checkEmptyFavorites() {
        const favoritesList = document.querySelector('.compras-coluna:nth-child(2) .item-lista');
        const favoriteItems = favoritesList.querySelectorAll('.saved-box');
        
        if (favoriteItems.length === 0) {
            // Adicionar mensagem de favoritos vazios
            const emptyHTML = `
                <div class="empty-message">
                    <p>Você não tem itens favoritos.</p>
                    <a href="compras.php" class="btn-shop">Ver Produtos</a>
                </div>
            `;
            
            favoritesList.innerHTML = emptyHTML;
        }
    }

    // Função para mover dos favoritos para o carrinho
    async function moveToCart(productId) {
        try {
            console.log("Movendo produto dos favoritos para o carrinho:", productId);
            // Feedback imediato
            showToast('Movendo para o carrinho...', 'info');
            
            // Primeiro adicionar ao carrinho
            const addFormData = new FormData();
            addFormData.append('product_id', productId);
            addFormData.append('action', 'add');
            addFormData.append('quantity', 1);

            const addResponse = await fetch('api/compras/update_cart.php', {
                method: 'POST',
                body: addFormData
            });

            if (!addResponse.ok) {
                throw new Error('Erro na resposta do servidor: ' + addResponse.status);
            }

            const addData = await addResponse.json();
            
            if (addData.success) {
                // Adicionar ao carrinho no DOM
                if (addData.item) {
                    addCartItemToDOM(addData.item);
                    
                    // Atualizar dados locais do carrinho
                    cart_data.push({
                        id: addData.item.product_id,
                        price: parseFloat(addData.item.preco),
                        quantity: parseInt(addData.item.quantity)
                    });
                    
                    // Atualizar contador do carrinho
                    updateCartCounter(1, true);
                    updateTotalPrice();
                }
                
                // Depois remover dos favoritos
                const removeFormData = new FormData();
                removeFormData.append('action', 'remove_from_favorites');
                removeFormData.append('product_id', productId);

                const removeResponse = await fetch('api/compras/cart_operations.php', {
                    method: 'POST',
                    body: removeFormData
                });

                if (!removeResponse.ok) {
                    throw new Error('Erro na resposta do servidor: ' + removeResponse.status);
                }

                const removeData = await removeResponse.json();
                
                if (removeData.status === 'success') {
                    // Remover dos favoritos no DOM
                    const savedBox = document.querySelector(`.saved-box[data-id="${productId}"]`);
                    if (savedBox) {
                        savedBox.style.height = savedBox.offsetHeight + 'px';
                        savedBox.style.opacity = '0';
                        savedBox.style.transform = 'translateX(20px)';
                        savedBox.style.overflow = 'hidden';
                        
                        setTimeout(() => {
                            savedBox.style.height = '0';
                            savedBox.style.padding = '0';
                            savedBox.style.margin = '0';
                            
                            setTimeout(() => {
                                savedBox.remove();
                                
                                // Atualizar contador de favoritos
                                updateCartCounter(-1, false);
                                
                                // Verificar se não há mais favoritos
                                checkEmptyFavorites();
                            }, 200);
                        }, 300);
                    }
                    
                    showToast('Produto movido para o carrinho');
                } else {
                    showToast('Produto adicionado ao carrinho, mas não foi removido dos favoritos', 'warning');
                }
            } else {
                // Restaurar visualização normal do item favorito
                const savedBox = document.querySelector(`.saved-box[data-id="${productId}"]`);
                if (savedBox) {
                    savedBox.style.opacity = '1';
                }
                
                showToast(addData.message || 'Erro ao mover para o carrinho', 'error');
            }
        } catch (error) {
            console.error('Erro ao mover para o carrinho:', error);
            showToast('Erro ao mover para o carrinho: ' + error.message, 'error');
            
            // Restaurar visualização normal do item favorito
            const savedBox = document.querySelector(`.saved-box[data-id="${productId}"]`);
            if (savedBox) {
                savedBox.style.opacity = '1';
            }
        }
    }

    // Função para exibir mensagens toast
    function showToast(message, type = 'success') {
        // Verificar se já existe um toast container
        let toastContainer = document.querySelector('.toast-container');
        
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
            
            // Adicionar estilos CSS se ainda não existirem
            if (!document.getElementById('toast-styles')) {
                const toastStyles = document.createElement('style');
                toastStyles.id = 'toast-styles';
                toastStyles.textContent = `
                    .toast-container {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 9999;
                        display: flex;
                        flex-direction: column;
                        gap: 10px;
                    }
                    .toast {
                        min-width: 250px;
                        padding: 15px;
                        border-radius: 4px;
                        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                        color: white;
                        font-weight: 500;
                        animation: toast-in 0.3s ease-in-out;
                    }
                    .toast-success {
                        background-color: #4CAF50;
                    }
                    .toast-error {
                        background-color: #F44336;
                    }
                    .toast-warning {
                        background-color: #FF9800;
                    }
                    .toast-info {
                        background-color: #2196F3;
                    }
                    .toast.fade-out {
                        animation: toast-out 0.3s ease-in-out forwards;
                    }
                    .pulse {
                        animation: pulse 0.5s ease-in-out;
                    }
                    @keyframes toast-in {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes toast-out {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                    @keyframes pulse {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.2); }
                        100% { transform: scale(1); }
                    }
                    /* Estilos de transição para itens */
                    .cart-box, .saved-box {
                        transition: all 0.3s ease;
                    }
                `;
                document.head.appendChild(toastStyles);
            }
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        toastContainer.appendChild(toast);
        
        // Auto-remover após 3 segundos
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => {
                toast.remove();
                
                // Remover o container se não houver mais toasts
                if (toastContainer.children.length === 0) {
                    toastContainer.remove();
                }
            }, 300);
        }, 3000);
    }

    // Usar apenas delegação de eventos para todos os botões
    document.addEventListener('click', function(event) {
        // Detectar cliques em botões de incremento
        if (event.target.closest('.increment')) {
            const cartItem = event.target.closest('.cart-box');
            if (cartItem) {
                const productId = cartItem.dataset.id;
                const quantitySpan = cartItem.querySelector('.number');
                const currentQuantity = parseInt(quantitySpan.textContent);
                const newQuantity = currentQuantity + 1;
                
                // Atualizar visual imediatamente
                quantitySpan.textContent = newQuantity;
                updateCartItemSubtotal(cartItem, newQuantity);
                
                // Enviar atualização para o servidor
                updateCartItemQuantity(productId, newQuantity);
            }
        }
        
        // Detectar cliques em botões de decremento
        else if (event.target.closest('.decrement')) {
            const cartItem = event.target.closest('.cart-box');
            if (cartItem) {
                const productId = cartItem.dataset.id;
                const quantitySpan = cartItem.querySelector('.number');
                const currentQuantity = parseInt(quantitySpan.textContent);
                
                if (currentQuantity > 1) {
                    const newQuantity = currentQuantity - 1;
                    
                    // Atualizar visual imediatamente
                    quantitySpan.textContent = newQuantity;
                    updateCartItemSubtotal(cartItem, newQuantity);
                    
                    // Enviar atualização para o servidor
                    updateCartItemQuantity(productId, newQuantity);
                }
            }
        }
        
        // Detectar cliques em botões de remoção do carrinho
        else if (event.target.closest('.cart-remove')) {
            const cartItem = event.target.closest('.cart-box');
            if (cartItem) {
                const productId = cartItem.dataset.id;
                
                // Animar remoção
                cartItem.style.opacity = '0.5';
                
                // Remover do servidor
                removeCartItem(productId);
            }
        }
        
        // Detectar cliques em botões de remoção dos favoritos
        else if (event.target.closest('.saved-remove')) {
            const savedItem = event.target.closest('.saved-box');
            if (savedItem) {
                const productId = savedItem.dataset.id;
                
                // Animar remoção
                savedItem.style.opacity = '0.5';
                
                // Remover dos favoritos
                removeFavoriteItem(productId);
            }
        }
        
        // Detectar cliques em botões de mover para o carrinho
        else if (event.target.closest('.move-to-cart')) {
            const savedItem = event.target.closest('.saved-box');
            if (savedItem) {
                const productId = savedItem.dataset.id;
                
                // Animar
                savedItem.style.opacity = '0.5';
                
                // Mover para o carrinho
                moveToCart(productId);
            }
        }
    });

    // Expor funções necessárias globalmente
    window.addToCart = addToCart;
    window.updateCartItemQuantity = updateCartItemQuantity;
    window.removeCartItem = removeCartItem;
    window.addToFavorites = addToFavorites;
    window.removeFavoriteItem = removeFavoriteItem;
    window.moveToCart = moveToCart;
});

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Loading screen fade out
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
        setTimeout(() => {
            loadingScreen.style.opacity = '0';
            setTimeout(() => {
                loadingScreen.style.display = 'none';
            }, 500);
        }, 800);
    }

    // Profile section switching
    const buttons = document.querySelectorAll('.button-group .button');
    const sections = document.querySelectorAll('.profile-section');

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Get the section to show
            const sectionToShow = this.getAttribute('data-section');
            
            // Fade out all sections first
            sections.forEach(section => {
                section.style.opacity = '0';
                setTimeout(() => {
                    section.classList.remove('active');
                    // After fade out, show the selected section
                    if (section.id === sectionToShow) {
                        section.classList.add('active');
                        // Fade in the selected section
                        setTimeout(() => {
                            section.style.opacity = '1';
                        }, 50);
                    }
                }, 300);
            });
        });
    });

    // Fix image paths for cart and favorite items
    fixProductImages();

});

/**
 * Fix product image paths in the cart and favorites sections
 */
function fixProductImages() {
    // Get all product items in cart and favorites
    const cartItems = document.querySelectorAll('.cart-box');
    const favoriteItems = document.querySelectorAll('.saved-box');
    
    // Process all items to fix image paths
    processItemImages(cartItems);
    processItemImages(favoriteItems);
}

/**
 * Process image paths for a collection of product items
 * @param {NodeList} items - Collection of product items to process
 */
function processItemImages(items) {
    items.forEach(item => {
        const productId = item.getAttribute('data-id');
        const imgElement = item.querySelector('.item-image img');
        
        if (imgElement && productId) {
            // Fix broken image path by fetching the correct image
            fetchProductImage(productId).then(imagePath => {
                if (imagePath) {
                    imgElement.src = imagePath;
                } else {
                    // Use placeholder if image not found
                    imgElement.src = '../adminView/uploads/produtos/placeholder.jpeg';
                }
            });
        }
    });
}

/**
 * Fetch the correct image path for a product
 * @param {string} productId - The product ID to fetch the image for
 * @returns {Promise<string>} - Promise resolving to the image path
 */
function fetchProductImage(productId) {
    return new Promise((resolve) => {
        // Create an AJAX request to get the product image
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'includes/profile/get_product_image.php?id=' + productId, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                resolve(xhr.responseText);
            } else {
                resolve(null);
            }
        };
        xhr.onerror = function() {
            resolve(null);
        };
        xhr.send();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Create notification system
    const notificationSystem = document.createElement('div');
    notificationSystem.className = 'notification-system';
    document.body.appendChild(notificationSystem);

    // Function to show notifications
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span>${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;
        
        // Add to notification system
        notificationSystem.appendChild(notification);
        
        // Fade in
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Auto remove after 5 seconds
        const timeout = setTimeout(() => {
            removeNotification(notification);
        }, 5000);
        
        // Close button functionality
        notification.querySelector('.notification-close').addEventListener('click', () => {
            clearTimeout(timeout);
            removeNotification(notification);
        });
    }
    
    function removeNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
    
    // Handle password update form
    const senhaForm = document.querySelector('#senha form');
    if (senhaForm) {
        senhaForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const senhaAtual = document.getElementById('senha_atual').value;
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            // Basic validation
            if (novaSenha !== confirmarSenha) {
                showNotification('As senhas não coincidem.', 'error');
                return;
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('senha_atual', senhaAtual);
            formData.append('nova_senha', novaSenha);
            formData.append('confirmar_senha', confirmarSenha);
            
            // Send AJAX request
            fetch('includes/profile/atualizar_senha.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('sucesso')) {
                    showNotification(data, 'success');
                    // Clear form
                    senhaForm.reset();
                } else {
                    showNotification(data, 'error');
                }
            })
            .catch(error => {
                showNotification('Erro ao processar a solicitação.', 'error');
                console.error('Error:', error);
            });
        });
    }
    
    // Handle user data update form
    const dadosForm = document.querySelector('#meus-dados form');
    if (dadosForm) {
        dadosForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Create form data from all form inputs
            const formData = new FormData(dadosForm);
            
            // Send AJAX request
            fetch('includes/profile/atualizar_dados.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('sucesso')) {
                    showNotification(data, 'success');
                    
                    // Update displayed username in the header if name was changed
                    const newName = document.getElementById('nome').value;
                    const userDisplayNames = document.querySelectorAll('#username-display');
                    userDisplayNames.forEach(element => {
                        const displayName = newName.length > 16 ? newName.substring(0, 16) + "..." : newName;
                        element.textContent = displayName;
                    });
                    
                    // Update email display in profile section
                    const newEmail = document.getElementById('email').value;
                    const profileEmail = document.querySelector('.profile-email');
                    if (profileEmail) {
                        profileEmail.textContent = newEmail;
                    }
                    
                    // Update header user name
                    const headerName = document.querySelector('.profile-info h1');
                    if (headerName && newName) {
                        headerName.textContent = newName;
                    }
                } else {
                    showNotification(data, 'error');
                }
            })
            .catch(error => {
                showNotification('Erro ao processar a solicitação.', 'error');
                console.error('Error:', error);
            });
        });
    }
});