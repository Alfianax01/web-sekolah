<?php
// includes/footer.php - Reusable Footer & Script Inclusions
?>
    <!-- ===== SCRIPTS UTAMA ===== -->
    <script src="assets/js/translations.js"></script>
    <script src="assets/js/main.js"></script>
    <?php if (!empty($extra_js) && is_array($extra_js)): ?>
        <?php foreach ($extra_js as $jsFile): ?>
            <script src="<?php echo htmlspecialchars($jsFile); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
