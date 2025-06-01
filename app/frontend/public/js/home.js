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
    .then(data => alert(data.message || data.error));
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
                    <strong>${group.name}</strong>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="window.location.href='/group/${group.id}'">Voir</button>
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="viewMembers(${group.id})">Membres</button>
                        <button class="btn btn-sm btn-outline-info me-1" onclick="checkRole(${group.id})">Mon rôle</button>
                        <button class="btn btn-sm btn-outline-danger me-1" onclick="deleteGroup(${group.id})">Supprimer</button>
                        <button class="btn btn-sm btn-outline-success" onclick="loadSettlements(${group.id})">Remboursements</button>
                    </div>
                </div>
                <div id="group-${group.id}-members" class="mt-2"></div>
                <div id="group-${group.id}-role" class="mt-1 text-muted"></div>
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

// Voir les remboursements
function loadSettlements(groupId) {
    fetch(`http://localhost:49162/api/groups/${groupId}/expenses`, {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.json())
    .then(expenses => {
        expenses.forEach(exp => {
            fetch(`http://localhost:49162/api/expenses/${exp.id}/settlements`, {
                headers: { 'Authorization': 'Bearer ' + token }
            })
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById(`group-${groupId}-settlements`);
                container.innerHTML += `<h5>Remboursements pour ${exp.title}</h5>`;

                if (!data.settlements?.length) {
                    container.innerHTML += `<p>Aucun remboursement.</p>`;
                    return;
                }

                const items = data.settlements.map(s => {
                    const from = s.from;
                    const to = s.to;
                    const ribInfo = (from.id === data.current_user_id && to.rib)
                        ? `<br><small>💳 RIB de ${to.username} : ${to.rib}</small>` : '';
                    return `<li>${from.username} doit <strong>${s.amount.toFixed(2)} €</strong> à ${to.username}${ribInfo}</li>`;
                }).join('');

                container.innerHTML += `<ul>${items}</ul>`;
            });
        });
    });
}

// Statistiques
function loadStatistics() {
    fetch('http://localhost:49162/api/statistics', {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.json())
    .then(stats => {
        document.getElementById('stats-total').innerHTML = `<strong>Total :</strong> ${parseFloat(stats.total).toFixed(2)} €`;

        document.getElementById('stats-by-category').innerHTML =
            `<h6>Par catégorie :</h6><ul>${
                Object.entries(stats.byCategory).map(([k, v]) =>
                    `<li>${k} : ${parseFloat(v).toFixed(2)} €</li>`
                ).join('')
            }</ul>`;

        document.getElementById('stats-by-month').innerHTML =
            `<h6>Par mois :</h6><ul>${
                Object.entries(stats.byMonth).map(([k, v]) =>
                    `<li>${k} : ${parseFloat(v).toFixed(2)} €</li>`
                ).join('')
            }</ul>`;

        document.getElementById('stats-by-group').innerHTML =
            `<h6>Par groupe :</h6><ul>${
                Object.entries(stats.byGroup).map(([k, v]) =>
                    `<li>${k} : ${parseFloat(v).toFixed(2)} €</li>`
                ).join('')
            }</ul>`;
    })
    .catch(() => {
        document.getElementById('stats-total').textContent = 'Erreur de chargement des stats.';
    });
}
