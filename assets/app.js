/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const toggle = document.getElementById('mobile-menu-toggle');
    const closeBtn = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    const toggleSidebar = (show) => {
        if (show) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }
    };

    if (toggle && sidebar && overlay) {
        toggle.addEventListener('click', () => toggleSidebar(true));
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => toggleSidebar(false));
        }

        overlay.addEventListener('click', () => toggleSidebar(false));
    }

    // Theme Toggle Logic
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const isDark = document.documentElement.classList.contains('dark');
            const newTheme = isDark ? 'light' : 'dark';
            
            // Toggle local class
            if (newTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            
            // Save to localStorage
            localStorage.setItem('theme', newTheme);
            
            // Update database setting via AJAX
            fetch('/settings/toggle-theme-db', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ theme: newTheme })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to sync theme to DB:', data.message);
                }
            })
            .catch(err => console.error('Error syncing theme to DB:', err));
        });
    }
});
