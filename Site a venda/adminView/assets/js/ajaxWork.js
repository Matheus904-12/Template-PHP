

function showProductItems() {
    $.ajax({
        url: "./adminView/viewAllProducts.php",
        method: "post",
        data: { record: 1 },
        success: function (data) {
            $('.allContent-section').html(data);
        }
    });
}
function showCategory() {
    $.ajax({
        url: "./adminView/viewCategories.php",
        method: "post",
        data: { record: 1 },
        success: function (data) {
            $('.allContent-section').html(data);
        }
    });
}
function showSizes() {
    $.ajax({
        url: "./adminView/viewSizes.php",
        method: "post",
        data: { record: 1 },
        success: function (data) {
            $('.allContent-section').html(data);
        }
    });
}
function showProductSizes() {
    $.ajax({
        url: "./adminView/viewProductSizes.php",
        method: "post",
        data: { record: 1 },
        success: function (data) {
            $('.allContent-section').html(data);
        }
    });
}

function showCustomers() {
    $.ajax({
        url: "./adminView/viewCustomers.php",
        method: "post",
        data: { record: 1 },
        success: function (data) {
            $('.allContent-section').html(data);
        }
    });
}

function showOrders() {
    $.ajax({
        url: "./adminView/viewAllOrders.php",
        method: "post",
        data: { record: 1 },
        success: function (data) {
            $('.allContent-section').html(data);
        }
    });
}

function ChangeOrderStatus(id) {
    $.ajax({
        url: "./controller/updateOrderStatus.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            alert('Order Status updated successfully');
            $('form').trigger('reset');
            showOrders();
        }
    });
}

function ChangePay(id) {
    $.ajax({
        url: "./controller/updatePayStatus.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            alert('Payment Status updated successfully');
            $('form').trigger('reset');
            showOrders();
        }
    });
}


//add product data
function addItems() {
    var p_name = $('#p_name').val();
    var p_desc = $('#p_desc').val();
    var p_price = $('#p_price').val();
    var category = $('#category').val();
    var upload = $('#upload').val();
    var file = $('#file')[0].files[0];

    var fd = new FormData();
    fd.append('p_name', p_name);
    fd.append('p_desc', p_desc);
    fd.append('p_price', p_price);
    fd.append('category', category);
    fd.append('file', file);
    fd.append('upload', upload);
    $.ajax({
        url: "./controller/addItemController.php",
        method: "post",
        data: fd,
        processData: false,
        contentType: false,
        success: function (data) {
            alert('Product Added successfully.');
            $('form').trigger('reset');
            showProductItems();
        }
    });
}

//edit product data
function itemEditForm(id) {
    $.ajax({
        url: "./adminView/editItemForm.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            $('.allContent-section').html(data);
        }
    });
}

// Função para criar alertas personalizados
function showCustomAlert(message, type = 'info') {
    let container = document.querySelector('.custom-alert-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'custom-alert-container';
        document.body.appendChild(container);
    }

    const alert = document.createElement('div');
    alert.className = `custom-alert ${type}`;

    let icon = '';
    switch (type) {
        case 'success':
            icon = '<i class="fa-solid fa-check"></i>';
            break;
        case 'error':
            icon = '<i class="fa-solid fa-xmark"></i>';
            break;
        default:
            icon = '<i class="fa-solid fa-info"></i>';
    }

    alert.innerHTML = `
        <div class="alert-content">
            <span class="alert-icon">${icon}</span>
            <span>${message}</span>
        </div>
        <button class="alert-close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;

    container.appendChild(alert);

    const closeBtn = alert.querySelector('.alert-close');
    closeBtn.addEventListener('click', () => {
        alert.remove();
    });

    setTimeout(() => {
        if (alert && alert.parentElement) {
            alert.remove();
        }
    }, 3000);
}

// Substituições dos alerts
function addItems() {
    var p_name = $('#p_name').val();
    var p_desc = $('#p_desc').val();
    var p_price = $('#p_price').val();
    var category = $('#category').val();
    var upload = $('#upload').val();
    var file = $('#file')[0].files[0];

    var fd = new FormData();
    fd.append('p_name', p_name);
    fd.append('p_desc', p_desc);
    fd.append('p_price', p_price);
    fd.append('category', category);
    fd.append('file', file);
    fd.append('upload', upload);
    $.ajax({
        url: "./controller/addItemController.php",
        method: "post",
        data: fd,
        processData: false,
        contentType: false,
        success: function (data) {
            showCustomAlert('Produto adicionado com sucesso!', 'success');
            $('form').trigger('reset');
            showProductItems();
        }
    });
}

function updateItems() {
    // ... resto do código ...
    $.ajax({
        url: './controller/updateItemController.php',
        method: 'post',
        data: fd,
        processData: false,
        contentType: false,
        success: function (data) {
            showCustomAlert('Dados atualizados com sucesso!', 'success');
            $('form').trigger('reset');
            showProductItems();
        }
    });
}

function itemDelete(id) {
    $.ajax({
        url: "./controller/deleteItemController.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Item excluído com sucesso!', 'success');
            $('form').trigger('reset');
            showProductItems();
        }
    });
}

function cartDelete(id) {
    $.ajax({
        url: "./controller/deleteCartController.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Item removido do carrinho!', 'success');
            $('form').trigger('reset');
            showMyCart();
        }
    });
}

function categoryDelete(id) {
    $.ajax({
        url: "./controller/catDeleteController.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Categoria excluída com sucesso!', 'success');
            $('form').trigger('reset');
            showCategory();
        }
    });
}

function sizeDelete(id) {
    $.ajax({
        url: "./controller/deleteSizeController.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Tamanho excluído com sucesso!', 'success');
            $('form').trigger('reset');
            showSizes();
        }
    });
}

function variationDelete(id) {
    $.ajax({
        url: "./controller/deleteVariationController.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Variação excluída com sucesso!', 'success');
            $('form').trigger('reset');
            showProductSizes();
        }
    });
}

function updateVariations() {
    var v_id = $('#v_id').val();
    var product = $('#product').val();
    var size = $('#size').val();
    var qty = $('#qty').val();
    var fd = new FormData();
    fd.append('v_id', v_id);
    fd.append('product', product);
    fd.append('size', size);
    fd.append('qty', qty);

    $.ajax({
        url: './controller/updateVariationController.php',
        method: 'post',
        data: fd,
        processData: false,
        contentType: false,
        success: function (data) {
            showCustomAlert('Atualizado com sucesso!', 'success');
            $('form').trigger('reset');
            showProductSizes();
        }
    });
}

function ChangeOrderStatus(id) {
    $.ajax({
        url: "./controller/updateOrderStatus.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Status do pedido atualizado com sucesso!', 'success');
            $('form').trigger('reset');
            showOrders();
        }
    });
}

function ChangePay(id) {
    $.ajax({
        url: "./controller/updatePayStatus.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Status do pagamento atualizado com sucesso!', 'success');
            $('form').trigger('reset');
            showOrders();
        }
    });
}

function removeFromWish(id) {
    $.ajax({
        url: "./controller/removeFromWishlist.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Removido da lista de desejos!', 'success');
        }
    });
}

function addToWish(id) {
    $.ajax({
        url: "./controller/addToWishlist.php",
        method: "post",
        data: { record: id },
        success: function (data) {
            showCustomAlert('Adicionado à lista de desejos!', 'success');
        }
    });
}
