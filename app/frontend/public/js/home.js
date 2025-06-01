const token = localStorage.getItem('jwt');
let userId = null;

if (!token) {
    window.location.href = '/login';
} else {
    fetch('http://localhost:49162/api/profile', {
        method: 'GET',
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.ok ? res.json() : Promise.reject())
    .then(user => {
        userId = user.id;
        document.getElementById('user-info').textContent = `Connecté : ${user.username} (${user.email})`;
        displayRibIfExists(user);
        loadGroups();
        loadStatistics();
    })
    .catch(() => {
        localStorage.removeItem('jwt');
        window.location.href = '/login';
    });
}

// Déconnexion
document.getElementById('logout').addEventListener('click', () => {
    localStorage.removeItem('jwt');
    window.location.href = '/login';
});

// Créer un groupe
document.getElementById('create-group').addEventListener('click', () => {
    const name = document.getElementById('new-group-name').value.trim();
    if (!name) return alert("Veuillez saisir un nom de groupe.");

    fetch('http://localhost:49162/api/groups', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ name })
    })
    .then(res => res.ok ? res.json() : Promise.reject(res.json()))
    .then(() => {
        alert("Groupe créé !");
        document.getElementById('new-group-name').value = '';
        loadGroups();
    })
    .catch(async res => {
        const error = await res;
        alert(error.error || "Erreur lors de la création du groupe.");
    });
});

// Upload RIB
function uploadRib() {
    const rib = document.getElementById('rib-input').value.trim();
    if (!rib) return alert("Veuillez saisir un RIB.");

    fetch('http://localhost:49162/api/user/upload-rib', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ rib })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message || data.error);
        // 🔁 Recharger la page après succès
        if (!data.error) window.location.reload();
    });
}


// Affiche le RIB si présent
function displayRibIfExists(user) {
    const ribDisplay = document.getElementById('rib-display');
    if (user.rib && user.rib.trim() !== "") {
        ribDisplay.textContent = `RIB enregistré : ${user.rib}`;
    } else {
        ribDisplay.textContent = "Aucun RIB enregistré.";
    }
}

// Charge les groupes
function loadGroups() {
    fetch('http://localhost:49162/api/my-groups', {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.json())
    .then(groups => {
        const list = document.getElementById('group-list');
        list.innerHTML = '';

        if (!groups.length) {
            list.innerHTML = `<div class="text-muted">Aucun groupe trouvé.</div>`;
            return;
        }

        groups.forEach(group => {
            const item = document.createElement('div');
            item.className = 'list-group-item';

            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${group.name}</strong>
                        <div id="group-${group.id}-role" class="mt-1 text-muted small"></div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="window.location.href='/group/${group.id}'">Voir</button>
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="viewMembers(${group.id})">Membres</button>
                        <button class="btn btn-sm btn-outline-info me-1" onclick="checkRole(${group.id})">Mon rôle</button>
                        <button class="btn btn-sm btn-outline-danger me-1" onclick="deleteGroup(${group.id})">Supprimer</button>
                    </div>
                </div>
                <div id="group-${group.id}-members" class="mt-2"></div>
                <div id="group-${group.id}-settlements" class="mt-3"></div>
            `;

            list.appendChild(item);

            
        });
    })
    .catch(() => {
        document.getElementById('group-list').innerHTML = '<div class="text-danger">Erreur de chargement.</div>';
    });
}


// Supprime un groupe
function deleteGroup(groupId) {
    if (!confirm("Supprimer ce groupe ?")) return;

    fetch(`http://localhost:49162/api/groups/${groupId}`, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => {
        if (res.ok) {
            alert("Groupe supprimé.");
            loadGroups();
        } else {
            alert("Erreur lors de la suppression.");
        }
    });
}

// Voir les membres
function viewMembers(groupId) {
    fetch(`http://localhost:49162/api/groups/${groupId}/members`, {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.json())
    .then(members => {
        const container = document.getElementById(`group-${groupId}-members`);
        container.innerHTML = '<ul>' +
            members.map(m => `<li>${m.username} (${m.email})</li>`).join('') +
            '</ul>';
    })
    .catch(() => alert("Erreur de récupération des membres."));
}

// Voir son rôle
function checkRole(groupId) {
    fetch(`http://localhost:49162/api/groups/${groupId}/role`, {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById(`group-${groupId}-role`).textContent = `Rôle : ${data.role}`;
    })
    .catch(() => alert("Erreur de récupération du rôle."));
}



// Statistiques
function loadStatistics() {
    const loadingEl = document.getElementById('stats-loading');
    const container = document.getElementById('stats-container');
    loadingEl.style.display = 'block';
    container.style.display = 'none';

    fetch('http://localhost:49162/api/statistics', {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.json())
    .then(stats => {
        document.getElementById('stats-total').innerHTML =
            `<h6>Total :</h6><p class="fw-bold">${parseFloat(stats.total).toFixed(2)} €</p>`;

        document.getElementById('stats-by-category').innerHTML =
            `<h6>Par catégorie :</h6><ul class="mb-0">${
                Object.entries(stats.byCategory).map(([k, v]) =>
                    `<li>${k} : ${parseFloat(v).toFixed(2)} €</li>`).join('')
            }</ul>`;

        document.getElementById('stats-by-month').innerHTML =
            `<h6>Par mois :</h6><ul class="mb-0">${
                Object.entries(stats.byMonth).map(([k, v]) =>
                    `<li>${k} : ${parseFloat(v).toFixed(2)} €</li>`).join('')
            }</ul>`;

        document.getElementById('stats-by-group').innerHTML =
            `<h6>Par groupe :</h6><ul class="mb-0">${
                Object.entries(stats.byGroup).map(([k, v]) =>
                    `<li>${k} : ${parseFloat(v).toFixed(2)} €</li>`).join('')
            }</ul>`;

        loadingEl.style.display = 'none';
        container.style.display = 'flex';
    })
    .catch(() => {
        loadingEl.textContent = 'Erreur de chargement des statistiques.';
    });
}

