<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get user info for imported_by
$user_name = $_SESSION['username'] ?? $_SESSION['email'] ?? 'Unknown';
$user_email = $_SESSION['email'] ?? '';
$imported_by_text = $user_email ? "$user_name ($user_email)" : $user_name;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Takwim Kursus v3</title>
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
            position: relative;
        }
        .header h1 { margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .history-btn {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        .history-btn:hover { background: rgba(255,255,255,0.3); }
        .content { padding: 40px; }
        
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
        
        .upload-zone {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 60px 20px;
            text-align: center;
            background: #f8f9ff;
            cursor: pointer;
            transition: all 0.3s;
            margin: 30px 0;
        }
        .upload-zone:hover { border-color: #764ba2; background: #f0f2ff; transform: translateY(-5px); }
        .upload-zone.dragover { background: #e8ebff; border-color: #5a67d8; }
        
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
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-small { padding: 8px 16px; font-size: 14px; }
        
        #preview-section { display: none; margin-top: 30px; }
        .preview-header { background: #f7fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        
        .summary-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 2px solid #e2e8f0;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-card {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .stat-card h3 { font-size: 24px; color: #667eea; }
        .stat-card p { color: #666; font-size: 14px; }
        
        .preview-table-container {
            overflow-x: auto;
            max-height: 500px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        th { background: #667eea; color: white; position: sticky; top: 0; z-index: 10; }
        tr:hover { background: #f7fafc; }
        tr.row-error { background: #fee; border-left: 4px solid #dc3545; }
        tr.row-warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        tr.row-success { background: #d4edda; border-left: 4px solid #28a745; }
        
        .checkbox-cell { text-align: center; width: 40px; }
        .actions-cell { text-align: center; width: 100px; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header { border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; }
        .modal-footer { border-top: 2px solid #e2e8f0; padding-top: 15px; margin-top: 20px; text-align: right; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        
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
        
        .file-info { display: none; background: #f0f2ff; padding: 15px; border-radius: 8px; margin-top: 15px; }
        .file-info.show { display: block; }
        
        .status-icon { font-size: 20px; }
        .bulk-actions { margin: 15px 0; padding: 15px; background: #f8f9ff; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Import Takwim Kursus v3</h1>
            <p>With Smart Detection, Edit, Validation & History</p>
            <a href="view_import_history.php" class="history-btn">📋 View History</a>
        </div>
        
        <div class="content">
            <div id="alert-container"></div>
            
            <div class="form-group">
                <label>Tahun:</label>
                <input type="number" id="tahun" min="2020" max="2050" value="2025" required placeholder="Enter year (e.g., 2025)">
            </div>
            
            <div class="form-group">
                <label>Semester:</label>
                <select id="semester" required>
                    <option value="">Pilih Semester</option>
                    <option value="JAN-JUN">JAN - JUN</option>
                    <option value="JULAI-DIS">JULAI - DIS</option>
                </select>
            </div>
            
            <div class="form-group" id="sheetSelectGroup" style="display:none;">
                <label>Select Sheet (Auto-detection failed):</label>
                <select id="sheetSelect"></select>
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
                <div class="summary-box">
                    <h2>📊 Import Summary</h2>
                    <div class="summary-grid" id="summaryStats"></div>
                </div>
                
                <div class="bulk-actions">
                    <button class="btn btn-secondary btn-small" onclick="selectAll()">☑️ Select All</button>
                    <button class="btn btn-secondary btn-small" onclick="deselectAll()">☐ Deselect All</button>
                    <button class="btn btn-danger btn-small" onclick="deleteSelected()">🗑️ Delete Selected</button>
                    <span style="margin-left: 20px; color: #666;" id="selectedCount">0 selected</span>
                </div>
                
                <div class="preview-table-container">
                    <table id="previewTable">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">☑️</th>
                                <th>Status</th>
                                <th>BIL</th>
                                <th>MINGGU</th>
                                <th>NAMA KURSUS</th>
                                <th>PLATFORM</th>
                                <th>KAPASITI</th>
                                <th>KATEGORI</th>
                                <th class="actions-cell">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <button class="btn btn-primary" id="importBtn" onclick="importData()">
                        ✓ Confirm & Import Selected Data
                    </button>
                </div>
            </div>
            
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill">0%</div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Kursus</h2>
            </div>
            <div class="form-group">
                <label>Nama Kursus:</label>
                <input type="text" id="edit_nama_kursus">
            </div>
            <div class="form-group">
                <label>Siri:</label>
                <input type="text" id="edit_siri">
            </div>
            <div class="form-group">
                <label>Platform:</label>
                <select id="edit_platform">
                    <option value="BERSEMUKA">BERSEMUKA</option>
                    <option value="ONLINE">ONLINE</option>
                    <option value="HYBRID">HYBRID</option>
                </select>
            </div>
            <div class="form-group">
                <label>Kapasiti:</label>
                <input type="number" id="edit_kapasiti" min="1">
            </div>
            <div class="form-group">
                <label>Pra-syarat:</label>
                <input type="text" id="edit_pra_syarat">
            </div>
            <div class="form-group">
                <label>Kategori:</label>
                <select id="edit_kategori">
                    <option value="Program Akuatik">Program Akuatik</option>
                    <option value="Program Teknik Tali">Program Teknik Tali</option>
                    <option value="Program Kesihatan">Program Kesihatan</option>
                    <option value="Program Kepimpinan">Program Kepimpinan</option>
                    <option value="Program Kecergasan">Program Kecergasan</option>
                    <option value="Program Keselamatan">Program Keselamatan</option>
                    <option value="Program Asas">Program Asas</option>
                    <option value="Program Lain-lain">Program Lain-lain</option>
                </select>
            </div>
            <div class="form-group">
                <label>Minggu No:</label>
                <input type="number" id="edit_minggu_no" min="1" max="52">
            </div>
            <div class="form-group">
                <label>Tarikh Mula:</label>
                <input type="date" id="edit_tarikh_mula">
            </div>
            <div class="form-group">
                <label>Tarikh Akhir:</label>
                <input type="date" id="edit_tarikh_akhir">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditModal()">❌ Cancel</button>
                <button class="btn btn-primary" onclick="saveEdit()">💾 Save Changes</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        let validCourses = [];
        let currentEditIndex = -1;
        let workbookGlobal = null;
        const importedBy = <?php echo json_encode($imported_by_text); ?>;
        
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        
        uploadZone.addEventListener('click', () => fileInput.click());
        uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
        uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
        });
        fileInput.addEventListener('change', (e) => { if (e.target.files[0]) handleFile(e.target.files[0]); });
        
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
                    workbookGlobal = XLSX.read(data, { type: 'array' });
                    
                    smartSheetSelection();
                    fileInfo.innerHTML = `<strong>📄 File:</strong> ${file.name}<br><strong>📦 Size:</strong> ${(file.size / 1024).toFixed(2)} KB<br><strong>✅ Berjaya dibaca!</strong>`;
                } catch (error) {
                    showAlert('Ralat membaca fail Excel: ' + error.message, 'error');
                    fileInfo.classList.remove('show');
                }
            };
            reader.readAsArrayBuffer(file);
        }
        
        function smartSheetSelection() {
            const semester = document.getElementById('semester').value;
            const tahun = document.getElementById('tahun').value;
            
            if (!semester || !tahun) {
                showAlert('Sila pilih tahun dan semester dahulu!', 'error');
                return;
            }
            
            let targetSheetName = null;
            const sheetNames = workbookGlobal.SheetNames;
            
            // Smart detection
            if (semester === 'JAN-JUN') {
                targetSheetName = sheetNames.find(name => 
                    name.toUpperCase().includes('JAN') && name.includes(tahun)
                );
            } else if (semester === 'JULAI-DIS') {
                targetSheetName = sheetNames.find(name => 
                    (name.toUpperCase().includes('JULAI') || name.toUpperCase().includes('DIS')) && name.includes(tahun)
                );
            }
            
            if (targetSheetName) {
                showAlert(`✅ Sheet detected: ${targetSheetName}`, 'success');
                processSheet(targetSheetName);
            } else {
                // Fallback: Show manual selection
                const sheetSelect = document.getElementById('sheetSelect');
                sheetSelect.innerHTML = sheetNames.map(name => `<option value="${name}">${name}</option>`).join('');
                document.getElementById('sheetSelectGroup').style.display = 'block';
                showAlert('⚠️ Auto-detection failed. Please select sheet manually.', 'warning');
                sheetSelect.addEventListener('change', function() {
                    processSheet(this.value);
                });
            }
        }
        
        function processSheet(sheetName) {
            const sheet = workbookGlobal.Sheets[sheetName];
            const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
            parseAndPreview(jsonData);
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
                showAlert('Header row tidak dijumpai.', 'error');
                return;
            }
            
            validCourses = [];
            for (let i = headerRowIndex + 1; i < data.length; i++) {
                const row = data[i];
                if (row[0] && !isNaN(row[0]) && row[2] && row[2].toString().length > 5) {
                    const course = {
                        bil: row[0],
                        minggu_tarikh: row[1] || '',
                        nama_kursus: row[2] || '',
                        platform: row[3] || 'BERSEMUKA',
                        kapasiti: row[4] || 30,
                        pra_syarat: row[5] || 'Tiada',
                        selected: true
                    };
                    
                    processCourseData(course);
                    validCourses.push(course);
                }
            }
            
            if (validCourses.length === 0) {
                showAlert('Tiada kursus dijumpai.', 'error');
                return;
            }
            
            validateDuplicates();
            displayPreview();
        }
        
        function processCourseData(course) {
            const mingguMatch = course.minggu_tarikh.match(/MINGGU\s+(\d+)/i);
            course.minggu_no = mingguMatch ? parseInt(mingguMatch[1]) : null;
            
            const dateMatch = course.minggu_tarikh.match(/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})/i);
            if (dateMatch) {
                const year = dateMatch[4].length == 2 ? '20' + dateMatch[4] : dateMatch[4];
                course.tarikh_mula = `${year}-${dateMatch[3].padStart(2,'0')}-${dateMatch[1].padStart(2,'0')}`;
                course.tarikh_akhir = `${year}-${dateMatch[3].padStart(2,'0')}-${dateMatch[2].padStart(2,'0')}`;
            }
            
            const siriMatch = course.nama_kursus.match(/SIRI\s+(\d+\/\d+)/i);
            course.siri = siriMatch ? 'SIRI ' + siriMatch[1] : '';
            
            course.kategori = getCourseCategory(course.nama_kursus);
            course.status = 'success';
            course.statusMsg = 'OK';
            
            // Validation
            if (!course.nama_kursus || course.nama_kursus.length < 10) {
                course.status = 'error';
                course.statusMsg = 'Nama kursus terlalu pendek';
            } else if (course.kapasiti <= 0) {
                course.status = 'error';
                course.statusMsg = 'Kapasiti tidak sah';
            }
        }
        
        function validateDuplicates() {
            const courseMap = {};
            validCourses.forEach((course, index) => {
                const key = `${course.nama_kursus}_${course.minggu_no}`;
                if (courseMap[key]) {
                    course.status = 'warning';
                    course.statusMsg = 'Possible duplicate (same course, same week)';
                    validCourses[courseMap[key]].status = 'warning';
                    validCourses[courseMap[key]].statusMsg = 'Possible duplicate (same course, same week)';
                } else {
                    courseMap[key] = index;
                }
            });
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
        
        function displayPreview() {
            // Summary
            const categoryCounts = {};
            validCourses.forEach(c => categoryCounts[c.kategori] = (categoryCounts[c.kategori] || 0) + 1);
            
            const dates = validCourses.filter(c => c.tarikh_mula).map(c => c.tarikh_mula).sort();
            const dateRange = dates.length > 0 ? `${dates[0]} to ${dates[dates.length-1]}` : 'N/A';
            
            const warnings = validCourses.filter(c => c.status === 'warning').length;
            const errors = validCourses.filter(c => c.status === 'error').length;
            
            let summaryHtml = `
                <div class="stat-card"><h3>${validCourses.length}</h3><p>Total Courses</p></div>
                <div class="stat-card"><h3>${dateRange}</h3><p>Date Range</p></div>
                <div class="stat-card"><h3>${warnings}</h3><p>⚠️ Warnings</p></div>
                <div class="stat-card"><h3>${errors}</h3><p>❌ Errors</p></div>
            `;
            
            Object.keys(categoryCounts).forEach(cat => {
                summaryHtml += `<div class="stat-card"><h3>${categoryCounts[cat]}</h3><p>${cat}</p></div>`;
            });
            
            document.getElementById('summaryStats').innerHTML = summaryHtml;
            
            // Table
            const previewBody = document.getElementById('previewBody');
            previewBody.innerHTML = '';
            
            validCourses.forEach((course, index) => {
                const statusIcon = course.status === 'error' ? '🔴' : course.status === 'warning' ? '🟡' : '🟢';
                const rowClass = course.status === 'error' ? 'row-error' : course.status === 'warning' ? 'row-warning' : 'row-success';
                
                const row = `
                    <tr class="${rowClass}" data-index="${index}">
                        <td class="checkbox-cell"><input type="checkbox" class="course-checkbox" ${course.selected ? 'checked' : ''} onchange="toggleCourse(${index})"></td>
                        <td title="${course.statusMsg}"><span class="status-icon">${statusIcon}</span></td>
                        <td>${course.bil}</td>
                        <td>${course.minggu_no || '-'}</td>
                        <td>${course.nama_kursus}</td>
                        <td>${course.platform}</td>
                        <td>${course.kapasiti}</td>
                        <td>${course.kategori}</td>
                        <td class="actions-cell">
                            <button class="btn btn-secondary btn-small" onclick="openEditModal(${index})">✏️</button>
                            <button class="btn btn-danger btn-small" onclick="deleteCourse(${index})">🗑️</button>
                        </td>
                    </tr>
                `;
                previewBody.innerHTML += row;
            });
            
            updateSelectedCount();
            document.getElementById('preview-section').style.display = 'block';
        }
        
        function toggleCourse(index) {
            validCourses[index].selected = !validCourses[index].selected;
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const count = validCourses.filter(c => c.selected).length;
            document.getElementById('selectedCount').textContent = `${count} selected`;
        }
        
        function selectAll() {
            validCourses.forEach(c => c.selected = true);
            document.querySelectorAll('.course-checkbox').forEach(cb => cb.checked = true);
            updateSelectedCount();
        }
        
        function deselectAll() {
            validCourses.forEach(c => c.selected = false);
            document.querySelectorAll('.course-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        }
        
        function deleteSelected() {
            if (!confirm('Delete all selected courses?')) return;
            validCourses = validCourses.filter(c => !c.selected);
            displayPreview();
        }
        
        function deleteCourse(index) {
            if (!confirm('Delete this course?')) return;
            validCourses.splice(index, 1);
            displayPreview();
        }
        
        function openEditModal(index) {
            currentEditIndex = index;
            const course = validCourses[index];
            
            document.getElementById('edit_nama_kursus').value = course.nama_kursus;
            document.getElementById('edit_siri').value = course.siri;
            document.getElementById('edit_platform').value = course.platform;
            document.getElementById('edit_kapasiti').value = course.kapasiti;
            document.getElementById('edit_pra_syarat').value = course.pra_syarat;
            document.getElementById('edit_kategori').value = course.kategori;
            document.getElementById('edit_minggu_no').value = course.minggu_no || '';
            document.getElementById('edit_tarikh_mula').value = course.tarikh_mula || '';
            document.getElementById('edit_tarikh_akhir').value = course.tarikh_akhir || '';
            
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
            currentEditIndex = -1;
        }
        
        function saveEdit() {
            if (currentEditIndex === -1) return;
            
            const course = validCourses[currentEditIndex];
            course.nama_kursus = document.getElementById('edit_nama_kursus').value;
            course.siri = document.getElementById('edit_siri').value;
            course.platform = document.getElementById('edit_platform').value;
            course.kapasiti = parseInt(document.getElementById('edit_kapasiti').value);
            course.pra_syarat = document.getElementById('edit_pra_syarat').value;
            course.kategori = document.getElementById('edit_kategori').value;
            course.minggu_no = parseInt(document.getElementById('edit_minggu_no').value) || null;
            course.tarikh_mula = document.getElementById('edit_tarikh_mula').value || null;
            course.tarikh_akhir = document.getElementById('edit_tarikh_akhir').value || null;
            
            // Re-validate
            if (!course.nama_kursus || course.nama_kursus.length < 10) {
                course.status = 'error';
                course.statusMsg = 'Nama kursus terlalu pendek';
            } else if (course.kapasiti <= 0) {
                course.status = 'error';
                course.statusMsg = 'Kapasiti tidak sah';
            } else {
                course.status = 'success';
                course.statusMsg = 'OK';
            }
            
            validateDuplicates();
            displayPreview();
            closeEditModal();
            showAlert('✅ Changes saved!', 'success');
        }
        
        async function importData() {
            const tahun = document.getElementById('tahun').value;
            const semester = document.getElementById('semester').value;
            
            if (!tahun || !semester) {
                showAlert('Sila pilih tahun dan semester!', 'error');
                return;
            }
            
            const selectedCourses = validCourses.filter(c => c.selected);
            
            if (selectedCourses.length === 0) {
                showAlert('Tiada kursus dipilih untuk import!', 'error');
                return;
            }
            
            const hasErrors = selectedCourses.some(c => c.status === 'error');
            if (hasErrors) {
                if (!confirm('Ada kursus dengan error. Continue import?')) return;
            }
            
            const importBtn = document.getElementById('importBtn');
            importBtn.disabled = true;
            importBtn.textContent = 'Importing...';
            
            const progressBar = document.getElementById('progressBar');
            const progressFill = document.getElementById('progressFill');
            progressBar.style.display = 'block';
            progressFill.style.width = '30%';
            progressFill.textContent = '30%';
            
            try {
                const response = await fetch('api_import_takwim.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tahun: parseInt(tahun),
                        semester: semester,
                        courses: selectedCourses,
                        imported_by: importedBy
                    })
                });
                
                progressFill.style.width = '70%';
                progressFill.textContent = '70%';
                
                const result = await response.json();
                
                progressFill.style.width = '100%';
                progressFill.textContent = '100%';
                
                if (result.success) {
                    showAlert(`✅ Import berjaya! ${result.success_count} kursus imported.` + 
                        (result.error_count > 0 ? ` (${result.error_count} failed)` : ''), 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('❌ Ralat import: ' + result.message, 'error');
                    importBtn.disabled = false;
                    importBtn.textContent = '✓ Confirm & Import Selected Data';
                }
            } catch (error) {
                showAlert('❌ Ralat: ' + error.message, 'error');
                importBtn.disabled = false;
                importBtn.textContent = '✓ Confirm & Import Selected Data';
            }
            
            setTimeout(() => {
                progressBar.style.display = 'none';
                progressFill.style.width = '0%';
            }, 3000);
        }
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : 'alert-error';
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => alertContainer.innerHTML = '', 5000);
        }
    </script>
</body>
</html>