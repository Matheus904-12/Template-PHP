document.addEventListener('DOMContentLoaded', function () {
    // Create and append modal HTML
    const modalHTML = `
        <div id="productModal" class="product-modal">
            <div class="modal-content">
                <span id="closeModal" class="modal-close">×</span>
                <div class="modal-grid">
                    <div class="modal-img">
                        <img id="modalImage" src="" alt="Product Image">
                    </div>
                    <div class="modal-details">
                        <h2 id="modalTitle" class="modal-title"></h2>
                        <p id="modalDescription" class="modal-description"></p>
                        <div id="modalPrice" class="modal-price">
                            <div class="price-container">
                                <span class="original-price"></span>
                                <span class="discount-price"></span>
                            </div>
                            <div class="installment-container">
                                <select id="installmentSelect" class="installment-select">
                                    <!-- Options will be populated dynamically -->
                                </select>
                            </div>
                        </div>
                        <div class="payment-options">
                            <span class="payment-option">
                                <i class="ri-bank-card-line"></i> Cartão de Crédito
                            </span>
                            <span class="payment-option">
                                <i class="ri-qr-code-line"></i> Pix
                            </span>
                        </div>
                        <div class="modal-actions">
                            <button id="addToCartBtn" class="modal-add-to-cart">
                                <i class="ri-shopping-cart-line"></i> Adicionar ao Carrinho
                            </button>
                            <button id="addToFavoritesBtn" class="modal-add-to-favorites">
                                <i class="ri-heart-line"></i> Adicionar aos Favoritos
                            </button>
                            <button id="buyNowBtn" class="modal-buy-now">
                                <i class="ri-wallet-line"></i> Comprar Agora
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Modal elements
    const modal = document.getElementById('productModal');
    const closeModal = document.getElementById('closeModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    const modalPrice = document.getElementById('modalPrice');
    const modalDescription = document.getElementById('modalDescription');
    const originalPrice = modalPrice.querySelector('.original-price');
    const discountPrice = modalPrice.querySelector('.discount-price');
    const installmentSelect = document.getElementById('installmentSelect');
    const addToCartBtn = document.getElementById('addToCartBtn');
    const addToFavoritesBtn = document.getElementById('addToFavoritesBtn');
    const buyNowBtn = document.getElementById('buyNowBtn');

    // Open modal when clicking product box
    document.querySelectorAll('.product-box').forEach(box => {
        box.addEventListener('click', function (e) {
            // Evitar que cliques nos botões de carrinho/favoritos abram o modal
            if (e.target.classList.contains('add-cart') || e.target.classList.contains('save-item')) {
                return;
            }

            const productId = this.dataset.id;
            const productName = this.dataset.name;
            const productPrice = parseFloat(this.dataset.price);
            const productImage = this.dataset.image;
            const productDescription = this.dataset.description;

            console.log('Abrindo modal para produto:', { productId, productPrice }); // Depuração

            // Populate modal
            modalImage.src = productImage;
            modalImage.alt = productName;
            modalTitle.textContent = productName;
            modalDescription.textContent = productDescription || 'Sem descrição disponível.';
            
            // Price calculation
            const discount = productPrice * 0.95; // 5% de desconto à vista
            originalPrice.textContent = `R$ ${productPrice.toFixed(2).replace('.', ',')}`;
            discountPrice.textContent = `R$ ${discount.toFixed(2).replace('.', ',')} à vista`;
            
            // Update installment options
            installmentSelect.innerHTML = '';
            const maxInstallments = 5; // Sempre permitir até 5 parcelas
            for (let i = 1; i <= maxInstallments; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i === 1 ? `À vista R$ ${discount.toFixed(2).replace('.', ',')}` : `${i}x de R$ ${(productPrice / i).toFixed(2).replace('.', ',')} sem juros`;
                installmentSelect.appendChild(option);
            }
            console.log('Parcelas geradas:', installmentSelect.innerHTML); // Depuração

            // Update favorite button state
            const isInFavorites = window.favorites_data && window.favorites_data.some(item => item.id === productId);
            addToFavoritesBtn.classList.toggle('active', isInFavorites);
            const heartIcon = addToFavoritesBtn.querySelector('i');
            heartIcon.classList.toggle('ri-heart-line', !isInFavorites);
            heartIcon.classList.toggle('ri-heart-fill', isInFavorites);
            heartIcon.style.color = isInFavorites ? '#e74c3c' : '';

            // Store product ID for buttons
            addToCartBtn.dataset.id = productId;
            addToFavoritesBtn.dataset.id = productId;
            buyNowBtn.dataset.id = productId;

            // Show modal
            modal.classList.add('active');
        });
    });

    // Close modal
    if (closeModal) {
        closeModal.addEventListener('click', () => {
            modal.classList.remove('active');
        });
    }

    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });

    // Add to cart from modal
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', () => {
            const productId = addToCartBtn.dataset.id;
            window.addToCart(productId);
            modal.classList.remove('active');
        });
    }

    // Add to favorites from modal
    if (addToFavoritesBtn) {
        addToFavoritesBtn.addEventListener('click', () => {
            const productId = addToFavoritesBtn.dataset.id;
            const isInFavorites = addToFavoritesBtn.classList.contains('active');
            
            if (isInFavorites) {
                window.removeFavoriteItem(productId);
                addToFavoritesBtn.classList.remove('active');
                const heartIcon = addToFavoritesBtn.querySelector('i');
                heartIcon.classList.remove('ri-heart-fill');
                heartIcon.classList.add('ri-heart-line');
                heartIcon.style.color = '';
            } else {
                window.addToFavorites(productId);
                addToFavoritesBtn.classList.add('active');
                const heartIcon = addToFavoritesBtn.querySelector('i');
                heartIcon.classList.remove('ri-heart-line');
                heartIcon.classList.add('ri-heart-fill');
                heartIcon.style.color = '#e74c3c';
            }
        });
    }

    // Buy now from modal
    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', () => {
            const productId = buyNowBtn.dataset.id;
            window.buyNow(productId);
            modal.classList.remove('active');
        });
    }

    // Update installment price on selection
    if (installmentSelect) {
        installmentSelect.addEventListener('change', (e) => {
            const selectedInstallments = parseInt(e.target.value);
            const productPrice = parseFloat(discountPrice.textContent.replace('R$ ', '').replace(',', '.'));
            const installmentPrice = (productPrice / selectedInstallments).toFixed(2).replace('.', ',');
            e.target.options[e.target.selectedIndex].textContent = selectedInstallments === 1 
                ? `À vista R$ ${productPrice.toFixed(2).replace('.', ',')}` 
                : `${selectedInstallments}x de R$ ${installmentPrice} sem juros`;
        });
    }
});