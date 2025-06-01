document.getElementById('register-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = e.target;
    const resultElement = document.getElementById('register-result');
    resultElement.style.color = 'red'; // couleur par défaut pour les erreurs

    try {
        const res = await fetch('http://localhost:49162/api/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                email: form.email.value,
                username: form.username.value,
                password: form.password.value
            })
        });

        const data = await res.json();

        if (res.ok) {
            resultElement.style.color = 'green';
            resultElement.textContent = data.message || "Inscription réussie ! Redirection en cours...";
            // Redirection après 5 secondes
            setTimeout(() => {
                window.location.href = '/login';
            }, 5000);
        } else {
            resultElement.textContent = data.error || "Échec de l'inscription. Veuillez réessayer.";
        }

    } catch (error) {
        console.error("Erreur lors de l'inscription :", error);
        resultElement.textContent = "Une erreur s'est produite. Veuillez réessayer.";
    }
});
