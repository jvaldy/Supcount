// ===================
// Gestion des onglets
// ===================
function showTab(tabId) {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    // Optionnel : scroll vers le haut sur mobile
    window.scrollTo({ top: 0, behavior: "smooth" });
}
document.querySelectorAll('.btn-group .btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});

// ===================
// Auth & Initialisation
// ===================
const token = localStorage.getItem('jwt');
const groupId = window.groupId || parseInt(window.location.pathname.match(/\d+$/)[0]); // fallback si groupId pas injecté
let rawExpenses = [];
let userId = null;

if (!token) {
    window.location.href = '/login';
}

// ===================
// Loader utilitaire
// ===================
function showLoader(id) {
    document.getElementById(id).classList.remove('d-none');
}
function hideLoader(id) {
    document.getElementById(id).classList.add('d-none');
}

// ===================
// Déconnexion
// ===================
document.getElementById('logout').addEventListener('click', () => {
    localStorage.removeItem('jwt');
    window.location.href = '/login';
});

// ===================
// Chargement infos groupe
// ===================
function handleAuthError(response) {
    if (response.status === 401 || response.status === 403) {
        localStorage.removeItem('jwt');
        window.location.href = '/login';
        return true;
    }
    return false;
}

// Soumission modale "participants"
document.getElementById('edit-participants-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const expenseId = document.getElementById('edit-participants-expense-id').value;
    const participantIds = [...document.querySelectorAll('input[name="participants"]:checked')].map(cb => parseInt(cb.value));
    const res = await fetch(`http://localhost:49162/api/expenses/${expenseId}/participants`, {
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ concerned_user_ids: participantIds })
    });
    const msg = await res.json();
    alert(msg.message || msg.error);
    if (res.ok) {
        bootstrap.Modal.getInstance(document.getElementById('editParticipantsModal')).hide();
        loadGroupPage();
    }
});

// Soumission modale "dépense"
document.getElementById('edit-expense-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('edit-expense-id').value;
    const title = document.getElementById('edit-expense-title').value.trim();
    const amount = document.getElementById('edit-expense-amount').value;
    const category = document.getElementById('edit-expense-category').value.trim();

    const res = await fetch(`http://localhost:49162/api/expenses/${id}/edit`, {
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ title, amount, category })
    });
    const msg = await res.json();
    alert(msg.message || msg.error);
    if (res.ok) {
        bootstrap.Modal.getInstance(document.getElementById('editExpenseModal')).hide();
        loadGroupPage();
    }
});

