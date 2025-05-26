/**
 * VocaBlitz Main Application
 * Datei: assets/js/app.js
 */

class VocaBlitzApp {
    constructor() {
        this.state = {
            currentUser: window.VocaBlitz.currentUser,
            isLoggedIn: window.VocaBlitz.isLoggedIn,
            currentList: null,
            vocabularies: [],
            currentVocabIndex: 0,
            learningMode: 'flashcard',
            direction: 'de-fr',
            stats: {
                correctCount: 0,
                wrongCount: 0,
                currentStreak: 0,
                bestStreak: 0,
                startTime: null,
                totalTimeSpent: 0
            },
            settings: {
                difficulty: 'easy',
                vocabCount: 10,
                showProgress: true,
                showAnimations: true
            }
        };
        
        this.init();
    }

    async init() {
        console.log('VocaBlitz App initializing...');
        
        // UI initialisieren
        UI.init();
        
        // Event Listeners
        this.setupEventListeners();
        
        // Gespeicherten Zustand laden
        this.loadState();
        
        // App-Content anzeigen
        this.showAppContent();
        
        console.log('VocaBlitz App initialized');
    }

    setupEventListeners() {
        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = e.target.dataset.section;
                if (section) {
                    this.showSection(section);
                }
            });
        });

        // List Selection
        document.getElementById('selectListBtn')?.addEventListener('click', () => {
            this.showListSelector();
        });

        document.getElementById('selectListBtnEmpty')?.addEventListener('click', () => {
            this.showListSelector();
        });

        // Learning Mode Buttons
        document.querySelectorAll('.mode-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.setLearningMode(btn.dataset.mode);
            });
        });

        // Direction Toggle
        document.querySelectorAll('.direction-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.setDirection(btn.dataset.direction);
            });
        });

        // Login/Register
        document.getElementById('loginBtn')?.addEventListener('click', () => {
            UI.showModal('loginModal');
        });

        // User Menu
        document.getElementById('userMenuToggle')?.addEventListener('click', () => {
            UI.toggleUserMenu();
        });
    }

    showAppContent() {
        document.getElementById('loadingContainer').classList.add('hidden');
        document.getElementById('appContent').classList.remove('hidden');
        
        // Initial section anzeigen
        this.showSection('trainer');
    }

    showSection(sectionName) {
        // Alle Sektionen verstecken
        document.querySelectorAll('.app-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Navigation aktualisieren
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        // Gewählte Sektion anzeigen
        const targetSection = document.getElementById(`${sectionName}-section`);
        if (targetSection) {
            targetSection.style.display = 'block';
        }
        
        // Navigation-Link aktivieren
        const activeLink = document.querySelector(`[data-section="${sectionName}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }

    async showListSelector() {
        try {
            const lists = await this.loadVocabularyLists();
            UI.showListSelector(lists);
        } catch (error) {
            console.error('Fehler beim Laden der Listen:', error);
            UI.showAlert('Fehler beim Laden der Vokabellisten', 'error');
        }
    }

    async loadVocabularyLists() {
        const response = await fetch(`${window.VocaBlitz.apiBase || 'api/'}get_vocabulary_lists.php`);
        if (!response.ok) {
            throw new Error('Fehler beim Laden der Listen');
        }
        return await response.json();
    }

    async selectVocabularyList(listId) {
        try {
            const response = await fetch(`api/get_vocabulary_items.php?list_id=${listId}`);
            if (!response.ok) {
                throw new Error('Fehler beim Laden der Vokabeln');
            }
            
            const data = await response.json();
            this.state.vocabularies = data;
            this.state.currentVocabIndex = 0;
            
            // Liste info aktualisieren
            this.updateCurrentListInfo();
            
            // Learning modes anzeigen
            this.showLearningModes();
            
            // Trainer benachrichtigen
            if (window.VocabularyTrainer) {
                window.VocabularyTrainer.reset();
                window.VocabularyTrainer.updateDisplay();
            }
            
            console.log(`Liste geladen: ${data.length} Vokabeln`);
            
        } catch (error) {
            console.error('Fehler beim Laden der Vokabelliste:', error);
            UI.showAlert('Fehler beim Laden der Vokabelliste', 'error');
        }
    }

    updateCurrentListInfo() {
        const info = document.getElementById('currentListInfo');
        const noList = document.getElementById('noListSelected');
        
        if (this.state.vocabularies.length > 0) {
            document.getElementById('currentListName').textContent = 'Aktuelle Liste';
            document.getElementById('currentListStats').textContent = `${this.state.vocabularies.length} Vokabeln`;
            
            info.classList.remove('hidden');
            noList.classList.add('hidden');
        } else {
            info.classList.add('hidden');
            noList.classList.remove('hidden');
        }
    }

    showLearningModes() {
        document.getElementById('learningModes').classList.remove('hidden');
        document.getElementById('noListSelected').classList.add('hidden');
    }

    setLearningMode(mode) {
        this.state.learningMode = mode;
        
        // UI aktualisieren
        document.querySelectorAll('.mode-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });
        
        document.querySelectorAll('.mode-container').forEach(container => {
            container.classList.toggle('active', container.id === `${mode}-mode`);
        });

        // Trainer aktualisieren
        if (window.VocabularyTrainer) {
            window.VocabularyTrainer.updateDisplay();
        }
    }

    setDirection(direction) {
        this.state.direction = direction;
        
        // UI aktualisieren
        document.querySelectorAll('.direction-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.direction === direction);
        });

        // Trainer aktualisieren
        if (window.VocabularyTrainer) {
            window.VocabularyTrainer.updateDisplay();
        }
    }

    // Methode zur Synchronisation des Vokabel-Index zwischen App und Trainer
    syncVocabularyIndex() {
        if (window.VocabularyTrainer) {
            this.state.currentVocabIndex = window.VocabularyTrainer.getCurrentVocabularyIndex();
        }
    }

    // API Methoden
    async processAnswer(vocabId, isCorrect) {
        if (this.state.isLoggedIn) {
            try {
                const formData = new FormData();
                formData.append('vocab_id', vocabId);
                formData.append('is_correct', isCorrect ? '1' : '0');
                
                const response = await fetch('api/process_answer.php', {
                    method: 'POST',
                    body: formData
                });
                
                return await response.json();
            } catch (error) {
                console.error('Fehler beim Verarbeiten der Antwort:', error);
            }
        }
        
        return { success: true, local: true };
    }

    // State Management
    saveState() {
        const stateToSave = {
            stats: this.state.stats,
            settings: this.state.settings,
            lastListId: this.state.currentList?.id
        };
        
        localStorage.setItem('vocaBlitzState', JSON.stringify(stateToSave));
    }

    loadState() {
        const savedState = localStorage.getItem('vocaBlitzState');
        if (savedState) {
            try {
                const parsed = JSON.parse(savedState);
                this.state.stats = { ...this.state.stats, ...parsed.stats };
                this.state.settings = { ...this.state.settings, ...parsed.settings };
                
                // Zuletzt genutzte Liste laden
                if (parsed.lastListId) {
                    this.selectVocabularyList(parsed.lastListId);
                }
            } catch (error) {
                console.error('Fehler beim Laden des gespeicherten Zustands:', error);
            }
        }
    }

    updateStats() {
        document.getElementById('correct-count').textContent = this.state.stats.correctCount;
        document.getElementById('current-streak').textContent = this.state.stats.currentStreak;
        document.getElementById('best-streak').textContent = this.state.stats.bestStreak;
        
        const timeSpent = Math.floor((Date.now() - this.state.stats.startTime) / 1000);
        const minutes = Math.floor(timeSpent / 60);
        const seconds = timeSpent % 60;
        document.getElementById('time-spent').textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        
        this.saveState();
    }
}

// App global verfügbar machen
window.App = new VocaBlitzApp();