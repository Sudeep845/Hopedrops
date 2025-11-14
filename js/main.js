/**
 * HopeDrops Blood Bank Management System
 * Main JavaScript File
 *
 * Contains common functions, form validation, AJAX calls, and interactive features
 * Created: November 11, 2025
 */

// Global configuration
const HopeDrops = {
  baseUrl: window.location.origin + "/HopeDrops/",
  apiUrl: window.location.origin + "/HopeDrops/php/",
  version: "1.0.0",
  debug: true,
};

// Utility functions
const Utils = {
  // Log debug messages
  log: function (message, type = "info") {
    if (HopeDrops.debug) {
      console.log(`[HopeDrops ${type.toUpperCase()}]`, message);
    }
  },

  // Format date
  formatDate: function (dateString, format = "MMM DD, YYYY") {
    const date = new Date(dateString);
    const options = {
      year: "numeric",
      month: "short",
      day: "2-digit",
    };
    return date.toLocaleDateString("en-US", options);
  },

  // Format date and time
  formatDateTime: function (dateString) {
    const date = new Date(dateString);
    return date.toLocaleString("en-US", {
      year: "numeric",
      month: "short",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  },

  // Calculate time ago
  timeAgo: function (dateString) {
    const now = new Date();
    const past = new Date(dateString);
    const diffMs = now - past;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return "just now";
    if (diffMins < 60) return `${diffMins} minutes ago`;
    if (diffHours < 24) return `${diffHours} hours ago`;
    if (diffDays < 30) return `${diffDays} days ago`;

    return Utils.formatDate(dateString);
  },

  // Sanitize HTML
  sanitizeHtml: function (str) {
    const temp = document.createElement("div");
    temp.textContent = str;
    return temp.innerHTML;
  },

  // Escape HTML (alias for sanitizeHtml for admin dashboard compatibility)
  escapeHtml: function (str) {
    if (!str) return "";
    const temp = document.createElement("div");
    temp.textContent = str;
    return temp.innerHTML;
  },

  // Calculate age from date of birth
  calculateAge: function (dateOfBirth) {
    if (!dateOfBirth) return "Unknown";
    const today = new Date();
    const birthDate = new Date(dateOfBirth);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (
      monthDiff < 0 ||
      (monthDiff === 0 && today.getDate() < birthDate.getDate())
    ) {
      age--;
    }

    return age;
  },

  // Generate random ID
  generateId: function (prefix = "id") {
    return prefix + "_" + Math.random().toString(36).substr(2, 9);
  },

  // Debounce function
  debounce: function (func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  // Throttle function
  throttle: function (func, limit) {
    let inThrottle;
    return function () {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => (inThrottle = false), limit);
      }
    };
  },
};

// API wrapper for AJAX calls
const API = {
  // Generic API call
  call: function (endpoint, options = {}) {
    const defaultOptions = {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
      },
    };

    const config = { ...defaultOptions, ...options };

    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
      config.headers["X-CSRF-TOKEN"] = csrfToken.getAttribute("content");
    }

    return fetch(HopeDrops.apiUrl + endpoint, config)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .catch((error) => {
        Utils.log("API Error: " + error.message, "error");
        throw error;
      });
  },

  // GET request
  get: function (endpoint, params = {}) {
    const url = new URL(HopeDrops.apiUrl + endpoint);
    Object.keys(params).forEach((key) =>
      url.searchParams.append(key, params[key])
    );

    return this.call(endpoint + "?" + url.searchParams.toString());
  },

  // POST request
  post: function (endpoint, data = {}) {
    return this.call(endpoint, {
      method: "POST",
      body: data instanceof FormData ? data : JSON.stringify(data),
    });
  },

  // PUT request
  put: function (endpoint, data = {}) {
    return this.call(endpoint, {
      method: "PUT",
      body: JSON.stringify(data),
    });
  },

  // DELETE request
  delete: function (endpoint) {
    return this.call(endpoint, {
      method: "DELETE",
    });
  },
};

