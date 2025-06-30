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
    // Handle form submission and other unrelated logic can remain here if needed
}); 