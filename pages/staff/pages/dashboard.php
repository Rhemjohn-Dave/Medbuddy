<?php
// Check if database connection is available
if (!isset($conn)) {
    die("Database connection not available");
}

// Get selected date from request, default to today
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get staff's assigned clinics
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$staff_user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT sc.clinic_id FROM staff_clinics sc JOIN staff s ON sc.staff_id = s.id WHERE s.user_id = ?");
$stmt->execute([$staff_user_id]);
$assigned_clinics = $stmt->fetchAll(PDO::FETCH_COLUMN);

try {
    // Get total appointments for selected date
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE DATE(date) = ? AND status = 'scheduled'
    ");
    $stmt->execute([$selected_date]);
    $total_appointments = $stmt->fetchColumn();

    // Get pending vital signs for today
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM appointments a 
        WHERE DATE(a.date) = ? 
        AND a.status = 'scheduled' 
        AND (a.vitals_recorded = 0 OR a.vitals_recorded IS NULL)
    ");
    $stmt->execute([$selected_date]);
    $pending_vitals = $stmt->fetchColumn();

    // Get completed consultations today
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE DATE(date) = ? AND status = 'completed'
    ");
    $stmt->execute([$selected_date]);
    $completed_consultations = $stmt->fetchColumn();

    // Get upcoming appointments (next 7 days)
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND status = 'scheduled'
    ");
    $stmt->execute();
    $upcoming_appointments = $stmt->fetchColumn();

    // Get today's consultations with vital signs status, filtered by assigned clinics
    if (!empty($assigned_clinics)) {
        $placeholders = implode(',', array_fill(0, count($assigned_clinics), '?'));
        $consult_sql = "
            SELECT 
                a.id,
                a.date,
                a.time,
                a.status,
                a.vitals_recorded,
                p.first_name as patient_first_name,
                p.last_name as patient_last_name,
                p.contact_number as patient_contact,
                d.first_name as doctor_first_name,
                d.last_name as doctor_last_name,
                c.name as clinic_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            JOIN clinics c ON a.clinic_id = c.id
            WHERE DATE(a.date) = ?
            AND a.status = 'scheduled'
            AND a.clinic_id IN ($placeholders)
            ORDER BY a.time ASC
        ";
        $params = array_merge([$selected_date], $assigned_clinics);
        $stmt = $conn->prepare($consult_sql);
        $stmt->execute($params);
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $consultations = [];
    }

    // Get recent activities (last 5 appointments with status changes)
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.date,
            a.time,
            a.status,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name,
            d.first_name as doctor_first_name,
            d.last_name as doctor_last_name,
            a.updated_at
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        ORDER BY a.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get appointment statistics for the chart (last 7 days)
    $stmt = $conn->prepare("
        SELECT 
            DATE(date) as appointment_date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN vitals_recorded = 1 THEN 1 ELSE 0 END) as vitals_recorded
        FROM appointments
        WHERE date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
        GROUP BY DATE(date)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $appointment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    // Set default values in case of error
    $total_appointments = 0;
    $pending_vitals = 0;
    $completed_consultations = 0;
    $upcoming_appointments = 0;
    $consultations = [];
    $recent_activities = [];
    $appointment_stats = [];
}
?>

