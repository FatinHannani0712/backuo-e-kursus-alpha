<!-- Section for confirmed TAKWIM courses -->
<div class="content-card">
    <div class="card-title">
        <i class="fas fa-calendar-check"></i>
        Kursus TAKWIM Telah Disahkan
        <span class="badge bg-success ms-2"><?php echo $takwim_stats['confirmed_courses']; ?> aktif</span>
    </div>
    
    <?php
    $confirmed_sql = "SELECT * FROM kursus 
                     WHERE is_takwim_course = 1 AND takwim_status = 'confirmed' AND user_id = :user_id
                     ORDER BY tarikh_mula ASC LIMIT 3";
    $confirmed_stmt = $pdo->prepare($confirmed_sql);
    $confirmed_stmt->execute([':user_id' => $userId]);
    $confirmed_courses = $confirmed_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <?php if (!empty($confirmed_courses)): ?>
        <div class="row">
            <?php foreach ($confirmed_courses as $course): ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title"><?php echo htmlspecialchars($course['nama_kursus']); ?></h6>
                        <small class="text-muted d-block"><?php echo $course['takwim_series']; ?></small>
                        <small class="text-muted d-block"><?php echo $course['takwim_week']; ?></small>
                        
                        <div class="mt-2">
                            <span class="badge bg-<?php echo getStatusClass($course['status_kursus']); ?>">
                                <?php echo getStatusText($course['status_kursus']); ?>
                            </span>
                        </div>
                        
                        <div class="mt-2">
                            <small><strong>Peserta:</strong> <?php echo $course['pendaftar']; ?>/<?php echo $course['kapasiti']; ?></small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="manage_course.php?id=<?php echo $course['kursus_id']; ?>" 
                           class="btn btn-sm btn-outline-primary w-100">
                           Urus Kursus
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
            <p class="text-muted">Tiada kursus TAKWIM yang telah disahkan</p>
        </div>
    <?php endif; ?>
</div>