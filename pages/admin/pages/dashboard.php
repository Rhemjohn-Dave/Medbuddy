<?php
// Check if this file is being included
if (!defined('ADMIN_ACCESS')) {
    header("Location: ../../index.php");
    exit();
}

// Fetch real data for charts
require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get weekly data
$weeklyData = [];
$weeklyLabels = [];
$weekStart = date('Y-m-d', strtotime('-6 days'));
$weekEnd = date('Y-m-d');

try {
    // Get user registrations
    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM users 
            WHERE created_at BETWEEN ? AND ? 
            GROUP BY DATE(created_at) 
            ORDER BY date";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$weekStart, $weekEnd]);
    
    // Initialize with zeros for all days
    $weeklyData = array_fill(0, 7, 0);
    $weeklyLabels = [];
    
    // Generate labels for the last 7 days
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $weeklyLabels[] = date('D', strtotime($date));
    }
    
    // Fill in the actual data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dayIndex = array_search(date('D', strtotime($row['date'])), $weeklyLabels);
        if ($dayIndex !== false) {
            $weeklyData[$dayIndex] = (int)$row['count'];
        }
    }
    
    error_log("Weekly Activity Data: " . json_encode([
        'labels' => $weeklyLabels,
        'data' => $weeklyData
    ]));
} catch (PDOException $e) {
    error_log("Error fetching weekly data: " . $e->getMessage());
}

// Get monthly data
$monthlyData = [];
$monthlyLabels = [];
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

try {
    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM users 
            WHERE created_at BETWEEN ? AND ? 
            GROUP BY DATE(created_at) 
            ORDER BY date";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$monthStart, $monthEnd]);
    
    // Initialize with zeros for 4 weeks
    $monthlyData = array_fill(0, 4, 0);
    $monthlyLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $weekNumber = ceil(date('d', strtotime($row['date'])) / 7) - 1;
        if ($weekNumber >= 0 && $weekNumber < 4) {
            $monthlyData[$weekNumber] += (int)$row['count'];
        }
    }
    
    error_log("Monthly Activity Data: " . json_encode([
        'labels' => $monthlyLabels,
        'data' => $monthlyData
    ]));
} catch (PDOException $e) {
    error_log("Error fetching monthly data: " . $e->getMessage());
}

// Get yearly data
$yearlyData = [];
$yearlyLabels = [];
$yearStart = date('Y-01-01');
$yearEnd = date('Y-12-31');

try {
    $sql = "SELECT MONTH(created_at) as month, COUNT(*) as count 
            FROM users 
            WHERE created_at BETWEEN ? AND ? 
            GROUP BY MONTH(created_at) 
            ORDER BY month";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$yearStart, $yearEnd]);
    
    // Initialize with zeros for all months
    $yearlyData = array_fill(0, 12, 0);
    $yearlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthIndex = $row['month'] - 1;
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $yearlyData[$monthIndex] = (int)$row['count'];
        }
    }
    
    error_log("Yearly Activity Data: " . json_encode([
        'labels' => $yearlyLabels,
        'data' => $yearlyData
    ]));
} catch (PDOException $e) {
    error_log("Error fetching yearly data: " . $e->getMessage());
}

// Get user distribution
try {
    // First, let's log all unique roles in the database
    $debugSql = "SELECT DISTINCT role FROM users";
    $debugStmt = $conn->prepare($debugSql);
    $debugStmt->execute();
    $roles = $debugStmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("Available roles in database: " . json_encode($roles));

    // Now get the distribution
    $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    // Initialize with all possible roles
    $userDistribution = [
        'doctor' => 0,    // Changed from 'doctors' to match database
        'patient' => 0,   // Changed from 'patients' to match database
        'staff' => 0,
        'admin' => 0      // Added admin role
    ];

    // Log raw data from database
    $rawData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rawData[] = $row;
        if (isset($userDistribution[$row['role']])) {
            $userDistribution[$row['role']] = (int)$row['count'];
        }
    }
    error_log("Raw distribution data from database: " . json_encode($rawData));
    error_log("Processed user distribution: " . json_encode($userDistribution));

} catch (PDOException $e) {
    error_log("Error fetching user distribution: " . $e->getMessage());
    $userDistribution = [
        'doctor' => 0,
        'patient' => 0,
        'staff' => 0,
        'admin' => 0
    ];
}

// Get unread message count
$unread_sql = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->execute([$_SESSION['user_id']]);
$unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<style>
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
    overflow: hidden;
}
.card {
    height: 100%;
    display: flex;
    flex-direction: column;
}
.card-body {
    flex: 1;
    overflow: hidden;
    position: relative;
}
</style>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                    <span class="material-icons text-primary">people</span>
                </div>
                <div>
                    <h6 class="card-title mb-0">Total Users</h6>
                    <h2 class="mb-0 mt-2" id="totalUsers">0</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                    <span class="material-icons text-success">local_hospital</span>
                </div>
                <div>
                    <h6 class="card-title mb-0">Active Doctors</h6>
                    <h2 class="mb-0 mt-2" id="activeDoctors">0</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                    <span class="material-icons text-warning">schedule</span>
                </div>
                <div>
                    <h6 class="card-title mb-0">Appointments Today</h6>
                    <h2 class="mb-0 mt-2" id="appointmentsToday">0</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                    <span class="material-icons text-danger">priority_high</span>
                </div>
                <div>
                    <h6 class="card-title mb-0">Pending Approvals</h6>
                    <h2 class="mb-0 mt-2" id="pendingApprovals">0</h2>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Messages Card -->
