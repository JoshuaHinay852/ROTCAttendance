<?php
// dashboard_footer.php
// This file contains the dashboard footer with JavaScript functions
?>
    
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            if (sidebar.classList.contains('collapsed')) {
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-20');
                document.querySelector('.sidebar i.fa-chevron-left').classList.remove('fa-chevron-left');
                document.querySelector('.sidebar i.fa-chevron-left').classList.add('fa-chevron-right');
            } else {
                mainContent.classList.remove('ml-20');
                mainContent.classList.add('ml-64');
                document.querySelector('.sidebar i.fa-chevron-right').classList.remove('fa-chevron-right');
                document.querySelector('.sidebar i.fa-chevron-right').classList.add('fa-chevron-left');
            }
        }
        
        // Toggle Notifications Dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('hidden');
            
            // Close other dropdowns
            document.getElementById('profileDropdown').classList.add('hidden');
        }
        
        // Toggle Profile Dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('hidden');
            
            // Close other dropdowns
            document.getElementById('notificationsDropdown').classList.add('hidden');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            
            if (!event.target.closest('.relative')) {
                notificationsDropdown.classList.add('hidden');
                profileDropdown.classList.add('hidden');
            }
        });
        
        <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
        // Auto-refresh dashboard every 60 seconds (only on dashboard)
        setTimeout(() => {
            window.location.reload();
        }, 60000);
        <?php endif; ?>
    </script>
</body>
</html>