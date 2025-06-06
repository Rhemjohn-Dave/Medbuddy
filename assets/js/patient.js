// Function to load available time slots when doctor, clinic, or date changes
function updateTimeSlots() {
    const doctorId = document.querySelector('select[name="doctor_id"]').value;
    const clinicId = document.querySelector('select[name="clinic_id"]').value;
    const date = document.querySelector('input[name="date"]').value;
    const timeSelect = document.querySelector('select[name="time"]');
    
    // Clear current time slots
    timeSelect.innerHTML = '<option value="">Select time...</option>';
    
    if (doctorId && clinicId && date) {
        // Add loading state
        timeSelect.disabled = true;
        
        // Show loading state with SweetAlert
        Swal.fire({
            title: 'Loading available slots...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Fetch available time slots
        fetch(`/Medbuddy/api/available-slots.php?doctor_id=${doctorId}&clinic_id=${clinicId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                // Close loading alert
                Swal.close();
                
                timeSelect.disabled = false;
                timeSelect.innerHTML = '<option value="">Select time...</option>';
                
                if (!data.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load time slots',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
                
                if (!data.slots || data.slots.length === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'No Available Slots',
                        text: 'Please try a different date or doctor',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
                
                // Add available slots to the dropdown
                data.slots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot;
                    const time = new Date('1970-01-01T' + slot);
                    option.textContent = time.toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                    timeSelect.appendChild(option);
                });

                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Time Slots Available',
                    text: `${data.slots.length} slots found for your selected date`,
                    timer: 2000,
                    showConfirmButton: false
                });
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load time slots. Please try again.',
                    confirmButtonColor: '#3085d6'
                });
            });
    }
}

// Function to confirm appointment cancellation
function confirmCancel(appointmentId) {
    Swal.fire({
        title: 'Cancel Appointment',
        text: 'Are you sure you want to cancel this appointment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, cancel it!',
        cancelButtonText: 'No, keep it'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Cancelling appointment...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Submit the form
            document.querySelector(`form[data-appointment-id="${appointmentId}"]`).submit();
        }
    });
}

// Add event listeners when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for doctor, clinic, and date changes
    const doctorSelect = document.querySelector('select[name="doctor_id"]');
    const clinicSelect = document.querySelector('select[name="clinic_id"]');
    const dateInput = document.querySelector('input[name="date"]');
    const timeSelect = document.querySelector('select[name="time"]');
    
    if (doctorSelect) doctorSelect.addEventListener('change', updateTimeSlots);
    if (clinicSelect) clinicSelect.addEventListener('change', updateTimeSlots);
    if (dateInput) dateInput.addEventListener('change', updateTimeSlots);
    
    // Handle time slot selection
    if (timeSelect) {
        timeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                // Store the selected time
                const selectedTime = selectedOption.value;
                const selectedTimeText = selectedOption.textContent;
                
                // Check if this time slot is already selected
                const existingSelectedTime = this.querySelector('option[selected]');
                if (existingSelectedTime && existingSelectedTime.value === selectedTime) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Time Already Selected',
                        text: 'This time slot is already selected.',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                // Remove all options except the first one
                while (this.options.length > 1) {
                    this.remove(1);
                }
                
                // Add the selected time as the first option
                const newOption = document.createElement('option');
                newOption.value = selectedTime;
                newOption.textContent = selectedTimeText;
                newOption.selected = true;
                this.insertBefore(newOption, this.firstChild);
                
                // Select the first option
                this.selectedIndex = 0;
                
                // Show confirmation
                Swal.fire({
                    icon: 'success',
                    title: 'Time Slot Selected',
                    text: `You have selected ${selectedTimeText}`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }

    // Handle form submission
    const appointmentForm = document.getElementById('appointmentForm');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Validate form
            const doctorId = formData.get('doctor_id');
            const clinicId = formData.get('clinic_id');
            const date = formData.get('date');
            const time = formData.get('time');
            const purpose = formData.get('purpose');

            if (!doctorId || !clinicId || !date || !time || !purpose) {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Check if time slot is still available
            fetch(`/Medbuddy/api/available-slots.php?doctor_id=${doctorId}&clinic_id=${clinicId}&date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.slots || !data.slots.includes(time)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Time Slot Unavailable',
                            text: 'The selected time slot is no longer available. Please select another time.',
                            confirmButtonColor: '#3085d6'
                        }).then(() => {
                            // Clear the time selection and refresh slots
                            timeSelect.innerHTML = '<option value="">Select time...</option>';
                            updateTimeSlots();
                        });
                        return;
                    }

                    // Show loading state
                    Swal.fire({
                        title: 'Scheduling appointment...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const appointmentData = {
                        doctor_id: doctorId,
                        clinic_id: clinicId,
                        date: date,
                        time: time,
                        purpose: purpose,
                        notes: formData.get('notes')
                    };

                    // Submit the appointment
                    return fetch('/Medbuddy/api/appointments.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(appointmentData)
                    });
                })
                .then(response => {
                    if (!response) return; // Skip if the previous promise was rejected
                    return response.json();
                })
                .then(data => {
                    if (!data) return; // Skip if the previous promise was rejected
                    
                    // Close loading alert
                    Swal.close();
                    
                    if (data.success === true && data.appointment) {
                        // Format the appointment time for display
                        const appointmentTime = new Date('1970-01-01T' + data.appointment.time).toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                        
                        // Format the appointment date
                        const appointmentDate = new Date(data.appointment.date).toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });

                        // Show success message with SweetAlert
                        Swal.fire({
                            icon: 'success',
                            title: 'Appointment Scheduled Successfully!',
                            html: `
                                <div class="text-left p-4">
                                    <div class="mb-4">
                                        <div class="appointment-details bg-light p-3 rounded">
                                            <p class="mb-3"><i class="fas fa-calendar-alt text-primary me-2"></i><strong>Date:</strong> ${appointmentDate}</p>
                                            <p class="mb-3"><i class="fas fa-clock text-primary me-2"></i><strong>Time:</strong> ${appointmentTime}</p>
                                            <p class="mb-3"><i class="fas fa-user-md text-primary me-2"></i><strong>Doctor:</strong> ${data.appointment.doctor_name}</p>
                                            <p class="mb-3"><i class="fas fa-hospital text-primary me-2"></i><strong>Clinic:</strong> ${data.appointment.clinic_name}</p>
                                            <p class="mb-3"><i class="fas fa-sticky-note text-primary me-2"></i><strong>Purpose:</strong> ${data.appointment.purpose}</p>
                                            ${data.appointment.notes ? `<p class="mb-3"><i class="fas fa-comment text-primary me-2"></i><strong>Notes:</strong> ${data.appointment.notes}</p>` : ''}
                                        </div>
                                    </div>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Please arrive 10 minutes before your scheduled time.
                                    </div>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'View My Appointments',
                            cancelButtonText: 'Close',
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            timer: null,
                            customClass: {
                                container: 'appointment-success-modal',
                                popup: 'appointment-success-popup',
                                title: 'appointment-success-title',
                                htmlContainer: 'appointment-success-content',
                                confirmButton: 'btn btn-success',
                                cancelButton: 'btn btn-secondary'
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = '/Medbuddy/pages/patient/pages/appointments.php';
                            } else {
                                // Reset form and clear time slots
                                this.reset();
                                timeSelect.innerHTML = '<option value="">Select time...</option>';
                                // Refresh available slots
                                updateTimeSlots();
                            }
                        });
                    } else {
                        // Handle error response
                        let errorMessage = 'Failed to schedule appointment';
                        if (data.message) {
                            errorMessage = data.message;
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: errorMessage,
                            confirmButtonColor: '#3085d6'
                        }).then(() => {
                            // Clear time slots and refresh
                            timeSelect.innerHTML = '<option value="">Select time...</option>';
                            updateTimeSlots();
                        });
                    }
                })
                .catch(error => {
                    // Close loading alert on error
                    Swal.close();
                    
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Failed to schedule appointment. Please try again later.',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        // Clear time slots and refresh
                        timeSelect.innerHTML = '<option value="">Select time...</option>';
                        updateTimeSlots();
                    });
                });
        });
    }

    // Show success/error messages if they exist
    const urlParams = new URLSearchParams(window.location.search);
    const successMessage = urlParams.get('success');
    const errorMessage = urlParams.get('error');

    if (successMessage) {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: successMessage,
            timer: 3000,
            showConfirmButton: false
        });
    }

    if (errorMessage) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: errorMessage,
            confirmButtonColor: '#3085d6'
        });
    }
}); 