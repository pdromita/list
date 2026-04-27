<?php
// ── Carica .env ───────────────────────────────────────────────────────────────
function getEnvVar(string $key, string $default = ''): string {
    $file = __DIR__ . '/.env';
    if (!file_exists($file)) return $default;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) return trim($v);
    }
    return $default;
}

define('LIST_PASSWORD',   getEnvVar('PASSWORD', 'changeme'));
define('SESSION_KEY_LIST','list_manager_auth');

session_name('lampsoft_listpage');
session_save_path(__DIR__ . '/../home/storage/sessions');
ini_set('session.cookie_lifetime', (string)(86400 * 365));
ini_set('session.gc_maxlifetime',  (string)(86400 * 365));
session_set_cookie_params([
    'lifetime' => 86400 * 365,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ── Logout ────────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    unset($_SESSION[SESSION_KEY_LIST]);
    header('Location: index.php');
    exit;
}

// ── Login ─────────────────────────────────────────────────────────────────────
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === LIST_PASSWORD) {
        $_SESSION[SESSION_KEY_LIST] = true;
        header('Location: index.php');
        exit;
    }
    $loginError = 'Password errata.';
}

if (empty($_SESSION[SESSION_KEY_LIST])) {
    ?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accesso Liste</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Segoe UI',sans-serif; background:#f5f5f5; display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .box { background:white; padding:36px 32px; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,.12); width:320px; }
  h2 { margin-bottom:20px; color:#333; text-align:center; }
  input[type=password] { width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; font-size:15px; margin-bottom:14px; }
  button { width:100%; padding:11px; background:#2196F3; color:white; border:none; border-radius:4px; font-size:15px; font-weight:bold; cursor:pointer; }
  button:hover { background:#0b7dda; }
  .err { color:#f44336; font-size:13px; margin-bottom:12px; }
</style>
</head>
<body>
<div class="box">
  <h2>🔒 Liste Giornaliere</h2>
  <?php if ($loginError): ?><div class="err"><?php echo htmlspecialchars($loginError); ?></div><?php endif; ?>
  <form method="POST">
    <input type="password" name="password" placeholder="Password" autofocus required>
    <button type="submit">Accedi</button>
  </form>
</div>
</body>
</html>
    <?php
    exit;
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$lists = [];
if (is_dir($uploadDir)) {
    $lists = array_diff(scandir($uploadDir), ['.', '..']);
    rsort($lists);
}

$currentList   = null;
$listContent   = [];
$editMode      = false;
$renameMode    = false;
$editContent   = '';

// ── Carica file ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['listFile'])) {
    $file = $_FILES['listFile'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($file['name']);
        // Accetta solo .txt
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'txt') {
            $filePath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $_SESSION['message'] = "Lista caricata: $fileName";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
}

// ── Crea nuova lista online ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $newName    = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', trim($_POST['list_name'] ?? ''));
    $newContent = $_POST['list_content'] ?? '';
    if ($newName !== '') {
        if (!str_ends_with(strtolower($newName), '.txt')) {
            $newName .= '.txt';
        }
        file_put_contents($uploadDir . $newName, $newContent);
        $_SESSION['message'] = "Lista creata: $newName";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($newName));
        exit;
    }
}

// ── Salva lista modificata ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $saveName    = basename($_POST['list_name'] ?? '');
    $saveContent = $_POST['list_content'] ?? '';
    $filePath    = $uploadDir . $saveName;
    if ($saveName !== '' && file_exists($filePath)) {
        file_put_contents($filePath, $saveContent);
        $_SESSION['message'] = "Lista salvata: $saveName";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($saveName));
        exit;
    }
}

// ── Rinomina lista ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename') {
    $oldName = basename($_POST['old_name'] ?? '');
    $newName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', trim($_POST['new_name'] ?? ''));

    if ($oldName !== '' && $newName !== '') {
        if (!str_ends_with(strtolower($newName), '.txt')) {
            $newName .= '.txt';
        }

        $oldPath = $uploadDir . $oldName;
        $newPath = $uploadDir . $newName;

        if (!file_exists($oldPath)) {
            $_SESSION['message'] = 'Lista da rinominare non trovata.';
            header('Location: index.php');
            exit;
        }

        if ($oldName === $newName) {
            $_SESSION['message'] = 'Il nuovo nome e uguale a quello attuale.';
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($oldName));
            exit;
        }

        if (file_exists($newPath)) {
            $_SESSION['message'] = 'Esiste gia una lista con questo nome.';
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($oldName) . '&rename=1');
            exit;
        }

        if (rename($oldPath, $newPath)) {
            $_SESSION['message'] = "Lista rinominata: $oldName -> $newName";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($newName));
            exit;
        }

        $_SESSION['message'] = 'Rinomina non riuscita. Riprova.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($oldName) . '&rename=1');
        exit;
    }

    $_SESSION['message'] = 'Nome lista non valido.';
    header('Location: index.php');
    exit;
}

