// Doctor Clinic Assignment Management
class DoctorClinicManager {
    constructor() {
        this.init();
    }

    init() {
        this.loadAssignedClinics();
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Load clinics when assign clinic modal is opened
        const assignModal = document.getElementById('assignClinicModal');
        if (assignModal) {
            assignModal.addEventListener('show.bs.modal', () => {
                console.log('Modal opening - loading clinics...');
                this.loadAllClinics();
            });

            // Refresh assigned clinics list when modal is closed
            assignModal.addEventListener('hidden.bs.modal', () => {
                console.log('Modal closing - refreshing assigned clinics...');
                this.loadAssignedClinics();
            });
        } else {
            console.error('Assign clinic modal not found');
        }
    }

    async loadAssignedClinics() {
        try {
            console.log('Loading assigned clinics...');
            // Try different API paths
            let response;
            const paths = [
                '/Medbuddy/api/doctor-clinics.php',
                '../../api/doctor-clinics.php',
                '../../../api/doctor-clinics.php'
            ];
            for (const path of paths) {
                try {
                    response = await fetch(path);
                    console.log(`Trying path ${path}, response:`, response.status);
                    if (response.ok) {
                        break; // Found working path
                    }
                } catch (e) {
                    console.log(`Path ${path} failed:`, e);
                    continue;
                }
            }
            if (!response || !response.ok) {
                throw new Error('All API paths failed');
            }
            const data = await response.json();
            console.log('API response data:', data);
            if (data.success) {
                const assignedClinics = data.clinics.filter(clinic => clinic.is_assigned);
                console.log('Assigned clinics:', assignedClinics);
                this.renderAssignedClinics(assignedClinics);
            } else {
                console.error('Failed to load assigned clinics:', data.message);
                this.showAlert('danger', 'Failed to load assigned clinics: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading assigned clinics:', error);
            this.showAlert('danger', 'Error loading assigned clinics: ' + error.message);
        }
    }

    async loadAllClinics() {
        try {
            console.log('Loading all clinics...');
            // Try different API paths
            let response;
            const paths = [
                '/Medbuddy/api/doctor-clinics.php',
                '../../api/doctor-clinics.php',
                '../../../api/doctor-clinics.php'
            ];
            for (const path of paths) {
                try {
                    response = await fetch(path);
                    console.log(`Trying path ${path}, response:`, response.status);
                    if (response.ok) {
                        break; // Found working path
                    }
                } catch (e) {
                    console.log(`Path ${path} failed:`, e);
                    continue;
                }
            }
            if (!response || !response.ok) {
                throw new Error('All API paths failed');
            }
            const data = await response.json();
            console.log('API response data:', data);
            if (data.success) {
                const availableClinics = data.clinics.filter(clinic => !clinic.is_assigned);
                const assignedClinics = data.clinics.filter(clinic => clinic.is_assigned);
                console.log('Available clinics:', availableClinics);
                console.log('Assigned clinics:', assignedClinics);
                this.renderAvailableClinics(availableClinics);
                this.renderModalAssignedClinics(assignedClinics);
            } else {
                console.error('Failed to load clinics:', data.message);
                this.showAlert('danger', 'Failed to load clinics: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading clinics:', error);
            this.showAlert('danger', 'Error loading clinics: ' + error.message);
        }
    }

    renderAssignedClinics(clinics) {
        const container = document.getElementById('assignedClinicsList');
        
        if (clinics.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <span class="material-icons">info</span>
                    You haven't been assigned to any clinics yet. Click "Assign Clinic" to get started.
                </div>
            `;
            return;
        }

        const clinicsHtml = clinics.map(clinic => `
            <div class="card mb-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">${this.escapeHtml(clinic.name)}</h6>
                            <p class="card-text text-muted mb-1">
                                <span class="material-icons" style="font-size: 14px;">location_on</span>
                                ${this.escapeHtml(clinic.address)}
                            </p>
                            ${clinic.phone ? `
                                <p class="card-text text-muted mb-1">
                                    <span class="material-icons" style="font-size: 14px;">phone</span>
                                    ${this.escapeHtml(clinic.phone)}
                                </p>
                            ` : ''}
                            ${clinic.email ? `
                                <p class="card-text text-muted mb-0">
                                    <span class="material-icons" style="font-size: 14px;">email</span>
                                    ${this.escapeHtml(clinic.email)}
                                </p>
                            ` : ''}
                        </div>
                        <button class="btn btn-outline-danger btn-sm" onclick="doctorClinicManager.removeClinicAssignment(${clinic.id})">
                            <span class="material-icons">remove_circle</span>
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = clinicsHtml;
    }

    renderAvailableClinics(clinics) {
        const container = document.getElementById('availableClinicsList');
        
        if (clinics.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <span class="material-icons">info</span>
                    No available clinics to assign.
                </div>
            `;
            return;
        }

        const clinicsHtml = clinics.map(clinic => `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${this.escapeHtml(clinic.name)}</h6>
                        <p class="mb-1 text-muted">${this.escapeHtml(clinic.address)}</p>
                        ${clinic.phone ? `<small class="text-muted">${this.escapeHtml(clinic.phone)}</small>` : ''}
                    </div>
                    <button class="btn btn-success btn-sm" onclick="doctorClinicManager.assignClinic(${clinic.id})">
                        <span class="material-icons">add</span>
                        Assign
                    </button>
                </div>
            </div>
        `).join('');

        container.innerHTML = clinicsHtml;
    }

    renderModalAssignedClinics(clinics) {
        const container = document.getElementById('modalAssignedClinicsList');
        
        if (clinics.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <span class="material-icons">info</span>
                    No clinics assigned yet.
                </div>
            `;
            return;
        }

        const clinicsHtml = clinics.map(clinic => `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${this.escapeHtml(clinic.name)}</h6>
                        <p class="mb-1 text-muted">${this.escapeHtml(clinic.address)}</p>
                        ${clinic.phone ? `<small class="text-muted">${this.escapeHtml(clinic.phone)}</small>` : ''}
                    </div>
                    <button class="btn btn-danger btn-sm" onclick="doctorClinicManager.removeClinicAssignment(${clinic.id})">
                        <span class="material-icons">remove</span>
                        Remove
                    </button>
                </div>
            </div>
        `).join('');

        container.innerHTML = clinicsHtml;
    }

    async assignClinic(clinicId) {
        try {
            // Try different API paths
            let response;
            const paths = [
                '/Medbuddy/api/doctor-clinics.php',
                '../../api/doctor-clinics.php',
                '../../../api/doctor-clinics.php'
            ];
            for (const path of paths) {
                try {
                    response = await fetch(path, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ clinic_id: clinicId })
                    });
                    if (response.ok) {
                        break; // Found working path
                    }
                } catch (e) {
                    console.log(`Path ${path} failed:`, e);
                    continue;
                }
            }
            if (!response || !response.ok) {
                throw new Error('All API paths failed');
            }
            const data = await response.json();
            if (data.success) {
                this.showAlert('success', data.message);
                this.loadAllClinics();
                this.loadAssignedClinics();
                this.refreshScheduleClinicOptions();
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error assigning clinic:', error);
            this.showAlert('danger', 'Failed to assign clinic. Please try again.');
        }
    }

    async removeClinicAssignment(clinicId) {
        if (!confirm('Are you sure you want to remove this clinic assignment? This action cannot be undone.')) {
            return;
        }
        try {
            // Try different API paths
            let response;
            const paths = [
                '/Medbuddy/api/doctor-clinics.php',
                '../../api/doctor-clinics.php',
                '../../../api/doctor-clinics.php'
            ];
            for (const path of paths) {
                try {
                    response = await fetch(path, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ clinic_id: clinicId })
                    });
                    if (response.ok) {
                        break; // Found working path
                    }
                } catch (e) {
                    console.log(`Path ${path} failed:`, e);
                    continue;
                }
            }
            if (!response || !response.ok) {
                throw new Error('All API paths failed');
            }
            const data = await response.json();
            if (data.success) {
                this.showAlert('success', data.message);
                this.loadAllClinics();
                this.loadAssignedClinics();
                this.refreshScheduleClinicOptions();
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error removing clinic assignment:', error);
            this.showAlert('danger', 'Failed to remove clinic assignment. Please try again.');
        }
    }

    refreshScheduleClinicOptions() {
        // Refresh the clinic options in the schedule forms
        this.loadAssignedClinics().then(() => {
            // Trigger a custom event to notify schedule.js to refresh its clinic options
            window.dispatchEvent(new CustomEvent('clinicsUpdated'));
            
            // Also refresh the page to update the PHP-rendered clinic options
            setTimeout(() => {
                window.location.reload();
            }, 1000);
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

        // Try to find a good place to insert the alert
        let targetElement = document.querySelector('main');
        if (!targetElement) {
            targetElement = document.querySelector('.container-fluid');
        }
        if (!targetElement) {
            targetElement = document.querySelector('.container');
        }
        if (!targetElement) {
            targetElement = document.body;
        }

        // Insert alert at the top of the target element
        if (targetElement.firstChild) {
            targetElement.insertBefore(alertDiv, targetElement.firstChild);
        } else {
            targetElement.appendChild(alertDiv);
        }

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

// Initialize the clinic manager when the page loads
let doctorClinicManager;
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing DoctorClinicManager...');
    doctorClinicManager = new DoctorClinicManager();
    
    // Test API call after a short delay
    setTimeout(() => {
        console.log('Testing API call...');
        doctorClinicManager.loadAssignedClinics();
    }, 1000);
}); 