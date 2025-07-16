// Password visibility toggle functionality
function setupPasswordVisibility() {
    // Find all password input fields
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    
    passwordInputs.forEach(input => {
        // Create a wrapper div for the input and toggle button
        const wrapper = document.createElement('div');
        wrapper.className = 'position-relative';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        // Create toggle button
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'btn btn-link position-absolute end-0 top-50 translate-middle-y';
        toggleButton.style.textDecoration = 'none';
        toggleButton.innerHTML = '<i class="bi bi-eye"></i>';
        
        // Add button to wrapper
        wrapper.appendChild(toggleButton);
        
        // Add click event listener
        toggleButton.addEventListener('click', () => {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            // Toggle eye icon
            toggleButton.innerHTML = type === 'password' ? 
                '<i class="bi bi-eye"></i>' : 
                '<i class="bi bi-eye-slash"></i>';
        });
    });
}

// Initialize password visibility toggle when DOM is loaded
document.addEventListener('DOMContentLoaded', setupPasswordVisibility); 