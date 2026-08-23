<?php
/**
 * Layout Footer Component
 */
?>
    </main>

    <!-- Bottom Footer -->
    <footer class="app-footer">
        <div class="footer-left">
            <span>&copy; <?= date('Y') + 543 ?> <strong><?= APP_NAME ?></strong> - โครงงานระดับ ปวส.</span>
        </div>
        <div class="footer-right text-muted small d-none d-sm-block">
            <span><?= APP_SUBTITLE ?> | Version <?= APP_VERSION ?></span>
        </div>
    </footer>
</div><!-- End .app-main -->

</div><!-- End .app-wrapper -->

<!-- Bootstrap 5.3.3 Bundle JS with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom App JS -->
<script src="<?= BASE_URL ?>assets/js/main.js"></script>

<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>

</body>
</html>
