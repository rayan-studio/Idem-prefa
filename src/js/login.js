const form = document.querySelector('#login-form');
const message = document.getElementById("form-message");

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton.disabled) return;
    submitButton.disabled = true;
    message.style.display = "none";

    const formData = new FormData(form);

    try {
        const response = await axios.post(
            './action.php',
            formData,
            { timeout: 15000 }
        );

        if (response.data.success) {
            window.location.href = '../dashboard/dashboard.php';
        } else {
            message.style.display = "flex";
            message.innerText = response.data.message || "La connexion a échoué. Veuillez réessayer.";
        }

    } catch (error) {
        message.style.display = "flex";
        if (error.response) {
            message.innerText = error.response.data?.message || "Le serveur est indisponible. Veuillez réessayer.";
        } else {
            message.innerText = "Le serveur ne répond pas. Veuillez réessayer dans quelques instants.";
        }
    } finally {
        submitButton.disabled = false;
    }
});