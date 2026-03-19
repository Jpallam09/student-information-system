<?php
/**
 * Inactive Enrollment Warning Banner
 * Shows when student's enrolled term != active term
 * Non-blocking - allows view-only access
 */

if (!isset($is_inactive) || !$is_inactive) return;

$active_year = getActiveSchoolYear($conn);
$active_sem = getActiveSemester($conn);
?>
<div class="inactive-warning-banner" style="
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    animation: slideDown 0.5s ease-out;
">
    <i class="fas fa-exclamation-triangle" style="
        font-size: 2rem; 
        color: #d97706; 
        flex-shrink: 0;
    "></i>
    
    <div style="flex: 1;">
        <h3 style="
            margin: 0 0 8px 0; 
            color: #92400e; 
            font-size: 1.3rem;
        ">
            📚 Viewing Past Term Records
        </h3>
        <p style="
            margin: 0 0 12px 0; 
            color: #a16207; 
            line-height: 1.6;
        ">
            Your enrollment: <strong><?php echo htmlspecialchars($student['school_year'] ?? 'N/A'); ?> 
            <?php echo htmlspecialchars($student['semester'] ?? 'N/A'); ?> Sem</strong><br>
            Active term: <strong><?php echo htmlspecialchars($active_year); ?> 
            <?php echo htmlspecialchars($active_sem); ?> Sem</strong>
        </p>
        <div style="font-size: 0.9rem; color: #a16207;">
            <i class="fas fa-info-circle"></i> 
            You can view past records but cannot submit new work. 
            Contact administrator to update enrollment.
        </div>
    </div>
    
    <div style="flex-shrink: 0;">
        <button onclick="window.open('../admin/manage_school_year.php', '_blank')" 
                style="
                    background: #f59e0b; 
                    color: white; 
                    border: none; 
                    padding: 10px 20px; 
                    border-radius: 8px; 
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                "
                onmouseover="this.style.background='#d97706'"
                onmouseout="this.style.background='#f59e0b'">
            <i class="fas fa-calendar-alt"></i> School Years
        </button>
    </div>
</div>

<style>
@keyframes slideDown {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
