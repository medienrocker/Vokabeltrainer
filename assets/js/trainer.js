/**
 * VocaBlitz Vocabulary Trainer
 * Datei: assets/js/trainer.js (angepasste Version)
 */

class VocabularyTrainer {
    constructor() {
        this.state = {
            currentVocabIndex: 0,
            isFlipped: false,
            isAnswering: false
        };
        
        this.positiveMessages = [
            "Super gemacht!",
            "Très bien!",
            "Fantastisch!",
            "Excellent!",
            "Bravo!",
            "Gut gemacht!",
            "Perfekt!",
            "Du bist auf dem richtigen Weg!",
            "Weiter so!",
            "Ausgezeichnet!"
        ];

        this.negativeMessages = [
            "Fast richtig!",
            "Versuche es noch einmal!",
            "Nicht ganz...",
            "Fast!",
            "Noch nicht ganz richtig.",
            "Kleine Korrektur nötig."
        ];

        this.init();
    }

    init() {
        this.setupEventListeners();
        console.log('VocabularyTrainer initialized');
    }

    setupEventListeners() {
        // Flashcard Click
        document.getElementById('flashcard')?.addEventListener('click', (e) => {
            // Verhindern, dass Button-Klicks die Karte drehen
            if (e.target.closest('.next-vocab-btn')) {
                return;
            }
            this.toggleFlashcard();
        });

        // Multiple Choice
        document.getElementById('mc-options')?.addEventListener('click', (e) => {
            if (e.target.classList.contains('option') && !this.state.isAnswering) {
                this.handleMultipleChoiceAnswer(e.target);
            }
        });

        // Writing Mode
        document.getElementById('check-answer')?.addEventListener('click', () => {
            this.checkWritingAnswer();
        });

        document.getElementById('writing-input')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.checkWritingAnswer();
            }
        });

        // Feedback Next Button
        document.getElementById('nextBtn')?.addEventListener('click', () => {
            this.nextVocabulary();
        });
    }

    // Hauptfunktion zum Aktualisieren der Anzeige
    updateDisplay() {
        if (!window.App.state.vocabularies || window.App.state.vocabularies.length === 0) {
            this.showEmptyState();
            return;
        }

        const currentVocab = this.getCurrentVocab();
        if (!currentVocab) return;

        const mode = window.App.state.learningMode;
        const direction = window.App.state.direction;

        switch (mode) {
            case 'flashcard':
                this.updateFlashcard(currentVocab, direction);
                break;
            case 'multiple-choice':
                this.updateMultipleChoice(currentVocab, direction);
                break;
            case 'writing':
                this.updateWriting(currentVocab, direction);
                break;
        }

        this.updateProgress();
        this.state.isAnswering = false;
    }

    getCurrentVocab() {
        const vocabs = window.App.state.vocabularies;
        const index = window.App.state.currentVocabIndex;
        return vocabs && vocabs[index] ? vocabs[index] : null;
    }

    // Flashcard Methods
    updateFlashcard(vocab, direction) {
        const frontText = document.getElementById('flashcard-front-text');
        const backText = document.getElementById('flashcard-back-text');
        const flashcard = document.getElementById('flashcard');

        if (direction === 'de-fr') {
            frontText.textContent = vocab.term_from || vocab.german;
            backText.textContent = vocab.term_to || vocab.french;
        } else {
            frontText.textContent = vocab.term_to || vocab.french;
            backText.textContent = vocab.term_from || vocab.german;
        }

        // Karte zurückdrehen
        flashcard.classList.remove('flipped');
        this.state.isFlipped = false;

        // Button zur Rückseite hinzufügen
        this.addNextButton();
    }

    addNextButton() {
        const flashcardBack = document.querySelector('.flashcard-back');
        
        // Alten Button entfernen
        const oldButton = document.getElementById('flashcard-next-btn');
        if (oldButton) {
            oldButton.remove();
        }

        // Neuen Button erstellen
        const nextButton = document.createElement('button');
        nextButton.id = 'flashcard-next-btn';
        nextButton.className = 'next-vocab-btn';
        nextButton.textContent = 'Nächste Aufgabe →';
        
        nextButton.addEventListener('click', (e) => {
            e.stopPropagation();
            this.nextVocabulary();
        });

        flashcardBack.appendChild(nextButton);
    }

    toggleFlashcard() {
        const flashcard = document.getElementById('flashcard');
        flashcard.classList.toggle('flipped');
        this.state.isFlipped = !this.state.isFlipped;
    }

    // Multiple Choice Methods
    updateMultipleChoice(vocab, direction) {
        const question = document.getElementById('mc-question');
        const hint = document.getElementById('mc-hint');
        const optionsContainer = document.getElementById('mc-options');

        let questionText, correctAnswer, allWords;

        if (direction === 'de-fr') {
            questionText = vocab.term_from || vocab.german;
            correctAnswer = vocab.term_to || vocab.french;
            hint.textContent = 'Wähle die richtige französische Übersetzung';
            allWords = window.App.state.vocabularies.map(v => v.term_to || v.french);
        } else {
            questionText = vocab.term_to || vocab.french;
            correctAnswer = vocab.term_from || vocab.german;
            hint.textContent = 'Wähle die richtige deutsche Übersetzung';
            allWords = window.App.state.vocabularies.map(v => v.term_from || v.german);
        }

        question.textContent = questionText;

        // Optionen generieren
        const options = this.generateOptions(correctAnswer, allWords);
        
        optionsContainer.innerHTML = '';
        options.forEach(option => {
            const optionElement = document.createElement('div');
            optionElement.className = 'option';
            optionElement.textContent = option;
            optionElement.dataset.value = option;
            optionsContainer.appendChild(optionElement);
        });
    }

    generateOptions(correctAnswer, allWords) {
        const options = [correctAnswer];
        const availableWords = allWords.filter(word => word !== correctAnswer);
        
        while (options.length < 4 && availableWords.length > 0) {
            const randomIndex = Math.floor(Math.random() * availableWords.length);
            options.push(availableWords[randomIndex]);
            availableWords.splice(randomIndex, 1);
        }
        
        return this.shuffleArray(options);
    }

    async handleMultipleChoiceAnswer(optionElement) {
        if (this.state.isAnswering) return;
        this.state.isAnswering = true;

        const selectedValue = optionElement.dataset.value;
        const currentVocab = this.getCurrentVocab();
        const direction = window.App.state.direction;
        const correctAnswer = direction === 'de-fr' 
            ? (currentVocab.term_to || currentVocab.french)
            : (currentVocab.term_from || currentVocab.german);

        const isCorrect = selectedValue === correctAnswer;

        // Visuelles Feedback
        if (isCorrect) {
            optionElement.classList.add('correct');
        } else {
            optionElement.classList.add('incorrect');
            // Richtige Antwort markieren
            document.querySelectorAll('.option').forEach(option => {
                if (option.dataset.value === correctAnswer) {
                    option.classList.add('correct');
                }
            });
        }

        // Antwort verarbeiten
        await this.processAnswer(currentVocab.id, isCorrect);
    }

    // Writing Mode Methods
    updateWriting(vocab, direction) {
        const question = document.getElementById('writing-question');
        const hint = document.getElementById('writing-hint');
        const input = document.getElementById('writing-input');

        if (direction === 'de-fr') {
            question.textContent = vocab.term_from || vocab.german;
            hint.textContent = 'Schreibe die französische Übersetzung';
        } else {
            question.textContent = vocab.term_to || vocab.french;
            hint.textContent = 'Schreibe die deutsche Übersetzung';
        }

        input.value = '';
        input.focus();
    }

    async checkWritingAnswer() {
        if (this.state.isAnswering) return;

        const input = document.getElementById('writing-input');
        const userAnswer = input.value.trim();
        
        if (!userAnswer) return;

        this.state.isAnswering = true;

        const currentVocab = this.getCurrentVocab();
        const direction = window.App.state.direction;
        const correctAnswer = direction === 'de-fr' 
            ? (currentVocab.term_to || currentVocab.french)
            : (currentVocab.term_from || currentVocab.german);

        // Tolerante Antwortprüfung
        const isCorrect = this.compareAnswers(userAnswer, correctAnswer);

        // Antwort verarbeiten
        await this.processAnswer(currentVocab.id, isCorrect);
    }

    compareAnswers(userAnswer, correctAnswer) {
        // Normalisierung für Vergleich
        const normalize = (text) => {
            return text.toLowerCase()
                      .normalize("NFD")
                      .replace(/[\u0300-\u036f]/g, "")
                      .trim();
        };

        const normalizedUser = normalize(userAnswer);
        const normalizedCorrect = normalize(correctAnswer);

        return normalizedUser === normalizedCorrect || 
               userAnswer.toLowerCase() === correctAnswer.toLowerCase();
    }

    // Answer Processing
    async processAnswer(vocabId, isCorrect) {
        // Stats aktualisieren
        if (isCorrect) {
            window.App.state.stats.correctCount++;
            window.App.state.stats.currentStreak++;
            window.App.state.stats.bestStreak = Math.max(
                window.App.state.stats.bestStreak, 
                window.App.state.stats.currentStreak
            );
        } else {
            window.App.state.stats.wrongCount++;
            window.App.state.stats.currentStreak = 0;
        }

        // Backend-Verarbeitung (falls angemeldet)
        if (window.App.state.isLoggedIn && vocabId) {
            try {
                await window.App.processAnswer(vocabId, isCorrect);
            } catch (error) {
                console.error('Fehler beim Verarbeiten der Antwort im Backend:', error);
            }
        }

        // UI aktualisieren
        window.App.updateStats();
        
        // Feedback zeigen
        this.showFeedback(isCorrect);
    }

    showFeedback(isCorrect) {
        const currentVocab = this.getCurrentVocab();
        const direction = window.App.state.direction;
        
        let message = isCorrect 
            ? this.getRandomItem(this.positiveMessages)
            : this.getRandomItem(this.negativeMessages);

        // Bei falscher Antwort die richtige Lösung zeigen
        if (!isCorrect) {
            const correctAnswer = direction === 'de-fr' 
                ? (currentVocab.term_to || currentVocab.french)
                : (currentVocab.term_from || currentVocab.german);
            message += ` Die richtige Antwort ist: ${correctAnswer}`;
        }

        const emoji = isCorrect ? '😊' : '😔';
        
        UI.showFeedback(isCorrect, message, emoji);

        // Belohnungsanimation bei Streak
        if (isCorrect && window.App.state.stats.currentStreak > 0 && 
            window.App.state.stats.currentStreak % 5 === 0) {
            this.showRewardAnimation();
        }
    }

    nextVocabulary() {
        UI.hideFeedback();
        
        window.App.state.currentVocabIndex++;
        
        // Prüfen ob Ende der Liste erreicht
        if (window.App.state.currentVocabIndex >= window.App.state.vocabularies.length) {
            this.showCompletionFeedback();
            window.App.state.currentVocabIndex = 0; // Zurück zum Anfang
        }
        
        this.updateDisplay();
    }

    showCompletionFeedback() {
        const totalVocabs = window.App.state.vocabularies.length;
        const correctCount = window.App.state.stats.correctCount;
        
        const message = `Glückwunsch! Du hast alle ${totalVocabs} Vokabeln durchgearbeitet! ${correctCount} richtig beantwortet.`;
        
        UI.showFeedback(true, message, '🎉');
        this.showRewardAnimation();
    }

    // Progress und Stats
    updateProgress() {
        const vocabs = window.App.state.vocabularies;
        const currentIndex = window.App.state.currentVocabIndex;
        
        if (!vocabs || vocabs.length === 0) return;
        
        const progress = ((currentIndex + 1) / vocabs.length) * 100;
        
        // Progress bars aktualisieren
        document.getElementById('flashcard-progress').style.width = `${progress}%`;
        document.getElementById('mc-progress').style.width = `${progress}%`;
        document.getElementById('writing-progress').style.width = `${progress}%`;
    }

    showEmptyState() {
        // Alle Lernmodi verstecken
        document.getElementById('learningModes')?.classList.add('hidden');
        document.getElementById('noListSelected')?.classList.remove('hidden');
    }

    // Utility Methods
    shuffleArray(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }

    getRandomItem(array) {
        return array[Math.floor(Math.random() * array.length)];
    }

    showRewardAnimation() {
        const container = document.getElementById('rewardAnimation');
        if (!container) return;
        
        container.innerHTML = '';
        
        for (let i = 0; i < 30; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            
            const size = Math.random() * 8 + 4;
            const left = Math.random() * 100;
            const animDuration = Math.random() * 2 + 1.5;
            const background = `hsl(${Math.random() * 360}, 70%, 60%)`;
            
            confetti.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${left}%;
                top: -10px;
                background: ${background};
                border-radius: ${Math.random() > 0.5 ? '50%' : '0'};
                transform: rotate(${Math.random() * 360}deg);
                opacity: 0;
                transition: all ${animDuration}s ease-out;
            `;
            
            container.appendChild(confetti);
            
            // Animation starten
            setTimeout(() => {
                confetti.style.opacity = '1';
                confetti.style.top = `${Math.random() * 100 + 20}%`;
                confetti.style.left = `${left + (Math.random() * 40 - 20)}%`;
                confetti.style.transform = `rotate(${Math.random() * 360 * 3}deg)`;
                
                setTimeout(() => {
                    confetti.style.opacity = '0';
                }, animDuration * 600);
                
                setTimeout(() => {
                    confetti.remove();
                }, animDuration * 1000);
            }, Math.random() * 300);
        }
    }

    // Public API für App-Integration
    reset() {
        this.state.currentVocabIndex = 0;
        this.state.isFlipped = false;
        this.state.isAnswering = false;
        this.updateDisplay();
    }

    getCurrentVocabularyIndex() {
        return this.state.currentVocabIndex;
    }

    setVocabularyIndex(index) {
        if (index >= 0 && index < window.App.state.vocabularies.length) {
            this.state.currentVocabIndex = index;
            this.updateDisplay();
        }
    }
}

// Global verfügbar machen
window.VocabularyTrainer = new VocabularyTrainer();