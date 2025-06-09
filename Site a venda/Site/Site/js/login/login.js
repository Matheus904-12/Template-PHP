//login.js

const inputs = document.querySelectorAll(".input-field");
const toggle_btn = document.querySelectorAll(".toggle");
const main = document.querySelector("main");
const bullets = document.querySelectorAll(".bullets span");
const images = document.querySelectorAll(".image");

inputs.forEach((inp) => {
    inp.addEventListener("focus", () => {
        inp.classList.add("active");
    });
    inp.addEventListener("blur", () => {
        if (inp.value != "") return;
        inp.classList.remove("active");
    });
});

toggle_btn.forEach((btn) => {
    btn.addEventListener("click", () => {
        main.classList.toggle("sign-up-mode");
    });
});

function moveSlider() {
    let index = this.dataset.value;

    let currentImage = document.querySelector(`.img-${index}`);
    images.forEach((img) => img.classList.remove("show"));
    currentImage.classList.add("show");

    const textSlider = document.querySelector(".text-group");
    textSlider.style.transform = `translateY(${-(index - 1) * 2.2}rem)`;

    bullets.forEach((bull) => bull.classList.remove("active"));
    this.classList.add("active");
}

bullets.forEach((bullet) => {
    bullet.addEventListener("click", moveSlider);
});

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('signup');
    const steps = Array.from(form.getElementsByClassName('step'));
    let currentStep = 0;

    // Adiciona barra de progresso ao formulário
    const progressBar = document.createElement('div');
    progressBar.className = 'progress-bar';
    const progress = document.createElement('div');
    progress.className = 'progress';
    progressBar.appendChild(progress);
    form.insertBefore(progressBar, form.firstChild);

    // Atualiza a barra de progresso
    function updateProgress() {
        const progressPercentage = ((currentStep + 1) / steps.length) * 100;
        progress.style.width = `${progressPercentage}%`;
    }

    // Mostra o passo atual
    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            step.classList.remove('active');
            if (index === stepIndex) {
                step.classList.add('active');
            }
        });
        updateProgress();
    }

    // Valida os campos do passo
    function validateStep(step) {
        const inputs = step.querySelectorAll('input[required]');
        let isValid = true;

        inputs.forEach(input => {
            input.classList.remove('input-error');
            const errorMessage = input.nextElementSibling;
            if (errorMessage && errorMessage.classList.contains('error-message')) {
                errorMessage.remove();
            }

            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('input-error');
                const error = document.createElement('div');
                error.className = 'error-message';
                error.textContent = 'Este campo é obrigatório';
                input.insertAdjacentElement('afterend', error);
            } else if (input.type === 'email' && !validateEmail(input.value)) {
                isValid = false;
                input.classList.add('input-error');
                const error = document.createElement('div');
                error.className = 'error-message';
                error.textContent = 'Email inválido';
                input.insertAdjacentElement('afterend', error);
            } else if (input.name === 'telefone' && !validatePhone(input.value)) {
                isValid = false;
                input.classList.add('input-error');
                const error = document.createElement('div');
                error.className = 'error-message';
                error.textContent = 'Telefone inválido';
                input.insertAdjacentElement('afterend', error);
            }
        });

        return isValid;
    }

    // Validação de email
    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Validação de telefone (exemplo com expressão regular)
    function validatePhone(phone) {
        return /^\(?\d{2}\)?\s?\d{4,5}-?\d{4}$/.test(phone);
    }
    

    // Lida com o clique no botão "Próximo"
    form.querySelectorAll('.next').forEach(button => {
        button.addEventListener('click', () => {
            const currentStepElement = steps[currentStep];
            if (validateStep(currentStepElement)) {
                currentStep++;
                showStep(currentStep);
            }
        });
    });

    // Lida com o clique no botão "Voltar"
    form.querySelectorAll('.prev').forEach(button => {
        button.addEventListener('click', () => {
            currentStep--;
            showStep(currentStep);
        });
    });

    // Lida com o envio do formulário
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Desabilita o botão de enviar
        const submitButton = e.submitter;
        submitButton.disabled = true;

        let formData = new FormData(form);

        fetch(form.action, {
            method: form.method,
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                if (response.status === 422) { // Erro de validação
                    return response.json().then(data => {
                        // Exibe mensagens de erro na página
                        Object.keys(data.errors).forEach(field => {
                            const input = form.querySelector(`[name="${field}"]`);
                            const error = document.createElement('div');
                            error.className = 'error-message';
                            error.textContent = data.errors[field][0];
                            input.insertAdjacentElement('afterend', error);
                        });
                    });
                } else {
                    throw new Error('Erro na resposta do servidor: ' + response.status);
                }
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (data.otpSent) {
                    currentStep++;
                    showStep(currentStep);
                } else {
                    window.location.href = "index.php";
                }
            } else {
                // Exibe mensagens de erro na página
                alert(data.message || 'Erro ao cadastrar. Tente novamente.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            if (error.message.startsWith('Erro na resposta do servidor')) {
                alert('Erro no servidor. Tente novamente mais tarde.');
            } else {
                alert('Erro ao cadastrar. Verifique sua conexão e tente novamente.');
            }
        })
        .finally(() => {
            // Reabilita o botão de enviar
            submitButton.disabled = false;
        });
    });

    showStep(currentStep);

    form.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', () => {
            input.classList.add('active');
        });

        input.addEventListener('blur', () => {
            if (!input.value) {
                input.classList.remove('active');
            }
        });
    });

    const cepInput = form.querySelector('input[name="cep"]');
    if (cepInput) {
        cepInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{5})(\d)/, '$1-$2');
            e.target.value = value.substring(0, 9);
        });
    }

    const telefoneInput = form.querySelector('input[name="telefone"]');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
            value = value.replace(/(\d)(\d{4})$/, '$1-$2');
            e.target.value = value.substring(0, 15);
        });
    }
});