<!-- Add SweetAlert2 CSS and JS in the head section -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid py-4">
    <!-- Date Selector -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form id="dateForm" class="d-flex align-items-center">
                        <label for="selectedDate" class="me-2">Select Date:</label>
                        <input type="date" id="selectedDate" name="date" class="form-control" style="width: auto;" 
                               value="<?php echo $selected_date; ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Today's Appointments</h5>
                    <h2 class="card-text"><?php echo $total_appointments; ?></h2>
                    <small>Total scheduled appointments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Pending Vitals</h5>
                    <h2 class="card-text"><?php echo $pending_vitals; ?></h2>
                    <small>Awaiting vital signs recording</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Completed Today</h5>
                    <h2 class="card-text"><?php echo $completed_consultations; ?></h2>
                    <small>Consultations completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Upcoming</h5>
                    <h2 class="card-text"><?php echo $upcoming_appointments; ?></h2>
                    <small>Next 7 days</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title mb-4">Appointments Overview</h5>
                    <div class="chart-container">
                        <canvas id="appointmentsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title mb-4">Today's Consultations</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Clinic</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations as $consultation): ?>
                                <tr>
                                    <td><?php echo date('h:i A', strtotime($consultation['time'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($consultation['patient_first_name'] . ' ' . $consultation['patient_last_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($consultation['patient_contact']); ?></small>
                                    </td>
                                    <td>Dr. <?php echo htmlspecialchars($consultation['doctor_first_name'] . ' ' . $consultation['doctor_last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['clinic_name']); ?></td>
                                    <td>
                                        <?php if ($consultation['vitals_recorded']): ?>
                                            <span class="badge bg-success">Ready for Consultation</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending Vitals</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Activities</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($activity['date'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($activity['time'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['patient_first_name'] . ' ' . $activity['patient_last_name']); ?></td>
                                    <td>Dr. <?php echo htmlspecialchars($activity['doctor_first_name'] . ' ' . $activity['doctor_last_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $activity['status'] === 'completed' ? 'success' : 
                                                ($activity['status'] === 'scheduled' ? 'primary' : 
                                                ($activity['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($activity['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y h:i A', strtotime($activity['updated_at'])); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: none;
    margin-bottom: 1rem;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.card-title {
    color: #344767;
    font-weight: 600;
    font-size: 1.1rem;
}

.badge {
    font-size: 0.8rem;
    padding: 0.5em 0.8em;
    font-weight: 500;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    color: #344767;
    border-bottom: 2px solid #e9ecef;
}

.text-muted {
    font-size: 0.85rem;
    color: #6c757d !important;
}

/* Chart styles */
.chart-container {
    position: relative;
    height: 350px;
    width: 100%;
    margin: 0 auto;
}

#appointmentsChart {
    width: 100% !important;
    height: 100% !important;
}

/* Card body padding adjustments */
.card-body {
    padding: 1.5rem;
}

/* Table responsive adjustments */
.table-responsive {
    margin: 0;
    padding: 0;
}

/* Status badge colors */
.bg-success {
    background-color: #2ecc71 !important;
}

.bg-warning {
    background-color: #f1c40f !important;
}

.bg-primary {
    background-color: #3498db !important;
}

.bg-info {
    background-color: #3498db !important;
}

/* Chart legend adjustments */
.chartjs-legend {
    margin-top: 1rem;
    text-align: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .chart-container {
        height: 300px;
    }
    
    .card-body {
        padding: 1rem;
    }
}
</style>

<script>
// Initialize date picker
document.getElementById('selectedDate').addEventListener('change', function() {
    window.location.href = '?page=dashboard&date=' + this.value;
});

// Initialize appointments chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('appointmentsChart').getContext('2d');
    const appointmentStats = <?php echo json_encode($appointment_stats); ?>;

    // Prepare data for the chart
    const labels = appointmentStats.map(stat => new Date(stat.appointment_date).toLocaleDateString());
    const totalData = appointmentStats.map(stat => stat.total);
    const completedData = appointmentStats.map(stat => stat.completed);
    const vitalsData = appointmentStats.map(stat => stat.vitals_recorded);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Appointments',
                data: totalData,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'Completed',
                data: completedData,
                borderColor: '#2ecc71',
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'Vitals Recorded',
                data: vitalsData,
                borderColor: '#f1c40f',
                backgroundColor: 'rgba(241, 196, 15, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                title: {
                    display: true,
                    text: 'Appointment Statistics (Last 7 Days)',
                    padding: {
                        top: 10,
                        bottom: 20
                    },
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 12
                        }
                    },
                    title: {
                        display: true,
                        text: 'Number of Appointments',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        padding: {
                            top: 10,
                            bottom: 10
                        }
                    },
                    grid: {
                        drawBorder: false,
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        padding: {
                            top: 10,
                            bottom: 10
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
});
</script> 