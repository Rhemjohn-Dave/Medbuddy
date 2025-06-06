<?php
// Check if PATIENT_ACCESS is defined
if (!defined('PATIENT_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch all active doctors with their specializations
$query = "SELECT d.id, d.first_name, d.last_name, s.name as specialization_name
          FROM doctors d
          LEFT JOIN specializations s ON d.specialization_id = s.id
          WHERE d.status = 'active'
          ORDER BY d.last_name, d.first_name";

$stmt = $db->prepare($query);
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Our Doctors</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Doctors</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Available Doctors
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Specialization</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($doctors)): ?>
                            <tr>
                                <td colspan="4" class="text-center">No doctors found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialization_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                            // Determine if Primary Healthcare Provider or Health Specialist
                                            // This is a basic logic based on specialization presence. Adjust as needed.
                                            if (empty($doctor['specialization_name']) || $doctor['specialization_name'] === 'General Practice') {
                                                echo 'Primary Healthcare Provider';
                                            } else {
                                                echo 'Health Specialist';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <!-- Add action buttons here, e.g., View Profile, Schedule Appointment -->
                                        <button class="btn btn-sm btn-primary view-doctor" data-doctor-id="<?php echo $doctor['id']; ?>">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Add modals for view doctor details if needed -->
<?php // include 'partials/doctor_details_modal.php'; ?>

<!-- Add JavaScript for handling view doctor button if needed -->
<?php // include 'js/doctors.js'; ?>

<!-- View Doctor Details Modal -->
<div class="modal fade" id="viewDoctorModal" tabindex="-1" aria-labelledby="viewDoctorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewDoctorModalLabel">Doctor Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <span id="modalDoctorFullName"></span></p>
                        <p><strong>Specialization:</strong> <span id="modalDoctorSpecialization"></span></p>
                        <p><strong>Type:</strong> <span id="modalDoctorType"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Email:</strong> <span id="modalDoctorEmail"></span></p>
                        <p><strong>Phone:</strong> <span id="modalDoctorPhone"></span></p>
                        <p><strong>Status:</strong> <span id="modalDoctorStatus"></span></p>
                    </div>
                </div>
                 <div class="mb-3">
                    <h6>Clinic(s)</h6>
                    <div id="modalDoctorClinics"></div>
                </div>
                 <div class="mb-3">
                    <h6>Schedule</h6>
                    <div id="modalDoctorSchedule"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <!-- Optional: Add button to schedule appointment -->
                <!-- <a href="#" class="btn btn-primary" id="modalScheduleAppointment">Schedule Appointment</a> -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewDoctorModal = new bootstrap.Modal(document.getElementById('viewDoctorModal'));
    const modalDoctorFullName = document.getElementById('modalDoctorFullName');
    const modalDoctorSpecialization = document.getElementById('modalDoctorSpecialization');
    const modalDoctorType = document.getElementById('modalDoctorType');
    const modalDoctorEmail = document.getElementById('modalDoctorEmail');
    const modalDoctorPhone = document.getElementById('modalDoctorPhone');
    const modalDoctorStatus = document.getElementById('modalDoctorStatus');
    const modalDoctorClinics = document.getElementById('modalDoctorClinics');
    const modalDoctorSchedule = document.getElementById('modalDoctorSchedule');

    document.querySelectorAll('.view-doctor').forEach(button => {
        button.addEventListener('click', function() {
            const doctorId = this.dataset.doctorId;
            console.log('View doctor button clicked for ID:', doctorId); // Debug log

            fetch(`../../api/patient/get-doctor.php?id=${doctorId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Received doctor data:', data); // Debug log
                    if (data.success) {
                        const doctor = data.doctor;

                        // Populate modal with doctor data
                        modalDoctorFullName.textContent = `${doctor.first_name || ''} ${doctor.last_name || ''}`;
                        modalDoctorSpecialization.textContent = doctor.specialization_name || 'N/A';
                        // Determine and set doctor type based on specialization
                         if (empty(doctor.specialization_name) || doctor.specialization_name === 'General Practice') {
                            modalDoctorType.textContent = 'Primary Healthcare Provider';
                        } else {
                            modalDoctorType.textContent = 'Health Specialist';
                        }
                        modalDoctorEmail.textContent = doctor.email || 'N/A';
                        modalDoctorPhone.textContent = doctor.phone || 'N/A';
                        modalDoctorStatus.textContent = doctor.status || 'Unknown';

                        // Populate clinics
                        if (doctor.clinics && doctor.clinics.length > 0) {
                            modalDoctorClinics.innerHTML = '<ul class="list-unstyled">' + doctor.clinics.map(clinic => `<li>${clinic}</li>`).join('') + '</ul>';
                        } else {
                            modalDoctorClinics.innerHTML = '<p class="text-muted">No clinics listed.</p>';
                        }

                        // Populate schedule
                         if (doctor.schedule && doctor.schedule.length > 0) {
                             modalDoctorSchedule.innerHTML = '<ul class="list-unstyled">' + doctor.schedule.map(slot => 
                                 `<li><strong>${slot.day}:</strong> ${slot.start} - ${slot.end}` +
                                 (slot.break_start ? ` (Break: ${slot.break_start} - ${slot.break_end})` : '') +
                                 `</li>`
                             ).join('') + '</ul>';
                         } else {
                             modalDoctorSchedule.innerHTML = '<p class="text-muted">No schedule available.</p>';
                         }

                        // Show the modal
                        viewDoctorModal.show();
                    } else {
                        console.error('Error fetching doctor details:', data.message);
                        alert('Failed to load doctor details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('An error occurred while fetching doctor details.');
                });
        });
    });
});

function empty(value) {
    return value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0) || (typeof value === 'object' && Object.keys(value).length === 0);
}
</script> 