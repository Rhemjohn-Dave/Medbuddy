// Password visibility toggle functionality
function setupPasswordVisibility() {
    // Find all password input fields
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    
    passwordInputs.forEach(input => {
        // Avoid double-wrapping if already inside an input-group or has a toggle
        if (input.closest('.input-group') || input.dataset.hasToggle) return;
        if (input.parentNode.classList.contains('position-relative')) return;

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
        toggleButton.tabIndex = -1;
        toggleButton.setAttribute('aria-label', 'Show/Hide Password');
        toggleButton.innerHTML = '<i class="bi bi-eye"></i>';
        
        // Add button to wrapper
        wrapper.appendChild(toggleButton);
        input.dataset.hasToggle = '1';
        
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

// Initialize password visibility toggle when DOM is loaded and on modal show
function initializePasswordToggles() {
    setupPasswordVisibility();
    // For Bootstrap modals: re-run when a modal is shown
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', setupPasswordVisibility);
    });
}

document.addEventListener('DOMContentLoaded', initializePasswordToggles);

// Common JavaScript functionality for MedBuddy

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Handle sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    if (sidebar && content) {
        sidebar.classList.toggle('active');
        content.classList.toggle('active');
    }
}

// Handle notifications
function markNotificationAsRead(notificationId) {
    fetch(`../../api/notifications/mark-read.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ notification_id: notificationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update notification count
            const badge = document.querySelector('#notificationsDropdown .badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent);
                if (currentCount > 1) {
                    badge.textContent = currentCount - 1;
                } else {
                    badge.remove();
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Handle user profile dropdown
document.addEventListener('click', function(event) {
    const userDropdown = document.getElementById('userDropdown');
    if (userDropdown) {
        const dropdownMenu = userDropdown.nextElementSibling;
        if (dropdownMenu && !userDropdown.contains(event.target) && !dropdownMenu.contains(event.target)) {
            dropdownMenu.classList.remove('show');
        }
    }
});

// Handle responsive behavior
function handleResponsive() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    
    if (sidebar && content) {
        if (window.innerWidth < 768) {
            sidebar.classList.add('active');
            content.classList.add('active');
        } else {
            sidebar.classList.remove('active');
            content.classList.remove('active');
        }
    }
}

// Add event listener for window resize
window.addEventListener('resize', handleResponsive);

// Initialize responsive behavior on page load
document.addEventListener('DOMContentLoaded', handleResponsive);

// Handle logout
document.addEventListener('DOMContentLoaded', function() {
    const logoutLink = document.querySelector('a[href*="logout.php"]');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = this.href;
            }
        });
    }
});

// Handle active menu items
function setActiveMenuItem() {
    const currentPage = window.location.pathname.split('/').pop().replace('.php', '');
    const menuItems = document.querySelectorAll('.nav-link');
    
    menuItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// Initialize active menu items on page load
document.addEventListener('DOMContentLoaded', setActiveMenuItem); 