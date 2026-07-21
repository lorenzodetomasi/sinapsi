<?php
include('functions.php');
// --- BACKEND AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $inputData = $_POST['payload'] ?? '';
    $action = $_POST['action'];

    try {
        if ($action === 'to_xml') {
            echo json_encode(['success' => true, 'result' => jsonToWsx($inputData)]);
        } elseif ($action === 'to_json') {
            echo json_encode(['success' => true, 'result' => WsxToJson($inputData)]);
        } 
        // --- NUOVA ACTION: VALIDAZIONE JSON ---
        elseif ($action === 'validate_json') {
            $errors = [];
            $detectedFormat = 'json';
            $parsed = json_decode($inputData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "<strong>Errore Sintassi JSON:</strong> " . json_last_error_msg();
                $errors[] = "Controlla la presenza di virgole finali (Trailing commas) non ammesse, virgolette mancanti o parentesi graffe non bilanciate.";
            } else {
                // Rilevamento automatico del formato specifico
                if (isset($parsed['@context'])) {
                    if (isset($parsed['@type']) && (is_array($parsed['@type']) || strpos(json_encode($parsed['@context']), 'meetoo') !== false)) {
                        $detectedFormat = 'ws-json-ld';
                    } else {
                        $detectedFormat = 'json-ld';
                    }
                }
                
                // Controllo strutturale avanzato su stringa grezza (le chiavi duplicate vengono sovrascritte da json_decode)
                if (preg_match_all('/"([^"]+)"\s*:/', $inputData, $matches)) {
                    $keys = $matches[1];
                    $counts = array_count_values($keys);
                    if (isset($counts['organizer']) && $counts['organizer'] > 1 && (!isset($parsed['organizer']) || !is_array($parsed['organizer']) || !isset($parsed['organizer'][0]))) {
                        $errors[] = "<strong>Anomalia Strutturale WS:</strong> La chiave 'organizer' è ripetuta più volte. Nello standard JSON-LD esteso, le chiavi devono essere univoche: raggruppa gli organizzatori all'interno di un array di oggetti `[]`.";
                    }
                    if (isset($counts['subEvent']) && $counts['subEvent'] > 1 && (!isset($parsed['subEvent']) || !is_array($parsed['subEvent']) || !isset($parsed['subEvent'][0]))) {
                        $errors[] = "<strong>Anomalia Strutturale WS:</strong> La chiave 'subEvent' è ripetuta più volte come oggetto singolo duplicato. Deve essere strutturata come una lista array `[]`.";
                    }
                }
            }
            echo json_encode(['success' => true, 'valid' => empty($errors), 'errors' => $errors, 'format' => $detectedFormat]);
        } 
        // --- NUOVA ACTION: VALIDAZIONE XML ---
        elseif ($action === 'validate_xml') {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $errors = [];
            $detectedFormat = 'xml';
            
            if (!trim($inputData)) {
                $errors[] = "Il codice XML fornito è completamente vuoto.";
            } else {
                $isValid = $dom->loadXML($inputData);
                if (!$isValid) {
                    foreach (libxml_get_errors() as $error) {
                        $errors[] = "<strong>Errore XML (Riga {$error->line}, Colonna {$error->column}):</strong> " . trim($error->message);
                    }
                    libxml_clear_errors();
                } else {
                    // Rilevamento automatico formato XML
                    $root = $dom->documentElement;
                    if ($root && ($root->getAttribute('xmlns:meetoo') || $root->getAttribute('xmlns:xi'))) {
                        $detectedFormat = 'ws-xml';
                    }
                }
            }
            echo json_encode(['success' => true, 'valid' => empty($errors), 'errors' => $errors, 'format' => $detectedFormat]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>WS CMS - Convertitore strutturale e Validatore Server-Side</title>
    <style>
        :root { 
            --bg: #1e1e2e; 
            --surface: #313244; 
            --text: #cdd6f4; 
            --accent: #89b4fa;
            --danger: #f38ba8;
            --warning: #f9e2af;
            --success: #a6e3a1;
            --editor-bg: #11111b;
            --gutter-bg: #181825;
            --gutter-text: #6c7086;
        }
        
        body { 
            font-family: system-ui, sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            margin: 0; 
            padding: 20px; 
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
            box-sizing: border-box; 
        }

        body.dragover::after {
            content: "Rilascia il file qui per importarlo";
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(30, 30, 46, 0.95);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            z-index: 9999;
            border: 4px dashed var(--accent);
            pointer-events: none;
        }
        
        header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
        }
        
        button { 
            background: var(--accent); 
            color: #111; 
            border: none; 
            padding: 10px 16px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        button:hover { opacity: 0.9; }
        
        .editor-container { 
            display: flex; 
            gap: 20px; 
            flex: 1; 
            min-height: 0; 
        }
        
        .pane { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            background: var(--surface); 
            border-radius: 8px; 
            overflow: hidden; 
        }
        
        .pane-header { 
            background: #181825; 
            padding: 10px; 
            font-weight: bold; 
            display: flex;
            justify-content: center;
            align-items: center;
            border-bottom: 1px solid #45475a; 
            position: relative;
        }
        
        select {
            background: var(--surface);
            color: var(--text);
            border: 1px solid #45475a;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-indicator {
            position: absolute;
            right: 15px;
            font-size: 20px;
        }

        .code-area { 
            display: flex; 
            flex: 1; 
            overflow: hidden; 
            background: var(--editor-bg);
            position: relative;
        }
        
        .editor-font {
            font-family: 'Fira Code', 'Courier New', Courier, monospace;
            font-size: 14px;
            line-height: 1.5;
            padding-top: 15px;
            padding-bottom: 15px;
            box-sizing: border-box;
        }

        .line-numbers { 
            padding-left: 10px;
            padding-right: 10px;
            background: var(--gutter-bg); 
            color: var(--gutter-text); 
            text-align: right; 
            user-select: none; 
            overflow: hidden; 
            min-width: 45px;
        }
        
        textarea { 
            flex: 1; 
            background: transparent; 
            color: var(--text); 
            padding-left: 15px;
            padding-right: 15px;
            border: none; 
            outline: none; 
            resize: none; 
            white-space: pre; 
            overflow: auto;
        }

        .debug-panel {
            background: rgba(243, 139, 168, 0.1);
            border-top: 1px solid var(--danger);
            color: var(--danger);
            padding: 15px;
            display: none;
            max-height: 150px;
            overflow-y: auto;
        }
        .debug-panel h4 { margin: 0 0 10px 0; display: flex; justify-content: space-between; align-items: center;}
        .debug-panel ul { margin: 0; list-style: none; padding: 0; }
        .debug-panel li { margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px;}
        .debug-panel input[type="checkbox"] { margin-top: 4px; }
        .debug-panel button.btn-validate {
            background: var(--danger);
            color: #111;
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 4px;
        }

        .actions-bar {
            display: flex;
            gap: 10px;
            padding: 10px;
            background: #181825;
            border-top: 1px solid #45475a;
            justify-content: center;
        }
        
        .actions-bar button { font-size: 13px; padding: 8px 12px; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>

    <header>
        <h2>WS CMS - Parser & Validatore</h2>
        <button onclick="document.getElementById('file-input').click()">
            <span class="material-symbols-outlined">upload_file</span> Carica File
        </button>
        <input type="file" id="file-input" style="display: none;" accept=".json, .xml">
    </header>

    <section class="editor-container">
        <section class="pane">
            <header class="pane-header">
                <h3>JSON-LD</option></h3>
                <span id="status-json" class="status-indicator material-symbols-outlined" style="color: var(--success); display:none;">check_circle</span>
            </header>
            
            <div class="code-area">
                <div class="line-numbers editor-font" id="lines-json">1</div>
                <textarea class="editor-font" id="editor-json" oninput="handleInput('json')" onscroll="syncScroll('json')"></textarea>
            </div>
            
            <div id="debug-json" class="debug-panel">
                <h4>⚠️ Errori rilevati dal server <button class="btn-validate" onclick="manualValidate('json')">Rivalida Ora</button></h4>
                <ul id="error-list-json"></ul>
            </div>

            <footer class="actions-bar">
                <button onclick="convert('to_xml')"><span class="material-symbols-outlined">swap_horizontal_circle</span> Converti in XML</button>
                <button class="copy-btn" onclick="copyToClipboard('editor-json')"><span class="material-symbols-outlined">content_copy</span> Copia</button>
                <button onclick="downloadFile('json')"><span class="material-symbols-outlined">download</span> Salva</button>
            </footer>
        </section>

        <section class="pane">
            <header class="pane-header">
                <h3>XML</h3>
                <span id="status-xml" class="status-indicator material-symbols-outlined" style="color: var(--success); display:none;">check_circle</span>
            </header>

            <div class="code-area">
                <div class="line-numbers editor-font" id="lines-xml">1</div>
                <textarea class="editor-font" id="editor-xml" oninput="handleInput('xml')" onscroll="syncScroll('xml')"></textarea>
            </div>

            <div id="debug-xml" class="debug-panel">
                <h4>⚠️ Errori rilevati dal server <button class="btn-validate" onclick="manualValidate('xml')">Rivalida Ora</button></h4>
                <ul id="error-list-xml"></ul>
            </div>

            <footer class="actions-bar">
                <button onclick="convert('to_json')"><span class="material-symbols-outlined">swap_horizontal_circle</span> Converti in JSON</button>
                <button class="copy-btn" onclick="copyToClipboard('editor-xml')"><span class="material-symbols-outlined">content_copy</span> Copia</button>
                <button onclick="downloadFile('xml')"><span class="material-symbols-outlined">download</span> Salva</button>
            </footer>
        </section>
    </section>

    <script>
        // --- DRAG & DROP & FILE HANDLING ---
        const fileInput = document.getElementById('file-input');
        let dragCounter = 0;

        document.body.addEventListener('dragenter', (e) => { e.preventDefault(); dragCounter++; document.body.classList.add('dragover'); });
        document.body.addEventListener('dragover', (e) => e.preventDefault());
        document.body.addEventListener('dragleave', () => { dragCounter--; if (dragCounter === 0) document.body.classList.remove('dragover'); });
        document.body.addEventListener('drop', (e) => {
            e.preventDefault(); dragCounter = 0; document.body.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) processFile(e.dataTransfer.files[0]);
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) { processFile(e.target.files[0]); e.target.value = ''; }
        });

        function processFile(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const content = e.target.result;
                if (file.name.endsWith('.json')) {
                    document.getElementById('editor-json').value = content;
                    handleInput('json');
                } else if (file.name.endsWith('.xml')) {
                    document.getElementById('editor-xml').value = content;
                    handleInput('xml');
                } else {
                    alert("Formato non supportato.");
                }
            };
            reader.readAsText(file);
        }

        // --- GESTIONE INPUT & VALIDAZIONE ASINCRONA SERVER-SIDE ---
        let inputTimeout = null;
        function handleInput(type) {
            syncLines(type);
            clearTimeout(inputTimeout);
            inputTimeout = setTimeout(async () => {
                const isValid = await validateCode(type);
                if(isValid) {
                    convert(type === 'json' ? 'to_xml' : 'to_json');
                }
            }, 800);
        }

        async function manualValidate(type) {
            const isValid = await validateCode(type);
            if (isValid) {
                convert(type === 'json' ? 'to_xml' : 'to_json');
            }
        }

        async function validateCode(type) {
            const content = document.getElementById(`editor-${type}`).value;
            const debugPanel = document.getElementById(`debug-${type}`);
            const errorList = document.getElementById(`error-list-${type}`);
            const statusIcon = document.getElementById(`status-${type}`);
            const selectEl = document.getElementById(`select-${type}`);
            
            if (!content.trim()) {
                debugPanel.style.display = 'none';
                statusIcon.style.display = 'none';
                return false;
            }

            const formData = new URLSearchParams();
            formData.append('action', `validate_${type}`);
            formData.append('payload', content);

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await res.json();
                
                if (data.success) {
                    // Imposta la select in base al formato rilevato dal server
                    if (data.format) {
                        selectEl.value = data.format;
                    }

                    if (!data.valid) {
                        errorList.innerHTML = '';
                        data.errors.forEach(err => {
                            errorList.innerHTML += `<li><label><input type="checkbox"> <span>${err}</span></label></li>`;
                        });
                        debugPanel.style.display = 'block';
                        statusIcon.textContent = 'error';
                        statusIcon.style.color = 'var(--danger)';
                        statusIcon.style.display = 'block';
                        return false;
                    } else {
                        debugPanel.style.display = 'none';
                        statusIcon.textContent = 'check_circle';
                        statusIcon.style.color = 'var(--success)';
                        statusIcon.style.display = 'block';
                        return true;
                    }
                }
            } catch (err) {
                console.error("Errore di validazione server-side:", err);
            }
            return false;
        }

        // --- UTILITY EDITOR ---
        function syncLines(type) {
            const textarea = document.getElementById(`editor-${type}`);
            const linesDiv = document.getElementById(`lines-${type}`);
            const linesCount = textarea.value.split('\n').length;
            linesDiv.innerHTML = Array.from({length: linesCount || 1}, (_, i) => i + 1).join('<br>');
        }
        function syncScroll(type) {
            document.getElementById(`lines-${type}`).scrollTop = document.getElementById(`editor-${type}`).scrollTop;
        }

        // --- AJAX CONVERSION LOGIC ---
        async function convert(action) {
            const sourceId = action === 'to_xml' ? 'editor-json' : 'editor-xml';
            const targetType = action === 'to_xml' ? 'xml' : 'json';
            const payload = document.getElementById(sourceId).value;

            if(!payload.trim()) return;

            const formData = new URLSearchParams();
            formData.append('action', action);
            formData.append('payload', payload);

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById(`editor-${targetType}`).value = data.result;
                    // Chiamiamo solo syncLines e la validazione visiva di successo per non innescare loop infiniti di conversioni reciproche
                    syncLines(targetType);
                    const debugPanel = document.getElementById(`debug-${targetType}`);
                    const statusIcon = document.getElementById(`status-${targetType}`);
                    debugPanel.style.display = 'none';
                    statusIcon.textContent = 'check_circle';
                    statusIcon.style.color = 'var(--success)';
                    statusIcon.style.display = 'block';
                }
            } catch (err) {
                console.error(err);
            }
        }

        function copyToClipboard(id) {
            const textarea = document.getElementById(id);
            textarea.select();
            document.execCommand('copy');
            window.getSelection().removeAllRanges();
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined">check</span> Copiato!';
            setTimeout(() => btn.innerHTML = originalText, 2000);
        }

        function downloadFile(type) {
            const content = document.getElementById(`editor-${type}`).value;
            if(!content.trim()) return alert("Nessun contenuto da salvare.");
            const mime = type === 'json' ? 'application/json' : 'application/xml';
            const blob = new Blob([content], { type: mime });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `converted_file.${type}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        }
    </script>
</body>
</html>