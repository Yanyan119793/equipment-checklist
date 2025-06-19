<?php
// --- PHP LOGIC (WITH FLASH SESSION) ---
session_start();

// Predefined equipment list
$equipmentList = [
    "SPRINKLER", "AGITATOR", "EGG RINGS", "TWEEZER", "WHITE HUTZLER",
    "SUPER SPATULA", "EGG SPATULA (ROUND EGG)", "EGG WHIP", "EGG FORK",
    "FOLDED RECTANGULAR RING", "RECTANGULAR RING (SCRAMBLE EGG)",
    "HOTCAKE SPATULA", "HOTCAKE DISPENSER", "EGG COOKER SCRAPER",
    "SPOODLE", "ICE SCOOPER (BEVCELL)", "DOUBLE JIGGER", "FEED TUBE",
    "GRILL ORGANIZER", "ORGANIZE OPS CABINET",
];

// --- FLASH SESSION LOGIC ---
$history = [];
if (isset($_SESSION['history_flash'])) {
    $history = $_SESSION['history_flash'];
    unset($_SESSION['history_flash']);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_checklist'])) {
    $previous_history = json_decode($_POST['previous_history'] ?? '[]', true);

    $submission = [
        'id'          => uniqid('entry-'),
        'timestamp'   => date("F j, Y, g:i a"),
        'shift'       => $_POST['shift'] ?? 'N/A',
        'ct_name'     => filter_input(INPUT_POST, 'ct_name', FILTER_SANITIZE_STRING),
        'mic_name'    => filter_input(INPUT_POST, 'mic_name', FILTER_SANITIZE_STRING),
        'ct_signature'=> $_POST['ct_signature'] ?? '',
        'mic_signature'=> $_POST['mic_signature'] ?? '',
        'note'        => filter_input(INPUT_POST, 'note', FILTER_SANITIZE_STRING),
        'status'      => $_POST['status'] ?? [],
    ];
    
    array_unshift($previous_history, $submission);
    
    $_SESSION['history_flash'] = $previous_history;

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>544 Small Equipment Checklist</title>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- jsPDF and html2canvas Libraries for PDF Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- --- INLINE CSS --- -->
    <style>
        /* --- Google Font & CSS Reset --- */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        :root {
            --primary-color:rgb(255, 255, 255);
            --primary-hover: #004170;
            --secondary-color: #C80F2E;
            --text-color: #333;
            --light-gray: #FEE600;
            --border-color: #e1e1e1;
            --white: #ffffff;
            --status-functioning: #28a745;
            --status-not-functioning: #ffc107;
            --status-missing: #dc3545;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --info-color: #17a2b8;
            --info-hover: #117a8b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-gray);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container { max-width: 900px; margin: 2rem auto; padding: 1rem; }
        h1 { text-align: center; color: #C80F2E; margin-bottom: 2rem; font-weight: 700; }
        h2 { color: #C80F2E; border-bottom: 2px solid var(--secondary-color); padding-bottom: 0.5rem; margin-bottom: 1.5rem; }

        /* --- Form & History Styling --- */
        .checklist-form, .history-entry {
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        .history-entry.pdf-capture { box-shadow: none; border: 1px solid var(--border-color); }
        .form-section { border: 1px solid var(--border-color); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; }
        .form-section legend { font-size: 1.2rem; font-weight: 600; color: #C80F2E; padding: 0 0.5rem; margin-left: 1rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        input[type="text"], textarea {
            width: 100%; padding: 0.75rem;
            border: 1px solid var(--border-color); border-radius: 6px;
            font-family: 'Poppins', sans-serif; transition: border-color 0.3s, box-shadow 0.3s;
        }
        input[type="text"]:focus, textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--secondary-color); }
        textarea { resize: vertical; min-height: 80px; }
        .input-group { margin-bottom: 1.5rem; }
        .shift-options { display: flex; flex-wrap: wrap; gap: 1rem; }
        .shift-options label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 400; }
        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem; }
        .signature-pad canvas { border: 2px dashed var(--border-color); border-radius: 6px; cursor: crosshair; width: 100%; }
        .signature-pad .clear-btn { margin-top: 0.5rem; background: none; border: 1px solid var(--border-color); color: var(--text-color); }

        /* --- Tables & Lists --- */
        .equipment-table { width: 100%; border-collapse: collapse; }
        .equipment-table th, .equipment-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .equipment-table th { background-color: var(--secondary-color); font-weight: 600; color: var(--primary-color); }
        .equipment-table td:nth-child(1) { font-weight: 500; }
        .equipment-table td { text-align: center; }
        .equipment-table td:first-child { text-align: left; }
        .status-list { list-style: none; padding: 0; columns: 2; gap: 1rem; }
        .status-list li { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; }
        .status-pill { padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.8rem; font-weight: 500; color: var(--white); white-space: nowrap; }
        .status-pill.functioning { background-color: var(--status-functioning); }
        .status-pill.not-functioning { background-color: var(--status-not-functioning); }
        .status-pill.missing { background-color: var(--status-missing); }

        /* --- Buttons --- */
        .button-group {
            display: flex; gap: 1rem; flex-wrap: wrap; position: sticky;
            bottom: 0; background-color: #fff; padding: 1rem;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1); z-index: 1000;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1.5rem; border: none; border-radius: 6px;
            font-family: 'Poppins', sans-serif; font-size: 1rem; font-weight: 500;
            text-decoration: none; cursor: pointer; transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn .fa-spinner { display: none; }
        .btn.loading .fa-spinner { display: inline-block; animation: fa-spin 1s infinite linear; }
        .btn.loading span { display: none; }
        .btn-primary { background-color: rgb(31, 121, 204); color: #ffffff; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-secondary { background-color: #6c757d; color: var(--white); }
        .btn-secondary:hover { background-color: #5a6268; }
        .btn-info { background-color: var(--info-color); color: var(--white); font-size: 0.9rem; padding: 0.5rem 1rem; }
        .btn-info:hover { background-color: var(--info-hover); }

        /* --- History Details --- */
        .history-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .history-entry-header {
            display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem;
            margin-bottom: 1rem; padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            align-items: center; justify-content: space-between;
        }
        .history-entry-header .details { display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem; }
        .history-entry-header strong { color: #C80F2E; }
        .history-note {
            background: #f8f9fa; border-left: 4px solid var(--info-color);
            padding: 1rem; border-radius: 6px; margin-bottom: 1rem;
            font-style: italic; word-wrap: break-word;
        }
        .history-signatures {
            display: flex; gap: 2rem; margin-top: 1rem;
            border-top: 1px solid var(--border-color); padding-top: 1rem; flex-wrap: wrap;
        }
        .history-signatures div { text-align: center; }
        .history-signatures img { background: #fdfdfd; border: 1px solid var(--border-color); border-radius: 4px; max-width: 150px; height: auto; }

        /* --- Responsive Design --- */
        @media (max-width: 768px) {
            .container { margin: 1rem auto; padding: 0.5rem; }
            h1 { font-size: 1.8rem; }
            .checklist-form, .history-entry { padding: 1.5rem; }
            .signature-grid, .status-list { grid-template-columns: 1fr; columns: 1; }
            .equipment-table thead { display: none; }
            .equipment-table, .equipment-table tbody, .equipment-table tr, .equipment-table td { display: block; width: 100%; }
            .equipment-table tr { margin-bottom: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; }
            .equipment-table td { display: flex; justify-content: space-between; align-items: center; text-align: right; padding: 0.75rem 0; border: none; }
            .equipment-table td:first-child { background-color: var(--secondary-color); padding: 0.75rem; margin: -1rem -1rem 1rem -1rem; border-radius: 8px 8px 0 0; font-weight: 600; color: var(--primary-color); }
            .equipment-table td::before { content: attr(data-label); font-weight: 500; text-align: left; margin-right: 1rem; }
            .equipment-table td:first-child::before { content: ""; }
        }

        /* --- Print Styles --- */
        @media print {
            /* Default B&W styles */
            body { background-color: var(--white); color: #000; }
            .container { max-width: 100%; margin: 0; padding: 0; }
            .checklist-form, .btn, .history-header { display: none; }
            h1 { display: none; }
            h2 { text-align: center; margin-top: 0; }
            .history-entry { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
            .status-list { columns: 2; }
            .download-pdf-btn { display: none !important; }

            /* --- NEW: STYLES FOR COLOR PRINTING --- */
            /* These styles ONLY apply when the 'color-print-active' class is on the body */
            body.color-print-active {
                /* This is the magic property that tells the browser to print backgrounds! */
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background-color: var(--light-gray); /* Restore the yellow page background */
            }
            body.color-print-active .history-entry { background-color: var(--white); }
            body.color-print-active h2 { color: #C80F2E; }
            body.color-print-active .status-pill { color: var(--white) !important; }
            body.color-print-active .status-pill.functioning { background-color: var(--status-functioning) !important; }
            body.color-print-active .status-pill.not-functioning { background-color: var(--status-not-functioning) !important; }
            body.color-print-active .status-pill.missing { background-color: var(--status-missing) !important; }
            body.color-print-active .history-note {
                background-color: #f8f9fa !important;
                border-left: 4px solid var(--info-color) !important;
            }
            body.color-print-active .history-signatures img {
                background: #fdfdfd; border: 1px solid var(--border-color);
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <h1><i class="fa-solid fa-clipboard-check"></i> 544 Small Equipment Checklist</h1>

        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="checklist-form">
            <input type="hidden" name="previous_history" value="<?= htmlspecialchars(json_encode($history)) ?>">
            
            <fieldset class="form-section">
                <!-- Form content unchanged -->
                <legend>Shift Information</legend>
                <div class="shift-options">
                    <label><input type="radio" name="shift" value="Opening Shift" required> Opening</label>
                    <label><input type="radio" name="shift" value="Mid Shift"> Mid</label>
                    <label><input type="radio" name="shift" value="Closing Shift"> Closing</label>
                    <label><input type="radio" name="shift" value="Graveyard Shift"> Graveyard</label>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Equipment Status</legend>
                <table class="equipment-table">
                    <thead><tr><th>Equipment</th><th>Functioning</th><th>Not Functioning</th><th>Missing</th></tr></thead>
                    <tbody>
                        <?php foreach ($equipmentList as $item): ?>
                        <tr>
                            <td data-label="Equipment"><?= htmlspecialchars($item) ?></td>
                            <td data-label="Functioning"><input type="radio" name="status[<?= htmlspecialchars($item) ?>]" value="Functioning" required></td>
                            <td data-label="Not Functioning"><input type="radio" name="status[<?= htmlspecialchars($item) ?>]" value="Not Functioning"></td>
                            <td data-label="Missing"><input type="radio" name="status[<?= htmlspecialchars($item) ?>]" value="Missing"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </fieldset>

            <fieldset class="form-section">
                <legend>Signatures & Notes</legend>
                <div class="input-group"><label for="note">Additional Notes:</label><textarea name="note" id="note" placeholder="Report any issues or add comments..."></textarea></div>
                <div class="signature-grid">
                    <div class="input-group"><label for="ct_name">CT Name:</label><input type="text" id="ct_name" name="ct_name" required></div>
                    <div class="input-group"><label for="mic_name">MIC Name:</label><input type="text" id="mic_name" name="mic_name" required></div>
                </div>
                <div class="signature-grid">
                    <div class="signature-pad"><label>CT Signature:</label><canvas id="ctCanvas" height="150" data-input="ctSignature"></canvas><input type="hidden" name="ct_signature" id="ctSignature"><button type="button" class="btn clear-btn"><i class="fa-solid fa-eraser"></i> Clear</button></div>
                    <div class="signature-pad"><label>MIC Signature:</label><canvas id="micCanvas" height="150" data-input="micSignature"></canvas><input type="hidden" name="mic_signature" id="micSignature"><button type="button" class="btn clear-btn"><i class="fa-solid fa-eraser"></i> Clear</button></div>
                </div>
            </fieldset>

            <div class="button-group">
                <button type="submit" name="submit_checklist" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Checklist</button>
                <!-- NEW: Updated button for color printing -->
                <button type="button" id="printColorBtn" class="btn btn-secondary"><i class="fa-solid fa-print"></i> Print Page (Color)</button>
            </div>
        </form>

        <!-- Submission History (content logic unchanged) -->
        <?php if (!empty($history)): ?>
            <div class="history-container">
                <div class="history-header"><h2><i class="fa-solid fa-clock-rotate-left"></i> Submission History</h2></div>
                <?php foreach ($history as $entry): ?>
                    <div class="history-entry" id="<?= htmlspecialchars($entry['id']) ?>">
                        <div class="history-entry-header">
                            <div class="details">
                                <div><strong>Shift:</strong> <?= htmlspecialchars($entry['shift']) ?></div>
                                <div><strong>Time:</strong> <?= htmlspecialchars($entry['timestamp']) ?></div>
                                <div><strong>CT:</strong> <?= htmlspecialchars($entry['ct_name']) ?></div>
                                <div><strong>MIC:</strong> <?= htmlspecialchars($entry['mic_name']) ?></div>
                            </div>
                            <button class="btn btn-info download-pdf-btn" data-entry-id="<?= htmlspecialchars($entry['id']) ?>" data-shift="<?= htmlspecialchars($entry['shift']) ?>" data-timestamp="<?= htmlspecialchars($entry['timestamp']) ?>">
                                <i class="fa-solid fa-file-pdf"></i><span>Download PDF</span><i class="fa-solid fa-spinner"></i>
                            </button>
                        </div>
                        <?php if (!empty($entry['note'])): ?><p class="history-note"><strong>Note:</strong> <?= nl2br(htmlspecialchars($entry['note'])) ?></p><?php endif; ?>
                        <ul class="status-list">
                            <?php foreach ($entry['status'] as $name => $status): ?>
                                <?php $status_class = strtolower(str_replace(' ', '-', $status)); ?>
                                <li><span><?= htmlspecialchars($name) ?></span><span class="status-pill <?= $status_class ?>"><?= htmlspecialchars($status) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!empty($entry['ct_signature']) || !empty($entry['mic_signature'])): ?>
                            <div class="history-signatures">
                                <?php if (!empty($entry['ct_signature'])): ?><div><strong>CT Signature</strong><br><img src="<?= $entry['ct_signature'] ?>" alt="CT Signature"></div><?php endif; ?>
                                <?php if (!empty($entry['mic_signature'])): ?><div><strong>MIC Signature</strong><br><img src="<?= $entry['mic_signature'] ?>" alt="MIC Signature"></div><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- --- JAVASCRIPTS --- -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Signature Pad Logic ---
        const signaturePads = document.querySelectorAll('.signature-pad');
        signaturePads.forEach(pad => {
            const canvas = pad.querySelector('canvas'), clearButton = pad.querySelector('.clear-btn'), hiddenInput = document.getElementById(canvas.dataset.input); if (!canvas || !clearButton || !hiddenInput) return; canvas.width = canvas.offsetWidth; const ctx = canvas.getContext('2d'); let isDrawing = false, lastX = 0, lastY = 0; function draw(e) { if (!isDrawing) return; const rect = canvas.getBoundingClientRect(); const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left; const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top; ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();[lastX, lastY] = [x, y]; } function startPosition(e) { isDrawing = true; const rect = canvas.getBoundingClientRect();[lastX, lastY] = [(e.touches ? e.touches[0].clientX : e.clientX) - rect.left, (e.touches ? e.touches[0].clientY : e.clientY) - rect.top]; draw(e); } function endPosition() { if (!isDrawing) return; isDrawing = false; hiddenInput.value = canvas.toDataURL('image/png'); } function clearCanvas() { ctx.clearRect(0, 0, canvas.width, canvas.height); hiddenInput.value = ''; } ctx.strokeStyle = '#000'; ctx.lineWidth = 2; ctx.lineCap = 'round'; canvas.addEventListener('mousedown', startPosition); canvas.addEventListener('mouseup', endPosition); canvas.addEventListener('mouseout', endPosition); canvas.addEventListener('mousemove', draw); canvas.addEventListener('touchstart', (e) => { e.preventDefault(); startPosition(e); }); canvas.addEventListener('touchend', (e) => { e.preventDefault(); endPosition(e); }); canvas.addEventListener('touchmove', e => { e.preventDefault(); draw(e); }); clearButton.addEventListener('click', clearCanvas); window.addEventListener('resize', () => { const currentData = canvas.toDataURL(); canvas.width = canvas.offsetWidth; const img = new Image(); img.src = currentData; img.onload = () => ctx.drawImage(img, 0, 0); ctx.strokeStyle = '#000'; ctx.lineWidth = 2; ctx.lineCap = 'round'; });
        });
        
        // --- NEW: Color Printing Logic ---
        const printColorButton = document.getElementById('printColorBtn');
        if (printColorButton) {
            printColorButton.addEventListener('click', () => {
                // Add class to body to trigger special print styles
                document.body.classList.add('color-print-active');
                // Open print dialog
                window.print();
            });
        }
        // After print dialog is closed, remove the class to clean up
        window.addEventListener('afterprint', () => {
            document.body.classList.remove('color-print-active');
        });
    });

    // --- PDF Generation Logic ---
    document.addEventListener('click', function(e) {
        if (e.target.matches('.download-pdf-btn') || e.target.closest('.download-pdf-btn')) {
            const button = e.target.closest('.download-pdf-btn');
            const entryId = button.dataset.entryId;
            const elementToCapture = document.getElementById(entryId);
            if (!elementToCapture) { console.error('History entry element not found!'); return; }

            button.disabled = true; button.classList.add('loading'); elementToCapture.classList.add('pdf-capture'); const downloadButtonInEntry = elementToCapture.querySelector('.download-pdf-btn'); if(downloadButtonInEntry) downloadButtonInEntry.style.display = 'none';
            html2canvas(elementToCapture, { scale: 2, useCORS: true, logging: false }).then(canvas => {
                elementToCapture.classList.remove('pdf-capture'); if(downloadButtonInEntry) downloadButtonInEntry.style.display = 'inline-flex';
                const imgData = canvas.toDataURL('image/png'); const { jsPDF } = window.jspdf; const pdfWidth = 210, margin = 10; const contentWidth = pdfWidth - (margin * 2); const contentHeight = contentWidth / (canvas.width / canvas.height); const pdf = new jsPDF('p', 'mm', 'a4'); pdf.addImage(imgData, 'PNG', margin, margin, contentWidth, contentHeight); const shift = button.dataset.shift.replace(/\s+/g, '-'); const timestamp = button.dataset.timestamp.replace(/[^a-z0-9]/gi, '_').toLowerCase(); const filename = `Checklist_${shift}_${timestamp}.pdf`; pdf.save(filename);
            }).catch(err => { console.error("PDF Generation Error:", err); alert("Sorry, there was an error generating the PDF."); }).finally(() => { button.disabled = false; button.classList.remove('loading'); });
        }
    });
    </script>
</body>
</html>