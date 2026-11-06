<?php
/**
 * Common Admin Footer Layout
 * Grocery Billing Management System
 */
?>
        </main>
        <!-- Page Content Ends -->

        <!-- Common Admin Footer -->
        <footer class="py-3 px-4 bg-white border-top text-center text-md-start d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <div>
                &copy; <?= date('Y') ?> <strong><?= e(STORE_NAME) ?></strong> &bull; All Rights Reserved.
            </div>
            <div class="mt-2 mt-md-0">
                <span>Core PHP POS &bull; PDO &bull; MySQL &bull; Bootstrap 5</span>
            </div>
        </footer>

    </div>
    <!-- App Main Ends -->

</div>
<!-- App Wrapper Ends -->

<!-- Bootstrap 5.3 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom App Script -->
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>

</body>
</html>
