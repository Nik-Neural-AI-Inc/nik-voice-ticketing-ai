/**
 * Nik VoiceDesk AI - Admin JavaScript
 * Handles Dark/Light mode switching, persistence via localStorage, and clipboard copying.
 */
(function() {
    'use strict';

    // Auto-detect theme preference or load saved theme
    function getPreferredTheme() {
        const saved = localStorage.getItem('nikvotia_admin_theme');
        if (saved === 'dark' || saved === 'light') {
            return saved;
        }
        // Auto-detect dark mode from OS or active admin theme
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        if (document.body && (
            document.body.classList.contains('dark') ||
            document.body.classList.contains('dark-mode') ||
            document.body.classList.contains('wp-dark-mode-active') ||
            document.documentElement.getAttribute('data-theme') === 'dark' ||
            document.documentElement.getAttribute('data-bs-theme') === 'dark'
        )) {
            return 'dark';
        }
        return 'light';
    }

    // Apply theme immediately to prevent flashing
    const initialTheme = getPreferredTheme();
    if (initialTheme === 'dark') {
        document.body.classList.add('nik-vd-dark-mode');
    }

    document.addEventListener('DOMContentLoaded', function() {
        initThemeToggle();
        initCopyButtons();
    });

    /**
     * Initialize Dark / Light Mode Switcher
     */
    function initThemeToggle() {
        const themeButtons = document.querySelectorAll('.nik-vd-theme-btn, #nik-vd-theme-toggle');
        const currentTheme = getPreferredTheme();

        // Set initial button label and icon based on current state
        updateThemeButtons(themeButtons, currentTheme === 'dark');

        themeButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const isDark = document.body.classList.toggle('nik-vd-dark-mode');
                const newTheme = isDark ? 'dark' : 'light';
                localStorage.setItem('nikvotia_admin_theme', newTheme);
                updateThemeButtons(themeButtons, isDark);
            });
        });
    }

    /**
     * Update all theme toggle buttons on the page
     */
    function updateThemeButtons(buttons, isDark) {
        buttons.forEach(btn => {
            const icon = btn.querySelector('.nik-vd-theme-icon');
            const text = btn.querySelector('.nik-vd-theme-text');
            if (isDark) {
                if (icon) icon.textContent = '☀️';
                if (text) text.textContent = 'Light Mode';
                btn.setAttribute('aria-label', 'Switch to Light Mode');
                btn.title = 'Switch to Light Mode';
            } else {
                if (icon) icon.textContent = '🌙';
                if (text) text.textContent = 'Dark Mode';
                btn.setAttribute('aria-label', 'Switch to Dark Mode');
                btn.title = 'Switch to Dark Mode';
            }
        });
    }

    /**
     * One-click Ticket ID copy
     */
    function initCopyButtons() {
        const copyBtns = document.querySelectorAll('.nik-vd-copy-btn');
        copyBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const textToCopy = this.getAttribute('data-copy');
                if (!textToCopy) return;

                navigator.clipboard.writeText(textToCopy).then(() => {
                    const origHtml = this.innerHTML;
                    this.innerHTML = '<span class="dashicons dashicons-yes" style="color: #22c55e;"></span>';
                    setTimeout(() => {
                        this.innerHTML = origHtml;
                    }, 1500);
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                });
            });
        });
    }
})();