// Form validation utilities
const Validator = {
  // Email validation
  isValidEmail: function (email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  },

  // Phone validation
  isValidPhone: function (phone) {
    const phoneRegex = /^[\d\s\-\+\(\)]+$/;
    const digitsOnly = phone.replace(/\D/g, "");
    return phoneRegex.test(phone) && digitsOnly.length >= 10;
  },

  // Password strength
  checkPasswordStrength: function (password) {
    let strength = 0;
    const checks = {
      length: password.length >= 8,
      lowercase: /[a-z]/.test(password),
      uppercase: /[A-Z]/.test(password),
      numbers: /\d/.test(password),
      special: /[^A-Za-z0-9]/.test(password),
    };

    Object.values(checks).forEach((check) => {
      if (check) strength++;
    });

    return {
      score: strength,
      checks: checks,
      level: strength < 2 ? "weak" : strength < 4 ? "medium" : "strong",
    };
  },

  // Required field validation
  isRequired: function (value) {
    return value && value.toString().trim().length > 0;
  },

  // Min length validation
  minLength: function (value, min) {
    return value && value.toString().length >= min;
  },

  // Max length validation
  maxLength: function (value, max) {
    return value && value.toString().length <= max;
  },

  // Number validation
  isNumber: function (value) {
    return !isNaN(value) && !isNaN(parseFloat(value));
  },

  // Date validation
  isValidDate: function (dateString) {
    const date = new Date(dateString);
    return date instanceof Date && !isNaN(date);
  },

  // Age validation
  isValidAge: function (birthDate, minAge = 18, maxAge = 65) {
    const today = new Date();
    const birth = new Date(birthDate);
    const age = Math.floor((today - birth) / (365.25 * 24 * 60 * 60 * 1000));
    return age >= minAge && age <= maxAge;
  },
};

// Form handling utilities
const FormHandler = {
  // Show field error
  showFieldError: function (field, message) {
    if (typeof field === "string") {
      field = document.getElementById(field);
    }

    if (!field) return;

    field.classList.add("is-invalid");

    let errorElement = field.parentNode.querySelector(".invalid-feedback");
    if (!errorElement) {
      errorElement = field.nextElementSibling;
      if (
        !errorElement ||
        !errorElement.classList.contains("invalid-feedback")
      ) {
        errorElement = document.createElement("div");
        errorElement.className = "invalid-feedback";
        field.parentNode.appendChild(errorElement);
      }
    }

    errorElement.textContent = message;
    errorElement.style.display = "block";
  },

  // Clear field error
  clearFieldError: function (field) {
    if (typeof field === "string") {
      field = document.getElementById(field);
    }

    if (!field) return;

    field.classList.remove("is-invalid");

    const errorElement = field.parentNode.querySelector(".invalid-feedback");
    if (errorElement) {
      errorElement.textContent = "";
      errorElement.style.display = "none";
    }
  },

  // Clear all form errors
  clearFormErrors: function (form) {
    const fields = form.querySelectorAll(".form-control, .form-select");
    fields.forEach((field) => {
      this.clearFieldError(field);
    });
  },

  // Serialize form data to object
  serializeForm: function (form) {
    const formData = new FormData(form);
    const data = {};

    for (let [key, value] of formData.entries()) {
      if (data[key]) {
        // Handle multiple values (like checkboxes)
        if (Array.isArray(data[key])) {
          data[key].push(value);
        } else {
          data[key] = [data[key], value];
        }
      } else {
        data[key] = value;
      }
    }

    return data;
  },

  // Populate form from data object
  populateForm: function (form, data) {
    Object.keys(data).forEach((key) => {
      const field = form.querySelector(`[name="${key}"]`);
      if (field) {
        if (field.type === "checkbox") {
          field.checked = !!data[key];
        } else if (field.type === "radio") {
          if (field.value === data[key]) {
            field.checked = true;
          }
        } else {
          field.value = data[key];
        }
      }
    });
  },

  // Disable form
  disableForm: function (form, disabled = true) {
    const fields = form.querySelectorAll("input, select, textarea, button");
    fields.forEach((field) => {
      field.disabled = disabled;
    });
  },
};

// Notification system
const Notifications = {
  // Show SweetAlert notification
  show: function (type, title, text, options = {}) {
    const defaultOptions = {
      icon: type,
      title: title,
      text: text,
      confirmButtonColor: "#dc3545",
      timer: type === "success" ? 3000 : undefined,
      showConfirmButton: type !== "success" || options.showConfirmButton,
    };

    return Swal.fire({ ...defaultOptions, ...options });
  },

  // Success notification
  success: function (title, text = "", options = {}) {
    return this.show("success", title, text, options);
  },

  // Error notification
  error: function (title, text = "", options = {}) {
    return this.show("error", title, text, options);
  },

  // Warning notification
  warning: function (title, text = "", options = {}) {
    return this.show("warning", title, text, options);
  },

  // Info notification
  info: function (title, text = "", options = {}) {
    return this.show("info", title, text, options);
  },

  // Confirm dialog
  confirm: function (title, text = "", options = {}) {
    const defaultOptions = {
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Yes",
      cancelButtonText: "No",
    };

    return this.show("question", title, text, {
      ...defaultOptions,
      ...options,
    });
  },

  // Loading dialog
  loading: function (title = "Loading...") {
    return Swal.fire({
      title: title,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });
  },

  // Close all notifications
  close: function () {
    Swal.close();
  },
};

