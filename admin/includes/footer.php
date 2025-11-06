            </main>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function updateSidebarState() {
            if (window.innerWidth <= 768) {
                if (sidebar && sidebar.classList.contains('active')) {
                    if (sidebarOverlay) {
                        sidebarOverlay.style.display = 'block';
                    }
                } else {
                    if (sidebarOverlay) {
                        sidebarOverlay.style.display = 'none';
                    }
                }
            }
        }
        
        menuToggle?.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.toggle('active');
                updateSidebarState();
            }
        });

        // Close sidebar when clicking overlay on mobile
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                if (sidebar) {
                    sidebar.classList.remove('active');
                    updateSidebarState();
                }
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                sidebar &&
                !sidebar.contains(e.target) && 
                menuToggle &&
                e.target !== menuToggle &&
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                updateSidebarState();
            }
        });
        
        // Update on resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar) {
                sidebar.classList.remove('active');
                updateSidebarState();
            }
        });

        // Confirmation dialogs
        document.querySelectorAll('.confirm-delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>

