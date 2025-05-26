/**
 * VocaBlitz UI Components
 * Datei: assets/js/ui.js
 */

class UIManager {
    constructor() {
        this.modals = new Map();
        this.alerts = [];
    }

    init() {
        this.setupModals();
        this.setupForms();
        this.setupResponsiveNavigation();
    }

    setupModals() {
        // Modal Event Listeners
        document.querySelectorAll('.modal-close').forEach(closeBtn => {
            closeBtn.addEventListener('click', (e) => {
                const modal = e.target.closest('.modal');
                this.hideModal(modal.id);
            });
        });

        // Overlay click zum Schließen
        document.getElementById('overlay').addEventListener('click', () => {
            this.hideAllModals();
        });

        // ESC-Taste zum Schließen
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideAllModals();
            }
        });
    }

    setupForms() {
        // Login Form
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleLogin(new FormData(e.target));
        });

        // Register Form
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleRegister(new FormData(e.target));
        });

        // Modal switching
        document.getElementById('showRegisterBtn').addEventListener('click', () => {
            this.hideModal('loginModal');
            this.showModal('registerModal');
        });

        document.getElementById('showLoginBtn').addEventListener('click', () => {
            this.hideModal('registerModal');
            this.showModal('loginModal');
        });
    }

    setupResponsiveNavigation() {
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');

        navToggle?.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // Modal Management
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        const overlay = document.getElementById('overlay');
        
        if (modal) {
            modal.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        const overlay = document.getElementById('overlay');
        
        if (modal) {
            modal.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    hideAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('active');
        });
        document.getElementById('overlay').classList.remove('active');
        document.body.style.overflow = '';
    }

    // User Menu
    toggleUserMenu() {
        const userMenu = document.querySelector('.user-menu');
        userMenu?.classList.toggle('active');
    }

    // List Selector
    showListSelector(lists) {
        // Modal HTML erstellen
        const modalHTML = `
            <div class="modal active" id="listSelectorModal">
                <div class="modal-content" style="max-width: 600px;">
                    <div class="modal-header">
                        <h2 class="modal-title">Vokabelliste auswählen</h2>
                        <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="list-grid" style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                            ${lists.map(list => `
                                <div class="list-item" data-list-id="${list.id}" style="
                                    padding: 1rem; 
                                    border: 2px solid #e5e7eb; 
                                    border-radius: 8px; 
                                    cursor: pointer; 
                                    transition: all 0.3s;
                                ">
                                    <h3 style="margin: 0 0 0.5rem 0; color: var(--primary);">${list.name}</h3>
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">${list.description || 'Keine Beschreibung'}</p>
                                    <div style="margin-top: 0.5rem; color: #888; font-size: 0.8rem;">
                                        📚 ${list.word_count || 0} Vokabeln
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Abbrechen</button>
                        <button class="btn btn-primary" id="modalSelectListBtn" disabled>Liste auswählen</button>
                    </div>
                </div>
            </div>
        `;
        
        // Modal zum DOM hinzufügen
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Event-Listener für Listenauswahl
        let selectedListId = null;
        document.querySelectorAll('.list-item').forEach(item => {
            item.addEventListener('click', function() {
                // Alle anderen deselektieren
                document.querySelectorAll('.list-item').forEach(i => {
                    i.style.borderColor = '#e5e7eb';
                    i.style.backgroundColor = 'white';
                });
                
                // Diese selektieren
                this.style.borderColor = 'var(--primary)';
                this.style.backgroundColor = 'rgba(67, 97, 238, 0.05)';
                
                selectedListId = this.dataset.listId;
                console.log('Liste ausgewählt:', selectedListId); // Debug

                const selectBtn = document.getElementById('modalSelectListBtn');
                if (selectBtn) {
                    selectBtn.disabled = false;
                    selectBtn.className = 'btn btn-primary';
                    selectBtn.style.opacity = '1';
                    selectBtn.style.cursor = 'pointer';
                    selectBtn.style.backgroundColor = '#4361ee';
                    selectBtn.style.color = 'white';
                    console.log('Button aktiviert'); // Debug
                } else {
                    console.error('Button modalSelectListBtn nicht gefunden!');
                }
            });
        });
        
        // "Liste auswählen" Button
        document.getElementById('modalSelectListBtn').addEventListener('click', function() {
            console.log('Button geklickt, selectedListId:', selectedListId); // Debug
            if (selectedListId) {
                console.log('Liste wird geladen:', selectedListId); // Debug
                window.App.selectVocabularyList(selectedListId);
                
                // Modal entfernen
                const modal = document.getElementById('listSelectorModal');
                if (modal) {
                    modal.remove();
                }
            }
        });
    }

    // Alert System
    showAlert(message, type = 'info', duration = 5000) {
        const alertId = 'alert-' + Date.now();
        const alertHtml = `
            <div class="alert alert-${type}" id="${alertId}">
                <div class="alert-content">
                    <i class="fas fa-${this.getAlertIcon(type)}"></i>
                    <span>${message}</span>
                </div>
                <button class="alert-close" onclick="UI.hideAlert('${alertId}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        // Alert Container erstellen falls nicht vorhanden
        let container = document.getElementById('alert-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'alert-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1100;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }

        container.insertAdjacentHTML('beforeend', alertHtml);

        // Auto-hide
        if (duration > 0) {
            setTimeout(() => this.hideAlert(alertId), duration);
        }

        return alertId;
    }

    hideAlert(alertId) {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }
    }

    getAlertIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Form Handling
    async handleLogin(formData) {
        const submitBtn = document.querySelector('#loginForm button[type="submit"]') || 
                  document.querySelector('button[form="loginForm"]') ||
                  document.querySelector('.modal button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        try {
            submitBtn.textContent = 'Anmelden...';
            submitBtn.disabled = true;

            // CSRF-Token aus Formular holen
            const csrfToken = formData.get('csrf_token');

            const response = await fetch('api/user/login.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Erfolgreich angemeldet!', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                this.showAlert(result.error || 'Anmeldung fehlgeschlagen', 'error');
            }
        } catch (error) {
            this.showAlert('Netzwerkfehler bei der Anmeldung', 'error');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }

    async handleRegister(formData) {
        const submitBtn = document.querySelector('#registerForm button[type="submit"]') || 
                  document.querySelector('button[form="registerForm"]');
        const originalText = submitBtn.textContent;
        
        try {
            submitBtn.textContent = 'Registrieren...';
            submitBtn.disabled = true;

            // CSRF-Token aus Formular holen
            const csrfToken = formData.get('csrf_token');

            const response = await fetch('api/user/register.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Erfolgreich registriert!', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                if (result.errors && Array.isArray(result.errors)) {
                    result.errors.forEach(error => this.showAlert(error, 'error'));
                } else {
                    this.showAlert(result.error || 'Registrierung fehlgeschlagen', 'error');
                }
            }
        } catch (error) {
            this.showAlert('Netzwerkfehler bei der Registrierung', 'error');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }

    // Loading States
    showLoading(elementId, message = 'Lädt...') {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p>${message}</p>
                </div>
            `;
        }
    }

    hideLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            const loading = element.querySelector('.loading');
            if (loading) {
                loading.remove();
            }
        }
    }

    // Feedback System
    showFeedback(isCorrect, message, emoji) {
        const overlay = document.getElementById('feedbackOverlay');
        const emojiEl = document.getElementById('feedbackEmoji');
        const messageEl = document.getElementById('feedbackMessage');
        
        emojiEl.textContent = emoji;
        messageEl.textContent = message;
        
        overlay.classList.add('active');
        document.getElementById('overlay').classList.add('active');
    }

    hideFeedback() {
        document.getElementById('feedbackOverlay').classList.remove('active');
        document.getElementById('overlay').classList.remove('active');
    }
}

// UI global verfügbar machen
window.UI = new UIManager();