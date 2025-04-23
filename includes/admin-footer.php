</div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Admin JS -->
    <script src="<?= SITE_URL ?>/assets/js/admin.js"></script>
    
    <?php if (isset($extraJS)): ?>
        <?= $extraJS ?>
    <?php endif; ?>
    <!-- Modal Backdrop Fix -->
<script>
$(document).ready(function() {
    // Fix for modal backdrop not being removed
    $(document).on('hidden.bs.modal', '.modal', function() {
        // Remove all modal backdrops
        $('.modal-backdrop').remove();
        // Remove modal-open class from body
        $('body').removeClass('modal-open');
        // Reset body style
        $('body').css({
            'padding-right': '',
            'overflow': ''
        });
    });
    
    // Alternative fix for stubborn modals
    $(document).on('click', '[data-dismiss="modal"]', function() {
        setTimeout(function() {
            if ($('.modal-backdrop').length > 0) {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                $('body').css({
                    'padding-right': '',
                    'overflow': ''
                });
            }
        }, 200);
    });
});
</script>
</body>
</html>