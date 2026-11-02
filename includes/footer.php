
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <strong>Campus Event Hub</strong>
            <p class="mb-0">
                A campus space for discovering activities and getting involved.
            </p>
        </div>

        <div class="footer-info">
            <span>
                &copy; <?= date('Y') ?> Campus Event Hub
            </span>
            <span>Integrative Programming and Technologies</span>
        </div>
    </div>
</footer>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?= e(rtrim($basePath ?? app_url(), '/') . '/') ?>assets/js/script.js"></script>

<!-- Campus Event Assistant Chatbot -->
<?php require_once __DIR__ . '/chatbot-widget.php'; ?>
<?php
$chatbotWidget = __DIR__ . '/chatbot-widget.php';

if (is_file($chatbotWidget)) {
    require_once $chatbotWidget;
} else {
    error_log('Missing chatbot widget: ' . $chatbotWidget);
}
?>
</body>
</html>