// ── Spunta / deseleziona elemento lista ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_item') {
    $listName  = basename($_POST['list_name'] ?? '');
    $itemIndex = isset($_POST['item_index']) ? (int)$_POST['item_index'] : -1;
    $filePath  = $uploadDir . $listName;

    if ($listName !== '' && $itemIndex >= 0 && file_exists($filePath)) {
        $rawLines = preg_split('/\r\n|\r|\n/', file_get_contents($filePath));

        if (isset($rawLines[$itemIndex])) {
            $currentLine = $rawLines[$itemIndex];

            if (preg_match('/^\s*\[x\]\s*/i', $currentLine)) {
                $rawLines[$itemIndex] = preg_replace('/^\s*\[x\]\s*/i', '[ ] ', $currentLine, 1);
            } elseif (preg_match('/^\s*\[\s\]\s*/', $currentLine)) {
                $rawLines[$itemIndex] = preg_replace('/^\s*\[\s\]\s*/', '[x] ', $currentLine, 1);
            } elseif (trim($currentLine) !== '') {
                $rawLines[$itemIndex] = '[x] ' . ltrim($currentLine);
            }

            file_put_contents($filePath, implode(PHP_EOL, $rawLines));
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($listName));
    exit;
}

// ── Download Markdown ─────────────────────────────────────────────────────────
if (isset($_GET['download_md']) && !empty($_GET['download_md'])) {
    $dlName   = basename($_GET['download_md']);
    $dlPath   = $uploadDir . $dlName;
    if (file_exists($dlPath)) {
        $rawLines = preg_split('/\r\n|\r|\n/', file_get_contents($dlPath));
        $items    = [];
        foreach ($rawLines as $line) {
            if (trim($line) === '') continue;
            $done = false;
            $text = $line;
            if (preg_match('/^\s*\[x\]\s*(.*)$/i', $line, $m)) { $done = true; $text = $m[1]; }
            elseif (preg_match('/^\s*\[\s\]\s*(.*)$/', $line, $m)) { $text = $m[1]; }
            $items[] = ['text' => $text, 'done' => $done];
        }
        $total     = count($items);
        $completed = count(array_filter($items, fn($i) => $i['done']));
        $pct       = $total > 0 ? round($completed / $total * 100) : 0;
        $date      = date('Y-m-d');

        $md  = "# Lista: " . $dlName . "\n\n";
        $md .= "**Data export:** $date  \n";
        $md .= "**Completati:** $completed / $total ($pct%)  \n\n";
        $md .= "## Elementi\n\n";
        foreach ($items as $item) {
            $md .= ($item['done'] ? '- [x] ' : '- [ ] ') . $item['text'] . "\n";
        }

        $mdName = pathinfo($dlName, PATHINFO_FILENAME) . '_' . $date . '.md';
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $mdName . '"');
        header('Content-Length: ' . strlen($md));
        echo $md;
        exit;
    }
}

// ── Elimina lista ─────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $fileToDelete = basename($_GET['delete']);
    $filePath     = $uploadDir . $fileToDelete;
    if (file_exists($filePath)) {
        unlink($filePath);
        $_SESSION['message'] = "Lista eliminata: $fileToDelete";
        header('Location: index.php');
        exit;
    }
}

