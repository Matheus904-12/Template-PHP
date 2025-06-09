// Global variables
let cart_data = [];
let favorites_data = [];
let userId = null;
let isUserLoggedIn = false;

// Expose favorites_data for modal.js
window.favorites_data = favorites_data;

document.addEventListener('DOMContentLoaded', function () {
    // DOM elements
    const cartIcon = document.getElementById('cart-icon');
    const cart = document.querySelector('.cart');
    const cartClose = document.getElementById('cart-close');
    const savedItemsIcon = document.querySelector('#salvar, .salvar');
    const savedItems = document.querySelector('.saved-items');
    const savedItemsClose = document.getElementById('saved-items-close');
    const cartContent = document.querySelector('.cart-content');
    const savedItemsContent = document.querySelector('.saved-items-content');
    const totalPrice = document.querySelector('.total-price');
    const cartItemCount = document.querySelectorAll('.cart-item-count');
    const savedItemsCount = document.querySelectorAll('.favorites-counter, .total-items-count');
    const moveAllToCartBtn = document.querySelector('.btn-move-to-cart');
    const checkoutBtn = document.querySelector('.btn-buy');
    const floatingCart = document.getElementById('floating-cart');
    const floatingFavorites = document.getElementById('floating-favorites');
    const floatingProfile = document.getElementById('floating-profile');
    const floatingSearch = document.getElementById('floating-search');

    // Check login status when page loads
    checkLoginStatus().then(status => {
        console.log('Login status verificado:', status);
        if (status) {
            loadCartItems();
            loadFavoriteItems();
        } else {
            updateCartUI();
            updateFavoritesUI();
        }
    });

    // Event listeners for cart and saved items UI
    if (cartIcon) {
        cartIcon.addEventListener('click', () => {
            cart.classList.add('active');
        });
    }

    if (cartClose) {
        cartClose.addEventListener('click', () => {
            cart.classList.remove('active');
        });
    }

    if (savedItemsIcon) {
        savedItemsIcon.addEventListener('click', (e) => {
            e.preventDefault();
            savedItems.classList.add('active');
        });
    }

    if (savedItemsClose) {
        savedItemsClose.addEventListener('click', () => {
            savedItems.classList.remove('active');
        });
    }

    if (moveAllToCartBtn) {
        moveAllToCartBtn.addEventListener('click', moveAllToCart);
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', proceedToCheckout);
    }

    // Event listeners for floating buttons
    if (floatingCart) {
        floatingCart.addEventListener('click', () => {
            if (cart) cart.classList.add('active');
        });
    }

    if (floatingFavorites) {
        floatingFavorites.addEventListener('click', () => {
            if (savedItems) savedItems.classList.add('active');
        });
    }

    if (floatingProfile) {
        floatingProfile.addEventListener('click', () => {
            window.location.href = 'profile.php';
        });
    }

    if (floatingSearch) {
        floatingSearch.addEventListener('click', () => {
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.scrollIntoView({ behavior: 'smooth' });
                setTimeout(() => searchInput.focus(), 500);
            }
        });
    }

    // Set up event delegation for product page buttons
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('add-cart')) {
            const productBox = e.target.closest('.product-box');
            if (productBox) {
                const productId = productBox.dataset.id;
                addToCart(productId);
            }
        }

        if (e.target.classList.contains('save-item')) {
            const productBox = e.target.closest('.product-box');
            if (productBox) {
                const productId = productBox.dataset.id;
                const isInFavorites = favorites_data.some(item => item.id === productId);
                if (isInFavorites) {
                    removeFavoriteItem(productId);
                } else {
                    addToFavorites(productId);
                }
            }
        }

        if (e.target.classList.contains('increment')) {
            const cartBox = e.target.closest('.cart-box');
            if (cartBox) {
                const productId = cartBox.dataset.id;
                const quantitySpan = cartBox.querySelector('.number');
                const currentQuantity = parseInt(quantitySpan.textContent);
                updateCartItemQuantity(productId, currentQuantity + 1);
            }
        }

        if (e.target.classList.contains('decrement')) {
            const cartBox = e.target.closest('.cart-box');
            if (cartBox) {
                const productId = cartBox.dataset.id;
                const quantitySpan = cartBox.querySelector('.number');
                const currentQuantity = parseInt(quantitySpan.textContent);
                if (currentQuantity > 1) {
                    updateCartItemQuantity(productId, currentQuantity - 1);
                } else {
                    showToast('Quantidade mínima atingida');
                }
            }
        }

        if (e.target.classList.contains('cart-remove')) {
            const cartBox = e.target.closest('.cart-box');
            if (cartBox) {
                const productId = cartBox.dataset.id;
                removeCartItem(productId);
            }
        }

        if (e.target.classList.contains('move-to-cart')) {
            const savedBox = e.target.closest('.saved-box');
            if (savedBox) {
                const productId = savedBox.dataset.id;
                moveToCart(productId);
            }
        }

        if (e.target.classList.contains('saved-remove')) {
            const savedBox = e.target.closest('.saved-box');
            if (savedBox) {
                const productId = savedBox.dataset.id;
                removeFavoriteItem(productId);
            }
        }
    });

    // Check login status
    async function checkLoginStatus() {
        try {
            console.log('Verificando status de login...');
            const response = await fetch('api/compras/cart_operations.php?action=check_login', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta do status de login:', data);

            isUserLoggedIn = data.data && data.data.isLoggedIn === true;
            userId = data.data && data.data.isLoggedIn ? data.data.userId : null;

            console.log('Status de login atualizado:', { isUserLoggedIn, userId });
            updateUIForLoginStatus();
            return isUserLoggedIn;
        } catch (error) {
            console.error('Erro ao verificar status de login:', error);
            isUserLoggedIn = false;
            userId = null;
            updateUIForLoginStatus();
            return false;
        }
    }

    // Update UI based on login status
    function updateUIForLoginStatus() {
        const loginElements = document.querySelectorAll('.login-required');
        const guestElements = document.querySelectorAll('.guest-only');

        if (isUserLoggedIn) {
            loginElements.forEach(el => el.style.display = 'block');
            guestElements.forEach(el => el.style.display = 'none');
        } else {
            loginElements.forEach(el => el.style.display = 'none');
            guestElements.forEach(el => el.style.display = 'block');
        }
    }

    // Show login modal
    function showLoginModal(context) {
        const modalOverlay = document.createElement('div');
        modalOverlay.classList.add('login-modal-overlay');

        const modal = document.createElement('div');
        modal.classList.add('login-modal');

        const contextMessages = {
            'carrinho': 'adicionar produtos ao seu carrinho',
            'favoritos': 'salvar produtos nos favoritos',
            'checkout': 'finalizar sua compra'
        };

        modal.innerHTML = `
            <div class="login-modal-content">
                <h2>Faça Login</h2>
                <p>Você precisa estar logado para ${contextMessages[context] || 'continuar'}.</p>
                <div class="login-modal-actions">
                    <button class="btn-login-now">Fazer Login</button>
                    <button class="btn-cancel">Cancelar</button>
                </div>
            </div>
        `;

        modalOverlay.appendChild(modal);
        document.body.appendChild(modalOverlay);

        const loginBtn = modal.querySelector('.btn-login-now');
        const cancelBtn = modal.querySelector('.btn-cancel');

        loginBtn.addEventListener('click', () => {
            window.location.href = './login_site.php';
        });

        cancelBtn.addEventListener('click', () => {
            document.body.removeChild(modalOverlay);
        });

        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                document.body.removeChild(modalOverlay);
            }
        });
    }

    // Show toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.classList.add('toast', `toast-${type}`);
        toast.textContent = message;

        document.body.appendChild(toast);

        const existingToasts = document.querySelectorAll('.toast');
        existingToasts.forEach(t => {
            if (t !== toast) {
                t.remove();
            }
        });

        setTimeout(() => {
            toast.classList.add('toast-visible');
        }, 100);

        setTimeout(() => {
            toast.classList.remove('toast-visible');
            toast.classList.add('toast-exit');
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    // Load cart items
    async function loadCartItems() {
        if (!isUserLoggedIn) {
            console.log('Usuário não está logado, não carregando carrinho');
            cart_data = [];
            updateCartUI();
            return;
        }

        try {
            console.log('Solicitando itens do carrinho para o usuário:', userId);
            const formData = new FormData();
            formData.append('action', 'get_cart');

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta dos itens do carrinho:', data);

            if (data.status === 'success') {
                cart_data = data.data.items || [];
                updateCartUI();
            } else {
                console.error('Erro ao carregar itens do carrinho:', data.message);
                showToast('Erro ao carregar o carrinho: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao carregar itens do carrinho:', error);
            showToast('Erro ao conectar com o servidor', 'error');
        }
    }

    // Load favorite items
    async function loadFavoriteItems() {
        if (!isUserLoggedIn) {
            console.log('Usuário não está logado, não carregando favoritos');
            favorites_data = [];
            window.favorites_data = favorites_data;
            updateFavoritesUI();
            updateHeartIcons();
            return;
        }

        try {
            console.log('Solicitando itens favoritos para o usuário:', userId);
            const formData = new FormData();
            formData.append('action', 'get_favorites');

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta dos itens favoritos:', data);

            if (data.status === 'success') {
                favorites_data = data.data.items || [];
                window.favorites_data = favorites_data;
                updateFavoritesUI();
                updateHeartIcons();
            } else {
                console.error('Erro ao carregar favoritos:', data.message);
                showToast('Erro ao carregar favoritos: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao carregar favoritos:', error);
            showToast('Erro ao conectar com o servidor', 'error');
        }
    }

    // Update cart UI
    function updateCartUI() {
        if (!cartContent) return;

        cartContent.innerHTML = '';
        let total = 0;

        if (cart_data.length === 0) {
            cartContent.innerHTML = '<div class="empty-cart">Seu carrinho está vazio</div>';
            if (totalPrice) totalPrice.innerText = 'R$ 0,00';
            updateCartCounter(0);
            return;
        }

        cart_data.forEach(item => {
            const cartItem = document.createElement('div');
            cartItem.classList.add('cart-box');
            cartItem.dataset.id = item.id;

            cartItem.innerHTML = `
                <img src="${item.image}" alt="${item.name}" class="cart-img">
                <div class="detail-box">
                    <div class="cart-product-title">${item.name}</div>
                    <div class="cart-price">R$ ${parseFloat(item.price).toFixed(2).replace('.', ',')}</div>
                    <div class="cart-quantity">
                        <button class="decrement">-</button>
                        <span class="number">${item.quantity}</span>
                        <button class="increment">+</button>
                    </div>
                </div>
                <i class="ri-delete-bin-line cart-remove"></i>
            `;

            cartContent.appendChild(cartItem);
            total += parseFloat(item.price) * parseInt(item.quantity);
        });

        if (totalPrice) {
            totalPrice.innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
        }

        const itemCount = cart_data.reduce((acc, item) => acc + parseInt(item.quantity), 0);
        updateCartCounter(itemCount);
    }

    // Update favorites UI
    function updateFavoritesUI() {
        if (!savedItemsContent) return;

        savedItemsContent.innerHTML = '';

        if (favorites_data.length === 0) {
            savedItemsContent.innerHTML = '<div class="empty-saved">Você não tem produtos salvos</div>';
            updateFavoritesCounter(0);
            if (moveAllToCartBtn) moveAllToCartBtn.style.display = 'none';
            return;
        }

        favorites_data.forEach(item => {
            const savedItem = document.createElement('div');
            savedItem.classList.add('saved-box');
            savedItem.dataset.id = item.id;

            savedItem.innerHTML = `
                <img src="${item.image}" alt="${item.name}" class="saved-item-img">
                <div class="detail-box">
                    <div class="saved-item-title">${item.name}</div>
                    <div class="saved-item-price">R$ ${parseFloat(item.price).toFixed(2).replace('.', ',')}</div>
                </div>
                <i class="ri-shopping-cart-line move-to-cart"></i>
                <i class="ri-delete-bin-line saved-remove"></i>
            `;

            savedItemsContent.appendChild(savedItem);
        });

        updateFavoritesCounter(favorites_data.length);
        if (moveAllToCartBtn) {
            moveAllToCartBtn.style.display = favorites_data.length > 0 ? 'flex' : 'none';
        }
    }

    // Update heart icons
    function updateHeartIcons() {
        const saveButtons = document.querySelectorAll('.save-item');
        const modalFavoriteButton = document.querySelector('.modal-add-to-favorites');
        saveButtons.forEach(button => {
            const productBox = button.closest('.product-box');
            if (productBox) {
                const productId = productBox.dataset.id;
                const isInFavorites = favorites_data.some(item => item.id === productId);

                button.classList.toggle('ri-heart-fill', isInFavorites);
                button.classList.toggle('ri-heart-line', !isInFavorites);
                button.style.color = isInFavorites ? '#e74c3c' : '';
            }
        });

        if (modalFavoriteButton) {
            const productId = modalFavoriteButton.dataset.id;
            if (productId) {
                const isInFavorites = favorites_data.some(item => item.id === productId);
                modalFavoriteButton.classList.toggle('active', isInFavorites);
                const heartIcon = modalFavoriteButton.querySelector('i');
                heartIcon.classList.toggle('ri-heart-fill', isInFavorites);
                heartIcon.classList.toggle('ri-heart-line', !isInFavorites);
                heartIcon.style.color = isInFavorites ? '#e74c3c' : '';
            }
        }
    }

    // Update cart counter
    function updateCartCounter(count) {
        cartItemCount.forEach(el => {
            if (el) el.innerText = count;
        });
    }

    // Update favorites counter
    function updateFavoritesCounter(count) {
        savedItemsCount.forEach(el => {
            if (el) el.innerText = count;
        });
    }

    // Add to cart
    async function addToCart(productId) {
        if (!isUserLoggedIn) {
            showLoginModal('carrinho');
            return;
        }

        try {
            // Atualizar contador localmente primeiro
            const productBox = document.querySelector(`.product-box[data-id="${productId}"]`);
            const existingItem = cart_data.find(item => item.id === productId);
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart_data.push({
                    id: productId,
                    name: productBox.dataset.name,
                    price: productBox.dataset.price,
                    image: productBox.dataset.image,
                    quantity: 1
                });
            }
            updateCartUI();

            console.log('Adicionando produto ao carrinho:', productId);
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', 1);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta ao adicionar ao carrinho:', data);

            if (data.status === 'success') {
                showToast('Produto adicionado ao carrinho!');
                await loadCartItems(); // Recarregar para sincronizar com o servidor
            } else {
                showToast(data.message || 'Erro ao adicionar ao carrinho', 'error');
                // Reverter atualização local em caso de erro
                if (existingItem) {
                    existingItem.quantity -= 1;
                } else {
                    cart_data = cart_data.filter(item => item.id !== productId);
                }
                updateCartUI();
            }
        } catch (error) {
            console.error('Erro ao adicionar ao carrinho:', error);
            showToast('Erro ao conectar com o servidor', 'error');
            // Reverter atualização local
            const existingItem = cart_data.find(item => item.id === productId);
            if (existingItem) {
                existingItem.quantity -= 1;
            } else {
                cart_data = cart_data.filter(item => item.id !== productId);
            }
            updateCartUI();
        }
    }

    // Make addToCart global
    window.addToCart = addToCart;

    // Add to favorites
    async function addToFavorites(productId) {
        if (!isUserLoggedIn) {
            showLoginModal('favoritos');
            return;
        }

        try {
            // Atualizar contador localmente primeiro
            const productBox = document.querySelector(`.product-box[data-id="${productId}"]`);
            if (!favorites_data.some(item => item.id === productId)) {
                favorites_data.push({
                    id: productId,
                    name: productBox.dataset.name,
                    price: productBox.dataset.price,
                    image: productBox.dataset.image,
                    description: productBox.dataset.description
                });
                window.favorites_data = favorites_data;
                updateFavoritesUI();
                updateHeartIcons();
            }

            console.log('Adicionando produto aos favoritos:', productId);
            const formData = new FormData();
            formData.append('action', 'add_to_favorites');
            formData.append('product_id', productId);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta ao adicionar aos favoritos:', data);

            if (data.status === 'success') {
                showToast('Produto adicionado aos favoritos!');
                await loadFavoriteItems(); // Recarregar para sincronizar com o servidor
                updateHeartIcons(); // Garantir atualização dos ícones
            } else {
                showToast(data.message || 'Erro ao adicionar aos favoritos', 'error');
                // Reverter atualização local
                favorites_data = favorites_data.filter(item => item.id !== productId);
                window.favorites_data = favorites_data;
                updateFavoritesUI();
                updateHeartIcons();
            }
        } catch (error) {
            console.error('Erro ao adicionar aos favoritos:', error);
            showToast('Erro ao conectar com o servidor', 'error');
            // Reverter atualização local
            favorites_data = favorites_data.filter(item => item.id !== productId);
            window.favorites_data = favorites_data;
            updateFavoritesUI();
            updateHeartIcons();
        }
    }

    // Make addToFavorites global
    window.addToFavorites = addToFavorites;

    // Remove cart item
    async function removeCartItem(productId) {
        if (!isUserLoggedIn) {
            showLoginModal('carrinho');
            return;
        }

        try {
            // Atualizar contador localmente primeiro
            cart_data = cart_data.filter(item => item.id !== productId);
            updateCartUI();

            console.log('Removendo produto do carrinho:', productId);
            const formData = new FormData();
            formData.append('action', 'remove_from_cart');
            formData.append('product_id', productId);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta ao remover do carrinho:', data);

            if (data.status === 'success') {
                showToast('Produto removido do carrinho');
                await loadCartItems();
            } else {
                showToast(data.message || 'Erro ao remover do carrinho', 'error');
                // Reverter atualização local
                await loadCartItems();
            }
        } catch (error) {
            console.error('Erro ao remover do carrinho:', error);
            showToast('Erro ao conectar com o servidor', 'error');
            // Reverter atualização local
            await loadCartItems();
        }
    }

    // Update cart item quantity
    async function updateCartItemQuantity(productId, quantity) {
        if (!isUserLoggedIn) {
            showLoginModal('carrinho');
            return;
        }

        try {
            // Atualizar contador localmente primeiro
            const item = cart_data.find(item => item.id === productId);
            if (item) {
                item.quantity = quantity;
                updateCartUI();
            }

            console.log('Atualizando quantidade do produto:', productId, 'para', quantity);
            const formData = new FormData();
            formData.append('action', 'update_cart_quantity');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta ao atualizar quantidade:', data);

            if (data.status === 'success') {
                showToast('Quantidade atualizada');
                await loadCartItems();
            } else {
                showToast(data.message || 'Erro ao atualizar quantidade', 'error');
                // Reverter atualização local
                await loadCartItems();
            }
        } catch (error) {
            console.error('Erro ao atualizar quantidade:', error);
            showToast('Erro ao conectar com o servidor', 'error');
            // Reverter atualização local
            await loadCartItems();
        }
    }

    // Remove favorite item
    async function removeFavoriteItem(productId) {
        if (!isUserLoggedIn) {
            showLoginModal('favoritos');
            return;
        }

        try {
            // Atualizar contador localmente primeiro
            favorites_data = favorites_data.filter(item => item.id !== productId);
            window.favorites_data = favorites_data;
            updateFavoritesUI();
            updateHeartIcons();

            console.log('Removendo produto dos favoritos:', productId);
            const formData = new FormData();
            formData.append('action', 'remove_from_favorites');
            formData.append('product_id', productId);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta ao remover dos favoritos:', data);

            if (data.status === 'success') {
                showToast('Produto removido dos favoritos');
                await loadFavoriteItems();
                updateHeartIcons(); // Garantir atualização dos ícones
            } else {
                showToast(data.message || 'Erro ao remover dos favoritos', 'error');
                // Reverter atualização local
                await loadFavoriteItems();
                updateHeartIcons();
            }
        } catch (error) {
            console.error('Erro ao remover dos favoritos:', error);
            showToast('Erro ao conectar com o servidor', 'error');
            // Reverter atualização local
            await loadFavoriteItems();
            updateHeartIcons();
        }
    }

    // Make removeFavoriteItem global
    window.removeFavoriteItem = removeFavoriteItem;

    // Move item from favorites to cart
    async function moveToCart(productId) {
        if (!isUserLoggedIn) {
            showLoginModal('carrinho');
            return;
        }

        try {
            // Atualizar contadores localmente primeiro
            const favoriteItem = favorites_data.find(item => item.id === productId);
            if (favoriteItem) {
                const existingCartItem = cart_data.find(item => item.id === productId);
                if (existingCartItem) {
                    existingCartItem.quantity += 1;
                } else {
                    cart_data.push({
                        id: productId,
                        name: favoriteItem.name,
                        price: favoriteItem.price,
                        image: favoriteItem.image,
                        quantity: 1
                    });
                }
                favorites_data = favorites_data.filter(item => item.id !== productId);
                window.favorites_data = favorites_data;
                updateCartUI();
                updateFavoritesUI();
                updateHeartIcons();
            }

            console.log('Movendo produto dos favoritos para o carrinho:', productId);
            const formData = new FormData();
            formData.append('action', 'move_to_cart');
            formData.append('product_id', productId);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta ao mover para o carrinho:', data);

            if (data.status === 'success') {
                showToast('Produto movido para o carrinho');
                await loadCartItems();
                await loadFavoriteItems();
                updateHeartIcons();
            } else {
                showToast(data.message || 'Erro ao mover para o carrinho', 'error');
                // Reverter atualização local
                await loadCartItems();
                await loadFavoriteItems();
                updateHeartIcons();
            }
        } catch (error) {
            console.error('Erro ao mover para o carrinho:', error);
            showToast('Erro ao conectar com o servidor', 'error');
            // Reverter atualização local
            await loadCartItems();
            await loadFavoriteItems();
            updateHeartIcons();
        }
    }

    // Move all items from favorites to cart
    async function moveAllToCart() {
        if (!isUserLoggedIn) {
            showLoginModal('carrinho');
            return;
        }

        try {
            // Atualizar contadores localmente primeiro
            favorites_data.forEach(favorite => {
                const existingCartItem = cart_data.find(item => item.id === favorite.id);
                if (existingCartItem) {
                    existingCartItem.quantity += 1;
                } else {
                    cart_data.push({
                        id: favorite.id,
                        name: favorite.name,
                        price: favorite.price,
                        image: favorite.image,
                        quantity: 1
                    });
                }
            });
            favorites_data = [];
            window.favorites_data = favorites_data;
            updateCartUI();
            updateFavoritesUI();
            updateHeartIcons();

            console.log('Movendo todos os produtos dos favoritos para o carrinho');
            const formData = new FormData();
            formData.append('action', 'move_all_to_cart');

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta ao mover todos para o carrinho:', data);

            if (data.status === 'success') {
                showToast(`${data.data.count || 'Todos os'} produtos movidos para o carrinho`);
                await loadCartItems();
                await loadFavoriteItems();
                updateHeartIcons();
            } else {
                showToast(data.message || 'Erro ao mover para o carrinho', 'error');
                // Reverter atualização local
                await loadCartItems();
                await loadFavoriteItems();
                updateHeartIcons();
            }
        } catch (error) {
            console.error('Erro ao mover todos para o carrinho:', error);
            showToast('Erro ao conectar com o servidor', 'error');
            // Reverter atualização local
            await loadCartItems();
            await loadFavoriteItems();
            updateHeartIcons();
        }
    }

    // Proceed to checkout
    async function proceedToCheckout() {
        if (!isUserLoggedIn) {
            showLoginModal('checkout');
            return;
        }

        if (cart_data.length === 0) {
            showToast('Seu carrinho está vazio', 'error');
            return;
        }

        window.location.href = './checkout.php';
    }

    // Buy now
    async function buyNow(productId) {
        if (!isUserLoggedIn) {
            showLoginModal('checkout');
            return;
        }

        try {
            // Adicionar ao carrinho
            const productBox = document.querySelector(`.product-box[data-id="${productId}"]`);
            const existingItem = cart_data.find(item => item.id === productId);
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart_data.push({
                    id: productId,
                    name: productBox.dataset.name,
                    price: productBox.dataset.price,
                    image: productBox.dataset.image,
                    quantity: 1
                });
            }
            updateCartUI();

            console.log('Adicionando produto ao carrinho para compra imediata:', productId);
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', 1);

            const response = await fetch('api/compras/cart_operations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('Resposta ao adicionar ao carrinho para compra:', data);

            if (data.status === 'success') {
                showToast('Produto adicionado, redirecionando para o checkout...');
                await loadCartItems();
                window.location.href = './checkout.php';
            } else {
                showToast(data.message || 'Erro ao adicionar ao carrinho', 'error');
                // Reverter atualização local
                if (existingItem) {
                    existingItem.quantity -= 1;
                } else {
                    cart_data = cart_data.filter(item => item.id !== productId);
                }
                updateCartUI();
            }
        } catch (error) {
            console.error('Erro ao processar compra imediata:', error);
            showToast('Erro ao conectar com o servidor', 'error');
            // Reverter atualização local
            const existingItem = cart_data.find(item => item.id === productId);
            if (existingItem) {
                existingItem.quantity -= 1;
            } else {
                cart_data = cart_data.filter(item => item.id !== productId);
            }
            updateCartUI();
        }
    }

    // Make buyNow global
    window.buyNow = buyNow;
});