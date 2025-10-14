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
    <title>Import Takwim Kursus - Hybrid Version</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
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
        
        .preview-table-container {
            overflow-x: auto;
            max-height: 400px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
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
        
        .file-info {
            display: none;
            background: #f0f2ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .file-info.show { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Import Takwim Kursus</h1>
            <p>Hybrid Version - Dengan Preview & Validation</p>
        </div>
        
        <div class="content">
            <div id="alert-container"></div>
            
            <div class="form-group">
                <label>Tahun:</label>
                <select id="tahun" required>
                    <option value="">Pilih Tahun</option>
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026">2026</option>
                </select>
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
            
            <div class="file-info" id="fileInfo"></div>
            
            <div id="preview-section">
                <div class="preview-header">
                    <h2>Preview Data</h2>
                    <div class="preview-stats" id="previewStats"></div>
                </div>
                
                <div class="preview-table-container">
                    <table id="previewTable">
                        <thead>
                            <tr>
                                <th>BIL</th>
                                <th>MINGGU & TARIKH</th>
                                <th>NAMA KURSUS</th>
                                <th>PLATFORM</th>
                                <th>KAPASITI</th>
                                <th>PRA-SYARAT</th>
                                <th>KATEGORI</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <button class="btn btn-primary" id="importBtn" onclick="importData()">
                        ✓ Confirm & Import Data
                    </button>
                </div>
            </div>
            
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill">0%</div>
            </div>
        </div>
    </div>

    <!-- SheetJS Library from CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        let validCourses = [];
        
        // Upload zone interactions
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
            fileInfo.innerHTML = `
                <strong>📄 File:</strong> ${file.name}<br>
                <strong>📦 Size:</strong> ${(file.size / 1024).toFixed(2)} KB<br>
                <strong>⏰ Processing...</strong>
            `;
            fileInfo.classList.add('show');
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
                    
                    parseAndPreview(jsonData);
                    
                    fileInfo.innerHTML = `
                        <strong>📄 File:</strong> ${file.name}<br>
                        <strong>📦 Size:</strong> ${(file.size / 1024).toFixed(2)} KB<br>
                        <strong>✅ Berjaya dibaca!</strong>
                    `;
                    
                } catch (error) {
                    showAlert('Ralat membaca fail Excel: ' + error.message, 'error');
                    fileInfo.classList.remove('show');
                }
            };
            reader.readAsArrayBuffer(file);
        }
        
        function parseAndPreview(data) {
            let headerRowIndex = -1;
            for (let i = 0; i < data.length; i++) {
                if (data[i][0] === 'BIL.' || data[i][0] === 'BIL') {
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
                if (row[0] && !isNaN(row[0]) && row[2] && row[2].toString().length > 10) {
                    validCourses.push({
                        bil: row[0],
                        minggu_tarikh: row[1] || '',
                        nama_kursus: row[2] || '',
                        platform: row[3] || 'BERSEMUKA',
                        kapasiti: row[4] || 30,
                        pra_syarat: row[5] || ''
                    });
                }
            }
            
            if (validCourses.length === 0) {
                showAlert('Tiada kursus yang sah dijumpai dalam fail Excel.', 'error');
                return;
            }
            
            displayPreview();
        }
        
        function displayPreview() {
            const statsHtml = `
                <div class="stat-card">
                    <h3>${validCourses.length}</h3>
                    <p>Total Kursus</p>
                </div>
                <div class="stat-card">
                    <h3>${validCourses.filter(c => c.platform === 'BERSEMUKA').length}</h3>
                    <p>Bersemuka</p>
                </div>
                <div class="stat-card">
                    <h3>${validCourses.filter(c => c.platform !== 'BERSEMUKA').length}</h3>
                    <p>Online/Hybrid</p>
                </div>
            `;
            document.getElementById('previewStats').innerHTML = statsHtml;
            
            const previewBody = document.getElementById('previewBody');
            previewBody.innerHTML = '';
            
            const displayRows = validCourses.slice(0, 50);
            displayRows.forEach(course => {
                const kategori = getCourseCategory(course.nama_kursus);
                const row = `
                    <tr>
                        <td>${course.bil}</td>
                        <td>${course.minggu_tarikh}</td>
                        <td>${course.nama_kursus}</td>
                        <td>${course.platform}</td>
                        <td>${course.kapasiti}</td>
                        <td>${course.pra_syarat}</td>
                        <td><span style="background:#e0e7ff;padding:4px 8px;border-radius:4px;font-size:12px">${kategori}</span></td>
                    </tr>
                `;
                previewBody.innerHTML += row;
            });
            
            if (validCourses.length > 50) {
                previewBody.innerHTML += `
                    <tr>
                        <td colspan="7" style="text-align:center;color:#666;font-style:italic;">
                            ... dan ${validCourses.length - 50} lagi kursus
                        </td>
                    </tr>
                `;
            }
            
            document.getElementById('preview-section').style.display = 'block';
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
                    
                    let tarikhMula = null;
                    let tarikhAkhir = null;
                    const dateMatch = course.minggu_tarikh.match(/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})/i);
                    if (dateMatch) {
                        const year = dateMatch[4].length == 2 ? '20' + dateMatch[4] : dateMatch[4];
                        tarikhMula = `${year}-${dateMatch[3]}-${dateMatch[1]}`;
                        tarikhAkhir = `${year}-${dateMatch[3]}-${dateMatch[2]}`;
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
                        kategori: getCourseCategory(course.nama_kursus)
                    };
                });
                
                progressFill.style.width = '50%';
                progressFill.textContent = '50%';
                
                const response = await fetch('api_import_takwim.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tahun: tahun,
                        semester: semester,
                        courses: processedCourses
                    })
                });
                
                const result = await response.json();
                
                progressFill.style.width = '100%';
                progressFill.textContent = '100%';
                
                if (result.success) {
                    showAlert(
                        `Import berjaya! ${result.success_count} kursus telah diimport.` + 
                        (result.error_count > 0 ? ` (${result.error_count} kursus gagal diimport)` : ''),
                        'success'
                    );
                    
                    setTimeout(() => location.reload(), 2000);
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
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => alertContainer.innerHTML = '', 5000);
        }
    </script>
</body>
</html>