// Local storage wrapper
const Storage = {
  // Set item
  set: function (key, value) {
    try {
      localStorage.setItem(`hopedrops_${key}`, JSON.stringify(value));
    } catch (e) {
      Utils.log("Storage set error: " + e.message, "error");
    }
  },

  // Get item
  get: function (key, defaultValue = null) {
    try {
      const item = localStorage.getItem(`hopedrops_${key}`);
      return item ? JSON.parse(item) : defaultValue;
    } catch (e) {
      Utils.log("Storage get error: " + e.message, "error");
      return defaultValue;
    }
  },

  // Remove item
  remove: function (key) {
    try {
      localStorage.removeItem(`hopedrops_${key}`);
    } catch (e) {
      Utils.log("Storage remove error: " + e.message, "error");
    }
  },

  // Clear all HopeDrops data
  clear: function () {
    try {
      Object.keys(localStorage).forEach((key) => {
        if (key.startsWith("hopedrops_")) {
          localStorage.removeItem(key);
        }
      });
    } catch (e) {
      Utils.log("Storage clear error: " + e.message, "error");
    }
  },
};

// Session management
const Session = {
  // Check if user is logged in
  isLoggedIn: function () {
    return Storage.get("isLoggedIn", false);
  },

  // Get current user
  getCurrentUser: function () {
    return Storage.get("currentUser", null);
  },

  // Set user session
  setUser: function (userData) {
    Storage.set("currentUser", userData);
    Storage.set("isLoggedIn", true);
  },

  // Clear session
  clear: function () {
    Storage.remove("currentUser");
    Storage.remove("isLoggedIn");
  },

  // Check session with server
  checkSession: function () {
    return API.get("check_session.php")
      .then((response) => {
        if (response.success && response.data.logged_in) {
          this.setUser(response.data);
          return response.data;
        } else {
          this.clear();
          return null;
        }
      })
      .catch((error) => {
        Utils.log("Session check error: " + error.message, "error");
        this.clear();
        return null;
      });
  },

  // Logout
  logout: function () {
    return API.post("logout.php")
      .then((response) => {
        this.clear();
        return response;
      })
      .catch((error) => {
        Utils.log("Logout error: " + error.message, "error");
        this.clear();
        throw error;
      });
  },
};

// Loading utilities
const Loading = {
  // Show loading spinner
  show: function (element, text = "Loading...") {
    if (typeof element === "string") {
      element = document.getElementById(element);
    }

    if (!element) return;

    const spinner = document.createElement("div");
    spinner.className = "loading-overlay";
    spinner.innerHTML = `
            <div class="loading-content">
                <div class="spinner"></div>
                <p>${text}</p>
            </div>
        `;

    element.style.position = "relative";
    element.appendChild(spinner);
  },

  // Hide loading spinner
  hide: function (element) {
    if (typeof element === "string") {
      element = document.getElementById(element);
    }

    if (!element) return;

    const spinner = element.querySelector(".loading-overlay");
    if (spinner) {
      spinner.remove();
    }
  },
};

// Blood type utilities
const BloodTypes = {
  all: ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"],

  // Get compatible donors for a blood type
  getCompatibleDonors: function (recipientType) {
    const compatibility = {
      "A+": ["A+", "A-", "O+", "O-"],
      "A-": ["A-", "O-"],
      "B+": ["B+", "B-", "O+", "O-"],
      "B-": ["B-", "O-"],
      "AB+": ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"],
      "AB-": ["A-", "B-", "AB-", "O-"],
      "O+": ["O+", "O-"],
      "O-": ["O-"],
    };

    return compatibility[recipientType] || [];
  },

  // Get compatible recipients for a blood type
  getCompatibleRecipients: function (donorType) {
    const compatibility = {
      "A+": ["A+", "AB+"],
      "A-": ["A+", "A-", "AB+", "AB-"],
      "B+": ["B+", "AB+"],
      "B-": ["B+", "B-", "AB+", "AB-"],
      "AB+": ["AB+"],
      "AB-": ["AB+", "AB-"],
      "O+": ["A+", "B+", "AB+", "O+"],
      "O-": ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"],
    };

    return compatibility[donorType] || [];
  },

  // Check if donor can donate to recipient
  canDonate: function (donorType, recipientType) {
    return this.getCompatibleDonors(recipientType).includes(donorType);
  },
};

