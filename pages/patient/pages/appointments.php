<?php
// Check if PATIENT_ACCESS is defined
if (!defined('PATIENT_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../../config/database.php';

// Get patient ID
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
$patient_id = $patient['id'];

// Get all active doctors with their specializations
$stmt = $conn->prepare("
    SELECT d.id, d.first_name, d.middle_name, d.last_name, s.name as specialization
    FROM doctors d
    LEFT JOIN specializations s ON d.specialization_id = s.id
    WHERE d.status = 'active'
    ORDER BY d.last_name, d.first_name
");
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active clinics
$stmt = $conn->prepare("
    SELECT c.* 
    FROM clinics c
    WHERE c.status = 'active'
    ORDER BY c.name
");
$stmt->execute();
$clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctor's schedules
$stmt = $conn->prepare("
    SELECT ds.*, d.first_name, d.last_name, c.name as clinic_name
    FROM doctor_schedules ds
    JOIN doctors d ON ds.doctor_id = d.id
    JOIN clinics c ON ds.clinic_id = c.id
    WHERE d.status = 'active' AND c.status = 'active'
    ORDER BY ds.day_of_week, ds.start_time
");
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing appointments for the next 30 days to check availability
$stmt = $conn->prepare("
    SELECT a.*, d.first_name, d.last_name, c.name as clinic_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN clinics c ON a.clinic_id = c.id
    WHERE a.date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND a.status != 'cancelled'
    ORDER BY a.date, a.time
");
$stmt->execute();
$existing_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of booked slots for quick lookup
$booked_slots = [];
foreach ($existing_appointments as $appointment) {
    $key = $appointment['doctor_id'] . '_' . $appointment['clinic_id'] . '_' . $appointment['date'] . '_' . $appointment['time'];
    $booked_slots[$key] = true;
    
    // Debug information
    error_log("Booked slot: " . $key);
}

// Debug information
error_log("All booked slots: " . print_r($booked_slots, true));

// Function to get available time slots for a doctor at a clinic on a specific date
function getAvailableTimeSlots($doctor_id, $clinic_id, $date, $schedules, $appointment_map) {
    $day_of_week = date('w', strtotime($date)) + 1; // 1 (Sunday) to 7 (Saturday)
    $available_slots = [];
    
    // Debug information
    error_log("Checking slots for doctor_id: $doctor_id, clinic_id: $clinic_id, date: $date, day_of_week: $day_of_week");
    
    // Find the schedule for this doctor, clinic, and day
    foreach ($schedules as $schedule) {
        if ($schedule['doctor_id'] == $doctor_id && 
            $schedule['clinic_id'] == $clinic_id && 
            $schedule['day_of_week'] == $day_of_week) {
            
            $start_time = strtotime($schedule['start_time']);
            $end_time = strtotime($schedule['end_time']);
            $break_start = $schedule['break_start'] ? strtotime($schedule['break_start']) : null;
            $break_end = $schedule['break_end'] ? strtotime($schedule['break_end']) : null;
            
            // Debug information
            error_log("Found schedule: " . print_r($schedule, true));
            
            for ($time = $start_time; $time < $end_time; $time += 1800) { // 30-minute slots
                if ($break_start && $break_end && $time >= $break_start && $time < $break_end) {
                    continue; // Skip break time
                }
                
                $time_str = date('H:i:s', $time);
                $key = $doctor_id . '_' . $clinic_id . '_' . $date . '_' . $time_str;
                
                // Debug information
                error_log("Checking slot: $key, is_booked: " . (isset($appointment_map[$key]) ? 'true' : 'false'));
                
                // Check if slot is available
                if (!isset($appointment_map[$key])) {
                    $available_slots[] = [
                        'time' => $time_str,
                        'formatted_time' => date('h:i A', $time)
                    ];
                }
            }
            break;
        }
    }
    
    // Debug information
    error_log("Available slots: " . print_r($available_slots, true));
    
    return $available_slots;
}

// Get available slots if doctor, clinic, and date are selected
$available_slots = [];
if (isset($_GET['doctor_id']) && isset($_GET['clinic_id']) && isset($_GET['date'])) {
    $available_slots = getAvailableTimeSlots(
        $_GET['doctor_id'],
        $_GET['clinic_id'],
        $_GET['date'],
        $schedules,
        $booked_slots
    );
}

// Get all appointments for the calendar
$calendar_sql = "SELECT a.*, 
                        d.first_name as doctor_first_name, 
                        d.last_name as doctor_last_name,
                        c.name as clinic_name,
                        CASE 
                            WHEN a.status = 'scheduled' THEN 'primary'
                            WHEN a.status = 'completed' THEN 'success'
                            WHEN a.status = 'cancelled' THEN 'danger'
                            ELSE 'secondary'
                        END as status_color
                 FROM appointments a
                 JOIN doctors d ON a.doctor_id = d.id
                 JOIN clinics c ON a.clinic_id = c.id
                 WHERE a.patient_id = ?";
$calendar_stmt = $conn->prepare($calendar_sql);
$calendar_stmt->execute([$patient_id]);
$calendar_appointments = $calendar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert appointments to calendar events format
$calendar_events = array_map(function($appointment) {
    return [
        'id' => $appointment['id'],
        'title' => "Dr. {$appointment['doctor_last_name']} - {$appointment['clinic_name']}",
        'start' => $appointment['date'] . 'T' . $appointment['time'],
        'className' => "bg-{$appointment['status_color']}",
        'extendedProps' => [
            'purpose' => $appointment['purpose'],
            'status' => $appointment['status'],
            'doctor_name' => "Dr. {$appointment['doctor_last_name']}, {$appointment['doctor_first_name']}",
            'clinic_name' => $appointment['clinic_name']
        ]
    ];
}, $calendar_appointments);

// Get doctor's schedules for the calendar
$schedules_sql = "SELECT ds.*, d.first_name, d.last_name, c.name as clinic_name,
                        CASE 
                            WHEN ds.day_of_week = 1 THEN 'Sunday'
                            WHEN ds.day_of_week = 2 THEN 'Monday'
                            WHEN ds.day_of_week = 3 THEN 'Tuesday'
                            WHEN ds.day_of_week = 4 THEN 'Wednesday'
                            WHEN ds.day_of_week = 5 THEN 'Thursday'
                            WHEN ds.day_of_week = 6 THEN 'Friday'
                            WHEN ds.day_of_week = 7 THEN 'Saturday'
                        END as day_name
                 FROM doctor_schedules ds
                 JOIN doctors d ON ds.doctor_id = d.id
                 JOIN clinics c ON ds.clinic_id = c.id
                 WHERE d.status = 'active' AND c.status = 'active'";
$schedules_stmt = $conn->prepare($schedules_sql);
$schedules_stmt->execute();
$doctor_schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert schedules to calendar events format
$schedule_events = array_map(function($schedule) {
    // Convert database day (1=Sunday to 7=Saturday) to FullCalendar format (0=Sunday to 6=Saturday)
    $calendarDay = $schedule['day_of_week'] - 1;
    
    return [
        'id' => 'schedule_' . $schedule['id'],
        'title' => "Dr. {$schedule['last_name']} - {$schedule['clinic_name']}",
        'daysOfWeek' => [$calendarDay],
        'startTime' => $schedule['start_time'],
        'endTime' => $schedule['end_time'],
        'display' => 'background',
        'className' => 'available-slot',
        'extendedProps' => [
            'doctor_id' => $schedule['doctor_id'],
            'clinic_id' => $schedule['clinic_id'],
            'doctor_name' => "Dr. {$schedule['last_name']}, {$schedule['first_name']}",
            'clinic_name' => $schedule['clinic_name'],
            'day_name' => $schedule['day_name']
        ]
    ];
}, $doctor_schedules);

// Combine appointment and schedule events
$all_calendar_events = array_merge($calendar_events, $schedule_events);

?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">My Appointments</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleAppointmentModal">
            <span class="material-icons align-text-bottom">add</span>
            Schedule New Appointment
        </button>
    </div>

    <!-- View Toggle -->
    <div class="btn-group mb-4" role="group">
        <button type="button" class="btn btn-outline-primary active" id="calendarViewBtn">
            <span class="material-icons align-text-bottom">calendar_month</span>
            Calendar View
        </button>
        <button type="button" class="btn btn-outline-primary" id="listViewBtn">
            <span class="material-icons align-text-bottom">list</span>
            List View
        </button>
    </div>

    <!-- Calendar View -->
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4" id="calendarView">
                <div class="card-body">
                    <div id="appointmentCalendar"></div>
                    
                    <!-- Calendar Legend -->
                    <div class="calendar-legend mt-3">
                        <h6 class="mb-2">Legend:</h6>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="d-flex align-items-center">
                                <div class="legend-color bg-primary me-2" style="width: 20px; height: 20px; border-radius: 4px;"></div>
                                <span>Scheduled</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="legend-color bg-success me-2" style="width: 20px; height: 20px; border-radius: 4px;"></div>
                                <span>Completed</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="legend-color bg-danger me-2" style="width: 20px; height: 20px; border-radius: 4px;"></div>
                                <span>Cancelled</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="legend-color available-slot me-2" style="width: 20px; height: 20px; border-radius: 4px;"></div>
                                <span>Available Schedule</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Upcoming Appointments</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="upcomingAppointmentsList">
                        <?php
                        // Get upcoming appointments
                        $upcoming_sql = "SELECT a.*, 
                                        d.first_name as doctor_first_name, 
                                        d.last_name as doctor_last_name,
                                        c.name as clinic_name,
                                        c.address as clinic_address
                                        FROM appointments a
                                        JOIN doctors d ON a.doctor_id = d.id
                                        JOIN clinics c ON a.clinic_id = c.id
                                        WHERE a.patient_id = ? 
                                        AND a.date >= CURDATE()
                                        AND a.status != 'cancelled'
                                        ORDER BY a.date ASC, a.time ASC
                                        LIMIT 5";
                        $upcoming_stmt = $conn->prepare($upcoming_sql);
                        $upcoming_stmt->execute([$patient_id]);
                        $upcoming_appointments = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($upcoming_appointments) > 0):
                            foreach ($upcoming_appointments as $appointment):
                                $appointment_date = new DateTime($appointment['date']);
                                $appointment_time = new DateTime($appointment['time']);
                                $is_today = $appointment_date->format('Y-m-d') === date('Y-m-d');
                        ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            Dr. <?php echo htmlspecialchars($appointment['doctor_last_name'] . ', ' . $appointment['doctor_first_name']); ?>
                                        </h6>
                                        <p class="mb-1 text-muted">
                                            <?php echo htmlspecialchars($appointment['clinic_name']); ?>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo $appointment_date->format('F d, Y'); ?> at 
                                            <?php echo $appointment_time->format('h:i A'); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo match($appointment['status']) {
                                            'scheduled' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">Purpose: <?php echo htmlspecialchars($appointment['purpose']); ?></small>
                                </div>
                                <?php if ($is_today): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-info">Today's Appointment</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <div class="list-group-item text-center py-4">
                                <p class="text-muted mb-0">No upcoming appointments</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="?page=appointments&view=list" class="btn btn-link text-decoration-none p-0">
                        View All Appointments
                        <span class="material-icons align-text-bottom">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- List View -->
    <div class="card" id="listView" style="display: none;">
    <!-- Filters -->
        <div class="card-header bg-white">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="appointments">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="scheduled" <?php echo isset($_GET['status']) && $_GET['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <select class="form-select" name="date_range">
                        <option value="upcoming" <?php echo isset($_GET['date_range']) && $_GET['date_range'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="past" <?php echo isset($_GET['date_range']) && $_GET['date_range'] === 'past' ? 'selected' : ''; ?>>Past</option>
                        <option value="all" <?php echo isset($_GET['date_range']) && $_GET['date_range'] === 'all' ? 'selected' : ''; ?>>All Time</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Doctor</label>
                    <select class="form-select" name="doctor">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" <?php echo isset($_GET['doctor']) && $_GET['doctor'] == $doctor['id'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['last_name'] . ', ' . $doctor['first_name']); ?> 
                                (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Search appointments..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
            </form>
        </div>

        <!-- Existing appointments table code ... -->
    </div>
</div>

<!-- Add FullCalendar CSS and JS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

<style>
.fc-event {
    cursor: pointer;
    padding: 2px 4px;
}
.fc-event-title {
    font-weight: 500;
}
.fc-event-time {
    font-size: 0.85em;
}
.fc-daygrid-event-dot {
    border-color: inherit !important;
}
.fc-event.bg-primary {
    background-color: #2196F3 !important;
    border-color: #2196F3 !important;
}
.fc-event.bg-success {
    background-color: #4CAF50 !important;
    border-color: #4CAF50 !important;
}
.fc-event.bg-danger {
    background-color: #DC3545 !important;
    border-color: #DC3545 !important;
}
.fc-event.bg-secondary {
    background-color: #6C757D !important;
    border-color: #6C757D !important;
}
.available-slot {
    background-color: rgba(33, 150, 243, 0.1) !important;
    border: 1px dashed #2196F3 !important;
}
.fc-event.available-slot:hover {
    background-color: rgba(33, 150, 243, 0.2) !important;
    cursor: pointer;
}

.calendar-legend {
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.legend-color {
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.legend-color.available-slot {
    background-color: rgba(33, 150, 243, 0.1) !important;
    border: 1px dashed #2196F3 !important;
}

.list-group-item {
    border-left: none;
    border-right: none;
    padding: 1rem;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

#upcomingAppointmentsList {
    max-height: 600px;
    overflow-y: auto;
}

/* Custom scrollbar for the appointments list */
#upcomingAppointmentsList::-webkit-scrollbar {
    width: 6px;
}

#upcomingAppointmentsList::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#upcomingAppointmentsList::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

#upcomingAppointmentsList::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize FullCalendar
    var calendarEl = document.getElementById('appointmentCalendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: <?php echo json_encode($all_calendar_events); ?>,
        dateClick: function(info) {
            // Check if the clicked date is in the future
            const clickedDate = new Date(info.date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (clickedDate < today) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Date',
                    text: 'Cannot schedule appointments for past dates.'
                });
                return;
            }

            // Get available doctors and clinics for this date
            // Convert JavaScript day (0=Sunday to 6=Saturday) to database format (1=Sunday to 7=Saturday)
            const dayOfWeek = clickedDate.getDay() + 1;
            const availableSchedules = <?php echo json_encode($doctor_schedules); ?>.filter(schedule => 
                schedule.day_of_week == dayOfWeek
            );

            // Only show SweetAlert if there are truly no schedules for this day
            if (availableSchedules.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'No Available Schedules',
                    text: 'No doctors are scheduled to work on this date.'
                });
                return;
            }

            // Always open the modal if there are schedules
            const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleAppointmentModal'));
            
            // Reset form
            document.getElementById('appointmentForm').reset();
            
            // Set the selected date
            document.querySelector('input[name="date"]').value = info.dateStr;
            
            // Update doctor options based on available schedules
            const doctorSelect = document.querySelector('select[name="doctor_id"]');
            const clinicSelect = document.querySelector('select[name="clinic_id"]');
            
            // Clear existing options except the first one
            while (doctorSelect.options.length > 1) {
                doctorSelect.remove(1);
            }
            while (clinicSelect.options.length > 1) {
                clinicSelect.remove(1);
            }

            // Add available doctors and clinics
            const availableDoctors = new Set();
            const availableClinics = new Set();
            
            availableSchedules.forEach(schedule => {
                availableDoctors.add(schedule.doctor_id);
                availableClinics.add(schedule.clinic_id);
            });

            <?php foreach ($doctors as $doctor): ?>
                if (availableDoctors.has(<?php echo $doctor['id']; ?>)) {
                    const option = new Option(
                        `Dr. <?php echo htmlspecialchars($doctor['last_name'] . ', ' . $doctor['first_name']); ?> 
                        (<?php echo htmlspecialchars($doctor['specialization']); ?>)`,
                        <?php echo $doctor['id']; ?>
                    );
                    doctorSelect.add(option);
                }
            <?php endforeach; ?>

            <?php foreach ($clinics as $clinic): ?>
                if (availableClinics.has(<?php echo $clinic['id']; ?>)) {
                    const option = new Option(
                        `<?php echo htmlspecialchars($clinic['name']); ?>`,
                        <?php echo $clinic['id']; ?>
                    );
                    clinicSelect.add(option);
                }
            <?php endforeach; ?>

            // Show the modal
            scheduleModal.show();
        },
        eventClick: function(info) {
            if (info.event.id.startsWith('schedule_')) {
                // Handle schedule background click
                return;
            }
            // Show appointment details modal
            fetch(`../../api/get_appointment.php?id=${info.event.id}`)
                .then(response => response.json())
                .then(data => {
                    console.log('API response:', data); // Log the full response
                    if (data.success) {
                        const appointment = data.appointment;
                        document.getElementById('modalDoctorName').textContent = 
                            `Dr. ${appointment.doctor_last_name}, ${appointment.doctor_first_name}`;
                        document.getElementById('modalDate').textContent = 
                            new Date(appointment.date).toLocaleDateString('en-US', { 
                                weekday: 'long', 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            });
                        // Display the formatted time directly from the API response
                        document.getElementById('modalTime').textContent = appointment.time || 'N/A';
                        document.getElementById('modalStatus').innerHTML = 
                            `<span class="badge bg-${appointment.status === 'scheduled' ? 'primary' : 
                                (appointment.status === 'completed' ? 'success' : 'danger')}">${
                                appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span>`;
                        document.getElementById('modalClinic').textContent = appointment.clinic_name;
                        document.getElementById('modalAddress').textContent = appointment.clinic_address;
                        document.getElementById('modalPurpose').textContent = appointment.purpose;
                        document.getElementById('modalNotes').textContent = appointment.notes || 'No additional notes';
                        // Show the modal
                        var modal = new bootstrap.Modal(document.getElementById('appointmentDetailsModal'));
                        modal.show();
                    } else {
                        console.error('API returned error:', data);
                        alert('Error loading appointment details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error loading appointment details:', error);
                    alert('Error loading appointment details: ' + error);
                });
        }
    });
    calendar.render();

    // View toggle functionality
    document.getElementById('calendarViewBtn').addEventListener('click', function() {
        this.classList.add('active');
        document.getElementById('listViewBtn').classList.remove('active');
        document.getElementById('calendarView').style.display = 'block';
        document.getElementById('listView').style.display = 'none';
    });

    document.getElementById('listViewBtn').addEventListener('click', function() {
        this.classList.add('active');
        document.getElementById('calendarViewBtn').classList.remove('active');
        document.getElementById('calendarView').style.display = 'none';
        document.getElementById('listView').style.display = 'block';
    });

    // Add event listeners for doctor, clinic, and date selection
    document.querySelector('select[name="doctor_id"]').addEventListener('change', updateTimeSlots);
    document.querySelector('select[name="clinic_id"]').addEventListener('change', updateTimeSlots);
    document.querySelector('input[name="date"]').addEventListener('change', updateTimeSlots);

    function updateTimeSlots() {
        const form = document.getElementById('appointmentForm');
        const doctorId = document.querySelector('select[name="doctor_id"]').value;
        const clinicId = document.querySelector('select[name="clinic_id"]').value;
        const date = document.querySelector('input[name="date"]').value;
        const timeSelect = document.querySelector('select[name="time"]');

        // Reset time select
        timeSelect.innerHTML = '<option value="">Select time...</option>';
        timeSelect.disabled = true;

        // Only proceed if all required fields are selected
        if (!doctorId || !clinicId || !date) {
            timeSelect.innerHTML = '<option value="">Select doctor, clinic, and date first</option>';
            return;
        }

        // Show loading state
        timeSelect.innerHTML = '<option value="">Loading available slots...</option>';

        // Fetch available slots from the server
        fetch(`../../api/get_available_slots.php?doctor_id=${doctorId}&clinic_id=${clinicId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const availableSlots = data.available_slots;
                    console.log('Available slots from server:', availableSlots);

                    // Clear the select element
                    timeSelect.innerHTML = '<option value="">Select time...</option>';

                    // Add available slots to the select
                    availableSlots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.time;
                        option.text = slot.formatted_time;
                        timeSelect.appendChild(option);
                    });
                    console.log('Options in dropdown after populate:', timeSelect.options.length);

                    // Enable the select after populating
                    timeSelect.disabled = false;

                    // If no slots are available, show a message and disable the submit button
                    if (availableSlots.length === 0) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.text = 'No available time slots';
                        option.disabled = true;
                        timeSelect.appendChild(option);
                        // Disable submit button
                        form.querySelector('button[type="submit"]').disabled = true;
                    } else {
                        // Enable submit button if slots are available
                        form.querySelector('button[type="submit"]').disabled = false;
                    }
                } else {
                    console.error('Error fetching available slots:', data.message);
                    timeSelect.innerHTML = '<option value="">Error loading slots</option>';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load available time slots. Please try again.'
                    }).then(() => {
                        // Refresh the time slots after showing the error
                        updateTimeSlots();
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                timeSelect.innerHTML = '<option value="">Error loading slots</option>';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load available time slots. Please try again.'
                }).then(() => {
                    // Refresh the time slots after showing the error
                    updateTimeSlots();
                });
            });
    }

    // Remove any existing event listeners
    document.querySelector('select[name="doctor_id"]').removeEventListener('change', updateTimeSlots);
    document.querySelector('select[name="clinic_id"]').removeEventListener('change', updateTimeSlots);
    document.querySelector('input[name="date"]').removeEventListener('change', updateTimeSlots);

    // Add event listeners for doctor, clinic, and date selection
    document.querySelector('select[name="doctor_id"]').addEventListener('change', updateTimeSlots);
    document.querySelector('select[name="clinic_id"]').addEventListener('change', updateTimeSlots);
    document.querySelector('input[name="date"]').addEventListener('change', updateTimeSlots);

    // Initialize time select with default message
    document.querySelector('select[name="time"]').innerHTML = '<option value="">Select doctor, clinic, and date first</option>';
    document.querySelector('select[name="time"]').disabled = true;

    // Form submission handler
    const form = document.getElementById('appointmentForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        
        fetch('../../api/appointments.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message
                }).then(() => {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleAppointmentModal'));
                    modal.hide();
                    
                    // Reset form
                    form.reset();
                    
                    // Add the new appointment to the calendar
                    const newAppointment = {
                        id: data.appointment_id,
                        title: `Dr. ${data.doctor_name} - ${data.clinic_name}`,
                        start: `${data.date}T${data.time}`,
                        className: 'bg-primary',
                        extendedProps: {
                            purpose: data.purpose,
                            status: 'scheduled',
                            doctor_name: data.doctor_name,
                            clinic_name: data.clinic_name
                        }
                    };
                    
                    // Add to calendar
                    calendar.addEvent(newAppointment);
                    
                    // Update upcoming appointments list
                    updateUpcomingAppointments();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An error occurred while processing your request. Please try again.'
            });
        });
    });

    // Function to update upcoming appointments list
    function updateUpcomingAppointments() {
        fetch('../../api/appointments.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const upcomingList = document.getElementById('upcomingAppointmentsList');
                    upcomingList.innerHTML = ''; // Clear existing list
                    
                    // Filter and sort upcoming appointments
                    const upcoming = data.appointments
                        .filter(apt => new Date(apt.date) >= new Date() && apt.status !== 'cancelled')
                        .sort((a, b) => new Date(a.date) - new Date(b.date))
                        .slice(0, 5); // Get only the next 5 appointments
                    
                    if (upcoming.length > 0) {
                        upcoming.forEach(appointment => {
                            const appointmentDate = new Date(appointment.date);
                            const appointmentTime = new Date(`2000-01-01T${appointment.time}`);
                            const isToday = appointmentDate.toDateString() === new Date().toDateString();
                            
                            const appointmentHtml = `
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">${appointment.doctor_name}</h6>
                                            <p class="mb-1 text-muted">${appointment.clinic_name}</p>
                                            <small class="text-muted">
                                                ${appointmentDate.toLocaleDateString('en-US', { 
                                                    year: 'numeric', 
                                                    month: 'long', 
                                                    day: 'numeric' 
                                                })} at 
                                                ${appointmentTime.toLocaleTimeString('en-US', {
                                                    hour: 'numeric',
                                                    minute: '2-digit'
                                                })}
                                            </small>
                                        </div>
                                        <span class="badge bg-primary">Scheduled</span>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">Purpose: ${appointment.purpose}</small>
                                    </div>
                                    ${isToday ? '<div class="mt-2"><span class="badge bg-info">Today\'s Appointment</span></div>' : ''}
                                </div>
                            `;
                            upcomingList.innerHTML += appointmentHtml;
                        });
                    } else {
                        upcomingList.innerHTML = `
                            <div class="list-group-item text-center py-4">
                                <p class="text-muted mb-0">No upcoming appointments</p>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Error updating appointments:', error);
            });
    }
});
</script>

<!-- Schedule Appointment Modal -->
<div class="modal fade" id="scheduleAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="../../api/appointments.php" method="POST" id="appointmentForm">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Select Doctor</label>
                            <select class="form-select" name="doctor_id" required>
                                <option value="">Choose a doctor...</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>" <?php echo isset($_GET['doctor_id']) && $_GET['doctor_id'] == $doctor['id'] ? 'selected' : ''; ?>>
                                        Dr. <?php echo htmlspecialchars($doctor['last_name'] . ', ' . $doctor['first_name']); ?> 
                                        (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Clinic</label>
                            <select class="form-select" name="clinic_id" required>
                                <option value="">Select clinic...</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?php echo $clinic['id']; ?>" <?php echo isset($_GET['clinic_id']) && $_GET['clinic_id'] == $clinic['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($clinic['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" required 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : ''; ?>">
                            <small class="text-muted">Select any future date when the doctor is available</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <select class="form-select" name="time" required>
                                <option value="">Select time...</option>
                                <?php
                                // Get available time slots if doctor, clinic, and date are selected
                                if (isset($_GET['doctor_id']) && isset($_GET['clinic_id']) && isset($_GET['date'])) {
                                    $available_slots = getAvailableTimeSlots(
                                        $_GET['doctor_id'],
                                        $_GET['clinic_id'],
                                        $_GET['date'],
                                        $schedules,
                                        $booked_slots
                                    );
                                    foreach ($available_slots as $slot) {
                                        echo '<option value="' . htmlspecialchars($slot['time']) . '">' . 
                                             htmlspecialchars($slot['formatted_time']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <small class="text-muted">Available time slots will be shown after selecting doctor, clinic, and date</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purpose of Visit</label>
                        <textarea class="form-control" name="purpose" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Appointment Details Modal -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Doctor:</strong> <span id="modalDoctorName"></span></p>
                        <p><strong>Date:</strong> <span id="modalDate"></span></p>
                        <p><strong>Time:</strong> <span id="modalTime"></span></p>
                        <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Clinic:</strong> <span id="modalClinic"></span></p>
                        <p><strong>Address:</strong> <span id="modalAddress"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <h6>Purpose</h6>
                    <p id="modalPurpose"></p>
                </div>
                <div class="mb-3">
                    <h6>Notes</h6>
                    <p id="modalNotes"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Medbuddy/assets/js/patient.js"></script>

<?php if (isset($_GET['success'])): ?>
<script>
    // Replace SweetAlert with a simple alert
    alert('<?php echo htmlspecialchars($_GET['success']); ?>');
</script>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<script>
    // Keep SweetAlert for errors as they need more attention
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?php echo htmlspecialchars($_GET['error']); ?>'
    });
</script>
<?php endif; ?>
</body>
</html> 