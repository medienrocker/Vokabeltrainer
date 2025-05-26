<?php
// components/csv_uploader.php
// Sicherstellen, dass der Benutzer angemeldet ist und Lehrer-Rechte hat

require_once '../includes/debug.php';
session_start();
$isTeacher = isset($_SESSION['user']) && $_SESSION['user']['is_teacher'];

if (!$isTeacher) {
    echo '<div class="error-message">Sie benötigen Lehrer-Rechte, um Vokabellisten hochzuladen.</div>';
    exit;
}
?>

<div class="csv-uploader">
    <h2>Vokabelliste hochladen</h2>
    <p>Laden Sie eine CSV-Datei mit Vokabeln hoch. Die Datei sollte mindestens die Spalten "german" und "french" enthalten.</p>
    
    <form id="uploadForm" enctype="multipart/form-data">
        <div class="upload-area">
            <input type="file" id="csvFile" name="csvFile" accept=".csv" required>
            <label for="csvFile" class="upload-label">
                <i class="fas fa-cloud-upload-alt"></i>
                <span>CSV-Datei auswählen oder hierher ziehen</span>
            </label>
        </div>
        
        <div class="upload-info">
            <h3>CSV-Format:</h3>
            <ul>
                <li>Trennzeichen: Semikolon (;)</li>
                <li>Erforderliche Spalten: <code>german;french</code></li>
                <li>Optionale Spalten: <code>category;level;tags</code></li>
            </ul>
            
            <div class="example-csv">
                <h4>Beispiel:</h4>
                <pre>german;french;category;level
Hallo;Bonjour;Begrüßungen;1
Auf Wiedersehen;Au revoir;Begrüßungen;1
Danke;Merci;Höflichkeit;1</pre>
            </div>
        </div>
        
        <div class="upload-options">
            <label>
                <input type="checkbox" name="is_public" checked>
                Öffentlich (für alle Lernenden sichtbar)
            </label>
            
            <label>
                <input type="checkbox" name="replace_existing">
                Vorhandene Liste mit gleichem Namen ersetzen
            </label>
        </div>
        
        <button type="submit" class="upload-btn">Hochladen</button>
    </form>
    
    <div id="uploadStatus"></div>
</div>

<style>
.csv-uploader {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
    background-color: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.upload-area {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    margin-bottom: 1.5rem;
    transition: all 0.3s;
}

.upload-area:hover {
    border-color: var(--primary);
    background-color: rgba(67, 97, 238, 0.05);
}

.upload-area input[type="file"] {
    display: none;
}

.upload-label {
    display: block;
    cursor: pointer;
}

.upload-label i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.upload-label span {
    display: block;
    font-size: 1.1rem;
    color: #666;
}

.upload-info {
    background-color: var(--light);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.upload-info h3 {
    margin-top: 0;
    font-size: 1.2rem;
    color: var(--primary);
}

.upload-info ul {
    margin-left: 1.5rem;
}

.upload-info code {
    background-color: #eee;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
}

.example-csv {
    margin-top: 1rem;
    border-top: 1px solid #eee;
    padding-top: 1rem;
}

.example-csv h4 {
    margin-top: 0;
    font-size: 1rem;
    color: #666;
}

.example-csv pre {
    background-color: #f8f9fa;
    padding: 0.8rem;
    border-radius: 4px;
    overflow-x: auto;
    font-family: monospace;
    font-size: 0.9rem;
}

.upload-options {
    margin-bottom: 1.5rem;
}

.upload-options label {
    display: block;
    margin-bottom: 0.5rem;
}

.upload-btn {
    background-color: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.8rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.upload-btn:hover {
    background-color: var(--secondary);
    transform: translateY(-2px);
}

#uploadStatus {
    margin-top: 1.5rem;
}

#uploadStatus .loading {
    color: var(--primary);
}

#uploadStatus .success {
    color: var(--success);
}

#uploadStatus .error {
    color: var(--error);
}

.error-message {
    padding: 1rem;
    background-color: #ffebee;
    color: #d32f2f;
    border-left: 4px solid #d32f2f;
    margin-bottom: 1rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const uploadStatus = document.getElementById('uploadStatus');
    const fileInput = document.getElementById('csvFile');
    const uploadArea = document.querySelector('.upload-area');
    
    // Drag & Drop Funktionalität
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        uploadArea.classList.add('highlight');
    }
    
    function unhighlight() {
        uploadArea.classList.remove('highlight');
    }
    
    uploadArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        
        // Update Label
        const fileLabel = uploadArea.querySelector('span');
        if (fileLabel && files.length > 0) {
            fileLabel.textContent = files[0].name;
        }
    }
    
    // File Input Change
    fileInput.addEventListener('change', function() {
        const fileLabel = uploadArea.querySelector('span');
        if (fileLabel && this.files.length > 0) {
            fileLabel.textContent = this.files[0].name;
        }
    });
    
    // Form Submit
    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('csvFile');
        const file = fileInput.files[0];
        
        if (!file) {
            uploadStatus.innerHTML = '<div class="error">Bitte wählen Sie eine Datei aus</div>';
            return;
        }
        
        const formData = new FormData(uploadForm);
        
        uploadStatus.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Datei wird hochgeladen...</div>';
        
        try {
            const response = await fetch('api/upload_vocabulary.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                uploadStatus.innerHTML = `<div class="success"><i class="fas fa-check-circle"></i> Liste "${result.name}" wurde erfolgreich hochgeladen</div>`;
                
                // Nach dem Hochladen die Liste neu laden
                setTimeout(() => {
                    // Lade die Liste neu, falls ein EventListener existiert
                    if (typeof loadVocabularyLists === 'function') {
                        loadVocabularyLists();
                    }
                    
                    // Optional: Modal schließen oder umleiten
                    const modal = document.querySelector('.csv-uploader').closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                }, 2000);
                
                // Formular zurücksetzen
                uploadForm.reset();
                const fileLabel = uploadArea.querySelector('span');
                if (fileLabel) {
                    fileLabel.textContent = 'CSV-Datei auswählen oder hierher ziehen';
                }
            } else {
                uploadStatus.innerHTML = `<div class="error"><i class="fas fa-exclamation-circle"></i> Fehler: ${result.error}</div>`;
            }
        } catch (error) {
            uploadStatus.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Fehler beim Hochladen der Datei</div>';
            console.error('Fehler beim Hochladen:', error);
        }
    });
});
</script>