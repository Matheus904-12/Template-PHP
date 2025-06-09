/**
 * Enhanced Checkout System
 * Handles checkout process, payment methods, form validation, and API interactions
 */

document.addEventListener('DOMContentLoaded', function () {
    // DOM elements
    const checkoutForm = document.getElementById('checkout-form');
    const paymentMethods = document.querySelectorAll('.payment-method');
    const paymentMethodInput = document.getElementById('payment_method');
    const creditCardDetails = document.getElementById('credit_card_details');
    const pixDetails = document.getElementById('pix_details');
    const cardNumberInput = document.getElementById('card_number');
    const cardExpiryInput = document.getElementById('card_expiry');
    const cardCvvInput = document.getElementById('card_cvv');
    const cepInput = document.getElementById('shipping_cep');
    const btnFindCep = document.getElementById('btn-find-cep');
    const processingOverlay = document.getElementById('processing-overlay');
    const pixModal = document.getElementById('pix-modal');
    const phoneInput = document.getElementById('shipping_phone');
    const savedCards = document.querySelectorAll('.saved-card');
    const newCardForm = document.getElementById('new_card_form');
    const STORE_ADDRESS = "Rua Mário Bochetti 1102, Suzano, SP, 08673-021";

    // Track order information globally
    const orderInfo = {
        paymentMethod: '',
        installments: 1,
        orderNumber: generateOrderNumber(),
        orderTotal: 0
    };

    // Initialize payment method
    let selectedPaymentMethod = '';

    // Initialize masks for form inputs
    initializeInputMasks();

    // Setup event listeners
    setupEventListeners();

    // Add installment selector to the page for credit card
    addCreditCardInstallmentSelector();

    /**
     * Initialize the checkout page
     */
    function initializeCheckout() {
        initializeInputMasks();
        setupEventListeners();

        // Auto-select first payment method (credit card by default)
        const firstPaymentMethod = document.querySelector('.payment-method');
        if (firstPaymentMethod) {
            selectPaymentMethod(firstPaymentMethod.dataset.method);
        }

        // Initialize installment selectors
        addCreditCardInstallmentSelector();

        // Initial price calculation
        updateAllPrices();

        // Set up real-time price updates
        document.querySelectorAll('.cart-item-quantity input').forEach(input => {
            input.addEventListener('change', updateAllPrices);
        });

        // Update prices when shipping is calculated
        if (cepInput) { // Add null check
            cepInput.addEventListener('blur', function () {
                if (this.value.replace(/\D/g, '').length === 8) {
                    findAddressByCep();
                }
            });
        } else {
            console.warn('CEP input not found, skipping event listener attachment');
        }

        // Initialize card inputs for brand detection
        initializeCardInputs();
    }

    /**
     * Initialize card input event listeners for brand detection
     */
    function initializeCardInputs() {
        if (cardNumberInput) {
            cardNumberInput.removeEventListener('input', handleCardInput); // Prevent duplicate listeners
            cardNumberInput.addEventListener('input', handleCardInput);

            // Trigger initial detection if input has a value
            if (cardNumberInput.value) {
                handleCardInput.call(cardNumberInput);
            }
        } else {
            console.warn('Card number input not found');
        }
    }

    /**
     * Handle card number input to detect and display card brand icon
     */
    function handleCardInput() {
        const cardNumber = this.value.replace(/\s/g, '');
        const cardTypeIconContainer = document.querySelector('.card-type-icon');

        if (!cardTypeIconContainer) {
            console.warn('Card type icon container not found');
            return;
        }

        // Clear existing icons
        cardTypeIconContainer.innerHTML = '';

        // Create new icon element
        const iconElement = document.createElement('i');
        iconElement.style.fontSize = '24px';
        iconElement.style.marginLeft = '10px';

        // Detect card brand based on number pattern
        if (cardNumber.startsWith('4')) {
            iconElement.className = 'fab fa-cc-visa';
        } else if (/^5[1-5]/.test(cardNumber)) {
            iconElement.className = 'fab fa-cc-mastercard';
        } else if (/^3[47]/.test(cardNumber)) {
            iconElement.className = 'fab fa-cc-amex';
        } else if (/^6(?:011|5)/.test(cardNumber)) {
            iconElement.className = 'fab fa-cc-discover';
        } else if (/^(?:30[0-5]|36|38)/.test(cardNumber)) {
            iconElement.className = 'fab fa-cc-diners-club';
        } else if (/^35(?:2[89]|[3-8][0-9])/.test(cardNumber)) {
            iconElement.className = 'fab fa-cc-jcb';
        } else if (/^((5067)|(4576)|(4011))/.test(cardNumber)) {
            iconElement.className = 'fas fa-credit-card'; // Elo não tem ícone específico no FontAwesome
        } else if (/^38/.test(cardNumber)) {
            iconElement.className = 'fas fa-credit-card'; // Hipercard não tem ícone específico
        } else {
            iconElement.className = 'fas fa-credit-card';
        }

        cardTypeIconContainer.appendChild(iconElement);
    }

    /**
     * Process credit card payment using Cielo API
     */
    // Adicione isso no início do arquivo, após as declarações iniciais
    const CIELO_ENV = 'production'; // ou 'sandbox' para testes
    const CIELO_API_URL = CIELO_ENV === 'sandbox'
        ? 'https://apisandbox.cieloecommerce.cielo.com.br'
        : 'https://api.cieloecommerce.cielo.com.br';

    async function processCreditCardPayment() {
        if (!validateCardInputs()) return;

        const savedCardId = document.getElementById('saved_card_id');
        const isDebit = selectedPaymentMethod === 'debit_card';

        // Formatar a data de expiração para MM/AAAA
        const expiryParts = cardExpiryInput.value.split('/');
        const expiryMonth = expiryParts[0].padStart(2, '0');
        const expiryYear = `20${expiryParts[1].padStart(2, '0')}`; // Ex.: "2024" para "24"
        const expirationDate = `${expiryMonth}/${expiryYear}`; // Ex.: "12/2024"

        // Validação da data
        const currentYear = new Date().getFullYear();
        const currentMonth = new Date().getMonth() + 1;
        const inputYear = parseInt(expiryYear);
        const inputMonth = parseInt(expiryMonth);

        if (inputMonth < 1 || inputMonth > 12) {
            showError('Mês de validade inválido.');
            return;
        }
        if (inputYear < currentYear || (inputYear === currentYear && inputMonth < currentMonth)) {
            showError('Data de validade expirada.');
            return;
        }
        if (inputYear > currentYear + 10) {
            showError('Data de validade muito futura.');
            return;
        }

        const paymentData = {
            MerchantOrderId: orderInfo.orderNumber,
            Customer: {
                Name: document.getElementById('card_name').value.trim(),
                Email: document.getElementById('cardholder_email').value,
                Identity: document.getElementById('identification_number').value.replace(/\D/g, ''),
                IdentityType: 'CPF'
            },
            Payment: {
                Type: isDebit ? 'DebitCard' : 'CreditCard',
                Amount: Math.round(orderInfo.orderTotal * 100),
                Installments: isDebit ? 1 : orderInfo.installments,
                Capture: true,
                SoftDescriptor: 'GOLDLARCRISTAIS',
                ReturnUrl: window.location.href,
                [isDebit ? 'DebitCard' : 'CreditCard']: {
                    CardNumber: cardNumberInput.value.replace(/\D/g, ''),
                    Holder: document.getElementById('card_name').value.trim(),
                    ExpirationDate: expirationDate,
                    SecurityCode: cardCvvInput.value.trim(),
                    Brand: detectCardBrand(cardNumberInput.value)
                }
            }
        };

        try {
            processingOverlay.style.display = 'flex';
            const response = await fetch('includes/checkout/process_cielo_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(paymentData)
            });

            const data = await response.json();

            if (data.success) {
                if (isDebit && data.payment.AuthenticationUrl) {
                    window.location.href = data.payment.AuthenticationUrl;
                    return;
                }
                const paymentIdInput = document.createElement('input');
                paymentIdInput.type = 'hidden';
                paymentIdInput.name = 'payment_id';
                paymentIdInput.value = data.payment.PaymentId;
                checkoutForm.appendChild(paymentIdInput);
                checkoutForm.submit();
            } else {
                showError(data.message || 'Erro ao processar pagamento. Verifique os logs para mais detalhes.');
            }
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao conectar ao gateway de pagamento. Verifique sua conexão.');
        } finally {
            processingOverlay.style.display = 'none';
        }
    }
    
    // Função de validação dos campos do cartão
    function validateCardInputs() {
        const cardNumber = cardNumberInput.value.replace(/\D/g, '');
        const cvv = cardCvvInput.value.trim();
        const expiry = cardExpiryInput.value;

        // Verificação de Luhn
        if (!isValidLuhn(cardNumber)) {
            showError('Número do cartão inválido (Luhn check failed).');
            return false;
        }
        if (!cardNumber || cardNumber.length < 13 || cardNumber.length > 19) {
            showError('Número do cartão inválido.');
            return false;
        }
        if (!cvv || cvv.length < 3 || cvv.length > 4) {
            showError('CVV inválido.');
            return false;
        }
        if (!validateCardExpiry(expiry)) {
            showError('Data de validade inválida.');
            return false;
        }
        return true;
    }

    function isValidLuhn(cardNumber) {
        let sum = 0;
        let isEven = false;
        for (let i = cardNumber.length - 1; i >= 0; i--) {
            let digit = parseInt(cardNumber[i]);
            if (isEven) {
                digit *= 2;
                if (digit > 9) digit -= 9;
            }
            sum += digit;
            isEven = !isEven;
        }
        return sum % 10 === 0;
    }

    /**
     * Detect card brand
     */
    function detectCardBrand(cardNumber) {
        const patterns = {
            'Visa': /^4/,
            'Mastercard': /^5[1-5]/,
            'Amex': /^3[47]/,
            'Discover': /^6(?:011|5)/,
            'Diners': /^(?:30[0-5]|36|38)/,
            'JCB': /^35(?:2[89]|[3-8][0-9])/,
            'Elo': /^((5067)|(4576)|(4011))/,
            'Hipercard': /^38/
        };
        cardNumber = cardNumber.replace(/\D/g, '');
        for (const brand in patterns) {
            if (patterns[brand].test(cardNumber)) {
                return brand;
            }
        }
        return 'Unknown';
    }

    /**
     * Copy text to clipboard
     */
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        if (!element) {
            console.error(`Element with ID ${elementId} not found`);
            return;
        }

        const tempElement = document.createElement('textarea');
        tempElement.value = element.textContent;
        document.body.appendChild(tempElement);
        tempElement.select();

        try {
            document.execCommand('copy');
            showMessage('Código PIX copiado para a área de transferência!', 'success');
        } catch (err) {
            console.error('Erro ao copiar texto:', err);
            showMessage('Erro ao copiar o código PIX. Por favor, copie manualmente.', 'error');
        }

        document.body.removeChild(tempElement);
    }

    /**
     * Generate a random order number
     */
    function generateOrderNumber() {
        return 'ORD-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
    }

    /**
     * Add installment selector for credit card payments
     */
    function addCreditCardInstallmentSelector() {
        const installmentSelect = document.createElement('select');
        installmentSelect.className = 'form-control';
        installmentSelect.id = 'installment-select';

        const maxInstallments = 6;
        const orderTotal = orderInfo.orderTotal;

        for (let i = 1; i <= maxInstallments; i++) {
            const option = document.createElement('option');
            option.value = i;
            const installmentAmount = (orderTotal / i).toFixed(2);
            option.text = `${i}x de R$${installmentAmount} ${i === 1 ? '(à vista)' : ''}`;
            installmentSelect.appendChild(option);
        }

        if (!checkoutForm) {
            console.error('Elemento com id "checkout-form" não encontrado no DOM. Certifique-se de que o formulário existe.');
            return; // Interrompe a execução se o elemento não for encontrado
        }

        const installmentContainer = document.createElement('div');
        installmentContainer.className = 'form-group';
        installmentContainer.innerHTML = '<label for="installment-select">Parcelas</label>';
        installmentContainer.appendChild(installmentSelect);
        checkoutForm.appendChild(installmentContainer);

        installmentSelect.addEventListener('change', () => {
            orderInfo.installments = parseInt(installmentSelect.value);
            updateAllPrices();
        });

        paymentMethods.forEach(method => {
            method.addEventListener('click', () => {
                selectedPaymentMethod = method.dataset.method;
                installmentSelect.disabled = selectedPaymentMethod === 'debit_card';
                if (selectedPaymentMethod === 'debit_card') {
                    orderInfo.installments = 1;
                    installmentSelect.value = '1';
                }
            });
        });
    }

    /**
     * Initialize input masks for better user experience
     */
    function initializeInputMasks() {
        if (cardNumberInput) $(cardNumberInput).mask('0000 0000 0000 0000');
        if (cardExpiryInput) $(cardExpiryInput).mask('00/00');
        if (cardCvvInput) $(cardCvvInput).mask('0000');
        if (cepInput) $(cepInput).mask('00000-000');
        if (phoneInput) $(phoneInput).mask('(00) 00000-0000');
        const identificationNumberInput = document.getElementById('identification_number');
        if (identificationNumberInput) $(identificationNumberInput).mask('000.000.000-00');
    }

    /**
     * Setup all event listeners
     */
    function setupEventListeners() {
        paymentMethods.forEach(method => {
            method.addEventListener('click', function () {
                selectPaymentMethod(this.dataset.method);
            });
        });

        if (btnFindCep) {
            btnFindCep.addEventListener('click', findAddressByCep);
        }

        if (cepInput) {
            cepInput.addEventListener('change', findAddressByCep);
        }

        if (savedCards) {
            savedCards.forEach(card => {
                card.addEventListener('click', function () {
                    if (this.classList.contains('new-card')) {
                        showNewCardForm();
                    } else {
                        selectSavedCard(this);
                    }
                });
            });
        }

        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function (e) {
                if (!validateCheckoutForm()) {
                    e.preventDefault();
                    return false;
                }

                processingOverlay.style.display = 'flex';

                if (selectedPaymentMethod === 'pix') {
                    e.preventDefault();
                    processPixPayment();
                    return false;
                } else if (selectedPaymentMethod === 'credit_card' || selectedPaymentMethod === 'debit_card') {
                    e.preventDefault();
                    processCreditCardPayment();
                    return false;
                }

                orderInfo.paymentMethod = selectedPaymentMethod;
                return true;
            });
        }

        // Exemplo: Adicionar botões para consulta e cancelamento (opcional)
        const checkStatusBtn = document.getElementById('check-status-btn');
        if (checkStatusBtn) {
            checkStatusBtn.addEventListener('click', checkPaymentStatus);
        }

        const cancelBtn = document.getElementById('cancel-payment-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', cancelPayment);
        }

        const modalClose = document.querySelector('.modal-close');
        if (modalClose) {
            modalClose.addEventListener('click', function () {
                pixModal.style.display = 'none';
            });
        }

        const copyPixCode = document.getElementById('copy-pix-code');
        if (copyPixCode) {
            copyPixCode.addEventListener('click', function () {
                copyToClipboard('pix-code-text');
            });
        }

        const pixConfirmBtn = document.getElementById('pix-confirm-payment');
        if (pixConfirmBtn) {
            pixConfirmBtn.addEventListener('click', confirmPixPayment);
        }
    }

    async function checkPaymentStatus() {
        const merchantOrderId = orderInfo.orderNumber;

        try {
            const response = await fetch('includes/checkout/process_cielo_payment.php?action=check_status&merchantOrderId=' + merchantOrderId, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();

            if (data.success) {
                showMessage(`Status do pagamento: ${data.statusDescription}`, 'info');
            } else {
                showError('Nenhuma transação encontrada para este pedido.');
            }
        } catch (error) {
            console.error('Erro ao consultar status:', error);
            showError('Erro ao consultar o status do pagamento.');
        }
    }

    async function cancelPayment() {
        const paymentId = orderInfo.paymentId || document.querySelector('input[name="payment_id"]')?.value;

        if (!paymentId) {
            showError('Nenhum pagamento encontrado para cancelar.');
            return;
        }

        try {
            const response = await fetch('includes/checkout/process_cielo_payment.php?action=cancel_payment&paymentId=' + paymentId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();

            if (data.success) {
                showMessage('Pagamento cancelado com sucesso!', 'success');
            } else {
                showError('Erro ao cancelar o pagamento: ' + (data.message || 'Desconhecido'));
            }
        } catch (error) {
            console.error('Erro ao cancelar pagamento:', error);
            showError('Erro ao conectar ao servidor para cancelamento.');
        }
    }

    /**
     * Select payment method and update UI
     */
    function selectPaymentMethod(method) {
        paymentMethods.forEach(m => m.classList.remove('active'));
        document.querySelector(`.payment-method[data-method="${method}"]`).classList.add('active');

        paymentMethodInput.value = method;
        selectedPaymentMethod = method;
        orderInfo.paymentMethod = method;

        if (method === 'credit_card' || method === 'debit_card') {
            creditCardDetails.style.display = 'block';
            pixDetails.style.display = 'none';

            // Atualiza o texto do botão conforme o tipo
            const checkoutBtn = document.querySelector('.btn-checkout');
            if (method === 'debit_card') {
                checkoutBtn.textContent = 'Pagar com Débito';
            } else {
                checkoutBtn.textContent = 'Finalizar Compra';
            }
        } else if (method === 'pix') {
            creditCardDetails.style.display = 'none';
            pixDetails.style.display = 'block';
        }
    }

    /**
     * Get the subtotal from the cart items
     */
    function getSubtotal() {
        let subtotal = 0;
        const cartItems = document.querySelectorAll('.cart-item');

        cartItems.forEach(item => {
            const priceElement = item.querySelector('.cart-item-price');
            const quantityElement = item.querySelector('.cart-item-quantity');

            if (priceElement && quantityElement) {
                const price = parseFloat(priceElement.textContent.replace('R$', '').replace(',', '.'));
                const quantity = parseInt(quantityElement.textContent.replace('Quantidade: ', ''));
                subtotal += price * quantity;
            }
        });

        return subtotal;
    }

    /**
     * Get the shipping cost
     */
    function getShippingCost() {
        const shippingCostElement = document.querySelector('.shipping-cost');
        if (!shippingCostElement) return 0;
        return parseFloat(shippingCostElement.textContent.replace('R$', '').replace(',', '.'));
    }

    /**
     * Get discount value
     */
    function getDiscount() {
        const discountElement = document.querySelector('.price-row:not(.price-total):not(.price-subtotal) span:last-child');
        if (!discountElement || !discountElement.textContent.includes('-')) return 0;
        return parseFloat(discountElement.textContent.replace('-R$', '').replace(',', '.'));
    }

    /**
     * Calculate shipping cost based on CEP
     */
    function calculateShippingCost(cep, destinationAddress) {
        const cartTotal = getSubtotal();
        if (cartTotal >= 350.00) {
            return 0.00;
        }

        const cleanCep = cep.replace(/\D/g, '');
        const cepBase = cleanCep.substring(0, 5);
        const spPrefixes = ['01', '02', '03', '04', '05', '06', '07', '08', '09'];
        const cepPrefix = cleanCep.substring(0, 2);

        if (spPrefixes.includes(cepPrefix)) {
            return 0.00;
        }

        let shippingCost = 100.00;
        if (cepBase >= '08000' && cepBase <= '08499') {
            shippingCost = 30.00;
        } else if (cepBase >= '08500' && cepBase <= '08999') {
            shippingCost = 45.00;
        } else if (cepBase >= '09000' && cepBase <= '09999') {
            shippingCost = 60.00;
        }
        return shippingCost;
    }

    /**
     * Find address information by CEP using API
     */
    function findAddressByCep() {
        const cep = cepInput.value.replace(/\D/g, '');

        if (cep.length !== 8) {
            showError('CEP inválido. Por favor, digite um CEP válido.');
            return;
        }

        btnFindCep.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btnFindCep.disabled = true;

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (data.erro) {
                    showError('CEP não encontrado.');
                    return;
                }

                const addressInput = document.getElementById('shipping_address');
                if (addressInput) {
                    addressInput.value = `${data.logradouro}, ${data.bairro}, ${data.localidade}, ${data.uf}`;
                    const fullAddress = `${data.logradouro}, ${data.bairro}, ${data.localidade}, ${data.uf}, ${cep}`;
                    let fullAddressInput = document.getElementById('full_shipping_address');
                    if (!fullAddressInput) {
                        fullAddressInput = document.createElement('input');
                        fullAddressInput.type = 'hidden';
                        fullAddressInput.id = 'full_shipping_address';
                        fullAddressInput.name = 'full_shipping_address';
                        checkoutForm.appendChild(fullAddressInput);
                    }
                    fullAddressInput.value = fullAddress;

                    const shippingCost = calculateShippingCost(cep, fullAddress);
                    updateShippingCost(shippingCost);

                    let shippingCostInput = document.getElementById('shipping_cost');
                    if (!shippingCostInput) {
                        shippingCostInput = document.createElement('input');
                        shippingCostInput.type = 'hidden';
                        shippingCostInput.id = 'shipping_cost';
                        shippingCostInput.name = 'shipping_cost';
                        checkoutForm.appendChild(shippingCostInput);
                    }
                    shippingCostInput.value = shippingCost.toFixed(2);
                }

                btnFindCep.innerHTML = 'Buscar';
                btnFindCep.disabled = false;
            })
            .catch(error => {
                console.error('Erro ao buscar CEP:', error);
                showError('Erro ao buscar CEP. Tente novamente.');
                btnFindCep.innerHTML = 'Buscar';
                btnFindCep.disabled = false;
            });
    }

    /**
     * Show new card form and hide saved cards
     */
    function showNewCardForm() {
        const savedCardsContainer = document.querySelector('.saved-cards');
        if (savedCardsContainer) savedCardsContainer.style.display = 'none';
        if (newCardForm) newCardForm.style.display = 'block';
        initializeCardInputs(); // Reinitialize card input for brand detection
    }

    /**
     * Select a saved card
     */
    function selectSavedCard(cardElement) {
        savedCards.forEach(card => card.classList.remove('active'));
        cardElement.classList.add('active');
        if (newCardForm) newCardForm.style.display = 'none';
        const cardId = cardElement.dataset.cardId;
        let cardIdInput = document.getElementById('saved_card_id');
        if (!cardIdInput) {
            cardIdInput = document.createElement('input');
            cardIdInput.type = 'hidden';
            cardIdInput.id = 'saved_card_id';
            cardIdInput.name = 'saved_card_id';
            checkoutForm.appendChild(cardIdInput);
        }
        cardIdInput.value = cardId;
    }

    /**
     * Validate checkout form before submission
     */
    function validateCheckoutForm() {
        if (!selectedPaymentMethod) {
            showError('Por favor, selecione um método de pagamento.');
            return false;
        }

        const shippingAddress = document.getElementById('shipping_address');
        const shippingNumber = document.getElementById('shipping_number');
        const shippingCep = document.getElementById('shipping_cep');

        if (!shippingAddress || !shippingAddress.value.trim()) {
            showError('Por favor, informe o endereço de entrega.');
            return false;
        }

        if (!shippingNumber || !shippingNumber.value.trim()) {
            showError('Por favor, informe o número do endereço.');
            return false;
        }

        if (!shippingCep || !shippingCep.value.trim() || shippingCep.value.replace(/\D/g, '').length !== 8) {
            showError('Por favor, informe um CEP válido.');
            return false;
        }

        if (selectedPaymentMethod === 'credit_card' || selectedPaymentMethod === 'debit_card') {
            const savedCardId = document.getElementById('saved_card_id');
            if (!savedCardId || !savedCardId.value) {
                if (!validateCardInputs()) {
                    return false;
                }
            } else {
                if (!cardCvvInput || !cardCvvInput.value.trim() || cardCvvInput.value.length < 3) {
                    showError('Por favor, informe o código de segurança do cartão salvo.');
                    return false;
                }
            }

            const cardholderEmail = document.getElementById('cardholder_email');
            if (!cardholderEmail || !cardholderEmail.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cardholderEmail.value)) {
                showError('Por favor, informe um e-mail válido.');
                return false;
            }

            const identificationNumber = document.getElementById('identification_number');
            if (!identificationNumber || !identificationNumber.value.trim() || identificationNumber.value.replace(/\D/g, '').length !== 11) {
                showError('Por favor, informe um CPF válido.');
                return false;
            }
        }

        return true;
    }

    /**
     * Validate credit card expiry date
     */
    function validateCardExpiry(expiry) {
        const parts = expiry.split('/');
        if (parts.length !== 2) return false;
        const month = parseInt(parts[0], 10);
        const year = parseInt('20' + parts[1], 10);
        if (isNaN(month) || isNaN(year)) return false;
        if (month < 1 || month > 12) return false;
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;
        if (year < currentYear) return false;
        if (year === currentYear && month < currentMonth) return false;
        return true;
    }

    /**
     * Process PIX payment
     */
    function processPixPayment() {
        const pixData = { amount: getTotal(), orderId: generateTempOrderId() };
        orderInfo.paymentMethod = 'pix';
        orderInfo.installments = 1;

        setTimeout(() => {
            processingOverlay.style.display = 'none';
            pixModal.style.display = 'flex';
            const pixPayload = generatePixPayload(pixData.amount, orderInfo);
            const encodedPixPayload = encodeURIComponent(pixPayload);
            const pixCodeText = document.getElementById('pix-code-text');
            if (pixCodeText) {
                pixCodeText.textContent = pixPayload;
            }

            const qrContainer = document.querySelector('.pix-qrcode');
            if (qrContainer) {
                qrContainer.innerHTML = '';
                const qrImage = document.createElement('img');
                qrImage.id = 'pix-qrcode-img';
                qrImage.style.width = '100%';
                qrImage.style.height = '100%';
                qrImage.style.maxWidth = '200px';
                qrImage.style.zIndex = '100';
                qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?data=${encodedPixPayload}&size=200x200`;
                qrImage.alt = 'QR Code PIX';
                qrContainer.appendChild(qrImage);
            }
        }, 1500);
    }

    /**
     * Update shipping cost display on the page
     */
    function updateShippingCost(cost) {
        const formattedCost = cost.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        const shippingCostElement = document.querySelector('.shipping-cost');
        if (shippingCostElement) {
            shippingCostElement.textContent = formattedCost;
        } else {
            const cartTotalElement = document.querySelector('.price-total');
            if (cartTotalElement) {
                const shippingElement = document.createElement('div');
                shippingElement.className = 'price-row shipping-row';
                shippingElement.innerHTML = `
                    <span>Frete:</span>
                    <span class="shipping-cost">${formattedCost}</span>
                `;
                cartTotalElement.parentNode.insertBefore(shippingElement, cartTotalElement);
            }
        }

        let shippingCostInput = document.getElementById('shipping_cost');
        if (!shippingCostInput) {
            shippingCostInput = document.createElement('input');
            shippingCostInput.type = 'hidden';
            shippingCostInput.id = 'shipping_cost';
            shippingCostInput.name = 'shipping_cost';
            checkoutForm.appendChild(shippingCostInput);
        }
        shippingCostInput.value = cost.toFixed(2);
        updateTotalWithShipping(cost);
    }

    /**
     * Update total price including shipping cost
     */
    function updateTotalWithShipping(shippingCost) {
        const subtotal = getSubtotal();
        const discount = getDiscount();
        const total = subtotal + shippingCost - discount;

        const shippingCostElement = document.querySelector('.shipping-cost');
        if (shippingCostElement) {
            shippingCostElement.textContent = `R$ ${shippingCost.toFixed(2).replace('.', ',')}`;
        }

        const totalElement = document.querySelector('.price-total span:last-child');
        if (totalElement) {
            totalElement.textContent = `R$${total.toFixed(2).replace('.', ',')}`;
        }

        let shippingCostInput = document.getElementById('shipping_cost');
        if (!shippingCostInput) {
            shippingCostInput = document.createElement('input');
            shippingCostInput.type = 'hidden';
            shippingCostInput.id = 'shipping_cost';
            shippingCostInput.name = 'shipping_cost';
            checkoutForm.appendChild(shippingCostInput);
        }
        shippingCostInput.value = shippingCost;
        orderInfo.orderTotal = total;
        updateInstallmentOptions();
    }

    /**
     * Update the installment options when the total price changes
     */
    function updateInstallmentOptions() {
        const total = getTotal();
        const ccInstallments = document.getElementById('cc_installments');
        if (ccInstallments) {
            const selectedValue = ccInstallments.value;
            let options = '';
            for (let i = 1; i <= 6; i++) {
                const installmentAmount = (total / i).toFixed(2).replace('.', ',');
                options += `<option value="${i}" ${i == selectedValue ? 'selected' : ''}>${i}x de R$${installmentAmount} sem juros</option>`;
            }
            ccInstallments.innerHTML = options;
        }
    }

    /**
     * Generate PIX payload
     */
    function generatePixPayload(value, orderInfo) {
        const pixKey = "cristaisgoldlar@outlook.com";
        const merchantName = "GOLDLAR CRISTAIS";
        const merchantCity = "SUZANO";
        const valueFormatted = value.toFixed(2);
        const transactionId = orderInfo.orderNumber.replace(/\D/g, '').padStart(10, '0').substring(0, 10);

        const payload = {
            '00': '01',
            '26': {
                '00': 'BR.GOV.BCB.PIX',
                '01': pixKey
            },
            '52': '0000',
            '53': '986',
            '54': valueFormatted,
            '58': 'BR',
            '59': merchantName,
            '60': merchantCity,
            '62': {
                '05': transactionId
            }
        };

        let pixCode = '';
        for (const [id, value] of Object.entries(payload)) {
            if (typeof value === 'object') {
                let subPayload = '';
                for (const [subId, subValue] of Object.entries(value)) {
                    subPayload += `${subId}${subValue.length.toString().padStart(2, '0')}${subValue}`;
                }
                pixCode += `${id}${subPayload.length.toString().padStart(2, '0')}${subPayload}`;
            } else {
                pixCode += `${id}${value.length.toString().padStart(2, '0')}${value}`;
            }
        }

        pixCode += '6304';
        const crc = calculateCRC16(pixCode);
        pixCode += crc;
        return pixCode;
    }

    /**
     * Calculate CRC16 for PIX payload
     */
    function calculateCRC16(payload) {
        const polynomial = 0x1021;
        let crc = 0xFFFF;
        const bytes = [];
        for (let i = 0; i < payload.length; i++) {
            bytes.push(payload.charCodeAt(i));
        }

        for (const byte of bytes) {
            crc ^= (byte << 8);
            for (let i = 0; i < 8; i++) {
                if ((crc & 0x8000) !== 0) {
                    crc = ((crc << 1) ^ polynomial) & 0xFFFF;
                } else {
                    crc = (crc << 1) & 0xFFFF;
                }
            }
        }
        return crc.toString(16).toUpperCase().padStart(4, '0');
    }

    /**
     * Confirm PIX payment
     */
    function confirmPixPayment() {
        processingOverlay.style.display = 'flex';
        setTimeout(() => {
            if (window.pixCountdownInterval) {
                clearInterval(window.pixCountdownInterval);
            }
            pixModal.style.display = 'none';
            const hiddenPaymentInfo = document.createElement('input');
            hiddenPaymentInfo.type = 'hidden';
            hiddenPaymentInfo.name = 'pix_payment_id';
            hiddenPaymentInfo.value = 'PIX_' + Date.now();
            checkoutForm.appendChild(hiddenPaymentInfo);
            checkoutForm.submit();
        }, 2000);
    }

    /**
     * Show error message
     */
    function showError(message) {
        let errorElement = document.querySelector('.error-message');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            const checkoutBtn = document.querySelector('.btn-checkout');
            checkoutBtn.parentNode.insertBefore(errorElement, checkoutBtn);
        }
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => {
            errorElement.style.display = 'none';
        }, 5000);
    }

    /**
     * Show message to user
     */
    function showMessage(text, type = 'success') {
        const colors = {
            success: '#4e8d7c',
            error: '#e74c3c',
            warning: '#f39c12'
        };
        const message = document.createElement('div');
        message.className = 'user-message';
        message.textContent = text;
        message.style.backgroundColor = colors[type] || colors.success;
        document.body.appendChild(message);
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 500);
        }, 3000);
    }

    /**
     * Calculate total order value
     */
    function getTotal() {
        const subtotal = getSubtotal();
        const shipping = getShippingCost();
        const discount = getDiscount();
        return subtotal + shipping - discount;
    }

    /**
     * Update all price totals in real-time
     */
    function updateAllPrices() {
        const subtotal = getSubtotal();
        const shipping = getShippingCost();
        const discount = getDiscount();
        const total = subtotal + shipping - discount;

        const subtotalElement = document.querySelector('.price-row:first-child span:last-child');
        if (subtotalElement) {
            subtotalElement.textContent = `R$${subtotal.toFixed(2).replace('.', ',')}`;
        }

        const totalElement = document.querySelector('.price-total span:last-child');
        if (totalElement) {
            totalElement.textContent = `R$${total.toFixed(2).replace('.', ',')}`;
        }

        updateInstallmentOptions();
    }

    /**
     * Generate temporary order ID for PIX
     */
    function generateTempOrderId() {
        return 'TEMP_' + Date.now();
    }

    /**
     * Update checkout progress
     */
    function updateProgress(step) {
        const steps = document.querySelectorAll('.progress-step');
        steps.forEach((stepElement, index) => {
            const circle = stepElement.querySelector('.step-circle');
            const line = stepElement.querySelector('.step-line');
            if (index < step) {
                circle.classList.add('completed');
                circle.classList.remove('active');
                if (line) line.classList.add('active');
            } else if (index === step) {
                circle.classList.add('active');
                circle.classList.remove('completed');
                if (line) line.classList.remove('active');
            } else {
                circle.classList.remove('active', 'completed');
                if (line) line.classList.remove('active');
            }
        });
    }

    // Initialize page
    if (paymentMethods.length > 0) {
        selectPaymentMethod(paymentMethods[0].dataset.method);
    }
    updateProgress(1);
});