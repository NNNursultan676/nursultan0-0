// Student Dark Notebook - JavaScript

document.addEventListener('DOMContentLoaded', function() {
 
  // Mobile Menu Toggle
  const menuToggle = document.getElementById('menuToggle');
  const closeMenu = document.getElementById('closeMenu');
  const sidebar = document.getElementById('sidebar');
  const menuOverlay = document.getElementById('menuOverlay');
  
  if (menuToggle) {
    menuToggle.addEventListener('click', function() {
      sidebar.classList.add('active');
      menuOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    });
  }
  
  if (closeMenu) {
    closeMenu.addEventListener('click', closeMenuFunc);
  }
  
  if (menuOverlay) {
    menuOverlay.addEventListener('click', closeMenuFunc);
  }
  
  function closeMenuFunc() {
    sidebar.classList.remove('active');
    menuOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }
  
  // Auto-hide success/error messages
  const messages = document.querySelectorAll('.success-message, .error-message');
  messages.forEach(function(message) {
    setTimeout(function() {
      message.style.transition = 'opacity 0.5s ease';
      message.style.opacity = '0';
      setTimeout(function() {
        message.remove();
      }, 500);
    }, 5000);
  });
  
  // Form validation - only visual feedback
  const forms = document.querySelectorAll('form');
  forms.forEach(function(form) {
    const requiredInputs = form.querySelectorAll('[required]');
    
    requiredInputs.forEach(function(input) {
      input.addEventListener('blur', function() {
        if (!this.value.trim()) {
          this.style.borderColor = 'var(--accent-danger)';
        } else {
          this.style.borderColor = '';
        }
      });
      
      input.addEventListener('input', function() {
        if (this.value.trim()) {
          this.style.borderColor = '';
        }
      });
    });
  });
  
  // Smooth scrolling for anchor links
  const anchorLinks = document.querySelectorAll('a[href^="#"]');
  anchorLinks.forEach(function(link) {
    link.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (href && href.length > 1 && href !== '#' && !href.startsWith('#!')) {
        try {
          const target = document.querySelector(href);
          if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
          }
        } catch (err) {
          // Ignore invalid selectors like plain '#'
        }
      }
    });
  });
      
  // Keyboard navigation
  document.addEventListener('keydown', function(e) {
    // ESC to close menu
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
      closeMenuFunc();
    }
  });
  
  // Add loading state to buttons
  const submitButtons = document.querySelectorAll('button[type="submit"]');
  submitButtons.forEach(function(button) {
    const form = button.closest('form');
    if (form) {
      form.addEventListener('submit', function(e) {
        // Only show loading if form is valid
        if (this.checkValidity()) {
          button.disabled = true;
          button.textContent = 'Загрузка...';
          // Don't prevent default - let form submit
        }
      });
    }
  });
  
});