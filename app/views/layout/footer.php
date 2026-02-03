<?php
// compute base for script reference (same logic as header)
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base === '') {
    $base = '';
}
?>
    </main> <!-- END .content -->
</div> <!-- END .layout -->

<script src="<?= htmlspecialchars($base) ?>/assets/js/app.js"></script>
</body>
</html>
