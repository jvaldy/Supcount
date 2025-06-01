// 🔒 Si un jeton existe déjà, on redirige immédiatement vers l'accueil
const existingToken = localStorage.getItem('jwt');
if (existingToken) {
    window.location.href = '/';
}

// 🎯 Gestion du formulaire de connexion
document.getElementById('login-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const email = e.target.email.value;
    const password = e.target.password.value;
    const resultElement = document.getElementById('login-result');

    resultElement.style.color = 'red';
    resultElement.textContent = '⏳ Connexion en cours...';

    try {
        const res = await fetch('http://localhost:49162/api/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await res.json();

        if (res.ok && data.token) {
            localStorage.setItem('jwt', data.token);
            resultElement.style.color = 'green';
            resultElement.textContent = 'Connexion réussie ✅ Redirection...';
            setTimeout(() => {
                window.location.href = '/';
            }, 1500);
        } else {
            resultElement.textContent = data.message || 'Identifiants incorrects ❌';
        }

    } catch (err) {
        resultElement.textContent = 'Erreur de connexion.';
        console.error(err);
    }
});