<div class="col-md-6 col-lg-3">
    <div class="card bg-primary text-white">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title mb-0">Messages</h6>
                    <h2 class="mt-2 mb-0"><?php echo $unread_count; ?></h2>
                    <small>Unread Messages</small>
                </div>
                <i class="material-icons">mail</i>
            </div>
        </div>
        <div class="card-footer bg-transparent border-0">
            <a href="index.php?page=messages" class="text-white text-decoration-none">
                View Messages <i class="material-icons align-middle">arrow_forward</i>
            </a>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-md-8 mb-3">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">System Activity</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-primary active" data-period="week">Week</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="month">Month</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="year">Year</button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">User Distribution</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Activity</h5>
                <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="recentActivities">
                    <!-- Activities will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Chart data configuration with real data
const chartData = {
    week: {
        labels: <?php echo json_encode($weeklyLabels); ?>,
        data: <?php echo json_encode($weeklyData); ?>,
        max: Math.max(...<?php echo json_encode($weeklyData); ?>) * 1.2
    },
    month: {
        labels: <?php echo json_encode($monthlyLabels); ?>,
        data: <?php echo json_encode($monthlyData); ?>,
        max: Math.max(...<?php echo json_encode($monthlyData); ?>) * 1.2
    },
    year: {
        labels: <?php echo json_encode($yearlyLabels); ?>,
        data: <?php echo json_encode($yearlyData); ?>,
        max: Math.max(...<?php echo json_encode($yearlyData); ?>) * 1.2
    }
};

const userDistribution = {
    labels: ['Doctors', 'Patients', 'Staff', 'Admins'],
    data: [
        <?php echo $userDistribution['doctor']; ?>,
        <?php echo $userDistribution['patient']; ?>,
        <?php echo $userDistribution['staff']; ?>,
        <?php echo $userDistribution['admin']; ?>
    ]
};

let activityChart = null;

function initPage() {
    loadDashboardData();
    initCharts();
    setupEventListeners();
}

function setupEventListeners() {
    document.querySelectorAll('[data-period]').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('[data-period]').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            updateActivityChart(this.dataset.period);
        });
    });
}

function loadDashboardData() {
    fetch('/medbuddy/api/admin/dashboard-stats.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            document.getElementById('totalUsers').textContent = data.totalUsers;
            document.getElementById('activeDoctors').textContent = data.activeDoctors;
            document.getElementById('appointmentsToday').textContent = data.appointmentsToday;
            document.getElementById('pendingApprovals').textContent = data.pendingApprovals;
        })
        .catch(error => {
            console.error('Error loading dashboard stats:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load dashboard statistics: ' + error.message
            });
        });

    fetch('/medbuddy/api/admin/recent-activities.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            const activitiesList = document.getElementById('recentActivities');
            if (!data.activities || !Array.isArray(data.activities)) {
                throw new Error('Invalid activities data received');
            }
            activitiesList.innerHTML = data.activities.map(activity => `
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${activity.title}</h6>
                        <small class="text-muted">${activity.time}</small>
                    </div>
                    <p class="mb-1">${activity.description}</p>
                </div>
            `).join('');
        })
        .catch(error => {
            console.error('Error loading recent activities:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load recent activities: ' + error.message
            });
        });
}

function initCharts() {
    try {
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        
        if (activityChart) {
            activityChart.destroy();
        }

        const weekData = chartData.week;

        activityChart = new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: weekData.labels,
                datasets: [{
                    label: 'User Registrations',
                    data: weekData.data,
                    backgroundColor: '#0d6efd',
                    borderColor: '#0d6efd',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Registrations: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: Math.max(...weekData.data) * 1.2,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Initialize User Distribution Chart
        const distributionCtx = document.getElementById('userDistributionChart').getContext('2d');
        if (!distributionCtx) {
            console.error('Could not find userDistributionChart canvas element');
            return;
        }

        console.log('User Distribution Data:', userDistribution); // Debug log

        new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: userDistribution.labels,
                datasets: [{
                    data: userDistribution.data,
                    backgroundColor: [
                        '#0d6efd',  // Doctors - Blue
                        '#198754',  // Patients - Green
                        '#ffc107',  // Staff - Yellow
                        '#dc3545'   // Admins - Red
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
}

function updateActivityChart(period) {
    const periodData = chartData[period];
    
    activityChart.data.labels = periodData.labels;
    activityChart.data.datasets[0].data = periodData.data;
    activityChart.options.scales.y.max = periodData.max;
    activityChart.options.scales.y.ticks.stepSize = Math.ceil(periodData.max / 10);
    
    activityChart.update();
}
</script> 