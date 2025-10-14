let validCourses = [];
let selectedSheetIndex = 0;

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
            
            // Process the first sheet only for now
            const firstSheetName = workbook.SheetNames[0];
            const sheet = workbook.Sheets[firstSheetName];
            const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
            
            console.log('Excel data loaded:', jsonData);
            
            // Simple parsing - look for rows with data
            validCourses = [];
            for (let i = 0; i < jsonData.length; i++) {
                const row = jsonData[i];
                if (row && row.length >= 3 && row[0] && !isNaN(row[0]) && row[2]) {
                    validCourses.push({
                        bil: row[0],
                        minggu_tarikh: row[1] || '',
                        nama_kursus: row[2].toString(),
                        platform: row[3] || 'BERSEMUKA',
                        kapasiti: row[4] || 30,
                        pra_syarat: row[5] || '',
                        isDuplicate: false,
                        duplicateAction: 'skip',
                        isSelected: false
                    });
                }
            }
            
            if (validCourses.length === 0) {
                showAlert('Tiada data kursus dijumpai. Pastikan fail mempunyai data dalam format yang betul.', 'error');
                fileInfo.innerHTML = `<strong>📄 File:</strong> ${file.name}<br><strong>❌ Tiada data dijumpai</strong>`;
                return;
            }
            
            fileInfo.innerHTML = `<strong>📄 File:</strong> ${file.name}<br><strong>✅ ${validCourses.length} kursus dijumpai!</strong>`;
            displayPreview();
            
        } catch (error) {
            console.error('Error:', error);
            showAlert('Ralat: ' + error.message, 'error');
            fileInfo.innerHTML = `<strong>📄 File:</strong> ${file.name}<br><strong>❌ Ralat: ${error.message}</strong>`;
        }
    };
    
    reader.onerror = function() {
        showAlert('Ralat membaca fail', 'error');
        fileInfo.innerHTML = `<strong>📄 File:</strong> ${file.name}<br><strong>❌ Gagal membaca fail</strong>`;
    };
    
    reader.readAsArrayBuffer(file);
}

function displayPreview() {
    const statsHtml = `
        <div class="stat-card"><h3>${validCourses.length}</h3><p>Total Kursus</p></div>
        <div class="stat-card"><h3>${validCourses.filter(c => c.platform === 'BERSEMUKA').length}</h3><p>Bersemuka</p></div>
        <div class="stat-card"><h3>${validCourses.filter(c => c.platform !== 'BERSEMUKA').length}</h3><p>Online/Hybrid</p></div>
    `;
    document.getElementById('previewStats').innerHTML = statsHtml;
    
    const previewBody = document.getElementById('previewBody');
    previewBody.innerHTML = '';
    
    validCourses.forEach((course, index) => {
        const kategori = getCourseCategory(course.nama_kursus);
        previewBody.innerHTML += `
            <tr>
                <td><input type="checkbox" class="row-select" onchange="toggleRowSelection(${index})"></td>
                <td>${course.bil}</td>
                <td>${course.minggu_tarikh}</td>
                <td>${course.nama_kursus}</td>
                <td>${course.platform}</td>
                <td>${course.kapasiti}</td>
                <td>${course.pra_syarat}</td>
                <td>${kategori}</td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="deleteRow(${index})">🗑️</button>
                </td>
            </tr>
        `;
    });
    
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
   