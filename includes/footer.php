<?php
// includes/footer.php
?>
    </main>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Handle form submissions with loading states
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
                    submitBtn.disabled = true;
                }
            });
        });
        
        // Handle sidebar on mobile
        function handleMobileSidebar() {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 992) {
                sidebar.classList.add('collapsed');
            }
        }
        
        // Initialize on load and resize
        window.addEventListener('load', handleMobileSidebar);
        window.addEventListener('resize', handleMobileSidebar);
    </script>
</body>
</html>