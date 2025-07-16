// Schedule Management for Doctors
class ScheduleManager {
    constructor() {
        this.init();
    }

    init() {
        this.loadSchedules();
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Filter change events
        document.getElementById('filterClinic').addEventListener('change', () => this.loadSchedules());
        document.getElementById('filterDay').addEventListener('change', () => this.loadSchedules());

        // Form submission events
        document.getElementById('saveSchedule').addEventListener('click', () => this.saveSchedule());
        document.getElementById('updateSchedule').addEventListener('click', () => this.updateSchedule());

        // Listen for clinic updates
        window.addEventListener('clinicsUpdated', () => {
            this.refreshClinicOptions();
        });
    }

    async loadSchedules() {
        const clinicFilter = document.getElementById('filterClinic').value;
        const dayFilter = document.getElementById('filterDay').value;

        let url = '../../api/schedules.php';
        const params = new URLSearchParams();
        if (clinicFilter) params.append('clinic_id', clinicFilter);
        if (dayFilter) params.append('day', dayFilter);
        if (params.toString()) url += '?' + params.toString();

        try {
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.renderSchedules(data.schedules);
            } else {
                console.error('Failed to load schedules:', data.message);
            }
        } catch (error) {
            console.error('Error loading schedules:', error);
        }
    }

    renderSchedules(schedules) {
        const tbody = document.getElementById('scheduleTable');
        
        if (schedules.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        <span class="material-icons">schedule</span>
                        No schedules found. Create your first schedule to get started.
                    </td>
                </tr>
            `;
            return;
        }

        const schedulesHtml = schedules.map(schedule => `
            <tr>
                <td>${this.escapeHtml(schedule.clinic_name)}</td>
                <td>${this.getDayName(schedule.day_of_week)}</td>
                <td>${this.formatTime(schedule.start_time)}</td>
                <td>${this.formatTime(schedule.end_time)}</td>
                <td>
                    ${schedule.break_start && schedule.break_end ? 
                        `${this.formatTime(schedule.break_start)} - ${this.formatTime(schedule.break_end)}` : 
                        'No break'
                    }
                </td>
                <td>${schedule.duration_per_appointment || 'N/A'} min</td>
                <td>${schedule.max_appointments_per_slot || 'N/A'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="scheduleManager.editSchedule(${schedule.id})">
                        <span class="material-icons">edit</span>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="scheduleManager.deleteSchedule(${schedule.id})">
                        <span class="material-icons">delete</span>
                    </button>
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = schedulesHtml;
    }

    async saveSchedule() {
        const form = document.getElementById('addScheduleForm');
        const formData = new FormData(form);
        
        // Validate form
        if (!this.validateScheduleForm(formData)) {
            return;
        }

        try {
            const response = await fetch('../../api/schedules.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showAlert('success', data.message);
                form.reset();
                bootstrap.Modal.getInstance(document.getElementById('addScheduleModal')).hide();
                this.loadSchedules();
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error saving schedule:', error);
            this.showAlert('danger', 'Failed to save schedule. Please try again.');
        }
    }

    async updateSchedule() {
        const form = document.getElementById('editScheduleForm');
        const formData = new FormData(form);
        
        // Validate form
        if (!this.validateScheduleForm(formData)) {
            return;
        }

        const scheduleId = formData.get('schedule_id');
        formData.append('_method', 'PUT');

        try {
            const response = await fetch(`../../api/schedules.php?id=${scheduleId}`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showAlert('success', data.message);
                bootstrap.Modal.getInstance(document.getElementById('editScheduleModal')).hide();
                this.loadSchedules();
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error updating schedule:', error);
            this.showAlert('danger', 'Failed to update schedule. Please try again.');
        }
    }

    async editSchedule(scheduleId) {
        try {
            const response = await fetch(`../../api/schedules.php?id=${scheduleId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateEditForm(data.schedule);
                const modal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
                modal.show();
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error loading schedule for edit:', error);
            this.showAlert('danger', 'Failed to load schedule details.');
        }
    }

    async deleteSchedule(scheduleId) {
        if (!confirm('Are you sure you want to delete this schedule? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`../../api/schedules.php?id=${scheduleId}`, {
                method: 'DELETE'
            });

            const data = await response.json();
            
            if (data.success) {
                this.showAlert('success', data.message);
                this.loadSchedules();
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error deleting schedule:', error);
            this.showAlert('danger', 'Failed to delete schedule. Please try again.');
        }
    }

    populateEditForm(schedule) {
        const form = document.getElementById('editScheduleForm');
        form.querySelector('input[name="schedule_id"]').value = schedule.id;
        form.querySelector('select[name="clinic_id"]').value = schedule.clinic_id;
        form.querySelector('select[name="day_of_week"]').value = schedule.day_of_week;
        form.querySelector('input[name="start_time"]').value = schedule.start_time;
        form.querySelector('input[name="end_time"]').value = schedule.end_time;
        form.querySelector('input[name="break_start"]').value = schedule.break_start || '';
        form.querySelector('input[name="break_end"]').value = schedule.break_end || '';
        form.querySelector('input[name="duration_per_appointment"]').value = schedule.duration_per_appointment || '';
        form.querySelector('input[name="max_appointments_per_slot"]').value = schedule.max_appointments_per_slot || '';
    }

    validateScheduleForm(formData) {
        const requiredFields = ['clinic_id', 'day_of_week', 'start_time', 'end_time'];
        
        for (const field of requiredFields) {
            if (!formData.get(field)) {
                this.showAlert('danger', `Please fill in the ${field.replace('_', ' ')} field.`);
                return false;
            }
        }

        const startTime = formData.get('start_time');
        const endTime = formData.get('end_time');
        
        if (startTime >= endTime) {
            this.showAlert('danger', 'End time must be after start time.');
            return false;
        }

        return true;
    }

    async refreshClinicOptions() {
        try {
            const response = await fetch('../../api/doctor-clinics.php');
            const data = await response.json();
            
            if (data.success) {
                const assignedClinics = data.clinics.filter(clinic => clinic.is_assigned);
                this.updateClinicSelects(assignedClinics);
            }
        } catch (error) {
            console.error('Error refreshing clinic options:', error);
        }
    }

    updateClinicSelects(clinics) {
        // Update filter select
        const filterSelect = document.getElementById('filterClinic');
        const currentValue = filterSelect.value;
        
        filterSelect.innerHTML = '<option value="">All Clinics</option>';
        clinics.forEach(clinic => {
            const option = document.createElement('option');
            option.value = clinic.id;
            option.textContent = clinic.name;
            if (clinic.id == currentValue) option.selected = true;
            filterSelect.appendChild(option);
        });

        // Update form selects
        const formSelects = document.querySelectorAll('select[name="clinic_id"]');
        formSelects.forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '<option value="">Select Clinic</option>';
            clinics.forEach(clinic => {
                const option = document.createElement('option');
                option.value = clinic.id;
                option.textContent = clinic.name;
                if (clinic.id == currentValue) option.selected = true;
                select.appendChild(option);
            });
        });
    }

    getDayName(dayNumber) {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return days[dayNumber - 1];
    }

    formatTime(time) {
        return new Date(`2000-01-01T${time}`).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

    showAlert(type, message) {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Insert alert at the top of the main content
        const mainContent = document.querySelector('main');
        mainContent.insertBefore(alertDiv, mainContent.firstChild);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize the schedule manager when the page loads
let scheduleManager;
document.addEventListener('DOMContentLoaded', () => {
    scheduleManager = new ScheduleManager();
}); 