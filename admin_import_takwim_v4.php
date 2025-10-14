<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Takwim Kursus - Enhanced Version</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 40px; }
        
        .upload-zone {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 60px 20px;
            text-align: center;
            background: #f8f9ff;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 30px;
        }
        .upload-zone:hover {
            border-color: #764ba2;
            background: #f0f2ff;
            transform: translateY(-5px);
        }
        .upload-zone.dragover {
            background: #e8ebff;
            border-color: #5a67d8;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 5px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Sheet Selection */
        .sheet-selection {
            display: none;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .sheet-option {
            padding: 15px;
            margin: 10px 0;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .sheet-option:hover {
            border-color: #667eea;
        }
        .sheet-option.selected {
            border-color: #667eea;
            background: #f0f2ff;
        }
        
        /* Preview Section */
        #preview-section {
            display: none;
            margin-top: 30px;
        }
        .preview-header {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .preview-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .stat-card h3 { font-size: 24px; color: #667eea; }
        .stat-card p { color: #666; font-size: 14px; }
        
        /* Enhanced Table */
        .preview-table-container {
            overflow-x: auto;
            max-height: 600px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        th {
            background: #667eea;
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:hover { background: #f7fafc; }
        tr.duplicate { background: #fff3cd; }
        tr.edited { background: #d1ecf1; }
        
        /* Editable Fields */
        .editable {
            cursor: pointer;
            border: 1px dashed transparent;
            padding: 4px;
            border-radius: 4px;
        }
        .editable:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .editable-input {
            width: 100%;
            padding: 4px;
            border: 1px solid #667eea;
            border-radius: 4px;
            font-size: 14px;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            display: none;
            padding: 15px;
            background: #e7f3ff;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        /* Duplicate Handling */
        .duplicate-actions {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .duplicate-item {
            background: white;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
        
        .progress-bar {
            display: none;
            width: 100%;
            height: 30px;
            background: #e2e8f0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .file-info {
            display: none;
            background: #f0f2ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .file-info.show { display: block; }
        
        /* Action Buttons Container */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        /* Template Download */
        .template-download {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #e7f3ff;
            border-radius: 10px;
        }
        
        /* Import History */
        .import-history {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .history-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Import Takwim Kursus - Enhanced</h1>
            <p>With Template Validation, Editing & Duplicate Handling</p>
        </div>
        
        <div class="content">
            <div id="alert-container"></div>
            
            <!-- Template Download -->
            <div class="template-download">
                <h3>📥 Download Template Excel</h3>
                <p>Gunakan template ini untuk memastikan format yang betul</p>
                <button class="btn btn-success" onclick="downloadTemplate()">
                    📋 Download Template
                </button>
            </div>
            
            <!-- Import History -->
            <div class="import-history" id="importHistorySection" style="display:none">
                <h3>📚 Sejarah Import</h3>
                <div id="importHistoryList"></div>
            </div>
            
            <div class="form-group">
                <label>Tahun:</label>
                <input type="number" id="tahun" min="2020" max="2030" value="<?php echo date('Y'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Semester:</label>
                <select id="semester" required>
                    <option value="">Pilih Semester</option>
                    <option value="JAN-JUN">JAN - JUN</option>
                    <option value="JULAI-DIS">JULAI - DIS</option>
                </select>
            </div>
            
            <div class="upload-zone" id="uploadZone">
                <input type="file" id="fileInput" accept=".xlsx,.xls" style="display:none">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <h3 style="margin: 20px 0 10px; color: #667eea;">Upload Excel File</h3>
                <p style="color: #666;">Klik atau seret fail .xlsx atau .xls ke sini</p>
            </div>
            
            <!-- Sheet Selection -->
            <div class="sheet-selection" id="sheetSelection">
                <h3>Pilih Lembaran Excel</h3>
                <div id="sheetOptions"></div>
            </div>
            
            <div class="file-info" id="fileInfo"></div>
            
            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <h4>Bulk Actions</h4>
                <button class="btn btn-warning btn-sm" onclick="bulkDelete()">Padam Dipilih</button>
                <button class="btn btn-secondary btn-sm" onclick="clearSelection()">Batal Pilihan</button>
                <span id="selectedCount">0 kursus dipilih</span>
            </div>
            
            <!-- Duplicate Handling -->
            <div class="duplicate-actions" id="duplicateActions" style="display:none">
                <h4>⚠️ Duplikat Dijumpai</h4>
                <div id="duplicateList"></div>
            </div>
            
            <div id="preview-section">
                <div class="preview-header">
                    <h2>Preview Data</h2>
                    <div class="preview-stats" id="previewStats"></div>
                </div>
                
                <div class="preview-table-container">
                    <table id="previewTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                <th>BIL</th>
                                <th>MINGGU & TARIKH</th>
                                <th>NAMA KURSUS</th>
                                <th>PLATFORM</th>
                                <th>KAPASITI</th>
                                <th>PRA-SYARAT</th>
                                <th>KATEGORI</th>
                                <th>TINDAKAN</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" id="importBtn" onclick="importData()">
                        ✓ Confirm & Import Data
                    </button>
                    <button class="btn btn-secondary" onclick="resetForm()">
                        ↻ Muat Semula
                    </button>
                </div>
            </div>
            
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill">0%</div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        let validCourses = [];
        let selectedSheetIndex = 0;
        let editingCell = null;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadImportHistory();
        });
        
        // Upload zone functionality
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        
        uploadZone.addEventListener('click', () => fileInput.click());
        
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (file) handleFile(file);
        });
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) handleFile(file);
        });
        
        function handleFile(file) {
            const validExtensions = ['xlsx', 'xls'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!validExtensions.includes(fileExtension)) {
                showAlert('Ralat: Format fail tidak sah. Sila upload fail .xlsx atau .xls', 'error');
                return;
            }
            
            const fileInfo = document.getElementById('fileInfo');
            fileInfo.innerHTML = `<strong>📄 File:</strong> ${file.name}<br><strong>📦 Size:</strong> ${(file.size / 1024).toFixed(2)} KB<br><strong>⏰ Processing...</strong>`;
            fileInfo.classList.add('show');
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    
                    // Check for multiple sheets
                    if (workbook.SheetNames.length > 1) {
                        showSheetSelection(workbook);
                    } else {
                        selectedSheetIndex = 0;
                        processWorkbook(workbook);
                    }
                    
                } catch (error) {
                    console.error('Error reading file:', error);
                    showAlert('Ralat membaca fail Excel: ' + error.message, 'error');
                    fileInfo.innerHTML = `<strong>📄 File:</strong> ${file.name}<br><strong>❌ Ralat: ${error.message}</strong>`;
                }
            };
    
    reader.onerror = function(e) {
        console.error('FileReader error:', e);
        showAlert('Ralat membaca fail: ' + e.target.error, 'error');
        fileInfo.innerHTML = `<strong>📄 File:</strong> ${file.name}<br><strong>❌ Ralat membaca fail</strong>`;
        fileInfo.classList.remove('show');
    };
    
    reader.readAsArrayBuffer(file);
}
        
        function showSheetSelection(workbook) {
            const sheetSelection = document.getElementById('sheetSelection');
            const sheetOptions = document.getElementById('sheetOptions');
            
            sheetOptions.innerHTML = '';
            workbook.SheetNames.forEach((sheetName, index) => {
                const sheet = workbook.Sheets[sheetName];
                const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
                const detectedSemester = detectSemesterFromData(jsonData);
                
                const sheetOption = document.createElement('div');
                sheetOption.className = 'sheet-option';
                sheetOption.innerHTML = `
                    <strong>${sheetName}</strong>
                    <div style="font-size: 12px; color: #666;">
                        Baris: ${jsonData.length} | Semester Terkesan: ${detectedSemester}
                    </div>
                `;
                sheetOption.onclick = () => {
                    document.querySelectorAll('.sheet-option').forEach(opt => opt.classList.remove('selected'));
                    sheetOption.classList.add('selected');
                    selectedSheetIndex = index;
                };
                sheetOptions.appendChild(sheetOption);
            });
            
            // Auto-select first sheet
            sheetOptions.firstChild?.click();
            sheetSelection.style.display = 'block';
            
            fileInfo.innerHTML += '<br><strong>📑 Pilih lembaran di atas</strong>';
        }
        
        function detectSemesterFromData(data) {
            const dateText = JSON.stringify(data).toUpperCase();
            if (dateText.includes('JAN') || dateText.includes('JUN')) return 'JAN-JUN';
            if (dateText.includes('JUL') || dateText.includes('DIS')) return 'JULAI-DIS';
            return 'Tidak Dikesan';
        }

        function debugExcelData(data) {
            console.log('=== DEBUG EXCEL DATA ===');
            console.log('Total rows:', data.length);
            data.forEach((row, index) => {
                console.log(`Row ${index}:`, row);
            });
            console.log('=== END DEBUG ===');
        }
        
        function processWorkbook(workbook) {
            const sheetName = workbook.SheetNames[selectedSheetIndex];
            const sheet = workbook.Sheets[sheetName];
            
            try {
                const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
                
                // Validate template format
                if (!validateTemplateFormat(jsonData)) {
                    showAlert('Format template tidak betul. Sila gunakan template yang disediakan.', 'error');
                    document.getElementById('fileInfo').innerHTML += '<br><strong>❌ Format tidak sah</strong>';
                    return;
                }
                
                parseAndPreview(jsonData);
                document.getElementById('fileInfo').innerHTML = 
                    `<strong>📄 File:</strong> ${fileInput.files[0].name}<br>` +
                    `<strong>📦 Size:</strong> ${(fileInput.files[0].size / 1024).toFixed(2)} KB<br>` +
                    `<strong>✅ Lembaran "${sheetName}" berjaya diproses!</strong><br>` +
                    `<strong>📊 ${validCourses.length} kursus dijumpai</strong>`;
                    
            } catch (error) {
                console.error('Error processing workbook:', error);
                showAlert('Ralat memproses lembaran Excel: ' + error.message, 'error');
                document.getElementById('fileInfo').innerHTML += '<br><strong>❌ Ralat memproses</strong>';
            }
        }
        
        function validateTemplateFormat(data) {
            if (!data || data.length === 0) {
                showAlert('Fail Excel kosong atau tidak mengandungi data.', 'error');
                return false;
            }
            
            // Check for required headers in first few rows
            let hasRequiredHeaders = false;
            for (let i = 0; i < Math.min(3, data.length); i++) {
                const row = data[i];
                if (row && row.length > 0) {
                    const headerText = row.join(' ').toUpperCase();
                    if (headerText.includes('BIL') && 
                        (headerText.includes('MINGGU') || headerText.includes('TARIKH')) &&
                        headerText.includes('KURSUS')) {
                        hasRequiredHeaders = true;
                        break;
                    }
                }
            }
            
            if (!hasRequiredHeaders) {
                showAlert('Format template tidak betul. Header diperlukan: BIL, MINGGU/TARIKH, KURSUS', 'error');
                return false;
            }
            
            return true;
        }
        
        function parseAndPreview(data) {
            console.log('Raw Excel data:', data); // Debug log
            
            let headerRowIndex = -1;
            for (let i = 0; i < data.length; i++) {
                if (data[i] && data[i][0] && 
                    (data[i][0].toString().toUpperCase().includes('BIL') || 
                    data[i][0].toString().toUpperCase().includes('NO'))) {
                    headerRowIndex = i;
                    break;
                }
            }
            
            if (headerRowIndex === -1) {
                showAlert('Header row tidak dijumpai. Pastikan Excel mengandungi header BIL, MINGGU & TARIKH, KURSUS, dll.', 'error');
                return;
            }
            
            validCourses = [];
            for (let i = headerRowIndex + 1; i < data.length; i++) {
                const row = data[i];
                
                // Skip empty rows
                if (!row || row.length === 0) continue;
                
                // Check if this is a valid course row (has BIL number and course name)
                if (row[0] !== undefined && row[0] !== null && row[0] !== '' && 
                    row[2] !== undefined && row[2] !== null && row[2] !== '') {
                    
                    validCourses.push({
                        bil: row[0],
                        minggu_tarikh: row[1] || '',
                        nama_kursus: row[2]?.toString() || '',
                        platform: (row[3]?.toString() || 'BERSEMUKA').toUpperCase(),
                        kapasiti: parseInt(row[4]) || 30,
                        pra_syarat: row[5]?.toString() || '',
                        isDuplicate: false,
                        duplicateAction: 'skip',
                        isSelected: false,
                        isEdited: false
                    });
                }
            }
            
            console.log('Parsed courses:', validCourses); // Debug log
            
            if (validCourses.length === 0) {
                showAlert('Tiada kursus yang sah dijumpai dalam fail Excel. Pastikan data berada dalam format yang betul.', 'error');
                return;
            }
            
            displayPreview();
            checkDuplicates();
        }
        
        function displayPreview() {
            const statsHtml = `
                <div class="stat-card"><h3>${validCourses.length}</h3><p>Total Kursus</p></div>
                <div class="stat-card"><h3>${validCourses.filter(c => c.platform === 'BERSEMUKA').length}</h3><p>Bersemuka</p></div>
                <div class="stat-card"><h3>${validCourses.filter(c => c.platform !== 'BERSEMUKA').length}</h3><p>Online/Hybrid</p></div>
                <div class="stat-card"><h3>${validCourses.filter(c => c.isDuplicate).length}</h3><p>Duplikat</p></div>
            `;
            document.getElementById('previewStats').innerHTML = statsHtml;
            
            const previewBody = document.getElementById('previewBody');
            previewBody.innerHTML = '';
            
            validCourses.forEach((course, index) => {
                const kategori = getCourseCategory(course.nama_kursus);
                const rowClass = course.isDuplicate ? 'duplicate' : (course.isEdited ? 'edited' : '');
                
                previewBody.innerHTML += `
                    <tr class="${rowClass}" id="row-${index}">
                        <td><input type="checkbox" class="row-select" onchange="toggleRowSelection(${index})" ${course.isSelected ? 'checked' : ''}></td>
                        <td>${course.bil}</td>
                        <td class="editable" onclick="editCell(${index}, 'minggu_tarikh')">${course.minggu_tarikh}</td>
                        <td class="editable" onclick="editCell(${index}, 'nama_kursus')">${course.nama_kursus}</td>
                        <td class="editable" onclick="editCell(${index}, 'platform')">${course.platform}</td>
                        <td class="editable" onclick="editCell(${index}, 'kapasiti')">${course.kapasiti}</td>
                        <td class="editable" onclick="editCell(${index}, 'pra_syarat')">${course.pra_syarat}</td>
                        <td>${kategori}</td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteRow(${index})">🗑️</button>
                            ${course.isDuplicate ? `
                                <select class="btn btn-warning btn-sm" onchange="setDuplicateAction(${index}, this.value)">
                                    <option value="skip">Skip</option>
                                    <option value="overwrite">Overwrite</option>
                                </select>
                            ` : ''}
                        </td>
                    </tr>
                `;
            });
            
            document.getElementById('preview-section').style.display = 'block';
            updateBulkActions();
        }
        
        function editCell(rowIndex, field) {
            if (editingCell) return; // Prevent multiple edits
            
            const cell = document.querySelector(`#row-${rowIndex} td.${field}`);
            const currentValue = validCourses[rowIndex][field];
            
            const input = document.createElement('input');
            input.type = field === 'kapasiti' ? 'number' : 'text';
            input.className = 'editable-input';
            input.value = currentValue;
            
            cell.innerHTML = '';
            cell.appendChild(input);
            input.focus();
            
            editingCell = { rowIndex, field, cell };
            
            input.addEventListener('blur', function() {
                finishEdit(input.value);
            });
            
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    finishEdit(input.value);
                }
            });
        }
        
        function finishEdit(newValue) {
            if (!editingCell) return;
            
            const { rowIndex, field, cell } = editingCell;
            validCourses[rowIndex][field] = newValue;
            validCourses[rowIndex].isEdited = true;
            
            cell.innerHTML = newValue;
            cell.className = 'editable edited';
            cell.onclick = () => editCell(rowIndex, field);
            
            editingCell = null;
            displayPreview(); // Refresh to update category if course name changed
        }
        
        function deleteRow(index) {
            if (confirm('Adakah anda pasti ingin memadam kursus ini?')) {
                validCourses.splice(index, 1);
                displayPreview();
            }
        }
        
        function toggleRowSelection(index) {
            validCourses[index].isSelected = !validCourses[index].isSelected;
            updateBulkActions();
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll').checked;
            validCourses.forEach(course => course.isSelected = selectAll);
            displayPreview();
        }
        
        function updateBulkActions() {
            const selectedCount = validCourses.filter(c => c.isSelected).length;
            const bulkActions = document.getElementById('bulkActions');
            
            if (selectedCount > 0) {
                bulkActions.style.display = 'block';
                document.getElementById('selectedCount').textContent = `${selectedCount} kursus dipilih`;
            } else {
                bulkActions.style.display = 'none';
            }
        }
        
        function bulkDelete() {
            const selectedCount = validCourses.filter(c => c.isSelected).length;
            if (selectedCount === 0) return;
            
            if (confirm(`Adakah anda pasti ingin memadam ${selectedCount} kursus?`)) {
                validCourses = validCourses.filter(c => !c.isSelected);
                displayPreview();
            }
        }
        
        function clearSelection() {
            validCourses.forEach(course => course.isSelected = false);
            document.getElementById('selectAll').checked = false;
            displayPreview();
        }
        
        function setDuplicateAction(index, action) {
            validCourses[index].duplicateAction = action;
        }
        
        async function checkDuplicates() {
            const tahun = document.getElementById('tahun').value;
            const semester = document.getElementById('semester').value;
            
            if (!tahun || !semester) return;
            
            try {
                const response = await fetch('api_check_duplicates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        tahun: parseInt(tahun), 
                        semester: semester, 
                        courses: validCourses 
                    })
                });
                
                const result = await response.json();
                
                if (result.duplicates && result.duplicates.length > 0) {
                    showDuplicates(result.duplicates);
                }
            } catch (error) {
                console.error('Error checking duplicates:', error);
            }
        }
        
        function showDuplicates(duplicates) {
            const duplicateActions = document.getElementById('duplicateActions');
            const duplicateList = document.getElementById('duplicateList');
            
            duplicateList.innerHTML = '';
            
            duplicates.forEach(dup => {
                const duplicateItem = document.createElement('div');
                duplicateItem.className = 'duplicate-item';
                duplicateItem.innerHTML = `
                    <strong>${dup.existing.nama_kursus_takwim}</strong> - Siri ${dup.existing.siri}<br>
                    <small>Tarikh: ${dup.existing.tarikh_mula_rancangan} hingga ${dup.existing.tarikh_akhir_rancangan}</small>
                    <div style="margin-top: 8px;">
                        <select onchange="setDuplicateAction(${dup.index}, this.value)" class="btn btn-warning btn-sm">
                            <option value="skip">Skip (Simpan yang sedia ada)</option>
                            <option value="overwrite">Overwrite (Gantikan data sedia ada)</option>
                        </select>
                    </div>
                `;
                duplicateList.appendChild(duplicateItem);
                
                // Mark as duplicate in main array
                validCourses[dup.index].isDuplicate = true;
            });
            
            duplicateActions.style.display = 'block';
            displayPreview(); // Refresh to show duplicate styling
        }
        
        function getCourseCategory(courseName) {
            courseName = courseName.toUpperCase();
            if (courseName.includes('RENANG') || courseName.includes('AKUATIK')) return 'Program Akuatik';
            if (courseName.includes('ASCENDING') || courseName.includes('DESCENDING')) return 'Program Teknik Tali';
            if (courseName.includes('FRLS') || courseName.includes('FIRST RESPONDER')) return 'Program Kesihatan';
            if (courseName.includes('BAKAL PEGAWAI') || courseName.includes('KEPIMPINAN')) return 'Program Kepimpinan';
            if (courseName.includes('KECERGASAN')) return 'Program Kecergasan';
            if (courseName.includes('BENCANA') || courseName.includes('SAR')) return 'Program Keselamatan';
            if (courseName.includes('ASAS PERTAHANAN AWAM')) return 'Program Asas';
            return 'Program Lain-lain';
        }
        
        async function importData() {
            const tahun = document.getElementById('tahun').value;
            const semester = document.getElementById('semester').value;
            
            if (!tahun || !semester) {
                showAlert('Sila pilih tahun dan semester!', 'error');
                return;
            }
            
            if (validCourses.length === 0) {
                showAlert('Tiada data untuk diimport!', 'error');
                return;
            }
            
            const importBtn = document.getElementById('importBtn');
            importBtn.disabled = true;
            importBtn.textContent = 'Importing...';
            
            const progressBar = document.getElementById('progressBar');
            const progressFill = document.getElementById('progressFill');
            progressBar.style.display = 'block';
            
            try {
                const processedCourses = validCourses.map(course => {
                    let mingguNo = null;
                    const mingguMatch = course.minggu_tarikh.match(/MINGGU\s+(\d+)/i);
                    if (mingguMatch) mingguNo = 'MINGGU ' + mingguMatch[1];
                    
                    let tarikhMula = null, tarikhAkhir = null;
                    const dateMatch = course.minggu_tarikh.match(/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})/i);
                    if (dateMatch) {
                        const year = dateMatch[4].length == 2 ? '20' + dateMatch[4] : dateMatch[4];
                        tarikhMula = `${year}-${dateMatch[3].padStart(2, '0')}-${dateMatch[1].padStart(2, '0')}`;
                        tarikhAkhir = `${year}-${dateMatch[3].padStart(2, '0')}-${dateMatch[2].padStart(2, '0')}`;
                    }
                    
                    let siri = '';
                    const siriMatch = course.nama_kursus.match(/SIRI\s+(\d+\/\d+)/i);
                    if (siriMatch) siri = 'SIRI ' + siriMatch[1];
                    
                    return {
                        ...course, 
                        minggu_no: mingguNo, 
                        tarikh_mula: tarikhMula, 
                        tarikh_akhir: tarikhAkhir, 
                        siri: siri, 
                        kategori: getCourseCategory(course.nama_kursus),
                        duplicate_action: course.duplicateAction || 'skip'
                    };
                });
                
                progressFill.style.width = '50%';
                progressFill.textContent = '50%';
                
                const response = await fetch('api_import_takwim.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        tahun: parseInt(tahun), 
                        semester: semester, 
                        courses: processedCourses 
                    })
                });
                
                const result = await response.json();
                progressFill.style.width = '100%';
                progressFill.textContent = '100%';
                
                if (result.success) {
                    showAlert(`Import berjaya! ${result.success_count} kursus telah diimport.` + 
                            (result.error_count > 0 ? ` (${result.error_count} kursus gagal diimport)` : ''), 'success');
                    
                    // Reload import history
                    loadImportHistory();
                    
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    showAlert('Ralat import: ' + result.message, 'error');
                    importBtn.disabled = false;
                    importBtn.textContent = '✓ Confirm & Import Data';
                }
            } catch (error) {
                showAlert('Ralat: ' + error.message, 'error');
                importBtn.disabled = false;
                importBtn.textContent = '✓ Confirm & Import Data';
            }
            
            setTimeout(() => {
                progressBar.style.display = 'none';
                progressFill.style.width = '0%';
            }, 3000);
        }
        
        function downloadTemplate() {
            // Create template workbook
            const wb = XLSX.utils.book_new();
            const ws_data = [
                ['BIL', 'MINGGU & TARIKH', 'NAMA KURSUS', 'PLATFORM', 'KAPASITI', 'PRA-SYARAT'],
                [1, 'MINGGU 1 (1-5/1/2024)', 'KURSUS RENANG ASAS SIRI 1/2024', 'BERSEMUKA', 30, 'TIADA'],
                [2, 'MINGGU 2 (8-12/1/2024)', 'KURSUS FRLS SIRI 1/2024', 'ONLINE', 25, 'SIJIL RENANG'],
                [3, 'MINGGU 3 (15-19/1/2024)', 'KURSUS KEPIMPINAN BAKAL PEGAWAI', 'HYBRID', 40, 'LAYAK AKUATIK']
            ];
            const ws = XLSX.utils.aoa_to_sheet(ws_data);
            XLSX.utils.book_append_sheet(wb, ws, "Template");
            
            // Download
            XLSX.writeFile(wb, "Template_Takwim_Kursus.xlsx");
        }
        
        async function loadImportHistory() {
            try {
                const response = await fetch('api_get_import_history.php');
                const result = await response.json();
                
                if (result.success && result.history.length > 0) {
                    const historySection = document.getElementById('importHistorySection');
                    const historyList = document.getElementById('importHistoryList');
                    
                    historyList.innerHTML = '';
                    result.history.forEach(history => {
                        const historyItem = document.createElement('div');
                        historyItem.className = 'history-item';
                        historyItem.innerHTML = `
                            <strong>${history.filename}</strong> - ${history.tahun} ${history.semester}
                            <br><small>Import: ${history.imported_at} | ${history.success_count}/${history.total_courses} berjaya</small>
                            <button class="btn btn-danger btn-sm" style="float:right" onclick="rollbackImport(${history.id})">Rollback</button>
                        `;
                        historyList.appendChild(historyItem);
                    });
                    
                    historySection.style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading import history:', error);
            }
        }
        
        async function rollbackImport(importId) {
            if (!confirm('Adakah anda pasti ingin rollback import ini? Semua data akan dipadam.')) return;
            
            try {
                const response = await fetch('api_rollback_import.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ import_id: importId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Rollback berjaya!', 'success');
                    loadImportHistory();
                    // You might want to refresh the page or current data
                } else {
                    showAlert('Rollback gagal: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('Ralat rollback: ' + error.message, 'error');
            }
        }
        
        function resetForm() {
            if (confirm('Adakah anda pasti ingin memulakan semula? Semua data akan hilang.')) {
                location.reload();
            }
        }
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = `alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'error'}`;
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => alertContainer.innerHTML = '', 5000);
        }
    </script>
</body>
</html>