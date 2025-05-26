<?php
/**
 * Vokabellisten-Verwaltung
 * Datei: admin/list_manager.php
 */

// Session starten und Zugriffsrechte prüfen
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_teacher']) {
    header('Location: ../login.php?redirect=admin&error=unauthorized');
    exit;
}

require_once '../includes/db_connect.php';
require_once '../includes/vocabulary_lists.php';
$pdo = connectDB();

// Parameter abrufen
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$listId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Formular-Daten verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Listendetails speichern
    if (isset($_POST['save_list'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $difficulty = (int)$_POST['difficulty'];
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        if (empty($name)) {
            $error = 'Bitte geben Sie einen Namen für die Liste ein.';
        } else {
            // Bestehende Liste aktualisieren
            if ($listId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE vocabulary_lists 
                    SET name = :name, 
                        description = :description, 
                        category = :category, 
                        difficulty = :difficulty,
                        is_public = :isPublic
                    WHERE id = :id
                ");
                
                $stmt->bindParam(':id', $listId, PDO::PARAM_INT);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_INT);
                $stmt->bindParam(':isPublic', $isPublic, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success = 'Liste wurde erfolgreich aktualisiert.';
                } else {
                    $error = 'Fehler beim Aktualisieren der Liste.';
                }
            } 
            // Neue Liste erstellen
            else {
                $userId = $_SESSION['user']['id'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO vocabulary_lists 
                    (name, description, category, difficulty, is_public, created_by) 
                    VALUES (:name, :description, :category, :difficulty, :isPublic, :userId)
                ");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_INT);
                $stmt->bindParam(':isPublic', $isPublic, PDO::PARAM_INT);
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $listId = $pdo->lastInsertId();
                    $success = 'Liste wurde erfolgreich erstellt.';
                    header("Location: list_manager.php?action=edit&id=$listId&success=created");
                    exit;
                } else {
                    $error = 'Fehler beim Erstellen der Liste.';
                }
            }
        }
    }
    
    // Vokabel hinzufügen
    else if (isset($_POST['add_vocabulary']) && $listId > 0) {
        $termFrom = trim($_POST['term_from']);
        $termTo = trim($_POST['term_to']);
        $vocabCategory = trim($_POST['vocab_category'] ?? '');
        $vocabDifficulty = (int)($_POST['vocab_difficulty'] ?? 1);
        
        if (empty($termFrom) || empty($termTo)) {
            $error = 'Bitte geben Sie beide Vokabeln ein.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO vocabulary_items 
                (list_id, term_from, term_to, category, difficulty) 
                VALUES (:listId, :termFrom, :termTo, :category, :difficulty)
            ");
            
            $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
            $stmt->bindParam(':termFrom', $termFrom);
            $stmt->bindParam(':termTo', $termTo);
            $stmt->bindParam(':category', $vocabCategory);
            $stmt->bindParam(':difficulty', $vocabDifficulty, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success = 'Vokabel wurde erfolgreich hinzugefügt.';
                // Nach dem Hinzufügen die Felder leeren und auf der Seite bleiben
                $_POST['term_from'] = '';
                $_POST['term_to'] = '';
            } else {
                $error = 'Fehler beim Hinzufügen der Vokabel.';
            }
        }
    }
    
    // Vokabel löschen
    else if (isset($_POST['delete_vocabulary']) && isset($_POST['vocab_id'])) {
        $vocabId = (int)$_POST['vocab_id'];
        
        $stmt = $pdo->prepare("DELETE FROM vocabulary_items WHERE id = :id AND list_id = :listId");
        $stmt->bindParam(':id', $vocabId, PDO::PARAM_INT);
        $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $success = 'Vokabel wurde erfolgreich gelöscht.';
        } else {
            $error = 'Fehler beim Löschen der Vokabel.';
        }
    }
    
    // Liste löschen
    else if (isset($_POST['delete_list']) && $listId > 0) {
        // Zuerst alle Vokabeln der Liste löschen
        $stmt = $pdo->prepare("DELETE FROM vocabulary_items WHERE list_id = :listId");
        $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Dann die Liste selbst löschen
        $stmt = $pdo->prepare("DELETE FROM vocabulary_lists WHERE id = :listId");
        $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            header('Location: list_manager.php?success=deleted');
            exit;
        } else {
            $error = 'Fehler beim Löschen der Liste.';
        }
    }
    
    // Mehrere Vokabeln hochladen
    else if (isset($_POST['bulk_upload']) && $listId > 0) {
        $bulkText = trim($_POST['bulk_vocab']);
        $delimiter = $_POST['delimiter'];
        
        if (empty($bulkText)) {
            $error = 'Bitte geben Sie Vokabeln ein.';
        } else {
            $lines = explode("\n", $bulkText);
            $addedCount = 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO vocabulary_items 
                (list_id, term_from, term_to) 
                VALUES (:listId, :termFrom, :termTo)
            ");
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $parts = explode($delimiter, $line);
                if (count($parts) >= 2) {
                    $termFrom = trim($parts[0]);
                    $termTo = trim($parts[1]);
                    
                    if (!empty($termFrom) && !empty($termTo)) {
                        $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
                        $stmt->bindParam(':termFrom', $termFrom);
                        $stmt->bindParam(':termTo', $termTo);
                        
                        if ($stmt->execute()) {
                            $addedCount++;
                        }
                    }
                }
            }
            
            if ($addedCount > 0) {
                $success = "$addedCount Vokabeln wurden erfolgreich hinzugefügt.";
            } else {
                $error = 'Keine Vokabeln konnten hinzugefügt werden. Bitte überprüfen Sie das Format.';
            }
        }
    }
}