// ── Visualizza / modifica lista ───────────────────────────────────────────────
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $currentList = basename($_GET['view']);
    $filePath    = $uploadDir . $currentList;
    if (file_exists($filePath)) {
        $rawContent  = file_get_contents($filePath);
        $editContent = $rawContent;

        $rawLines = preg_split('/\r\n|\r|\n/', $rawContent);
        foreach ($rawLines as $lineIndex => $line) {
            if (trim($line) === '') {
                continue;
            }

            $itemText = $line;
            $isDone   = false;

            if (preg_match('/^\s*\[x\]\s*(.*)$/i', $line, $m)) {
                $isDone   = true;
                $itemText = $m[1];
            } elseif (preg_match('/^\s*\[\s\]\s*(.*)$/', $line, $m)) {
                $itemText = $m[1];
            }

            $listContent[] = [
                'index' => $lineIndex,
                'text'  => $itemText,
                'done'  => $isDone,
            ];
        }
    }
    $editMode   = isset($_GET['edit']);
    $renameMode = isset($_GET['rename']) && !$editMode;
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestore Liste Giornaliere</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; }
        h1 { color: #333; margin-bottom: 10px; }
        .message { background: #4caf50; color: white; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .top-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .upload-section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1; }
        .create-section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; color: #333; font-weight: bold; }
        input[type="text"], input[type="file"], textarea { width: 100%; padding: 9px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; font-size: 14px; }
        textarea { resize: vertical; min-height: 90px; }
        .btn { display: inline-block; padding: 10px 18px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; text-decoration: none; }
        .btn-blue  { background: #2196F3; color: white; }
        .btn-blue:hover  { background: #0b7dda; }
        .btn-green { background: #4caf50; color: white; }
        .btn-green:hover { background: #388e3c; }
        .btn-orange{ background: #ff9800; color: white; }
        .btn-orange:hover{ background: #e65100; }
        .btn-slate { background: #607d8b; color: white; }
        .btn-slate:hover { background: #455a64; }
        .content { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .lists-panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .lists-panel h2 { color: #333; margin-bottom: 15px; font-size: 18px; }
        .list-item { padding: 10px 12px; margin-bottom: 8px; background: #f9f9f9; border-left: 4px solid #2196F3; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; gap: 6px; }
        .list-item a.name { color: #2196F3; text-decoration: none; font-weight: bold; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .list-item a.name:hover { text-decoration: underline; }
        .list-item .actions { display: flex; gap: 6px; flex-shrink: 0; }
        .list-item .edit-lnk { color: #ff9800; font-size: 13px; text-decoration: none; }
        .list-item .edit-lnk:hover { text-decoration: underline; }
        .list-item .rename-lnk { color: #607d8b; font-size: 13px; text-decoration: none; }
        .list-item .rename-lnk:hover { text-decoration: underline; }
        .list-item .delete { color: #f44336; font-size: 13px; text-decoration: none; }
        .list-item .delete:hover { text-decoration: underline; }
        .view-panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .view-panel h2 { color: #333; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .list-items { list-style: none; }
        .list-items li { padding: 12px; margin-bottom: 8px; background: #f0f7ff; border-left: 4px solid #4caf50; border-radius: 4px; }
        .item-row { display: flex; align-items: center; gap: 10px; }
        .item-row input[type="checkbox"] { transform: scale(1.2); cursor: pointer; }
        .item-row .txt { line-height: 1.45; }
        .list-items li.done { opacity: .78; background: #eef7ee; border-left-color: #7cb342; }
        .list-items li.done .txt { text-decoration: line-through; color: #4f6b4f; }
        .edit-area { width: 100%; min-height: 280px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 14px; resize: vertical; }
        .edit-actions { display: flex; gap: 10px; margin-top: 12px; }
        .no-list { text-align: center; color: #999; padding: 40px 20px; }
        .empty { text-align: center; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Gestore Liste Giornaliere</h1>
            <p>Carica, visualizza e gestisci le tue liste giornaliere &nbsp;|&nbsp; <a href="?logout=1" style="color:#f44336;font-size:13px;">Esci</a></p>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="top-bar">
            <!-- Upload file -->
            <div class="upload-section">
                <h2 style="margin-bottom:12px;font-size:16px;">⬆️ Carica file .txt</h2>
                <form method="POST" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;flex:1;">
                        <input type="file" id="listFile" name="listFile" accept=".txt" required>
                    </div>
                    <button type="submit" class="btn btn-blue">Carica</button>
                </form>
            </div>

            <!-- Crea nuova lista online -->
            <div class="create-section">
                <h2 style="margin-bottom:12px;font-size:16px;">✏️ Crea nuova lista</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <input type="text" name="list_name" placeholder="Nome lista (es. spesa)" required>
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <textarea name="list_content" placeholder="Scrivi gli elementi, uno per riga..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-green">Crea Lista</button>
                </form>
            </div>
        </div>

        <div class="content">
            <div class="lists-panel">
                <h2>📁 Le Tue Liste</h2>
                <?php if (count($lists) > 0): ?>
                    <?php foreach ($lists as $list): ?>
                        <div class="list-item">
                            <a class="name" href="?view=<?php echo urlencode($list); ?>">
                                <?php echo htmlspecialchars($list); ?>
                            </a>
                            <div class="actions">
                                <a class="edit-lnk" href="?view=<?php echo urlencode($list); ?>&amp;edit=1">✏️ Modifica</a>
                                <a class="rename-lnk" href="?view=<?php echo urlencode($list); ?>&amp;rename=1">📝 Rinomina</a>
                                <a class="delete" href="?delete=<?php echo urlencode($list); ?>" onclick="return confirm('Eliminare questa lista?');">✕</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">Nessuna lista caricata</div>
                <?php endif; ?>
            </div>

            <div class="view-panel">
                <?php if ($currentList): ?>
                    <h2>
                        <?php echo htmlspecialchars($currentList); ?>
                        <?php if (!$editMode && !$renameMode): ?>
                            <div style="display:flex;gap:8px;">
                                <a href="?view=<?php echo urlencode($currentList); ?>&amp;rename=1" class="btn btn-slate" style="font-size:13px;padding:6px 12px;">📝 Rinomina</a>
                                <a href="?view=<?php echo urlencode($currentList); ?>&amp;edit=1" class="btn btn-orange" style="font-size:13px;padding:6px 12px;">✏️ Modifica</a>
                                <a href="?download_md=<?php echo urlencode($currentList); ?>" class="btn btn-green" style="font-size:13px;padding:6px 12px;" title="Scarica in formato Markdown per analisi AI">⬇️ MD</a>
                            </div>
                        <?php endif; ?>
                    </h2>

                    <?php if ($editMode): ?>
                        <!-- Modalità modifica -->
                        <form method="POST">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="list_name" value="<?php echo htmlspecialchars($currentList); ?>">
                            <textarea class="edit-area" name="list_content"><?php echo htmlspecialchars($editContent); ?></textarea>
                            <div class="edit-actions">
                                <button type="submit" class="btn btn-green">💾 Salva</button>
                                <a href="?view=<?php echo urlencode($currentList); ?>" class="btn btn-blue">Annulla</a>
                            </div>
                        </form>
                    <?php elseif ($renameMode): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="rename">
                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($currentList); ?>">
                            <div class="form-group" style="max-width:460px;">
                                <label for="new_name">Nuovo nome lista</label>
                                <input id="new_name" type="text" name="new_name" value="<?php echo htmlspecialchars($currentList); ?>" required>
                            </div>
                            <div class="edit-actions">
                                <button type="submit" class="btn btn-slate">Salva nome</button>
                                <a href="?view=<?php echo urlencode($currentList); ?>" class="btn btn-blue">Annulla</a>
                            </div>
                        </form>
                    <?php elseif (count($listContent) > 0): ?>
                        <ul class="list-items">
                            <?php $i = 1; foreach ($listContent as $item): ?>
                                <li class="<?php echo $item['done'] ? 'done' : ''; ?>">
                                    <form method="POST" class="item-row">
                                        <input type="hidden" name="action" value="toggle_item">
                                        <input type="hidden" name="list_name" value="<?php echo htmlspecialchars($currentList); ?>">
                                        <input type="hidden" name="item_index" value="<?php echo (int)$item['index']; ?>">
                                        <input type="checkbox" <?php echo $item['done'] ? 'checked' : ''; ?>>
                                        <span class="txt"><?php echo $i++ . '. ' . htmlspecialchars($item['text']); ?></span>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty">Lista vuota — <a href="?view=<?php echo urlencode($currentList); ?>&amp;edit=1">Aggiungi elementi</a></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-list">Seleziona una lista da visualizzare</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<script>
(function () {
    var viewPanel = document.querySelector('.view-panel');
    if (!viewPanel) return;

    viewPanel.addEventListener('change', function (e) {
        var cb = e.target;
        if (cb.type !== 'checkbox') return;

        var form = cb.closest('form');
        if (!form) return;

        cb.disabled = true;

        fetch(location.pathname + location.search, { method: 'POST', body: new FormData(form) })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newPanel = doc.querySelector('.view-panel');
                if (newPanel) viewPanel.innerHTML = newPanel.innerHTML;
            })
            .catch(function () { cb.disabled = false; });
    });
}());
</script>
</body>
</html>