// Chargement global au démarrage
async function loadGroupPage() {
    showLoader('loader-group-info');
    try {
        const [group, role, members, expenses, balances, profile] = await Promise.all([
            fetch(`http://localhost:49162/api/groups/${groupId}`, { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
            fetch(`http://localhost:49162/api/groups/${groupId}/role`, { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
            fetch(`http://localhost:49162/api/groups/${groupId}/members`, { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
            fetch(`http://localhost:49162/api/groups/${groupId}/expenses`, { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
            fetch(`http://localhost:49162/api/groups/${groupId}/balances`, { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
            fetch('http://localhost:49162/api/profile', { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json())
        ]);
        userId = profile.id;
        localStorage.setItem('userId', userId);

        // Infos du groupe
        document.getElementById('group-info').innerHTML = `
          <h2 class="section-title">${group.name}</h2>
          <p>Créé le : ${group.createdAt}</p>
          <p>Créé par : ${group.createdBy.username} (${group.createdBy.email})</p>
        `;
        document.getElementById('group-role').innerHTML = `<strong>Mon rôle :</strong> ${role.role}`;

        // Membres
        document.getElementById('group-members').innerHTML = `
          <h5>Membres :</h5>
          <ul class="list-group mb-2">
            ${members.map(m => {
                const isCreator = m.role === 'créateur';
                const deleteButton = isCreator ? '' : `<button class="btn btn-sm btn-outline-danger ms-2" onclick="removeMember(${m.id})">❌</button>`;
                return `<li class="list-group-item d-flex align-items-center justify-content-between">
                  <span><strong>${m.username}</strong> (${m.email}) – <em>${m.role}</em></span>
                  ${deleteButton}
                </li>`;
            }).join('')}
          </ul>
        `;

        // Ajouter un membre
        document.getElementById('add-member-btn').onclick = async function () {
            const input = document.getElementById('new-member-id');
            const value = input.value.trim();
            const result = document.getElementById('add-member-result');
            if (!value) {
                result.textContent = 'Veuillez entrer un ID ou un email.';
                return;
            }
            const isEmail = value.includes('@');
            const body = isEmail ? { email: value } : { user_id: parseInt(value) };

            fetch(`http://localhost:49162/api/groups/${groupId}/add-member`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(body)
            })
                .then(async res => {
                    if (handleAuthError(res)) return;
                    const data = await res.json();
                    result.textContent = data.message || data.error;
                    if (res.ok) loadGroupPage();
                })
                .catch(() => {
                    result.textContent = "Erreur lors de l’ajout du membre.";
                });
        };

        


        // Dépenses
        rawExpenses = expenses;
        renderExpenses(expenses, members);

        // Soldes
        const balanceList = Object.entries(balances).map(([uid, solde]) => {
            const member = members.find(m => m.id == uid);
            const name = member ? member.username : `Utilisateur #${uid}`;
            return `<li class="list-group-item">${name} : ${parseFloat(solde).toFixed(2)} €</li>`;
        }).join('');
        document.getElementById('group-balances').innerHTML = balanceList;

        // Select remboursements
        const settlementSelect = document.getElementById('settlement-select');
        if (expenses.length === 0) {
            settlementSelect.innerHTML = `<option disabled>Aucune dépense</option>`;
        } else {
            settlementSelect.innerHTML = `<option disabled selected>Choisir une dépense</option>`;
            expenses.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = `${e.title} – ${e.amount} €`;
                settlementSelect.appendChild(opt);
            });
            settlementSelect.onchange = () => loadSettlements(settlementSelect.value);
        }

        // Chat
        loadChatMessages();

    } catch (err) {
        alert('Erreur lors du chargement du groupe.');
        window.location.reload();
    } finally {
        hideLoader('loader-group-info');
    }
}

// ===================
// Gestion des membres
// ===================
function removeMember(memberId) {
    if (!confirm("Confirmer la suppression de ce membre du groupe ?")) return;
    fetch(`http://localhost:49162/api/groups/${groupId}/remove-member`, {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ user_id: memberId })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message || data.error);
        loadGroupPage();
    })
    .catch(() => alert("Erreur lors de la suppression du membre."));
}

// ===================
// Dépenses
// ===================
function renderExpenses(expenses, members) {
    const list = document.getElementById('group-expenses');
    if (!expenses.length) {
        list.innerHTML = '<li class="list-group-item">Aucune dépense.</li>';
        return;
    }
    list.innerHTML = expenses.map(e => {
        const receiptLink = e.receipt
            ? `<br><a href="http://localhost:49162/uploads/receipts/${e.receipt}" target="_blank">📄 Voir le justificatif</a>`
            : '';
        return `
          <li class="list-group-item">
            <strong>${e.title}</strong> – ${e.amount} €<br>
            Catégorie : ${e.category} | Date : ${e.date}<br>
            Payé par : ${e.paidBy}<br>
            Participants : ${e.concernedUsers.join(', ')}
            ${receiptLink}
            <br>
            <button class="btn btn-sm btn-outline-secondary mt-2" onclick="openEditExpenseModal(${e.id}, '${e.title}', '${e.amount}', '${e.category}')">✏️ Modifier infos</button>
            <button class="btn btn-sm btn-outline-primary mt-2" onclick="editParticipants(${e.id})">✏️ Participants</button>
            <button class="btn btn-sm btn-outline-danger mt-2" onclick="deleteExpense(${e.id})">🗑️ Supprimer</button>
          </li>
        `;
    }).join('');
}

// Tri des dépenses
document.getElementById('sort-expenses').addEventListener('change', e => {
    const by = e.target.value;
    const sorted = [...rawExpenses];
    sorted.sort((a, b) => {
        if (by === 'amount') return b.amount - a.amount;
        if (by === 'paidBy') return a.paidBy.localeCompare(b.paidBy);
        if (by === 'date') return new Date(b.date) - new Date(a.date);
        return 0;
    });
    renderExpenses(sorted);
});

// Ajout d'une dépense
document.getElementById('expense-form').addEventListener('submit', e => {
    e.preventDefault();
    const form = new FormData();
    form.append('title', document.getElementById('expense-title').value.trim());
    form.append('amount', document.getElementById('expense-amount').value);
    form.append('date', document.getElementById('expense-date').value);
    form.append('category', document.getElementById('expense-category').value.trim());
    form.append('group_id', groupId);
    const users = document.getElementById('expense-users').value
        .split(',').map(id => parseInt(id.trim())).filter(Boolean);
    form.append('concerned_user_ids', JSON.stringify(users));
    const file = document.getElementById('expense-receipt').files[0];
    if (file) form.append('receipt', file);

    fetch('http://localhost:49162/api/expenses', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token },
        body: form
    }).then(async res => {
        const msg = await res.json();
        document.getElementById('expense-result').textContent = msg.message || msg.error;
        if (res.ok) loadGroupPage();
    });
});

function deleteExpense(expenseId) {
    if (!confirm("Supprimer définitivement cette dépense ?")) return;
    fetch(`http://localhost:49162/api/expenses/${expenseId}`, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(async res => {
        const msg = await res.json();
        alert(msg.message || msg.error);
        if (res.ok) loadGroupPage();
    });
}

// ===================
// Edition participants/dépenses (modals à compléter)
// ===================
window.editParticipants = async function (expenseId) {
    const members = await fetch(`http://localhost:49162/api/groups/${groupId}/members`, {
        headers: { 'Authorization': 'Bearer ' + token }
    }).then(r => r.json());

    const expense = await fetch(`http://localhost:49162/api/expenses/${expenseId}`, {
        headers: { 'Authorization': 'Bearer ' + token }
    }).then(r => r.json());

    const list = members.map(user => {
        const checked = expense.concernedUsers.includes(user.id) ? 'checked' : '';
        return `<label class="form-check">
          <input type="checkbox" name="participants" value="${user.id}" class="form-check-input" ${checked}>
          ${user.username} (${user.email})
        </label>`;
    }).join('');

    document.getElementById('edit-participants-list').innerHTML = list;
    document.getElementById('edit-participants-expense-id').value = expenseId;
    new bootstrap.Modal(document.getElementById('editParticipantsModal')).show();
};
window.openEditExpenseModal = function (id, title, amount, category) {
    document.getElementById('edit-expense-id').value = id;
    document.getElementById('edit-expense-title').value = title;
    document.getElementById('edit-expense-amount').value = amount;
    document.getElementById('edit-expense-category').value = category;
    new bootstrap.Modal(document.getElementById('editExpenseModal')).show();
};

// ===================
// Export PDF / CSV
// ===================
window.downloadPdf = function () {
    fetch(`http://localhost:49162/api/groups/${groupId}/export/pdf`, {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `depenses_groupe_${groupId}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
    });
};
window.downloadCsv = function () {
    fetch(`http://localhost:49162/api/groups/${groupId}/export/csv`, {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `depenses_groupe_${groupId}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
    });
};

// ===================
// Remboursements par dépense
// ===================
function loadSettlements(expenseId) {
    fetch(`http://localhost:49162/api/expenses/${expenseId}/settlements`, {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById('settlement-results');
        if (!data.settlements?.length) {
            container.innerHTML = '<li class="list-group-item">Aucun remboursement à effectuer pour cette dépense.</li>';
            return;
        }
        const settlementsHTML = data.settlements.map(s => {
            const fromName = s.from.username || `Utilisateur #${s.from.id}`;
            const toName = s.to.username || `Utilisateur #${s.to.id}`;
            let validationHTML = '';
            const currentUserId = parseInt(localStorage.getItem('userId'));
            const isPayeur = currentUserId === s.to.id;
            if (s.validated) {
                validationHTML = `<span class="text-success">✔️ Remboursé</span>`;
            } else if (isPayeur) {
                validationHTML = `<button class="btn btn-sm btn-outline-success" onclick="validateReimbursement(${data.expense.id}, ${s.from.id})"
                    data-expense="${data.expense.id}" data-debtor="${s.from.id}">Valider</button>`;
            } else {
                validationHTML = `<span class="text-warning">⏳ En attente</span>`;
            }
            return `<li class="list-group-item">${fromName} doit <strong>${s.amount.toFixed(2)} €</strong> à ${toName} – ${validationHTML}</li>`;
        }).join('');
        container.innerHTML = `<ul class="list-group">${settlementsHTML}</ul>`;
    });
}

window.validateReimbursement = function (expenseId, debtorId) {
    fetch(`http://localhost:49162/api/expenses/${expenseId}/validate/${debtorId}`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        loadSettlements(expenseId);
    })
    .catch(() => alert("Erreur lors de la validation du remboursement."));
};

// ===================
// Messagerie en temps réel (Mercure ou polling)
// ===================
function loadChatMessages() {
    fetch(`http://localhost:49162/api/groups/${groupId}/messages`, {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.json())
    .then(messages => {
        const chatBox = document.getElementById('chat-messages');
        if (!messages.length) {
            chatBox.innerHTML = "<em>Aucun message dans ce groupe.</em>";
        } else {
            chatBox.innerHTML = messages.map(m =>
                `<div><strong>${m.author}</strong> <span style="color:#888;font-size:0.9em">[${m.createdAt}]</span><br>${m.content}</div>`
            ).join('<hr style="margin:6px 0;">');
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    });
}

// Envoi de message
document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const content = input.value.trim();
    if (!content) return;

    fetch(`http://localhost:49162/api/groups/${groupId}/messages`, {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ content })
    })
    .then(res => res.json())
    .then(() => {
        input.value = '';
        loadChatMessages(); // instantanéité : recharge
    });
});


// Démarrage du chargement initial
loadGroupPage();