// Listen-Details laden
$list = null;
$vocabItems = [];

if ($listId > 0 && ($action === 'view' || $action === 'edit')) {
    // Liste laden
    $stmt = $pdo->prepare("
        SELECT vl.*, u.username as creator_name
        FROM vocabulary_lists vl
        LEFT JOIN users u ON vl.created_by = u.id
        WHERE vl.id = :id
    ");
    $stmt->bindParam(':id', $listId, PDO::PARAM_INT);
    $stmt->execute();
    
    $list = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$list) {
        header('Location: list_manager.php?error=not_found');
        exit;
    }
    
    // Vokabeln laden
    $stmt = $pdo->prepare("
        SELECT * FROM vocabulary_items 
        WHERE list_id = :listId 
        ORDER BY id ASC
    ");
    $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
    $stmt->execute();
    
    $vocabItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Alle Listen anzeigen
$allLists = [];
if ($action === 'list') {
    $stmt = $pdo->prepare("
        SELECT vl.*, u.username as creator_name,
            (SELECT COUNT(*) FROM vocabulary_items WHERE list_id = vl.id) as vocab_count
        FROM vocabulary_lists vl
        LEFT JOIN users u ON vl.created_by = u.id
        ORDER BY vl.name ASC
    ");
    $stmt->execute();
    
    $allLists = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/admin_header.php';
?>

<div class="admin-content">
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
        <div class="alert alert-success">
            Die Liste wurde erfolgreich gelöscht.
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
        <div class="alert alert-success">
            Die Liste wurde erfolgreich erstellt. Sie können jetzt Vokabeln hinzufügen.
        </div>
    <?php endif; ?>
    
    <!-- Listenübersicht -->
    <?php if ($action === 'list'): ?>
        <div class="admin-header">
            <h1>Vokabellisten</h1>
            <div class="admin-actions">
                <a href="list_manager.php?action=new" class="btn-primary">
                    <i class="fas fa-plus"></i> Neue Liste
                </a>
            </div>
        </div>
        
        <?php if (count($allLists) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Kategorie</th>
                        <th>Schwierigkeit</th>
                        <th>Vokabeln</th>
                        <th>Erstellt von</th>
                        <th>Datum</th>
                        <th>Öffentlich</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allLists as $item): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                            <td>
                                <?php 
                                    $difficultyLabels = [
                                        1 => 'Leicht (A1)',
                                        2 => 'Mittel (A2)',
                                        3 => 'Fortgeschritten (B1)',
                                        4 => 'Schwer (B2)',
                                        5 => 'Sehr schwer (C1/C2)'
                                    ];
                                    echo $difficultyLabels[$item['difficulty']] ?? $item['difficulty'];
                                ?>
                            </td>
                            <td><?php echo $item['vocab_count']; ?></td>
                            <td><?php echo htmlspecialchars($item['creator_name'] ?? 'Unbekannt'); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($item['created_at'])); ?></td>
                            <td>
                                <?php if ($item['is_public']): ?>
                                    <span class="badge badge-success">Ja</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Nein</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="list_manager.php?action=view&id=<?php echo $item['id']; ?>" class="btn-icon" title="Anzeigen">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="list_manager.php?action=edit&id=<?php echo $item['id']; ?>" class="btn-icon" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn-icon delete-list-btn" 
                                            data-id="<?php echo $item['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                            title="Löschen">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>Keine Vokabellisten vorhanden.</p>
                <a href="list_manager.php?action=new" class="btn-primary">Erste Liste erstellen</a>
            </div>
        <?php endif; ?>
        
        <!-- Lösch-Bestätigungsdialog -->
        <div id="deleteConfirmModal" class="modal">
            <div class="modal-content">
                <h2>Liste löschen</h2>
                <p>Möchten Sie die Liste "<span id="deleteListName"></span>" wirklich löschen?</p>
                <p class="warning">Diese Aktion kann nicht rückgängig gemacht werden!</p>
                
                <form method="post" id="deleteListForm">
                    <input type="hidden" name="delete_list" value="1">
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" id="cancelDeleteBtn">Abbrechen</button>
                        <button type="submit" class="btn-danger">Löschen</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Neue Liste erstellen -->
    <?php if ($action === 'new'): ?>
        <div class="admin-header">
            <h1>Neue Vokabelliste erstellen</h1>
            <div class="admin-actions">
                <a href="list_manager.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                </a>
            </div>
        </div>
        
        <form method="post" class="admin-form">
            <div class="form-group">
                <label for="name">Name der Liste *</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Beschreibung</label>
                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="category">Kategorie</label>
                    <input type="text" id="category" name="category" 
                           value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="difficulty">Schwierigkeitsgrad</label>
                    <select id="difficulty" name="difficulty">
                        <option value="1" <?php echo (($_POST['difficulty'] ?? '') == 1) ? 'selected' : ''; ?>>Leicht (A1)</option>
                        <option value="2" <?php echo (($_POST['difficulty'] ?? '') == 2) ? 'selected' : ''; ?>>Mittel (A2)</option>
                        <option value="3" <?php echo (($_POST['difficulty'] ?? '') == 3) ? 'selected' : ''; ?>>Fortgeschritten (B1)</option>
                        <option value="4" <?php echo (($_POST['difficulty'] ?? '') == 4) ? 'selected' : ''; ?>>Schwer (B2)</option>
                        <option value="5" <?php echo (($_POST['difficulty'] ?? '') == 5) ? 'selected' : ''; ?>>Sehr schwer (C1/C2)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_public" value="1" 
                           <?php echo (isset($_POST['is_public']) || !isset($_POST)) ? 'checked' : ''; ?>>
                    Öffentlich (für alle Benutzer sichtbar)
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_list" class="btn-primary">Liste erstellen</button>
                <a href="list_manager.php" class="btn-secondary">Abbrechen</a>
            </div>
        </form>
    <?php endif; ?>
    
    <!-- Liste anzeigen -->
    <?php if ($action === 'view' && $list): ?>
        <div class="admin-header">
            <h1><?php echo htmlspecialchars($list['name']); ?></h1>
            <div class="admin-actions">
                <a href="list_manager.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                </a>
                <a href="list_manager.php?action=edit&id=<?php echo $list['id']; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Bearbeiten
                </a>
            </div>
        </div>
        
        <div class="list-details">
            <div class="detail-row">
                <div class="detail-label">Kategorie:</div>
                <div class="detail-value"><?php echo htmlspecialchars($list['category'] ?? '-'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Schwierigkeitsgrad:</div>
                <div class="detail-value">
                    <?php 
                        $difficultyLabels = [
                            1 => 'Leicht (A1)',
                            2 => 'Mittel (A2)',
                            3 => 'Fortgeschritten (B1)',
                            4 => 'Schwer (B2)',
                            5 => 'Sehr schwer (C1/C2)'
                        ];
                        echo $difficultyLabels[$list['difficulty']] ?? $list['difficulty'];
                    ?>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Erstellt von:</div>
                <div class="detail-value"><?php echo htmlspecialchars($list['creator_name'] ?? 'Unbekannt'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Erstellt am:</div>
                <div class="detail-value"><?php echo date('d.m.Y H:i', strtotime($list['created_at'])); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Öffentlich:</div>
                <div class="detail-value">
                    <?php if ($list['is_public']): ?>
                        <span class="badge badge-success">Ja</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Nein</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($list['description'])): ?>
                <div class="detail-row full-width">
                    <div class="detail-label">Beschreibung:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($list['description'])); ?></div>
                </div>
            <?php endif; ?>
        </div>
        
        <h2>Vokabeln <span class="vocab-count">(<?php echo count($vocabItems); ?>)</span></h2>
        
        <?php if (count($vocabItems) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Deutsch</th>
                        <th>Französisch</th>
                        <th>Kategorie</th>
                        <th>Schwierigkeit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vocabItems as $vocab): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vocab['term_from']); ?></td>
                            <td><?php echo htmlspecialchars($vocab['term_to']); ?></td>
                            <td><?php echo htmlspecialchars($vocab['category'] ?? '-'); ?></td>
                            <td>
                                <?php 
                                    $vocabDifficultyLabels = [
                                        1 => 'Leicht',
                                        2 => 'Mittel',
                                        3 => 'Schwer'
                                    ];
                                    echo $vocabDifficultyLabels[$vocab['difficulty']] ?? $vocab['difficulty']; 
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>Keine Vokabeln in dieser Liste.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Liste bearbeiten -->
    <?php if ($action === 'edit' && $list): ?>
        <div class="admin-header">
            <h1>Liste bearbeiten: <?php echo htmlspecialchars($list['name']); ?></h1>
            <div class="admin-actions">
                <a href="list_manager.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                </a>
            </div>
        </div>
        
        <div class="edit-tabs">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="details">Listendetails</button>
                <button class="tab-btn" data-tab="vocabulary">Vokabeln (<?php echo count($vocabItems); ?>)</button>
                <button class="tab-btn" data-tab="bulk-upload">Massenimport</button>
            </div>
            
            <div class="tab-content">
                <!-- Tab: Listendetails -->
                <div class="tab-pane active" id="details-tab">
                    <form method="post" class="admin-form">
                        <div class="form-group">
                            <label for="name">Name der Liste *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($list['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Beschreibung</label>
                            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($list['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="category">Kategorie</label>
                                <input type="text" id="category" name="category" 
                                       value="<?php echo htmlspecialchars($list['category'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="difficulty">Schwierigkeitsgrad</label>
                                <select id="difficulty" name="difficulty">
                                    <option value="1" <?php echo ($list['difficulty'] == 1) ? 'selected' : ''; ?>>Leicht (A1)</option>
                                    <option value="2" <?php echo ($list['difficulty'] == 2) ? 'selected' : ''; ?>>Mittel (A2)</option>
                                    <option value="3" <?php echo ($list['difficulty'] == 3) ? 'selected' : ''; ?>>Fortgeschritten (B1)</option>
                                    <option value="4" <?php echo ($list['difficulty'] == 4) ? 'selected' : ''; ?>>Schwer (B2)</option>
                                    <option value="5" <?php echo ($list['difficulty'] == 5) ? 'selected' : ''; ?>>Sehr schwer (C1/C2)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_public" value="1" 
                                       <?php echo $list['is_public'] ? 'checked' : ''; ?>>
                                Öffentlich (für alle Benutzer sichtbar)
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="save_list" class="btn-primary">Änderungen speichern</button>
                        </div>
                    </form>
                </div>
                
                <!-- Tab: Vokabeln -->
                <div class="tab-pane" id="vocabulary-tab">
                    <h3>Neue Vokabel hinzufügen</h3>
                    <form method="post" class="admin-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="term_from">Deutsch *</label>
                                <input type="text" id="term_from" name="term_from" required
                                       value="<?php echo htmlspecialchars($_POST['term_from'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="term_to">Französisch *</label>
                                <input type="text" id="term_to" name="term_to" required
                                       value="<?php echo htmlspecialchars($_POST['term_to'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="vocab_category">Kategorie</label>
                                <input type="text" id="vocab_category" name="vocab_category"
                                       value="<?php echo htmlspecialchars($_POST['vocab_category'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="vocab_difficulty">Schwierigkeit</label>
                                <select id="vocab_difficulty" name="vocab_difficulty">
                                    <option value="1" <?php echo (($_POST['vocab_difficulty'] ?? '') == 1) ? 'selected' : ''; ?>>Leicht</option>
                                    <option value="2" <?php echo (($_POST['vocab_difficulty'] ?? '') == 2) ? 'selected' : ''; ?>>Mittel</option>
                                    <option value="3" <?php echo (($_POST['vocab_difficulty'] ?? '') == 3) ? 'selected' : ''; ?>>Schwer</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_vocabulary" class="btn-primary">Vokabel hinzufügen</button>
                        </div>
                    </form>
                    
                    <h3>Vokabeln in dieser Liste</h3>
                    
                    <?php if (count($vocabItems) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Deutsch</th>
                                    <th>Französisch</th>
                                    <th>Kategorie</th>
                                    <th>Schwierigkeit</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vocabItems as $vocab): ?>
                                    <tr>
                                        <td><?php echo $vocab['id']; ?></td>
                                        <td><?php echo htmlspecialchars($vocab['term_from']); ?></td>
                                        <td><?php echo htmlspecialchars($vocab['term_to']); ?></td>
                                        <td><?php echo htmlspecialchars($vocab['category'] ?? '-'); ?></td>
                                        <td>
                                            <?php 
                                                $vocabDifficultyLabels = [
                                                    1 => 'Leicht',
                                                    2 => 'Mittel',
                                                    3 => 'Schwer'
                                                ];
                                                echo $vocabDifficultyLabels[$vocab['difficulty']] ?? $vocab['difficulty']; 
                                            ?>
                                        </td>
                                        <td>
                                            <form method="post" class="delete-vocab-form">
                                                <input type="hidden" name="vocab_id" value="<?php echo $vocab['id']; ?>">
                                                <button type="submit" name="delete_vocabulary" class="btn-icon delete-vocab-btn" title="Löschen">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <p>Keine Vokabeln in dieser Liste.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab: Massenimport -->
                <div class="tab-pane" id="bulk-upload-tab">
                    <h3>Vokabeln importieren</h3>
                    <p>Fügen Sie mehrere Vokabeln gleichzeitig hinzu. Jede Zeile enthält ein Vokabelpaar, getrennt durch das gewählte Trennzeichen.</p>
                    
                    <form method="post" class="admin-form">
                        <div class="form-group">
                            <label for="delimiter">Trennzeichen</label>
                            <select id="delimiter" name="delimiter">
                                <option value="=">Gleichheitszeichen (=)</option>
                                <option value=";">Semikolon (;)</option>
                                <option value=",">Komma (,)</option>
                                <option value="-">Bindestrich (-)</option>
                                <option value="\t">Tabulator</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bulk_vocab">Vokabeln (eine pro Zeile)</label>
                            <textarea id="bulk_vocab" name="bulk_vocab" rows="10" placeholder="Hallo=Bonjour
Auf Wiedersehen=Au revoir
Danke=Merci"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="bulk_upload" class="btn-primary">Vokabeln importieren</button>
                        </div>
                    </form>
                    
                    <div class="import-example">
                        <h4>Beispiel:</h4>
                        <pre>Hallo=Bonjour
Auf Wiedersehen=Au revoir
Danke=Merci</pre>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab-Wechsel
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Tabs deaktivieren
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Gewählten Tab aktivieren
            this.classList.add('active');
            document.getElementById(tabId + '-tab').classList.add('active');
        });
    });
    
    // Löschbestätigung für Listen
    const deleteListBtns = document.querySelectorAll('.delete-list-btn');
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const deleteListForm = document.getElementById('deleteListForm');
    const deleteListName = document.getElementById('deleteListName');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    
    if (deleteListBtns.length > 0 && deleteConfirmModal) {
        deleteListBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const listId = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                deleteListForm.action = `list_manager.php?action=edit&id=${listId}`;
                deleteListName.textContent = name;
                deleteConfirmModal.style.display = 'block';
            });
        });
        
        // Schließen-Buttons
        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', function() {
                deleteConfirmModal.style.display = 'none';
            });
        }
        
        window.addEventListener('click', function(event) {
            if (event.target == deleteConfirmModal) {
                deleteConfirmModal.style.display = 'none';
            }
        });
    }
    
    // Bestätigung für Vokabel-Löschung
    const deleteVocabForms = document.querySelectorAll('.delete-vocab-form');
    
    deleteVocabForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Möchten Sie diese Vokabel wirklich löschen?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>