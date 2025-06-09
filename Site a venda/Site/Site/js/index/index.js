// Script para o modal de detalhes do produto
document.addEventListener('DOMContentLoaded', function() {
    // Seleciona o modal e elementos relacionados
    const modal = document.getElementById('product-modal');
    const closeBtn = document.querySelector('.close-modal');
    const detailButtons = document.querySelectorAll('.detalhes-btn');
    
    // Elementos para exibir informações do produto no modal
    const modalProductImage = document.getElementById('modal-product-image');
    const modalProductName = document.getElementById('modal-product-name');
    const modalProductPrice = document.getElementById('modal-product-price');
    const modalProductParcelas = document.getElementById('modal-product-parcelas');
    
    // Adiciona evento de click para todos os botões "Ver Detalhes"
    detailButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Obtém os dados do produto do card pai
            const card = this.closest('.card');
            const productImage = card.querySelector('.imagem img').src;
            const productName = card.querySelector('p:nth-of-type(1)').textContent;
            const productPrice = card.querySelector('.preco').textContent;
            const productParcelas = card.querySelector('.parcelamento').textContent;
            
            // Preenche o modal com os dados do produto
            modalProductImage.src = productImage;
            modalProductName.textContent = productName;
            modalProductPrice.textContent = productPrice;
            modalProductParcelas.textContent = productParcelas;
            
            // Exibe o modal
            modal.style.display = 'block';
            
            // Adiciona uma classe ao body para evitar rolagem
            document.body.style.overflow = 'hidden';
        });
    });
    
    // Fecha o modal quando clicar no botão X
    closeBtn.addEventListener('click', closeModal);
    
    // Fecha o modal quando clicar fora dele
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    
    // Função para fechar o modal
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restaura a rolagem
    }
    
    // Fecha o modal com a tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.style.display === 'block') {
            closeModal();
        }
    });
});