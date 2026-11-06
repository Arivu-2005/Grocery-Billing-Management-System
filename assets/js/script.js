/**
 * Grocery Billing Management System - Core JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Live Clock in Topbar
    const liveClockEl = document.getElementById('liveClock');
    if (liveClockEl) {
        function updateClock() {
            const now = new Date();
            const options = { 
                weekday: 'short', 
                day: 'numeric', 
                month: 'short', 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            liveClockEl.textContent = now.toLocaleString('en-US', options);
        }
        updateClock();
        setInterval(updateClock, 1000);
    }

    // 2. Mobile Sidebar Toggle
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const appSidebar = document.querySelector('.app-sidebar');
    if (sidebarToggleBtn && appSidebar) {
        sidebarToggleBtn.addEventListener('click', function () {
            appSidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (event) {
            if (window.innerWidth < 992) {
                if (!appSidebar.contains(event.target) && !sidebarToggleBtn.contains(event.target)) {
                    appSidebar.classList.remove('show');
                }
            }
        });
    }

    // 3. Generic Client-side Table Filter
    const tableSearchInputs = document.querySelectorAll('[data-table-search]');
    tableSearchInputs.forEach(input => {
        const targetTableId = input.getAttribute('data-table-search');
        const targetTable = document.getElementById(targetTableId);
        if (!targetTable) return;

        input.addEventListener('keyup', function () {
            const filterValue = this.value.toLowerCase().trim();
            const rows = targetTable.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(filterValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    // 4. Delete Confirmation Handler
    const deleteButtons = document.querySelectorAll('.btn-confirm-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            const itemName = this.getAttribute('data-item-name') || 'this item';
            if (!confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    });

    // 5. Auto dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 6000);
    });
});
