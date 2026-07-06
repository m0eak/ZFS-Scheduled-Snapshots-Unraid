            </section>
        </main>
    </div>
    <script src="<?php echo htmlspecialchars(zss_asset_url('assets/js/next.js')); ?>"></script>
    <?php if (!empty($nextPageScript)): ?>
        <script src="<?php echo htmlspecialchars(zss_asset_url($nextPageScript)); ?>"></script>
    <?php endif; ?>
</body>
</html>
