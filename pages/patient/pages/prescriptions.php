<?php
// Check if PATIENT_ACCESS is defined
if (!defined('PATIENT_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get patient info from session
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM patients WHERE user_id = :user_id");
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    echo '<div class="alert alert-danger">Patient record not found.</div>';
    exit();
}
$patient_id = $patient['id'];

// Fetch patient's prescriptions (medications)
$sql = "SELECT m.*, 
            d.first_name as doctor_first_name,
            d.last_name as doctor_last_name
        FROM medications m
        JOIN doctors d ON m.prescribed_by = d.id
        WHERE m.patient_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$patient_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique medications for filter
$medications = array_unique(array_filter(array_column($prescriptions, 'medication_name')));
sort($medications);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">My Prescriptions</h4>
        <div class="d-flex gap-2">
            <select class="form-select" id="medicationFilter">
                <option value="">All Medications</option>
                <?php foreach ($medications as $med): ?>
                    <option value="<?php echo htmlspecialchars($med); ?>"><?php echo htmlspecialchars($med); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" id="sortOrder">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
            </select>
        </div>
    </div>

    <?php if (empty($prescriptions)): ?>
        <div class="alert alert-info">
            <i class="material-icons align-middle me-2">info</i>
            No prescriptions found.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($prescriptions as $medication): ?>
                <div class="col-md-6 col-lg-4 prescription-card">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <span class="text-primary">
                                <i class="material-icons align-middle me-1">medication</i>
                                <?php echo htmlspecialchars($medication['medication_name']); ?>
                            </span>
                            <span class="badge bg-primary">
                                <?php echo date('M d, Y', strtotime($medication['start_date'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Prescribed by:</small>
                                <div class="d-flex align-items-center">
                                    <span class="material-icons text-primary me-2">person</span>
                                    <div>
                                        <div class="fw-bold">Dr. <?php echo htmlspecialchars($medication['doctor_last_name'] . ', ' . $medication['doctor_first_name']); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Dosage</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($medication['dosage']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Frequency</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($medication['frequency']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Duration</small>
                                    <div class="fw-bold"><?php echo $medication['end_date'] ? date('M d, Y', strtotime($medication['end_date'])) : 'Ongoing'; ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Status</small>
                                    <div class="fw-bold">
                                        <span class="badge bg-<?php echo $medication['status'] === 'active' ? 'success' : ($medication['status'] === 'completed' ? 'secondary' : 'warning'); ?>">
                                            <?php echo ucfirst($medication['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Instructions:</small>
                                <div class="p-2 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($medication['instructions'] ?? 'No specific instructions provided.')); ?>
                                </div>
                            </div>

                            <?php if (!empty($medication['chief_complaint'])): ?>
                                <div>
                                    <small class="text-muted d-block mb-1">Consultation Reason:</small>
                                    <div class="p-2 bg-light rounded">
                                        <?php echo htmlspecialchars($medication['chief_complaint']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const medicationFilter = document.getElementById('medicationFilter');
    const sortOrder = document.getElementById('sortOrder');
    const prescriptionCards = document.querySelectorAll('.prescription-card');

    function filterAndSort() {
        const selectedMedication = medicationFilter.value.toLowerCase();
        const sortBy = sortOrder.value;
        
        // Convert NodeList to Array for sorting
        const cards = Array.from(prescriptionCards);
        
        // Filter cards
        cards.forEach(card => {
            const medicationName = card.querySelector('.card-header .text-primary').textContent.trim().toLowerCase();
            if (!selectedMedication || medicationName === selectedMedication) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });

        // Sort cards
        const container = document.querySelector('.row');
        cards.sort((a, b) => {
            const dateA = new Date(a.querySelector('.badge').textContent);
            const dateB = new Date(b.querySelector('.badge').textContent);
            return sortBy === 'newest' ? dateB - dateA : dateA - dateB;
        });

        // Reappend sorted cards
        cards.forEach(card => {
            if (card.style.display !== 'none') {
                container.appendChild(card);
            }
        });
    }

    medicationFilter.addEventListener('change', filterAndSort);
    sortOrder.addEventListener('change', filterAndSort);
});
</script>

<style>
.prescription-card .card {
    transition: transform 0.2s;
}
.prescription-card .card:hover {
    transform: translateY(-5px);
}
</style> 