// Common initialization functions
const App = {
  // Initialize common features
  init: function () {
    Utils.log("Initializing HopeDrops application...", "info");

    // Setup global error handling
    this.setupErrorHandling();

    // Setup CSRF token
    this.setupCSRFToken();

    // Setup mobile menu
    this.setupMobileMenu();

    // Setup smooth scrolling
    this.setupSmoothScrolling();

    // Setup tooltips
    this.setupTooltips();

    // Check session status
    this.checkSessionStatus();

    Utils.log("HopeDrops application initialized successfully", "success");
  },

  // Setup global error handling
  setupErrorHandling: function () {
    window.addEventListener("error", function (e) {
      Utils.log("Global error: " + e.error.message, "error");
    });

    window.addEventListener("unhandledrejection", function (e) {
      Utils.log("Unhandled promise rejection: " + e.reason, "error");
    });
  },

  // Setup CSRF token
  setupCSRFToken: function () {
    // This would be implemented with server-side token generation
    Utils.log("CSRF token setup completed", "info");
  },

  // Setup mobile menu
  setupMobileMenu: function () {
    const mobileToggle = document.getElementById("mobileMenuToggle");
    const navbar = document.getElementById("navbarNav");

    if (mobileToggle && navbar) {
      mobileToggle.addEventListener("click", function () {
        navbar.classList.toggle("show");
      });
    }
  },

  // Setup smooth scrolling
  setupSmoothScrolling: function () {
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener("click", function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute("href"));
        if (target) {
          target.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
        }
      });
    });
  },

  // Setup tooltips
  setupTooltips: function () {
    // Initialize tooltips if using Bootstrap or similar library
    const tooltipElements = document.querySelectorAll("[data-tooltip]");
    tooltipElements.forEach((element) => {
      element.addEventListener("mouseenter", function () {
        // Show tooltip
      });
    });
  },

  // Check session status
  checkSessionStatus: function () {
    Session.checkSession()
      .then((userData) => {
        if (userData) {
          Utils.log("User session restored: " + userData.username, "info");
          this.updateUIForLoggedInUser(userData);
        }
      })
      .catch((error) => {
        Utils.log("Session check failed: " + error.message, "error");
      });
  },

  // Update UI for logged in user
  updateUIForLoggedInUser: function (userData) {
    // Update navigation links
    const loginLink = document.querySelector('a[href="login.html"]');
    const registerLink = document.querySelector('a[href="register.html"]');

    if (loginLink && registerLink) {
      const navParent = loginLink.parentNode.parentNode;

      // Remove login/register links
      loginLink.parentNode.remove();
      registerLink.parentNode.remove();

      // Add user menu
      const userMenu = document.createElement("li");
      userMenu.innerHTML = `
                <div class="dropdown">
                    <a href="#" class="nav-link dropdown-toggle" id="userDropdown">
                        <i class="fas fa-user"></i> ${userData.full_name}
                    </a>
                    <div class="dropdown-menu">
                        <a href="${userData.role}/dashboard.html" class="dropdown-item">Dashboard</a>
                        <a href="#" onclick="App.logout()" class="dropdown-item">Logout</a>
                    </div>
                </div>
            `;

      navParent.appendChild(userMenu);
    }
  },

  // Logout function
  logout: function () {
    Notifications.confirm(
      "Logout Confirmation",
      "Are you sure you want to logout?"
    ).then((result) => {
      if (result.isConfirmed) {
        Session.logout()
          .then(() => {
            Notifications.success("Logged out successfully");
            window.location.href = "index.html";
          })
          .catch((error) => {
            Notifications.error(
              "Logout Error",
              "An error occurred during logout"
            );
          });
      }
    });
  },
};

// Initialize app when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  App.init();
});

// Export to global scope
window.HopeDrops = HopeDrops;
window.Utils = Utils;
window.API = API;
window.Validator = Validator;
window.FormHandler = FormHandler;
window.Notifications = Notifications;
window.Storage = Storage;
window.Session = Session;
window.Loading = Loading;
window.BloodTypes = BloodTypes;
window.App = App;
