// Doctor Dashboard and Patient Management Functions

// Initialize patient list functionality
function initPatientList() {
    // Search functionality
    const searchInput = document.getElementById('searchPatient');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#patientListTable tr');
            
            rows.forEach(row => {
                const patientName = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                row.style.display = patientName.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    // Filter by condition
    const conditionFilter = document.getElementById('filterCondition');
    if (conditionFilter) {
        conditionFilter.addEventListener('change', function() {
            const selectedCondition = this.value.toLowerCase();
            const rows = document.querySelectorAll('#patientListTable tr');
            
            rows.forEach(row => {
                const conditions = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase() || '';
                row.style.display = !selectedCondition || conditions.includes(selectedCondition) ? '' : 'none';
            });
        });
    }

    // Filter by last visit
    const lastVisitFilter = document.getElementById('filterLastVisit');
    if (lastVisitFilter) {
        lastVisitFilter.addEventListener('change', function() {
            const days = parseInt(this.value) || 0;
            const rows = document.querySelectorAll('#patientListTable tr');
            const today = new Date();
            
            rows.forEach(row => {
                const lastVisitCell = row.querySelector('td:nth-child(4)');
                if (!lastVisitCell) return;
                
                const lastVisitText = lastVisitCell.textContent;
                if (lastVisitText === 'Never') {
                    row.style.display = days === 0 ? '' : 'none';
                    return;
                }

                const lastVisit = new Date(lastVisitText);
                const diffDays = Math.floor((today - lastVisit) / (1000 * 60 * 60 * 24));
                row.style.display = days === 0 || diffDays <= days ? '' : 'none';
            });
        });
    }

    // Sort functionality
    const sortSelect = document.getElementById('sortPatients');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const sortBy = this.value;
            const tbody = document.querySelector('#patientListTable');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                let aValue, bValue;
                
                switch(sortBy) {
                    case 'name':
                        aValue = a.querySelector('td:first-child')?.textContent || '';
                        bValue = b.querySelector('td:first-child')?.textContent || '';
                        break;
                    case 'lastVisit':
                        aValue = a.querySelector('td:nth-child(4)')?.textContent || 'Never';
                        bValue = b.querySelector('td:nth-child(4)')?.textContent || 'Never';
                        if (aValue === 'Never') aValue = '9999-12-31';
                        if (bValue === 'Never') bValue = '9999-12-31';
                        break;
                    case 'condition':
                        aValue = a.querySelector('td:nth-child(5)')?.textContent || '';
                        bValue = b.querySelector('td:nth-child(5)')?.textContent || '';
                        break;
                    default:
                        return 0;
                }
                
                return aValue.localeCompare(bValue);
            });
            
            rows.forEach(row => tbody.appendChild(row));
        });
    }

    // Export functionality
    const exportBtn = document.getElementById('exportPatients');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const table = document.querySelector('.table');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            // Convert table to CSV
            const csv = rows.map(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(cell => {
                    // Remove action buttons and get only text content
                    const text = cell.textContent.trim();
                    return `"${text}"`;
                }).join(',');
            }).join('\n');
            
            // Create and download CSV file
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'patient_list.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
    }

    // View patient details
    const viewButtons = document.querySelectorAll('.view-patient');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const patientId = this.dataset.patientId;
            window.location.href = `?page=patient-details&id=${patientId}`;
        });
    });

    // Schedule appointment
    const scheduleButtons = document.querySelectorAll('.schedule-appointment');
    scheduleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const patientId = this.dataset.patientId;
            const patientName = this.dataset.patientName;
            
            // Set patient name in modal
            document.getElementById('appointmentPatientName').value = patientName;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('scheduleAppointmentModal'));
            modal.show();
        });
    });

    // Save appointment
    const saveAppointmentBtn = document.getElementById('saveAppointment');
    if (saveAppointmentBtn) {
        saveAppointmentBtn.addEventListener('click', function() {
            const form = document.getElementById('scheduleAppointmentForm');
            const formData = new FormData(form);
            
            // Add patient ID to form data
            const patientId = document.querySelector('.schedule-appointment').dataset.patientId;
            formData.append('patient_id', patientId);
            
            // Send appointment data to server
            fetch('../../api/appointments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Appointment scheduled successfully!');
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('scheduleAppointmentModal')).hide();
                    // Refresh page to show new appointment
                    location.reload();
                } else {
                    alert(data.message || 'Error scheduling appointment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error scheduling appointment');
            });
        });
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initPatientList();
}); 