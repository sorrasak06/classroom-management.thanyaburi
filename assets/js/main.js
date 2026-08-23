/**
 * Classroom Management System - Main JavaScript
 * Interactive UI, Mobile Navigation, Table Filtering, Print & Export Helpers
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Mobile Sidebar Toggle & Backdrop
    const sidebar = document.querySelector('.app-sidebar');
    const sidebarToggler = document.querySelector('.topbar-toggler');
    const sidebarBackdrop = document.querySelector('.sidebar-backdrop');
    
    if (sidebarToggler && sidebar) {
        sidebarToggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.toggle('show');
            }
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
        });
    }

    // 2. Show/Hide Password Toggle
    const togglePasswordBtns = document.querySelectorAll('.toggle-password-btn');
    togglePasswordBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetInputId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetInputId);
            const icon = this.querySelector('i');
            
            if (targetInput) {
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    if (icon) {
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                } else {
                    targetInput.type = 'password';
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                }
            }
        });
    });

    // 3. Quick Table Search Filter
    const searchInputs = document.querySelectorAll('.table-search-input');
    searchInputs.forEach(function(input) {
        const targetTableId = input.getAttribute('data-target-table');
        const targetTable = document.getElementById(targetTableId);
        
        if (targetTable) {
            input.addEventListener('keyup', function() {
                const filterValue = this.value.toLowerCase().trim();
                const rows = targetTable.querySelectorAll('tbody tr');
                
                rows.forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(filterValue)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });

    // 4. Auto Dismiss Alerts after 6 seconds
    const autoDismissAlerts = document.querySelectorAll('.alert-dismissible');
    autoDismissAlerts.forEach(function(alertEl) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });

    // 5. Attendance Batch Setter (e.g. Set all students to Present)
    window.setAllAttendanceStatus = function(status) {
        const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
        radios.forEach(function(radio) {
            radio.checked = true;
            // Highlight active container if present
            const container = radio.closest('.attendance-status-group');
            if (container) {
                container.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
                const parentLabel = radio.closest('label');
                if (parentLabel) parentLabel.classList.add('active');
            }
        });
    };

    // 6. Print Trigger Helper
    window.printReport = function() {
        window.print();
    };

    // 7. Export HTML Table to CSV (Excel Compatible with UTF-8 BOM)
    window.exportTableToCSV = function(tableId, filename = 'report.csv') {
        const table = document.getElementById(tableId);
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll('tr');

        for (let i = 0; i < rows.length; i++) {
            let row = [];
            const cols = rows[i].querySelectorAll('td, th');

            for (let j = 0; j < cols.length; j++) {
                // Ignore action columns if marked with no-export
                if (cols[j].classList.contains('no-export')) continue;
                
                let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""').trim();
                row.push('"' + text + '"');
            }
            if (row.length > 0) {
                csv.push(row.join(','));
            }
        }

        // Add UTF-8 BOM (\uFEFF) so Thai characters open correctly in Excel
        const csvContent = '\uFEFF' + csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // 8. Delete Confirmation Helper
    window.confirmDelete = function(url, message = 'คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้? การกระทำนี้ไม่สามารถย้อนกลับได้') {
        if (confirm(message)) {
            window.location.href = url;
        }
    };
});
